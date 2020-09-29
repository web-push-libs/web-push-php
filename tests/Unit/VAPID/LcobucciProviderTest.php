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

namespace Minishlink\Tests\Unit\VAPID;

use function count;
use InvalidArgumentException;
use Lcobucci\JWT\Signer\Key;
use Minishlink\WebPush\VAPID\LcobucciProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Safe\DateTimeImmutable;

/**
 * @internal
 */
final class LcobucciProviderTest extends TestCase
{
    /**
     * @test
     */
    public function invalidKey(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('This key is not suitable for ECDSA signature');

        $key = new Key(<<<'RSA'
-----BEGIN RSA PRIVATE KEY-----
MIICdgIBADANBgkqhkiG9w0BAQEFAASCAmAwggJcAgEAAoGBAKwLEBxn03XbpEyt
7Ae1I7mm2eO04ZGgStCOML1lqmDpuoIDlduqai3rQ4kW8vX0seqfkGMHXnbKQWAB
Y5wsPfCtPccMkMhnextJVdJTuCelvs/ep/UgZ67RTChvBE1lnBiQ7UyizpFB5Cz/
sHkbJipfAdoonBZv7UGGggjaDr6LAgMBAAECgYBtcxhLye2oNBRhR+A5ww44RKKn
j2JVf9E4vszZIP10bB1gKyHCPrcQAXTUmQn2WTZ62gpEReLd1awjhJ63MxovoOh6
+4Lsr+EquvzGPaE6qtvvbeJRYaWTygROVv7S1gXa1rCgbOutInUeVhhw35whZVSJ
LwGgWRALUaAVVd/4SQJBANu6wE/6g/5PGVXk3ZFZwS5v47cv7PxzsqW0X0QM8UU3
T8jzCo3z6jnpbNrcMIPLI6oC9GeXbEM3TFIxvyI3BL0CQQDIcTPEstq6cYQjkg5a
3KOoboL5nCDAENv+DqTgvMJ1bhsZMKisYR1WkkvOQtSZEZJpCvc+bNkeJm+zpq3/
stjnAkAhGgw4wEO8PwxRDU53xC6/ISoMAdNQ4Nkr73VemhiK1d9WJY8UfYduvASj
IALLCAJSbWmGZaBwq9b6lvX1YJZ9AkA1bA5rH2wyguy/+j5/Mw0faAzacCU+a3/m
r4p8J3MAj08DoLdj8iI6n5U6rQ8ymL9X5cdNyP75DS96RzNa0hUDAkEAtuBdMCMd
X4MZBYlHUvYx+C7UoxDKwfqWCik+b9TWBQ4YV61QpxHkAbYIIbXUON2UDl4D2Fab
N3hhiVXBB+eeTQ==
-----END RSA PRIVATE KEY-----

RSA
);
        new LcobucciProvider('audience', 'subject', $key);
    }

    /**
     * @test
     * @dataProvider dataComputeHeader
     */
    public function computeHeader(Key $key, string $expectedSerializedKey): void
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

        $provider = new LcobucciProvider('audience', 'subject', $key);
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
                'key' => new Key(<<<'KEY'
-----BEGIN EC PRIVATE KEY-----
Proc-Type: 4,ENCRYPTED
DEK-Info: AES-256-CBC,EFCC09DFD0D5D1D0E7D2C8C6B913F293

Or2gh8zXsBfiGIRxmTS3VE8SaPbVbkfTZcMIRfXnZrlL3G88jskPnlBWfLT4sfI1
X+8D9LmP7eIcpb4Gncb0vVdkFFw+NPns/uT5o/m7SUloD/jac8vtsx47LoHCiXVM
bInhgqlgbqs8IXXLUCNY+UZ5PYZSPt6QXzUi+Lynft8=
-----END EC PRIVATE KEY-----
KEY
, 'test'),
                'serializedKey' => 'BDCgQkzSHClEg4otdckrN-duog2fAIk6O07uijwKr-w-4Etl6SRW2YiLUrN5vfvVHuhp7x8PxltmWWlbbM4IFyM',
            ],
            [
                'key' => new Key(<<<'KEY'
-----BEGIN EC PRIVATE KEY-----
MHcCAQEEIE3D+fkpWzW04YJ7Qwe00B1y7JYWsWr/EKnXfwK+1bjaoAoGCCqGSM49
AwEHoUQDQgAE0US8Ce/tJ9UbPjbEW91y77P5b/cVWb/JFIZsRW3FVFFeoIFgm2+e
BDxk371vPNqbEuOlvDu+4dwdNPYf3wvAsw==
-----END EC PRIVATE KEY-----
KEY
),
                'serializedKey' => 'BNFEvAnv7SfVGz42xFvdcu-z-W_3FVm_yRSGbEVtxVRRXqCBYJtvngQ8ZN-9bzzamxLjpbw7vuHcHTT2H98LwLM',
            ],
        ];
    }
}
