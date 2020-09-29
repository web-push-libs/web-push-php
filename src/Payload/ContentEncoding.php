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

namespace Minishlink\WebPush\Payload;

use Minishlink\WebPush\Subscription;
use Psr\Http\Message\RequestInterface;

interface ContentEncoding
{
    public function encode(string $payload, RequestInterface $request, Subscription $subscription): RequestInterface;

    public function name(): string;
}
