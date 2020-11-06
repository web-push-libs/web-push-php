# Simple Service

If you want to quickly send push notifications and don’t want to have a fine-grained service,
you can start with the `Minishlink\WebPush\SimpleWebPush` service.

This service is designed to be as simple as possible.
It does not support all library features such as caching, logging or custom content padding.
If you need these features, please read the [Advanced Service page](advanced_service.md).

```php
<?php

use Minishlink\WebPush\SimpleWebPush;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\Notification;

// In this example, we consider you already received a subscription object
// from the user agent
$subscription = Subscription::createFromString('{"endpoint":"…"}');

// We create a notification
// No payload or other information at this stage.
// These features will be explained later
$notification = Notification::create();


//PSR-18 HTTP client
/** @var Psr\Http\Client\ClientInterface $client */
$client = '…';

//PSR-17 request factory
/** @var Psr\Http\Message\RequestFactoryInterface $requestFactory */
$requestFactory = '…';

// The WebPush service
$statusReport = SimpleWebPush::create( $client, $requestFactory)
    ->enableVapid(        //Mandatory for some Push Services. See the dedicated section
        'http://localhost:8000',
        'BB4W1qfBi7MF_Lnrc6i2oL-glAuKF4kevy9T0k2vyKV4qvuBrN3T6o9-7-NR3mKHwzDXzD3fe7XvIqIU1iADpGQ',
        'C40jLFSa5UWxstkFvdwzT3eHONE2FIJSEsVIncSCAqU'
    )
    ->send($notification, $subscription) // We send the notification
;
```

The status report you receive is described in [the dedicated page](status_report.md).
