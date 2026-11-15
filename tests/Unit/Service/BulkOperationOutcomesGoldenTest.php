<?php

/**
 * BulkOperationOutcomesGoldenTest
 *
 * Golden record of every per-dashboard outcome of the bulk delete, move,
 * status and reindex batches: counts, skip and error entries, the writes
 * made and the failures logged, with and without dry-run and cascade.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Activity\ActivityPublisher;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\BulkDashboardStores;
use OCA\LaunchPad\Service\BulkOperationAccess;
use OCA\LaunchPad\Service\BulkOperationService;
use OCA\LaunchPad\Service\BulkStatusRequest;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IAppConfig;
use OCP\IGroupManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Every per-dashboard branch of every bulk batch.
 */
class BulkOperationOutcomesGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/bulk-operation-outcomes.json';

	private const UUIDS = ['gone', 'leaf', 'parent', 'same-parent', 'cycle', 'boom', 'draft'];

	/**
	 * Writes and log lines of the current batch.
	 *
	 * @var array<int, string>
	 */
	private array $trace = [];

	/**
	 * Every batch answers, writes and logs as recorded.
	 *
	 * @return void
	 */
	public function testOutcomesMatchTheRecording(): void {
		$batches = [];
		foreach ([false, true] as $dryRun) {
			$flag = $dryRun ? 'dry run' : 'real';
			foreach ([false, true] as $cascade) {
				$batches['delete / '.$flag.' / cascade '.($cascade ? 'on' : 'off')] = fn (BulkOperationService $s): array => $s->bulkDelete(dashboardUuids: self::UUIDS, userId: 'admin', dryRun: $dryRun, cascade: $cascade);
			}
			$batches['move / '.$flag] = fn (BulkOperationService $s): array => $s->bulkMove(dashboardUuids: self::UUIDS, parentUuid: 'p-new', userId: 'admin', dryRun: $dryRun);
			$batches['move to root / '.$flag] = fn (BulkOperationService $s): array => $s->bulkMove(dashboardUuids: self::UUIDS, parentUuid: null, userId: 'admin', dryRun: $dryRun);
			$batches['publish / '.$flag] = fn (BulkOperationService $s): array => $s->bulkStatus(dashboardUuids: self::UUIDS, request: new BulkStatusRequest(publicationStatus: Dashboard::STATUS_PUBLISHED, publishAt: null, dryRun: $dryRun), userId: 'admin');
			$batches['draft / '.$flag] = fn (BulkOperationService $s): array => $s->bulkStatus(dashboardUuids: self::UUIDS, request: new BulkStatusRequest(publicationStatus: Dashboard::STATUS_DRAFT, publishAt: null, dryRun: $dryRun), userId: 'admin');
			$batches['reindex / '.$flag] = fn (BulkOperationService $s): array => $s->bulkReindex(dashboardUuids: self::UUIDS, userId: 'admin', dryRun: $dryRun);
		}

		$actual = [];
		foreach ($batches as $name => $batch) {
			$this->trace   = [];
			$payload       = $batch($this->service());
			$actual[$name] = ['payload' => $payload, 'trace' => $this->trace];
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
			$this->markTestIncomplete('Fixture recorded.');
		}

		$this->assertSame(json_decode((string) file_get_contents(self::FIXTURE), true), json_decode((string) json_encode($actual), true));
	}//end testOutcomesMatchTheRecording()

	/**
	 * The service over mapped dashboards: one per branch.
	 *
	 * @return BulkOperationService
	 */
	private function service(): BulkOperationService {
		$rows = [];
		foreach (self::UUIDS as $i => $uuid) {
			$dashboard = new Dashboard();
			$dashboard->setId($i + 1);
			$dashboard->setUuid($uuid);
			$dashboard->setUserId('owner');
			$dashboard->setParentUuid(($uuid === 'same-parent') ? 'p-new' : 'p-old');
			$dashboard->setPublicationStatus(($uuid === 'draft') ? Dashboard::STATUS_DRAFT : Dashboard::STATUS_PUBLISHED);
			$rows[$uuid] = $dashboard;
		}

		$target = new Dashboard();
		$target->setId(99);
		$target->setUuid('p-new');
		$rows['p-new'] = $target;

		$mapper = $this->createMock(DashboardMapper::class);
		$mapper->method('findByUuid')->willReturnCallback(
			static function (string $uuid) use ($rows): Dashboard {
				if ($uuid === 'gone' || isset($rows[$uuid]) === false) {
					throw new DoesNotExistException('gone');
				}
				return clone $rows[$uuid];
			}
		);
		$mapper->method('countChildrenByParent')->willReturnCallback(static fn (string $uuid): int => ($uuid === 'parent') ? 2 : 0);
		$mapper->method('update')->willReturnCallback(
			function (Dashboard $dashboard): Dashboard {
				$this->trace[] = 'update '.$dashboard->getUuid().' parent='.$dashboard->getParentUuid().' status='.$dashboard->getPublicationStatus();
				if ($dashboard->getUuid() === 'boom') {
					throw new RuntimeException('write failed');
				}
				return $dashboard;
			}
		);
		$mapper->method('delete')->willReturnCallback(
			function (Dashboard $dashboard): Dashboard {
				$this->trace[] = 'delete '.$dashboard->getUuid();
				if ($dashboard->getUuid() === 'boom') {
					throw new RuntimeException('delete failed');
				}
				return $dashboard;
			}
		);

		$placements = $this->createMock(WidgetPlacementMapper::class);
		$placements->method('deleteByDashboardId')->willReturnCallback(
			function (int $id): int {
				$this->trace[] = 'placements '.$id;
				return 0;
			}
		);

		$tree = $this->createMock(DashboardTreeService::class);
		$tree->method('validateParent')->willReturnCallback(
			static function (?string $movingUuid, ?string $newParentUuid): void {
				if ($movingUuid === 'cycle') {
					throw new InvalidArgumentException('cycle via '.$newParentUuid);
				}
			}
		);
		$tree->method('deleteSubtree')->willReturnCallback(
			function (Dashboard $dashboard): int {
				$this->trace[] = 'subtree '.$dashboard->getUuid();
				return 3;
			}
		);

		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('error')->willReturnCallback(
			function (string $message, array $context): void {
				$this->trace[] = 'log '.$message.' '.$context['uuid'].' '.$context['exception']->getMessage();
			}
		);

		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturn(true);
		$config = $this->createMock(IAppConfig::class);
		$config->method('getValueInt')->willReturn(500);
		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('resolveAccessLevel')->willReturn(Dashboard::PERMISSION_FULL);

		return new BulkOperationService(
			stores: new BulkDashboardStores(dashboardMapper: $mapper, placementMapper: $placements, treeService: $tree),
			access: new BulkOperationAccess(permissionService: $permissions, groupManager: $groups, appConfig: $config),
			activityPublisher: $this->createMock(ActivityPublisher::class),
			logger: $logger
		);
	}//end service()
}//end class
