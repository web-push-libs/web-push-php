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
use Minishlink\WebPush\Payload\AES128GCM;
use Minishlink\WebPush\Payload\AESGCM;
use Minishlink\WebPush\Payload\PayloadExtension;
use Minishlink\WebPush\VAPID\VAPIDExtension;
use Minishlink\WebPush\VAPID\WebTokenProvider;
use Psr\Cache\CacheItemPoolInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;

class SimpleWebPush
{
    private WebPush $service;
    private AES128GCM $aes128gcm;
    private AESGCM $aesgcm;
    private ExtensionManager $extensionManager;
    private PayloadExtension $payloadExtension;
    private TTLExtension $ttlExtension;
    private TopicExtension $topicExtension;
    private UrgencyExtension $urgencyExtension;
    private PreferAsyncExtension $preferAsyncExtension;
    private ?VAPIDExtension $vapidExtension = null;
    private ?WebTokenProvider $jwsProvider = null;

    public function __construct(ClientInterface $client, RequestFactoryInterface $requestFactory)
    {
        $this->aes128gcm = AES128GCM::create();
        $this->aesgcm = AESGCM::create();
        $this->payloadExtension = PayloadExtension::create()
            ->addContentEncoding($this->aesgcm)
            ->addContentEncoding($this->aes128gcm)
        ;
        $this->ttlExtension = TTLExtension::create();
        $this->topicExtension = TopicExtension::create();
        $this->urgencyExtension = UrgencyExtension::create();
        $this->preferAsyncExtension = PreferAsyncExtension::create();

        $this->extensionManager = ExtensionManager::create()
            ->add($this->ttlExtension)
            ->add($this->topicExtension)
            ->add($this->urgencyExtension)
            ->add($this->preferAsyncExtension)
            ->add($this->payloadExtension)
        ;
        $this->service = WebPush::create($client, $requestFactory, $this->extensionManager);
    }

    public static function create(ClientInterface $client, RequestFactoryInterface $requestFactory): self
    {
        return new self($client, $requestFactory);
    }

    public function enableVapid(string $subject, string $publicKey, string $privateKey): self
    {
        $this->jwsProvider = WebTokenProvider::create($publicKey, $privateKey);
        Assertion::null($this->vapidExtension, 'VAPID has already been enabled');
        $this->vapidExtension = VAPIDExtension::create($subject, $this->jwsProvider);
        $this->extensionManager->add($this->vapidExtension);

        return $this;
    }

    public function setLogger(LoggerInterface $logger): self
    {
        $this->service->setLogger($logger);
        $this->aes128gcm->setLogger($logger);
        $this->aesgcm->setLogger($logger);
        $this->extensionManager->setLogger($logger);
        $this->payloadExtension->setLogger($logger);
        $this->ttlExtension->setLogger($logger);
        $this->topicExtension->setLogger($logger);
        $this->urgencyExtension->setLogger($logger);
        $this->preferAsyncExtension->setLogger($logger);
        if (null !== $this->vapidExtension) {
            $this->vapidExtension->setLogger($logger);
        }
        if (null !== $this->jwsProvider) {
            $this->jwsProvider->setLogger($logger);
        }

        return $this;
    }

    public function setCache(CacheItemPoolInterface $cache): self
    {
        $this->aes128gcm->setCache($cache);
        $this->aesgcm->setCache($cache);
        if (null !== $this->vapidExtension) {
            $this->vapidExtension->setCache($cache);
        }

        return $this;
    }

    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): self
    {
        $this->service->setEventDispatcher($eventDispatcher);

        return $this;
    }

    public function send(Notification $notification, Subscription $subscription): StatusReport
    {
        return $this->service->send($notification, $subscription);
    }
}
