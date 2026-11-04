<?php

/**
 * AcknowledgementServiceTest
 *
 * Unit tests for AcknowledgementService covering the dashboard-acknowledgements
 * capability — idempotent receipt writes (REQ-ACK-003), outstanding /
 * re-acknowledge-on-change resolution (REQ-ACK-002 / REQ-ACK-005), the
 * audience-scoped read-receipt report resolved live via IGroupManager
 * (REQ-ACK-004), and single-shot activity emission (REQ-ACK-006).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Activity\ActivityPublisher;
use OCA\LaunchPad\Activity\ActivityRow;
use OCA\LaunchPad\Activity\Extension;
use OCA\LaunchPad\Db\Acknowledgement;
use OCA\LaunchPad\Db\AcknowledgementMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AcknowledgementService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception as DbException;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AcknowledgementService.
 */
class AcknowledgementServiceTest extends TestCase {
	/**
	 * Receipt mapper double.
	 *
	 * @var AcknowledgementMapper&MockObject
	 */
	private AcknowledgementMapper&MockObject $ackMapper;

	/**
	 * Placement mapper double.
	 *
	 * @var WidgetPlacementMapper&MockObject
	 */
	private WidgetPlacementMapper&MockObject $placementMapper;

	/**
	 * Dashboard mapper double.
	 *
	 * @var DashboardMapper&MockObject
	 */
	private DashboardMapper&MockObject $dashboardMapper;

	/**
	 * Group manager double.
	 *
	 * @var IGroupManager&MockObject
	 */
	private IGroupManager&MockObject $groupManager;

	/**
	 * Activity publisher double.
	 *
	 * @var ActivityPublisher&MockObject
	 */
	private ActivityPublisher&MockObject $activityPublisher;

	/**
	 * The service under test.
	 *
	 * @var AcknowledgementService
	 */
	private AcknowledgementService $service;

	/**
	 * Build the service over fresh doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->ackMapper = $this->createMock(originalClassName: AcknowledgementMapper::class);
		$this->placementMapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$this->dashboardMapper = $this->createMock(originalClassName: DashboardMapper::class);
		$this->groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$this->activityPublisher = $this->createMock(originalClassName: ActivityPublisher::class);

		$this->service = new AcknowledgementService(
			acknowledgementMapper: $this->ackMapper,
			placementMapper: $this->placementMapper,
			dashboardMapper: $this->dashboardMapper,
			groupManager: $this->groupManager,
			activityPublisher: $this->activityPublisher,
		);
	}

	/**
	 * Build a receipt.
	 *
	 * @param string $key     The announcement key.
	 * @param string $userId  The acknowledging user.
	 * @param int    $version The content version.
	 * @param string $at      The acknowledgement time.
	 *
	 * @return Acknowledgement The receipt.
	 */
	private function makeReceipt(string $key, string $userId, int $version, string $at = '2026-07-07 09:00:00'): Acknowledgement {
		$receipt = new Acknowledgement();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$receipt->setAnnouncementKey($key);
		$receipt->setUserId($userId);
		$receipt->setContentVersion($version);
		$receipt->setAcknowledgedAt(new \DateTime($at));
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		return $receipt;
	}

	/**
	 * Build a mandatory-read placement.
	 *
	 * @param string $key         The announcement key.
	 * @param int    $dashboardId The dashboard holding it.
	 * @param int    $version     The content version.
	 * @param int    $reack       The re-acknowledge-on-change flag.
	 *
	 * @return WidgetPlacement The placement.
	 */
	private function makePlacement(string $key, int $dashboardId, int $version = 1, int $reack = 0): WidgetPlacement {
		$placement = new WidgetPlacement();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$placement->setId($dashboardId * 10);
		$placement->setDashboardId($dashboardId);
		$placement->setWidgetId('launchpad_header');
		$placement->setRequiresAcknowledgement(1);
		$placement->setAnnouncementKey($key);
		$placement->setAcknowledgementContentVersion($version);
		$placement->setReacknowledgeOnChange($reack);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		return $placement;
	}

	/**
	 * Build a user double.
	 *
	 * @param string $uid The user id.
	 *
	 * @return IUser&MockObject The user.
	 */
	private function makeUser(string $uid): IUser&MockObject {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn($uid);
		return $user;
	}

	/**
	 * REQ-ACK-003: the first acknowledgement writes exactly one receipt and
	 * emits exactly one activity event.
	 *
	 * @return void
	 */
	public function testFirstAcknowledgeRecordsAndEmitsActivity(): void {
		$this->ackMapper->method('findOneFor')->willReturn(null);
		$this->ackMapper->expects($this->once())
			->method('record')
			->with('ak-1', 'alice', 1)
			->willReturn($this->makeReceipt(key: 'ak-1', userId: 'alice', version: 1));

		// Activity emission resolves the announcement's dashboard.
		$this->placementMapper->method('findByAnnouncementKey')->willReturn([]);
		$this->activityPublisher->expects($this->once())
			->method('publish')
			->with(
				$this->callback(callback: static fn (ActivityRow $row): bool => $row->type === Extension::EVENT_ACKNOWLEDGED && $row->actorUserId === 'alice'),
				'alice'
			);

		$receipt = $this->service->acknowledge(
			announcementKey: 'ak-1',
			userId: 'alice',
			contentVersion: 1
		);

		$this->assertSame(expected: 'alice', actual: $receipt->getUserId());
	}

	/**
	 * REQ-ACK-003: a repeated acknowledgement is idempotent — no second
	 * insert, no second activity event, original timestamp preserved.
	 *
	 * @return void
	 */
	public function testRepeatedAcknowledgeIsIdempotent(): void {
		$existing = $this->makeReceipt(key: 'ak-1', userId: 'alice', version: 1, at: '2026-07-01 08:00:00');
		$this->ackMapper->method('findOneFor')->willReturn($existing);
		$this->ackMapper->expects($this->never())->method('record');
		$this->activityPublisher->expects($this->never())->method('publish');

		$receipt = $this->service->acknowledge(
			announcementKey: 'ak-1',
			userId: 'alice',
			contentVersion: 1
		);

		$this->assertSame(
			expected: '2026-07-01 08:00:00',
			actual: $receipt->getAcknowledgedAtFormatted()
		);
	}

	/**
	 * REQ-ACK-003: a race that hits the unique index is swallowed — the
	 * winning row is returned and no duplicate activity is emitted.
	 *
	 * @return void
	 */
	public function testAcknowledgeRaceReturnsWinnerWithoutActivity(): void {
		$winner = $this->makeReceipt(key: 'ak-1', userId: 'alice', version: 1);
		// First lookup: none; after the failed insert: the winner.
		$this->ackMapper->method('findOneFor')
			->willReturnOnConsecutiveCalls(null, $winner);

		$dbException = $this->createMock(originalClassName: DbException::class);
		$dbException->method('getReason')
			->willReturn(DbException::REASON_UNIQUE_CONSTRAINT_VIOLATION);
		$this->ackMapper->method('record')->willThrowException($dbException);

		$this->activityPublisher->expects($this->never())->method('publish');

		$receipt = $this->service->acknowledge(
			announcementKey: 'ak-1',
			userId: 'alice',
			contentVersion: 1
		);

		$this->assertSame(expected: 'alice', actual: $receipt->getUserId());
	}

	/**
	 * REQ-ACK-005: with reacknowledgeOnChange = 1, an item is outstanding
	 * unless the user holds a receipt for the CURRENT version.
	 *
	 * @return void
	 */
	public function testIsOutstandingReackOnChangeChecksCurrentVersion(): void {
		$placement = $this->makePlacement(key: 'ak-1', dashboardId: 5, version: 2, reack: 1);

		// No receipt at version 2 → outstanding.
		$this->ackMapper->method('existsFor')
			->with('ak-1', 'alice', 2)
			->willReturn(false);

		$this->assertTrue(
			condition: $this->service->isOutstanding(placement: $placement, userId: 'alice')
		);
	}

	/**
	 * REQ-ACK-005: with reacknowledgeOnChange = 0, ANY prior receipt for the
	 * announcement satisfies the requirement even after a version bump.
	 *
	 * @return void
	 */
	public function testIsOutstandingNoReackAnyReceiptSatisfies(): void {
		$placement = $this->makePlacement(key: 'ak-1', dashboardId: 5, version: 2, reack: 0);

		$this->ackMapper->method('findByUserForAnnouncement')
			->with('ak-1', 'alice')
			->willReturn([$this->makeReceipt(key: 'ak-1', userId: 'alice', version: 1)]);

		$this->assertFalse(
			condition: $this->service->isOutstanding(placement: $placement, userId: 'alice')
		);
	}

	/**
	 * REQ-ACK-002: a placement with requiresAcknowledgement = 0 is never
	 * outstanding (no regression to non-acknowledgement placements).
	 *
	 * @return void
	 */
	public function testIsOutstandingFalseWhenRequirementOff(): void {
		$placement = new WidgetPlacement();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$placement->setRequiresAcknowledgement(0);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters

		$this->assertFalse(
			condition: $this->service->isOutstanding(placement: $placement, userId: 'alice')
		);
	}

	/**
	 * REQ-ACK-004: the report separates acknowledged from pending against
	 * the live group audience resolved via IGroupManager.
	 *
	 * @return void
	 */
	public function testReportSeparatesAcknowledgedFromPending(): void {
		// Template dashboard (id 1) routing to group "sociaal-domein".
		$template = new Dashboard();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$template->setId(1);
		$template->setUuid('tmpl-uuid');
		$template->setName('HR 2026');
		$template->setType(Dashboard::TYPE_ADMIN_TEMPLATE);
		$template->setTargetGroups(json_encode(['sociaal-domein']));
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters

		$blueprint = $this->makePlacement(key: 'ak-1', dashboardId: 1, version: 1);

		$this->placementMapper->method('findByAnnouncementKey')
			->with('ak-1')->willReturn([$blueprint]);
		$this->dashboardMapper->method('find')->with(1)->willReturn($template);

		// Live audience {alice, bob, carol}.
		$group = $this->createMock(originalClassName: IGroup::class);
		$group->method('getUsers')->willReturn([
			$this->makeUser(uid: 'alice'),
			$this->makeUser(uid: 'bob'),
			$this->makeUser(uid: 'carol'),
		]);
		$this->groupManager->method('get')->with('sociaal-domein')->willReturn($group);

		// Alice and carol acknowledged version 1.
		$this->ackMapper->method('findByAnnouncement')
			->with('ak-1', 1)
			->willReturn([
				$this->makeReceipt(key: 'ak-1', userId: 'alice', version: 1),
				$this->makeReceipt(key: 'ak-1', userId: 'carol', version: 1),
			]);

		$report = $this->service->report(announcementKey: 'ak-1');

		$this->assertSame(expected: 2, actual: $report['acknowledgedCount']);
		$this->assertSame(expected: 1, actual: $report['pendingCount']);
		$this->assertSame(expected: ['bob'], actual: $report['pending']);
		$this->assertCount(expectedCount: 3, haystack: $report['rows']);
	}

	/**
	 * REQ-ACK-004: report throws for an unknown announcement key.
	 *
	 * @return void
	 */
	public function testReportThrowsForUnknownAnnouncement(): void {
		$this->placementMapper->method('findByAnnouncementKey')->willReturn([]);

		$this->expectException(exception: DoesNotExistException::class);
		$this->service->report(announcementKey: 'nope');
	}

	/**
	 * Route announcement ak-1 through a template that targets one group of
	 * the given members.
	 *
	 * @param array<int, string> $members The group members.
	 *
	 * @return void
	 */
	private function routeToGroup(array $members): void {
		$template = new Dashboard();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$template->setId(1);
		$template->setType(Dashboard::TYPE_ADMIN_TEMPLATE);
		$template->setTargetGroups(json_encode(['sociaal-domein']));
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$this->placementMapper->method('findByAnnouncementKey')->willReturnMap([
			['ak-1', [$this->makePlacement(key: 'ak-1', dashboardId: 1)]],
			['nope', []],
		]);
		$this->dashboardMapper->method('find')->with(1)->willReturn($template);
		$group = $this->createMock(originalClassName: IGroup::class);
		$group->method('getUsers')->willReturn(array_map(fn (string $uid): IUser => $this->makeUser(uid: $uid), $members));
		$this->groupManager->method('get')->with('sociaal-domein')->willReturn($group);
	}

	/**
	 * REQ-ACK-003: only a member of the announcement's live audience is in
	 * its audience; an unknown announcement has nobody in it.
	 *
	 * @return void
	 */
	public function testIsInAudienceFollowsTheGroupRouting(): void {
		$this->routeToGroup(members: ['alice', 'carol']);

		$this->assertTrue(condition: $this->service->isAllowedToAcknowledge(announcementKey: 'ak-1', userId: 'alice'));
		$this->assertTrue(condition: $this->service->isAllowedToAcknowledge(announcementKey: 'ak-1', userId: 'carol'));
		$this->assertFalse(condition: $this->service->isAllowedToAcknowledge(announcementKey: 'ak-1', userId: 'mallory'));
		$this->assertFalse(condition: $this->service->isAllowedToAcknowledge(announcementKey: 'ak-1', userId: ''));
		$this->assertFalse(condition: $this->service->isAllowedToAcknowledge(announcementKey: 'nope', userId: 'alice'));
	}

	/**
	 * REQ-ACK-003: without group routing the audience is the recipients that
	 * hold a copy of the announcement.
	 *
	 * @return void
	 */
	public function testIsInAudienceFallsBackToTheRecipients(): void {
		$template = new Dashboard();
		$copy = new Dashboard();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$template->setId(1);
		$template->setType(Dashboard::TYPE_ADMIN_TEMPLATE);
		$copy->setId(2);
		$copy->setType(Dashboard::TYPE_USER);
		$copy->setUserId('bob');
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$this->placementMapper->method('findByAnnouncementKey')->willReturn([
			$this->makePlacement(key: 'ak-2', dashboardId: 1),
			$this->makePlacement(key: 'ak-2', dashboardId: 2),
		]);
		$this->dashboardMapper->method('find')->willReturnMap([[1, $template], [2, $copy]]);

		$this->assertTrue(condition: $this->service->isAllowedToAcknowledge(announcementKey: 'ak-2', userId: 'bob'));
		$this->assertFalse(condition: $this->service->isAllowedToAcknowledge(announcementKey: 'ak-2', userId: 'alice'));
	}

	/**
	 * A numeric user id is matched as the same string, and never loosely:
	 * "1e1" is not user "10".
	 *
	 * @return void
	 */
	public function testIsInAudienceMatchesNumericUserIdsExactly(): void {
		$this->routeToGroup(members: ['10']);

		$this->assertTrue(condition: $this->service->isAllowedToAcknowledge(announcementKey: 'ak-1', userId: '10'));
		$this->assertFalse(condition: $this->service->isAllowedToAcknowledge(announcementKey: 'ak-1', userId: '1e1'));
	}

	/**
	 * Give alice one user dashboard holding ak-in (routed to a group she is
	 * in) and ak-out (routed to a group she has left), neither acknowledged.
	 *
	 * @return void
	 */
	private function giveAliceTwoAnnouncements(): void {
		$own = new Dashboard();
		$inTemplate = new Dashboard();
		$outTemplate = new Dashboard();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$own->setId(2);
		$own->setUuid('d-2');
		$own->setType(Dashboard::TYPE_USER);
		$own->setUserId('alice');
		$inTemplate->setId(1);
		$inTemplate->setType(Dashboard::TYPE_ADMIN_TEMPLATE);
		$inTemplate->setTargetGroups(json_encode(['g-in']));
		$outTemplate->setId(3);
		$outTemplate->setType(Dashboard::TYPE_ADMIN_TEMPLATE);
		$outTemplate->setTargetGroups(json_encode(['g-out']));
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$inCopy = $this->makePlacement(key: 'ak-in', dashboardId: 2, version: 4);
		$outCopy = $this->makePlacement(key: 'ak-out', dashboardId: 2);
		$this->dashboardMapper->method('findByUserId')->with('alice')->willReturn([$own]);
		$this->placementMapper->method('findByDashboardId')->with(2)->willReturn([$inCopy, $outCopy]);
		$this->placementMapper->method('findByAnnouncementKey')->willReturnMap([
			['ak-in', [$this->makePlacement(key: 'ak-in', dashboardId: 1, version: 4), $inCopy]],
			['ak-out', [$this->makePlacement(key: 'ak-out', dashboardId: 3), $outCopy]],
		]);
		$this->dashboardMapper->method('find')->willReturnMap([[1, $inTemplate], [2, $own], [3, $outTemplate]]);
		$this->ackMapper->method('findByUserForAnnouncement')->willReturn([]);
		$member = $this->createMock(originalClassName: IGroup::class);
		$member->method('getUsers')->willReturn([$this->makeUser(uid: 'alice')]);
		$left = $this->createMock(originalClassName: IGroup::class);
		$left->method('getUsers')->willReturn([$this->makeUser(uid: 'bob')]);
		$this->groupManager->method('get')->willReturnMap([['g-in', $member], ['g-out', $left]]);
	}

	/**
	 * REQ-ACK-002: a copy of an announcement the user is no longer addressed
	 * by is not pending, since it can never be acknowledged.
	 *
	 * @return void
	 */
	public function testPendingListsOnlyAnnouncementsStillAddressedToTheUser(): void {
		$this->giveAliceTwoAnnouncements();

		$this->assertSame(
			expected: [
				'count' => 1,
				'items' => [
					[
						'placementId' => 20,
						'dashboardUuid' => 'd-2',
						'announcementKey' => 'ak-in',
						'prompt' => null,
						'deadline' => null,
						'contentVersion' => 4,
					],
				],
			],
			actual: $this->service->getPending(userId: 'alice')
		);
	}

	/**
	 * The current content version is the blueprint's, not a copy's; an
	 * unknown announcement has none.
	 *
	 * @return void
	 */
	public function testCurrentContentVersionIsTheBlueprintVersion(): void {
		$template = new Dashboard();
		$copy = new Dashboard();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$template->setId(1);
		$template->setType(Dashboard::TYPE_ADMIN_TEMPLATE);
		$copy->setId(2);
		$copy->setType(Dashboard::TYPE_USER);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$this->placementMapper->method('findByAnnouncementKey')->willReturnMap([
			['ak-1', [$this->makePlacement(key: 'ak-1', dashboardId: 2, version: 2), $this->makePlacement(key: 'ak-1', dashboardId: 1, version: 3)]],
			['nope', []],
		]);
		$this->dashboardMapper->method('find')->willReturnMap([[1, $template], [2, $copy]]);

		$this->assertSame(expected: 3, actual: $this->service->currentContentVersion(announcementKey: 'ak-1', userId: 'alice'));
		$this->assertNull(actual: $this->service->currentContentVersion(announcementKey: 'nope', userId: 'alice'));
	}

	/**
	 * Build dashboards (id => [type, owner, target groups]) for the find() stub.
	 *
	 * @param array<int, array{0: string, 1: string|null, 2: array<int, string>}> $specs The dashboards.
	 *
	 * @return void
	 */
	private function dashboards(array $specs): void {
		$map = [];
		foreach ($specs as $id => [$type, $owner, $groups]) {
			$dashboard = new Dashboard();
			// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$dashboard->setId($id);
			$dashboard->setUuid('d-' . $id);
			$dashboard->setType($type);
			$dashboard->setUserId($owner);
			$dashboard->setTargetGroups(json_encode($groups));
			// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$map[$id] = $dashboard;
		}

		$this->dashboardMapper->method('find')->willReturnCallback(static function (int $id) use ($map): Dashboard {
			if (isset($map[$id]) === false) {
				throw new DoesNotExistException(msg: 'none');
			}

			return $map[$id];
		});
	}

	/**
	 * REQ-ACK-002: every announcement still addressed to the user is
	 * pending, also after one that is not, and an acknowledged one is not.
	 *
	 * @return void
	 */
	public function testPendingListsEveryAddressedAnnouncement(): void {
		$this->dashboards(specs: [
			1 => [Dashboard::TYPE_ADMIN_TEMPLATE, 'tom', ['g-left']],
			2 => [Dashboard::TYPE_USER, 'alice', []],
			3 => [Dashboard::TYPE_ADMIN_TEMPLATE, 'tom', ['g-in']],
			4 => [Dashboard::TYPE_ADMIN_TEMPLATE, 'tom', ['g-other']],
			5 => [Dashboard::TYPE_ADMIN_TEMPLATE, 'tom', ['g-in']],
		]);
		$own = new Dashboard();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$own->setId(2);
		$own->setUuid('d-2');
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$copies = [];
		foreach (['ak-out' => 1, 'ak-a' => 3, 'ak-b' => 4, 'ak-done' => 5] as $key => $templateId) {
			$copies[$key] = [$this->makePlacement(key: $key, dashboardId: $templateId), $this->makePlacement(key: $key, dashboardId: 2)];
		}

		$this->dashboardMapper->method('findByUserId')->with('alice')->willReturn([$own]);
		$ownCopies = array_map(static fn (array $pair): WidgetPlacement => $pair[1], array_values($copies));
		$this->placementMapper->method('findByDashboardId')->with(2)->willReturn($ownCopies);
		$this->placementMapper->method('findByAnnouncementKey')->willReturnCallback(static fn (string $key): array => $copies[$key] ?? []);
		$done = [$this->makeReceipt(key: 'ak-done', userId: 'alice', version: 1)];
		$this->ackMapper->method('findByUserForAnnouncement')->willReturnMap([
			['ak-out', 'alice', []],
			['ak-a', 'alice', []],
			['ak-b', 'alice', []],
			['ak-done', 'alice', $done],
		]);
		$groups = [];
		foreach (['g-left' => 'bob', 'g-in' => 'alice', 'g-other' => 'alice'] as $gid => $member) {
			$group = $this->createMock(originalClassName: IGroup::class);
			$group->method('getUsers')->willReturn([$this->makeUser(uid: $member)]);
			$groups[] = [$gid, $group];
		}

		$this->groupManager->method('get')->willReturnMap($groups);

		$pending = $this->service->getPending(userId: 'alice');

		$this->assertSame(expected: ['ak-a', 'ak-b'], actual: array_column($pending['items'], 'announcementKey'));
		$this->assertSame(expected: 2, actual: $pending['count']);
	}

	/**
	 * REQ-ACK-005: without a template the current version is the one on the
	 * user's own copy; with a template it is the template's.
	 *
	 * @return void
	 */
	public function testCurrentContentVersionWithoutATemplateIsTheUsersCopy(): void {
		$this->dashboards(specs: [
			1 => [Dashboard::TYPE_ADMIN_TEMPLATE, 'tom', []],
			2 => [Dashboard::TYPE_USER, 'bob', []],
			3 => [Dashboard::TYPE_USER, 'alice', []],
		]);
		$this->placementMapper->method('findByAnnouncementKey')->willReturnMap([
			[
				'orphaned',
				[$this->makePlacement(key: 'orphaned', dashboardId: 2, version: 1), $this->makePlacement(key: 'orphaned', dashboardId: 3, version: 2)],
			],
			[
				'templated',
				[$this->makePlacement(key: 'templated', dashboardId: 3, version: 5), $this->makePlacement(key: 'templated', dashboardId: 1, version: 4)],
			],
		]);

		$this->assertSame(expected: 2, actual: $this->service->currentContentVersion(announcementKey: 'orphaned', userId: 'alice'));
		$this->assertSame(expected: 1, actual: $this->service->currentContentVersion(announcementKey: 'orphaned', userId: 'bob'));
		$this->assertSame(expected: 1, actual: $this->service->currentContentVersion(announcementKey: 'orphaned', userId: 'carol'));
		$this->assertSame(expected: 4, actual: $this->service->currentContentVersion(announcementKey: 'templated', userId: 'alice'));
	}

	/**
	 * REQ-ACK-004: the owner is the template's owner even when a copy comes
	 * first; without a template it is the first placement's owner.
	 *
	 * @return void
	 */
	public function testResolveOwnerUserIdIsTheTemplateOwner(): void {
		$this->dashboards(specs: [
			1 => [Dashboard::TYPE_ADMIN_TEMPLATE, 'tom', []],
			2 => [Dashboard::TYPE_USER, 'bob', []],
			3 => [Dashboard::TYPE_USER, '', []],
		]);
		$this->placementMapper->method('findByAnnouncementKey')->willReturnMap([
			['templated', [$this->makePlacement(key: 'templated', dashboardId: 2), $this->makePlacement(key: 'templated', dashboardId: 1)]],
			['personal', [$this->makePlacement(key: 'personal', dashboardId: 2)]],
			['ownerless', [$this->makePlacement(key: 'ownerless', dashboardId: 3)]],
			['gone', [$this->makePlacement(key: 'gone', dashboardId: 9)]],
			['nope', []],
		]);

		$this->assertSame(expected: 'tom', actual: $this->service->resolveOwnerUserId(announcementKey: 'templated'));
		$this->assertSame(expected: 'bob', actual: $this->service->resolveOwnerUserId(announcementKey: 'personal'));
		$this->assertNull(actual: $this->service->resolveOwnerUserId(announcementKey: 'ownerless'));
		$this->assertNull(actual: $this->service->resolveOwnerUserId(announcementKey: 'gone'));
		$this->assertNull(actual: $this->service->resolveOwnerUserId(announcementKey: 'nope'));
	}

	/**
	 * REQ-ACK-006: the activity line names the acknowledged version.
	 *
	 * @return void
	 */
	public function testActivityNamesTheAcknowledgedVersion(): void {
		$this->ackMapper->method('findOneFor')->willReturn(null);
		$this->ackMapper->method('record')->willReturn($this->makeReceipt(key: 'ak-1', userId: 'alice', version: 2));
		$this->placementMapper->method('findByAnnouncementKey')->willReturn([]);
		$this->activityPublisher->expects($this->once())->method('publish')->with(
			$this->callback(callback: static fn (ActivityRow $row): bool => $row->extraParams === ['self' => true, 'version' => 2]),
			'alice'
		);

		$this->service->acknowledge(announcementKey: 'ak-1', userId: 'alice', contentVersion: 2);
	}
}//end class
