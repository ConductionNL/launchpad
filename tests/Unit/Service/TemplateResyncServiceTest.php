<?php

/**
 * TemplateResyncServiceTest
 *
 * Unit tests for the `admin-template-resync` capability
 * (REQ-RESYNC-001..005). Covers:
 *   - Dry-run reports the plan and mutates nothing.
 *   - `merge` reconciles template-origin placements while preserving
 *     user-added ones; `overwrite` replaces the whole layout.
 *   - Compulsory widgets are reconciled (restored + position/flags
 *     aligned) under BOTH strategies.
 *   - Idempotency — re-applying an unchanged template is a no-op.
 *   - The audit record is written exactly once per real run.
 *   - Affected users are notified.
 *   - Invalid strategy / non-template dashboard are rejected.
 *   - Large target groups enqueue the async job instead of applying
 *     inline.
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
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\TemplateResyncService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for {@see TemplateResyncService}.
 */
class TemplateResyncServiceTest extends TestCase {
	/** @var DashboardMapper&MockObject */
	private $dashboardMapper;

	/** @var WidgetPlacementMapper&MockObject */
	private $placementMapper;

	/** @var IDBConnection&MockObject */
	private $db;

	/** @var ActivityPublisher&MockObject */
	private $activityPublisher;

	/** @var INotificationManager&MockObject */
	private $notificationManager;

	/** @var IJobList&MockObject */
	private $jobList;

	/** @var LoggerInterface&MockObject */
	private $logger;

	private TemplateResyncService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->dashboardMapper = $this->createMock(DashboardMapper::class);
		$this->placementMapper = $this->createMock(WidgetPlacementMapper::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->activityPublisher = $this->createMock(ActivityPublisher::class);
		$this->notificationManager = $this->createMock(INotificationManager::class);
		$this->jobList = $this->createMock(IJobList::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->service = new TemplateResyncService(
			dashboardMapper: $this->dashboardMapper,
			placementMapper: $this->placementMapper,
			db: $this->db,
			activityPublisher: $this->activityPublisher,
			notificationManager: $this->notificationManager,
			jobList: $this->jobList,
			logger: $this->logger,
		);
	}

	// ---------------------------------------------------------------
	// Helpers
	// ---------------------------------------------------------------

	private function makeTemplate(int $id = 1): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId($id);
		$dashboard->setUuid('template-uuid-' . $id);
		$dashboard->setName('Marketing Dashboard');
		$dashboard->setType(Dashboard::TYPE_ADMIN_TEMPLATE);
		$dashboard->setUserId(null);

		return $dashboard;
	}

	private function makeUserDashboard(int $id = 5): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId($id);
		$dashboard->setUuid('user-dashboard-uuid-' . $id);
		$dashboard->setName('My Dashboard');
		$dashboard->setType(Dashboard::TYPE_USER);
		$dashboard->setUserId('alice');

		return $dashboard;
	}

	private function makeCopy(int $id, string $userId): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId($id);
		$dashboard->setUuid('copy-uuid-' . $id);
		$dashboard->setName('Marketing Dashboard');
		$dashboard->setType(Dashboard::TYPE_USER);
		$dashboard->setUserId($userId);
		$dashboard->setBasedOnTemplate(1);

		return $dashboard;
	}

	private function makePlacement(
		int $id,
		int $dashboardId,
		string $widgetId = 'w1',
		int $gridX = 0,
		int $isCompulsory = 0,
		?int $templatePlacementId = null,
	): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setId($id);
		$placement->setDashboardId($dashboardId);
		$placement->setWidgetId($widgetId);
		$placement->setGridX($gridX);
		$placement->setGridY(0);
		$placement->setGridWidth(4);
		$placement->setGridHeight(4);
		$placement->setIsCompulsory($isCompulsory);
		$placement->setIsVisible(1);
		$placement->setShowTitle(1);
		$placement->setSortOrder(0);
		$placement->setRequiresAcknowledgement(0);
		$placement->setReacknowledgeOnChange(0);
		$placement->setAcknowledgementContentVersion(1);
		$placement->setTemplatePlacementId($templatePlacementId);

		return $placement;
	}

	// ---------------------------------------------------------------
	// Validation
	// ---------------------------------------------------------------

	public function testInvalidStrategyIsRejected(): void {
		$this->dashboardMapper->method('find')->willReturn($this->makeTemplate());

		$this->expectException(InvalidArgumentException::class);
		$this->service->resync(
			templateId: 1,
			strategy: 'replace-all',
			dryRun: true,
			actingAdminId: 'admin1'
		);
	}

	public function testNonTemplateDashboardIsRejected(): void {
		$this->dashboardMapper->method('find')->willReturn($this->makeUserDashboard());

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Not an admin template');
		$this->service->resync(
			templateId: 5,
			strategy: TemplateResyncService::STRATEGY_OVERWRITE,
			dryRun: true,
			actingAdminId: 'admin1'
		);
	}

	public function testUnknownTemplateIdIsRejected(): void {
		$this->dashboardMapper->method('find')
			->willThrowException(new DoesNotExistException(msg: 'not found'));

		$this->expectException(InvalidArgumentException::class);
		$this->service->resync(
			templateId: 999,
			strategy: TemplateResyncService::STRATEGY_OVERWRITE,
			dryRun: true,
			actingAdminId: 'admin1'
		);
	}

	// ---------------------------------------------------------------
	// Dry-run — reports without mutating (REQ-RESYNC-002)
	// ---------------------------------------------------------------

	public function testDryRunReportsAffectedCopiesWithoutMutating(): void {
		$template = $this->makeTemplate();
		$this->dashboardMapper->method('find')->willReturn($template);

		// Template now has 2 placements; the announcements one is new.
		$templatePlacements = [
			$this->makePlacement(id: 10, dashboardId: 1, widgetId: 'links'),
			$this->makePlacement(id: 11, dashboardId: 1, widgetId: 'announcements'),
		];

		$copyA = $this->makeCopy(id: 100, userId: 'alice');
		$copyB = $this->makeCopy(id: 101, userId: 'bob');

		$this->dashboardMapper->method('findByBasedOnTemplate')
			->willReturn([$copyA, $copyB]);

		// alice's copy still has only the original "links" placement,
		// cloned from template placement 10.
		$aliceCopyPlacements = [
			$this->makePlacement(id: 200, dashboardId: 100, widgetId: 'links', templatePlacementId: 10),
		];
		// bob's copy also has "links" plus a personal "notes" widget.
		$bobCopyPlacements = [
			$this->makePlacement(id: 201, dashboardId: 101, widgetId: 'links', templatePlacementId: 10),
			$this->makePlacement(id: 202, dashboardId: 101, widgetId: 'notes', templatePlacementId: null),
		];

		$this->placementMapper->method('findByDashboardId')
			->willReturnMap([
				[1, $templatePlacements],
				[100, $aliceCopyPlacements],
				[101, $bobCopyPlacements],
			]);

		$this->placementMapper->expects($this->never())->method('insert');
		$this->placementMapper->expects($this->never())->method('update');
		$this->placementMapper->expects($this->never())->method('delete');
		$this->activityPublisher->expects($this->never())->method('publish');
		$this->notificationManager->expects($this->never())->method('notify');

		$result = $this->service->resync(
			templateId: 1,
			strategy: TemplateResyncService::STRATEGY_MERGE,
			dryRun: true,
			actingAdminId: 'admin1'
		);

		$this->assertTrue($result['dryRun']);
		$this->assertFalse($result['async']);
		$this->assertSame(2, $result['totalCopies']);
		// Both copies are missing the new "announcements" placement.
		$this->assertSame(2, $result['affectedCount']);

		$aliceReport = $this->findCopyReport($result['copies'], 100);
		$this->assertSame(1, $aliceReport['toAdd']);
		$this->assertSame(0, $aliceReport['toUpdate']);
		$this->assertSame(0, $aliceReport['toRemove']);
		$this->assertSame(1, $aliceReport['toPreserve']);

		$bobReport = $this->findCopyReport($result['copies'], 101);
		$this->assertSame(1, $bobReport['toAdd']);
		$this->assertSame(0, $bobReport['toRemove']);
		// bob's personal "notes" widget is preserved under merge.
		$this->assertSame(2, $bobReport['toPreserve']);
	}

	public function testDryRunOnUpToDateTemplateReportsNoChanges(): void {
		$template = $this->makeTemplate();
		$this->dashboardMapper->method('find')->willReturn($template);

		$templatePlacement = $this->makePlacement(id: 10, dashboardId: 1, widgetId: 'links');
		$copy = $this->makeCopy(id: 100, userId: 'alice');
		$copyPlacement = $this->makePlacement(id: 200, dashboardId: 100, widgetId: 'links', templatePlacementId: 10);

		$this->dashboardMapper->method('findByBasedOnTemplate')->willReturn([$copy]);
		$this->placementMapper->method('findByDashboardId')
			->willReturnMap([
				[1, [$templatePlacement]],
				[100, [$copyPlacement]],
			]);

		$result = $this->service->resync(
			templateId: 1,
			strategy: TemplateResyncService::STRATEGY_OVERWRITE,
			dryRun: true,
			actingAdminId: 'admin1'
		);

		$this->assertSame(0, $result['affectedCount']);
		$report = $this->findCopyReport($result['copies'], 100);
		$this->assertSame(0, $report['toAdd']);
		$this->assertSame(0, $report['toUpdate']);
		$this->assertSame(0, $report['toRemove']);
		$this->assertSame(1, $report['toPreserve']);
	}

	// ---------------------------------------------------------------
	// Merge preserves user-added widgets (REQ-RESYNC-003)
	// ---------------------------------------------------------------

	public function testMergeKeepsUserAddedWidgetsWhileApplyingTemplateChanges(): void {
		$template = $this->makeTemplate();
		$this->dashboardMapper->method('find')->willReturn($template);

		// Template: "links" moved to gridX=4, new "announcements" added.
		$templatePlacements = [
			$this->makePlacement(id: 10, dashboardId: 1, widgetId: 'links', gridX: 4),
			$this->makePlacement(id: 11, dashboardId: 1, widgetId: 'announcements'),
		];

		$copy = $this->makeCopy(id: 100, userId: 'alice');
		$this->dashboardMapper->method('findByBasedOnTemplate')->willReturn([$copy]);

		$linksCopy = $this->makePlacement(id: 200, dashboardId: 100, widgetId: 'links', gridX: 0, templatePlacementId: 10);
		$notesCopy = $this->makePlacement(id: 201, dashboardId: 100, widgetId: 'notes', templatePlacementId: null);

		$this->placementMapper->method('findByDashboardId')
			->willReturnMap([
				[1, $templatePlacements],
				[100, [$linksCopy, $notesCopy]],
			]);

		$this->placementMapper->expects($this->once())
			->method('insert')
			->with($this->callback(function (WidgetPlacement $p) {
				return $p->getWidgetId() === 'announcements'
					&& $p->getDashboardId() === 100
					&& $p->getTemplatePlacementId() === 11;
			}));

		$this->placementMapper->expects($this->once())
			->method('update')
			->with($this->callback(function (WidgetPlacement $p) {
				return $p->getId() === 200 && $p->getGridX() === 4;
			}));

		// The user-added "notes" widget must never be deleted under merge.
		$this->placementMapper->expects($this->never())->method('delete');

		$result = $this->service->applyResync(
			templateId: 1,
			strategy: TemplateResyncService::STRATEGY_MERGE,
			actingAdminId: 'admin1'
		);

		$this->assertSame(1, $result['affectedCount']);
	}

	// ---------------------------------------------------------------
	// Overwrite replaces the layout (REQ-RESYNC-003)
	// ---------------------------------------------------------------

	public function testOverwriteReplacesTheLayout(): void {
		$template = $this->makeTemplate();
		$this->dashboardMapper->method('find')->willReturn($template);

		$templatePlacements = [
			$this->makePlacement(id: 10, dashboardId: 1, widgetId: 'links'),
		];

		$copy = $this->makeCopy(id: 101, userId: 'bob');
		$this->dashboardMapper->method('findByBasedOnTemplate')->willReturn([$copy]);

		$linksCopy = $this->makePlacement(id: 300, dashboardId: 101, widgetId: 'links', gridX: 6, templatePlacementId: 10);
		$userAdded = $this->makePlacement(id: 301, dashboardId: 101, widgetId: 'personal-widget', templatePlacementId: null);

		$this->placementMapper->method('findByDashboardId')
			->willReturnMap([
				[1, $templatePlacements],
				[101, [$linksCopy, $userAdded]],
			]);

		// "links" repositioned back to template's gridX=0.
		$this->placementMapper->expects($this->once())
			->method('update')
			->with($this->callback(fn (WidgetPlacement $p) => $p->getId() === 300 && $p->getGridX() === 0));

		// bob's personally-added widget MUST be removed under overwrite.
		$this->placementMapper->expects($this->once())
			->method('delete')
			->with($this->callback(fn (WidgetPlacement $p) => $p->getId() === 301));

		$result = $this->service->applyResync(
			templateId: 1,
			strategy: TemplateResyncService::STRATEGY_OVERWRITE,
			actingAdminId: 'admin1'
		);

		$this->assertSame(1, $result['affectedCount']);
	}

	// ---------------------------------------------------------------
	// Compulsory widgets reconciled under both strategies (REQ-RESYNC-004)
	// ---------------------------------------------------------------

	/**
	 * @dataProvider strategyProvider
	 */
	public function testCompulsoryWidgetRestoredUnderBothStrategies(string $strategy): void {
		$template = $this->makeTemplate();
		$this->dashboardMapper->method('find')->willReturn($template);

		$compulsoryTemplatePlacement = $this->makePlacement(
			id: 10,
			dashboardId: 1,
			widgetId: 'company-news',
			isCompulsory: 1
		);

		$copy = $this->makeCopy(id: 102, userId: 'carol');
		$this->dashboardMapper->method('findByBasedOnTemplate')->willReturn([$copy]);

		// Carol's copy is missing the compulsory widget entirely.
		$this->placementMapper->method('findByDashboardId')
			->willReturnMap([
				[1, [$compulsoryTemplatePlacement]],
				[102, []],
			]);

		$this->placementMapper->expects($this->once())
			->method('insert')
			->with($this->callback(function (WidgetPlacement $p) {
				return $p->getWidgetId() === 'company-news'
					&& $p->getIsCompulsory() === 1
					&& $p->getTemplatePlacementId() === 10;
			}));

		$result = $this->service->applyResync(
			templateId: 1,
			strategy: $strategy,
			actingAdminId: 'admin1'
		);

		$this->assertSame(1, $result['affectedCount']);
	}

	/**
	 * @dataProvider strategyProvider
	 */
	public function testCompulsoryWidgetPositionAlignedUnderBothStrategies(string $strategy): void {
		$template = $this->makeTemplate();
		$this->dashboardMapper->method('find')->willReturn($template);

		$compulsoryTemplatePlacement = $this->makePlacement(
			id: 10,
			dashboardId: 1,
			widgetId: 'company-news',
			gridX: 8,
			isCompulsory: 1
		);

		$copy = $this->makeCopy(id: 103, userId: 'dave');
		$this->dashboardMapper->method('findByBasedOnTemplate')->willReturn([$copy]);

		// Dave's copy has the compulsory widget at the old position.
		$staleCopyPlacement = $this->makePlacement(
			id: 400,
			dashboardId: 103,
			widgetId: 'company-news',
			gridX: 0,
			isCompulsory: 1,
			templatePlacementId: 10
		);

		$this->placementMapper->method('findByDashboardId')
			->willReturnMap([
				[1, [$compulsoryTemplatePlacement]],
				[103, [$staleCopyPlacement]],
			]);

		$this->placementMapper->expects($this->once())
			->method('update')
			->with($this->callback(fn (WidgetPlacement $p) => $p->getId() === 400 && $p->getGridX() === 8));

		$this->service->applyResync(
			templateId: 1,
			strategy: $strategy,
			actingAdminId: 'admin1'
		);
	}

	public static function strategyProvider(): array {
		return [
			'overwrite' => [TemplateResyncService::STRATEGY_OVERWRITE],
			'merge' => [TemplateResyncService::STRATEGY_MERGE],
		];
	}

	// ---------------------------------------------------------------
	// Idempotency, audit, notification (REQ-RESYNC-005)
	// ---------------------------------------------------------------

	public function testReapplyingAnUnchangedTemplateIsANoOp(): void {
		$template = $this->makeTemplate();
		$this->dashboardMapper->method('find')->willReturn($template);

		$templatePlacement = $this->makePlacement(id: 10, dashboardId: 1, widgetId: 'links');
		$copy = $this->makeCopy(id: 100, userId: 'alice');
		// Already reconciled: matches the template exactly.
		$copyPlacement = $this->makePlacement(id: 200, dashboardId: 100, widgetId: 'links', templatePlacementId: 10);

		$this->dashboardMapper->method('findByBasedOnTemplate')->willReturn([$copy]);
		$this->placementMapper->method('findByDashboardId')
			->willReturnMap([
				[1, [$templatePlacement]],
				[100, [$copyPlacement]],
			]);

		$this->placementMapper->expects($this->never())->method('insert');
		$this->placementMapper->expects($this->never())->method('update');
		$this->placementMapper->expects($this->never())->method('delete');
		$this->notificationManager->expects($this->never())->method('notify');

		$result = $this->service->applyResync(
			templateId: 1,
			strategy: TemplateResyncService::STRATEGY_OVERWRITE,
			actingAdminId: 'admin1'
		);

		$this->assertSame(0, $result['affectedCount']);

		// Running it again is still a no-op — no exception, same result.
		$result2 = $this->service->applyResync(
			templateId: 1,
			strategy: TemplateResyncService::STRATEGY_OVERWRITE,
			actingAdminId: 'admin1'
		);
		$this->assertSame(0, $result2['affectedCount']);
	}

	public function testAuditRecordIsWrittenOnARealRun(): void {
		$template = $this->makeTemplate();
		$this->dashboardMapper->method('find')->willReturn($template);

		$templatePlacement = $this->makePlacement(id: 10, dashboardId: 1, widgetId: 'links');
		$copies = [];
		$copyPlacementsById = [];
		for ($i = 1; $i <= 12; $i++) {
			$copy = $this->makeCopy(id: 100 + $i, userId: 'user' . $i);
			$copies[] = $copy;
			// Every copy is missing the "links" widget → all 12 affected.
			$copyPlacementsById[100 + $i] = [];
		}

		$this->dashboardMapper->method('findByBasedOnTemplate')->willReturn($copies);

		$returnMap = [[1, [$templatePlacement]]];
		foreach ($copyPlacementsById as $id => $placements) {
			$returnMap[] = [$id, $placements];
		}
		$this->placementMapper->method('findByDashboardId')->willReturnMap($returnMap);

		$this->activityPublisher->expects($this->once())
			->method('publish')
			->willReturnCallback(function (
				string $type,
				string $actorUserId,
				string $recipientUserId,
				string $dashboardUuid,
				string $dashboardName,
				string $dashboardLink,
				array $extraParams = [],
			) {
				$this->assertSame('admin1', $actorUserId);
				$this->assertSame(1, $extraParams['templateId']);
				$this->assertSame(TemplateResyncService::STRATEGY_MERGE, $extraParams['strategy']);
				$this->assertSame(12, $extraParams['affectedCount']);
				return true;
			});

		$result = $this->service->applyResync(
			templateId: 1,
			strategy: TemplateResyncService::STRATEGY_MERGE,
			actingAdminId: 'admin1'
		);

		$this->assertSame(12, $result['affectedCount']);
	}

	public function testAffectedUsersAreNotified(): void {
		$template = $this->makeTemplate();
		$this->dashboardMapper->method('find')->willReturn($template);

		$templatePlacement = $this->makePlacement(id: 10, dashboardId: 1, widgetId: 'links');
		$copy = $this->makeCopy(id: 100, userId: 'erin');
		$this->dashboardMapper->method('findByBasedOnTemplate')->willReturn([$copy]);
		$this->placementMapper->method('findByDashboardId')
			->willReturnMap([
				[1, [$templatePlacement]],
				[100, []],
			]);

		$notification = $this->createMock(INotification::class);
		$notification->method('setApp')->willReturnSelf();
		$notification->method('setUser')->with('erin')->willReturnSelf();
		$notification->method('setDateTime')->willReturnSelf();
		$notification->method('setObject')->willReturnSelf();
		$notification->method('setSubject')
			->with('dashboard_template_resynced', $this->anything())
			->willReturnSelf();

		$this->notificationManager->method('createNotification')->willReturn($notification);
		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$this->service->applyResync(
			templateId: 1,
			strategy: TemplateResyncService::STRATEGY_MERGE,
			actingAdminId: 'admin1'
		);
	}

	// ---------------------------------------------------------------
	// Async for large groups (REQ-RESYNC-005)
	// ---------------------------------------------------------------

	public function testLargeGroupsEnqueueTheAsyncJobInsteadOfApplyingInline(): void {
		$template = $this->makeTemplate();
		$this->dashboardMapper->method('find')->willReturn($template);

		$templatePlacement = $this->makePlacement(id: 10, dashboardId: 1, widgetId: 'links');

		// Above TemplateResyncService::ASYNC_THRESHOLD (50) — REQ-RESYNC-005
		// "Large groups apply asynchronously" (the spec scenario itself
		// uses 800; 60 already exercises the threshold branch without
		// slowing the suite down).
		$copyCount = TemplateResyncService::ASYNC_THRESHOLD + 10;
		$copies = [];
		$returnMap = [[1, [$templatePlacement]]];
		for ($i = 1; $i <= $copyCount; $i++) {
			$copy = $this->makeCopy(id: 1000 + $i, userId: 'user' . $i);
			$copies[] = $copy;
			$returnMap[] = [1000 + $i, [$this->makePlacement(id: 5000 + $i, dashboardId: 1000 + $i, widgetId: 'links', templatePlacementId: 10)]];
		}

		$this->dashboardMapper->method('findByBasedOnTemplate')->willReturn($copies);
		$this->placementMapper->method('findByDashboardId')->willReturnMap($returnMap);

		$this->jobList->expects($this->once())
			->method('add')
			->with(
				\OCA\LaunchPad\BackgroundJob\TemplateResyncJob::class,
				$this->callback(fn (array $arg) => $arg['templateId'] === 1
					&& $arg['strategy'] === TemplateResyncService::STRATEGY_OVERWRITE
					&& $arg['actingAdminId'] === 'admin1')
			);

		$this->placementMapper->expects($this->never())->method('insert');
		$this->placementMapper->expects($this->never())->method('update');
		$this->placementMapper->expects($this->never())->method('delete');

		$result = $this->service->resync(
			templateId: 1,
			strategy: TemplateResyncService::STRATEGY_OVERWRITE,
			dryRun: false,
			actingAdminId: 'admin1'
		);

		$this->assertTrue($result['async']);
		$this->assertTrue($result['accepted']);
		$this->assertSame($copyCount, $result['totalCopies']);
	}

	// ---------------------------------------------------------------
	// Small helpers
	// ---------------------------------------------------------------

	private function findCopyReport(array $copies, int $dashboardId): array {
		foreach ($copies as $copy) {
			if ($copy['dashboardId'] === $dashboardId) {
				return $copy;
			}
		}

		$this->fail('No report found for dashboard ' . $dashboardId);
	}
}
