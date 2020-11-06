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

require_once __DIR__.'/vendor/autoload.php';

use Minishlink\WebPush\Action;
use Minishlink\WebPush\ExtensionManager;
use Minishlink\WebPush\Message;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\Payload\AES128GCM;
use Minishlink\WebPush\Payload\AESGCM;
use Minishlink\WebPush\Payload\PayloadExtension;
use Minishlink\WebPush\PreferAsyncExtension;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\TopicExtension;
use Minishlink\WebPush\TTLExtension;
use Minishlink\WebPush\UrgencyExtension;
use Minishlink\WebPush\VAPID\VAPIDExtension;
use Minishlink\WebPush\VAPID\WebTokenProvider;
use Minishlink\WebPush\WebPush;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\Psr18Client;

// PSR-3 Logger
$log = new Logger('WebPush');
$log->pushHandler(new StreamHandler(__DIR__.'/server.log'));

// PSR-6 Cache
$cache = new FilesystemAdapter('WebPush');

// PSR-14 Event Dispatcher
$eventDispatcher = new EventDispatcher();

// PSR-17 Request Factory
$psr17Factory = new Psr17Factory();

// PSR-18 Client
$client = new Psr18Client();

$serverPublicKey = 'BB4W1qfBi7MF_Lnrc6i2oL-glAuKF4kevy9T0k2vyKV4qvuBrN3T6o9-7-NR3mKHwzDXzD3fe7XvIqIU1iADpGQ';
$serverPrivateKey = 'C40jLFSa5UWxstkFvdwzT3eHONE2FIJSEsVIncSCAqU';

$payloadExtension = PayloadExtension::create()
    ->setLogger($log)
    ->addContentEncoding(AESGCM::create()->setCache($cache)->noPadding())
    ->addContentEncoding(AES128GCM::create()->setCache($cache)->noPadding())
;

$jwsProvider = WebTokenProvider::create($serverPublicKey, $serverPrivateKey)
    ->setLogger($log)
;
$vapidExtension = VAPIDExtension::create('http://localhost:8000', $jwsProvider)
    ->setLogger($log)
    ->setCache($cache)
    ->setTokenExpirationTime('now +2 hours')
;

$extensionManager = ExtensionManager::create()
    ->setLogger($log)
    ->add(new TTLExtension())
    ->add(new TopicExtension())
    ->add(new UrgencyExtension())
    ->add(new PreferAsyncExtension())
    ->add($vapidExtension)
    ->add($payloadExtension)
;

$message = Message::create('Hello World!')
    ->withLang('en-GB')
    ->interactionRequired()
    ->withTimestamp(time())
    ->addAction(Action::create('accept', 'Accept'))
    ->addAction(Action::create('cancel', 'Cancel'))
;


$notification = Notification::create()
    ->withTTL(0)
    ->withTopic('test')
    ->high()
    ->async()
    ->withPayload((string) $message)
;

$subscription = Subscription::createFromString(file_get_contents('php://input'));

WebPush::create($client, $psr17Factory, $extensionManager)
    ->setLogger($log)
    ->setEventDispatcher($eventDispatcher)
    ->send($notification, $subscription)
;
