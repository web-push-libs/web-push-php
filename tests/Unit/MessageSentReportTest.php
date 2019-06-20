<?php
/**
 * @author Igor Timoshenkov [it@campoint.net]
 * @started: 2018-12-03 11:31
 */

namespace Minishlink\WebPush\Tests\Unit;

use GuzzleHttp\Psr7\Request;
use \Minishlink\WebPush\MessageSentReport;
use \GuzzleHttp\Psr7\Response;
use Minishlink\WebPush\Tests\TestCase;

/**
 * @covers \Minishlink\WebPush\MessageSentReport
 */
final class MessageSentReportTest extends TestCase
{

    /**
     * @param MessageSentReport $report
     * @param bool              $expected
     * @dataProvider generateReportsWithExpiration
     */
    public function testIsSubscriptionExpired(MessageSentReport $report, bool $expected): void
    {
        $this->assertEquals($expected, $report->isSubscriptionExpired());
    }

    /**
     * @return array
     */
    public function generateReportsWithExpiration(): array
    {
        $request = new Request('POST', 'https://example.com');
        return [
            [new MessageSentReport($request, new Response(404)), true],
            [new MessageSentReport($request, new Response(410)), true],
            [new MessageSentReport($request, new Response(500)), false],
            [new MessageSentReport($request, new Response(200)), false]
        ];
    }

    /**
     * @param MessageSentReport $report
     * @param string            $expected
     * @dataProvider generateReportsWithEndpoints
     */
    public function testGetEndpoint(MessageSentReport $report, string $expected): void
    {
        $this->assertEquals($expected, $report->getEndpoint());
    }

    /**
     * @return array
     */
    public function generateReportsWithEndpoints(): array
    {
        return [
            [new MessageSentReport(new Request('POST', 'https://www.example.com'), new Response(200)), 'https://www.example.com'],
            [new MessageSentReport(new Request('POST', 'https://m.example.com'), new Response(200)), 'https://m.example.com'],
            [new MessageSentReport(new Request('POST', 'https://test.net'), new Response(200)), 'https://test.net'],
        ];
    }

    /**
     * @param MessageSentReport $report
     * @param bool              $expected
     * @dataProvider generateReportsWithSuccess
     */
    public function testIsSuccess(MessageSentReport $report, bool $expected): void
    {
        $this->assertEquals($expected, $report->isSuccess());
    }

    /**
     * @return array
     */
    public function generateReportsWithSuccess(): array
    {
        $request = new Request('POST', 'https://example.com');
        return [
            [new MessageSentReport($request, new Response(200)), true],
            [new MessageSentReport($request, new Response(200)), true],
            [new MessageSentReport($request, new Response(404)), false],
        ];
    }
}
