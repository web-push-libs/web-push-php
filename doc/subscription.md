# Subscription

The subscription is created on client side when the end-user allows your application to send push messages.
On client side (Javascript), you can simply send to your server the object you receive using `JSON.stringify`.

A subscription object will look like:

```json
{
    "endpoint":"https://updates.push.services.mozilla.com/wpush/v2/AAAAAAAA[…]AAAAAAAAA",
    "keys":{
        "auth":"XXXXXXXXXXXXXX",
        "p256dh":"YYYYYYYY[…]YYYYYYYYYYYYY"
    }
}
```

On server side, you can get a `Minishlink\WebPush\Subscription` from the JSON string using the dedicated method `Minishlink\WebPush\Subscription::createFromString`.

```php
use Minishlink\WebPush\Subscription;

$subscription = Subscription::createFromString('{"endpoint":"https://updates.push.services.mozilla.com/wpush/v2/AAAAAAAA[…]AAAAAAAAA","keys":{"auth":"XXXXXXXXXXXXXX","p256dh":"YYYYYYYY[…]YYYYYYYYYYYYY"}}');
```
