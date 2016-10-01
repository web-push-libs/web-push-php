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

use Buzz\Browser;
use Buzz\Client\AbstractClient;
use Buzz\Client\MultiCurl;
use Buzz\Exception\RequestException;
use Buzz\Message\Response;

class WebPush
{
    const GCM_URL = 'https://android.googleapis.com/gcm/send';
    const FCM_URL = 'https://fcm.googleapis.com/fcm/send';

    /** @var Browser */
    protected $browser;

    /** @var array */
    protected $auth;

    /** @var array Array of array of Notifications */
    private $notifications;

    /** @var array Default options : TTL, urgency, topic */
    private $defaultOptions;

    /** @var bool Automatic padding of payloads, if disabled, trade security for bandwidth */
    private $automaticPadding = true;

    /** @var boolean */
    private $nativePayloadEncryptionSupport;

    /**
     * WebPush constructor.
     *
     * @param array $auth Some servers needs authentication.
     * @param array $defaultOptions TTL, urgency, topic
     * @param int|null $timeout Timeout of POST request
     * @param AbstractClient|null $client
     */
    public function __construct(array $auth = array(), $defaultOptions = array(), $timeout = 30, AbstractClient $client = null)
    {
        $this->auth = $auth;
        $this->setDefaultOptions($defaultOptions);

        $client = isset($client) ? $client : new MultiCurl();
        $client->setTimeout($timeout);
        $this->browser = new Browser($client);
        
        $this->nativePayloadEncryptionSupport = version_compare(phpversion(), '7.1', '>=');
    }

    /**
     * Send a notification.
     *
     * @param string $endpoint
     * @param string|null $payload If you want to send an array, json_encode it.
     * @param string|null $userPublicKey
     * @param string|null $userAuthToken
     * @param bool $flush If you want to flush directly (usually when you send only one notification)
     * @param array $options Array with several options tied to this notification. If not set, will use the default options that you can set in the WebPush object.
     * @return array|bool Return an array of information if $flush is set to true and the queued requests has failed.
     *                    Else return true.
     * @throws \ErrorException
     */
    public function sendNotification($endpoint, $payload = null, $userPublicKey = null, $userAuthToken = null, $flush = false, $options = array())
    {
        if(isset($payload)) {
            if (Utils::safe_strlen($payload) > Encryption::MAX_PAYLOAD_LENGTH) {
                throw new \ErrorException('Size of payload must not be greater than '.Encryption::MAX_PAYLOAD_LENGTH.' octets.');
            }

            $payload = Encryption::padPayload($payload, $this->automaticPadding);
        }

        $this->notifications[] = new Notification($endpoint, $payload, $userPublicKey, $userAuthToken, $options);

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
     * @return array|bool If there are no errors, return true.
     *                    If there were no notifications in the queue, return false.
     *                    Else return an array of information for each notification sent (success, statusCode, headers, content).
     *
     * @throws \ErrorException
     */
    public function flush()
    {
        if (empty($this->notifications)) {
            return false;
        }

        // for each endpoint server type
        $responses = $this->prepareAndSend($this->notifications);

        // if multi curl, flush
        if ($this->browser->getClient() instanceof MultiCurl) {
            /** @var MultiCurl $multiCurl */
            $multiCurl = $this->browser->getClient();
            $multiCurl->flush();
        }

        /** @var Response|null $response */
        $return = array();
        $completeSuccess = true;
        foreach ($responses as $i => $response) {
            if (!isset($response)) {
                $return[] = array(
                    'success' => false,
                    'endpoint' => $this->notifications[$i]->getEndpoint(),
                );

                $completeSuccess = false;
            } elseif (!$response->isSuccessful()) {
                $return[] = array(
                    'success' => false,
                    'endpoint' => $this->notifications[$i]->getEndpoint(),
                    'statusCode' => $response->getStatusCode(),
                    'headers' => $response->getHeaders(),
                    'content' => $response->getContent(),
                    'expired' => in_array($response->getStatusCode(), array(400, 404, 410)),
                );

                $completeSuccess = false;
            } else {
                $return[] = array(
                    'success' => true,
                );
            }
        }

        // reset queue
        $this->notifications = null;

        return $completeSuccess ? true : $return;
    }

    private function prepareAndSend(array $notifications)
    {
        $responses = array();
        /** @var Notification $notification */
        foreach ($notifications as $notification) {
            $endpoint = $notification->getEndpoint();
            $payload = $notification->getPayload();
            $userPublicKey = $notification->getUserPublicKey();
            $userAuthToken = $notification->getUserAuthToken();
            $options = $notification->getOptions($this->getDefaultOptions());

            if (isset($payload) && isset($userPublicKey) && isset($userAuthToken)) {
                $encrypted = Encryption::encrypt($payload, $userPublicKey, $userAuthToken, $this->nativePayloadEncryptionSupport);

                $headers = array(
                    'Content-Length' => Utils::safe_strlen($encrypted['cipherText']),
                    'Content-Type' => 'application/octet-stream',
                    'Content-Encoding' => 'aesgcm',
                    'Encryption' => 'keyid="p256dh";salt="'.$encrypted['salt'].'"',
                    'Crypto-Key' => 'keyid="p256dh";dh="'.$encrypted['localPublicKey'].'"',
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

            // if GCM or FCM url
            if (substr($endpoint, 0, Utils::safe_strlen(self::GCM_URL)) === self::GCM_URL
                || substr($endpoint, 0, Utils::safe_strlen(self::FCM_URL)) === self::FCM_URL) {
                if (array_key_exists('GCM', $this->auth)) {
                    $headers['Authorization'] = 'key='.$this->auth['GCM'];
                } else {
                    throw new \ErrorException('No GCM/FCM API Key specified.');
                }
            }

            $responses[] = $this->sendRequest($endpoint, $headers, $content);
        }

        return $responses;
    }

    /**
     * @param string $url
     * @param array  $headers
     * @param string $content
     *
     * @return \Buzz\Message\MessageInterface|null
     */
    private function sendRequest($url, array $headers, $content)
    {
        try {
            $response = $this->browser->post($url, $headers, $content);
        } catch (RequestException $e) {
            $response = null;
        }

        return $response;
    }

    /**
     * @return Browser
     */
    public function getBrowser()
    {
        return $this->browser;
    }

    /**
     * @param Browser $browser
     *
     * @return WebPush
     */
    public function setBrowser($browser)
    {
        $this->browser = $browser;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isAutomaticPadding()
    {
        return $this->automaticPadding;
    }

    /**
     * @param boolean $automaticPadding
     *
     * @return WebPush
     */
    public function setAutomaticPadding($automaticPadding)
    {
        $this->automaticPadding = $automaticPadding;

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
     * @param array $defaultOptions Keys 'TTL' (Time To Live, defaults 4 weeks), 'urgency', and 'topic'
     */
    public function setDefaultOptions(array $defaultOptions)
    {
        $this->defaultOptions['TTL'] = array_key_exists('TTL', $defaultOptions) ? $defaultOptions['TTL'] : 2419200;
        $this->defaultOptions['urgency'] = array_key_exists('urgency', $defaultOptions) ? $defaultOptions['urgency'] : null;
        $this->defaultOptions['topic'] = array_key_exists('topic', $defaultOptions) ? $defaultOptions['topic'] : null;
    }
}
