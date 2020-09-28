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

namespace Minishlink\Tests\Unit;

use Minishlink\WebPush\Extension;
use Minishlink\WebPush\ExtensionManager;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\Subscription;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class ExtensionManagerTest extends TestCase
{
    /**
     * @test
     */
    public function topicIsSetInHeader(): void
    {
        $extension1 = self::createMock(Extension::class);
        $extension1
            ->expects(static::once())
            ->method('process')
            ->willReturnArgument(0)
        ;

        $extension2 = self::createMock(Extension::class);
        $extension2
            ->expects(static::once())
            ->method('process')
            ->willReturnArgument(0)
        ;

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(static::exactly(4))
            ->method('debug')
            ->withConsecutive(
                ['Extension added', ['extension' => $extension1]],
                ['Extension added', ['extension' => $extension2]],
                ['Processing the request'],
                ['Processing done'],
            )
        ;

        $request = self::createMock(RequestInterface::class);
        $notification = self::createMock(Notification::class);
        $subscription = self::createMock(Subscription::class);

        $manager = new ExtensionManager();
        $manager
            ->setLogger($logger)
            ->add($extension1)
            ->add($extension2)
            ->process($request, $notification, $subscription)
        ;
    }
}
