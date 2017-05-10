# WebPush
> Web Push library for PHP

[![Build Status](https://travis-ci.org/web-push-libs/web-push-php.svg?branch=master)](https://travis-ci.org/web-push-libs/web-push-php)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/d60e8eea-aea1-4739-8ce0-a3c3c12c6ccf/mini.png)](https://insight.sensiolabs.com/projects/d60e8eea-aea1-4739-8ce0-a3c3c12c6ccf)

WebPush can be used to send notifications to endpoints which server delivers Web Push notifications as described in 
the [Web Push protocol](https://tools.ietf.org/html/draft-thomson-webpush-protocol-00).
As it is standardized, you don't have to worry about what server type it relies on.

## Requirements
* PHP 5.6
  * gmp
  * mbstring
  * curl
  * openssl

PHP 7.1 is recommended for better performance.

## Installation
Use [composer](https://getcomposer.org/) to download and install the library and its dependencies.

`composer require minishlink/web-push`

## Usage
```php
<?php

use Minishlink\WebPush\WebPush;

// array of notifications
$notifications = array(
    array(
        'endpoint' => 'https://updates.push.services.mozilla.com/push/abc...', // Firefox 43+
        'payload' => 'hello !',
        'userPublicKey' => 'BPcMbnWQL5GOYX/5LKZXT6sLmHiMsJSiEvIFvfcDvX7IZ9qqtq68onpTPEYmyxSQNiH7UD/98AUcQ12kBoxz/0s=', // base 64 encoded, should be 88 chars
        'userAuthToken' => 'CxVX6QsVToEGEcjfYPqXQw==', // base 64 encoded, should be 24 chars
    ), array(
        'endpoint' => 'https://android.googleapis.com/gcm/send/abcdef...', // Chrome
        'payload' => null,
        'userPublicKey' => null,
        'userAuthToken' => null,
    ), array(
        'endpoint' => 'https://example.com/other/endpoint/of/another/vendor/abcdef...',
        'payload' => '{msg:"test"}',
        'userPublicKey' => '(stringOf88Chars)', 
        'userAuthToken' => '(stringOf24Chars)',
    ),
);

$webPush = new WebPush();

// send multiple notifications with payload
foreach ($notifications as $notification) {
    $webPush->sendNotification(
        $notification['endpoint'],
        $notification['payload'], // optional (defaults null)
        $notification['userPublicKey'], // optional (defaults null)
        $notification['userAuthToken'] // optional (defaults null)
    );
}
$webPush->flush();

// send one notification and flush directly
$webPush->sendNotification(
    $notifications[0]['endpoint'],
    $notifications[0]['payload'], // optional (defaults null)
    $notifications[0]['userPublicKey'], // optional (defaults null)
    $notifications[0]['userAuthToken'], // optional (defaults null)
    true // optional (defaults false)
);
```

### Full examples of Web Push implementations
* An example with web-push-php: [Minishlink/web-push-php-example](https://github.com/Minishlink/web-push-php-example)
* Matthew Gaunt's [Web Push Book](https://web-push-book.gauntface.com) - a must read
* Mozilla's [ServiceWorker Cookbooks](https://serviceworke.rs/push-payload_index_doc.html) (don't mind the `server.js` file: it should be replaced by your PHP server code with this library)
* Google's [introduction to push notifications](https://developers.google.com/web/fundamentals/getting-started/push-notifications/) (as of 03-20-2016, it doesn't mention notifications with payload)
* you may want to take a look at my own implementation: [sw.js](https://github.com/Minishlink/physbook/blob/02a0d5d7ca0d5d2cc6d308a3a9b81244c63b3f14/web/service-worker.js) and [app.js](https://github.com/Minishlink/physbook/blob/02a0d5d7ca0d5d2cc6d308a3a9b81244c63b3f14/app/Resources/public/js/app.js)

### Authentication (VAPID)
Browsers need to verify your identity. A standard called VAPID can authenticate you for all browsers. You'll need to create and provide a public and private key for your server. These keys must be safely stored and should not change.

You can specify your authentication details when instantiating WebPush. The keys can be passed directly (recommended), or you can load a PEM file or its content:
```php
<?php

use Minishlink\WebPush\WebPush;

$endpoint = 'https://android.googleapis.com/gcm/send/abcdef...'; // Chrome

$auth = array(
    'GCM' => 'MY_GCM_API_KEY', // deprecated and optional, it's here only for compatibility reasons
    'VAPID' => array(
        'subject' => 'mailto:me@website.com', // can be a mailto: or your website address
        'publicKey' => '~88 chars', // (recommended) uncompressed public key P-256 encoded in Base64-URL
        'privateKey' => '~44 chars', // (recommended) in fact the secret multiplier of the private key encoded in Base64-URL
        'pemFile' => 'path/to/pem', // if you have a PEM file and can link to it on your filesystem
        'pem' => 'pemFileContent', // if you have a PEM file and want to hardcode its content
    ),
);

$webPush = new WebPush($auth);
$webPush->sendNotification(...);
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

### Notifications and default options
Each notification can have a specific Time To Live, urgency, and topic.
You can change the default options with `setDefaultOptions()` or in the constructor:

```php
<?php

use Minishlink\WebPush\WebPush;

$defaultOptions = array(
    'TTL' => 300, // defaults to 4 weeks
    'urgency' => 'normal', // protocol defaults to "normal"
    'topic' => 'new_event', // not defined by default,
    'batchSize' => 200, // defaults to 1000
);

// for every notifications
$webPush = new WebPush(array(), $defaultOptions);
$webPush->setDefaultOptions($defaultOptions);

// or for one notification
$webPush->sendNotification($endpoint, $payload, $userPublicKey, $userAuthToken, $flush, array('TTL' => 5000));
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

#### batchSize
If you send tens of thousands notifications at a time, you may get memory overflows due to how endpoints are called in Guzzle.
In order to fix this, WebPush sends notifications in batches. The default size is 1000. Depending on your server configuration (memory), you may want
to decrease this number. Do this while instanciating WebPush or calling `setDefaultOptions`. Or, if you want to customize this for a specific flush, give
it as a parameter : `$webPush->flush($batchSize)`.

### Server errors
You can see what the browser vendor's server sends back in case it encountered an error (push subscription expiration, wrong parameters...).
`sendNotification()` (with flush as true) and `flush()` returns true if there were no errors. If there are errors it returns an array like the following.
The `expired` key can be useful to clean your database of expired endpoints.

```php
$res = array(
    array( // first notification (failed)
        'success' => false,
        'endpoint' => $theEndpointToDeleteInYourDatabaseIfExpired
        'message' => $responseMessage,
        'statusCode' => $responseStatusCode,
        'headers' => $responseHeaders,
        'content' => $responseContent, // you may have more infos here
        'expired' => $isTheEndpointWrongOrExpired,
    ),
    array( // second notification (succeeded)
        'success' => true,
    ),
    array( // third notification
        ...
    ), ...
);
```

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
$clientOptions = array(
    \GuzzleHttp\RequestOptions::ALLOW_REDIRECTS => false,
); // see \GuzzleHttp\RequestOptions
$webPush = new WebPush(array(), array(), $timeout, $clientOptions);
```

## Common questions

### Is there any plugin/bundle/extension for my favorite PHP framework?
The following are available:

- Symfony: [MinishlinkWebPushBundle](https://github.com/Minishlink/web-push-bundle)
- Laravel: [laravel-notification-channels/webpush](https://github.com/laravel-notification-channels/webpush)

Feel free to add your own!

### Is the API stable?
Not until the [Push API spec](http://www.w3.org/TR/push-api/) is finished.

### What about security?
Payload is encrypted according to the [Message Encryption for Web Push](https://tools.ietf.org/html/draft-ietf-webpush-encryption-01) standard,
using the user public key and authentication secret that you can get by following the [Web Push API](http://www.w3.org/TR/push-api/) specification.

Internally, WebPush uses the [phpecc](https://github.com/phpecc/phpecc) Elliptic Curve Cryptography library to create 
local public and private keys and compute the shared secret.
Then, if you have a PHP >= 7.1, WebPush uses `openssl` in order to encrypt the payload with the encryption key.
Otherwise, if you have PHP < 7.1, it uses [Spomky-Labs/php-aes-gcm](https://github.com/Spomky-Labs/php-aes-gcm), which is slower.

### How do I scale?
Here are some ideas:

1. Upgrade to PHP 7.1
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
You won't be able to send any payload, so you'll only be able to use `sendNotification($endpoint)`.
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
