<?php

/**
 * Unit tests for FavouriteDashboardService (more-menu-favourites, REQ-SHELL-014, REQ-SHELL-016).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
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
 * The favourites preference: starring order, visibility, idempotency and cleanup.
 */
class FavouriteDashboardServiceTest extends TestCase {
	/** @var array<string, string> The preference store, by key. */
	private array $prefs = [];

	/** @var IConfig&MockObject */
	private $config;

	/** @var DashboardService&MockObject */
	private $dashboards;

	private FavouriteDashboardService $service;

	protected function setUp(): void {
		$this->prefs  = [];
		$this->config = $this->createMock(IConfig::class);
		$this->config->method('getUserValue')->willReturnCallback(
			fn (string $u, string $a, string $k, $d = '') => $this->prefs[$u.'/'.$k] ?? $d
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
		$this->dashboards = $this->createMock(DashboardService::class);
		$this->service    = new FavouriteDashboardService($this->config, $this->dashboards);
	}

	/**
	 * Make these UUIDs the dashboards alice can open.
	 *
	 * @param string ...$uuids The visible UUIDs.
	 */
	private function visible(string ...$uuids): void {
		$entries = [];
		foreach ($uuids as $uuid) {
			$dashboard = new Dashboard();
			$dashboard->setUuid($uuid);
			$entries[] = ['dashboard' => $dashboard, 'source' => 'user'];
		}
		$this->dashboards->method('getVisibleToUser')->willReturn($entries);
	}

	private function stored(): mixed {
		$raw = $this->prefs['alice/'.FavouriteDashboardService::PREFERENCE_KEY] ?? null;
		return $raw === null ? null : json_decode($raw, true);
	}

	public function testNothingStarredIsAnEmptyList(): void {
		$this->visible('a', 'b');
		$this->assertSame([], $this->service->listFor('alice'));
	}

	public function testStarsKeepTheStarringOrder(): void {
		$this->visible('a', 'b', 'c');
		$this->service->star('alice', 'c');
		$this->assertSame(['c', 'a'], $this->service->star('alice', 'a'));
		$this->assertSame(['c', 'a'], $this->stored());
	}

	public function testStarringTwiceChangesNothing(): void {
		$this->visible('a');
		$this->service->star('alice', 'a');
		$this->assertSame(['a'], $this->service->star('alice', 'a'));
	}

	public function testUnstarRemovesAndIsIdempotent(): void {
		$this->visible('a', 'b');
		$this->service->star('alice', 'a');
		$this->service->star('alice', 'b');
		$this->assertSame(['b'], $this->service->unstar('alice', 'a'));
		$this->assertSame(['b'], $this->service->unstar('alice', 'a'));
	}

	public function testUnstarringTheLastRemovesThePreference(): void {
		$this->visible('a');
		$this->service->star('alice', 'a');
		$this->assertSame([], $this->service->unstar('alice', 'a'));
		$this->assertNull($this->stored());
	}

	public function testAnInvisibleDashboardCannotBeStarred(): void {
		$this->visible('a');
		$this->expectException(DoesNotExistException::class);
		$this->service->star('alice', 'secret');
	}

	public function testAnInvisibleDashboardCannotBeUnstarred(): void {
		$this->visible('a');
		$this->expectException(DoesNotExistException::class);
		$this->service->unstar('alice', 'secret');
	}

	public function testAGoneDashboardIsDroppedOnReadAndThePreferenceRewritten(): void {
		$this->prefs['alice/'.FavouriteDashboardService::PREFERENCE_KEY] = json_encode(['a', 'gone', 'b']);
		$this->dashboards->expects($this->never())->method('getVisibleToUser');
		$this->assertSame(['a', 'b'], $this->service->listFor('alice', ['b', 'a']));
		$this->assertSame(['a', 'b'], $this->stored());
	}

	public function testAListThatNeedsNoCleanupIsNotRewritten(): void {
		$this->prefs['alice/'.FavouriteDashboardService::PREFERENCE_KEY] = json_encode(['a']);
		$this->config->expects($this->never())->method('setUserValue');
		$this->assertSame(['a'], $this->service->listFor('alice', ['a']));
	}

	public function testAMalformedPreferenceReadsAsNoFavourites(): void {
		$this->prefs['alice/'.FavouriteDashboardService::PREFERENCE_KEY] = '{not json';
		$this->assertSame([], $this->service->listFor('alice', ['a']));
		$this->prefs['alice/'.FavouriteDashboardService::PREFERENCE_KEY] = json_encode([1, '', 'a', 'a']);
		$this->assertSame(['a'], $this->service->listFor('alice', ['a']));
	}
}
