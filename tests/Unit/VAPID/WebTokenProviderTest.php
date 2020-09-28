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

namespace Minishlink\Tests\Unit\VAPID;

use function count;
use Jose\Component\Core\JWK;
use Minishlink\WebPush\VAPID\WebTokenProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Safe\DateTimeImmutable;

/**
 * @internal
 */
final class WebTokenProviderTest extends TestCase
{
    /**
     * @test
     * @dataProvider dataComputeHeader
     */
    public function computeHeader(JWK $key, string $expectedSerializedKey): void
    {
        $expiresAt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:sP', '2020-01-28T16:22:37-07:00');

        $logger = self::createMock(LoggerInterface::class);
        $logger
            ->expects(static::exactly(2))
            ->method('debug')
            ->withConsecutive(
                ['Computing the JWS'],
                ['JWS computed', static::callback(static function (array $data): bool {
                    return 0 === count(array_diff(['token', 'key'], array_keys($data)));
                })],
            )
        ;

        $provider = new WebTokenProvider('audience', 'subject', $key);
        $header = $provider
            ->setLogger($logger)
            ->computeHeader($expiresAt)
        ;

        static::assertStringStartsWith('eyJ0eXAiOiJKV1QiLCJhbGciOiJFUzI1NiJ9.eyJhdWQiOiJhdWRpZW5jZSIsInN1YiI6InN1YmplY3QiLCJleHAiOjE1ODAyNTM3NTd9.', $header->getToken());
        static::assertEquals($expectedSerializedKey, $header->getKey());
    }

    public function dataComputeHeader(): array
    {
        return [
            [
                'key' => new JWK([
                    'kty' => 'EC',
                    'crv' => 'P-256',
                    'x' => 'MKBCTNIcKUSDii11ySs3526iDZ8AiTo7Tu6KPAqv7D4',
                    'y' => '4Etl6SRW2YiLUrN5vfvVHuhp7x8PxltmWWlbbM4IFyM',
                    'd' => '870MB6gfuTJ4HtUnUvYMyJpr5eUZNP4Bk43bVdj3eAE',
                ]),
                'serializedKey' => 'BDCgQkzSHClEg4otdckrN-duog2fAIk6O07uijwKr-w-4Etl6SRW2YiLUrN5vfvVHuhp7x8PxltmWWlbbM4IFyM',
            ],
            [
                'key' => new JWK([
                    'kty' => 'EC',
                    'crv' => 'P-256',
                    'x' => '0US8Ce_tJ9UbPjbEW91y77P5b_cVWb_JFIZsRW3FVFE',
                    'y' => 'XqCBYJtvngQ8ZN-9bzzamxLjpbw7vuHcHTT2H98LwLM',
                    'd' => 'TcP5-SlbNbThgntDB7TQHXLslhaxav8Qqdd_Ar7VuNo',
                ]),
                'serializedKey' => 'BNFEvAnv7SfVGz42xFvdcu-z-W_3FVm_yRSGbEVtxVRRXqCBYJtvngQ8ZN-9bzzamxLjpbw7vuHcHTT2H98LwLM',
            ],
        ];
    }
}
