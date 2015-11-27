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
use Mdanter\Ecc\EccFactory;
use Mdanter\Ecc\Message\MessageFactory;

class WebPush
{
    /** @var Browser */
    protected $browser;

    /** @var array Key is push server type and value is the API key */
    protected $apiKeys;

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
     * Send one notification.
     *
     * @param string      $endpoint
     * @param string|null $payload       If you want to send an array, json_encode it.
     * @param string|null $userPublicKey
     *
     * @return array
     *
     * @throws \ErrorException
     */
    public function sendNotification($endpoint, $payload = null, $userPublicKey = null)
    {
        $endpoints = array($endpoint);
        $payloads = isset($payload) ? array($payload) : null;
        $userPublicKeys = isset($userPublicKey) ? array($userPublicKey) : null;

        return $this->sendNotifications($endpoints, $payloads, $userPublicKeys);
    }

    /**
     * Send multiple notifications.
     *
     * @param array      $endpoints
     * @param array|null $payloads
     * @param array|null $userPublicKeys
     *
     * @return array
     *
     * @throws \ErrorException
     */
    public function sendNotifications(array $endpoints, array $payloads = null, array $userPublicKeys = null)
    {
        // sort endpoints by server type
        $endpointsByServerType = $this->sortEndpoints($endpoints);

        // if GCM we should check for the API key
        if (array_key_exists('GCM', $endpointsByServerType)) {
            if (empty($this->apiKeys['GCM'])) {
                throw new \ErrorException('No GCM API Key specified.');
            }
        }

        // for each endpoint server type
        $responses = array();
        foreach ($endpointsByServerType as $serverType => $endpoints) {
            switch ($serverType) {
                case 'GCM':
                    $responses += $this->sendToGCMEndpoints($endpoints);
                    break;
                case 'standard':
                    $responses += $this->sendToStandardEndpoints($endpoints, $payloads, $userPublicKeys);
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
        foreach ($responses as $response) {
            if (!isset($response)) {
                return array(
                    'success' => false,
                );
            } elseif (!$response->isSuccessful()) {
                return array(
                    'success' => false,
                    'statusCode' => $response->getStatusCode(),
                    'headers' => $response->getHeaders(),
                );
            }
        }

        return array(
            'success' => true,
        );
    }

    /**
     * @param string $userPublicKey base 64 encoded
     * @param string $payload
     *
     * @return array
     *
     * @throws
     */
    private function encrypt($userPublicKey, $payload)
    {
        throw new \ErrorException('Encryption does not work yet.');

        // get local curve
        $localCurveGenerator = EccFactory::getNistCurves()->generator256();
        $localPrivateKey = $localCurveGenerator->createPrivateKey();
        $localPublicKey = $localPrivateKey->getPublicKey();
        // var sharedSecret = localCurve.computeSecret(userPublicKey);

        //var salt = crypto.randomBytes(16);

        //ece.saveKey('webpushKey', sharedSecret);

        /*var cipherText = ece.encrypt(payload, {
            keyid: 'webpushKey',
            salt: urlBase64.encode(salt),
        });*/

        $messages = new MessageFactory(EccFactory::getAdapter());
        $message = $messages->plaintext($payload, 'sha256');

        $dh = $localPrivateKey->createExchange($messages, $userPublicKey);
        $salt = hash('sha256', $dh->calculateSharedKey(), true);

        $cipherText = $dh->encrypt($message)->getContent();

        return array(
            'localPublicKey' => base64_encode($localPublicKey),
            'salt' => base64_encode($salt),
            'cipherText' => base64_encode($cipherText),
        );
    }

    /**
     * @param array      $endpoints
     * @param array|null $payloads
     * @param array|null $userPublicKeys
     *
     * @return array
     *
     * @throws \ErrorException
     */
    private function sendToStandardEndpoints(array $endpoints, array $payloads = null, array $userPublicKeys = null)
    {
        $responses = array();
        foreach ($endpoints as $i => $endpoint) {
            $payload = $payloads[$i];

            if (isset($payload)) {
                $encrypted = $this->encrypt($userPublicKeys[$i], $payload);

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

            $responses[] = $this->sendRequest($endpoint, $headers, $content);
        }

        return $responses;
    }

    /**
     * @param array $endpoints
     *
     * @return array
     */
    private function sendToGCMEndpoints(array $endpoints)
    {
        $maxBatchSubscriptionIds = 1000;
        $url = 'https://android.googleapis.com/gcm/send';

        $headers['Authorization'] = 'key='.$this->apiKeys['GCM'];
        $headers['Content-Type'] = 'application/json';

        $subscriptionIds = array();
        foreach ($endpoints as $endpoint) {
            // get all subscriptions ids
            $endpointsSections = explode('/', $endpoint);
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
     * @param array $endpoints
     *
     * @return array
     */
    private function sortEndpoints(array $endpoints)
    {
        $sortedEndpoints = array();

        $serverTypesByUrl = array(
            'GCM' => 'https://android.googleapis.com/gcm/send',
        );

        foreach ($endpoints as $endpoint) {
            $standard = true;

            foreach ($serverTypesByUrl as $type => $url) {
                if (substr($endpoint, 0, strlen($url)) === $url) {
                    $sortedEndpoints[$type][] = $endpoint;
                    $standard = false;
                    break;
                }
            }

            if ($standard) {
                $sortedEndpoints['standard'][] = $endpoint;
            }
        }

        return $sortedEndpoints;
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
