# WebPush
> Web Push library for PHP

## Installation
`composer require minishlink/web-push`

## Usage
WebPush can be used to send notifications to endpoints which server delivers web push notifications as described in 
the [Web Push API specification](http://www.w3.org/TR/push-api/).
As it is standardized, you don't have to worry about what server type it relies on.
__*Currently, WebPush doesn't support payloads at all.
It is under development (see ["payload" branch](https://github.com/Minishlink/web-push/tree/payload).*__

```php
<?php

use Minishlink\WebPush\WebPush;

// array of endpoints
$endpoints = array(
    'https://android.googleapis.com/gcm/send/abcdef...', // Chrome
    'https://updates.push.services.mozilla.com/push/adcdef...', // Firefox 43+
    'https://example.com/other/endpoint/of/another/vendor/abcdef...',
);

$webPush = new WebPush();
$webPush->sendNotification($endpoints[0]); // send one notification
$webPush->sendNotifications($endpoints); // send multiple notifications
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
$webPush->sendNotification($endpoints[0]); // send one notification
$webPush->sendNotifications($endpoints); // send multiple notifications
```

### Changing the browser client
By default, WebPush will use `MultiCurl`, allowing to send multiple notifications in parallel.
You can change the client to any client extending `\Buzz\Client\AbstractClient`.
Timeout is configurable in the constructor.

```php
<?php

use Minishlink\WebPush\WebPush;

$webPush = new WebPush(array(), null, null, $client);
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

### Is the API stable?
Not until the [Push API spec](http://www.w3.org/TR/push-api/) is finished.

### What about security?
Internally, WebPush uses the [phpecc](https://github.com/phpecc/phpecc) Elliptic Curve Cryptography library.

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
