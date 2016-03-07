# WebPush
> Web Push library for PHP

[![Build Status](https://travis-ci.org/Minishlink/web-push.svg?branch=master)](https://travis-ci.org/Minishlink/web-push)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/d60e8eea-aea1-4739-8ce0-a3c3c12c6ccf/mini.png)](https://insight.sensiolabs.com/projects/d60e8eea-aea1-4739-8ce0-a3c3c12c6ccf)

## Installation
`composer require minishlink/web-push`

## Usage
WebPush can be used to send notifications to endpoints which server delivers web push notifications as described in 
the [Web Push API specification](http://www.w3.org/TR/push-api/).
As it is standardized, you don't have to worry about what server type it relies on.

```php
<?php

use Minishlink\WebPush\WebPush;

// array of notifications
$notifications = array(
    array(
        'endpoint' => 'https://updates.push.services.mozilla.com/push/abc...', // Firefox 43+
        'payload' => 'hello !',
        'userPublicKey' => 'dahaj5365sq',
    ), array(
        'endpoint' => 'https://android.googleapis.com/gcm/send/abcdef...', // Chrome
        'payload' => null,
        'userPublicKey' => null,
    ), array(
        'endpoint' => 'https://example.com/other/endpoint/of/another/vendor/abcdef...',
        'payload' => '{"msg":"test"}',
        'userPublicKey' => 'fsqdjknadsanlk',
    ),
);

$webPush = new WebPush();

// send multiple notifications with payload
foreach ($notifications as $notification) {
    $webPush->sendNotification(
        $notification['endpoint'],
        $notification['payload'], // optional (defaults null)
        $notification['userPublicKey'] // optional (defaults null)
    );
}
$webPush->flush();

// send one notification and flush directly
$webPush->sendNotification(
    $notifications[0]['endpoint'],
    $notifications[0]['payload'], // optional (defaults null)
    $notifications[0]['userPublicKey'], // optional (defaults null)
    true // optional (defaults false)
);
```

### GCM servers notes (Chrome)
For compatibility reasons, this library detects if the server is a GCM server and appropriately sends the notification.
GCM servers don't support encrypted payloads yet so WebPush will skip the payload.
If you still want to show that payload on your notification, you should get that data on client-side from your server 
where you will have to store somewhere the history of notifications.

You will need to specify your GCM api key when instantiating WebPush:
```php
<?php

use Minishlink\WebPush\WebPush;

$endpoint = 'https://android.googleapis.com/gcm/send/abcdef...'; // Chrome
$apiKeys = array(
    'GCM' => 'MY_GCM_API_KEY',
);

$webPush = new WebPush($apiKeys);
$webPush->sendNotification($endpoint, null, null, true);
```

### Time To Live
Time To Live (TTL, in seconds) is how long a push message is retained by the push service (eg. Mozilla) in case the user browser 
is not yet accessible (eg. is not connected). You may want to use a very long time for important notifications. The default TTL is 4 weeks. 
However, if you send multiple nonessential notifications, set a TTL of 0: the push notification will be delivered only 
if the user is currently connected. For other cases, you should use a minimum of one day if your users have multiple time 
zones, and if you don't several hours will suffice.

```php
<?php

use Minishlink\WebPush\WebPush;

$webPush = new WebPush(); // default TTL is 4 weeks
// send some important notifications...

$webPush->setTTL(3600);
// send some not so important notifications

$webPush->setTTL(0);
// send some trivial notifications
```

### Changing the browser client
By default, WebPush will use `MultiCurl`, allowing to send multiple notifications in parallel.
You can change the client to any client extending `\Buzz\Client\AbstractClient`.
Timeout is configurable in the constructor.

```php
<?php

use Minishlink\WebPush\WebPush;

$client = new \Buzz\Client\Curl();
$timeout = 20; // seconds
$webPush = new WebPush(array(), null, $timeout, $client);
```

You have access to the inner browser if you want to configure it further.
```php
<?php

use Minishlink\WebPush\WebPush;

$webPush = new WebPush();

/** @var $browser \Buzz\Browser */
$browser = $webPush->getBrowser();
```

## Common questions

### Is there any plugin/bundle/extension for my favorite PHP framework?
The following are available:

- Symfony: [MinishlinkWebPushBundle](https://github.com/Minishlink/web-push-bundle)

Feel free to add your own!

### Is the API stable?
Not until the [Push API spec](http://www.w3.org/TR/push-api/) is finished.

### What about security?
Internally, WebPush uses the [phpecc](https://github.com/phpecc/phpecc) Elliptic Curve Cryptography library to create 
local public and private keys and compute the shared secret. 
Then, WebPush uses `openssl` in order to encrypt the payload with the encryption key.

### How to solve "SSL certificate problem: unable to get local issuer certificate" ?
Your installation lacks some certificates.

1. Download [cacert.pem](http://curl.haxx.se/ca/cacert.pem).
2. Edit your `php.ini`: after `[curl]`, type `curl.cainfo = /path/to/cacert.pem`.

You can also force using a client without peer verification.

### I need to send notifications to native apps. (eg. APNS for iOS)
WebPush is for web apps.
You need something like [RMSPushNotificationsBundle](https://github.com/richsage/RMSPushNotificationsBundle) (Symfony).

### This is PHP... I need Javascript!
This library was inspired by the Node.js [marco-c/web-push](https://github.com/marco-c/web-push) library.

## Contributing
See [CONTRIBUTING.md](https://github.com/Minishlink/web-push/blob/master/CONTRIBUTING.md).

## Tests
Copy `phpunit.xml` from `phpunit.dist.xml` and fill it with your test endpoints and private keys.

## License
[MIT](https://github.com/Minishlink/web-push/blob/master/LICENSE)
