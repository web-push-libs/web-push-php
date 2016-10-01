# Contributing to WebPush
WebPush is an open source library.
Feel free to contribute by submitting a pull request or creating (and solving) issues!

## Running Tests

First, you will need to create your own configuration file by copying
phpunit.dist.xml to phpunit.xml and filling in the fields you need for
testing (i.e. STANDARD_ENDPOINT, GCM_API_KEY etc.).

Then, download [phpunit](https://phpunit.de/) and tests with one of the
following commands:

**For All Tests**
    php phpunit.phar

**For a Specific Test File**
    php phpunit.phar tests/EncryptionTest.php

**For a Single Test**
    php phpunit.phar . --filter "/::testPadPayload( .*)?$/" (regex)

Some tests have a custom decorator @skipIfTravis. The reason is that
there's no way in Travis to update the push subscription, so the endpoint
in my phpunit.travis.xml would ultimately expire
(and require a human modification), and the corresponding tests would fail.
But locally, these tests are handy.

On Ubuntu you may need to install php-gmp, php-mbstring, php-curl.