# WebPush

> Web Push library for PHP

[![Build Status](https://github.com/web-push-libs/web-push-php/actions/workflows/tests.yml/badge.svg)](https://github.com/web-push-libs/web-push-php/actions/workflows/tests.yml)

WebPush can be used to send push messages to endpoints as described in the [Web Push protocol](https://datatracker.ietf.org/doc/html/rfc8030).

This push message is then received by the browser, which can then create a notification using the [service worker](https://developer.mozilla.org/en-US/docs/Web/API/Service_Worker_API) and the [Notifications API](https://developer.mozilla.org/en-US/docs/Web/API/Notifications_API).

## Requirements

PHP 8.2+ and the following extensions:

- bcmath and/or gmp (optional but better for performance)
- mbstring
- curl
- openssl (with elliptic curve support)

There is no support and maintenance for older PHP versions, however you are free to use the following compatible versions:

- PHP 5.6 or HHVM: `v1.x`
- PHP 7.0: `v2.x`
- PHP 7.1: `v3.x-v5.x`
- PHP 7.2: `v6.x`
- PHP 7.3 7.4: `v7.x`
- PHP 8.0 / Openssl without elliptic curve support: `v8.x`
- PHP 8.1: `v9.x`

This README is only compatible with the latest version. Each version of the library has a git tag where the corresponding README can be read.

## Installation

Use [composer](https://getcomposer.org/) to download and install the library and its dependencies.

```bash
composer require minishlink/web-push
```

## Usage

### Example

A complete example with html+JS frontend and php backend using `web-push-php` can be found here: [Minishlink/web-push-php-example](https://github.com/Minishlink/web-push-php-example)

### Send Push Message

```php
<?php

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

// store the client-side `PushSubscription` object (calling `.toJSON` on it) as-is and then create a WebPush\Subscription from it
$subscription = Subscription::create(json_decode($clientSidePushSubscriptionJSON, true));

// array of notifications
$notifications = [
    [
        'subscription' => $subscription,
        'payload' => '{"message":"Hello World!"}',
    ], [
          // current PushSubscription format (browsers might change this in the future)
          'subscription' => Subscription::create([
              "endpoint" => "https://example.com/other/endpoint/of/another/vendor/abcdef...",
              "keys" => [
                  'p256dh' => '(stringOf88Chars)',
                  'auth' => '(stringOf24Chars)'
              ],
              // key 'contentEncoding' is optional and defaults to Subscription::defaultContentEncoding
          ]),
          'payload' => '{"message":"Hello World!"}',
    ], [
        // old Firefox PushSubscription format
        'subscription' => Subscription::create([
            'endpoint' => 'https://updates.push.services.mozilla.com/push/abc...', // Firefox 43+,
            'publicKey' => 'BPcMbnWQL5GOYX/5LKZXT6sLmHiMsJSiEvIFvfcDvX7IZ9qqtq68onpTPEYmyxSQNiH7UD/98AUcQ12kBoxz/0s=', // base 64 encoded, should be 88 chars
            'authToken' => 'CxVX6QsVToEGEcjfYPqXQw==', // base 64 encoded, should be 24 chars
        ]),
        'payload' => 'hello !',
    ], [
        // old Chrome PushSubscription format
        'subscription' => Subscription::create([
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abcdef...',
        ]),
        'payload' => null,
    ], [
        // old PushSubscription format
        'subscription' => Subscription::create([
            'endpoint' => 'https://example.com/other/endpoint/of/another/vendor/abcdef...',
            'publicKey' => '(stringOf88Chars)',
            'authToken' => '(stringOf24Chars)',
            'contentEncoding' => 'aesgcm', // one of PushManager.supportedContentEncodings
        ]),
        'payload' => '{"message":"test"}',
    ]
];

$webPush = new WebPush();

// send multiple notifications with payload
foreach ($notifications as $notification) {
    $webPush->queueNotification(
        $notification['subscription'],
        $notification['payload'] // optional (defaults null)
    );
}

/**
 * Check sent results
 * @var MessageSentReport $report
 */
foreach ($webPush->flush() as $report) {
    $endpoint = $report->getRequest()->getUri()->__toString();

    if ($report->isSuccess()) {
        echo "[v] Message sent successfully for subscription {$endpoint}.";
    } else {
        echo "[x] Message failed to sent for subscription {$endpoint}: {$report->getReason()}";
    }
}

/**
 * send one notification and flush directly
 * @var MessageSentReport $report
 */
$report = $webPush->sendOneNotification(
    $notifications[0]['subscription'],
    $notifications[0]['payload'], // optional (defaults null)
);
```

### Authentication (VAPID)

Browsers need to verify your identity. A standard called VAPID can authenticate you for all browsers. You'll need to create and provide a public and private key for your server. These keys must be safely stored and should not change.

You can specify your authentication details when instantiating WebPush. The keys can be passed directly (recommended), or you can load a PEM file or its content:

```php
<?php

use Minishlink\WebPush\WebPush;

$endpoint = 'https://fcm.googleapis.com/fcm/send/abcdef...'; // Chrome

$auth = [
    'VAPID' => [
        'subject' => 'mailto:me@website.com', // can be a mailto: or your website address
        'publicKey' => '~88 chars', // (recommended) uncompressed public key P-256 encoded in Base64-URL
        'privateKey' => '~44 chars', // (recommended) in fact the secret multiplier of the private key encoded in Base64-URL
        'pemFile' => 'path/to/pem', // if you have a PEM file and can link to it on your filesystem
        'pem' => 'pemFileContent', // if you have a PEM file and want to hardcode its content
    ],
];

$webPush = new WebPush($auth);
$webPush->queueNotification(...);
```

In order to generate the uncompressed public and secret key, encoded in Base64, enter the following in your Linux bash:

```bash
$ openssl ecparam -genkey -name prime256v1 -out private_key.pem
$ openssl ec -in private_key.pem -pubout -outform DER|tail -c 65|base64|tr -d '=' |tr '/+' '_-' >> public_key.txt
$ openssl ec -in private_key.pem -outform DER|tail -c +8|head -c 32|base64|tr -d '=' |tr '/+' '_-' >> private_key.txt
```

If you can't access a Linux bash, you can print the output of the `createVapidKeys` function:

```php
var_dump(VAPID::createVapidKeys()); // store the keys afterwards
```

On the client-side, don't forget to subscribe with the VAPID public key as the `applicationServerKey`: (`urlBase64ToUint8Array` source [here](https://github.com/Minishlink/physbook/blob/02a0d5d7ca0d5d2cc6d308a3a9b81244c63b3f14/app/Resources/public/js/app.js#L177))

```js
serviceWorkerRegistration.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: urlBase64ToUint8Array(vapidPublicKey)
})
```

#### Reusing VAPID headers

VAPID headers make use of a JSON Web Token (JWT) to verify your identity. That token payload includes the protocol and hostname of the endpoint included in the subscription and an expiration timestamp (usually between 12-24h), and it's signed using your public and private key. Given that, two notifications sent to the same push service will use the same token, so you can reuse them for the same flush session to boost performance using:

```php
$webPush->setReuseVAPIDHeaders(true);
```

### Notifications and default options

Each notification can have a specific Time To Live, urgency, and topic.
The WebPush standard states that `urgency` is optional but some users reports that Safari throws errors when it is not specified. This might be fixed in the future.
You can change the default options with `setDefaultOptions()` or in the constructor:

```php
<?php

use Minishlink\WebPush\WebPush;

$defaultOptions = [
    'TTL' => 300, // defaults to 4 weeks
    'urgency' => 'normal', // protocol defaults to "normal". (very-low, low, normal, or high)
    'topic' => 'newEvent', // not defined by default. Max. 32 characters from the URL or filename-safe Base64 characters sets
    'batchSize' => 200, // defaults to 1000
    'contentType' => 'application/json', // defaults to "application/octet-stream"
];

// for every notification
$webPush = new WebPush([], $defaultOptions);
$webPush->setDefaultOptions($defaultOptions);

// or for one notification
$webPush->sendOneNotification($subscription, $payload, ['TTL' => 5000]);
```

#### TTL

Time To Live (TTL, in seconds) is how long a push message is retained by the push service (eg. Mozilla) in case the user browser
is not yet accessible (eg. is not connected). You may want to use a very long time for important notifications. The default TTL is 4 weeks.
However, if you send multiple nonessential notifications, set a TTL of 0: the push notification will be delivered only
if the user is currently connected. For other cases, you should use a minimum of one day if your users have multiple time
zones, and if they don't several hours will suffice.

#### urgency

Urgency can be either "very-low", "low", "normal", or "high". If the browser vendor has implemented this feature, it will save battery life on mobile devices (cf. [protocol](https://datatracker.ietf.org/doc/html/rfc8030#section-5.3)).

#### topic

This string will make the vendor show to the user only the last notification of this topic (cf. [protocol](https://datatracker.ietf.org/doc/html/rfc8030#section-5.4)).

#### batchSize

If you send tens of thousands notifications at a time, you may get memory overflows due to how endpoints are called in Guzzle.
In order to fix this, WebPush sends notifications in batches. The default size is 1000. Depending on your server configuration (memory), you may want
to decrease this number. Do this while instantiating WebPush or calling `setDefaultOptions`. Or, if you want to customize this for a specific flush, give
it as a parameter : `$webPush->flush($batchSize)`.

#### contentType

Sets the "Content-Type" header for HTTP requests with a non-empty payload sent to the push service. 
Especially newer [Declarative push messages](https://www.w3.org/TR/push-api/#declarative-push-message) require a specific JSON payload, so this should be set to "application/json" in such cases.

### Server errors

You can see what the browser vendor's server sends back in case it encountered an error (push subscription expiration, wrong parameters...).

* `sendOneNotification()` returns a [`MessageSentReport`](https://github.com/web-push-libs/web-push-php/blob/master/src/MessageSentReport.php)
* `flush()` returns a [`\Generator`](https://www.php.net/manual/en/language.generators.php) with [`MessageSentReport`](https://github.com/web-push-libs/web-push-php/blob/master/src/MessageSentReport.php) objects. To loop through the results, just pass it into `foreach`. You can also use [`iterator_to_array`](https://php.net/manual/en/function.iterator-to-array.php) to check the contents while debugging.

```php
<?php

/** @var \Minishlink\WebPush\MessageSentReport $report */
foreach ($webPush->flush() as $report) {
    $endpoint = $report->getEndpoint();

    if ($report->isSuccess()) {
        echo "[v] Message sent successfully for subscription {$endpoint}.";
    } else {
        echo "[x] Message failed to sent for subscription {$endpoint}: {$report->getReason()}";

        // also available (to get more info)

        /** @var \Psr\Http\Message\RequestInterface $requestToPushService */
        $requestToPushService = $report->getRequest();

        /** @var \Psr\Http\Message\ResponseInterface $responseOfPushService */
        $responseOfPushService = $report->getResponse();

        /** @var string $failReason */
        $failReason = $report->getReason();

        /** @var bool $isTheEndpointWrongOrExpired */
        $isTheEndpointWrongOrExpired = $report->isSubscriptionExpired();
    }
}
```

**PLEASE NOTE:** You can only iterate **once** over the `\Generator` object.

Firefox errors are listed in the [autopush documentation](https://mozilla-services.github.io/autopush-rs/errors.html).

### Payload length, security, and performance

Payloads are encrypted by the library. The maximum payload length is theoretically 4078 bytes (or ASCII characters).
For [compatibility reasons (archived)](https://github.com/mozilla-services/autopush/issues/748) though, your payload should be less than 3052 bytes long.

The library pads the payload by default. This is more secure but it decreases performance for both your server and your user's device.

#### Why is it more secure?

When you encrypt a string of a certain length, the resulting string will always have the same length,
no matter how many times you encrypt the initial string. This can make attackers guess the content of the payload.
In order to circumvent this, this library adds some null padding to the initial payload, so that all the input of the encryption process
will have the same length. This way, all the output of the encryption process will also have the same length and attackers won't be able to
guess the content of your payload.

#### Why does it decrease performance?

Encrypting more bytes takes more runtime on your server, and also slows down the user's device with decryption. Moreover, sending and receiving the packet will take more time.
It's also not very friendly with users who have limited data plans.

#### How can I disable or customize automatic padding?

You can customize automatic padding in order to better fit your needs.

Here are some ideas of settings:

* (default) `Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH` (2820 bytes) for compatibility purposes with Firefox for Android (See [#108](https://github.com/web-push-libs/web-push-php/issues/108))
* `Encryption::MAX_PAYLOAD_LENGTH` (4078 bytes) for maximum security
* `false` for maximum performance
* If you know your payloads will not exceed `X` bytes, then set it to `X` for the best balance between security and performance.

```php
<?php

use Minishlink\WebPush\WebPush;

$webPush = new WebPush();
$webPush->setAutomaticPadding(false); // disable automatic padding
$webPush->setAutomaticPadding(512); // enable automatic padding to 512 bytes (you should make sure that your payload is less than 512 bytes, or else an attacker could guess the content)
$webPush->setAutomaticPadding(true); // enable automatic padding to default maximum compatibility length
```

### Customizing the HTTP client

WebPush uses [Guzzle](https://github.com/guzzle/guzzle). It will use the most appropriate client it finds,
and most of the time it will be `MultiCurl`, which allows to send multiple notifications in parallel.

You can customize the default request options and timeout when instantiating WebPush:

```php
<?php

use Minishlink\WebPush\WebPush;

$timeout = 20; // seconds
$clientOptions = [
    \GuzzleHttp\RequestOptions::ALLOW_REDIRECTS => false,
]; // see \GuzzleHttp\RequestOptions
$webPush = new WebPush([], [], $timeout, $clientOptions);
```

## Common questions (FAQ)

### Is there any plugin/bundle/extension for my favorite PHP framework?

The following are available:

- Symfony:
    - [MinishlinkWebPushBundle](https://github.com/Minishlink/web-push-bundle)
    - [bentools/webpush-bundle](https://github.com/bpolaszek/webpush-bundle) (associate your Symfony users to WebPush subscriptions)
- Laravel: [laravel-notification-channels/webpush](https://github.com/laravel-notification-channels/webpush)
- WordPress plugin: [Perfecty Push Notifications](https://github.com/rwngallego/perfecty-push-wp/)

Feel free to add your own!

### What about security?

Payload is encrypted according to the [Message Encryption for Web Push](https://datatracker.ietf.org/doc/html/rfc8291) standard,
using the user public key and authentication secret that you can get by following the [Web Push API](https://www.w3.org/TR/push-api/) specification.

Internally, WebPush uses the [WebToken](https://github.com/web-token) framework and OpenSSL to handle encryption keys generation and encryption.

### How do I scale?

Here are some ideas:

1. Make sure MultiCurl is available on your server
2. Find the right balance for your needs between security and performance (see above)
3. Find the right batch size (set it in `defaultOptions` or as parameter to `flush()`)
4. Use `flushPooled()` instead of `flush()`. The former uses concurrent requests, accelerating the process and often doubling the speed of the requests.

### How to solve "SSL certificate problem: unable to get local issuer certificate"?

Your installation lacks some certificates.

1. Download [cacert.pem](https://curl.haxx.se/ca/cacert.pem).
2. Edit your `php.ini`: after `[curl]`, type `curl.cainfo = /path/to/cacert.pem`.

You can also force using a client without peer verification.

### How to solve "Class 'Minishlink\WebPush\WebPush' not found"

Make sure to require Composer's [autoloader](https://getcomposer.org/doc/01-basic-usage.md#autoloading).

```php
require __DIR__ . '/path/to/vendor/autoload.php';
```

### I get authentication errors when sending notifications

Make sure to have database fields that are large enough for the length of the data you are storing ([#233](https://github.com/web-push-libs/web-push-php/issues/233#issuecomment-1252617883)). For the endpoint, users have reported that the URL length does not exceed 500 characters, but this can evolve so you can set it to the 2048 characters limit of most browsers.

### I lost my VAPID keys!

See [issue #58](https://github.com/web-push-libs/web-push-php/issues/58).

### I'm using Google Cloud Messaging (GCM), how do I use this library?

This service does not exist anymore.
It has been superseded by Google's Firebase Cloud Messaging (FCM) on May 29, 2019.

### I'm using Firebase Cloud Messaging (FCM), how do I use this library?

This library does not support Firebase Cloud Messaging (FCM).
Old Chrome subscriptions (prior 2018 and VAPID) do use Legacy HTTP protocol by Firebase Cloud Messaging (FCM) which is [deprecated](https://firebase.google.com/support/faq#fcm-23-deprecation) since 2023 and will stop working in June 2024.
The support for this outdated subscription is removed.

Please do not be confused as Legacy HTTP protocol and Web Push with VAPID use the identical endpoint URL:

> https://fcm.googleapis.com/fcm/send

Web Push with VAPID will remain available at this URL.
No further action is currently required.

### How to send data?

The browser vendors do not allow to send data using the Push API without creating a notification.
Use some alternative APIs like WebSocket/WebTransport or Background Synchronization.

### I need to send notifications to native apps. (eg. APNS for iOS)

WebPush is for web apps.
You need something like [RMSPushNotificationsBundle](https://github.com/richsage/RMSPushNotificationsBundle) (Symfony).

### This is PHP... I need Javascript!

This library was inspired by the Node.js [web-push-libs/web-push](https://github.com/web-push-libs/web-push) library.

## Reference

### Examples, Notes and Overviews

- Google's [introduction to push notifications](https://web.dev/explore/notifications)
- Mozilla [Push API (browser side)](https://developer.mozilla.org/en-US/docs/Web/API/Push_API)
- Apple [Safari](https://developer.apple.com/documentation/usernotifications/sending_web_push_notifications_in_web_apps_and_browsers)
- (Archive) Matthew Gaunt's [Web Push Book](https://web-push-book.gauntface.com)
- (Archive) Mozilla's [ServiceWorker Cookbooks](https://github.com/mdn/serviceworker-cookbook/blob/master/push-payload/README.md) (don't mind the `server.js` file: it should be replaced by your PHP server code with this library)

### Internet Engineering Task Force (IETF)

- Generic Event Delivery Using HTTP Push [RFC8030](https://www.rfc-editor.org/rfc/rfc8030.html)
- Message Encryption for Web Push [RFC8291](https://www.rfc-editor.org/rfc/rfc8291)
- Voluntary Application Server Identification (VAPID) for Web Push [RFC8292](https://www.rfc-editor.org/rfc/rfc8292)

### W3C

- Working Draft [Push API](https://www.w3.org/TR/push-api/)

## License

[MIT](https://github.com/web-push-libs/web-push-php/blob/master/LICENSE)
