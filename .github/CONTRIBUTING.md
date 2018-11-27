# Contributing to WebPush
WebPush is an open source library.
Feel free to contribute by submitting a pull request or creating (and solving) issues!

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
testing (i.e. STANDARD_ENDPOINT, GCM_API_KEY etc.).

## Running Tests

Then, download [phpunit](https://phpunit.de/) and test with one of the following commands:

**For All Tests**
    `php phpunit.phar`

**For a Specific Test File**
    `php phpunit.phar tests/EncryptionTest.php`

**For a Single Test**
    `php phpunit.phar . --filter "/::testPadPayload( .*)?$/"` (regex)

But locally, these tests are handy.
