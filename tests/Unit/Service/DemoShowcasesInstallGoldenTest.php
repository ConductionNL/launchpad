<?php

/**
 * DemoShowcasesService install golden test
 *
 * Pins, on the code before its split, which showcase installs are
 * refused before the install lock is taken (unknown id, missing
 * archive), the refusal while another install holds the lock, the
 * already-installed and forced-reinstall paths, and that the lock is
 * released after every install attempt, failed or not.
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

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Exception\ShowcaseNotFoundException;
use OCA\LaunchPad\Service\DemoShowcasesService;
use OCA\LaunchPad\Service\ShowcasePlatform;
use OCA\LaunchPad\Service\ShowcaseStores;
use OCP\Dashboard\IManager;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\IURLGenerator;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use ZipArchive;

class DemoShowcasesInstallGoldenTest extends TestCase {
	/** @var list<string> */
	private array $log = [];

	private DashboardMapper $dashboardMapper;
	private ILockingProvider $lockingProvider;
	private IAppConfig $appConfig;
	private IDBConnection $db;
	private DemoShowcasesService $service;
	private string $fixtureDir;

	/** @var array<string,string> */
	private array $installed = [];

	protected function setUp(): void {
		$this->dashboardMapper = $this->createMock(DashboardMapper::class);
		$this->lockingProvider = $this->createMock(ILockingProvider::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->db = $this->createMock(IDBConnection::class);
		$manager = $this->createMock(IManager::class);
		$manager->method('getWidgets')->willReturn([]);

		$this->lockingProvider->method('acquireLock')->willReturnCallback(function (string $path, int $type): void {
			$this->log[] = 'acquire:' . $path . ':' . $type;
			if (str_ends_with($path, 'busy')) {
				throw new LockedException($path);
			}
		});
		$this->lockingProvider->method('releaseLock')->willReturnCallback(function (string $path, int $type): void {
			$this->log[] = 'release:' . $path . ':' . $type;
		});
		$this->appConfig->method('getValueString')->willReturnCallback(fn (string $app, string $key, string $default = ''): string => $this->installed[$key] ?? $default);
		$this->appConfig->method('setValueString')->willReturnCallback(function (string $app, string $key, string $value): bool {
			$this->log[] = 'mark:' . $key . '=' . $value;
			$this->installed[$key] = $value;
			return true;
		});
		$this->appConfig->method('deleteKey')->willReturnCallback(function (string $app, string $key): void {
			$this->log[] = 'unmark:' . $key;
			unset($this->installed[$key]);
		});
		$this->dashboardMapper->method('insert')->willReturnCallback(function (Dashboard $dashboard): Dashboard {
			$this->log[] = 'insert';
			if ($dashboard->getName() === 'Broken') {
				throw new RuntimeException('db down');
			}

			$dashboard->setId(5);
			$dashboard->setUuid('new-uuid');
			return $dashboard;
		});
		$this->dashboardMapper->method('findByUuid')->willReturnCallback(function (string $uuid): Dashboard {
			$this->log[] = 'find:' . $uuid;
			$dashboard = new Dashboard();
			$dashboard->setId(4);
			$dashboard->setUuid($uuid);
			return $dashboard;
		});
		$this->dashboardMapper->method('delete')->willReturnCallback(function ($entity) {
			$this->log[] = 'delete:' . $entity->getUuid();
			return $entity;
		});

		$urls = $this->createMock(IURLGenerator::class);
		$this->service = new DemoShowcasesService(
			new ShowcaseStores($this->dashboardMapper, $this->createMock(WidgetPlacementMapper::class), $this->db, $this->lockingProvider),
			new ShowcasePlatform($this->appConfig, $manager, $urls),
			new NullLogger(),
		);
		$this->fixtureDir = sys_get_temp_dir() . '/launchpad-showcase-install-' . uniqid();
		mkdir($this->fixtureDir, 0o755, true);
		$this->service->setDataDirForTesting(path: $this->fixtureDir);
	}

	protected function tearDown(): void {
		foreach (glob($this->fixtureDir . '/*/*') ?: [] as $file) {
			unlink($file);
		}

		foreach (glob($this->fixtureDir . '/*') ?: [] as $dir) {
			rmdir($dir);
		}

		rmdir($this->fixtureDir);
	}

	private function archive(string $showcaseId, string $name = 'Showcase'): void {
		$dir = $this->fixtureDir . '/' . $showcaseId;
		mkdir($dir, 0o755, true);
		$zip = new ZipArchive();
		$zip->open($dir . '/' . $showcaseId . '.zip', ZipArchive::CREATE);
		$zip->addFromString('manifest.json', '{"showcaseName":"S"}');
		$zip->addFromString('dashboards/d.json', json_encode(['uuid' => 'd', 'name' => $name, 'widgets' => [], 'gridColumns' => 12]));
		$zip->close();
	}

	public function testUnknownShowcaseIsRefusedBeforeTheLock(): void {
		$this->expectException(ShowcaseNotFoundException::class);
		$this->expectExceptionMessage('Unknown showcase ID: nope');

		try {
			$this->service->installShowcase(showcaseId: 'nope');
		} finally {
			$this->assertSame([], $this->log);
		}
	}

	public function testMissingArchiveIsRefusedBeforeTheLock(): void {
		$this->expectException(ShowcaseNotFoundException::class);
		$this->expectExceptionMessage('Showcase ZIP missing for de-bron');

		try {
			$this->service->installShowcase(showcaseId: 'de-bron');
		} finally {
			$this->assertSame([], $this->log);
		}
	}

	public function testBusyLockIsRefusedAndNotReleased(): void {
		$this->archive('de-bron');
		$service = $this->service;
		$lockingProvider = $this->createMock(ILockingProvider::class);
		$lockingProvider->method('acquireLock')->willThrowException(new LockedException('x'));
		$lockingProvider->expects($this->never())->method('releaseLock');
		$service = new DemoShowcasesService(
			new ShowcaseStores($this->dashboardMapper, $this->createMock(WidgetPlacementMapper::class), $this->db, $lockingProvider),
			new ShowcasePlatform($this->appConfig, $this->createMock(IManager::class), $this->createMock(IURLGenerator::class)),
			new NullLogger(),
		);
		$service->setDataDirForTesting(path: $this->fixtureDir);

		try {
			$service->installShowcase(showcaseId: 'de-bron');
			$this->fail('expected a refusal');
		} catch (RuntimeException $e) {
			$this->assertSame('Showcase installation already in progress for de-bron', $e->getMessage());
			$this->assertInstanceOf(LockedException::class, $e->getPrevious());
			$this->assertSame([], $this->log);
		}
	}

	public function testAlreadyInstalledIsReusedUnderTheLock(): void {
		$this->archive('de-bron');
		$this->installed['showcase_installed_de-bron'] = 'old-uuid';

		$result = $this->service->installShowcase(showcaseId: 'de-bron');

		$this->assertSame(['installedDashboardUuid' => 'old-uuid', 'skippedWidgets' => [], 'alreadyInstalled' => true], $result);
		$this->assertSame([
			'acquire:launchpad-showcase-install-de-bron:' . ILockingProvider::LOCK_EXCLUSIVE,
			'release:launchpad-showcase-install-de-bron:' . ILockingProvider::LOCK_EXCLUSIVE,
		], $this->log);
	}

	public function testForcedReinstallUninstallsFirst(): void {
		$this->archive('de-bron');
		$this->installed['showcase_installed_de-bron'] = 'old-uuid';

		$result = $this->service->installShowcase(showcaseId: 'de-bron', lang: 'en', force: true);

		$this->assertSame(['installedDashboardUuid' => 'new-uuid', 'skippedWidgets' => [], 'alreadyInstalled' => false], $result);
		$lock = 'launchpad-showcase-install-de-bron:' . ILockingProvider::LOCK_EXCLUSIVE;
		$this->assertSame('acquire:' . $lock, $this->log[0]);
		$this->assertSame('release:' . $lock, end($this->log));
		$this->assertLessThan(array_search('insert', $this->log, true), array_search('unmark:showcase_installed_de-bron', $this->log, true));
	}

	public function testFreshInstallMarksAndReleases(): void {
		$this->archive('de-bron');

		$result = $this->service->installShowcase(showcaseId: 'de-bron', force: true);

		$this->assertFalse($result['alreadyInstalled']);
		$this->assertNotContains('unmark:showcase_installed_de-bron', $this->log);
		$this->assertContains('mark:showcase_installed_de-bron=new-uuid', $this->log);
		$this->assertSame('release:launchpad-showcase-install-de-bron:' . ILockingProvider::LOCK_EXCLUSIVE, end($this->log));
	}

	public function testFailedInstallStillReleasesTheLock(): void {
		$this->archive('de-bron', 'Broken');

		try {
			$this->service->installShowcase(showcaseId: 'de-bron');
			$this->fail('expected a failure');
		} catch (RuntimeException $e) {
			$this->assertSame('Failed to persist showcase: db down', $e->getMessage());
			$this->assertSame('release:launchpad-showcase-install-de-bron:' . ILockingProvider::LOCK_EXCLUSIVE, end($this->log));
		}
	}
}//end class
