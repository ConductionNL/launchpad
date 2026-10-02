<?php

/**
 * Issue #713: the activity events a digest needs are sent from where a
 * dashboard changes state (engagement-activity-digest tasks 2 to 4).
 *
 * The services under test are the real DashboardShareService and
 * DashboardService with the real DashboardActivityEmitter and DebounceHelper
 * between them; only the Nextcloud-facing ActivityPublisher and the mappers
 * are doubles, created from the real classes.
 *
 * @category  Tests
 * @package   Unit\Activity
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
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
use OCA\LaunchPad\Activity\Extension;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Db\DashboardShareMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardFactory;
use OCA\LaunchPad\Service\DashboardResolver;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardShareService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\PersonalLayerService;
use OCA\LaunchPad\Service\TemplateService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\L10N\IFactory;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DashboardActivityEmissionTest extends TestCase {

	/** @var ActivityPublisher&MockObject */
	private $publisher;

	/** @var DashboardShareMapper&MockObject */
	private $shareMapper;

	/** @var DashboardMapper&MockObject */
	private $dashboardMapper;

	/** @var IGroupManager&MockObject */
	private $groupManager;

	private int $now = 1_800_000_000;

	private DashboardActivityEmitter $emitter;

	protected function setUp(): void {
		$this->publisher = $this->createMock(ActivityPublisher::class);
		$this->shareMapper = $this->createMock(DashboardShareMapper::class);
		$this->dashboardMapper = $this->createMock(DashboardMapper::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->dashboardMapper->method('update')->willReturnArgument(0);

		$this->emitter = new DashboardActivityEmitter(
			publisher: $this->publisher,
			shareMapper: $this->shareMapper,
			debounce: new DebounceHelper(fn (): int => $this->now),
			logger: $this->createMock(LoggerInterface::class),
		);
	}

	private function dashboard(string $type = Dashboard::TYPE_USER, ?string $groupId = null): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId(7);
		$dashboard->setUuid('dash-7');
		$dashboard->setName('Team board');
		$dashboard->setUserId('alice');
		$dashboard->setType($type);
		$dashboard->setGroupId($groupId);
		$dashboard->setPublicationStatus(Dashboard::STATUS_DRAFT);

		return $dashboard;
	}

	private function shareService(Dashboard $dashboard): DashboardShareService {
		$this->dashboardMapper->method('find')->willReturn($dashboard);
		$this->shareMapper->method('findShare')->willReturn(null);
		$this->shareMapper->method('insert')->willReturnArgument(0);

		$notifications = $this->createMock(INotificationManager::class);
		$notification = $this->createMock(INotification::class);
		foreach (['setApp', 'setUser', 'setDateTime', 'setObject', 'setSubject'] as $method) {
			$notification->method($method)->willReturnSelf();
		}
		$notifications->method('createNotification')->willReturn($notification);

		return new DashboardShareService(
			shareMapper: $this->shareMapper,
			dashboardMapper: $this->dashboardMapper,
			groupManager: $this->groupManager,
			notificationManager: $notifications,
			db: $this->createMock(IDBConnection::class),
			activity: $this->emitter,
		);
	}

	private function dashboardService(): DashboardService {
		$this->groupManager->method('isAdmin')->willReturn(true);

		return new DashboardService(
			dashboardMapper: $this->dashboardMapper,
			placementMapper: $this->createMock(WidgetPlacementMapper::class),
			settingMapper: $this->createMock(AdminSettingMapper::class),
			templateService: $this->createMock(TemplateService::class),
			dashboardFactory: $this->createMock(DashboardFactory::class),
			dashResolver: $this->createMock(DashboardResolver::class),
			treeService: $this->createMock(DashboardTreeService::class),
			groupManager: $this->groupManager,
			adminTemplateService: $this->createMock(AdminTemplateService::class),
			db: $this->createMock(IDBConnection::class),
			config: $this->createMock(IConfig::class),
			l10nFactory: $this->createMock(IFactory::class),
			logger: $this->createMock(LoggerInterface::class),
			personalLayers: $this->createMock(PersonalLayerService::class),
			footerService: $this->createMock(\OCA\LaunchPad\Service\FooterService::class),
			activity: $this->emitter,
		);
	}

	public function testSharingWithAUserSendsDashboardSharedToThatUser(): void {
		$this->publisher->expects($this->once())->method('publishToRecipients')
			->with(
				Extension::EVENT_SHARED,
				'alice',
				'dash-7',
				'Team board',
				'',
				['bob'],
				$this->callback(static fn (array $p): bool => ($p['recipient'] ?? '') === 'bob')
			)
			->willReturn(2);

		$this->shareService($this->dashboard())->addShare(
			dashboardId: 7,
			shareType: DashboardShare::SHARE_TYPE_USER,
			shareWith: 'bob',
			permissionLevel: Dashboard::PERMISSION_VIEW_ONLY,
			callerId: 'alice'
		);
	}

	public function testPublishingAGroupDashboardTellsTheGroupOnce(): void {
		$dashboard = $this->dashboard(Dashboard::TYPE_GROUP_SHARED, 'staff');
		$this->dashboardMapper->method('findByUuid')->willReturn($dashboard);
		$this->shareMapper->method('findByDashboardId')->willReturn([]);

		$this->publisher->expects($this->once())->method('publishToGroup')
			->with(Extension::EVENT_PUBLISHED, 'alice', 'staff', 'dash-7', 'Team board', '')
			->willReturn(3);

		$service = $this->dashboardService();
		$service->publishDashboard(uuid: 'dash-7', userId: 'alice');
		// Already published: idempotent, and no second event.
		$service->publishDashboard(uuid: 'dash-7', userId: 'alice');
	}

	public function testAScheduledDashboardThatSurfacesIsAnnouncedByItsOwner(): void {
		$dashboard = $this->dashboard(Dashboard::TYPE_GROUP_SHARED, 'staff');
		$dashboard->setPublicationStatus(Dashboard::STATUS_SCHEDULED);
		$this->dashboardMapper->method('findDueScheduled')->willReturn([$dashboard]);
		$this->shareMapper->method('findByDashboardId')->willReturn([]);

		$this->publisher->expects($this->once())->method('publishToGroup')
			->with(Extension::EVENT_PUBLISHED, 'alice', 'staff');

		$this->assertSame(1, $this->dashboardService()->materialiseScheduledDashboards());
	}

	public function testSavingAGroupDashboardIsAnnouncedAtMostOnceADay(): void {
		$dashboard = $this->dashboard(Dashboard::TYPE_GROUP_SHARED, 'staff');
		$this->dashboardMapper->method('findByUuid')->willReturn($dashboard);
		$this->shareMapper->method('findByDashboardId')->willReturn([]);

		$calls = 0;
		$this->publisher->method('publishToGroup')->willReturnCallback(
			function (string $type) use (&$calls): int {
				$this->assertSame(Extension::EVENT_UPDATED, $type);
				$calls++;
				return 1;
			}
		);

		$service = $this->dashboardService();
		$service->updateGroupShared(actorUserId: 'alice', groupId: 'staff', uuid: 'dash-7', patch: ['name' => 'A']);
		$service->updateGroupShared(actorUserId: 'alice', groupId: 'staff', uuid: 'dash-7', patch: ['name' => 'B']);
		$this->assertSame(1, $calls, 'a second save the same day sends nothing');

		$this->now += 86400;
		$service->updateGroupShared(actorUserId: 'alice', groupId: 'staff', uuid: 'dash-7', patch: ['name' => 'C']);
		$this->assertSame(2, $calls, 'a day later the next save is announced again');
	}

	public function testSavingAPrivateDashboardSendsNothing(): void {
		$this->dashboardMapper->method('find')->willReturn($this->dashboard());
		$this->shareMapper->method('findByDashboardId')->willReturn([]);

		$this->publisher->expects($this->never())->method('publishToRecipients');
		$this->publisher->expects($this->never())->method('publishToGroup');

		$this->dashboardService()->updateDashboard(dashboardId: 7, userId: 'alice', data: ['name' => 'Mine']);
	}

	public function testSavingADashboardSharedWithAUserTellsThatUser(): void {
		$this->dashboardMapper->method('find')->willReturn($this->dashboard());
		$share = new DashboardShare();
		$share->setShareType(DashboardShare::SHARE_TYPE_USER);
		$share->setShareWith('bob');
		$this->shareMapper->method('findByDashboardId')->willReturn([$share]);

		$this->publisher->expects($this->once())->method('publishToRecipients')
			->with(Extension::EVENT_UPDATED, 'alice', 'dash-7', 'Ours', '', ['bob']);

		$this->dashboardService()->updateDashboard(dashboardId: 7, userId: 'alice', data: ['name' => 'Ours']);
	}
}
