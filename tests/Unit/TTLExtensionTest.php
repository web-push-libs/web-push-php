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

use function array_key_exists;
use Minishlink\WebPush\Notification;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\TTLExtension;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
final class TTLExtensionTest extends TestCase
{
    /**
     * @test
     * @dataProvider dataTTLIsSetInHeader
     */
    public function ttlIsSetInHeader(int $ttl): void
    {
        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(static::once())
            ->method('debug')
            ->with('Processing with the TTL extension', static::callback(static function (array $data) use ($ttl): bool {
                if (!array_key_exists('TTL', $data)) {
                    return false;
                }

                return ((string) $ttl) === $data['TTL'];
            }))
        ;

        $request = self::createMock(RequestInterface::class);
        $request
            ->expects(static::once())
            ->method('withHeader')
            ->with('TTL', static::equalTo((string) $ttl))
            ->willReturnSelf()
        ;

        $notification = self::createMock(Notification::class);
        $notification
            ->expects(static::once())
            ->method('getTTL')
            ->willReturn($ttl)
        ;
        $subscription = self::createMock(Subscription::class);

        $extension = new TTLExtension();
        $extension
            ->setLogger($logger)
            ->process($request, $notification, $subscription)
        ;
    }

    public function dataTTLIsSetInHeader(): array
    {
        return [
            [0],
            [10],
            [3600],
        ];
    }
}
