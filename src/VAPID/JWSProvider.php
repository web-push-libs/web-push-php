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

namespace Minishlink\WebPush\VAPID;

use DateTimeInterface;

interface JWSProvider
{
    public function computeHeader(DateTimeInterface $expiresAt): Header;
}
