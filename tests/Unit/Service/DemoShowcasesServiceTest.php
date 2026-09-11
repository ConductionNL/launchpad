<?php

/**
 * DemoShowcasesServiceTest
 *
 * Unit tests for {@see \OCA\LaunchPad\Service\DemoShowcasesService} covering
 * the `demo-data-showcases` capability — REQ-DEMO-001 (bundled
 * archives), REQ-DEMO-003 (install path), REQ-DEMO-004 (idempotency),
 * REQ-DEMO-005 (widget skip-on-missing), REQ-DEMO-006 (uninstall).
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
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Exception\ShowcaseNotFoundException;
use OCA\LaunchPad\Service\DemoShowcasesService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Dashboard\IManager;
use OCP\Dashboard\IWidget;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\IURLGenerator;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ZipArchive;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Mirrors constructor.
 */
class DemoShowcasesServiceTest extends TestCase {
	/** @var DashboardMapper&MockObject */
	private $dashboardMapper;

	/** @var WidgetPlacementMapper&MockObject */
	private $placementMapper;

	/** @var IDBConnection&MockObject */
	private $db;

	/** @var IAppConfig&MockObject */
	private $appConfig;

	/** @var IManager&MockObject */
	private $dashboardManager;

	/** @var ILockingProvider&MockObject */
	private $lockingProvider;

	/** @var IURLGenerator&MockObject */
	private $urlGenerator;

	private DemoShowcasesService $service;

	private string $fixtureDir;

	protected function setUp(): void {
		parent::setUp();

		$this->dashboardMapper = $this->createMock(originalClassName: DashboardMapper::class);
		$this->placementMapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$this->db = $this->createMock(originalClassName: IDBConnection::class);
		$this->appConfig = $this->createMock(originalClassName: IAppConfig::class);
		$this->dashboardManager = $this->createMock(originalClassName: IManager::class);
		$this->lockingProvider = $this->createMock(originalClassName: ILockingProvider::class);
		$this->urlGenerator = $this->createMock(originalClassName: IURLGenerator::class);

		// Stand in for IURLGenerator::imagePath() with an opaque sentinel
		// that echoes the arguments. The real URL shape (static path vs
		// front-controller route, with or without /index.php/) is the URL
		// generator's concern and varies per install — the service's only
		// contract is that it delegates with the right (app, file) pair.
		$this->urlGenerator->method('imagePath')->willReturnCallback(
			static fn (string $app, string $file): string => 'imagePath(' . $app . ',' . $file . ')'
		);

		$this->service = new DemoShowcasesService(
			dashboardMapper: $this->dashboardMapper,
			placementMapper: $this->placementMapper,
			db: $this->db,
			appConfig: $this->appConfig,
			dashboardManager: $this->dashboardManager,
			logger: new NullLogger(),
			lockingProvider: $this->lockingProvider,
			urlGenerator: $this->urlGenerator,
		);

		$this->fixtureDir = sys_get_temp_dir() . '/launchpad-showcase-fixture-' . uniqid();
		mkdir(directory: $this->fixtureDir, permissions: 0o755, recursive: true);
		$this->service->setDataDirForTesting(path: $this->fixtureDir);
	}

	protected function tearDown(): void {
		$this->rrmdir(dir: $this->fixtureDir);
		parent::tearDown();
	}

	/**
	 * REQ-DEMO-001: missing ZIP yields a `null` descriptor and the
	 * showcase is skipped from the available list.
	 */
	public function testDescribeShowcaseReturnsNullWhenZipMissing(): void {
		$this->appConfig->method('getValueString')->willReturn('');
		$result = $this->service->describeShowcase(showcaseId: 'de-bron');
		$this->assertNull(actual: $result);
	}

	/**
	 * REQ-DEMO-001 + REQ-DEMO-002: a present ZIP with valid manifest
	 * produces a descriptor with name + thumbnail URL + install state.
	 */
	public function testDescribeShowcasePopulatesDescriptor(): void {
		$this->writeFixtureZip(
			showcaseId: 'de-bron',
			manifest: [
				'schemaVersion' => 1,
				'showcaseName' => 'De Bron',
				'showcaseDescription' => 'Zorginstelling',
				'showcaseLanguage' => 'nl',
			],
			dashboardPayload: $this->validDashboardPayload(uuid: 'dashbrd-uuid', widgets: []),
		);

		$this->appConfig->method('getValueString')->willReturn('');

		$result = $this->service->describeShowcase(showcaseId: 'de-bron');
		$this->assertIsArray(actual: $result);
		$this->assertSame(expected: 'de-bron', actual: $result['id']);
		$this->assertSame(expected: 'De Bron', actual: $result['name']);
		$this->assertSame(expected: 'nl', actual: $result['language']);
		$this->assertFalse(condition: $result['isInstalled']);
		$this->assertNull(actual: $result['installedDashboardUuid']);
		// The thumbnail is delegated to IURLGenerator::imagePath() with the
		// app id and the per-showcase image path — not a hardcoded URL.
		$this->assertSame(
			expected: 'imagePath(launchpad,showcases/de-bron.png)',
			actual: $result['thumbnailUrl']
		);
	}

	/**
	 * REQ-DEMO-002: the available list mirrors the `BUNDLED_IDS` order
	 * and only includes showcases whose ZIPs are readable.
	 */
	public function testGetAvailableShowcasesIteratesBundledIds(): void {
		$this->writeFixtureZip(
			showcaseId: 'de-bron',
			manifest: ['schemaVersion' => 1, 'showcaseName' => 'De Bron', 'showcaseLanguage' => 'nl'],
			dashboardPayload: $this->validDashboardPayload(uuid: 'a-uuid', widgets: []),
		);
		$this->writeFixtureZip(
			showcaseId: 'horizon-labs',
			manifest: ['schemaVersion' => 1, 'showcaseName' => 'Horizon Labs', 'showcaseLanguage' => 'nl'],
			dashboardPayload: $this->validDashboardPayload(uuid: 'b-uuid', widgets: []),
		);

		$this->appConfig->method('getValueString')->willReturn('');

		$available = $this->service->getAvailableShowcases();
		$ids = array_map(callback: static fn (array $row) => $row['id'], array: $available);
		$this->assertContains(needle: 'de-bron', haystack: $ids);
		$this->assertContains(needle: 'horizon-labs', haystack: $ids);
		$this->assertNotContains(needle: 'gemeente-duin', haystack: $ids);
	}

	/**
	 * REQ-DEMO-003: install creates a `group_shared` dashboard with the
	 * `default` group sentinel and stores the install UUID via IConfig.
	 */
	public function testInstallCreatesGroupSharedDashboard(): void {
		$this->writeFixtureZip(
			showcaseId: 'de-bron',
			manifest: ['schemaVersion' => 1, 'showcaseName' => 'De Bron', 'showcaseLanguage' => 'nl'],
			dashboardPayload: $this->validDashboardPayload(uuid: 'src-uuid', widgets: []),
		);

		$this->dashboardManager->method('getWidgets')->willReturn([]);
		$this->appConfig->method('getValueString')->willReturn('');

		$persisted = new Dashboard();
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$persisted->setId(123);
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$persisted->setUuid('installed-uuid');

		$this->dashboardMapper
			->expects($this->once())
			->method('insert')
			->willReturnCallback(function (Dashboard $entity) use ($persisted): Dashboard {
				$this->assertSame(expected: Dashboard::TYPE_GROUP_SHARED, actual: $entity->getType());
				$this->assertSame(expected: Dashboard::DEFAULT_GROUP_ID, actual: $entity->getGroupId());
				$this->assertSame(expected: Dashboard::PERMISSION_VIEW_ONLY, actual: $entity->getPermissionLevel());
				return $persisted;
			});

		$this->appConfig
			->expects($this->once())
			->method('setValueString')
			->with('launchpad', 'showcase_installed_de-bron', 'installed-uuid');

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');

		$result = $this->service->installShowcase(showcaseId: 'de-bron', lang: 'nl');

		$this->assertSame(expected: 'installed-uuid', actual: $result['installedDashboardUuid']);
		$this->assertFalse(condition: $result['alreadyInstalled']);
		$this->assertSame(expected: [], actual: $result['skippedWidgets']);
	}

	/**
	 * REQ-DEMO-005: unknown widget IDs are dropped and surfaced in the
	 * `skippedWidgets` response array; valid widgets are still placed.
	 */
	public function testInstallSkipsUnknownWidgetTypes(): void {
		$this->writeFixtureZip(
			showcaseId: 'de-bron',
			manifest: ['schemaVersion' => 1, 'showcaseName' => 'De Bron', 'showcaseLanguage' => 'nl'],
			dashboardPayload: $this->validDashboardPayload(
				uuid: 'src-uuid',
				widgets: [
					['widgetId' => 'recommendations', 'gridX' => 0, 'gridY' => 0],
					['widgetId' => 'future-timeline', 'gridX' => 4, 'gridY' => 0],
					['widgetId' => 'launchpad-tile', 'tileType' => 'shortcut', 'tileTitle' => 'Files', 'gridX' => 8, 'gridY' => 0],
				]
			),
		);

		$widget = $this->createMock(originalClassName: IWidget::class);
		$widget->method('getId')->willReturn('recommendations');
		$this->dashboardManager->method('getWidgets')->willReturn([$widget]);
		$this->appConfig->method('getValueString')->willReturn('');

		$persisted = new Dashboard();
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$persisted->setId(7);
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$persisted->setUuid('installed-uuid');

		$this->dashboardMapper->method('insert')->willReturn($persisted);
		$this->placementMapper->expects($this->exactly(2))->method('insert')->willReturnCallback(
			static fn (WidgetPlacement $p): WidgetPlacement => $p
		);

		$result = $this->service->installShowcase(showcaseId: 'de-bron');

		$this->assertSame(expected: ['future-timeline'], actual: $result['skippedWidgets']);
	}

	/**
	 * REQ-DEMO-004: a second install without `--force` returns the
	 * already-installed UUID without persisting a new dashboard.
	 */
	public function testInstallIsIdempotent(): void {
		$this->writeFixtureZip(
			showcaseId: 'de-bron',
			manifest: ['schemaVersion' => 1, 'showcaseName' => 'De Bron', 'showcaseLanguage' => 'nl'],
			dashboardPayload: $this->validDashboardPayload(uuid: 'src', widgets: []),
		);

		$this->appConfig->method('getValueString')->willReturn('previously-installed-uuid');
		$this->dashboardMapper->expects($this->never())->method('insert');

		$result = $this->service->installShowcase(showcaseId: 'de-bron');

		$this->assertSame(expected: 'previously-installed-uuid', actual: $result['installedDashboardUuid']);
		$this->assertTrue(condition: $result['alreadyInstalled']);
	}

	/**
	 * REQ-DEMO-003: an unknown showcase ID raises a typed exception.
	 */
	public function testUnknownShowcaseRaisesException(): void {
		$this->expectException(exception: ShowcaseNotFoundException::class);
		$this->service->installShowcase(showcaseId: 'not-a-real-id');
	}

	/**
	 * REQ-DEMO-006: uninstall deletes the dashboard, cascades widgets,
	 * and clears the install marker.
	 */
	public function testUninstallDeletesDashboardAndClearsMarker(): void {
		$existing = new Dashboard();
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$existing->setId(42);
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$existing->setUuid('installed-uuid');

		$this->appConfig->method('getValueString')->willReturn('installed-uuid');
		$this->dashboardMapper
			->method('findByUuid')
			->with(uuid: 'installed-uuid')
			->willReturn($existing);

		$this->placementMapper->expects($this->once())->method('deleteByDashboardId')->with(dashboardId: 42);
		$this->dashboardMapper->expects($this->once())->method('delete')->with(entity: $existing);
		$this->appConfig
			->expects($this->once())
			->method('deleteKey')
			->with('launchpad', 'showcase_installed_de-bron');

		$this->service->uninstallShowcase(showcaseId: 'de-bron');
	}

	/**
	 * REQ-DEMO-006: uninstall is a silent no-op when nothing is installed.
	 */
	public function testUninstallIsIdempotent(): void {
		$this->appConfig->method('getValueString')->willReturn('');
		$this->dashboardMapper->expects($this->never())->method('delete');
		$this->placementMapper->expects($this->never())->method('deleteByDashboardId');
		$this->appConfig->expects($this->never())->method('deleteKey');

		$this->service->uninstallShowcase(showcaseId: 'de-bron');
	}

	/**
	 * REQ-DEMO-006: uninstall tolerates a dashboard already deleted
	 * out-of-band — clears the marker without raising.
	 */
	public function testUninstallTolerantWhenDashboardMissing(): void {
		$this->appConfig->method('getValueString')->willReturn('orphan-uuid');
		$this->dashboardMapper
			->method('findByUuid')
			->willThrowException(exception: new DoesNotExistException(msg: 'gone'));

		$this->appConfig
			->expects($this->once())
			->method('deleteKey')
			->with('launchpad', 'showcase_installed_de-bron');

		$this->service->uninstallShowcase(showcaseId: 'de-bron');
	}

	/**
	 * REQ-DEMO-005: tile placements (with `tileType`) are always
	 * considered valid regardless of the widget registry.
	 */
	public function testPartitionWidgetsTreatsTilesAsValid(): void {
		$this->dashboardManager->method('getWidgets')->willReturn([]);

		[$valid, $skipped] = $this->service->partitionWidgets(widgets: [
			['widgetId' => 'launchpad-tile', 'tileType' => 'shortcut'],
			['widgetId' => 'unknown-id'],
		]);

		$this->assertCount(expectedCount: 1, haystack: $valid);
		$this->assertSame(expected: ['unknown-id'], actual: $skipped);
	}

	private function validDashboardPayload(string $uuid, array $widgets): array {
		return [
			'uuid' => $uuid,
			'name' => 'Showcase',
			'description' => 'Test',
			'widgets' => $widgets,
			'gridColumns' => 12,
		];
	}

	private function writeFixtureZip(string $showcaseId, array $manifest, array $dashboardPayload): void {
		$dir = $this->fixtureDir . '/' . $showcaseId;
		if (is_dir(filename: $dir) === false) {
			mkdir(directory: $dir, permissions: 0o755, recursive: true);
		}

		$zipPath = $dir . '/' . $showcaseId . '.zip';
		$zip = new ZipArchive();
		$zip->open(filename: $zipPath, flags: ZipArchive::CREATE | ZipArchive::OVERWRITE);
		$zip->addFromString(
			name: 'manifest.json',
			content: (string)json_encode(value: $manifest)
		);
		$zip->addFromString(
			name: 'dashboards/' . $dashboardPayload['uuid'] . '.json',
			content: (string)json_encode(value: $dashboardPayload)
		);
		$zip->close();
	}

	/**
	 * REQ-DEMO-001: every bundled id resolves to a REAL archive that
	 * carries the manifest fields the gallery reads.
	 *
	 * Every other test in this file writes its own fixture ZIP into a temp
	 * directory, which is right for exercising the service but means the
	 * archives actually shipped in `data/demo-showcases/` were never opened
	 * by any test. A showcase added to `BUNDLED_IDS` with a missing,
	 * malformed or mis-named ZIP would therefore pass the whole suite and
	 * fail on a user's instance, where the only symptom is a gallery entry
	 * that will not install.
	 *
	 * @return void
	 */
	public function testEveryBundledIdShipsAReadableArchive(): void {
		$root = dirname(path: __DIR__, levels: 3) . '/data/demo-showcases';

		foreach (DemoShowcasesService::BUNDLED_IDS as $showcaseId) {
			$path = $root . '/' . $showcaseId . '/' . $showcaseId . '.zip';
			$this->assertFileExists(
				filename: $path,
				message: $showcaseId . ' is in BUNDLED_IDS but ships no archive'
			);

			$zip = new \ZipArchive();
			$this->assertTrue(
				condition: $zip->open(filename: $path) === true,
				message: $showcaseId . ' archive is not a readable ZIP'
			);

			$raw = $zip->getFromName(name: 'manifest.json');
			$this->assertNotFalse($raw, $showcaseId . ' has no manifest.json');

			$manifest = json_decode(json: (string)$raw, associative: true);
			$this->assertIsArray(actual: $manifest, message: $showcaseId . ' manifest.json is not JSON');

			// The id has to agree with where the file was found, or the
			// gallery lists one showcase and installs another.
			$this->assertSame(
				expected: $showcaseId,
				actual: $manifest['showcaseId'] ?? null,
				message: $showcaseId . ' manifest declares a different showcaseId'
			);

			foreach (['showcaseName', 'showcaseDescription', 'showcaseLanguage', 'schemaVersion'] as $key) {
				$this->assertArrayHasKey(
					key: $key,
					array: $manifest,
					message: $showcaseId . ' manifest is missing ' . $key
				);
			}

			$dashboards = [];
			for ($i = 0; $i < $zip->numFiles; $i++) {
				$name = (string)$zip->getNameIndex(index: $i);
				if (str_starts_with(haystack: $name, needle: 'dashboards/') === true
					&& str_ends_with(haystack: $name, needle: '.json') === true
				) {
					$dashboards[] = $name;
				}
			}

			$this->assertCount(
				expectedCount: (int)($manifest['dashboardCount'] ?? 0),
				haystack: $dashboards,
				message: $showcaseId . ' ships a different number of dashboards than it declares'
			);

			$zip->close();
		}//end foreach
	}//end testEveryBundledIdShipsAReadableArchive()

	/**
	 * REQ-DEMO-001: the role showcase installs as a read-only group
	 * dashboard, and places the four widgets a case handler works from.
	 *
	 * A showcase is authored on somebody's instance as their PERSONAL
	 * dashboard. Shipping that shape would hand every installing admin a
	 * dashboard owned by a user id that does not exist there, so the
	 * re-shaping to `group_shared` is the part worth pinning.
	 *
	 * @return void
	 */
	public function testCaseHandlerShowcaseIsAReadOnlyGroupDashboard(): void {
		$root = dirname(path: __DIR__, levels: 3) . '/data/demo-showcases';
		$zip = new \ZipArchive();
		$this->assertTrue(condition: $zip->open(filename: $root . '/case-handler/case-handler.zip') === true);

		$payload = null;
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$name = (string)$zip->getNameIndex(index: $i);
			if (str_starts_with(haystack: $name, needle: 'dashboards/') === true
				&& str_ends_with(haystack: $name, needle: '.json') === true
			) {
				$payload = json_decode(json: (string)$zip->getFromName(name: $name), associative: true);
				break;
			}
		}

		$zip->close();
		$this->assertIsArray(actual: $payload, message: 'case-handler ships no dashboard payload');

		$this->assertSame(expected: 'group_shared', actual: $payload['type'] ?? null);
		$this->assertArrayHasKey(key: 'userId', array: $payload);
		$this->assertNull(actual: $payload['userId'], message: 'a showcase must not carry its author');
		$this->assertSame(expected: 'view_only', actual: $payload['permissionLevel'] ?? null);
		$this->assertSame(expected: 'showcase-case-handler', actual: $payload['slug'] ?? null);

		$types = array_map(
			callback: static fn (array $w) => $w['widgetId'] ?? '',
			array: ($payload['widgets'] ?? [])
		);
		$this->assertCount(expectedCount: 4, haystack: $types);
		$this->assertSame(
			expected: ['object-list', 'nc-widget', 'calendar', 'nc-widget'],
			actual: $types,
			message: 'the case handler dashboard places cases, tasks, today and mail'
		);

		// The calendar widget must ship with NO calendar chosen: the ids on
		// the authoring instance mean nothing anywhere else, and pointing a
		// stranger's widget at calendar "1" is worse than asking them.
		$calendar = null;
		foreach (($payload['widgets'] ?? []) as $widget) {
			if (($widget['widgetId'] ?? '') === 'calendar') {
				$calendar = $widget;
				break;
			}
		}

		$this->assertIsArray(actual: $calendar);
		$this->assertSame(expected: [], actual: $calendar['content']['internalCalendars'] ?? null);
	}//end testCaseHandlerShowcaseIsAReadOnlyGroupDashboard()

	/**
	 * Every bundled id ships the preview image the gallery asks for.
	 *
	 * `IURLGenerator::imagePath()` THROWS for an image that does not exist,
	 * and the setUp() stand-in above always returns a string, so no unit test
	 * here could see it. CI's Newman lane did: adding `case-handler` without
	 * `img/showcases/case-handler.png` turned GET /api/admin/demo-showcases
	 * into a 500 for every showcase, not just the new one.
	 *
	 * @return void
	 */
	public function testEveryBundledIdShipsAPreviewImage(): void {
		$root = dirname(path: __DIR__, levels: 3) . '/img/showcases';
		foreach (DemoShowcasesService::BUNDLED_IDS as $showcaseId) {
			$this->assertFileExists(
				filename: $root . '/' . $showcaseId . '.png',
				message: $showcaseId . ' is in BUNDLED_IDS but has no img/showcases preview'
			);
		}
	}//end testEveryBundledIdShipsAPreviewImage()

	/**
	 * A showcase with no preview still lists, with a null thumbnail.
	 *
	 * The guard above keeps the bundled set honest; this one keeps the
	 * listing standing when it is not. One missing image is a cosmetic gap
	 * in one card, not a reason to hide every other showcase behind a 500.
	 *
	 * @return void
	 */
	public function testAMissingPreviewDoesNotFailTheListing(): void {
		$throwing = $this->createMock(originalClassName: IURLGenerator::class);
		$throwing->method('imagePath')->willThrowException(
			new \RuntimeException('image not found: image:showcases/de-bron.png webroot: serverroot:')
		);

		$service = new DemoShowcasesService(
			dashboardMapper: $this->dashboardMapper,
			placementMapper: $this->placementMapper,
			db: $this->db,
			appConfig: $this->appConfig,
			dashboardManager: $this->dashboardManager,
			logger: new NullLogger(),
			lockingProvider: $this->lockingProvider,
			urlGenerator: $throwing,
		);
		$service->setDataDirForTesting(path: $this->fixtureDir);

		$this->writeFixtureZip(
			showcaseId: 'de-bron',
			manifest: ['schemaVersion' => 1, 'showcaseName' => 'De Bron', 'showcaseLanguage' => 'nl'],
			dashboardPayload: $this->validDashboardPayload(uuid: 'a-uuid', widgets: []),
		);
		$this->appConfig->method('getValueString')->willReturn('');

		$row = $service->describeShowcase(showcaseId: 'de-bron');

		$this->assertIsArray(actual: $row, message: 'a missing preview must not drop the showcase');
		$this->assertSame(expected: 'de-bron', actual: $row['id']);
		$this->assertNull(actual: $row['thumbnailUrl']);
	}//end testAMissingPreviewDoesNotFailTheListing()

	private function rrmdir(string $dir): void {
		if (is_dir(filename: $dir) === false) {
			return;
		}

		foreach (scandir(directory: $dir) as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$path = $dir . '/' . $entry;
			if (is_dir(filename: $path) === true) {
				$this->rrmdir(dir: $path);
				continue;
			}

			@unlink(filename: $path);
		}

		@rmdir(directory: $dir);
	}
}
