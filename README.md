# WebPush
> Web Push library for PHP

[![Build Status](https://travis-ci.org/web-push-libs/web-push-php.svg?branch=master)](https://travis-ci.org/web-push-libs/web-push-php)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/d60e8eea-aea1-4739-8ce0-a3c3c12c6ccf/mini.png)](https://insight.sensiolabs.com/projects/d60e8eea-aea1-4739-8ce0-a3c3c12c6ccf)

WebPush can be used to send notifications to endpoints which server delivers Web Push notifications as described in 
the [Web Push protocol](https://tools.ietf.org/html/draft-thomson-webpush-protocol-00).
As it is standardized, you don't have to worry about what server type it relies on.

## Requirements

* PHP 7.1+ (_recommended 7.3 for increased performance_)
* [Async HTTP client](https://packagist.org/providers/php-http/async-client-implementation)
* [PSR-7 implementation](https://packagist.org/providers/psr/http-message-implementation)
* gmp
* mbstring
* curl
* openssl

There is no support and maintenance for older PHP versions, however you are free to use the following compatible versions:
- PHP 5.6 or HHVM: `v1.x`
- PHP 7.0: `v2.x`

## Installation
Use [composer](https://getcomposer.org/) to download and install the library and its dependencies.

`composer require minishlink/web-push php-http/guzzle2-adapter guzzlehttp/psr7`

## Usage
```php
<?php

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\SubscriptionFactory;

$notifications = [
    [
        'subscription' => SubscriptionFactory::create([
            'endpoint' => 'https://updates.push.services.mozilla.com/push/abc...', // Firefox 43+,
            'publicKey' => 'BPcMbnWQL5GOYX/5LKZXT6sLmHiMsJSiEvIFvfcDvX7IZ9qqtq68onpTPEYmyxSQNiH7UD/98AUcQ12kBoxz/0s=', // base 64 encoded, should be 88 chars
            'authToken' => 'CxVX6QsVToEGEcjfYPqXQw==', // base 64 encoded, should be 24 chars
        ]),
        'payload' => 'hello !',
    ], [
        'subscription' => SubscriptionFactory::create([
            'endpoint' => 'https://android.googleapis.com/gcm/send/abcdef...', // Chrome
        ]),
        'payload' => null,
    ], [
        'subscription' => SubscriptionFactory::create([
            'endpoint' => 'https://example.com/other/endpoint/of/another/vendor/abcdef...',
            'publicKey' => '(stringOf88Chars)',
            'authToken' => '(stringOf24Chars)',
            'contentEncoding' => 'aesgcm', // one of PushManager.supportedContentEncodings
        ]),
        'payload' => '{msg:"test"}',
    ], [
          'subscription' => SubscriptionFactory::create([ // this is the structure for the working draft from october 2018 (https://www.w3.org/TR/2018/WD-push-api-20181026/) 
              'endpoint' => 'https://example.com/other/endpoint/of/another/vendor/abcdef...',
              'keys' => [
                  'p256dh' => '(stringOf88Chars)',
                  'auth' => '(stringOf24Chars)'
              ],
          ]),
          'payload' => 'Hello World!',
      ],
];

$webPush = new WebPush();

// send multiple notifications with payload
foreach ($notifications as $notification) {
    $webPush->queueNotification(
        $notification['subscription'],
        $notification['payload'] ?? null // optional, string value (defaults null)
    );
}

/**
 * Check sent results
 * @var Minishlink\WebPush\MessageSentReport $report
 */
foreach ($webPush->deliver() as $report) {
    $endpoint = $report->getEndpoint();

    if ($report->isSuccess()) {
        echo "[v] Message sent successfully for subscription {$endpoint}.";
    } else {
        echo "[x] Message failed to send for subscription {$endpoint}: {$report->getReasonPhrase()}";
    }
}
```

### Full examples of Web Push implementations
* An example with web-push-php: [Minishlink/web-push-php-example](https://github.com/Minishlink/web-push-php-example)
* Matthew Gaunt's [Web Push Book](https://web-push-book.gauntface.com) - a must read
* Mozilla's [ServiceWorker Cookbooks](https://serviceworke.rs/push-payload_index_doc.html) (don't mind the `server.js` file: it should be replaced by your PHP server code with this library)
* Google's [introduction to push notifications](https://developers.google.com/web/fundamentals/getting-started/push-notifications/) (as of 03-20-2016, it doesn't mention notifications with payload)
* you may want to take a look at my own implementation: [sw.js](https://github.com/Minishlink/physbook/blob/9bfcc2bbf7311a5de4628eb8f3ae56b6c3e74067/web/service-worker.js) and [app.js](https://github.com/Minishlink/physbook/blob/02a0d5d7ca0d5d2cc6d308a3a9b81244c63b3f14/app/Resources/public/js/app.js)

### Authentication (VAPID)
Browsers need to verify your identity. A standard called VAPID can authenticate you for all browsers. You'll need to create and provide a public and private key for your server. These keys must be safely stored and should not change.

You can specify your authentication details when instantiating WebPush. The keys can be passed directly (recommended), or you can load a PEM file or its content:
```php
<?php

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Authorization;

$endpoint = 'https://android.googleapis.com/gcm/send/abcdef...'; // Chrome

$auth = new Authorization(
    'private_key', // ~44 chars, the secret multiplier of the private key encoded in Base64-URL
    'public_key', // ~88 chars, uncompressed public key P-256 encoded in Base64-URL
    'subject' // can be a mailto: or your website address
);

$webPush = new WebPush($auth);
$webPush->queueNotification(...);
```

In order to generate the uncompressed public and secret key, encoded in Base64, enter the following in your Linux bash:
```
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
$webPush->enableVapidHeaderReuse();
```

### Notifications and default options
Each notification can have a specific Time To Live, urgency, and topic.
You can specify options by specifying the `$options` parameter to the `WebPush` constructor, or override those by specifying the `$options` parameter to the `WebPush::queueNotification()` call:

```php
<?php

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Options;

$options = new Options([
    'ttl' => 300, // defaults to 4 weeks if left empty
    'urgency' => 'normal', // protocol defaults to "normal"
    'topic' => 'new_event', // not defined by default,
]);

$overrides = new Options([
    'ttl' => '600'
]);

// for every notifications
$webPush = new WebPush(null, $options);

// or, for one individual notification - this will also
// override the default options specified on $webpush
$webPush->queueNotification($subscription, $payload, $auth, $options);
```

#### TTL
Time To Live (TTL, in seconds) is how long a push message is retained by the push service (eg. Mozilla) in case the user browser 
is not yet accessible (eg. is not connected). You may want to use a very long time for important notifications. The default TTL is 4 weeks. 
However, if you send multiple nonessential notifications, set a TTL of 0: the push notification will be delivered only 
if the user is currently connected. For other cases, you should use a minimum of one day if your users have multiple time 
zones, and if they don't several hours will suffice.

#### urgency
Urgency can be either "very-low", "low", "normal", or "high". If the browser vendor has implemented this feature, it will save battery life on mobile devices (cf. [protocol](https://tools.ietf.org/html/draft-ietf-webpush-protocol-08#section-5.3)). 

#### topic
Similar to the old `collapse_key` on legacy GCM servers, this string will make the vendor show to the user only the last notification of this topic (cf. [protocol](https://tools.ietf.org/html/draft-ietf-webpush-protocol-08#section-5.4)).

### Server errors
You can see what the browser vendor's server sends back in case it encountered an error (push subscription expiration, wrong parameters...).
`WebPush::deliver()` **always** returns a [`\Generator`](http://php.net/manual/en/language.generators.php) with [`MessageSentReport`](https://github.com/web-push-libs/web-push-php/blob/master/src/MessageSentReport.php) objects.
To loop through the results, just pass it into `foreach`. You can also use [`iterator_to_array`](http://php.net/manual/en/function.iterator-to-array.php) to check the contents while debugging.

```php
<?php

/** @var Minishlink\WebPush\MessageSentReport $report */
foreach ($webPush->deliver() as $report) {
    $endpoint = $report->getEndpoint();

    if ($report->isSuccess()) {
        echo "[v] Message sent successfully for subscription {$endpoint}.";
    } else {
        echo "[x] Message failed to sent for subscription {$endpoint}: {$report->getReasonPhrase()}";
        
        // also available (to get more info)
        
        /** @var string $failReason */
        $failReason = $report->getReasonPhrase();
        
        /** @var bool $isTheEndpointWrongOrExpired */
        $isTheEndpointWrongOrExpired = $report->isSubscriptionExpired();
    }
}
```

**PLEASE NOTE:** You can only iterate **once** over the `\Generator` object.

Firefox errors are listed in the [autopush documentation](https://autopush.readthedocs.io/en/latest/http.html#errors).

### Payload length, security, and performance
Payloads are encrypted by the library. The maximum payload length is theoretically 4078 bytes (or ASCII characters).
For [compatibility reasons](mozilla-services/autopush/issues/748) though, your payload should be less than 3052 bytes long.

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
* (default) `Encryption::MAX_COMPATIBILITY_PAYLOAD_LENGTH` (3052 bytes) for compatibility purposes with Firefox for Android
* `Encryption::MAX_PAYLOAD_LENGTH` (4078 bytes) for maximum security
* `false` for maximum performance
* If you know your payloads will not exceed `X` bytes, then set it to `X` for the best balance between security and performance.

```php
<?php

use Minishlink\WebPush\WebPush;

$webPush = new WebPush();
$webPush->setPadding(false); // disable automatic padding
$webPush->setPadding(512); // enable automatic padding to 512 bytes (you should make sure that your payload is less than 512 bytes, or else an attacker could guess the content)
$webPush->setPadding(true); // enable automatic padding to default maximum compatibility length
```

### Customizing the HTTP client
WebPush uses [HTTPlug](https://httplug.io). It will use the most appropriate client it finds, 
but you must first install [one](https://packagist.org/providers/php-http/async-client-implementation)

## Common questions

### Is there any plugin/bundle/extension for my favorite PHP framework?
The following are available:

- Symfony: 
    - [MinishlinkWebPushBundle](https://github.com/Minishlink/web-push-bundle)
    - [bentools/webpush-bundle](https://github.com/bpolaszek/webpush-bundle) (associate your Symfony users to WebPush subscriptions)
- Laravel: [laravel-notification-channels/webpush](https://github.com/laravel-notification-channels/webpush)

Feel free to add your own!

### Is the API stable?
Not until the [Push API spec](http://www.w3.org/TR/push-api/) is finished.

### What about security?
Payload is encrypted according to the [Message Encryption for Web Push](https://tools.ietf.org/html/draft-ietf-webpush-encryption-01) standard,
using the user public key and authentication secret that you can get by following the [Web Push API](http://www.w3.org/TR/push-api/) specification.

Internally, WebPush uses the [WebToken](https://github.com/web-token) framework or OpenSSL to handle encryption keys generation and encryption.

### How do I scale?
Here are some ideas:

1. Upgrade to PHP 7.2
2. Make sure MultiCurl is available on your server
3. Find the right balance for your needs between security and performance (see above)
4. Find the right batch size (set it in `defaultOptions` or as parameter to `flush()`)

### How to solve "SSL certificate problem: unable to get local issuer certificate"?
Your installation lacks some certificates.

1. Download [cacert.pem](http://curl.haxx.se/ca/cacert.pem).
2. Edit your `php.ini`: after `[curl]`, type `curl.cainfo = /path/to/cacert.pem`.

You can also force using a client without peer verification.

### How to solve "Bad key encryption key length" or "Unsupported key type"?
Disable `mbstring.func_overload` in your `php.ini`.

### How to solve "Class 'Minishlink\WebPush\WebPush' not found"
Make sure to require Composer's [autoloader](https://getcomposer.org/doc/01-basic-usage.md#autoloading).

```php
require __DIR__ . '/path/to/vendor/autoload.php';
```

### I must use PHP 5.4 or 5.5. What can I do?
You won't be able to send any payload, so you'll only be able to use `sendNotification($subscription)`.
Install the library with `composer` using `--ignore-platform-reqs`.
The workaround for getting the payload is to fetch it in the service worker ([example](https://github.com/Minishlink/physbook/blob/2ed8b9a8a217446c9747e9191a50d6312651125d/web/service-worker.js#L75)). 

### I lost my VAPID keys!
See [issue #58](https://github.com/web-push-libs/web-push-php/issues/58).

### I'm using Firebase push notifications, how do I use this library?
This library is not designed for Firebase push notifications.
You can still use it for your web projects (for standard WebPush notifications), but you should forget any link to Firebase while using the library.

### I need to send notifications to native apps. (eg. APNS for iOS)
WebPush is for web apps.
You need something like [RMSPushNotificationsBundle](https://github.com/richsage/RMSPushNotificationsBundle) (Symfony).

### This is PHP... I need Javascript!
This library was inspired by the Node.js [marco-c/web-push](https://github.com/marco-c/web-push) library.

## Contributing
See [CONTRIBUTING.md](https://github.com/Minishlink/web-push/blob/master/CONTRIBUTING.md).

## License
[MIT](https://github.com/Minishlink/web-push/blob/master/LICENSE)

## Sponsors
Thanks to [JetBrains](https://www.jetbrains.com/) for supporting the project through sponsoring some [All Products Packs](https://www.jetbrains.com/products.html) within their [Free Open Source License](https://www.jetbrains.com/buy/opensource/) program.
