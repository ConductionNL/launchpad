<?php

/**
 * Activity call sites golden test
 *
 * Pins the activity rows that the bulk-operation, template-resync and
 * acknowledgement audit helpers write through a real ActivityPublisher,
 * against a recorded fixture.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Activity
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Activity;

use OCA\LaunchPad\Activity\PublisherTools;
use Closure;
use OCA\LaunchPad\Activity\ActivityPublisher;
use OCA\LaunchPad\Activity\DebounceHelper;
use OCA\LaunchPad\Db\Acknowledgement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AcknowledgementService;
use OCA\LaunchPad\Service\BulkOperationService;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Service\TemplateResyncService;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\IGroupManager;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionClass;

/**
 * The audit rows of three services, case by case.
 *
 * @covers \OCA\LaunchPad\Service\AcknowledgementService
 * @covers \OCA\LaunchPad\Service\BulkOperationService
 * @covers \OCA\LaunchPad\Service\TemplateResyncService
 * @uses   \OCA\LaunchPad\Activity\ActivityPublisher
 * @uses   \OCA\LaunchPad\Db\Acknowledgement
 * @uses   \OCA\LaunchPad\Activity\ActivityRow
 * @uses   \OCA\LaunchPad\Activity\ActivitySubject
 * @uses   \OCA\LaunchPad\Activity\DebounceHelper
 * @uses   \OCA\LaunchPad\Activity\PublisherTools
 * @uses   \OCA\LaunchPad\Db\Dashboard
 */
class ActivityCallSitesGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/activity-call-sites.json';

	/**
	 * The events written in this test.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $rows = [];

	/**
	 * A service without its constructor, with the named collaborators set.
	 *
	 * @param class-string         $class      The service class.
	 * @param array<string, mixed> $properties Property values by name.
	 *
	 * @return object The service.
	 */
	private function service(string $class, array $properties): object {
		$service = (new ReflectionClass($class))->newInstanceWithoutConstructor();
		Closure::bind(
			function (array $values): void {
				foreach ($values as $name => $value) {
					$this->$name = $value;
				}
			},
			$service,
			$class
		)($properties);
		return $service;
	}//end service()

	/**
	 * A real publisher over a recording activity manager.
	 *
	 * @return ActivityPublisher
	 */
	private function publisher(): ActivityPublisher {
		$manager = $this->createMock(IManager::class);
		$manager->method('generateEvent')->willReturnCallback(static fn (): IEvent => new RecordingEvent());
		$manager->method('publish')->willReturnCallback(
			function (IEvent $event): void {
				$this->rows[] = [
					'type'     => $event->getType(),
					'author'   => $event->getAuthor(),
					'affected' => $event->getAffectedUser(),
					'params'   => $event->getSubjectParameters(),
					'object'   => $event->getObjectName(),
					'link'     => $event->getLink(),
				];
			}
		);

		return new ActivityPublisher(
			manager: $manager,
			groupManager: $this->createMock(IGroupManager::class),
			userManager: $this->createMock(IUserManager::class),
			tools: new PublisherTools(
				debounce: new DebounceHelper(clock: static fn (): int => 1000),
				logger: new NullLogger()
			)
		);
	}//end publisher()

	/**
	 * Call a private method.
	 *
	 * @param object       $service The service.
	 * @param string       $method  The method name.
	 * @param list<mixed>  $args    Positional arguments.
	 *
	 * @return void
	 */
	private static function invoke(object $service, string $method, array $args): void {
		(new ReflectionClass($service))->getMethod($method)->invoke($service, ...$args);
	}//end invoke()

	/**
	 * Each helper writes the recorded rows.
	 *
	 * @return void
	 */
	public function testRowsMatchTheRecordedFixture(): void {
		$actual = [];

		$bulk = $this->service(BulkOperationService::class, ['activityPublisher' => $this->publisher(), 'logger' => new NullLogger()]);
		$run = static fn (int $count, string $user, int $ms, bool $dryRun): array => [
			'dashboardUuids' => array_fill(0, $count, 'u'),
			'userId'         => $user,
			'start'          => (microtime(true) - ($ms / 1000)),
			'dryRun'         => $dryRun,
		];
		self::invoke($bulk, 'emitAuditEvent', ['delete', $run(3, 'admin', 12, false)]);
		self::invoke($bulk, 'emitAuditEvent', ['status', $run(2, 'root', 5, true) + ['extra' => ['publicationStatus' => 'published', 'dryRun' => 'x']]]);
		// The duration is measured from the run's start: at least the time
		// that had passed, and pinned to it for the comparison.
		foreach ([12, 5] as $i => $ms) {
			$measured = $this->rows[$i]['params']['durationMs'];
			$this->assertGreaterThanOrEqual($ms, $measured);
			$this->assertLessThan($ms + 1000, $measured);
			$this->rows[$i]['params']['durationMs'] = $ms;
		}

		$actual['bulk'] = $this->rows;

		$this->rows = [];
		$resync     = $this->service(TemplateResyncService::class, ['activityPublisher' => $this->publisher(), 'logger' => new NullLogger()]);
		$template = new Dashboard();
		$template->setName('Intranet');
		self::invoke($resync, 'emitAuditEvent', [$template, ['templateId' => 7, 'strategy' => 'merge', 'affectedCount' => 4, 'actingAdminId' => 'admin']]);
		$actual['resync'] = $this->rows;

		$this->rows = [];
		$placements = $this->createMock(WidgetPlacementMapper::class);
		$placements->method('findByAnnouncementKey')->willReturn([]);
		$ack = $this->service(
			AcknowledgementService::class,
			['activityPublisher' => $this->publisher(), 'placementMapper' => $placements]
		);
		$receipt = new Acknowledgement();
		$receipt->setAnnouncementKey('ak-1');
		$receipt->setUserId('alice');
		$receipt->setContentVersion(1);
		self::invoke($ack, 'emitAcknowledgedActivity', [$receipt]);
		$actual['acknowledged'] = $this->rows;

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
			$this->markTestIncomplete('Fixture recorded.');
		}

		$this->assertSame(json_decode((string) file_get_contents(self::FIXTURE), true), $actual);
	}//end testRowsMatchTheRecordedFixture()
}//end class
