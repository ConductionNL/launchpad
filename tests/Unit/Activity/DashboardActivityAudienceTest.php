<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/activity-feed-integration/spec.md
 */

declare(strict_types=1);

namespace Unit\Activity;

use OCA\LaunchPad\Activity\ActivityPublisher;
use OCA\LaunchPad\Activity\DashboardActivityEmitter;
use OCA\LaunchPad\Activity\DebounceHelper;
use OCA\LaunchPad\Activity\PublisherTools;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardShareMapper;
use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The rows a group dashboard's activity writes, through the real publisher.
 */
class DashboardActivityAudienceTest extends TestCase {
	/** @var list<IEvent> */
	private array $rows = [];

	/**
	 * An emitter over a real publisher whose group `team` holds `$members`.
	 *
	 * @param list<string> $members The member ids.
	 *
	 * @return DashboardActivityEmitter
	 */
	private function emitter(array $members): DashboardActivityEmitter {
		$manager = $this->createMock(IManager::class);
		$manager->method('generateEvent')->willReturnCallback(static fn (): IEvent => new RecordingEvent());
		$manager->method('publish')->willReturnCallback(
			function (IEvent $event): void {
				$this->rows[] = $event;
			}
		);

		$users = [];
		foreach ($members as $uid) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
			$users[] = $user;
		}

		$group = $this->createMock(IGroup::class);
		$group->method('getUsers')->willReturn($users);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('get')->willReturn($group);

		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRouteAbsolute')->willReturn('https://nc.example/apps/launchpad/');

		$logger    = $this->createMock(LoggerInterface::class);
		$debounce  = new DebounceHelper(clock: static fn (): int => 1000);
		$publisher = new ActivityPublisher(
			manager: $manager,
			groupManager: $groups,
			userManager: $this->createMock(IUserManager::class),
			tools: new PublisherTools(debounce: $debounce, logger: $logger, urlGenerator: $urls)
		);

		return new DashboardActivityEmitter(
			publisher: $publisher,
			shareMapper: $this->createMock(DashboardShareMapper::class),
			debounce: $debounce,
			logger: $logger
		);
	}//end emitter()

	/**
	 * A group dashboard of group `team`.
	 *
	 * @return Dashboard
	 */
	private function dashboard(): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId(7);
		$dashboard->setUuid('dash-7');
		$dashboard->setName('Campaigns');
		$dashboard->setType(Dashboard::TYPE_GROUP_SHARED);
		$dashboard->setGroupId('team');

		return $dashboard;
	}//end dashboard()

	/**
	 * Every row links to the dashboard by its UUID.
	 *
	 * @return void
	 */
	public function testARowLinksToItsDashboard(): void {
		$count = $this->emitter(members: ['bob'])->published(dashboard: $this->dashboard(), actor: 'admin');

		$this->assertSame(2, $count);
		foreach ($this->rows as $row) {
			$this->assertSame('https://nc.example/apps/launchpad/?dashboard=dash-7', $row->getLink());
		}
	}//end testARowLinksToItsDashboard()

	/**
	 * A group with no members gets no row, and neither does the actor.
	 *
	 * @return void
	 */
	public function testAnEmptyGroupWritesNoRow(): void {
		$count = $this->emitter(members: [])->updated(dashboard: $this->dashboard(), actor: 'admin');

		$this->assertSame(0, $count);
		$this->assertSame([], $this->rows);
	}//end testAnEmptyGroupWritesNoRow()
}//end class
