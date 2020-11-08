# Status Report

After sending a notification, you will receive a StatusReport object.
This status report has the following properties:

* The notification
* The subscription
* The PSR-7 request
* The PSR-7 response

Depending on the status code, you will be able to know if it is a success or not.
In case of success, you can directly access the management link (`location` header parameter)
or the links entity fields in case of asynchronous call.

```php
<?php
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\WebPushService;

/** @var Notification $notification */
/** @var Subscription $subscription */

/** @var WebPushService $webPushService */
$statusReport = $webPushService->send($notification, $subscription);

if(!$statusReport->isSuccess()) {
    //Something went wrong    
} else {
    $statusReport->getLocation();
    $statusReport->getLinks();
}
```

In some cases, it could be interesting to dispatch the status report through an event dispatcher.
The `WebPush` class has a convenient method to dispatch reports using a PSR-14 (Event Dispatcher) implementation. 

The `WebPush` class implements the interface `Minishlink\WebPush\Dispatchable`.
This interface has a single method `setEventDispatcher` needs a PSR-14 object as unique argument.

```php
<?php
use Minishlink\WebPush\WebPush;
use Psr\EventDispatcher\EventDispatcherInterface;

/** @var EventDispatcherInterface $eventDispatcher */

/** @var WebPush $webPushService */
$webPushService->setEventDispatcher($eventDispatcher);
```
