<?php declare(strict_types=1);
/*
 * This file is part of the WebPush library.
 *
 * (c) Louis Lagrange <lagrange.louis@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(WebPush::class)]
final class WebPushRejectedReportTest extends PHPUnit\Framework\TestCase
{
    /**
     * @throws \ErrorException
     */
    public function testNetworkFailureProducesRejectedReport(): void
    {
        $handler = new MockHandler([
            new ConnectException('Connection refused', new Request('POST', 'https://push.example.com/send')),
        ]);
        $webPush = new WebPush([], [], 30, ['handler' => HandlerStack::create($handler)]);

        $report = $webPush->sendOneNotification(new Subscription('https://push.example.com/send'));

        $this->assertFalse($report->isSuccess());
        $this->assertNull($report->getResponse());
        $this->assertEquals('Connection refused', $report->getReason());
        $this->assertEquals('https://push.example.com/send', $report->getEndpoint());
    }

    /**
     * @throws \ErrorException
     */
    public function testHttpErrorProducesRejectedReportWithResponse(): void
    {
        $handler = new MockHandler([
            new Response(410, [], '', '1.1', 'Gone'),
        ]);
        $webPush = new WebPush([], [], 30, ['handler' => HandlerStack::create($handler)]);

        $report = $webPush->sendOneNotification(new Subscription('https://push.example.com/send'));

        $this->assertFalse($report->isSuccess());
        $this->assertTrue($report->isSubscriptionExpired());
        $this->assertEquals(410, $report->getResponse()->getStatusCode());
        $this->assertNotEmpty($report->getReason());
    }
}
