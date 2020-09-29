<?php

declare(strict_types=1);

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2014-2020 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

namespace Minishlink\Tests\Benchmark;

use Jose\Component\Core\JWK;
use Minishlink\WebPush\ExtensionManager;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\Payload\AESGCM;
use Minishlink\WebPush\Payload\PayloadExtension;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\TopicExtension;
use Minishlink\WebPush\TTLExtension;
use Minishlink\WebPush\UrgencyExtension;
use Minishlink\WebPush\VAPID\VAPID;
use Minishlink\WebPush\VAPID\WebTokenProvider;
use Minishlink\WebPush\WebPush;
use Http\Mock\Client;
use Nyholm\Psr7\Factory\Psr17Factory;
use PhpBench\Benchmark\Metadata\Annotations\Revs;
use PhpBench\Benchmark\Metadata\Annotations\Subject;

/**
 * @BeforeMethods({"init"})
 * @Revs(4096)
 */
class WebPushBench
{
    private WebPush $webPush;
    private Notification $notification;
    private Subscription $subscription;

    public function init(): void
    {
        $client = new Client();
        $psr17Factory = new Psr17Factory();

        $vapidKey = JWK::createFromJson('{"kty":"EC","crv":"P-256","d":"fiDSHFnef96_AX-BI5m6Ew2uiW-CIqoKtKnrIAeDRMI","x":"Xea1H6hwYhGqE4vBHcW8knbx9sNZsnXHwgikrpWyLQI","y":"Kl7gDKfzYe_TFJWHxDNDU1nhBB2nzx9OTlGcF4G7Z2w"}');

        $jwsProvider = new WebTokenProvider(
            'http://localhost:8000',
            'mailto:foo@bar.com',
            $vapidKey
        );

        $payloadExtension = new PayloadExtension();
        $payloadExtension
            ->addContentEncoding(new AESGCM(
                'vplfkITvu0cwHqzK9Kj-DYStbCH_9AhGx9LqMyaeI6w',
                'BMBlr6YznhYMX3NgcWIDRxZXs0sh7tCv7_YCsWcww0ZCv9WGg-tRCXfMEHTiBPCksSqeve1twlbmVAZFv7GSuj0'
            ))
        ;

        $vapidExtension = new VAPID($jwsProvider);

        $extensionManager = new ExtensionManager();
        $extensionManager
            ->add(new TTLExtension())
            ->add(new TopicExtension())
            ->add(new UrgencyExtension())
            ->add($vapidExtension)
            ->add($payloadExtension)
        ;

        $this->webPush = new WebPush($client, $psr17Factory, $extensionManager);

        $this->subscription = Subscription::createFromString('{"endpoint":"https://updates.push.services.mozilla.com/wpush/v2/gAAAAABfcsdu1p9BdbYIByt9F76MHcSiuix-ZIiICzAkU9z_p0gnolYLMOi71rqss5pMOZuYJVZLa7rRN58uOgfdsux7k51Ph6KJRFEkf1LqTRMv2d8OhQaL2TR36WUR2d5twzYVwcQJAnTLrhVrWqKVo8ekAonuwyFHDUGzD8oUWpFTK9y2F68","keys":{"auth":"wSfP1pfACMwFesCEfJx4-w","p256dh":"BIlDpD05YLrVPXfANOKOCNSlTvjpb5vdFo-1e0jNcbGlFrP49LyOjYyIIAZIVCDAHEcX-135b859bdsse-PgosU"},"contentEncoding":"aesgcm"}');
    }

    /**
     * @Subject
     */
    public function sendNotificationWithoutPayload(): void
    {
        $this->notification = Notification::create();
        $this->webPush->send($this->notification, $this->subscription);
    }

    /**
     * @Subject
     */
    public function sendNotificationWithPayload(): void
    {
        $this->notification = Notification::create()
            ->withPayload('Lorem ipsum dolor sit amet, consectetur adipiscing elit. Maecenas nisi justo, cursus sed fringilla at, mollis ac velit. Duis vulputate libero eget luctus posuere. Nam in ex turpis. Nullam commodo elit tortor. Phasellus ipsum sapien, venenatis non tellus et, ullamcorper faucibus felis. Nullam quis eleifend diam, ut tincidunt nibh. Ut massa lectus, imperdiet a mollis sed, tempor a arcu. Nulla facilisi.')
        ;
        $this->webPush->send($this->notification, $this->subscription);
    }
}
