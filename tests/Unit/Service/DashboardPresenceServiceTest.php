<?php

/**
 * Unit tests for who is editing a dashboard (building-edit-a-dashboard-together,
 * task 1.1): a second editor sees the first and since when, a renewal keeps
 * the start, a silent editor drops off, and leaving takes one off.
 *
 * The cache is an in-memory double that keeps what set() was given.
 *
 * @category Test
 * @package  Unit\Service
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Service\DashboardPresenceService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\DashboardPresenceService
 * @uses   \OCA\LaunchPad\Exception\ForbiddenException
 * @uses   \OCA\LaunchPad\Exception\ResourceException
 */
class DashboardPresenceServiceTest extends TestCase {

	/**
	 * What the cache holds, by key.
	 *
	 * @var array<string,mixed>
	 */
	private array $stored = [];

	/**
	 * The seconds the clock reads.
	 *
	 * @var int
	 */
	private int $now = 1789378920;

	/**
	 * Who may edit dashboard 7.
	 *
	 * @var list<string>
	 */
	private array $editorsAllowed = ['marieke', 'sanne'];

	/**
	 * The service over the doubles.
	 *
	 * @return DashboardPresenceService
	 */
	private function service(): DashboardPresenceService {
		$cache = $this->createMock(ICache::class);
		$cache->method('get')->willReturnCallback(fn (string $key) => ($this->stored[$key] ?? null));
		$cache->method('set')->willReturnCallback(function (string $key, mixed $value, int $ttl): bool {
			self::assertSame(DashboardPresenceService::PRESENCE_TTL, $ttl);
			$this->stored[$key] = $value;
			return true;
		});
		$cache->method('remove')->willReturnCallback(function (string $key): bool {
			unset($this->stored[$key]);
			return true;
		});
		$factory = $this->createMock(ICacheFactory::class);
		$factory->method('createDistributed')->with('launchpad_presence_')->willReturn($cache);

		$dashboard = new Dashboard();
		$dashboard->setId(7);
		$mapper = $this->createMock(DashboardMapper::class);
		$mapper->method('findByUuid')->willReturnCallback(static function (string $uuid) use ($dashboard): Dashboard {
			if ($uuid !== 'dash-7') {
				throw new DoesNotExistException('none');
			}

			return $dashboard;
		});

		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('canEditDashboard')->willReturnCallback(
			fn (string $userId, int $dashboardId): bool => $dashboardId === 7 && in_array($userId, $this->editorsAllowed, true)
		);

		$users = $this->createMock(IUserManager::class);
		$users->method('get')->willReturnCallback(function (string $uid): ?IUser {
			$names = ['marieke' => 'Marieke Jansen', 'sanne' => 'Sanne de Vries', 'nameless' => ''];
			if (array_key_exists($uid, $names) === false) {
				return null;
			}

			$user = $this->createMock(IUser::class);
			$user->method('getDisplayName')->willReturn($names[$uid]);
			return $user;
		});

		$clock = $this->createMock(ITimeFactory::class);
		$clock->method('getTime')->willReturnCallback(fn (): int => $this->now);

		return new DashboardPresenceService($factory, $mapper, $permissions, $users, $clock);
	}//end service()

	/**
	 * The first editor sees nobody; the second sees the first, with the moment she started.
	 *
	 * @return void
	 */
	public function testASecondEditorSeesTheFirstAndSinceWhen(): void {
		$service = $this->service();

		self::assertSame([], $service->join(dashboardUuid: 'dash-7', userId: 'marieke'));
		$started = date(DATE_ATOM, $this->now);

		$this->now += 60;
		self::assertSame(
			[['displayName' => 'Marieke Jansen', 'since' => $started]],
			$service->join(dashboardUuid: 'dash-7', userId: 'sanne')
		);
		self::assertSame(
			[['displayName' => 'Sanne de Vries', 'since' => date(DATE_ATOM, $this->now)]],
			$service->join(dashboardUuid: 'dash-7', userId: 'marieke')
		);
	}//end testASecondEditorSeesTheFirstAndSinceWhen()

	/**
	 * A renewal keeps the start; colleagues come longest first.
	 *
	 * @return void
	 */
	public function testARenewalKeepsTheStartAndColleaguesComeLongestFirst(): void {
		$this->editorsAllowed[] = 'nameless';
		$service = $this->service();
		$service->join(dashboardUuid: 'dash-7', userId: 'sanne');
		$sanneSince = date(DATE_ATOM, $this->now);
		$this->now += 30;
		$service->join(dashboardUuid: 'dash-7', userId: 'nameless');
		$this->now += 30;
		$service->join(dashboardUuid: 'dash-7', userId: 'sanne');

		self::assertSame(
			[
				['displayName' => 'Sanne de Vries', 'since' => $sanneSince],
				['displayName' => 'nameless', 'since' => date(DATE_ATOM, $this->now - 30)],
			],
			$service->join(dashboardUuid: 'dash-7', userId: 'marieke')
		);
	}//end testARenewalKeepsTheStartAndColleaguesComeLongestFirst()

	/**
	 * An editor without a heartbeat for longer than the TTL is no longer listed.
	 *
	 * @return void
	 */
	public function testASilentEditorDropsOff(): void {
		$service = $this->service();
		$service->join(dashboardUuid: 'dash-7', userId: 'marieke');

		$this->now += DashboardPresenceService::PRESENCE_TTL;
		self::assertCount(1, $service->join(dashboardUuid: 'dash-7', userId: 'sanne'));

		$this->now += DashboardPresenceService::PRESENCE_TTL + 1;
		self::assertSame([], $service->join(dashboardUuid: 'dash-7', userId: 'sanne'));
		self::assertSame(['sanne'], array_keys($this->stored['dash-7']));
	}//end testASilentEditorDropsOff()

	/**
	 * Leaving takes only the caller off, and the last one out clears the entry.
	 *
	 * @return void
	 */
	public function testLeavingTakesTheCallerOff(): void {
		$service = $this->service();
		$service->join(dashboardUuid: 'dash-7', userId: 'marieke');
		$service->join(dashboardUuid: 'dash-7', userId: 'sanne');

		$service->leave(dashboardUuid: 'dash-7', userId: 'marieke');
		self::assertSame(['sanne'], array_keys($this->stored['dash-7']));

		$service->leave(dashboardUuid: 'dash-7', userId: 'marieke');
		$service->leave(dashboardUuid: 'dash-7', userId: 'sanne');
		self::assertArrayNotHasKey('dash-7', $this->stored);
	}//end testLeavingTakesTheCallerOff()

	/**
	 * A person without edit rights is refused and not listed; an unknown dashboard throws.
	 *
	 * @return void
	 */
	public function testOnlyAnEditorIsListed(): void {
		$service = $this->service();
		try {
			$service->join(dashboardUuid: 'dash-7', userId: 'viewer');
			self::fail('A viewer was listed as an editor.');
		} catch (ForbiddenException $e) {
			self::assertSame('You may not edit this dashboard', $e->getMessage());
		}

		self::assertSame([], $this->stored);

		$this->expectException(DoesNotExistException::class);
		$service->join(dashboardUuid: 'dash-unknown', userId: 'marieke');
	}//end testOnlyAnEditorIsListed()

	/**
	 * A malformed cache entry is ignored, not trusted.
	 *
	 * @return void
	 */
	public function testAMalformedEntryIsIgnored(): void {
		$this->stored['dash-7'] = ['ghost' => 'x', 'old' => ['displayName' => 'Old', 'since' => 's']];
		$service = $this->service();

		self::assertSame([], $service->join(dashboardUuid: 'dash-7', userId: 'marieke'));
		self::assertSame(['marieke'], array_keys($this->stored['dash-7']));
	}//end testAMalformedEntryIsIgnored()
}//end class
