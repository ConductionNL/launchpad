<?php

/**
 * ColleagueActivityService Test
 *
 * REQ-DWMS-007: colleague activity filtered to what the reader may see, totals included.
 *
 * @category  Test
 * @package   Unit\Service\ColleagueActivity
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace Unit\Service\ColleagueActivity;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Service\ColleagueActivity\ColleagueActivityReader;
use OCA\LaunchPad\Service\ColleagueActivity\ColleagueActivityService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUserManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColleagueActivityService::class)]
#[UsesClass(Dashboard::class)]
class ColleagueActivityServiceTest extends TestCase {

	private const NOW = 1_800_000_000;

	private ColleagueActivityReader&MockObject $reader;
	private DashboardMapper&MockObject $dashboards;
	private PermissionService&MockObject $permissions;

	protected function setUp(): void {
		$this->reader      = $this->createMock(ColleagueActivityReader::class);
		$this->dashboards  = $this->createMock(DashboardMapper::class);
		$this->permissions = $this->createMock(PermissionService::class);
	}

	private function service(): ColleagueActivityService {
		$users = $this->createMock(IUserManager::class);
		$users->method('getDisplayName')->willReturnCallback(static fn (string $uid): ?string => match ($uid) {
			'bob' => 'Bob Bakker',
			default => null,
		});
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getTime')->willReturn(self::NOW);

		return new ColleagueActivityService($this->reader, $this->dashboards, $this->permissions, $users, $time);
	}

	private static function row(int $id, string $actor, string $type, string $objectType='', string $uuid='', string $dashboard=''): array {
		return [
			'activity_id'   => $id,
			'timestamp'     => (self::NOW - $id),
			'type'          => $type,
			'user'          => $actor,
			'app'           => ($objectType === 'launchpad_dashboard' ? 'launchpad' : 'files'),
			'subject'       => $type,
			'subjectparams' => json_encode(['dashboard' => $dashboard]),
			'object_type'   => $objectType,
			'object_name'   => $uuid,
			'link'          => '/x/' . $id,
		];
	}

	private static function dashboard(int $id): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId($id);
		return $dashboard;
	}

	public function testAnEditOnAHiddenDashboardIsAbsentFromTheListAndFromEveryCount(): void {
		$this->reader->method('rows')->willReturn([
			self::row(1, 'bob', 'dashboard_updated', 'launchpad_dashboard', 'uuid-a', 'Dashboard A'),
			self::row(2, 'bob', 'dashboard_updated', 'launchpad_dashboard', 'uuid-b', 'Dashboard B'),
			self::row(3, 'carol', 'file_changed'),
		]);
		$this->dashboards->method('findByUuid')->willReturnCallback(static fn (string $uuid): Dashboard => match ($uuid) {
			'uuid-a' => self::dashboard(10),
			'uuid-b' => self::dashboard(20),
		});
		$this->permissions->method('canViewDashboard')->willReturnCallback(
			static fn (string $userId, int $dashboardId): bool => ($userId === 'anna' && $dashboardId === 20)
		);

		$result = $this->service()->recent('anna');

		$this->assertSame([2, 3], array_column($result['entries'], 'id'));
		$this->assertSame(2, $result['total']);
		$this->assertSame(['dashboard_updated' => 1, 'file_changed' => 1], $result['counts']);
		$this->assertSame(2, $result['actors']);
		$this->assertNotContains('Dashboard A', array_column($result['entries'], 'title'));
	}

	public function testTotalsCountEveryVisibleEntryNotOnlyTheListedOnes(): void {
		$this->reader->method('rows')->willReturn([
			self::row(1, 'bob', 'file_changed'),
			self::row(2, 'bob', 'file_changed'),
			self::row(3, 'carol', 'calendar_event'),
		]);

		$result = $this->service()->recent('anna', 1);

		$this->assertCount(1, $result['entries']);
		$this->assertSame(3, $result['total']);
		$this->assertSame(['calendar_event' => 1, 'file_changed' => 2], $result['counts']);
	}

	public function testAnEventOnADashboardThatIsGoneIsAbsent(): void {
		$this->reader->method('rows')->willReturn([self::row(1, 'bob', 'dashboard_deleted', 'launchpad_dashboard', 'uuid-gone')]);
		$this->dashboards->method('findByUuid')->willThrowException(new DoesNotExistException('gone'));
		$this->permissions->expects($this->never())->method('canViewDashboard');

		$result = $this->service()->recent('anna');

		$this->assertSame([], $result['entries']);
		$this->assertSame(0, $result['total']);
		$this->assertSame([], $result['counts']);
	}

	public function testOneDashboardIsCheckedOnceHoweverManyEventsNameIt(): void {
		$this->reader->method('rows')->willReturn([
			self::row(1, 'bob', 'dashboard_updated', 'launchpad_dashboard', 'uuid-a'),
			self::row(2, 'carol', 'dashboard_updated', 'launchpad_dashboard', 'uuid-a'),
		]);
		$this->dashboards->expects($this->once())->method('findByUuid')->with('uuid-a')->willReturn(self::dashboard(10));
		$this->permissions->expects($this->once())->method('canViewDashboard')->with('anna', 10)->willReturn(true);

		$this->assertSame(2, $this->service()->recent('anna')['total']);
	}

	public function testAnEntryCarriesTheActorItsNameAndTheDashboardTitle(): void {
		$this->reader->method('rows')->willReturn([
			self::row(4, 'bob', 'dashboard_shared', 'launchpad_dashboard', 'uuid-a', 'Team'),
			self::row(5, 'dave', ''),
		]);
		$this->dashboards->method('findByUuid')->willReturn(self::dashboard(10));
		$this->permissions->method('canViewDashboard')->willReturn(true);

		$entries = $this->service()->recent('anna')['entries'];

		$this->assertSame(
			[
				'id'               => 4,
				'actor'            => 'bob',
				'actorDisplayName' => 'Bob Bakker',
				'app'              => 'launchpad',
				'type'             => 'dashboard_shared',
				'subject'          => 'dashboard_shared',
				'title'            => 'Team',
				'link'             => '/x/4',
				'timestamp'        => (self::NOW - 4),
			],
			$entries[0]
		);
		$this->assertSame('dave', $entries[1]['actorDisplayName']);
		$this->assertSame('other', $entries[1]['type']);
	}

	public function testThePeriodAndTheLimitAreBoundedAndThePeriodSetsTheFirstSecond(): void {
		$this->reader->expects($this->once())->method('rows')
			->with('anna', (self::NOW - (90 * 86400)))
			->willReturn([]);

		$result = $this->service()->recent('anna', 500, 400);

		$this->assertSame(90, $result['days']);
		$this->assertTrue($result['available']);
	}

	public function testWithoutTheActivityAppTheWidgetSaysSo(): void {
		$this->reader->method('rows')->willReturn(null);

		$result = $this->service()->recent('anna', 0, 0);

		$this->assertFalse($result['available']);
		$this->assertSame(1, $result['days']);
		$this->assertSame(0, $result['total']);
	}
}
