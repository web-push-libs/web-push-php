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

    /** @var int Time To Live of notifications */
    private $TTL;

    /**
     * WebPush constructor.
     *
     * @param array               $apiKeys Some servers needs authentication. Provide your API keys here. (eg. array('GCM' => 'GCM_API_KEY'))
     * @param int|null            $TTL     Time To Live of notifications, default being 4 weeks.
     * @param int|null            $timeout Timeout of POST request
     * @param AbstractClient|null $client
     */
    public function __construct(array $apiKeys = array(), $TTL = 2419200, $timeout = 30, AbstractClient $client = null)
    {
        $this->apiKeys = $apiKeys;
        $this->TTL = $TTL;

        $client = isset($client) ? $client : new MultiCurl();
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

    private function sendToStandardEndpoints(array $notifications)
    {
        $headers = array(
            'Content-Length' => 0,
            'TTL' => $this->TTL,
        );

        $content = '';

        $responses = array();
        /** @var Notification $notification */
        foreach ($notifications as $notification) {
            $responses[] = $this->sendRequest($notification->getEndpoint(), $headers, $content);
        }

        return $responses;
    }

    private function sendToGCMEndpoints(array $notifications)
    {
        $maxBatchSubscriptionIds = 1000;
        $url = $this->urlByServerType['GCM'];

        $headers = array(
            'Authorization' => 'key='.$this->apiKeys['GCM'],
            'Content-Type' => 'application/json',
            'TTL' => $this->TTL,
        );

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

    /**
     * @return int
     */
    public function getTTL()
    {
        return $this->TTL;
    }

    /**
     * @param int $TTL
     */
    public function setTTL($TTL)
    {
        $this->TTL = $TTL;
    }
}
