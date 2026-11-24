<?php

/**
 * TemplateRolloutChoicesTest
 *
 * The three rollout choices of a template re-sync (template-rollout-choices):
 * `add_only` adds and never removes or moves (REQ-RESYNC-006), `merge` keeps a
 * widget where its owner put it (REQ-RESYNC-007), and the dry run names what
 * the template gained and lost and how many owners changed their copy
 * (REQ-RESYNC-008).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/template-rollout-choices/specs/admin-templates/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Activity\ActivityPublisher;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\PersonalLayerService;
use OCA\LaunchPad\Service\PlacementLayout;
use OCA\LaunchPad\Service\ResyncFollowUps;
use OCA\LaunchPad\Service\ResyncStores;
use OCA\LaunchPad\Service\TemplateResyncService;
use OCP\BackgroundJob\IJobList;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for the rollout choices of {@see TemplateResyncService}.
 */
class TemplateRolloutChoicesTest extends TestCase {

	/** @var DashboardMapper&MockObject */
	private $dashboardMapper;

	/** @var WidgetPlacementMapper&MockObject */
	private $placementMapper;

	private TemplateResyncService $service;

	protected function setUp(): void {
		parent::setUp();

		$this->dashboardMapper = $this->createMock(DashboardMapper::class);
		$this->placementMapper = $this->createMock(WidgetPlacementMapper::class);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $params = []): string => vsprintf($text, $params));

		$notifications = $this->createMock(INotificationManager::class);
		$notification  = $this->createMock(INotification::class);
		foreach (['setApp', 'setUser', 'setDateTime', 'setObject', 'setSubject'] as $setter) {
			$notification->method($setter)->willReturnSelf();
		}

		$notifications->method('createNotification')->willReturn($notification);

		$this->service = new TemplateResyncService(
			stores: new ResyncStores(
				dashboardMapper: $this->dashboardMapper,
				placementMapper: $this->placementMapper,
				db: $this->createMock(IDBConnection::class),
				jobList: $this->createMock(IJobList::class)
			),
			activityPublisher: $this->createMock(ActivityPublisher::class),
			notificationManager: $notifications,
			logger: $this->createMock(LoggerInterface::class),
			followUps: new ResyncFollowUps(personalLayers: $this->createMock(PersonalLayerService::class), l10n: $l10n)
		);

		$template = new Dashboard();
		$template->setId(1);
		$template->setUuid('template-1');
		$template->setName('Intranet medewerkers');
		$template->setType(Dashboard::TYPE_ADMIN_TEMPLATE);
		$this->dashboardMapper->method('find')->willReturn($template);
	}

	private function copy(int $id, string $userId): Dashboard {
		$copy = new Dashboard();
		$copy->setId($id);
		$copy->setUuid('copy-' . $id);
		$copy->setName('Intranet medewerkers');
		$copy->setType(Dashboard::TYPE_USER);
		$copy->setUserId($userId);
		$copy->setBasedOnTemplate(1);
		$copy->setGridColumns(12);

		return $copy;
	}

	/**
	 * A placement; `synced` stamps its current layout as the template's.
	 *
	 * @param array<string, mixed> $fields
	 */
	private function placement(int $id, int $dashboardId, array $fields = []): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setId($id);
		$placement->setDashboardId($dashboardId);
		$placement->setWidgetId($fields['widgetId'] ?? 'w' . $id);
		$placement->setGridX($fields['x'] ?? 0);
		$placement->setGridY($fields['y'] ?? 0);
		$placement->setGridWidth($fields['w'] ?? 4);
		$placement->setGridHeight($fields['h'] ?? 4);
		$placement->setIsCompulsory($fields['compulsory'] ?? 0);
		$placement->setIsVisible(1);
		$placement->setShowTitle(1);
		$placement->setSortOrder(0);
		$placement->setRequiresAcknowledgement(0);
		$placement->setReacknowledgeOnChange(0);
		$placement->setAcknowledgementContentVersion(1);
		$placement->setCustomTitle($fields['title'] ?? null);
		$placement->setContent($fields['content'] ?? null);
		$placement->setTemplatePlacementId($fields['origin'] ?? null);
		if (($fields['synced'] ?? null) !== null) {
			$placement->setSyncedLayout($fields['synced']);
		}

		return $placement;
	}

	/**
	 * @param array<int, WidgetPlacement[]> $byDashboard
	 * @param Dashboard[]                   $copies
	 */
	private function given(array $byDashboard, array $copies): void {
		$this->dashboardMapper->method('findByBasedOnTemplate')->willReturn($copies);
		$map = [];
		foreach ($byDashboard as $dashboardId => $placements) {
			$map[] = [$dashboardId, $placements];
		}

		$this->placementMapper->method('findByDashboardId')->willReturnMap($map);
	}

	public function testAddOnlyIsAnAcceptedStrategy(): void {
		$this->assertContains(TemplateResyncService::STRATEGY_ADD_ONLY, TemplateResyncService::VALID_STRATEGIES);
		$this->given([1 => [], 100 => []], [$this->copy(100, 'alice')]);

		$plan = $this->service->resync(templateId: 1, strategy: 'add_only', dryRun: true, actingAdminId: 'admin');

		$this->assertSame('add_only', $plan['strategy']);
		$this->assertTrue($plan['dryRun']);
	}

	public function testAnUnknownStrategyIsStillRejected(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('"add_only"');

		$this->service->resync(templateId: 1, strategy: 'add-only', dryRun: true, actingAdminId: 'admin');
	}

	public function testAddOnlyAddsNewWidgetsAndNeverRemovesOrMoves(): void {
		// Template: news moved to x=8 with a new feed, birthdays new at the spot alice's own notes hold.
		$template = [
			$this->placement(10, 1, ['widgetId' => 'news', 'x' => 8, 'content' => 'new-feed']),
			$this->placement(12, 1, ['widgetId' => 'birthdays', 'x' => 4, 'title' => 'Verjaardagen']),
		];
		// Alice: news where it was, old-news whose template placement is gone, her own notes at x=4.
		$copy = [
			$this->placement(200, 100, ['widgetId' => 'news', 'x' => 0, 'origin' => 10, 'content' => 'old-feed']),
			$this->placement(201, 100, ['widgetId' => 'old-news', 'x' => 0, 'y' => 4, 'origin' => 11]),
			$this->placement(202, 100, ['widgetId' => 'notes', 'x' => 4]),
		];
		$this->given([1 => $template, 100 => $copy], [$this->copy(100, 'alice')]);

		$inserted = [];
		$this->placementMapper->method('insert')->willReturnCallback(
			static function (WidgetPlacement $placement) use (&$inserted): WidgetPlacement {
				$inserted[] = $placement;
				return $placement;
			}
		);
		$this->placementMapper->expects($this->never())->method('update');
		$this->placementMapper->expects($this->never())->method('delete');

		$result = $this->service->applyResync(templateId: 1, strategy: 'add_only', actingAdminId: 'admin');

		$this->assertSame(1, $result['affectedCount']);
		$this->assertCount(1, $inserted);
		$this->assertSame('birthdays', $inserted[0]->getWidgetId());
		$this->assertSame(12, $inserted[0]->getTemplatePlacementId());
		// x=4 is alice's notes, so the first free spot of the top row is x=8.
		$this->assertSame([8, 0], [$inserted[0]->getGridX(), $inserted[0]->getGridY()]);
		$this->assertSame('8,0,4,4', $inserted[0]->getSyncedLayout());
		$this->assertSame(0, $copy[0]->getGridX());
		$this->assertSame('old-feed', $copy[0]->getContent());
	}

	public function testAddOnlyKeepsTheTemplateSpotWhenItIsFree(): void {
		$this->given(
			[1 => [$this->placement(12, 1, ['widgetId' => 'birthdays', 'x' => 4, 'y' => 8])], 100 => []],
			[$this->copy(100, 'alice')]
		);
		$inserted = null;
		$this->placementMapper->method('insert')->willReturnCallback(
			static function (WidgetPlacement $placement) use (&$inserted): WidgetPlacement {
				$inserted = $placement;
				return $placement;
			}
		);

		$this->service->applyResync(templateId: 1, strategy: 'add_only', actingAdminId: 'admin');

		$this->assertSame([4, 8], [$inserted->getGridX(), $inserted->getGridY()]);
	}

	public function testAddOnlyStillReconcilesACompulsoryWidget(): void {
		$template = [
			$this->placement(10, 1, ['widgetId' => 'policy', 'x' => 8, 'compulsory' => 1]),
			$this->placement(11, 1, ['widgetId' => 'rules', 'compulsory' => 1, 'y' => 4]),
		];
		$copy = [$this->placement(200, 100, ['widgetId' => 'policy', 'x' => 0, 'compulsory' => 1, 'origin' => 10])];
		$this->given([1 => $template, 100 => $copy], [$this->copy(100, 'alice')]);

		$this->placementMapper->expects($this->once())->method('update')->with(
			$this->callback(static fn (WidgetPlacement $placement): bool => $placement->getId() === 200 && $placement->getGridX() === 8)
		);
		$this->placementMapper->expects($this->once())->method('insert')->with(
			$this->callback(static fn (WidgetPlacement $placement): bool => $placement->getWidgetId() === 'rules')
		);

		$this->service->applyResync(templateId: 1, strategy: 'add_only', actingAdminId: 'admin');
	}

	public function testMergeKeepsAWidgetTheOwnerMovedAndAppliesTheNewFeed(): void {
		// Provisioned at y=4; alice moved it to the top; the admin moved it down to y=8 and changed the feed.
		$template = [$this->placement(10, 1, ['widgetId' => 'news', 'y' => 8, 'content' => 'new-feed'])];
		$copy     = [$this->placement(200, 100, ['widgetId' => 'news', 'y' => 0, 'origin' => 10, 'content' => 'old-feed', 'synced' => '0,4,4,4'])];
		$this->given([1 => $template, 100 => $copy], [$this->copy(100, 'alice')]);

		$this->placementMapper->expects($this->once())->method('update')->with(
			$this->callback(
				static fn (WidgetPlacement $placement): bool => $placement->getId() === 200
					&& $placement->getGridY() === 0
					&& $placement->getContent() === 'new-feed'
			)
		);

		$result = $this->service->applyResync(templateId: 1, strategy: 'merge', actingAdminId: 'admin');

		$this->assertSame(1, $result['affectedCount']);
	}

	public function testMergeLeavesAMovedWidgetAloneWhenOnlyTheTemplateLayoutChanged(): void {
		$template = [$this->placement(10, 1, ['widgetId' => 'news', 'y' => 8])];
		$copy     = [$this->placement(200, 100, ['widgetId' => 'news', 'y' => 0, 'origin' => 10, 'synced' => '0,4,4,4'])];
		$this->given([1 => $template, 100 => $copy], [$this->copy(100, 'alice')]);

		$this->placementMapper->expects($this->never())->method('update');

		$result = $this->service->applyResync(templateId: 1, strategy: 'merge', actingAdminId: 'admin');

		$this->assertSame(0, $result['affectedCount']);
	}

	public function testMergeMovesAWidgetTheOwnerDidNotTouch(): void {
		$template = [$this->placement(10, 1, ['widgetId' => 'news', 'y' => 8])];
		$copy     = [$this->placement(200, 100, ['widgetId' => 'news', 'y' => 4, 'origin' => 10, 'synced' => '0,4,4,4'])];
		$this->given([1 => $template, 100 => $copy], [$this->copy(100, 'alice')]);

		$this->placementMapper->expects($this->once())->method('update')->with(
			$this->callback(
				static fn (WidgetPlacement $placement): bool => $placement->getGridY() === 8
					&& $placement->getSyncedLayout() === '0,8,4,4'
			)
		);

		$this->service->applyResync(templateId: 1, strategy: 'merge', actingAdminId: 'admin');
	}

	public function testOverwriteMovesAWidgetTheOwnerMoved(): void {
		$template = [$this->placement(10, 1, ['widgetId' => 'news', 'y' => 8])];
		$copy     = [$this->placement(200, 100, ['widgetId' => 'news', 'y' => 0, 'origin' => 10, 'synced' => '0,4,4,4'])];
		$this->given([1 => $template, 100 => $copy], [$this->copy(100, 'alice')]);

		$this->placementMapper->expects($this->once())->method('update')->with(
			$this->callback(static fn (WidgetPlacement $placement): bool => $placement->getGridY() === 8)
		);

		$this->service->applyResync(templateId: 1, strategy: 'overwrite', actingAdminId: 'admin');
	}

	public function testTheDryRunNamesWhatWasGainedAndLostAndCountsChangedCopies(): void {
		$template = [
			$this->placement(10, 1, ['widgetId' => 'news', 'title' => 'Nieuws']),
			$this->placement(12, 1, ['widgetId' => 'ack', 'title' => 'Leesbevestiging', 'y' => 4]),
			$this->placement(13, 1, ['widgetId' => 'birthdays', 'title' => 'Verjaardagen', 'y' => 8]),
		];
		$copies = [$this->copy(100, 'alice'), $this->copy(101, 'bob'), $this->copy(102, 'carol')];
		$this->given(
			[
				1 => $template,
				// Alice moved the news widget.
				100 => [
					$this->placement(200, 100, ['widgetId' => 'news', 'y' => 12, 'origin' => 10, 'synced' => '0,0,4,4']),
					$this->placement(201, 100, ['widgetId' => 'old', 'title' => 'Oud nieuws', 'origin' => 11]),
				],
				// Bob left everything as provisioned.
				101 => [
					$this->placement(300, 101, ['widgetId' => 'news', 'origin' => 10, 'synced' => '0,0,4,4']),
					$this->placement(301, 101, ['widgetId' => 'old', 'title' => 'Oud nieuws', 'origin' => 11]),
				],
				// Carol removed the news widget and added her own.
				102 => [
					$this->placement(400, 102, ['widgetId' => 'old', 'title' => 'Oud nieuws', 'origin' => 11]),
					$this->placement(401, 102, ['widgetId' => 'notes']),
				],
			],
			$copies
		);
		$this->placementMapper->expects($this->never())->method('insert');
		$this->placementMapper->expects($this->never())->method('update');

		$plan = $this->service->resync(templateId: 1, strategy: 'merge', dryRun: true, actingAdminId: 'admin');

		$this->assertSame(['Leesbevestiging', 'Verjaardagen'], $plan['gained']);
		$this->assertSame(['Oud nieuws'], $plan['lost']);
		$this->assertSame(2, $plan['changedByOwnerCount']);
		$this->assertSame(3, $plan['totalCopies']);
	}

	public function testTheDryRunOfATemplateWithoutCopiesGainsNothing(): void {
		$this->given([1 => [$this->placement(10, 1)]], []);

		$plan = $this->service->resync(templateId: 1, strategy: 'add_only', dryRun: true, actingAdminId: 'admin');

		$this->assertSame([], $plan['gained']);
		$this->assertSame([], $plan['lost']);
		$this->assertSame(0, $plan['changedByOwnerCount']);
	}

	public function testALayoutIsReadAndAMoveIsOnlySeenAgainstABaseline(): void {
		$layout    = new PlacementLayout();
		$placement = $this->placement(1, 100, ['x' => 2, 'y' => 3, 'w' => 5, 'h' => 6]);

		$this->assertSame('2,3,5,6', $layout->layoutOf($placement));
		$this->assertFalse($layout->ownerMoved($placement));

		$layout->stamp($placement);
		$this->assertFalse($layout->ownerMoved($placement));

		$layout->apply($placement, ['gridX' => 0, 'gridY' => 0, 'gridWidth' => 5, 'gridHeight' => 6]);
		$this->assertTrue($layout->ownerMoved($placement));
		$this->assertSame(['gridX' => 0, 'gridY' => 0, 'gridWidth' => 5, 'gridHeight' => 6], $layout->rectOf($placement));
	}
}
