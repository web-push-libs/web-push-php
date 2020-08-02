# Contributing to WebPush
WebPush is an open source library.
Feel free to contribute by submitting a pull request or creating (and solving) issues!

## Requirements before submitting a pull request

The CI used to check that push notifications can still be sent after the proposed code changes thanks to [web-push testing service](https://www.npmjs.com/package/web-push-testing-service). Unfortunately, this package doesn't work anymore and I don't have the available time to fix it. We can't accept new PR without being sure that the code changes doesn't break anything. So, for a PR to be accepted, it is now requested to have one of these 3 solutions :

1. You fix web-push-testing-service completely, but it's very time consuming
2. You fix web-push-testing-service but only for stable version of Chrome, looks a bit more promising but still you'll need some time
3. You don't fix web-push-testing-service, but you add a video that shows that the PR changes work as expected, and that core feature (sending a simple push with payload) works. For example a video showing a local test passing with a push notification on Chrome. Please make it enough clear for me to be 100% sure that it's ok to merge by looking at your code and the video.

Please don't make huge pull requests that introduce too many changes too.

Thanks!

## Installing a mock push service

Before running tests, you'll need to install the [web-push testing service](https://www.npmjs.com/package/web-push-testing-service):

```bash
npm install web-push-testing-service -g
```

NOTE: You might need to make sure command `web-push-testing-service` runs OK on cli. In my case on OSX, I needed to add a bash alias after install:

```~/.bash_profile
alias web-push-testing-service='/usr/local/Cellar/node/7.4.0/bin/web-push-testing-service'
```

After that, please create your own configuration file by copying
`phpunit.dist.xml` to phpunit.xml and filling in the fields you need for
testing (i.e. STANDARD_ENDPOINT, etc.).

## Running Tests

Then, download [phpunit](https://phpunit.de/) and test with one of the following commands:

**For All Tests**
    `php phpunit.phar`

**For a Specific Test File**
    `php phpunit.phar tests/EncryptionTest.php`

**For a Single Test**
    `php phpunit.phar . --filter "/::testPadPayload( .*)?$/"` (regex)

But locally, these tests are handy.
