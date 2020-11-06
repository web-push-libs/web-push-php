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
use Minishlink\WebPush\Message;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\SimpleWebPush;
use Minishlink\WebPush\Subscription;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\HttpClient\Psr18Client;

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


// PSR-17 Request Factory
$psr17Factory = new Psr17Factory();

// PSR-18 Client
$client = new Psr18Client();

// VAPID Keys
$publicKey = 'BB4W1qfBi7MF_Lnrc6i2oL-glAuKF4kevy9T0k2vyKV4qvuBrN3T6o9-7-NR3mKHwzDXzD3fe7XvIqIU1iADpGQ';
$privateKey = 'C40jLFSa5UWxstkFvdwzT3eHONE2FIJSEsVIncSCAqU';

$report = SimpleWebPush::create($client, $psr17Factory)
    ->enableVapid('http://localhost:9000', $publicKey, $privateKey)
    ->send($notification,$subscription)
;
