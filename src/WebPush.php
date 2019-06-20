<?php

declare(strict_types = 1);

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
use ErrorException;
use Exception;
use Generator;
use GuzzleHttp\Psr7\Request;

class WebPush
{
    /**
     * @var Client
     */
    private $client;
    /**
     * @var array
     */
    private $auth;
    /**
     * @var null|array Array of array of Notifications
     */
    private $notifications;
    /**
     * @var Options
     */
    private $options;
    /**
     * @var int Automatic padding of payloads, if disabled, trade security for bandwidth
     */
    private $automaticPadding = Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH;
    /**
     * @var bool Reuse VAPID headers in the same flush session to improve performance
     */
    private $reuseVAPIDHeaders = false;
    /**
     * @var array Dictionary for VAPID headers cache
     */
    private $vapidHeaders = [];

    /**
     * @param array $auth Some servers needs authentication
     * @param Options|null $options
     * @param Client|null $client
     *
     * @throws ErrorException
     */
    public function __construct(array $auth = [], ?Options $options = null, ?Client $client = null)
    {
        if (ini_get('mbstring.func_overload') >= 2) {
            trigger_error('[WebPush] mbstring.func_overload is enabled for str* functions. You must disable it if you want to send push notifications with payload or use VAPID. You can fix this in your php.ini.');
        }

        if (isset($auth['VAPID'])) {
            $auth['VAPID'] = VAPID::validate($auth['VAPID']);
        }

        $this->auth = $auth;

        $this->options = Options::wrap($options);

        $this->client = $client ?? new Client();
    }

    /**
     * Send a notification.
     *
     * @param Subscription $subscription
     * @param string|null $payload If you want to send an array, json_encode it
     * @param bool $flush If you want to flush directly (usually when you send only one notification)
     * @param Options|array $options Options to use for this notification. If not set, the default options
     *         set while instantiating the Webpush object will be used.
     * @param array $auth Use this auth details instead of what you provided when creating WebPush
     *
     * @return Generator|MessageSentReport[]|true Return an array of information if $flush is set to true and the
     *     queued requests has failed. Else return true
     *
     * @throws ErrorException
     */
    public function sendNotification(
        Subscription $subscription,
        ?string $payload = null,
        bool $flush = false,
        $options = [],
        array $auth = []
    ) {
        $notification = $this->buildNotification($subscription, (string) $payload,
            Options::wrap($options)->with($this->options), $auth);

        $this->notifications[] = $notification;

        return $flush ? $this->flush() : true;
    }

    /**
     * @param int $batch_size
     *
     * @return Generator|MessageSentReport[]
     * @throws ErrorException
     */
    public function flush(int $batch_size = 1000): ?Generator
    {
        if (empty($this->notifications)) {
            return null;
        }

        $batches = array_chunk($this->notifications, $batch_size);
        $this->notifications = [];

        foreach ($batches as $batch) {
            $promises = [];
            foreach ($batch as $notification) {
                $promises[] = $this->client->sendAsync($this->prepare($notification));
            }

            foreach ($promises as $promise) {
                yield $promise->wait();
            }
        }

        if ($this->reuseVAPIDHeaders) {
            $this->vapidHeaders = [];
        }
    }

    /**
     * @param Notification $notification
     *
     * @return Request
     *
     * @throws ErrorException
     */
    private function prepare(Notification $notification): Request
    {
        /** @var Notification $notification */
        $subscription = $notification->getSubscription();
        $endpoint = $subscription->getEndpoint();
        $userPublicKey = $subscription->getPublicKey();
        $userAuthToken = $subscription->getAuthToken();
        $contentEncoding = $subscription->getContentEncoding();
        $payload = $notification->getPayload();
        $options = $notification->getOptions();
        $auth = $notification->getAuth();

        if (!empty($payload) && !empty($userPublicKey) && !empty($userAuthToken)) {
            if (!$contentEncoding) {
                throw new ErrorException('Subscription should have a content encoding');
            }

            $encrypted = Encryption::encrypt($payload, $userPublicKey, $userAuthToken, $contentEncoding);
            $cipherText = $encrypted['cipherText'];
            $salt = $encrypted['salt'];
            $localPublicKey = $encrypted['localPublicKey'];

            $headers = [
                'Content-Type' => 'application/octet-stream',
                'Content-Encoding' => $contentEncoding,
            ];

            if ($contentEncoding === "aesgcm") {
                $headers['Encryption'] = 'salt=' . Base64Url::encode($salt);
                $headers['Crypto-Key'] = 'dh=' . Base64Url::encode($localPublicKey);
            }

            $encryptionContentCodingHeader = Encryption::getContentCodingHeader($salt, $localPublicKey,
                $contentEncoding);
            $content = $encryptionContentCodingHeader . $cipherText;

            $headers['Content-Length'] = Utils::safeStrlen($content);
        } else {
            $headers = [
                'Content-Length' => 0,
            ];

            $content = '';
        }

        $headers['TTL'] = $options->getTtl();

        if ($urgency = $options->getUrgency()) {
            $headers['Urgency'] = $urgency;
        }

        if ($topic = $options->getTopic()) {
            $headers['Topic'] = $topic;
        }

        // if GCM
        if ($subscription->getServiceName() === 'GCM') {
            if ($notification->hasAuth() === false) {
                throw new ErrorException('No GCM API Key specified.');
            }
            $headers['Authorization'] = 'key=' . $notification->getAuth();
        } elseif ($contentEncoding && $notification->getAuthType() === 'VAPID') {
            // if VAPID (GCM doesn't support it but FCM does)
            $audience = parse_url($endpoint, PHP_URL_SCHEME) . '://' . parse_url($endpoint, PHP_URL_HOST);
            if (!parse_url($audience)) {
                throw new ErrorException('Audience "' . $audience . '"" could not be generated.');
            }

            $vapidHeaders = $this->getVAPIDHeaders($audience, $contentEncoding, $auth['VAPID']);

            $headers['Authorization'] = $vapidHeaders['Authorization'];

            if ($contentEncoding === 'aesgcm') {
                if (array_key_exists('Crypto-Key', $headers)) {
                    $headers['Crypto-Key'] .= ';' . $vapidHeaders['Crypto-Key'];
                } else {
                    $headers['Crypto-Key'] = $vapidHeaders['Crypto-Key'];
                }
            }
        }

        return $this->client->buildRequest($endpoint, $headers, $content);
    }

    /**
     * @return bool
     */
    public function isAutomaticPadding(): bool
    {
        return $this->automaticPadding !== 0;
    }

    /**
     * @return int
     */
    public function getAutomaticPadding()
    {
        return $this->automaticPadding;
    }

    /**
     * @param int|bool $automaticPadding Max padding length
     *
     * @return WebPush
     *
     * @throws Exception
     */
    public function setAutomaticPadding($automaticPadding): WebPush
    {
        if ($automaticPadding > Encryption::MAX_PAYLOAD_LENGTH) {
            throw new Exception('Automatic padding is too large. Max is ' . Encryption::MAX_PAYLOAD_LENGTH . '. Recommended max is ' . Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH . ' for compatibility reasons (see README).');
        }

        if ($automaticPadding < 0) {
            throw new Exception('Padding length should be positive or zero.');
        }

        if (is_bool($automaticPadding)) {
            $this->automaticPadding = $automaticPadding ? Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH : 0;
        } else {
            $this->automaticPadding = $automaticPadding;
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function getReuseVAPIDHeaders()
    {
        return $this->reuseVAPIDHeaders;
    }

    /**
     * Reuse VAPID headers in the same flush session to improve performance
     *
     * @param bool $enabled
     *
     * @return WebPush
     */
    public function setReuseVAPIDHeaders(bool $enabled)
    {
        $this->reuseVAPIDHeaders = $enabled;

        return $this;
    }

    public function getOptions(): Options
    {
        return $this->options;
    }

    /**
     * @return int
     */
    public function countPendingNotifications(): int
    {
        return null !== $this->notifications ? count($this->notifications) : 0;
    }

    /**
     * @param Subscription $subscription
     * @param string $payload
     * @param Options $options
     * @param array $auth
     *
     * @return Notification
     * @throws ErrorException
     */
    private function buildNotification(
        Subscription $subscription,
        string $payload,
        Options $options,
        array $auth
    ): Notification {
        if (!empty($payload)) {
            if (Utils::safeStrlen($payload) > Encryption::MAX_PAYLOAD_LENGTH) {
                throw new ErrorException('Size of payload must not be greater than ' . Encryption::MAX_PAYLOAD_LENGTH . ' octets.');
            }

            $contentEncoding = $subscription->getContentEncoding();
            if (!$contentEncoding) {
                throw new ErrorException('Subscription should have a content encoding');
            }

            $payload = Encryption::padPayload($payload, $this->automaticPadding, $contentEncoding);
        }

        $auth = empty($auth) ? $this->auth : $auth;
        if (array_key_exists('VAPID', $auth)) {
            $auth['VAPID'] = VAPID::validate($auth['VAPID']);
        }

        return new Notification($subscription, $payload, $options, $auth);
    }

    /**
     * @param string $audience
     * @param string $contentEncoding
     * @param array $vapid
     *
     * @return array
     * @throws ErrorException
     */
    private function getVAPIDHeaders(string $audience, string $contentEncoding, array $vapid)
    {
        $vapidHeaders = null;

        $cache_key = null;
        if ($this->reuseVAPIDHeaders) {
            $cache_key = implode('#', [$audience, $contentEncoding, crc32(serialize($vapid))]);
            if (array_key_exists($cache_key, $this->vapidHeaders)) {
                $vapidHeaders = $this->vapidHeaders[$cache_key];
            }
        }

        if (!$vapidHeaders) {
            $vapidHeaders = VAPID::getVapidHeaders($audience, $vapid['subject'], $vapid['publicKey'],
                $vapid['privateKey'], $contentEncoding);
        }

        if ($this->reuseVAPIDHeaders) {
            $this->vapidHeaders[$cache_key] = $vapidHeaders;
        }

        return $vapidHeaders;
    }
}
