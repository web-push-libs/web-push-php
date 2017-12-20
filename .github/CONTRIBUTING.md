# Contributing to WebPush
WebPush is an open source library.
Feel free to contribute by submitting a pull request or creating (and solving) issues!

## Running Tests

First, you will need to create your own configuration file by copying
phpunit.dist.xml to phpunit.xml and filling in the fields you need for
testing (i.e. STANDARD_ENDPOINT, GCM_API_KEY etc.).

Then, download [phpunit](https://phpunit.de/) and test with one of the
following commands:

**For All Tests**
    `php phpunit.phar`

**For a Specific Test File**
    `php phpunit.phar tests/EncryptionTest.php`

**For a Single Test**
    `php phpunit.phar . --filter "/::testPadPayload( .*)?$/"` (regex)

But locally, these tests are handy.
