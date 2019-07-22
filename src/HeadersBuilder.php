<?php

namespace WebPush;

use Base64Url\Base64Url;
use ErrorException;

class HeadersBuilder
{
    /**
     * @param Notification $notification
     *
     * @return Headers
     * @throws ErrorException
     */
    public function build(Notification $notification): Headers
    {
        $headers = new Headers();
        $headers->set('Content-Length', 0);

        if ($notification->getPayload()->isNotEmpty()) {
            $headers->add([
                'Content-Type' => 'application/octet-stream',
                'Content-Encoding' => $notification->getSubscription()->getEncoding(),
            ]);

            if ($notification->getSubscription()->getEncoding() === 'aesgcm') {
                $headers->add([
                    'Encryption' => 'salt=' . Base64Url::encode($notification->getPayload()->getSalt()),
                    'Crypto-Key' => 'dh=' . Base64Url::encode($notification->getPayload()->getLocalPublicKey())
                ]);
            }

            $headers->set('Content-Length', $notification->getPayload()->getLength());
        }

        $headers->set('TTL', $notification->getOptions()->getTtl());

        if ($urgency = $notification->getOptions()->getUrgency()) {
            $headers->set('Urgency', $urgency);
        }

        if ($topic = $notification->getOptions()->getTopic()) {
            $headers->set('Topic', $topic);
        }

        $vapid_headers = $this->getVAPIDHeaders(
            $this->getAudience($notification->getSubscription()->getEndpoint()),
            $notification->getSubscription()->getEncoding(),
            $notification->getAuth()
        );

        $headers->set('Authorization', $vapid_headers['Authorization']);

        if ($notification->getSubscription()->getEncoding() === 'aesgcm') {
            $headers->append('Crypto-Key', $vapid_headers['Crypto-Key'], ';');
        }

        return $headers;
    }

    /**
     * @param string $audience
     * @param string $encoding
     * @param Contracts\AuthorizationInterface $auth
     *
     * @return array
     * @throws ErrorException
     */
    private function getVAPIDHeaders(string $audience, string $encoding, Contracts\AuthorizationInterface $auth): array
    {
        $headers = static function () use ($audience, $auth, $encoding) {
            return VAPID::getVapidHeaders(
                $audience,
                $auth->getSubject(),
                $auth->getPublicKey(),
                $auth->getPrivateKey(),
                $encoding
            );
        };

        if (CacheFactory::create('vapid')->isEnabled()) {
            $key = implode('#', [$audience, $encoding, crc32(serialize($auth))]);
            return CacheFactory::create('vapid')->remember($key, $headers);
        }

        return $headers();
    }

    /**
     * @param string $url
     *
     * @return string
     * @throws ErrorException
     */
    private function getAudience(string $url): string
    {
        if ($parsed = parse_url($url)) {
            $audience = implode('://', [$parsed['scheme'], $parsed['host']]);
        }

        if (empty($audience) || parse_url($audience) === false) {
            throw new ErrorException(sprintf('Audience could not be generated for %s.', $url));
        }

        return $audience;
    }
}
