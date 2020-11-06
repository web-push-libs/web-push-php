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
* Optional:
    * A PSR-14 (Event Dispatcher) implementation

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

## About the fluent syntax

In the documentation, you will see that methods are called “fluently”.

```php
<?php

use Minishlink\WebPush\Payload\AES128GCM;
use Minishlink\WebPush\Payload\AESGCM;
use Minishlink\WebPush\Payload\PayloadExtension;

$payloadExtension = PayloadExtension::create()
    ->addContentEncoding(AESGCM::create()->maxPadding())
    ->addContentEncoding(AES128GCM::create()->maxPadding())
;
```

If you don’t adhere to this coding style, you are free to use the “standard” way of coding.
The following example has the same behavior ase above.

```php
<?php

use Minishlink\WebPush\Payload\AES128GCM;
use Minishlink\WebPush\Payload\AESGCM;
use Minishlink\WebPush\Payload\PayloadExtension;

$aesgcm = new AESGCM();
$aesgcm->maxPadding();

$aes128gcm = new AES128GCM();
$aes128gcm->maxPadding();

$payloadExtension = new PayloadExtension();
$payloadExtension->addContentEncoding($aesgcm);
$payloadExtension->addContentEncoding($aes128gcm);
```

## Usage

* [The Subscription](doc/subscription.md)
* [The Notification](doc/notification.md)
* Sending notifications
    * [Simple Service](doc/simple_service.md)
    * [Advanced Service](doc/advanced_service.md)
    * [VAPID Keys](doc/vapid.md)
* [Status Report](doc/status_report.md)

## Contributing
See [CONTRIBUTING.md](https://github.com/Minishlink/web-push/blob/master/CONTRIBUTING.md).

## License
[MIT](https://github.com/Minishlink/web-push/blob/master/LICENSE)

## Sponsors
Thanks to [JetBrains](https://www.jetbrains.com/) for supporting the project through sponsoring some [All Products Packs](https://www.jetbrains.com/products.html) within their [Free Open Source License](https://www.jetbrains.com/buy/opensource/) program.
