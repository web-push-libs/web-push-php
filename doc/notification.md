# The Notification

To reach the client (web browser), you need to send a Notification.

It may have a payload. In this case, the payload will be encrypted on server side and decrypted by the client. 
The payload may be a string, or a JSON object. The structure of the latter is described in the next section.

```php
<?php

use Minishlink\WebPush\Notification;

$notification = Notification::create()
    ->withPayload('Hello world')
;
```

## TTL (Time-To-Live)

With this feature, a value in seconds is added to the notification.
It suggests how long a push message is retained by the push service.
A value of 0 (zero) indicates the notification is delivered immediately.

```php
<?php

use Minishlink\WebPush\Notification;

$notification = Notification::create()
    ->withTTL(3600)
;
```

#### Topic

A push message that has been stored by the push service can be replaced with new content.
If the user agent is offline during the time the push messages are sent,
updating a push message avoids the situation where outdated or redundant messages are sent to the user agent.

Only push messages that have been assigned a topic can be replaced.
A push message with a topic replaces any outstanding push message with an identical topic.

```php
<?php

use Minishlink\WebPush\Notification;

$notification = Notification::create()
    ->withTopic('user-account-updated')
;
```

#### Urgency

For a device that is battery-powered, it is often critical it remains dormant for extended periods.
Radio communication in particular consumes significant power and limits the length of time the device can operate.

To avoid consuming resources to receive trivial messages,
it is helpful if an application server can communicate the urgency of a message and if the user agent can request
that the push server only forwards messages of a specific urgency.

| Urgency  | Device State                | Examples                                        |
|----------|-----------------------------|-------------------------------------------------|
| very-low | On power and Wi-Fi          | Advertisements                                  |
| low      | On either power or Wi-Fi    | Topic updates                                   |
| normal   | On neither power nor Wi-Fi  | Chat or Calendar Message                        |
| high     | Low battery                 | Incoming phone call or time-sensitive alert     |

```php
<?php

use Minishlink\WebPush\Notification;

$notification = Notification::create()
    ->veryLowUrgency()
    ->lowUrgency()
    ->normalUrgency()
    ->highUrgency()
;
```

## Asynchronous Response

Your application may prefer asynchronous responses to request confirmation from the
push service when a push message is delivered and then acknowledged by the user agent.

The push service MUST support delivery confirmations to use this feature.

```php
<?php

use Minishlink\WebPush\Notification;

$notification = Notification::create()
    ->async() // Prefer async response
    ->sync() // Prefer sync response (default)
;
```

## JSON Messages

You may have noticed that the specification [defines a structure for the payload](https://notifications.spec.whatwg.org/#notifications).
This structure contains properties that the client should be understood and render an appropriate way.

The library provides a `Minishlink\WebPush\Message` class with convenient methods to ease the creation of a message. 

```php
<?php

use Minishlink\WebPush\Action;
use Minishlink\WebPush\Message;
use Minishlink\WebPush\Notification;

$message = Message::create('Hello World!')
    ->mute() // Silent
    ->unmute() // Not silent (default)


    ->auto() //Direction = auto
    ->ltr() //Direction = left to right
    ->rtl() //Direction = right to left

    ->addAction(Action::create('alert', 'TITLE'))

    ->interactionRequired()
    ->noInteraction()

    ->renotify()
    ->doNotRenotify()

    ->withIcon('https://…')
    ->withImage('https://…')
    ->withData(['foo' => 'BAR']) // Arbitrary data
    ->withBadge('badge1')
    ->withLang('fr-FR')
    ->withTimestamp(time())
    ->withTag('foo')

    ->vibrate(300, 100, 400)
;

$notification = Notification::create()
    ->withPayload((string) $message)
;
```
