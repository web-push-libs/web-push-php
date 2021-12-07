<?php
/**
 * @author Igor Timoshenkov [it@campoint.net]
 * @started: 2018-12-03 11:31
 */

use GuzzleHttp\Psr7\Request;
use \Minishlink\WebPush\MessageSentReport;
use \GuzzleHttp\Psr7\Response;

/**
 * @covers \Minishlink\WebPush\MessageSentReport
 */
class MessageSentReportTest extends \PHPUnit\Framework\TestCase {

	/**
	 * @dataProvider generateReportsWithExpiration
	 */
	public function testIsSubscriptionExpired(MessageSentReport $report, bool $expected): void {
		$this->assertEquals($expected, $report->isSubscriptionExpired());
	}

	public function generateReportsWithExpiration(): array {
	    $request = new Request('POST', 'https://example.com');
		return [
			[new MessageSentReport($request, new Response(404)), true],
			[new MessageSentReport($request, new Response(410)), true],
			[new MessageSentReport($request, new Response(500)), false],
			[new MessageSentReport($request, new Response(200)), false]
		];
	}

	/**
	 * @dataProvider generateReportsWithEndpoints
	 */
	public function testGetEndpoint(MessageSentReport $report, string $expected): void {
		$this->assertEquals($expected, $report->getEndpoint());
	}

	public function generateReportsWithEndpoints(): array {
		return [
			[new MessageSentReport(new Request('POST', 'https://www.example.com')), 'https://www.example.com'],
			[new MessageSentReport(new Request('POST', 'https://m.example.com')), 'https://m.example.com'],
			[new MessageSentReport(new Request('POST', 'https://test.net')), 'https://test.net'],
		];
	}

	/**
	 * @dataProvider generateReportsWithRequests
	 */
	public function testGetRequest(MessageSentReport $report, Request $expected): void {
		$this->assertEquals($expected, $report->getRequest());
	}

	public function generateReportsWithRequests(): array {
		$r1 = new Request('POST', 'https://www.example.com');
		$r2 = new Request('PUT', 'https://m.example.com');
		$r3 = new Request('GET', 'https://test.net');

		return [
			[new MessageSentReport($r1), $r1],
			[new MessageSentReport($r2), $r2],
			[new MessageSentReport($r3), $r3],
		];
	}

	/**
	 * @dataProvider generateReportsWithJson
	 */
	public function testJsonSerialize(MessageSentReport $report, string $json): void {
		$this->assertJsonStringEqualsJsonString($json, json_encode($report, JSON_THROW_ON_ERROR));
	}

	public function generateReportsWithJson(): array {
		$request1Body = json_encode(['title' => 'test', 'body' => 'blah', 'data' => []]);
		$request1 = new Request('POST', 'https://www.example.com', [], $request1Body);
		$response1 = new Response(200, [], 'test');

		$request2Body = '';
		$request2 = new Request('POST', 'https://www.example.com', [], $request2Body);
		$response2 = new Response(410, [], 'Faield to do somthing', '1.1', 'Gone');

		return [
			[
				new MessageSentReport($request1, $response1),
				json_encode([
					'success'  => true,
					'expired'  => false,
					'reason'   => 'OK',
					'endpoint' => (string) $request1->getUri(),
					'payload'  => $request1Body,
				], JSON_THROW_ON_ERROR)
			],
			[
				new MessageSentReport($request2, $response2, false, 'Gone'),
				json_encode([
					'success'  => false,
					'expired'  => true,
					'reason'   => 'Gone',
					'endpoint' => (string) $request2->getUri(),
					'payload'  => $request2Body,
				], JSON_THROW_ON_ERROR)
			]
		];
	}

	/**
	 * @dataProvider generateReportsWithSuccess
	 */
	public function testIsSuccess(MessageSentReport $report, bool $expected): void {
		$this->assertEquals($expected, $report->isSuccess());
	}

	public function generateReportsWithSuccess(): array {
        $request = new Request('POST', 'https://example.com');
		return [
			[new MessageSentReport($request), true],
			[new MessageSentReport($request, null, true), true],
			[new MessageSentReport($request, null, false), false],
		];
	}
}
