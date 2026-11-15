<?php

/**
 * DashboardActivityEmitter Test
 *
 * Drives the emitter directly against a mocked publisher and share
 * mapper: who each dashboard event reaches (the default group, target
 * groups, group shares, user shares), the daily debounce on updates,
 * and that a failing publisher or share lookup is logged and swallowed
 * rather than breaking the dashboard write that triggered it.
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

use OCA\LaunchPad\Activity\ActivityPublisher;
use OCA\LaunchPad\Activity\ActivityRow;
use OCA\LaunchPad\Activity\ActivitySubject;
use OCA\LaunchPad\Activity\DashboardActivityEmitter;
use OCA\LaunchPad\Activity\DebounceHelper;
use OCA\LaunchPad\Activity\Extension;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Db\DashboardShareMapper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Unit tests for DashboardActivityEmitter.
 *
 * @covers \OCA\LaunchPad\Activity\DashboardActivityEmitter
 * @uses   \OCA\LaunchPad\Activity\ActivityRow
 * @uses   \OCA\LaunchPad\Activity\ActivitySubject
 * @uses   \OCA\LaunchPad\Activity\DebounceHelper
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Db\DashboardShare
 */
class DashboardActivityEmitterTest extends TestCase {
	/**
	 * Publisher mock.
	 *
	 * @var ActivityPublisher&MockObject
	 */
	private ActivityPublisher&MockObject $publisher;

	/**
	 * Share mapper mock.
	 *
	 * @var DashboardShareMapper&MockObject
	 */
	private DashboardShareMapper&MockObject $shareMapper;

	/**
	 * Logger mock.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private LoggerInterface&MockObject $logger;

	/**
	 * The fake clock the debounce helper reads.
	 *
	 * @var integer
	 */
	private int $now = 1_000_000;

	/**
	 * The emitter under test.
	 *
	 * @var DashboardActivityEmitter
	 */
	private DashboardActivityEmitter $emitter;

	/**
	 * Wire the emitter with mocks and a fake-clock debounce helper.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->publisher   = $this->createMock(originalClassName: ActivityPublisher::class);
		$this->shareMapper = $this->createMock(originalClassName: DashboardShareMapper::class);
		$this->logger      = $this->createMock(originalClassName: LoggerInterface::class);

		$this->emitter = new DashboardActivityEmitter(
			publisher: $this->publisher,
			shareMapper: $this->shareMapper,
			debounce: new DebounceHelper(clock: fn (): int => $this->now),
			logger: $this->logger,
		);
	}

	/**
	 * Build a dashboard.
	 *
	 * @param string      $type         The dashboard type.
	 * @param string|null $groupId      The owning group.
	 * @param array       $targetGroups Target groups.
	 * @param int|null    $id           The database id.
	 *
	 * @return Dashboard The dashboard.
	 */
	private function dashboard(
		string $type = Dashboard::TYPE_USER,
		?string $groupId = null,
		array $targetGroups = [],
		?int $id = 7,
	): Dashboard {
		$dashboard = new Dashboard();
		if ($id !== null) {
			$dashboard->setId($id);
		}

		$dashboard->setUuid('dash-7');
		$dashboard->setName('Board');
		$dashboard->setType($type);
		$dashboard->setGroupId($groupId);
		$dashboard->setTargetGroupsArray($targetGroups);

		return $dashboard;
	}

	/**
	 * Build a share.
	 *
	 * @param string $type  The share type.
	 * @param string $with  The share target.
	 * @param string $level The permission level.
	 *
	 * @return DashboardShare The share.
	 */
	private function share(string $type, string $with, string $level = Dashboard::PERMISSION_VIEW_ONLY): DashboardShare {
		$share = new DashboardShare();
		$share->setShareType($type);
		$share->setShareWith($with);
		$share->setPermissionLevel($level);

		return $share;
	}

	/**
	 * The row an event about the test dashboard carries.
	 *
	 * @param string $type The event type.
	 *
	 * @return ActivityRow The row.
	 */
	private function row(string $type): ActivityRow {
		return new ActivityRow(type: $type, actorUserId: 'alice', dashboard: new ActivitySubject(uuid: 'dash-7', name: 'Board'));
	}

	/**
	 * shared() sends one row with the recipient and role to the given users.
	 *
	 * @return void
	 */
	public function testSharedPublishesRecipientAndRoleToTheRecipients(): void {
		$expected = new ActivityRow(
			type: Extension::EVENT_SHARED,
			actorUserId: 'alice',
			dashboard: new ActivitySubject(uuid: 'dash-7', name: 'Board'),
			extraParams: ['recipient' => 'bob', 'role' => Dashboard::PERMISSION_VIEW_ONLY]
		);
		$this->publisher->expects($this->once())
			->method('publishToRecipients')
			->with($expected, ['bob', 'carol'])
			->willReturn(2);

		$count = $this->emitter->shared(
			dashboard: $this->dashboard(),
			share: $this->share(type: DashboardShare::SHARE_TYPE_USER, with: 'bob'),
			actor: 'alice',
			recipients: ['bob', 'carol']
		);

		$this->assertSame(expected: 2, actual: $count);
	}

	/**
	 * A failing publisher is logged as a warning and reported as zero sent.
	 *
	 * @return void
	 */
	public function testSharedSwallowsAndLogsAPublisherFailure(): void {
		$this->publisher->method('publishToRecipients')->willThrowException(new RuntimeException('activity down'));
		$this->logger->expects($this->once())
			->method('warning')
			->with('LaunchPad dashboard activity could not be emitted', ['exception' => 'activity down']);

		$count = $this->emitter->shared(
			dashboard: $this->dashboard(),
			share: $this->share(type: DashboardShare::SHARE_TYPE_USER, with: 'bob'),
			actor: 'alice',
			recipients: ['bob']
		);

		$this->assertSame(expected: 0, actual: $count);
	}

	/**
	 * Publishing to the default group goes global; other target groups get
	 * their own fan-out, duplicates of the owning group only once.
	 *
	 * @return void
	 */
	public function testPublishedReachesDefaultGlobalAndEachGroupOnce(): void {
		$this->shareMapper->method('findByDashboardId')->willReturn([]);
		$this->publisher->expects($this->once())
			->method('publishGlobal')
			->with($this->row(type: Extension::EVENT_PUBLISHED))
			->willReturn(10);
		$groups = [];
		$this->publisher->expects($this->exactly(2))
			->method('publishToGroup')
			->willReturnCallback(
				function (ActivityRow $row, string $groupId) use (&$groups): int {
					$groups[] = $groupId;
					return 3;
				}
			);
		$this->publisher->expects($this->never())->method('publishToRecipients');

		$count = $this->emitter->published(
			dashboard: $this->dashboard(
				type: Dashboard::TYPE_GROUP_SHARED,
				groupId: 'staff',
				targetGroups: ['staff', Dashboard::DEFAULT_GROUP_ID, '', 'sales']
			),
			actor: 'alice'
		);

		$this->assertSame(expected: ['staff', 'sales'], actual: $groups);
		$this->assertSame(expected: 16, actual: $count);
	}

	/**
	 * Shares add group fan-outs and one batched send to all user recipients.
	 *
	 * @return void
	 */
	public function testPublishedReachesGroupSharesAndBatchesUserShares(): void {
		$this->shareMapper->expects($this->once())
			->method('findByDashboardId')
			->with(7)
			->willReturn(
				[
					$this->share(type: DashboardShare::SHARE_TYPE_USER, with: 'bob'),
					$this->share(type: DashboardShare::SHARE_TYPE_GROUP, with: 'sales'),
					$this->share(type: DashboardShare::SHARE_TYPE_USER, with: 'carol'),
				]
			);
		$this->publisher->expects($this->once())
			->method('publishToGroup')
			->with($this->row(type: Extension::EVENT_PUBLISHED), 'sales')
			->willReturn(4);
		$this->publisher->expects($this->once())
			->method('publishToRecipients')
			->with($this->row(type: Extension::EVENT_PUBLISHED), ['bob', 'carol'])
			->willReturn(2);

		$this->assertSame(expected: 6, actual: $this->emitter->published(dashboard: $this->dashboard(), actor: 'alice'));
	}

	/**
	 * A failing share lookup is logged and treated as "no shares".
	 *
	 * @return void
	 */
	public function testPublishedTreatsAFailingShareLookupAsNoShares(): void {
		$this->shareMapper->method('findByDashboardId')->willThrowException(new RuntimeException('db gone'));
		$this->logger->expects($this->once())->method('warning');
		$this->publisher->expects($this->never())->method('publishToRecipients');
		$this->publisher->expects($this->once())->method('publishToGroup')->with($this->anything(), 'staff')->willReturn(1);

		$count = $this->emitter->published(
			dashboard: $this->dashboard(type: Dashboard::TYPE_GROUP_SHARED, groupId: 'staff'),
			actor: 'alice'
		);

		$this->assertSame(expected: 1, actual: $count);
	}

	/**
	 * An unsaved dashboard has no shares, so the mapper is never asked.
	 *
	 * @return void
	 */
	public function testAnUnsavedDashboardNeverQueriesShares(): void {
		$this->shareMapper->expects($this->never())->method('findByDashboardId');
		$this->publisher->expects($this->never())->method($this->anything());

		$this->assertSame(expected: 0, actual: $this->emitter->published(dashboard: $this->dashboard(id: null), actor: 'alice'));
	}

	/**
	 * One failing group fan-out does not stop the others.
	 *
	 * @return void
	 */
	public function testOneFailingGroupDoesNotStopTheRest(): void {
		$this->shareMapper->method('findByDashboardId')->willReturn([]);
		$this->publisher->method('publishToGroup')->willReturnCallback(
			function (ActivityRow $row, string $groupId): int {
				if ($groupId === 'a') {
					throw new RuntimeException('boom');
				}

				return 5;
			}
		);
		$this->logger->expects($this->once())->method('warning');

		$count = $this->emitter->published(dashboard: $this->dashboard(targetGroups: ['a', 'b']), actor: 'alice');

		$this->assertSame(expected: 5, actual: $count);
	}

	/**
	 * updated() on a dashboard without audience sends nothing and does not
	 * spend the daily debounce slot.
	 *
	 * @return void
	 */
	public function testUpdatedWithoutAudienceSendsNothingAndKeepsTheDailySlot(): void {
		$this->shareMapper->method('findByDashboardId')->willReturnOnConsecutiveCalls([], [$this->share(type: DashboardShare::SHARE_TYPE_USER, with: 'bob')]);
		$this->publisher->expects($this->once())
			->method('publishToRecipients')
			->with($this->row(type: Extension::EVENT_UPDATED), ['bob'])
			->willReturn(1);

		$this->assertSame(expected: 0, actual: $this->emitter->updated(dashboard: $this->dashboard(), actor: 'alice'));
		$this->assertSame(expected: 1, actual: $this->emitter->updated(dashboard: $this->dashboard(), actor: 'alice'));
	}

	/**
	 * updated() is announced once per day per dashboard, then again after a day.
	 *
	 * @return void
	 */
	public function testUpdatedIsDebouncedForADay(): void {
		$this->shareMapper->method('findByDashboardId')->willReturn([]);
		$this->publisher->expects($this->exactly(2))->method('publishToGroup')->willReturn(1);

		$dashboard = $this->dashboard(targetGroups: ['sales']);
		$this->assertSame(expected: 1, actual: $this->emitter->updated(dashboard: $dashboard, actor: 'alice'));
		$this->now += (DebounceHelper::DAILY_TTL_SECONDS - 1);
		$this->assertSame(expected: 0, actual: $this->emitter->updated(dashboard: $dashboard, actor: 'alice'));
		$this->now += 1;
		$this->assertSame(expected: 1, actual: $this->emitter->updated(dashboard: $dashboard, actor: 'alice'));
	}
}
