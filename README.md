# WebPush
> Web Push library for PHP

WebPush can be used to send notifications to endpoints which server delivers Web Push notifications as described in 

* The [RFC8030: “Generic Event Delivery Using HTTP Push“](https://tools.ietf.org/html/rfc8030).
* The [RFC8291: “Message Encryption for Web Push“](https://tools.ietf.org/html/rfc8291).
* The [RFC8292: “Voluntary Application Server Identification (VAPID) for Web Push“](https://tools.ietf.org/html/rfc8292).

In addition, some features from the [Push API](https://w3c.github.io/push-api/) are implemented.
This specification is a working draft at the time of writing (2020-09)

## Requirements

* Mandatory
    * PHP 7.4+
    * A PSR-17 (HTTP Message Factory) implementation
    * A PSR-18 (HTTP Client) implementation
    * `json`

* Optional
    * When using VAPID extension, one of the following extensions:
        * `gmp` (better for performance)
        * `BCMath` (good for performance)
    * When using VAPID extension:
        * a JWT Provider. This library provides implementations for `web-token` and `lcobucci/jwt`
        * a `symfony/cache` implementation (highly recommended)
    * When using VAPID or Payload extensions:
        * `openssl`
        * `mbstring`
    * A PSR-6 (Logging) implementation for debugging

**There is no support and maintenance for older PHP versions.**

## Installation

Use [composer](https://getcomposer.org/) to download and install the library and its dependencies.

`composer require minishlink/web-push`

## Usage
```php
<?php

use Minishlink\WebPush\ExtensionManager;use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\WebPush;

//PSR-18 HTTP client
$client = …;

//PSR-17 request factory
$requestFactory = …;

// Extension Manager is detailed later
$extensionManager = new ExtensionManager();

// The WebPush service
$webPush = new WebPush(
    $client,          //PSR-18 HTTP client
    $requestFactory,  //PSR-17 request factory
    $extensionManager //Will be explained later
);


// In this example, we already received a subscription object
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

## Contributing
See [CONTRIBUTING.md](https://github.com/Minishlink/web-push/blob/master/CONTRIBUTING.md).

## License
[MIT](https://github.com/Minishlink/web-push/blob/master/LICENSE)

## Sponsors
Thanks to [JetBrains](https://www.jetbrains.com/) for supporting the project through sponsoring some [All Products Packs](https://www.jetbrains.com/products.html) within their [Free Open Source License](https://www.jetbrains.com/buy/opensource/) program.
