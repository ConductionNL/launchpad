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

	/**
	 * The service under test.
	 *
	 * @var TemplateResyncService
	 */
	private TemplateResyncService $service;

	/**
	 * A template "Intranet medewerkers" with id 1 and the service over mocked stores.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->dashboardMapper = $this->createMock(originalClassName: DashboardMapper::class);
		$this->placementMapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);

		$l10n = $this->createMock(originalClassName: IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $params = []): string => vsprintf($text, $params));

		$notifications = $this->createMock(originalClassName: INotificationManager::class);
		$notification  = $this->createMock(originalClassName: INotification::class);
		foreach (['setApp', 'setUser', 'setDateTime', 'setObject', 'setSubject'] as $setter) {
			$notification->method($setter)->willReturnSelf();
		}

		$notifications->method('createNotification')->willReturn($notification);

		$this->service = new TemplateResyncService(
			stores: new ResyncStores(
				dashboardMapper: $this->dashboardMapper,
				placementMapper: $this->placementMapper,
				db: $this->createMock(originalClassName: IDBConnection::class),
				jobList: $this->createMock(originalClassName: IJobList::class)
			),
			activityPublisher: $this->createMock(originalClassName: ActivityPublisher::class),
			notificationManager: $notifications,
			logger: $this->createMock(originalClassName: LoggerInterface::class),
			followUps: new ResyncFollowUps(personalLayers: $this->createMock(originalClassName: PersonalLayerService::class), l10n: $l10n)
		);

		$template = new Dashboard();
		$template->setId(1);
		$template->setUuid('template-1');
		$template->setName('Intranet medewerkers');
		$template->setType(Dashboard::TYPE_ADMIN_TEMPLATE);
		$this->dashboardMapper->method('find')->willReturn($template);
	}

	/**
	 * A copy of template 1 on a 12-column grid.
	 *
	 * @param int    $id     The copy's id.
	 * @param string $userId Its owner.
	 *
	 * @return Dashboard The copy.
	 */
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
	 * A placement; `synced` sets the layout the template last gave it.
	 *
	 * @param int                  $id          The placement id.
	 * @param int                  $dashboardId Its dashboard.
	 * @param array<string, mixed> $fields      Overrides: widgetId, x, y, w, h, compulsory, title, content, origin, synced.
	 *
	 * @return WidgetPlacement The placement.
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
	 * Wire the mappers: placements per dashboard id and the template's copies.
	 *
	 * @param array<int, WidgetPlacement[]> $byDashboard The placements per dashboard id.
	 * @param Dashboard[]                   $copies      The template's copies.
	 *
	 * @return void
	 */
	private function given(array $byDashboard, array $copies): void {
		$this->dashboardMapper->method('findByBasedOnTemplate')->willReturn($copies);
		$map = [];
		foreach ($byDashboard as $dashboardId => $placements) {
			$map[] = [$dashboardId, $placements];
		}

		$this->placementMapper->method('findByDashboardId')->willReturnMap($map);
	}

	/**
	 * Add only is an accepted strategy.
	 *
	 * @return void
	 */
	public function testAddOnlyIsAnAcceptedStrategy(): void {
		$this->assertContains(needle: TemplateResyncService::STRATEGY_ADD_ONLY, haystack: TemplateResyncService::VALID_STRATEGIES);
		$this->given(byDashboard: [1 => [], 100 => []], copies: [$this->copy(id: 100, userId: 'alice')]);

		$plan = $this->service->resync(templateId: 1, strategy: 'add_only', dryRun: true, actingAdminId: 'admin');

		$this->assertSame(expected: 'add_only', actual: $plan['strategy']);
		$this->assertTrue(condition: $plan['dryRun']);
	}

	/**
	 * An unknown strategy is still rejected.
	 *
	 * @return void
	 */
	public function testAnUnknownStrategyIsStillRejected(): void {
		$this->expectException(exception: InvalidArgumentException::class);
		$this->expectExceptionMessage(message: '"add_only"');

		$this->service->resync(templateId: 1, strategy: 'add-only', dryRun: true, actingAdminId: 'admin');
	}

	/**
	 * Add only adds new widgets and never removes or moves.
	 *
	 * @return void
	 */
	public function testAddOnlyAddsNewWidgetsAndNeverRemovesOrMoves(): void {
		// Template: news moved to x=8 with a new feed, birthdays new at the spot alice's own notes hold.
		$template = [
			$this->placement(id: 10, dashboardId: 1, fields: ['widgetId' => 'news', 'x' => 8, 'content' => 'new-feed']),
			$this->placement(id: 12, dashboardId: 1, fields: ['widgetId' => 'birthdays', 'x' => 4, 'title' => 'Verjaardagen']),
		];
		// Alice: news where it was, old-news whose template placement is gone, her own notes at x=4.
		$copy = [
			$this->placement(id: 200, dashboardId: 100, fields: ['widgetId' => 'news', 'x' => 0, 'origin' => 10, 'content' => 'old-feed']),
			$this->placement(id: 201, dashboardId: 100, fields: ['widgetId' => 'old-news', 'x' => 0, 'y' => 4, 'origin' => 11]),
			$this->placement(id: 202, dashboardId: 100, fields: ['widgetId' => 'notes', 'x' => 4]),
		];
		$this->given(byDashboard: [1 => $template, 100 => $copy], copies: [$this->copy(id: 100, userId: 'alice')]);

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

		$this->assertSame(expected: 1, actual: $result['affectedCount']);
		$this->assertCount(expectedCount: 1, haystack: $inserted);
		$this->assertSame(expected: 'birthdays', actual: $inserted[0]->getWidgetId());
		$this->assertSame(expected: 12, actual: $inserted[0]->getTemplatePlacementId());
		// Column 4 holds alice's notes, so the first free spot of the top row is column 8.
		$this->assertSame(expected: [8, 0], actual: [$inserted[0]->getGridX(), $inserted[0]->getGridY()]);
		$this->assertSame(expected: '8,0,4,4', actual: $inserted[0]->getSyncedLayout());
		$this->assertSame(expected: 0, actual: $copy[0]->getGridX());
		$this->assertSame(expected: 'old-feed', actual: $copy[0]->getContent());
	}

	/**
	 * Add only keeps the template spot when it is free.
	 *
	 * @return void
	 */
	public function testAddOnlyKeepsTheTemplateSpotWhenItIsFree(): void {
		$this->given(
			byDashboard: [1 => [$this->placement(id: 12, dashboardId: 1, fields: ['widgetId' => 'birthdays', 'x' => 4, 'y' => 8])], 100 => []],
			copies: [$this->copy(id: 100, userId: 'alice')]
		);
		$inserted = null;
		$this->placementMapper->method('insert')->willReturnCallback(
			static function (WidgetPlacement $placement) use (&$inserted): WidgetPlacement {
				$inserted = $placement;
				return $placement;
			}
		);

		$this->service->applyResync(templateId: 1, strategy: 'add_only', actingAdminId: 'admin');

		$this->assertSame(expected: [4, 8], actual: [$inserted->getGridX(), $inserted->getGridY()]);
	}

	/**
	 * Add only still reconciles a compulsory widget.
	 *
	 * @return void
	 */
	public function testAddOnlyStillReconcilesACompulsoryWidget(): void {
		$template = [
			$this->placement(id: 10, dashboardId: 1, fields: ['widgetId' => 'policy', 'x' => 8, 'compulsory' => 1]),
			$this->placement(id: 11, dashboardId: 1, fields: ['widgetId' => 'rules', 'compulsory' => 1, 'y' => 4]),
		];
		$copy = [$this->placement(id: 200, dashboardId: 100, fields: ['widgetId' => 'policy', 'x' => 0, 'compulsory' => 1, 'origin' => 10])];
		$this->given(byDashboard: [1 => $template, 100 => $copy], copies: [$this->copy(id: 100, userId: 'alice')]);

		$this->placementMapper->expects($this->once())->method('update')->with(
			$this->callback(callback: static fn (WidgetPlacement $placement): bool => $placement->getId() === 200 && $placement->getGridX() === 8)
		);
		$this->placementMapper->expects($this->once())->method('insert')->with(
			$this->callback(callback: static fn (WidgetPlacement $placement): bool => $placement->getWidgetId() === 'rules')
		);

		$this->service->applyResync(templateId: 1, strategy: 'add_only', actingAdminId: 'admin');
	}

	/**
	 * Merge keeps a widget the owner moved and applies the new feed.
	 *
	 * @return void
	 */
	public function testMergeKeepsAWidgetTheOwnerMovedAndAppliesTheNewFeed(): void {
		// Provisioned at y=4; alice moved it to the top; the admin moved it down to y=8 and changed the feed.
		$template = [$this->placement(id: 10, dashboardId: 1, fields: ['widgetId' => 'news', 'y' => 8, 'content' => 'new-feed'])];
		$copy     = [
			$this->placement(
				id: 200,
				dashboardId: 100,
				fields: ['widgetId' => 'news', 'y' => 0, 'origin' => 10, 'content' => 'old-feed', 'synced' => '0,4,4,4']
			),
		];
		$this->given(byDashboard: [1 => $template, 100 => $copy], copies: [$this->copy(id: 100, userId: 'alice')]);

		$this->placementMapper->expects($this->once())->method('update')->with(
			$this->callback(
				callback: static fn (WidgetPlacement $placement): bool => $placement->getId() === 200
					&& $placement->getGridY() === 0
					&& $placement->getContent() === 'new-feed'
			)
		);

		$result = $this->service->applyResync(templateId: 1, strategy: 'merge', actingAdminId: 'admin');

		$this->assertSame(expected: 1, actual: $result['affectedCount']);
	}

	/**
	 * Merge leaves a moved widget alone when only the template layout changed.
	 *
	 * @return void
	 */
	public function testMergeLeavesAMovedWidgetAloneWhenOnlyTheTemplateLayoutChanged(): void {
		$template = [$this->placement(id: 10, dashboardId: 1, fields: ['widgetId' => 'news', 'y' => 8])];
		$copy     = [$this->placement(id: 200, dashboardId: 100, fields: ['widgetId' => 'news', 'y' => 0, 'origin' => 10, 'synced' => '0,4,4,4'])];
		$this->given(byDashboard: [1 => $template, 100 => $copy], copies: [$this->copy(id: 100, userId: 'alice')]);

		$this->placementMapper->expects($this->never())->method('update');

		$result = $this->service->applyResync(templateId: 1, strategy: 'merge', actingAdminId: 'admin');

		$this->assertSame(expected: 0, actual: $result['affectedCount']);
	}

	/**
	 * Merge moves a widget the owner did not touch.
	 *
	 * @return void
	 */
	public function testMergeMovesAWidgetTheOwnerDidNotTouch(): void {
		$template = [$this->placement(id: 10, dashboardId: 1, fields: ['widgetId' => 'news', 'y' => 8])];
		$copy     = [$this->placement(id: 200, dashboardId: 100, fields: ['widgetId' => 'news', 'y' => 4, 'origin' => 10, 'synced' => '0,4,4,4'])];
		$this->given(byDashboard: [1 => $template, 100 => $copy], copies: [$this->copy(id: 100, userId: 'alice')]);

		$this->placementMapper->expects($this->once())->method('update')->with(
			$this->callback(
				callback: static fn (WidgetPlacement $placement): bool => $placement->getGridY() === 8
					&& $placement->getSyncedLayout() === '0,8,4,4'
			)
		);

		$this->service->applyResync(templateId: 1, strategy: 'merge', actingAdminId: 'admin');
	}

	/**
	 * Overwrite moves a widget the owner moved.
	 *
	 * @return void
	 */
	public function testOverwriteMovesAWidgetTheOwnerMoved(): void {
		$template = [$this->placement(id: 10, dashboardId: 1, fields: ['widgetId' => 'news', 'y' => 8])];
		$copy     = [$this->placement(id: 200, dashboardId: 100, fields: ['widgetId' => 'news', 'y' => 0, 'origin' => 10, 'synced' => '0,4,4,4'])];
		$this->given(byDashboard: [1 => $template, 100 => $copy], copies: [$this->copy(id: 100, userId: 'alice')]);

		$this->placementMapper->expects($this->once())->method('update')->with(
			$this->callback(callback: static fn (WidgetPlacement $placement): bool => $placement->getGridY() === 8)
		);

		$this->service->applyResync(templateId: 1, strategy: 'overwrite', actingAdminId: 'admin');
	}

	/**
	 * The dry run names what was gained and lost and counts changed copies.
	 *
	 * @return void
	 */
	public function testTheDryRunNamesWhatWasGainedAndLostAndCountsChangedCopies(): void {
		$template = [
			$this->placement(id: 10, dashboardId: 1, fields: ['widgetId' => 'news', 'title' => 'Nieuws']),
			$this->placement(id: 12, dashboardId: 1, fields: ['widgetId' => 'ack', 'title' => 'Leesbevestiging', 'y' => 4]),
			$this->placement(id: 13, dashboardId: 1, fields: ['widgetId' => 'birthdays', 'title' => 'Verjaardagen', 'y' => 8]),
		];
		$copies = [$this->copy(id: 100, userId: 'alice'), $this->copy(id: 101, userId: 'bob'), $this->copy(id: 102, userId: 'carol')];
		$this->given(
			byDashboard: [
				1 => $template,
				// Alice moved the news widget.
				100 => [
					$this->placement(id: 200, dashboardId: 100, fields: ['widgetId' => 'news', 'y' => 12, 'origin' => 10, 'synced' => '0,0,4,4']),
					$this->placement(id: 201, dashboardId: 100, fields: ['widgetId' => 'old', 'title' => 'Oud nieuws', 'origin' => 11]),
				],
				// Bob left everything as provisioned.
				101 => [
					$this->placement(id: 300, dashboardId: 101, fields: ['widgetId' => 'news', 'origin' => 10, 'synced' => '0,0,4,4']),
					$this->placement(id: 301, dashboardId: 101, fields: ['widgetId' => 'old', 'title' => 'Oud nieuws', 'origin' => 11]),
				],
				// Carol removed the news widget and added her own.
				102 => [
					$this->placement(id: 400, dashboardId: 102, fields: ['widgetId' => 'old', 'title' => 'Oud nieuws', 'origin' => 11]),
					$this->placement(id: 401, dashboardId: 102, fields: ['widgetId' => 'notes']),
				],
			],
			copies: $copies
		);
		$this->placementMapper->expects($this->never())->method('insert');
		$this->placementMapper->expects($this->never())->method('update');

		$plan = $this->service->resync(templateId: 1, strategy: 'merge', dryRun: true, actingAdminId: 'admin');

		$this->assertSame(expected: ['Leesbevestiging', 'Verjaardagen'], actual: $plan['gained']);
		$this->assertSame(expected: ['Oud nieuws'], actual: $plan['lost']);
		$this->assertSame(expected: 2, actual: $plan['changedByOwnerCount']);
		$this->assertSame(expected: 3, actual: $plan['totalCopies']);
	}

	/**
	 * The dry run of a template without copies gains nothing.
	 *
	 * @return void
	 */
	public function testTheDryRunOfATemplateWithoutCopiesGainsNothing(): void {
		$this->given(byDashboard: [1 => [$this->placement(id: 10, dashboardId: 1)]], copies: []);

		$plan = $this->service->resync(templateId: 1, strategy: 'add_only', dryRun: true, actingAdminId: 'admin');

		$this->assertSame(expected: [], actual: $plan['gained']);
		$this->assertSame(expected: [], actual: $plan['lost']);
		$this->assertSame(expected: 0, actual: $plan['changedByOwnerCount']);
	}

	/**
	 * A layout is read and a move is only seen against a baseline.
	 *
	 * @return void
	 */
	public function testALayoutIsReadAndAMoveIsOnlySeenAgainstABaseline(): void {
		$layout    = new PlacementLayout();
		$placement = $this->placement(id: 1, dashboardId: 100, fields: ['x' => 2, 'y' => 3, 'w' => 5, 'h' => 6]);

		$this->assertSame(expected: '2,3,5,6', actual: $layout->layoutOf(placement: $placement));
		$this->assertFalse(condition: $layout->ownerMoved(placement: $placement));

		$layout->stamp(placement: $placement);
		$this->assertFalse(condition: $layout->ownerMoved(placement: $placement));

		$layout->apply(placement: $placement, rect: ['gridX' => 0, 'gridY' => 0, 'gridWidth' => 5, 'gridHeight' => 6]);
		$this->assertTrue(condition: $layout->ownerMoved(placement: $placement));
		$this->assertSame(expected: ['gridX' => 0, 'gridY' => 0, 'gridWidth' => 5, 'gridHeight' => 6], actual: $layout->rectOf(placement: $placement));
	}
}
