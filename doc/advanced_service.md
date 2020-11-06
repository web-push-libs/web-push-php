# Advanced Service

This section is reserved for advanced use cases that are not covered by [the simple service](simple_service.md).

Non-exhaustive reason list:

* You need to log messages
* You need caching feature
* You want status reports to be sent as events
* You want to customize the content padding
* You need to use custom extensions
* You want to have fun going deeper into the library hidden concepts
* You want to use a JWS Provider other than the supported ones

## Extensions

As some core concepts are still not approved or mature enough at the time of writing,
extensions have been introduced to allow smooth integration over the evolution of the specifications. 

**Please note that extension `TTLExtension` is mandatory**

```php
<?php

use Minishlink\WebPush\ExtensionManager;
use Minishlink\WebPush\TTLExtension;
use Minishlink\WebPush\TopicExtension;
use Minishlink\WebPush\UrgencyExtension;
use Minishlink\WebPush\PreferAsyncExtension;

$extensionManager = ExtensionManager::create()
    ->add(TTLExtension::create())
    ->add(TopicExtension::create())
    ->add(UrgencyExtension::create())
    ->add(PreferAsyncExtension::create())
;
```

### VAPID

To use this extension, you need a JWTProvider. This service will generate signed Json Web Tokens (JWS)
that will be added to the request sent to the push service.

The library provides implementations for the following third party libraries. Feel free to choose one or the other.

* [`web-token`](https://github.com/web-token/jwt-framework): `composer require web-token/jwt-signature-algorithm-ecdsa`
* [`lcobucci/jwt`](https://github.com/lcobucci/jwt): `composer require lcobucci/jwt`

```php
<?php

use Minishlink\WebPush\ExtensionManager;
use Minishlink\WebPush\VAPID\VAPIDExtension;
use Minishlink\WebPush\VAPID\WebTokenProvider;
use Minishlink\WebPush\VAPID\LcobucciProvider;

$publicKey = 'BNFEvAnv7SfVGz42xFvdcu-z-W_3FVm_yRSGbEVtxVRRXqCBYJtvngQ8ZN-9bzzamxLjpbw7vuHcHTT2H98LwLM';
$privateKey = 'TcP5-SlbNbThgntDB7TQHXLslhaxav8Qqdd_Ar7VuNo';

// With Web-Token
$jwsProvider = WebTokenProvider::create($publicKey, $privateKey);

// With Lcobucci
$jwsProvider = LcobucciProvider::create($publicKey, $privateKey);


$vapidExtension = VAPIDExtension::create(
    'http://example.com', // You can use an URL or an e-mail address (mailto:…)
    $jwsProvider
);
$vapidExtension->setTokenExpirationTime('now +12h'); // Optional. By default the token is valid for 1 hour.

$extensionManager = ExtensionManager::create()
    ->add($vapidExtension)
;
```

*Note: you can use another JWS Provider. This service shall implement `Minishlink\WebPush\VAPID\JWSProvider`.*

### Payload and Content Encryption

The payload is encrypted on server side and decrypted by the browser.
To do so, you shall add Content Encoding services.
The specification defines two encodings that are very similar: `aesgcm` and `aes128gcm`.
Please use both of them.

```php
<?php

use Minishlink\WebPush\ExtensionManager;
use Minishlink\WebPush\Payload\PayloadExtension;
use Minishlink\WebPush\Payload\AES128GCM;
use Minishlink\WebPush\Payload\AESGCM;

$payloadExtension = PayloadExtension::create()
    ->addContentEncoding(AES128GCM::create())
    ->addContentEncoding(AESGCM::create())
;

$extensionManager = ExtensionManager::create()
    ->add($payloadExtension)
;
```

#### Padding

By default, both `AESGCM` and `AES128GCM` content encodings use the recommended padding.
This feature is used to prevent attacks (Size and Timing) that could reveal the notification payload.

You can change the padding size if needed using one of the three options:

* No padding (not recommended)
* Recommended padding (default)
* Maximum padding
* Custom padding

With the maximum padding, the encrypted payload will always have a size of 4096 bytes.
This may increase the computation time, that’s why this padding is not enabled, but highly recommended.

**Be careful with the custom padding!**. You can set the padding length of your choice, but the maximum size depends on the encoding.
It is 4078 and 3993 for `AESGCM` and `AES128GCM` respectively.

```php
<?php

use Minishlink\WebPush\Payload\AES128GCM;
use Minishlink\WebPush\Payload\AESGCM;

$aes128gcm = AES128GCM::create()
    ->noPadding()          // No padding
    ->recommendedPadding() // Recommended padding
    ->maxPadding()         // Maximum padding
    ->customPadding(1024)  // Custom padding of 1024 bytes
;

$aesgcm = AESGCM::create()
    ->noPadding()          // No padding
    ->recommendedPadding() // Recommended padding
    ->maxPadding()         // Maximum padding
    ->customPadding(1024)  // Custom padding of 1024 bytes
;
``` 

## Caching

By default, a new encryption key is generated for each message you send.
This may slow down your application.

The VAPID feature also needs to generate an authorization header that is digitally signed.

You can ask the library to cache and reuse
* The encryption key for a limited time
* The VAPID header for a given endpoint

The tests perform using Github Action show that it will reduce the computation time of 40% to 50%.

* Without this feature: ~50k notifications/min
* With this feature: ~100k notifications/min

*Please note that the service still depends on the speed of the Internet connection and the HTTP client.*

```php
<?php

use Minishlink\WebPush\Payload\PayloadExtension;
use Minishlink\WebPush\Payload\AES128GCM;
use Minishlink\WebPush\Payload\AESGCM;
use Minishlink\WebPush\VAPID\VAPIDExtension;
use Psr\Cache\CacheItemPoolInterface;

/** @var CacheItemPoolInterface $cache */
$cache = '…';

$payloadExtension = PayloadExtension::create()
    ->addContentEncoding(AES128GCM::create()->setCache($cache, 'now +30 min'))
    ->addContentEncoding(AESGCM::create()->setCache($cache, 'now +30 min'))
;

$vapidExtension = VAPIDExtension::create(…)
    ->setCache($cache, 'now +12 hours') // Should not exceed 24 hours
;
```

**Important:** if you customize the token expiration time (`setTokenExpirationTime`),
you have to make sure the validity period of the cache is lower than the lifetime of the token.

As an example, if you call
* `$vapidExtension->setTokenExpirationTime('now +1 hour)`
* and `$vapidExtension->setCache($cache, 'now +12 hours')`

then an expired header will be cached and reused.
We recommend dividing by 2 the cache lifetime:

* `$vapidExtension->setTokenExpirationTime('now +2 hours)`
* `$vapidExtension->setCache($cache, 'now +1 hour)`

or 

* `$vapidExtension->setTokenExpirationTime('now +24 hours)`
* `$vapidExtension->setCache($cache, 'now +12 hours)`

## Logging

Most of the objects showed on this page have a `setLogger` method.
This method needs a PSR-3 cache object as unique argument.

```php
<?php

use Minishlink\WebPush\ExtensionManager;
use Minishlink\WebPush\TTLExtension;
use Minishlink\WebPush\TopicExtension;
use Minishlink\WebPush\UrgencyExtension;
use Minishlink\WebPush\PreferAsyncExtension;
use Psr\Log\LoggerInterface;

/** @var LoggerInterface $logger */
$logger = '…';

$extensionManager = ExtensionManager::create()
    ->setLogger($logger)
    ->add(TTLExtension::create()->setLogger($logger))
    ->add(TopicExtension::create()->setLogger($logger))
    ->add(UrgencyExtension::create()->setLogger($logger))
    ->add(PreferAsyncExtension::create()->setLogger($logger))
;
```