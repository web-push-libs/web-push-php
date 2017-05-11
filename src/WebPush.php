<?php

/*
 * This file is part of the WebPush library.
 *
 * (c) Louis Lagrange <lagrange.louis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Minishlink\WebPush;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Promise;

class WebPush
{
    const GCM_URL = 'https://android.googleapis.com/gcm/send';

    /** @var Client */
    protected $client;

    /** @var array */
    protected $auth;

    /** @var array Array of array of Notifications */
    private $notifications;

    /** @var array Default options : TTL, urgency, topic, batchSize */
    private $defaultOptions;

    /** @var int Automatic padding of payloads, if disabled, trade security for bandwidth */
    private $automaticPadding = Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH;

    /**
     * WebPush constructor.
     *
     * @param array               $auth           Some servers needs authentication
     * @param array               $defaultOptions TTL, urgency, topic, batchSize
     * @param int|null            $timeout        Timeout of POST request
     * @param array               $clientOptions
     */
    public function __construct(array $auth = array(), $defaultOptions = array(), $timeout = 30, $clientOptions = array())
    {
        if (array_key_exists('VAPID', $auth)) {
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
     * @param string      $endpoint
     * @param string|null $payload       If you want to send an array, json_encode it
     * @param string|null $userPublicKey
     * @param string|null $userAuthToken
     * @param bool        $flush         If you want to flush directly (usually when you send only one notification)
     * @param array       $options       Array with several options tied to this notification. If not set, will use the default options that you can set in the WebPush object
     * @param array       $auth          Use this auth details instead of what you provided when creating WebPush
     *
     * @return array|bool Return an array of information if $flush is set to true and the queued requests has failed.
     *                    Else return true
     *
     * @throws \ErrorException
     */
    public function sendNotification($endpoint, $payload = null, $userPublicKey = null, $userAuthToken = null, $flush = false, $options = array(), $auth = array())
    {
        if (isset($payload)) {
            if (Utils::safeStrlen($payload) > Encryption::MAX_PAYLOAD_LENGTH) {
                throw new \ErrorException('Size of payload must not be greater than '.Encryption::MAX_PAYLOAD_LENGTH.' octets.');
            }

            $payload = Encryption::padPayload($payload, $this->automaticPadding);
        }

        if (array_key_exists('VAPID', $auth)) {
            $auth['VAPID'] = VAPID::validate($auth['VAPID']);
        }

        $this->notifications[] = new Notification($endpoint, $payload, $userPublicKey, $userAuthToken, $options, $auth);

        if ($flush) {
            $res = $this->flush();

            // if there has been a problem with at least one notification
            if (is_array($res)) {
                // if there was only one notification, return the information directly
                if (count($res) === 1) {
                    return $res[0];
                }

                return $res;
            }

            return true;
        }

        return true;
    }

    /**
     * Flush notifications. Triggers the requests.
     *
     * @param int $batchSize Defaults the value defined in defaultOptions during instanciation (which defaults to 1000).
     * @return array|bool If there are no errors, return true.
     *                    If there were no notifications in the queue, return false.
     * Else return an array of information for each notification sent (success, statusCode, headers, content)
     */
    public function flush($batchSize = null)
    {
        if (empty($this->notifications)) {
            return false;
        }

        if (!isset($batchSize)) {
            $batchSize = $this->defaultOptions['batchSize'];
        }

        $batches = array_chunk($this->notifications, $batchSize);
        $return = array();
        $completeSuccess = true;
        foreach ($batches as $batch) {
            // for each endpoint server type
            $requests = $this->prepare($batch);
            $promises = [];
            foreach ($requests as $request) {
                $promises[] = $this->client->sendAsync($request);
            }
            $results = Promise\settle($promises)->wait();

            foreach ($results as $result) {
                if ($result['state'] === "rejected") {
                    /** @var RequestException $reason **/
                    $reason = $result['reason'];

                    $error = array(
                        'success' => false,
                        'endpoint' => "".$reason->getRequest()->getUri(),
                        'message' => $reason->getMessage(),
                    );

                    $response = $reason->getResponse();
                    if ($response !== null) {
                        $statusCode = $response->getStatusCode();
                        $error['statusCode'] = $statusCode;
                        $error['expired'] = in_array($statusCode, array(404, 410));
                        $error['content'] = $response->getBody();
                        $error['headers'] = $response->getHeaders();
                    }

                    $return[] = $error;
                    $completeSuccess = false;
                } else {
                    $return[] = array(
                        'success' => true,
                    );
                }
            }
        }

        // reset queue
        $this->notifications = null;

        return $completeSuccess ? true : $return;
    }

    private function prepare(array $notifications)
    {
        $requests = array();
        /** @var Notification $notification */
        foreach ($notifications as $notification) {
            $endpoint = $notification->getEndpoint();
            $payload = $notification->getPayload();
            $userPublicKey = $notification->getUserPublicKey();
            $userAuthToken = $notification->getUserAuthToken();
            $options = $notification->getOptions($this->getDefaultOptions());
            $auth = $notification->getAuth($this->auth);

            if (isset($payload) && isset($userPublicKey) && isset($userAuthToken)) {
                $encrypted = Encryption::encrypt($payload, $userPublicKey, $userAuthToken);

                $headers = array(
                    'Content-Length' => Utils::safeStrlen($encrypted['cipherText']),
                    'Content-Type' => 'application/octet-stream',
                    'Content-Encoding' => 'aesgcm',
                    'Encryption' => 'salt='.$encrypted['salt'],
                    'Crypto-Key' => 'dh='.$encrypted['localPublicKey'],
                );

                $content = $encrypted['cipherText'];
            } else {
                $headers = array(
                    'Content-Length' => 0,
                );

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

                $vapidHeaders = VAPID::getVapidHeaders($audience, $vapid['subject'], $vapid['publicKey'], $vapid['privateKey']);

                $headers['Authorization'] = $vapidHeaders['Authorization'];

                if (array_key_exists('Crypto-Key', $headers)) {
                    // FUTURE replace ';' with ','
                    $headers['Crypto-Key'] .= ';'.$vapidHeaders['Crypto-Key'];
                } else {
                    $headers['Crypto-Key'] = $vapidHeaders['Crypto-Key'];
                }
            }

            $requests[] = new Request('POST', $endpoint, $headers, $content);
        }

        return $requests;
    }

    /**
     * @return bool
     */
    public function isAutomaticPadding()
    {
        return $this->automaticPadding !== false && $this->automaticPadding !== 0;
    }

    /**
     * @return int
     */
    public function getAutomaticPadding()
    {
        return $this->automaticPadding;
    }

    /**
     * @param int $automaticPadding Max padding length
     *
     * @return WebPush
     */
    public function setAutomaticPadding($automaticPadding)
    {
        if ($automaticPadding > Encryption::MAX_PAYLOAD_LENGTH) {
            throw new \Exception('Automatic padding is too large. Max is '.Encryption::MAX_PAYLOAD_LENGTH.'. Recommended max is '.Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH.' for compatibility reasons (see README).');
        } elseif ($automaticPadding < 0) {
            throw new \Exception('Padding length should be positive or zero.');
        } elseif ($automaticPadding === true) {
            $this->automaticPadding = Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH;
        } else {
            $this->automaticPadding = $automaticPadding;
        }

        return $this;
    }

    /**
     * @return array
     */
    public function getDefaultOptions()
    {
        return $this->defaultOptions;
    }

    /**
     * @param array $defaultOptions Keys 'TTL' (Time To Live, defaults 4 weeks), 'urgency', 'topic', 'batchSize'
     */
    public function setDefaultOptions(array $defaultOptions)
    {
        $this->defaultOptions['TTL'] = array_key_exists('TTL', $defaultOptions) ? $defaultOptions['TTL'] : 2419200;
        $this->defaultOptions['urgency'] = array_key_exists('urgency', $defaultOptions) ? $defaultOptions['urgency'] : null;
        $this->defaultOptions['topic'] = array_key_exists('topic', $defaultOptions) ? $defaultOptions['topic'] : null;
        $this->defaultOptions['batchSize'] = array_key_exists('batchSize', $defaultOptions) ? $defaultOptions['batchSize'] : 1000;
    }
}
