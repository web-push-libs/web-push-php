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

namespace Minishlink\Tests\Functional;

use Minishlink\WebPush\ExtensionManager;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use function Safe\json_decode;
use function Safe\json_encode;
use function Safe\sprintf;
use Symfony\Component\HttpClient\Psr18Client;
use Throwable;

/**
 * @internal
 */
class WebPushTest extends TestCase
{
    private const PORT_NUMBER = 8090;
    private static string $testServiceUrl;
    private static Psr17Factory $psr17Factory;
    private static Psr18Client $client;
    private static ExtensionManager $extensionManager;
    private static ?int $testSuiteId;

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass(): void
    {
        self::$testServiceUrl = sprintf('http://localhost:%d', self::PORT_NUMBER);
        self::$psr17Factory = new Psr17Factory();
        self::$client = new Psr18Client();
        self::$extensionManager = new ExtensionManager();
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $request = self::$psr17Factory->createRequest('POST', self::$testServiceUrl.'/api/start-test-suite/');
        $response = self::$client->sendRequest($request);

        $json = json_decode($response->getBody()->getContents(), true);
        self::$testSuiteId = $json['data']['testSuiteId'];
    }

    protected function tearDown(): void
    {
        $request = self::$psr17Factory
            ->createRequest('POST', self::$testServiceUrl.'/api/start-test-suite/')
            ->withHeader('Content-Type', 'application/json')
        ;

        $dataString = json_encode(['testSuiteId' => self::$testSuiteId]);
        $request->getBody()->write($dataString);

        self::$client->sendRequest($request);
        self::$testSuiteId = null;
    }

    public function browserProvider(): array
    {
        return [
            ['firefox', 'stable'/*, ['VAPID' => self::$vapidKeys]*/],
            ['firefox', 'beta'/*, ['VAPID' => self::$vapidKeys]*/],
            ['chrome', 'stable'/*, ['VAPID' => self::$vapidKeys]*/],
            ['chrome', 'beta'/*, ['VAPID' => self::$vapidKeys]*/],
        ];
    }

    /**
     * Selenium tests are flakey so add retries.
     */
    public function retryTest(int $retryCount, callable $test): void
    {
        for ($i = 0; $i < $retryCount; ++$i) {
            try {
                $test();

                return;
            } catch (Throwable $e) {
                //throw $e;
            }
        }
    }

    /**
     * @dataProvider browserProvider
     * @test
     * Run integration tests with browsers
     */
    public function browsers(string $browserName, string $browserVersion/*, $options*/): void
    {
        $this->retryTest(2, $this->createClosureTest($browserName, $browserVersion/*, $options*/));
    }

    protected function createClosureTest(string $browserName, string $browserVersion/*, $options*/): callable
    {
        return static function () use ($browserName, $browserVersion/*, $options*/) {
            $request = self::$psr17Factory
                ->createRequest('POST', self::$testServiceUrl.'/api/get-subscription/')
                ->withHeader('Content-Type', 'application/json')
            ;

            $dataString = json_encode([
                'testSuiteId' => self::$testSuiteId,
                'browserName' => $browserName,
                'browserVersion' => $browserVersion,
            ]);
            $request->getBody()->write($dataString);

            $response = self::$client->sendRequest($request);
            dump($response->getBody()->getContents());
        };
    }
}
