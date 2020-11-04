<?php

declare(strict_types=1);

/*
 * This file is part of the WebPush library.
 *
 * (c) Louis Lagrange <lagrange.louis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// adapted from http://stackoverflow.com/a/38926070
// DON'T USE IN PRODUCTION, please code your own router or use a framework!

chdir(__DIR__);
$filePath = realpath('./'.ltrim($_SERVER['REQUEST_URI'], '/'));
if ($filePath && is_dir($filePath)) {
    // attempt to find an index file
    foreach (['index.php', 'index.html'] as $indexFile) {
        if ($filePath = realpath($filePath.DIRECTORY_SEPARATOR.$indexFile)) {
            break;
        }
    }
}

if ($filePath && is_file($filePath)) {
    // 1. check that file is not outside of this directory for security
    // 2. check for circular reference to router.php
    // 3. don't serve dot files
    if (0 === mb_strpos($filePath, __DIR__.DIRECTORY_SEPARATOR) &&
        $filePath !== __DIR__.DIRECTORY_SEPARATOR.'router.php' &&
        '.' !== mb_substr(basename($filePath), 0, 1)
    ) {
        if ('.php' === mb_strtolower(mb_substr($filePath, -4))) {
            include $filePath;
        } else {
            if ('.js' === mb_strtolower(mb_substr($filePath, -3))) {
                header('Content-Type: text/javascript');
            }
            readfile($filePath);
        }
    } else {
        // disallowed file
        header('HTTP/1.1 404 Not Found');
        echo '404 Not Found';
    }
} else {
    // rewrite to our index file
    include '.'.DIRECTORY_SEPARATOR.'index.html';
}
