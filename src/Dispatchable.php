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

namespace Minishlink\WebPush;

use Psr\EventDispatcher\EventDispatcherInterface;

interface Dispatchable
{
    public function setEventDispatcher(EventDispatcherInterface $eventDispatcher): self;
}
