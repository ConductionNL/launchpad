<?php

/**
 * DashboardService steps golden test
 *
 * Pins, on the code before its split, who may delete a group-shared
 * dashboard and what the delete does, which dashboards the publication
 * state lets a viewer see (draft, scheduled, due, taken down, legacy
 * rows), the order in which group-shared dashboards are picked for a
 * viewer, the footer-mode validation and the plain field updates.
 * Private steps are reached through reflection so every branch is pinned
 * directly; times are relative to now, never pinned to a calendar date.
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

use DateTime;
use Exception;
use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardBuilders;
use OCA\LaunchPad\Service\DashboardFactory;
use OCA\LaunchPad\Service\DashboardOutlets;
use OCA\LaunchPad\Service\DashboardPlatform;
use OCA\LaunchPad\Service\DashboardResolver;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardStores;
use OCA\LaunchPad\Service\DashboardStructure;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\FooterService;
use OCA\LaunchPad\Service\PersonalLayerService;
use OCA\LaunchPad\Service\TemplateService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

class DashboardServiceStepsGoldenTest extends TestCase {
	private DashboardMapper $dashboardMapper;
	private WidgetPlacementMapper $placementMapper;
	private IGroupManager $groupManager;
	private ?IEventDispatcher $dispatcher;

	protected function setUp(): void {
		$this->dashboardMapper = $this->createMock(DashboardMapper::class);
		$this->placementMapper = $this->createMock(WidgetPlacementMapper::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
	}

	private function service(): DashboardService {
		return new DashboardService(
			new DashboardStores($this->dashboardMapper, $this->placementMapper, $this->createMock(AdminSettingMapper::class)),
			new DashboardBuilders(
				$this->createMock(TemplateService::class),
				$this->createMock(DashboardFactory::class),
				$this->createMock(DashboardResolver::class),
				$this->createMock(AdminTemplateService::class),
			),
			new DashboardStructure(
				$this->createMock(DashboardTreeService::class),
				$this->createMock(PersonalLayerService::class),
				null,
				$this->createMock(FooterService::class),
			),
			new DashboardPlatform($this->groupManager, $this->createMock(IDBConnection::class), $this->createMock(IConfig::class), $this->createMock(IFactory::class)),
			new DashboardOutlets($this->createMock(LoggerInterface::class), $this->dispatcher),
		);
	}

	private function call(string $method, array $args): mixed {
		$reflection = new ReflectionMethod(DashboardService::class, $method);

		return $reflection->invokeArgs($this->service(), $args);
	}

	private function groupDashboard(string $groupId, string $uuid = 'g-1', int $isDefault = 0): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId(70);
		$dashboard->setUuid($uuid);
		$dashboard->setType(Dashboard::TYPE_GROUP_SHARED);
		$dashboard->setGroupId($groupId);
		$dashboard->setIsDefault($isDefault);

		return $dashboard;
	}

	private function stamp(string $modify): string {
		return (new DateTime())->modify($modify)->format('Y-m-d H:i:s');
	}

	public function testNonAdminCannotDeleteAndNothingIsRead(): void {
		$this->groupManager->method('isAdmin')->willReturn(false);
		$this->dashboardMapper->expects($this->never())->method('findByUuid');
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Forbidden: admin only');

		$this->service()->deleteGroupShared(actorUserId: 'bob', groupId: 'sales', uuid: 'g-1');
	}

	public function testGroupMismatchIsNotFound(): void {
		$this->groupManager->method('isAdmin')->willReturn(true);
		$this->dashboardMapper->method('findByUuid')->willReturn($this->groupDashboard('other'));
		$this->dashboardMapper->expects($this->never())->method('delete');
		$this->expectException(DoesNotExistException::class);

		$this->service()->deleteGroupShared(actorUserId: 'admin', groupId: 'sales', uuid: 'g-1');
	}

	public function testLastDashboardOfAGroupIsKept(): void {
		$this->groupManager->method('isAdmin')->willReturn(true);
		$this->dashboardMapper->method('findByUuid')->willReturn($this->groupDashboard('sales'));
		$this->dashboardMapper->method('countByGroup')->willReturn(1);
		$this->placementMapper->expects($this->never())->method('deleteByDashboardId');
		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Cannot delete the only dashboard in the group');

		$this->service()->deleteGroupShared(actorUserId: 'admin', groupId: 'sales', uuid: 'g-1');
	}

	public function testDefaultGroupDeleteSkipsTheCountAndCascades(): void {
		$this->groupManager->method('isAdmin')->willReturn(true);
		$dashboard = $this->groupDashboard(Dashboard::DEFAULT_GROUP_ID);
		$this->dashboardMapper->method('findByUuid')->willReturn($dashboard);
		$this->dashboardMapper->expects($this->never())->method('countByGroup');
		$order = [];
		$this->placementMapper->method('deleteByDashboardId')->willReturnCallback(static function (int $dashboardId) use (&$order) {
			$order[] = 'placements:' . $dashboardId;
			return 0;
		});
		$this->dashboardMapper->method('delete')->willReturnCallback(static function ($entity) use (&$order) {
			$order[] = 'dashboard';
			return $entity;
		});
		$this->dispatcher->expects($this->once())->method('dispatchTyped')->willReturnCallback(static function ($event) use (&$order): void {
			$order[] = 'event:' . $event->getDashboardUuid() . ':' . $event->getOwnerUserId() . ':' . $event->getType() . ':' . $event->getDashboardId();
		});

		$this->service()->deleteGroupShared(actorUserId: 'admin', groupId: Dashboard::DEFAULT_GROUP_ID, uuid: 'g-1');

		$this->assertSame(['placements:70', 'dashboard', 'event:g-1:admin:group_shared:70'], $order);
	}

	public function testDeleteWithSiblingsDispatchesNoEventWithoutDispatcherOrUuid(): void {
		$this->groupManager->method('isAdmin')->willReturn(true);
		$this->dashboardMapper->method('findByUuid')->willReturn($this->groupDashboard('sales', ''));
		$this->dashboardMapper->method('countByGroup')->willReturn(2);
		$this->dashboardMapper->expects($this->once())->method('delete');
		$this->dispatcher->expects($this->never())->method('dispatchTyped');

		$this->service()->deleteGroupShared(actorUserId: 'admin', groupId: 'sales', uuid: '');

		$this->dispatcher = null;
		$this->dashboardMapper = $this->createMock(DashboardMapper::class);
		$this->dashboardMapper->method('findByUuid')->willReturn($this->groupDashboard('sales'));
		$this->dashboardMapper->method('countByGroup')->willReturn(2);
		$this->dashboardMapper->expects($this->once())->method('delete');
		$this->service()->deleteGroupShared(actorUserId: 'admin', groupId: 'sales', uuid: 'g-1');
	}

	/**
	 * Publication states and their effective status (and stored status after).
	 *
	 * @return array<string,array{0:string,1:?string,2:?string,3:string,4:string}>
	 */
	public static function publicationStates(): array {
		return [
			'legacy empty'       => ['', null, null, 'published', ''],
			'draft'              => ['draft', '-1 day', null, 'draft', 'draft'],
			'scheduled due'      => ['scheduled', '-1 minute', null, 'published', 'published'],
			'scheduled future'   => ['scheduled', '+1 day', null, 'scheduled', 'scheduled'],
			'scheduled no time'  => ['scheduled', '', null, 'scheduled', 'scheduled'],
			'scheduled bad time' => ['scheduled', 'not a time', null, 'scheduled', 'scheduled'],
			'published live'     => ['published', null, '+1 day', 'published', 'published'],
			'published down'     => ['published', null, '-1 minute', 'draft', 'draft'],
			'published bad down' => ['published', null, 'nope', 'published', 'published'],
			'due then down'      => ['scheduled', '-2 days', '-1 day', 'draft', 'draft'],
			'legacy taken down'  => ['', null, '-1 day', 'draft', 'draft'],
		];
	}

	/**
	 * @dataProvider publicationStates
	 */
	public function testEffectivePublicationStatus(string $stored, ?string $publishAt, ?string $unpublishAt, string $effective, string $after): void {
		$dashboard = new Dashboard();
		$dashboard->setPublicationStatus($stored);
		$dashboard->setPublishAt($this->timeOrRaw($publishAt));
		$dashboard->setUnpublishAt($this->timeOrRaw($unpublishAt));

		$this->assertSame($effective, $this->call('effectivePublicationStatus', [$dashboard, new DateTime()]));
		$this->assertSame($after, $dashboard->getPublicationStatus());
	}

	private function timeOrRaw(?string $value): ?string {
		if ($value === null || $value === '' || preg_match('/^[+-]\d/', $value) !== 1) {
			return $value;
		}

		return $this->stamp($value);
	}

	public function testPublicationFilterKeepsPublishedAndOwnOrAdminDrafts(): void {
		$entries = [];
		foreach ([['published', 'bob'], ['draft', 'alice'], ['draft', 'bob'], ['scheduled', null], ['', 'bob']] as $i => [$status, $owner]) {
			$dashboard = new Dashboard();
			$dashboard->setPublicationStatus($status);
			$dashboard->setPublishAt($this->stamp('+1 day'));
			$dashboard->setUserId($owner);
			$entries[] = ['dashboard' => $dashboard, 'source' => 'n' . $i];
		}

		$asAlice = $this->call('filterByPublicationState', [$entries, 'alice', false]);
		$asAdmin = $this->call('filterByPublicationState', [$entries, 'carol', true]);
		$asNobody = $this->call('filterByPublicationState', [$entries, '', false]);

		$this->assertSame(['n0', 'n1', 'n4'], array_column($asAlice, 'source'));
		$this->assertSame(['n0', 'n1', 'n2', 'n3', 'n4'], array_column($asAdmin, 'source'));
		$this->assertSame(['n0', 'n4'], array_column($asNobody, 'source'));
	}

	/**
	 * Scheduled materialisation cases: stored status, publishAt, status after.
	 *
	 * @return array<string,array{0:string,1:?string,2:string}>
	 */
	public static function schedules(): array {
		return [
			'not scheduled' => ['draft', '-1 day', 'draft'],
			'no time'       => ['scheduled', null, 'scheduled'],
			'empty time'    => ['scheduled', '', 'scheduled'],
			'due'           => ['scheduled', '-1 minute', 'published'],
			'future'        => ['scheduled', '+1 day', 'scheduled'],
			'malformed'     => ['scheduled', 'never', 'scheduled'],
		];
	}

	/**
	 * @dataProvider schedules
	 */
	public function testMaterialiseDueSchedule(string $stored, ?string $publishAt, string $after): void {
		$dashboard = new Dashboard();
		$dashboard->setPublicationStatus($stored);
		$dashboard->setPublishAt($this->timeOrRaw($publishAt));

		$this->call('materialiseDueSchedule', [$dashboard]);

		$this->assertSame($after, $dashboard->getPublicationStatus());
	}

	/**
	 * Visible sets and the uuid picked for a viewer in group `sales`.
	 *
	 * @return array<string,array{0:list<array{0:string,1:string,2:string,3:int,4:string}>,1:string,2:?string}>
	 */
	public static function groupSharedPicks(): array {
		$primaryDefault = ['group', 'sales', Dashboard::TYPE_GROUP_SHARED, 1, 'p-def'];
		$primaryFirst = ['group', 'sales', Dashboard::TYPE_GROUP_SHARED, 0, 'p-first'];
		$defaultDefault = ['default', 'default', Dashboard::TYPE_GROUP_SHARED, 1, 'd-def'];
		$defaultFirst = ['default', 'default', Dashboard::TYPE_GROUP_SHARED, 0, 'd-first'];
		$personal = ['group', 'sales', 'user', 1, 'personal'];
		$wrongSource = ['user', 'sales', Dashboard::TYPE_GROUP_SHARED, 1, 'wrong-source'];
		$otherGroup = ['group', 'hr', Dashboard::TYPE_GROUP_SHARED, 1, 'other-group'];
		return [
			'primary default first'    => [[$defaultFirst, $defaultDefault, $primaryFirst, $primaryDefault], 'sales', 'p-def'],
			'default default next'     => [[$defaultFirst, $primaryFirst, $defaultDefault], 'sales', 'd-def'],
			'primary first next'       => [[$defaultFirst, $primaryFirst], 'sales', 'p-first'],
			'default first last'       => [[$personal, $wrongSource, $otherGroup, $defaultFirst], 'sales', 'd-first'],
			'nothing'                  => [[$personal, $wrongSource, $otherGroup], 'sales', null],
			'default group only'       => [[$primaryDefault, $defaultFirst], 'default', 'd-first'],
		];
	}

	/**
	 * @dataProvider groupSharedPicks
	 */
	public function testGroupSharedPickOrder(array $rows, string $groupId, ?string $expected): void {
		$visible = [];
		foreach ($rows as [$source, $group, $type, $isDefault, $uuid]) {
			$dashboard = $this->groupDashboard($group, $uuid, $isDefault);
			$dashboard->setType($type);
			$visible[] = ['dashboard' => $dashboard, 'source' => $source];
		}

		$result = $this->call('resolveGroupSharedDashboard', [$visible, $groupId]);

		$this->assertSame($expected, $result === null ? null : $result['dashboard']->getUuid());
	}

	/**
	 * Footer modes: dashboard mode, patch, provided flag, result or error.
	 *
	 * @return array<string,array{0:?string,1:array<string,mixed>,2:bool,3:string}>
	 */
	public static function footerModes(): array {
		return [
			'provided custom'   => ['hidden', ['dashboardFooterMode' => 'custom'], true, 'custom'],
			'kept stored'       => ['hidden', ['dashboardFooterMode' => 'custom'], false, 'hidden'],
			'stored empty'      => ['', [], false, 'inherit'],
			'provided empty'    => ['hidden', ['dashboardFooterMode' => ''], true, 'inherit'],
			'provided null'     => ['hidden', ['dashboardFooterMode' => null], true, 'inherit'],
			'unknown'           => ['hidden', ['dashboardFooterMode' => 'loud'], true, 'error'],
			'not a string'      => ['hidden', ['dashboardFooterMode' => 3], true, 'error'],
			'stored unknown'    => ['loud', [], false, 'error'],
		];
	}

	/**
	 * @dataProvider footerModes
	 */
	public function testFooterMode(?string $stored, array $data, bool $provided, string $expected): void {
		$dashboard = new Dashboard();
		$dashboard->setDashboardFooterMode($stored);
		if ($expected === 'error') {
			$this->expectException(InvalidArgumentException::class);
			$this->expectExceptionMessage('dashboardFooterMode must be one of: ' . implode(', ', Dashboard::FOOTER_MODES));
		}

		$this->assertSame($expected, $this->call('resolveFooterMode', [$dashboard, $data, $provided]));
	}

	public function testPlainFieldUpdates(): void {
		$dashboard = new Dashboard();
		$dashboard->setName('Old');
		$dashboard->setDescription('Old description');
		$dashboard->setIcon('old-icon');
		$dashboard->setGridColumns(12);
		$this->placementMapper->expects($this->once())->method('updatePositions')->with([['id' => 1, 'x' => 2]]);

		$this->call('applyDashboardUpdates', [$dashboard, [
			'name' => 'New',
			'description' => null,
			'icon' => 5,
			'gridColumns' => 6,
			'placements' => [['id' => 1, 'x' => 2]],
		]]);

		$this->assertSame(['New', 'Old description', null, 6], [$dashboard->getName(), $dashboard->getDescription(), $dashboard->getIcon(), $dashboard->getGridColumns()]);
		$this->assertNotNull($dashboard->getUpdatedAt());
	}

	public function testIconStringAndAbsentKeysAndScalarPlacements(): void {
		$dashboard = new Dashboard();
		$dashboard->setName('Keep');
		$dashboard->setIcon('old-icon');
		$this->placementMapper->expects($this->never())->method('updatePositions');

		$this->call('applyDashboardUpdates', [$dashboard, ['icon' => 'star', 'placements' => 'x', 'name' => null]]);

		$this->assertSame(['Keep', 'star'], [$dashboard->getName(), $dashboard->getIcon()]);

		$this->call('applyDashboardUpdates', [$dashboard, []]);

		$this->assertSame('star', $dashboard->getIcon());
	}
}//end class
