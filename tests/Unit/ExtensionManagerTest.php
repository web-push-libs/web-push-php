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

        $manager = ExtensionManager::create()
            ->setLogger($logger)
            ->add($extension1)
            ->add($extension2)
            ->process($request, $notification, $subscription)
        ;
    }
}
