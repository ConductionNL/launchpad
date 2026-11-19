<?php

/**
 * Embed usage telemetry unit tests (REQ-EMB-008).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-008--usage-telemetry-without-leaking-pii
 */

declare(strict_types=1);

namespace Unit\Service\Embed;

use OCA\LaunchPad\Entity\EmbedUsageEvent;
use OCA\LaunchPad\Service\Embed\EmbedObjectStore;
use OCA\LaunchPad\Service\Embed\UsageTelemetryService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Unit\Service\Embed\Support\InMemoryObjectService;

#[CoversClass(UsageTelemetryService::class)]
#[CoversClass(EmbedUsageEvent::class)]
#[UsesClass(EmbedObjectStore::class)]
class UsageTelemetryServiceTest extends TestCase {

	private const FIREFOX = 'Mozilla/5.0 (X11; Linux x86_64; rv:125.0) Gecko/20100101 Firefox/125.0';

	private InMemoryObjectService $objects;

	private bool $enabled = true;

	/**
	 * @var list<object>
	 */
	private array $dispatched = [];

	private function service(): UsageTelemetryService {
		$this->objects ??= new InMemoryObjectService();
		$container       = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturn($this->objects);

		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueBool')->willReturnCallback(function (string $app, string $key, bool $default): bool {
			$this->assertSame(['launchpad', 'embed_telemetry_enabled', true], [$app, $key, $default]);
			return $this->enabled;
		});
		$config->method('setValueBool')->willReturnCallback(function (string $app, string $key, bool $value): bool {
			$this->enabled = $value;
			return true;
		});

		$time = $this->createMock(ITimeFactory::class);
		$time->method('getTime')->willReturn((int)strtotime('2026-05-22T14:30:00Z'));

		$dispatcher = $this->createMock(IEventDispatcher::class);
		$dispatcher->method('dispatchTyped')->willReturnCallback(function (object $event): void {
			$this->dispatched[] = $event;
		});

		return new UsageTelemetryService(new EmbedObjectStore($container), $config, $time, $dispatcher, $this->createMock(LoggerInterface::class));
	}//end service()

	/**
	 * A recorded event keeps the origin, the browser family and a hash —
	 * never the IP, the full user agent or the host page's path.
	 *
	 * @return void
	 */
	public function testARecordedEventHoldsNoPersonalData(): void {
		$event = $this->service()->record(
			tokenId: 't-1',
			eventType: 'pageView',
			origin: null,
			referer: 'https://intranet.zeist.nl/afdeling/woo?medewerker=jan',
			userAgent: self::FIREFOX,
			viewport: '1024x768',
			status: 200,
			latencyMs: 87
		);

		$this->assertSame(
			[
				'tokenId'            => 't-1',
				'eventType'          => 'pageView',
				'hostOrigin'         => 'https://intranet.zeist.nl',
				'userAgent'          => 'Firefox 125',
				'userAgentHash'      => hash('sha256', self::FIREFOX),
				'viewportSize'       => 'medium',
				'timestamp'          => '2026-05-22T14:30:00Z',
				'responseStatusCode' => 200,
				'responseLatencyMs'  => 87,
				'correlationId'      => null,
				'id'                 => 'obj-1',
			],
			$event
		);
		$this->assertStringNotContainsString('medewerker', (string)json_encode($this->objects->objects));
		$this->assertStringNotContainsString('Gecko', (string)json_encode($this->objects->objects));
	}//end testARecordedEventHoldsNoPersonalData()

	/**
	 * Browser families, origins and viewport buckets are read conservatively.
	 *
	 * @return void
	 */
	public function testTheEventFieldsAreBucketed(): void {
		$agents = [
			'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36 Edg/124.0' => 'Edge 124',
			'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.6367.60 Safari/537.36' => 'Chrome 124',
			'Mozilla/5.0 (Macintosh) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4 Safari/605.1.15' => 'Safari 17',
			'Mozilla/5.0 AppleWebKit/537.36 Chrome/120.0 Safari/537.36 OPR/106.0' => 'Opera 106',
			'curl/8.0' => 'Other',
		];
		foreach ($agents as $agent => $family) {
			$this->assertSame($family, EmbedUsageEvent::browser(userAgent: $agent));
		}

		$this->assertSame('https://portal.example.com:8443', EmbedUsageEvent::hostOrigin(origin: 'HTTPS://Portal.Example.com:8443', referer: null));
		$this->assertNull(EmbedUsageEvent::hostOrigin(origin: 'null', referer: 'file:///etc/passwd'));
		$this->assertSame(['small', 'medium', 'large', 'large', 'unknown', 'unknown'], array_map(
			static fn (?string $v): string => EmbedUsageEvent::viewportBucket(viewport: $v),
			['375', '768x1024', '1200', 'LARGE', 'huge', null]
		));
		$this->assertSame('pageView', EmbedUsageEvent::build('t', 'surprise', null, null, null, null, 200, -3, 'now')['eventType']);
		$this->assertSame(0, EmbedUsageEvent::build('t', 'export', null, null, null, null, 200, -3, 'now')['responseLatencyMs']);
	}//end testTheEventFieldsAreBucketed()

	/**
	 * With telemetry off nothing is written; turning it off and on is audited
	 * once per change.
	 *
	 * @return void
	 */
	public function testTheKillSwitchStopsWritesAndIsAudited(): void {
		$service = $this->service();
		$service->setEnabled(enabled: false, userId: 'admin');
		$this->assertNull($service->record('t-1', 'pageView', null, null, null, null, 200, 5));
		$this->assertSame([], $this->objects->objects);

		$service->setEnabled(enabled: false, userId: 'admin');
		$service->setEnabled(enabled: true, userId: 'admin');
		$this->assertCount(2, $this->dispatched);
		$this->assertInstanceOf(CriticalActionPerformedEvent::class, $this->dispatched[0]);
		$this->assertSame(['off', 'admin'], $this->dispatched[0]->getParameters());
		$this->assertSame(['on', 'admin'], $this->dispatched[1]->getParameters());
		$this->assertNotNull($service->record('t-1', 'pageView', null, null, null, null, 200, 5));
	}//end testTheKillSwitchStopsWritesAndIsAudited()

	/**
	 * A failed write is absorbed: the embed still renders.
	 *
	 * @return void
	 */
	public function testAFailedWriteIsAbsorbed(): void {
		$service = $this->service();
		$this->objects->failSaves = true;
		$this->assertNull($service->record('t-1', 'pageView', null, null, null, null, 200, 5));
	}//end testAFailedWriteIsAbsorbed()

	/**
	 * The report aggregates the last seven days of one token, and counts use
	 * after revocation; other tokens and older events are left out.
	 *
	 * @return void
	 */
	public function testTheReportAggregatesOnly(): void {
		$this->service();
		$rows = [
			['t-1', 'pageView', 'https://a.nl', 'Firefox 125', 'small', '2026-05-22T09:00:00Z', 200],
			['t-1', 'pageView', 'https://a.nl', 'Firefox 125', 'large', '2026-05-21T09:00:00Z', 200],
			['t-1', 'pageView', 'https://b.nl', 'Chrome 124', 'medium', '2026-05-22T15:00:00Z', 401],
			['t-1', 'drillDown', 'https://a.nl', 'Chrome 124', 'medium', '2026-05-20T09:00:00Z', 200],
			['t-1', 'rateLimited', 'https://a.nl', 'Chrome 124', 'medium', '2026-05-20T09:00:01Z', 429],
			['t-1', 'pageView', 'https://old.nl', 'Chrome 124', 'medium', '2026-05-01T09:00:00Z', 200],
			['t-2', 'pageView', 'https://other.nl', 'Chrome 124', 'medium', '2026-05-22T09:00:00Z', 200],
		];
		foreach ($rows as $i => [$token, $type, $origin, $browser, $viewport, $at, $status]) {
			$this->objects->objects['launchpad/embed_usage_event']['e' . $i] = [
				'tokenId' => $token, 'eventType' => $type, 'hostOrigin' => $origin, 'userAgent' => $browser,
				'viewportSize' => $viewport, 'timestamp' => $at, 'responseStatusCode' => $status,
			];
		}

		$report = $this->service()->report(tokenId: 't-1', revokedAt: '2026-05-22T14:35:00Z');

		$this->assertSame(['pageViews' => 3, 'lastActivity' => '2026-05-22T15:00:00Z', 'usedAfterRevocation' => 1], $report['summary']);
		$this->assertSame([0, 0, 0, 0, 0, 1, 2], array_column($report['byDate'], 'count'));
		$this->assertSame('2026-05-22', $report['byDate'][6]['label']);
		$this->assertSame([['label' => 'https://a.nl', 'count' => 2], ['label' => 'https://b.nl', 'count' => 1]], $report['byHostOrigin']);
		$this->assertSame(['small' => 1, 'medium' => 1, 'large' => 1, 'unknown' => 0], $report['byViewport']);
		$this->assertSame([['label' => 'Firefox 125', 'count' => 2], ['label' => 'Chrome 124', 'count' => 1]], $report['byBrowser']);
		$this->assertSame(['filterApplied' => 0, 'drillDown' => 1, 'export' => 0], $report['interactions']);
		$this->assertSame(1, $report['rateLimited']);
		$this->assertSame(['200' => 3, '401' => 1, '429' => 1], $report['byStatus']);
		$this->assertStringNotContainsString('tokenHash', (string)json_encode($report));
	}//end testTheReportAggregatesOnly()
}//end class
