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

use Assert\Assertion;
use InvalidArgumentException;
use Jose\Component\Signature\Algorithm\ES256;
use Lcobucci\JWT\Signer\Ecdsa\Sha256;
use Minishlink\WebPush\Payload\AES128GCM;
use Minishlink\WebPush\Payload\AESGCM;
use Minishlink\WebPush\Payload\PayloadExtension;
use Minishlink\WebPush\VAPID\JWSProvider;
use Minishlink\WebPush\VAPID\LcobucciProvider;
use Minishlink\WebPush\VAPID\VAPIDExtension;
use Minishlink\WebPush\VAPID\WebTokenProvider;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

class SimpleWebPush implements WebPushService
{
    private WebPush $service;
    private ExtensionManager $extensionManager;
    private bool $vapidEnabled = false;

    public function __construct(ClientInterface $client, RequestFactoryInterface $requestFactory)
    {
        $payloadExtension = PayloadExtension::create()
            ->addContentEncoding(AESGCM::create())
            ->addContentEncoding(AES128GCM::create())
        ;
        $this->extensionManager = ExtensionManager::create()
            ->add(TTLExtension::create())
            ->add(TopicExtension::create())
            ->add(UrgencyExtension::create())
            ->add(PreferAsyncExtension::create())
            ->add($payloadExtension)
        ;
        $this->service = WebPush::create($client, $requestFactory, $this->extensionManager);
    }

    public static function create(ClientInterface $client, RequestFactoryInterface $requestFactory): self
    {
        return new self($client, $requestFactory);
    }

    public function enableVapid(string $subject, string $publicKey, string $privateKey): self
    {
        $jwsProvider = $this->getJwsProvider($publicKey, $privateKey);
        Assertion::false($this->vapidEnabled, 'VAPID has already been enabled');
        $this->extensionManager->add(
            VAPIDExtension::create($subject, $jwsProvider)
        );
        $this->vapidEnabled = true;

        return $this;
    }

    public function send(Notification $notification, Subscription $subscription): StatusReport
    {
        return $this->service->send($notification, $subscription);
    }

    private function getJwsProvider(string $publicKey, string $privateKey): JWSProvider
    {
        switch (true) {
            case class_exists(ES256::class):
                return WebTokenProvider::create($publicKey, $privateKey);
            case class_exists(Sha256::class):
                return LcobucciProvider::create($publicKey, $privateKey);
            default:
                throw new InvalidArgumentException('Please install "web-token/jwt-signature-algorithm-ecdsa" or "lcobucci/jwt" to use this feature');
        }
    }
}
