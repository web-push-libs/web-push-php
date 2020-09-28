<?php

declare(strict_types=1);

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2020 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

namespace Minishlink\WebPush;

use Psr\Http\Message\RequestInterface;

interface Extension
{
    public function process(RequestInterface $request, Notification $notification, Subscription $subscription): RequestInterface;
}
