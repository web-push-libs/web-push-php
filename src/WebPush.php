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
use Mdanter\Ecc\Crypto\Key\PublicKey;
use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Serializer\Point\UncompressedPointSerializer;

class WebPush
{
    /** @var Browser */
    protected $browser;

    /** @var array Key is push server type and value is the API key */
    protected $apiKeys;

    /** @var array Array of array of Notifications by server type */
    private $notificationsByServerType;

    /** @var array Array of not standard endpoint sources */
    private $urlByServerType = array(
        'GCM' => 'https://android.googleapis.com/gcm/send',
    );

    /**
     * WebPush constructor.
     *
     * @param array               $apiKeys Some servers needs authentication. Provide your API keys here. (eg. array('GCM' => 'GCM_API_KEY'))
     * @param int|null            $TTL     Time to live of notifications
     * @param int|null            $timeout Timeout of POST request
     * @param AbstractClient|null $client
     */
    public function __construct(array $apiKeys = array(), $TTL = null, $timeout = null, AbstractClient $client = null)
    {
        $this->apiKeys = $apiKeys;
        $this->TTL = $TTL;

        $client = isset($client) ? $client : new MultiCurl();
        $timeout = isset($timeout) ? $timeout : 30;
        $client->setTimeout($timeout);
        $this->browser = new Browser($client);
    }

    /**
     * Send a notification.
     *
     * @param string      $endpoint
     * @param string|null $payload If you want to send an array, json_encode it.
     * @param string|null $userPublicKey
     * @param bool        $flush If you want to flush directly (usually when you send only one notification)
     *
     * @return bool|array Return an array of information if $flush is set to true and the request has failed.
     *                    Else return true.
     * @throws \ErrorException
     */
    public function sendNotification($endpoint, $payload = null, $userPublicKey = null, $flush = false)
    {
        // sort notification by server type
        $type = $this->sortEndpoint($endpoint);
        $this->notificationsByServerType[$type][] = new Notification($endpoint, $payload, $userPublicKey);

        if ($flush) {
            $res = $this->flush();
            return is_array($res) ? $res[0] : true;
        }

        return true;
    }

    /**
     * Flush notifications. Triggers the requests.
     *
     * @return array|bool If there are no errors, return true.
     *                    If there were no notifications in the queue, return false.
     *                    Else return an array of information for each notification sent (success, statusCode, headers).
     *
     * @throws \ErrorException
     */
    public function flush()
    {
        if (empty($this->notificationsByServerType)) {
            return false;
        }

        // if GCM is present, we should check for the API key
        if (array_key_exists('GCM', $this->notificationsByServerType)) {
            if (empty($this->apiKeys['GCM'])) {
                throw new \ErrorException('No GCM API Key specified.');
            }
        }

        // for each endpoint server type
        $responses = array();
        foreach ($this->notificationsByServerType as $serverType => $notifications) {
            switch ($serverType) {
                case 'GCM':
                    $responses += $this->sendToGCMEndpoints($notifications);
                    break;
                case 'standard':
                    $responses += $this->sendToStandardEndpoints($notifications);
                    break;
            }
        }

        // if multi curl, flush
        if ($this->browser->getClient() instanceof MultiCurl) {
            /** @var MultiCurl $multiCurl */
            $multiCurl = $this->browser->getClient();
            $multiCurl->flush();
        }

        /** @var Response|null $response */
        $return = array();
        $completeSuccess = true;
        foreach ($responses as $response) {
            if (!isset($response)) {
                $return[] = array(
                    'success' => false,
                );

                $completeSuccess = false;
            } elseif (!$response->isSuccessful()) {
                $return[] = array(
                    'success' => false,
                    'statusCode' => $response->getStatusCode(),
                    'headers' => $response->getHeaders(),
                );

                $completeSuccess = false;
            } else {
                $return[] = array(
                    'success' => true,
                );
            }
        }

        // reset queue
        $this->notificationsByServerType = null;

        return $completeSuccess ? true : $return;
    }

    /**
     * @param string $userPublicKey base 64 encoded
     * @param string $payload
     *
     * @return array
     */
    private function encrypt($userPublicKey, $payload)
    {
        // initialize utilities
        $math = EccFactory::getAdapter();
        $keySerializer = new UncompressedPointSerializer($math);
        $curveGenerator = EccFactory::getNistCurves()->generator256();
        $curve = EccFactory::getNistCurves()->curve256();

        // get local key pair
        $localPrivateKeyObject = $curveGenerator->createPrivateKey();
        $localPublicKeyObject = $localPrivateKeyObject->getPublicKey();
        $localPublicKey = base64_encode(hex2bin($keySerializer->serialize($localPublicKeyObject->getPoint())));

        // get user public key object
        $userPublicKeyObject = new PublicKey($math, $curveGenerator, $keySerializer->unserialize($curve, bin2hex(base64_decode($userPublicKey))));

        // get shared secret from user public key and local private key
        $sharedSecret = $userPublicKeyObject->getPoint()->mul($localPrivateKeyObject->getSecret())->getX();

        // generate salt
        $salt = openssl_random_pseudo_bytes(16);

        // get encryption key
        $encryptionKey = hash_hmac('sha256', $salt, $sharedSecret);

        // encrypt
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-128-gcm'));
        $cipherText = openssl_encrypt($payload, 'aes-128-gcm', $encryptionKey, false, $iv); // base 64 encoded

        return array(
            'localPublicKey' => $localPublicKey,
            'salt' => base64_encode($salt),
            'cipherText' => $cipherText,
        );
    }

    private function sendToStandardEndpoints(array $notifications)
    {
        $responses = array();
        /** @var Notification $notification */
        foreach ($notifications as $notification) {
            $payload = $notification->getPayload();
            $userPublicKey = $notification->getUserPublicKey();

            if (isset($payload) && isset($userPublicKey)) {
                $encrypted = $this->encrypt($userPublicKey, $payload);

                $headers = array(
                    'Content-Length' => strlen($encrypted['cipherText']),
                    'Content-Type' => 'application/octet-stream',
                    'Encryption-Key' => 'keyid=p256dh;dh='.$encrypted['localPublicKey'],
                    'Encryption' => 'keyid=p256dh;salt='.$encrypted['salt'],
                    'Content-Encoding' => 'aesgcm128',
                );

                $content = $encrypted['cipherText'];
            } else {
                $headers = array(
                    'Content-Length' => 0,
                );

                $content = '';
            }

            if (isset($this->TTL)) {
                $headers['TTL'] = $this->TTL;
            }

            $responses[] = $this->sendRequest($notification->getEndpoint(), $headers, $content);
        }

        return $responses;
    }

    private function sendToGCMEndpoints(array $notifications)
    {
        $maxBatchSubscriptionIds = 1000;
        $url = $this->urlByServerType['GCM'];

        $headers['Authorization'] = 'key='.$this->apiKeys['GCM'];
        $headers['Content-Type'] = 'application/json';

        $subscriptionIds = array();
        /** @var Notification $notification */
        foreach ($notifications as $notification) {
            // get all subscriptions ids
            $endpointsSections = explode('/', $notification->getEndpoint());
            $subscriptionIds[] = $endpointsSections[count($endpointsSections) - 1];
        }

        // chunk by max number
        $batch = array_chunk($subscriptionIds, $maxBatchSubscriptionIds);

        $responses = array();
        foreach ($batch as $subscriptionIds) {
            $content = json_encode(array(
                'registration_ids' => $subscriptionIds,
            ));

            $headers['Content-Length'] = strlen($content);

            $responses[] = $this->sendRequest($url, $headers, $content);
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
     * @param string $endpoint
     *
     * @return string
     */
    private function sortEndpoint($endpoint)
    {
        foreach ($this->urlByServerType as $type => $url) {
            if (substr($endpoint, 0, strlen($url)) === $url) {
                return $type;
            }
        }

        return 'standard';
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
     */
    public function setBrowser($browser)
    {
        $this->browser = $browser;
    }
}
