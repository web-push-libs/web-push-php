<?php declare(strict_types=1);
/*
 * This file is part of the WebPush library.
 *
 * (c) Louis Lagrange <lagrange.louis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Minishlink\WebPush;

use Base64Url\Base64Url;
use Http\Client\Exception\HttpException;
use Http\Client\HttpAsyncClient;
use Http\Discovery\HttpAsyncClientDiscovery;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

class WebPush
{
    protected ClientInterface $client;
    protected RequestFactoryInterface $requestFactory;
    protected StreamFactoryInterface $streamFactory;
    protected ?HttpAsyncClient $asyncClient;
    protected array $auth;
    protected ?LoggerInterface $logger;

    /**
     * @var null|array Array of array of Notifications
     */
    protected ?array $notifications = null;

    /**
     * @var array Default options: TTL, urgency, topic, batchSize, requestConcurrency
     */
    protected array $defaultOptions;

    /**
     * @var int Automatic padding of payloads, if disabled, trade security for bandwidth
     */
    protected int $automaticPadding = Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH;

    /**
     * @var bool Reuse VAPID headers in the same flush session to improve performance
     */
    protected bool $reuseVAPIDHeaders = false;

    /**
     * @var array Dictionary for VAPID headers cache
     */
    protected array $vapidHeaders = [];

    /**
     * WebPush constructor.
     *
     * @param array           $auth           Some servers need authentication
     * @param array           $defaultOptions TTL, urgency, topic, batchSize, requestConcurrency
     * @param ClientInterface|null $client    PSR-18 HTTP client. Defaults to an auto-discovered client (e.g. Guzzle, if installed). Configure timeouts/proxies/redirects directly on this client instance.
     * @param RequestFactoryInterface|null $requestFactory PSR-17 request factory. Defaults to an auto-discovered factory.
     * @param StreamFactoryInterface|null $streamFactory PSR-17 stream factory. Defaults to an auto-discovered factory.
     * @param HttpAsyncClient|null $asyncClient Optional HTTPlug async client, required for concurrent sending via flushPooled(). Defaults to an auto-discovered async client, if any is installed.
     * @param LoggerInterface|null $logger    Optional PSR-3 logger; if provided, replaces trigger_error() calls
     *
     * @throws \ErrorException
     */
    public function __construct(
        array $auth = [],
        array $defaultOptions = [],
        ?ClientInterface $client = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?HttpAsyncClient $asyncClient = null,
        ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger;

        Utils::checkRequirement($this->logger);

        if (isset($auth['VAPID'])) {
            $auth['VAPID'] = VAPID::validate($auth['VAPID']);
        }

        $this->auth = $auth;

        $this->setDefaultOptions($defaultOptions);

        $this->client = $client ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();

        if ($asyncClient) {
            $this->asyncClient = $asyncClient;
        } else {
            try {
                $this->asyncClient = HttpAsyncClientDiscovery::find();
            } catch (\Throwable) {
                $this->asyncClient = null;
            }
        }
    }

    /**
     * Queue a notification. Will be sent when flush() is called.
     *
     * @param string|null $payload If you want to send an array or object, json_encode it
     * @param array $options Array with several options tied to this notification. If not set, will use the default options that you can set in the WebPush object
     * @param array $auth Use this auth details instead of what you provided when creating WebPush
     * @throws \ErrorException
     */
    public function queueNotification(SubscriptionInterface $subscription, ?string $payload = null, array $options = [], array $auth = []): void
    {
        if (isset($payload)) {
            if (Utils::safeStrlen($payload) > Encryption::MAX_PAYLOAD_LENGTH) {
                throw new \ErrorException('Size of payload must not be greater than '.Encryption::MAX_PAYLOAD_LENGTH.' octets.');
            }

            $contentEncoding = $subscription->getContentEncoding();
            if (!$contentEncoding) {
                throw new \ErrorException('Subscription should have a content encoding');
            }

            $payload = Encryption::padPayload($payload, $this->automaticPadding, ContentEncoding::from($contentEncoding));
        }

        if (array_key_exists('VAPID', $auth)) {
            $auth['VAPID'] = VAPID::validate($auth['VAPID']);
        }

        $this->notifications[] = new Notification($subscription, $payload, $options, $auth);
    }

    /**
     * @param string|null $payload If you want to send an array or object, json_encode it
     * @param array $options Array with several options tied to this notification. If not set, will use the default options that you can set in the WebPush object
     * @param array $auth Use this auth details instead of what you provided when creating WebPush
     * @throws \ErrorException
     */
    public function sendOneNotification(SubscriptionInterface $subscription, ?string $payload = null, array $options = [], array $auth = []): MessageSentReport
    {
        $this->queueNotification($subscription, $payload, $options, $auth);
        return $this->flush()->current();
    }

    /**
     * Flush notifications. Triggers the requests.
     *
     * @param null|int $batchSize Defaults the value defined in defaultOptions during instantiation (which defaults to 1000).
     *
     * @return \Generator
     * @throws \ErrorException
     * @throws \Random\RandomException
     */
    public function flush(?int $batchSize = null): \Generator
    {
        if (empty($this->notifications)) {
            yield from [];
            return;
        }

        if (null === $batchSize) {
            $batchSize = $this->defaultOptions['batchSize'];
        }

        $batches = array_chunk($this->notifications, $batchSize);

        // reset queue
        $this->notifications = [];

        foreach ($batches as $batch) {
            // for each endpoint server type
            $requests = $this->prepare($batch);

            foreach ($requests as $request) {
                try {
                    $response = $this->client->sendRequest($request);
                    yield new MessageSentReport($request, $response);
                } catch (ClientExceptionInterface $reason) {
                    yield $this->createRejectedReport($request, $reason);
                }
            }
        }

        if ($this->reuseVAPIDHeaders) {
            $this->vapidHeaders = [];
        }
    }

    /**
     * Flush notifications. Triggers concurrent requests.
     *
     * Requires an HTTPlug async client (e.g. via `php-http/guzzle7-adapter`), injected in the
     * constructor or auto-discovered. See the "Customizing the HTTP client" section of the README.
     *
     * @param callable(MessageSentReport): void $callback Callback for each notification
     * @param null|int $batchSize Defaults the value defined in defaultOptions during instantiation (which defaults to 1000).
     * @param null|int $requestConcurrency Unused. Concurrency is now controlled by the underlying async client's own configuration.
     *
     * @throws \LogicException If no HTTPlug async client is available
     */
    public function flushPooled(callable $callback, ?int $batchSize = null, ?int $requestConcurrency = null): void
    {
        if (empty($this->notifications)) {
            return;
        }

        if (!$this->asyncClient) {
            throw new \LogicException('flushPooled() requires an HTTPlug async client for concurrent sending. Install one, e.g. "composer require php-http/guzzle7-adapter", or use flush() for sequential sending.');
        }

        if (null === $batchSize) {
            $batchSize = $this->defaultOptions['batchSize'];
        }

        $batches = array_chunk($this->notifications, $batchSize);
        $this->notifications = [];

        foreach ($batches as $batch) {
            $requests = $this->prepare($batch);

            $promises = [];
            foreach ($requests as $request) {
                $promises[] = $this->asyncClient->sendAsyncRequest($request)
                    ->then(
                        function (ResponseInterface $response) use ($callback, $request): void {
                            $callback(new MessageSentReport($request, $response));
                        },
                        function (\Throwable $reason) use ($callback, $request): void {
                            $callback($this->createRejectedReport($request, $reason));
                        }
                    );
            }

            foreach ($promises as $promise) {
                $promise->wait();
            }
        }

        if ($this->reuseVAPIDHeaders) {
            $this->vapidHeaders = [];
        }
    }

    protected function createRejectedReport(RequestInterface $request, \Throwable $reason): MessageSentReport
    {
        $response = $reason instanceof HttpException ? $reason->getResponse() : null;

        return new MessageSentReport($request, $response, false, $reason->getMessage());
    }

    /**
     * @throws \ErrorException Thrown on php 8.1
     * @throws \Random\RandomException Thrown on php 8.2 and higher
     */
    protected function prepare(array $notifications): array
    {
        $requests = [];
        foreach ($notifications as $notification) {
            \assert($notification instanceof Notification);
            $subscription = $notification->getSubscription();
            $endpoint = $subscription->getEndpoint();
            $userPublicKey = $subscription->getPublicKey();
            $userAuthToken = $subscription->getAuthToken();
            $contentEncoding = $subscription->getContentEncoding();
            $payload = $notification->getPayload();
            $options = $notification->getOptions($this->getDefaultOptions());
            $auth = $notification->getAuth($this->auth);

            if (!empty($payload) && !empty($userPublicKey) && !empty($userAuthToken)) {
                if (!$contentEncoding) {
                    throw new \ErrorException('Subscription should have a content encoding');
                }

                $encrypted = Encryption::encrypt($payload, $userPublicKey, $userAuthToken, ContentEncoding::from($contentEncoding));
                $cipherText = $encrypted['cipherText'];
                $salt = $encrypted['salt'];
                $localPublicKey = $encrypted['localPublicKey'];

                $headers = [
                    'Content-Type' => $options['contentType'],
                    'Content-Encoding' => $contentEncoding,
                ];

                if ($contentEncoding === ContentEncoding::aesgcm->value) {
                    $headers['Encryption'] = 'salt='.Base64Url::encode($salt);
                    $headers['Crypto-Key'] = 'dh='.Base64Url::encode($localPublicKey);
                }

                $encryptionContentCodingHeader = Encryption::getContentCodingHeader($salt, $localPublicKey, ContentEncoding::from($contentEncoding));
                $content = $encryptionContentCodingHeader.$cipherText;

                $headers['Content-Length'] = (string) Utils::safeStrlen($content);
            } else {
                $headers = [
                    'Content-Length' => '0',
                ];

                $content = '';
            }

            $headers['TTL'] = (string) $options['TTL'];

            if (isset($options['urgency'])) {
                $headers['Urgency'] = $options['urgency'];
            }

            if (isset($options['topic'])) {
                $headers['Topic'] = $options['topic'];
            }

            if (array_key_exists('VAPID', $auth) && $contentEncoding) {
                $audience = parse_url($endpoint, PHP_URL_SCHEME).'://'.parse_url($endpoint, PHP_URL_HOST);
                if (!parse_url($audience)) {
                    throw new \ErrorException('Audience "'.$audience.'"" could not be generated.');
                }

                $vapidHeaders = $this->getVAPIDHeaders($audience, ContentEncoding::from($contentEncoding), $auth['VAPID']);

                $headers['Authorization'] = $vapidHeaders['Authorization'];

                if ($contentEncoding === ContentEncoding::aesgcm->value) {
                    if (array_key_exists('Crypto-Key', $headers)) {
                        $headers['Crypto-Key'] .= ';'.$vapidHeaders['Crypto-Key'];
                    } else {
                        $headers['Crypto-Key'] = $vapidHeaders['Crypto-Key'];
                    }
                }
            }

            $request = $this->requestFactory->createRequest('POST', $endpoint);
            foreach ($headers as $name => $value) {
                $request = $request->withHeader($name, $value);
            }
            $requests[] = $request->withBody($this->streamFactory->createStream($content));
        }

        return $requests;
    }

    public function isAutomaticPadding(): bool
    {
        return $this->automaticPadding !== 0;
    }

    public function getAutomaticPadding(): int
    {
        return $this->automaticPadding;
    }

    /**
     * @param bool|int $automaticPadding Max padding length
     *
     * @throws \ValueError
     */
    public function setAutomaticPadding(bool|int $automaticPadding): WebPush
    {
        if ($automaticPadding === true) {
            $automaticPadding = Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH;
        } elseif ($automaticPadding === false) {
            $automaticPadding = 0;
        }

        if ($automaticPadding > Encryption::MAX_PAYLOAD_LENGTH) {
            throw new \ValueError('Automatic padding is too large. Max is '.Encryption::MAX_PAYLOAD_LENGTH.'. Recommended max is '.Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH.' for compatibility reasons (see README).');
        }
        if ($automaticPadding < 0) {
            throw new \ValueError('Padding length should be positive or zero.');
        }

        $this->automaticPadding = $automaticPadding;

        return $this;
    }

    public function getReuseVAPIDHeaders(): bool
    {
        return $this->reuseVAPIDHeaders;
    }

    /**
     * Reuse VAPID headers in the same flush session to improve performance
     */
    public function setReuseVAPIDHeaders(bool $enabled): WebPush
    {
        $this->reuseVAPIDHeaders = $enabled;

        return $this;
    }

    public function getDefaultOptions(): array
    {
        return $this->defaultOptions;
    }

    /**
     * @param array $defaultOptions Keys 'TTL' (Time To Live, defaults 4 weeks), 'urgency', 'topic', 'batchSize', 'requestConcurrency'
     */
    public function setDefaultOptions(array $defaultOptions): WebPush
    {
        $this->defaultOptions['TTL'] = $defaultOptions['TTL'] ?? 2419200;
        $this->defaultOptions['urgency'] = $defaultOptions['urgency'] ?? null;
        $this->defaultOptions['topic'] = $defaultOptions['topic'] ?? null;
        $this->defaultOptions['batchSize'] = $defaultOptions['batchSize'] ?? 1000;
        $this->defaultOptions['requestConcurrency'] = $defaultOptions['requestConcurrency'] ?? 100;
        $this->defaultOptions['contentType'] = $defaultOptions['contentType'] ?? 'application/octet-stream';


        return $this;
    }

    public function countPendingNotifications(): int
    {
        return null !== $this->notifications ? count($this->notifications) : 0;
    }

    /**
     * @throws \ErrorException
     */
    protected function getVAPIDHeaders(string $audience, ContentEncoding $contentEncoding, array $vapid): ?array
    {
        $vapidHeaders = null;

        $cache_key = null;
        if ($this->reuseVAPIDHeaders) {
            $cache_key = implode('#', [$audience, $contentEncoding->value, crc32(serialize($vapid))]);
            if (array_key_exists($cache_key, $this->vapidHeaders)) {
                $vapidHeaders = $this->vapidHeaders[$cache_key];
            }
        }

        if (!$vapidHeaders) {
            $vapidHeaders = VAPID::getVapidHeaders($audience, $vapid['subject'], $vapid['publicKey'], $vapid['privateKey'], $contentEncoding);
        }

        if ($this->reuseVAPIDHeaders) {
            $this->vapidHeaders[$cache_key] = $vapidHeaders;
        }

        return $vapidHeaders;
    }
}
