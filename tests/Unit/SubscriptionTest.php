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

use Assert\InvalidArgumentException;
use Minishlink\WebPush\Subscription;
use PHPUnit\Framework\TestCase;
use Safe\Exceptions\JsonException;
use function Safe\json_encode;

/**
 * @internal
 */
final class SubscriptionTest extends TestCase
{
    /**
     * @test
     * @dataProvider dataInvalidSubscription
     */
    public function invalidInputCannotBeLoaded(string $input, string $exception, string $message): void
    {
        $this->expectException($exception);
        $this->expectExceptionMessage($message);

        Subscription::createFromString($input);
    }

    /**
     * @test
     */
    public function createSubscriptionFluent(): void
    {
        $subscription = Subscription::create('https://foo.bar');
        $subscription->getKeys()
            ->set('p256dh', 'Public key')
            ->set('auth', 'Authorization Token')
        ;

        static::assertEquals('https://foo.bar', $subscription->getEndpoint());
        static::assertEquals('Public key', $subscription->getKeys()->get('p256dh'));
        static::assertEquals('Authorization Token', $subscription->getKeys()->get('auth'));
        static::assertEquals(Subscription::CONTENT_ENCODING_AESGCM, $subscription->getContentEncoding());
    }

    /**
     * @test
     */
    public function createSubscriptionWithAESGCMENCODINGFluent(): void
    {
        $subscription = Subscription::create('https://foo.bar')
            ->withAESGCMContentEncoding()
        ;

        static::assertEquals('https://foo.bar', $subscription->getEndpoint());
        static::assertEquals(Subscription::CONTENT_ENCODING_AESGCM, $subscription->getContentEncoding());
    }

    /**
     * @test
     */
    public function createSubscriptionWithAES128GCMENCODINGFluent(): void
    {
        $subscription = Subscription::create('https://foo.bar')
            ->withAES128GCMContentEncoding()
        ;

        static::assertEquals('https://foo.bar', $subscription->getEndpoint());
        static::assertEquals(Subscription::CONTENT_ENCODING_AES128GCM, $subscription->getContentEncoding());
    }

    /**
     * @test
     * @dataProvider dataSubscription
     */
    public function createSubscription(string $endpoint, ?string $contentEncoding, array $keys): void
    {
        $subscription = Subscription::create($endpoint)
            ->witContentEncoding($contentEncoding)
        ;
        foreach ($keys as $k => $v) {
            $subscription->getKeys()->set($k, $v);
        }

        static::assertEquals($endpoint, $subscription->getEndpoint());
        static::assertEquals($keys, $subscription->getKeys()->all());
        static::assertEquals($contentEncoding, $subscription->getContentEncoding());

        $json = json_encode($subscription);
        $newSubscription = Subscription::createFromString($json);

        static::assertEquals($endpoint, $newSubscription->getEndpoint());
        static::assertEquals($keys, $newSubscription->getKeys()->all());
        static::assertEquals($contentEncoding, $newSubscription->getContentEncoding());
    }

    public function dataSubscription(): array
    {
        return [
            [
                'endpoint' => 'https://foo.bar',
                'content_encoding' => 'FOO',
                'keys' => [],
            ],
            [
                'endpoint' => 'https://bar.foo',
                'content_encoding' => 'FOO',
                'keys' => [
                    'authToken' => 'bar-foo',
                    'publicKey' => 'FOO-BAR',
                ],
            ],
        ];
    }

    public function dataInvalidSubscription(): array
    {
        return [
            [
                'input' => json_encode(0),
                'exception' => InvalidArgumentException::class,
                'message' => 'Invalid input',
            ],
            [
                'input' => '',
                'exception' => JsonException::class,
                'message' => 'Syntax error',
            ],
            [
                'input' => '[]',
                'exception' => InvalidArgumentException::class,
                'message' => 'Invalid input',
            ],
            [
                'input' => json_encode([
                    'endpoint' => 0,
                ]),
                'exception' => InvalidArgumentException::class,
                'message' => 'Invalid input',
            ],
            [
                'input' => json_encode([
                    'endpoint' => 'https://foo.bar',
                    'contentEncoding' => 0,
                ]),
                'exception' => InvalidArgumentException::class,
                'message' => 'Invalid input',
            ],
            [
                'input' => json_encode([
                    'endpoint' => 'https://foo.bar',
                    'contentEncoding' => 'FOO',
                    'keys' => [
                        12 => 0,
                    ],
                ]),
                'exception' => InvalidArgumentException::class,
                'message' => 'Invalid key name',
            ],
            [
                'input' => json_encode([
                    'endpoint' => 'https://foo.bar',
                    'contentEncoding' => 'FOO',
                    'keys' => [
                        'authToken' => 'BAR',
                        'publicKey' => 0,
                    ],
                ]),
                'exception' => InvalidArgumentException::class,
                'message' => 'Invalid key value',
            ],
        ];
    }
}
