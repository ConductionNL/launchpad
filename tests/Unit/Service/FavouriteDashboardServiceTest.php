<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The favourites preference: starring order, visibility, idempotency and
 * cleanup (more-menu-favourites, REQ-SHELL-014, REQ-SHELL-016).
 *
 * @spec openspec/changes/more-menu-favourites/tasks.md#task-11
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\FavouriteDashboardService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\FavouriteDashboardService
 */
class FavouriteDashboardServiceTest extends TestCase {

	/**
	 * The preference store, by "user/key".
	 *
	 * @var array<string, string>
	 */
	private array $prefs = [];

	/**
	 * The preference service double.
	 *
	 * @var IConfig&MockObject
	 */
	private IConfig $config;

	/**
	 * The dashboard service double.
	 *
	 * @var DashboardService&MockObject
	 */
	private DashboardService $dashboards;

	/**
	 * The service under test.
	 *
	 * @var FavouriteDashboardService
	 */
	private FavouriteDashboardService $service;

	/**
	 * A preference store in memory and a service on it.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->prefs  = [];
		$this->config = $this->createMock(originalClassName: IConfig::class);
		$this->config->method('getUserValue')->willReturnCallback(
			fn (string $u, string $a, string $k, $d = '') => ($this->prefs[$u.'/'.$k] ?? $d)
		);
		$this->config->method('setUserValue')->willReturnCallback(
			function (string $u, string $a, string $k, $v): void {
				$this->prefs[$u.'/'.$k] = (string) $v;
			}
		);
		$this->config->method('deleteUserValue')->willReturnCallback(
			function (string $u, string $a, string $k): void {
				unset($this->prefs[$u.'/'.$k]);
			}
		);
		$this->dashboards = $this->createMock(originalClassName: DashboardService::class);
		$this->service    = new FavouriteDashboardService(config: $this->config, dashboardService: $this->dashboards);
	}//end setUp()

	/**
	 * Visible-dashboard entries for these UUIDs.
	 *
	 * @param array<string> $uuids The UUIDs.
	 *
	 * @return array<int, array{dashboard: Dashboard, source: string}>
	 */
	private function entries(array $uuids): array {
		$entries = [];
		foreach ($uuids as $uuid) {
			$dashboard = new Dashboard();
			$dashboard->setUuid($uuid);
			$entries[] = ['dashboard' => $dashboard, 'source' => 'user'];
		}

		return $entries;
	}//end entries()

	/**
	 * Make these UUIDs the dashboards alice can open.
	 *
	 * @param array<string> $uuids The visible UUIDs.
	 *
	 * @return void
	 */
	private function visible(array $uuids): void {
		$this->dashboards->method('getVisibleToUser')->willReturn($this->entries(uuids: $uuids));
	}//end visible()

	/**
	 * The stored list of alice, decoded, or null when unset.
	 *
	 * @return mixed
	 */
	private function stored(): mixed {
		$raw = ($this->prefs['alice/'.FavouriteDashboardService::PREFERENCE_KEY] ?? null);
		if ($raw === null) {
			return null;
		}

		return json_decode(json: $raw, associative: true);
	}//end stored()

	/**
	 * Store a raw preference value for alice.
	 *
	 * @param string $raw The raw value.
	 *
	 * @return void
	 */
	private function store(string $raw): void {
		$this->prefs['alice/'.FavouriteDashboardService::PREFERENCE_KEY] = $raw;
	}//end store()

	/**
	 * Nothing starred is an empty list.
	 *
	 * @return void
	 */
	public function testNothingStarredIsAnEmptyList(): void {
		$this->visible(uuids: ['a', 'b']);
		$this->assertSame(expected: [], actual: $this->service->listFor(userId: 'alice'));
	}//end testNothingStarredIsAnEmptyList()

	/**
	 * Stars keep the order they were given in.
	 *
	 * @return void
	 */
	public function testStarsKeepTheStarringOrder(): void {
		$this->visible(uuids: ['a', 'b', 'c']);
		$this->service->star(userId: 'alice', uuid: 'c');
		$this->assertSame(expected: ['c', 'a'], actual: $this->service->star(userId: 'alice', uuid: 'a'));
		$this->assertSame(expected: ['c', 'a'], actual: $this->stored());
	}//end testStarsKeepTheStarringOrder()

	/**
	 * Starring a favourite again changes nothing.
	 *
	 * @return void
	 */
	public function testStarringTwiceChangesNothing(): void {
		$this->visible(uuids: ['a']);
		$this->service->star(userId: 'alice', uuid: 'a');
		$this->assertSame(expected: ['a'], actual: $this->service->star(userId: 'alice', uuid: 'a'));
	}//end testStarringTwiceChangesNothing()

	/**
	 * Unstarring removes the dashboard, and doing it again changes nothing.
	 *
	 * @return void
	 */
	public function testUnstarRemovesAndIsIdempotent(): void {
		$this->visible(uuids: ['a', 'b']);
		$this->service->star(userId: 'alice', uuid: 'a');
		$this->service->star(userId: 'alice', uuid: 'b');
		$this->assertSame(expected: ['b'], actual: $this->service->unstar(userId: 'alice', uuid: 'a'));
		$this->assertSame(expected: ['b'], actual: $this->service->unstar(userId: 'alice', uuid: 'a'));
	}//end testUnstarRemovesAndIsIdempotent()

	/**
	 * Unstarring the last favourite removes the preference.
	 *
	 * @return void
	 */
	public function testUnstarringTheLastRemovesThePreference(): void {
		$this->visible(uuids: ['a']);
		$this->service->star(userId: 'alice', uuid: 'a');
		$this->assertSame(expected: [], actual: $this->service->unstar(userId: 'alice', uuid: 'a'));
		$this->assertNull(actual: $this->stored());
	}//end testUnstarringTheLastRemovesThePreference()

	/**
	 * A dashboard alice cannot open cannot be starred.
	 *
	 * @return void
	 */
	public function testAnInvisibleDashboardCannotBeStarred(): void {
		$this->visible(uuids: ['a']);
		$this->expectException(exception: DoesNotExistException::class);
		$this->service->star(userId: 'alice', uuid: 'secret');
	}//end testAnInvisibleDashboardCannotBeStarred()

	/**
	 * A dashboard alice cannot open cannot be unstarred either.
	 *
	 * @return void
	 */
	public function testAnInvisibleDashboardCannotBeUnstarred(): void {
		$this->visible(uuids: ['a']);
		$this->expectException(exception: DoesNotExistException::class);
		$this->service->unstar(userId: 'alice', uuid: 'secret');
	}//end testAnInvisibleDashboardCannotBeUnstarred()

	/**
	 * A gone dashboard is dropped on read and the preference rewritten, with
	 * the visible list the caller passed and no extra read.
	 *
	 * @return void
	 */
	public function testAGoneDashboardIsDroppedOnReadAndThePreferenceRewritten(): void {
		$this->store(raw: json_encode(value: ['a', 'gone', 'b']));
		$this->dashboards->expects($this->never())->method('getVisibleToUser');
		$this->assertSame(expected: ['a', 'b'], actual: $this->service->listFor(userId: 'alice', visibleUuids: ['b', 'a']));
		$this->assertSame(expected: ['a', 'b'], actual: $this->stored());
	}//end testAGoneDashboardIsDroppedOnReadAndThePreferenceRewritten()

	/**
	 * A list that needs no cleanup is not written.
	 *
	 * @return void
	 */
	public function testAListThatNeedsNoCleanupIsNotRewritten(): void {
		$this->store(raw: json_encode(value: ['a']));
		$this->config->expects($this->never())->method('setUserValue');
		$this->assertSame(expected: ['a'], actual: $this->service->listFor(userId: 'alice', visibleUuids: ['a']));
	}//end testAListThatNeedsNoCleanupIsNotRewritten()

	/**
	 * A malformed preference reads as no favourites; junk entries are dropped.
	 *
	 * @return void
	 */
	public function testAMalformedPreferenceReadsAsNoFavourites(): void {
		$this->store(raw: '{not json');
		$this->assertSame(expected: [], actual: $this->service->listFor(userId: 'alice', visibleUuids: ['a']));
		$this->store(raw: json_encode(value: [1, '', 'a', 'a']));
		$this->assertSame(expected: ['a'], actual: $this->service->listFor(userId: 'alice', visibleUuids: ['a']));
	}//end testAMalformedPreferenceReadsAsNoFavourites()

	/**
	 * The page's list is checked against the visible entries the page read; no
	 * session means no list.
	 *
	 * @return void
	 */
	public function testForPageChecksTheVisibleEntriesAndNeedsAUser(): void {
		$this->store(raw: json_encode(value: ['b', 'gone']));
		$this->dashboards->expects($this->never())->method('getVisibleToUser');
		$this->assertSame(
			expected: ['b'],
			actual: $this->service->forPage(userId: 'alice', visible: $this->entries(uuids: ['a', 'b']))
		);
		$this->assertNull(actual: $this->service->forPage(userId: '', visible: []));
	}//end testForPageChecksTheVisibleEntriesAndNeedsAUser()
}//end class
