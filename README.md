# WebPush
> Web Push library for PHP

WebPush can be used to send notifications to endpoints which server delivers Web Push notifications as described in 

* The [RFC8030: “Generic Event Delivery Using HTTP Push“](https://tools.ietf.org/html/rfc8030)
* The [RFC8291: “Message Encryption for Web Push“](https://tools.ietf.org/html/rfc8291)
* The [RFC8292: “Voluntary Application Server Identification (VAPID) for Web Push“](https://tools.ietf.org/html/rfc8292)

In addition, some features from the [Push API](https://w3c.github.io/push-api/) are implemented.
This specification is a working draft at the time of writing (2020-11).

## Requirements

* Mandatory
    * PHP 7.4+
    * A PSR-17 (HTTP Message Factory) implementation
    * A PSR-18 (HTTP Client) implementation
    * `json`

* Depending on the extensions you use
    * When using VAPID extension (*highly recommended*):
        * Required: 
            * `openssl`
            * `mbstring`
           * a JWT Provider. This library provides implementations for [`web-token`](https://web-token.spomky-labs.com) and [`lcobucci/jwt`](https://github.com/lcobucci/jwt)
        * Optional:
            * a PSR-6 (Caching Interface) implementation (*highly recommended*)
    * When using Payload extensions (notifications with a payload):
        * Required: 
            * `openssl`
            * `mbstring`
        * Optional:
            * a PSR-6 (Caching Interface) implementation (*highly recommended*)
    * A PSR-3 (Logger Interface) implementation for debugging

**There is no support and maintenance for older PHP versions.**

## Installation

Use [composer](https://getcomposer.org/) to download and install the library and its dependencies.

`composer require minishlink/web-push`

## Usage

```php
<?php

use Minishlink\WebPush\ExtensionManager;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\WebPush;

//PSR-18 HTTP client
/** @var Psr\Http\Client\ClientInterface $client */
$client = '…';

//PSR-17 request factory
/** @var Psr\Http\Message\RequestFactoryInterface $requestFactory */
$requestFactory = '…';

// Extension Manager is detailed later
$extensionManager = new ExtensionManager();

// The WebPush service
$webPush = new WebPush(
    $client,          //PSR-18 HTTP client
    $requestFactory,  //PSR-17 request factory
    $extensionManager //Will be explained later
);


// In this example, we consider you already received a subscription object
// from the user agent
$subscription = Subscription::createFromString('{"endpoint":"…"}');

// We create a notification
// No payload or other information at this stage.
// These features will be explained later
$notification = Notification::create();

// We send the notification
$statusReport = $webPush->send($notification, $subscription);

//The status report is either a Success or a Failure one.
```

## The Subscription

The subscription is created on client side when the end-user allows your application to send push messages.
You can simply send the object you receive using `JSON.stringify`.

A subscription object will look like:

```json
{
    "endpoint":"https://updates.push.services.mozilla.com/wpush/v2/AAAAAAAA[…]AAAAAAAAA",
    "keys":{
        "auth":"XXXXXXXXXXXXXX",
        "p256dh":"YYYYYYYY[…]YYYYYYYYYYYYY"
    }
}
```

On server side, you can directly use the dedicated method `Subscription::createFromString` as showed in the example above.

## Extensions

As some core concepts are still not approved or mature enough at the time of writing,
extensions have been introduced to allow smooth integration over the evolution of the specifications. 

**Please note that some extensions are mandatory**.

### Core Extensions

#### TTL (Time-To-Live)

With this extension, a value in seconds is added to the notification.
It suggests how long a push message is retained by the push service.
A value of 0 (zero) indicates the notification is delivered immediately.

```php
<?php

use Minishlink\WebPush\ExtensionManager;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\TTLExtension;

$extensionManager = new ExtensionManager();
$extensionManager->add(new TTLExtension());

$notification = Notification::create()
    ->withTTL(3600)
;
```

#### Topic

A push message that has been stored by the push service can be replaced with new content.
If the user agent is offline during the time the push messages are sent,
updating a push message avoids the situation where outdated or redundant messages are sent to the user agent.

Only push messages that have been assigned a topic can be replaced.
A push message with a topic replaces any outstanding push message with an identical topic.

```php
<?php

use Minishlink\WebPush\ExtensionManager;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\TopicExtension;

$extensionManager = new ExtensionManager();
$extensionManager->add(new TopicExtension());

$notification = Notification::create()
    ->withTopic('user-account-updated')
;
```

#### Urgency

For a device that is battery-powered, it is often critical it remains dormant for extended periods.
Radio communication in particular consumes significant power and limits the length of time the device can operate.

To avoid consuming resources to receive trivial messages,
it is helpful if an application server can communicate the urgency of a message and if the user agent can request
that the push server only forwards messages of a specific urgency.

| Urgency  | Device State                | Examples                                        |
|----------|-----------------------------|-------------------------------------------------|
| very-low | On power and Wi-Fi          | Advertisements                                  |
| low      | On either power or Wi-Fi    | Topic updates                                   |
| normal   | On neither power nor Wi-Fi  | Chat or Calendar Message                        |
| high     | Low battery                 | Incoming phone call or time-sensitive alert     |

```php
<?php

use Minishlink\WebPush\ExtensionManager;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\UrgencyExtension;

$extensionManager = new ExtensionManager();
$extensionManager->add(new UrgencyExtension());

$notification = Notification::create()
    ->veryLow()
    ->low()
    ->normal()
    ->high()
;
```

### VAPID

“VAPID” stands for “Voluntary Application Server Identification”.
This feature allows to application server to send information about itself to a push service.

A consistent identity can be used by a push service to establish behavioral expectations for an application server.
Significant deviations from an established norm can then be used to trigger exception-handling procedures.
Voluntarily provided contact information can be used to contact an application server operator in the case of exceptional situations.

Additionally, the design of [RFC8030] relies on maintaining the secrecy of push message subscription URIs.
Any application server in possession of a push message subscription URI is able to send messages to the user agent.
If use of a subscription could be limited to a single application server, this would reduce the impact
of the push message subscription URI being learned by an unauthorized party.

To use this extension, you need a JWTProvider. This service will generate signed Json Web Tokens (JWS)
that will be added to the request sent to the push service.

The library provides implementations for the following third party libraries. Feel free to choose one or the other.

* [`web-token`](https://github.com/web-token/jwt-framework): `composer require web-token/jwt-signature-algorithm-ecdsa`
* [`lcobucci/jwt`](https://github.com/lcobucci/jwt): `composer require lcobucci/jwt`

```php
<?php

use Jose\Component\Core\JWK;
use Minishlink\WebPush\ExtensionManager;
use Minishlink\WebPush\VAPID\VAPID;
use Minishlink\WebPush\VAPID\WebTokenProvider;
use Minishlink\WebPush\VAPID\LcobucciProvider;

// With Web-Token
$vapidKey = JWK::createFromJson('{"kty":"EC","crv":"P-256","d":"fiDSHFnef96_AX-BI5m6Ew2uiW-CIqoKtKnrIAeDRMI","x":"Xea1H6hwYhGqE4vBHcW8knbx9sNZsnXHwgikrpWyLQI","y":"Kl7gDKfzYe_TFJWHxDNDU1nhBB2nzx9OTlGcF4G7Z2w"}');
$jwsProvider = new WebTokenProvider($vapidKey);

// With Lcobucci
$publicKey = 'BNFEvAnv7SfVGz42xFvdcu-z-W_3FVm_yRSGbEVtxVRRXqCBYJtvngQ8ZN-9bzzamxLjpbw7vuHcHTT2H98LwLM';
$privateKey = 'TcP5-SlbNbThgntDB7TQHXLslhaxav8Qqdd_Ar7VuNo';
$jwsProvider = new LcobucciProvider($publicKey, $privateKey);


$vapidExtension = new VAPID('http://example.com', $jwsProvider); // You can use an URL or an e-mail address (mailto:…)
$vapidExtension
    ->setTokenExpirationTime('now +12h') // Optional. By default the token is valid for 1 hour.
;

$extensionManager = new ExtensionManager();
$extensionManager->add($vapidExtension);
```

This extension provides a caching feature that will allow the JWS to be reused and avoid JWS computation for each request.

```php
<?php

use Minishlink\WebPush\ExtensionManager;
use Minishlink\WebPush\VAPID\VAPID;

$cache = '…'; // PSR-6 service

$vapidExtension = new VAPID('http://example.com', $jwsProvider);
$vapidExtension
    ->setCache(
        $cache,    // PSR-6 caching service
        'now +10h' // Cache lifetime. Shall be lower than the token expiration time!
    )
;

$extensionManager = new ExtensionManager();
$extensionManager->add($vapidExtension);
```

### Payload

Sending notifications is nice, but you may need to send a payload to customize messages.
The payload may be a string, or a JSON object. The structure of the later is described in the next section.

The payload is encrypted on server side and decrypted by the browser.
To do so, you shall add Content Encoding services.
The specification defines two encodings that are very similar: `aesgcm` and `s128gcm`.
Please use both of them.

```php
<?php

use Minishlink\WebPush\ExtensionManager;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\Payload\PayloadExtension;
use Minishlink\WebPush\Payload\AES128GCM;
use Minishlink\WebPush\Payload\AESGCM;

$cache = '…'; // PSR-6 service

$aes128gcm = new AES128GCM();
$aes128gcm->setCache($cache); // Optional, but highly recommended

$aesgcm = new AESGCM();
$aesgcm->setCache($cache); // Optional, but highly recommended


$payloadExtension = new PayloadExtension();
$payloadExtension
    ->addContentEncoding($aes128gcm)
    ->addContentEncoding($aesgcm)
;

$extensionManager = new ExtensionManager();
$extensionManager->add($payloadExtension);

$notification = Notification::create()
    ->withPayload('Hello World!')
;
```

#### API Messages

You may have noticed that the specification [defines a structure for the payload](https://notifications.spec.whatwg.org/#notifications).
This structure contains properties that the client should be understood and render an appropriate way.

The library provides a `Minishlink\WebPush\Message` class with convenient methods to ease the creation of a message. 

```php
<?php

use Minishlink\WebPush\Action;
use Minishlink\WebPush\Message;
use Minishlink\WebPush\Notification;

$message = Message::create('Hello World!')
    ->mute() // Silent
    ->unmute() // Not silent (default)


    ->auto() //Direction = auto
    ->ltr() //Direction = left to right
    ->rtl() //Direction = right to left

    ->addAction(new Action('alert', 'TITLE', 'https://…'))

    ->interactionRequired()
    ->noInteraction()

    ->renotify()
    ->doNotRenotify()

    ->withIcon('https://…')
    ->withImage('https://…')
    ->withData(['foo' => 'BAR']) // Arbitrary data
    ->withBadge('badge1')
    ->withLang('fr-FR')
    ->withTimestamp(time())
    ->withTag('foo')

    ->vibrate(300, 100, 400)
;

$notification = Notification::create()
    ->withPayload((string) $message)
;
```

### Asynchronous Response

Your application may prefer asynchronous responses to request confirmation from the
push service when a push message is delivered and then acknowledged by the user agent.

The push service MUST support delivery confirmations to use this feature.

```php
<?php

use Minishlink\WebPush\ExtensionManager;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\PreferAsyncExtension;

$cache = '…'; // PSR-6 service

$extension = new PreferAsyncExtension();

$extensionManager = new ExtensionManager();
$extensionManager->add($extension);


$notification = Notification::create()
    ->async() // Prefer async response
    ->sync() // Prefer sync response (default)
;
```

## WebPush Service and States Reports

As you can see in the first example, a StatusReport object is returned.
It can be of two types:

* `Minishlink\WebPush\StatusReportSuccess`: the notification has correctly been sent to the subscriber.
* `Minishlink\WebPush\StatusReportFailure`: an error occurred.


In some cases, it could be interesting to dispatch the status report.

```php
<?php
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\WebPush;

$subscription = Subscription::createFromString('{"endpoint":"…"}');
$notification = Notification::create();

/** @var WebPush $webPush */
$statusReport = $webPush->send($notification, $subscription);
```

## Contributing
See [CONTRIBUTING.md](https://github.com/Minishlink/web-push/blob/master/CONTRIBUTING.md).

## License
[MIT](https://github.com/Minishlink/web-push/blob/master/LICENSE)

## Sponsors
Thanks to [JetBrains](https://www.jetbrains.com/) for supporting the project through sponsoring some [All Products Packs](https://www.jetbrains.com/products.html) within their [Free Open Source License](https://www.jetbrains.com/buy/opensource/) program.
