<?php

if (ini_get('mbstring.func_overload') >= 2) {
    throw new RuntimeException('You must disable mbstring.func_overload for str* function in order to use web-push-php. You can fix this in your php.ini.');
}
