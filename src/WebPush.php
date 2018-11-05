<?php

declare(strict_types=1);

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
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\ResponseInterface;

class WebPush
{
    public const GCM_URL = 'https://android.googleapis.com/gcm/send';
    public const FCM_BASE_URL = 'https://fcm.googleapis.com';

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
     * @var array Default options : TTL, urgency, topic, batchSize
     */
    private $defaultOptions;

    /**
     * @var int Automatic padding of payloads, if disabled, trade security for bandwidth
     */
    private $automaticPadding = Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH;

    /**
     * WebPush constructor.
     *
     * @param array    $auth           Some servers needs authentication
     * @param array    $defaultOptions TTL, urgency, topic, batchSize
     * @param int|null $timeout        Timeout of POST request
     * @param array    $clientOptions
     *
     * @throws \ErrorException
     */
    public function __construct(array $auth = [], array $defaultOptions = [], ?int $timeout = 30, array $clientOptions = [])
    {
        $extensions = [
            'curl' => '[WebPush] curl extension is not loaded but is required. You can fix this in your php.ini.',
            'gmp' => '[WebPush] gmp extension is not loaded but is required for sending push notifications with payload or for VAPID authentication. You can fix this in your php.ini.',
            'mbstring' => '[WebPush] mbstring extension is not loaded but is required for sending push notifications with payload or for VAPID authentication. You can fix this in your php.ini.',
            'openssl' => '[WebPush] openssl extension is not loaded but is required for sending push notifications with payload or for VAPID authentication. You can fix this in your php.ini.',
        ];
        foreach ($extensions as $extension => $message) {
            if (!extension_loaded($extension)) {
                trigger_error($message, E_USER_WARNING);
            }
        }

        if (ini_get('mbstring.func_overload') >= 2) {
            trigger_error("[WebPush] mbstring.func_overload is enabled for str* functions. You must disable it if you want to send push notifications with payload or use VAPID. You can fix this in your php.ini.", E_USER_NOTICE);
        }

        if (isset($auth['VAPID'])) {
            $auth['VAPID'] = VAPID::validate($auth['VAPID']);
        }

        $this->auth = $auth;

        $this->setDefaultOptions($defaultOptions);

        if (!array_key_exists('timeout', $clientOptions) && isset($timeout)) {
            $clientOptions['timeout'] = $timeout;
        }
        $this->client = new Client($clientOptions);
    }

    /**
     * Send a notification.
     *
     * @param Subscription $subscription
     * @param string|null $payload If you want to send an array, json_encode it
     * @param bool $flush If you want to flush directly (usually when you send only one notification)
     * @param array $options Array with several options tied to this notification. If not set, will use the default options that you can set in the WebPush object
     * @param array $auth Use this auth details instead of what you provided when creating WebPush
     *
     * @return array|bool Return an array of information if $flush is set to true and the queued requests has failed.
     *                    Else return true
     *
     * @throws \ErrorException
     */
    public function sendNotification(Subscription $subscription, ?string $payload = null, bool $flush = false, array $options = [], array $auth = [])
    {
        if (isset($payload)) {
            if (Utils::safeStrlen($payload) > Encryption::MAX_PAYLOAD_LENGTH) {
                throw new \ErrorException('Size of payload must not be greater than '.Encryption::MAX_PAYLOAD_LENGTH.' octets.');
            }

            $payload = Encryption::padPayload($payload, $this->automaticPadding, $subscription->getContentEncoding());
        }

        if (array_key_exists('VAPID', $auth)) {
            $auth['VAPID'] = VAPID::validate($auth['VAPID']);
        }

        $this->notifications[] = new Notification($subscription, $payload, $options, $auth);

	    return false !== $flush ? $this->flush() : true;
    }

	/**
	 * Flush notifications. Triggers the requests.
	 *
	 * @param null|int $batchSize Defaults the value defined in defaultOptions during instantiation (which defaults to 1000).
	 *
	 * @return iterable
	 * @throws \ErrorException
	 */
    public function flush(?int $batchSize = null) : iterable
    {
        if (empty($this->notifications)) {
	        yield from [];
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
	        	$result = null;

		        $this->client->sendAsync($request)
			        ->then(function ($response) use ($request, &$result) {
				        /** @var ResponseInterface $response * */
				        $result = new MessageSentReport($request, $response);
			        })
			        ->otherwise(function ($reason) use (&$result) {
				        /** @var RequestException $reason **/
				        $result = new MessageSentReport($reason->getRequest(), $reason->getResponse(), false, $reason->getMessage());
			        })
			        ->wait(false);

		        yield $result;
	        }
        }
    }

    /**
     * @param array $notifications
     *
     * @return array
     *
     * @throws \ErrorException
     */
    private function prepare(array $notifications): array
    {
        $requests = [];
        /** @var Notification $notification */
        foreach ($notifications as $notification) {
            $subscription = $notification->getSubscription();
            $endpoint = $subscription->getEndpoint();
            $userPublicKey = $subscription->getPublicKey();
            $userAuthToken = $subscription->getAuthToken();
            $contentEncoding = $subscription->getContentEncoding();
            $payload = $notification->getPayload();
            $options = $notification->getOptions($this->getDefaultOptions());
            $auth = $notification->getAuth($this->auth);

            if (!empty($payload) && !empty($userPublicKey) && !empty($userAuthToken)) {
                $encrypted = Encryption::encrypt($payload, $userPublicKey, $userAuthToken, $contentEncoding);
                $cipherText = $encrypted['cipherText'];
                $salt = $encrypted['salt'];
                $localPublicKey = $encrypted['localPublicKey'];

                $headers = [
                    'Content-Type' => 'application/octet-stream',
                    'Content-Encoding' => $contentEncoding,
                ];

                if ($contentEncoding === "aesgcm") {
                    $headers['Encryption'] = 'salt='.Base64Url::encode($salt);
                    $headers['Crypto-Key'] = 'dh='.Base64Url::encode($localPublicKey);
                }

                $encryptionContentCodingHeader = Encryption::getContentCodingHeader($salt, $localPublicKey, $contentEncoding);
                $content = $encryptionContentCodingHeader.$cipherText;

                $headers['Content-Length'] = Utils::safeStrlen($content);
            } else {
                $headers = [
                    'Content-Length' => 0,
                ];

                $content = '';
            }

            $headers['TTL'] = $options['TTL'];

            if (isset($options['urgency'])) {
                $headers['Urgency'] = $options['urgency'];
            }

            if (isset($options['topic'])) {
                $headers['Topic'] = $options['topic'];
            }

            // if GCM
            if (substr($endpoint, 0, strlen(self::GCM_URL)) === self::GCM_URL) {
                if (array_key_exists('GCM', $auth)) {
                    $headers['Authorization'] = 'key='.$auth['GCM'];
                } else {
                    throw new \ErrorException('No GCM API Key specified.');
                }
            }
            // if VAPID (GCM doesn't support it but FCM does)
            elseif (array_key_exists('VAPID', $auth)) {
                $vapid = $auth['VAPID'];

                $audience = parse_url($endpoint, PHP_URL_SCHEME).'://'.parse_url($endpoint, PHP_URL_HOST);

                if (!parse_url($audience)) {
                    throw new \ErrorException('Audience "'.$audience.'"" could not be generated.');
                }

                $vapidHeaders = VAPID::getVapidHeaders($audience, $vapid['subject'], $vapid['publicKey'], $vapid['privateKey'], $contentEncoding);

                $headers['Authorization'] = $vapidHeaders['Authorization'];

                if ($contentEncoding === 'aesgcm') {
                    if (array_key_exists('Crypto-Key', $headers)) {
                        $headers['Crypto-Key'] .= ';'.$vapidHeaders['Crypto-Key'];
                    } else {
                        $headers['Crypto-Key'] = $vapidHeaders['Crypto-Key'];
                    }
                } else if ($contentEncoding === 'aes128gcm' && substr($endpoint, 0, strlen(self::FCM_BASE_URL)) === self::FCM_BASE_URL) {
                    $endpoint = str_replace('fcm/send', 'wp', $endpoint);
                }
            }

            $requests[] = new Request('POST', $endpoint, $headers, $content);
        }

        return $requests;
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
     * @throws \Exception
     */
    public function setAutomaticPadding($automaticPadding): WebPush
    {
        if ($automaticPadding > Encryption::MAX_PAYLOAD_LENGTH) {
            throw new \Exception('Automatic padding is too large. Max is '.Encryption::MAX_PAYLOAD_LENGTH.'. Recommended max is '.Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH.' for compatibility reasons (see README).');
        } elseif ($automaticPadding < 0) {
            throw new \Exception('Padding length should be positive or zero.');
        } elseif ($automaticPadding === true) {
            $this->automaticPadding = Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH;
        } elseif ($automaticPadding === false) {
            $this->automaticPadding = 0;
        } else {
            $this->automaticPadding = $automaticPadding;
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getDefaultOptions(): array
    {
        return $this->defaultOptions;
    }

    /**
     * @param array $defaultOptions Keys 'TTL' (Time To Live, defaults 4 weeks), 'urgency', 'topic', 'batchSize'
     */
    public function setDefaultOptions(array $defaultOptions)
    {
        $this->defaultOptions['TTL'] = isset($defaultOptions['TTL']) ? $defaultOptions['TTL'] : 2419200;
        $this->defaultOptions['urgency'] = isset($defaultOptions['urgency']) ? $defaultOptions['urgency'] : null;
        $this->defaultOptions['topic'] = isset($defaultOptions['topic']) ? $defaultOptions['topic'] : null;
        $this->defaultOptions['batchSize'] = isset($defaultOptions['batchSize']) ? $defaultOptions['batchSize'] : 1000;
    }
}
