<?php

/**
 * ImportServiceTest
 *
 * Unit tests for {@see \OCA\LaunchPad\Service\ImportService} covering the
 * `dashboard-export-import` capability — REQ-EXIM-005 (UUID collisions),
 * REQ-EXIM-008 (manifest validation), REQ-EXIM-011 (per-dashboard
 * transactional import).
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

use InvalidArgumentException;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\ExportService;
use OCA\LaunchPad\Service\ImportService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use OCP\IGroupManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ZipArchive;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Mirrors constructor.
 */
class ImportServiceTest extends TestCase {

	/** @var DashboardMapper&MockObject */
	private $dashboardMapper;

	/** @var WidgetPlacementMapper&MockObject */
	private $placementMapper;

	/** @var IDBConnection&MockObject */
	private $db;

	private ImportService $service;

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->dashboardMapper = $this->createMock(originalClassName: DashboardMapper::class);
		$this->placementMapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$this->db = $this->createMock(originalClassName: IDBConnection::class);

		$this->service = new ImportService(
			dashboardMapper: $this->dashboardMapper,
			placementMapper: $this->placementMapper,
			db: $this->db,
			logger: new NullLogger(),
		);
	}

	/**
	 * REQ-EXIM-008: missing manifest.json is rejected with HTTP 400.
	 *
	 * @return void
	 */
	public function testInvalidZipMissingManifest(): void {
		$zipPath = $this->makeZip(entries: ['dashboards/foo.json' => '{}']);

		$this->expectException(exception: InvalidArgumentException::class);
		$this->expectExceptionMessage(message: 'manifest.json not found');

		try {
			$this->service->import(
				zipPath: $zipPath,
				preserveUuids: false,
				currentUserId: 'admin'
			);
		} finally {
			@unlink(filename: $zipPath);
		}
	}

	/**
	 * REQ-EXIM-008: an unsupported schemaVersion is rejected.
	 *
	 * @return void
	 */
	public function testUnsupportedSchemaVersion(): void {
		$manifest = (string)json_encode(value: [
			'schemaVersion' => 2,
			'scope' => 'site',
		]);
		$zipPath = $this->makeZip(entries: ['manifest.json' => $manifest]);

		$this->expectException(exception: InvalidArgumentException::class);
		$this->expectExceptionMessage(message: 'Unsupported manifest schema version');

		try {
			$this->service->import(
				zipPath: $zipPath,
				preserveUuids: false,
				currentUserId: 'admin'
			);
		} finally {
			@unlink(filename: $zipPath);
		}
	}

	/**
	 * REQ-EXIM-005: with `preserveUuids=true`, an existing UUID returns
	 * a status flag that the controller maps to HTTP 409.
	 *
	 * @return void
	 */
	public function testImportDashboardPreservingUuidsCollision(): void {
		$uuid = 'abc-123-uuid-collision';
		$payload = [
			'uuid' => $uuid,
			'name' => 'Collide',
			'widgets' => [],
		];

		$zipPath = $this->makeZip(entries: [
			'manifest.json' => (string)json_encode(value: [
				'schemaVersion' => 1,
				'scope' => 'dashboard',
			]),
			'dashboards/' . $uuid . '.json' => (string)json_encode(value: $payload),
		]);

		$existing = new Dashboard();
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$existing->setUuid($uuid);

		$this->dashboardMapper
			->method('findByUuid')
			->with(uuid: $uuid)
			->willReturn($existing);

		$result = $this->service->import(
			zipPath: $zipPath,
			preserveUuids: true,
			currentUserId: 'admin'
		);

		$this->assertSame(
			expected: ImportService::ERR_UUID_COLLISION,
			actual: $result['status']
		);
		$this->assertSame(expected: 0, actual: $result['importedDashboardCount']);
		$this->assertNotEmpty(actual: $result['errors']);
		$this->assertSame(
			expected: ImportService::ERR_UUID_COLLISION,
			actual: $result['errors'][0]['type']
		);
		@unlink(filename: $zipPath);
	}

	/**
	 * REQ-EXIM-005: with `preserveUuids=false`, a colliding UUID is
	 * remapped to a fresh UUID and the dashboard is imported.
	 *
	 * @return void
	 */
	public function testImportDashboardFreshUuidsRemap(): void {
		$uuid = 'xyz-444-555-666-aaaa';

		$payload = [
			'uuid' => $uuid,
			'name' => 'Fresh',
			'widgets' => [],
		];
		$zipPath = $this->makeZip(entries: [
			'manifest.json' => (string)json_encode(value: [
				'schemaVersion' => 1,
				'scope' => 'dashboard',
			]),
			'dashboards/' . $uuid . '.json' => (string)json_encode(value: $payload),
		]);

		$this->dashboardMapper
			->method('findByUuid')
			->willThrowException(exception: new DoesNotExistException(msg: 'no'));

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->never())->method('rollBack');

		$inserted = new Dashboard();
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$inserted->setId(99);
		$this->dashboardMapper
			->expects($this->once())
			->method('insert')
			->willReturn($inserted);

		$result = $this->service->import(
			zipPath: $zipPath,
			preserveUuids: false,
			currentUserId: 'admin'
		);

		$this->assertSame(expected: 'ok', actual: $result['status']);
		$this->assertSame(expected: 1, actual: $result['importedDashboardCount']);
		$this->assertSame(expected: 0, actual: $result['skippedDashboardCount']);
		@unlink(filename: $zipPath);
	}

	/**
	 * REQ-EXIM-008: a dashboard JSON file missing a required field is
	 * skipped and reported as an error rather than aborting the batch.
	 *
	 * @return void
	 */
	public function testInvalidDashboardSkippedNotFatal(): void {
		$zipPath = $this->makeZip(entries: [
			'manifest.json' => (string)json_encode(value: [
				'schemaVersion' => 1,
				'scope' => 'dashboard',
			]),
			// Missing required `widgets` field.
			'dashboards/bad-uuid.json' => (string)json_encode(value: [
				'uuid' => 'bad-uuid',
				'name' => 'No widgets',
			]),
		]);

		$this->dashboardMapper
			->method('findByUuid')
			->willThrowException(exception: new DoesNotExistException(msg: 'no'));

		$result = $this->service->import(
			zipPath: $zipPath,
			preserveUuids: false,
			currentUserId: 'admin'
		);

		$this->assertSame(expected: 0, actual: $result['importedDashboardCount']);
		$this->assertSame(expected: 1, actual: $result['skippedDashboardCount']);
		$this->assertNotEmpty(actual: $result['errors']);
		$this->assertSame(
			expected: ImportService::ERR_INVALID_DASHBOARD,
			actual: $result['errors'][0]['type']
		);
		@unlink(filename: $zipPath);
	}

	/**
	 * REQ-EXIM-004 "Invalid JSON in dashboard file skips that dashboard": the
	 * error names the corrupt file.
	 *
	 * A file that is not valid JSON has no `uuid` to report, so it used to come
	 * back as `uuid: ""` with "Missing required field: corrupt JSON payload",
	 * which identified nothing. The entry name is its only identity, and the
	 * file name is the exported UUID.
	 *
	 * @return void
	 */
	public function testACorruptDashboardFileIsNamedInTheError(): void {
		$good = static fn (string $uuid): string => (string)json_encode(
			value: ['uuid' => $uuid, 'name' => 'Good ' . $uuid, 'widgets' => []]
		);
		$zipPath = $this->makeZip(entries: [
			'manifest.json' => (string)json_encode(value: ['schemaVersion' => 1, 'scope' => 'site']),
			'dashboards/good-one.json' => $good('good-one'),
			'dashboards/broken-uuid.json' => '{"uuid": "broken-uuid", "name": ',
			'dashboards/good-two.json' => $good('good-two'),
		]);

		$this->dashboardMapper->method('findByUuid')
			->willThrowException(exception: new DoesNotExistException(msg: 'no'));
		$this->dashboardMapper->method('insert')->willReturnCallback(
			static function (Dashboard $dashboard): Dashboard {
				// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
				$dashboard->setId(3);
				return $dashboard;
			}
		);

		try {
			$result = $this->service->import(zipPath: $zipPath, preserveUuids: false, currentUserId: 'admin');
		} finally {
			@unlink(filename: $zipPath);
		}

		$this->assertSame(expected: 2, actual: $result['importedDashboardCount']);
		$this->assertSame(expected: 1, actual: $result['skippedDashboardCount']);
		$this->assertCount(expectedCount: 1, haystack: $result['errors']);
		$this->assertSame(expected: 'broken-uuid', actual: $result['errors'][0]['uuid']);
		$this->assertSame(
			expected: 'dashboards/broken-uuid.json is not valid JSON',
			actual: $result['errors'][0]['message']
		);
	}//end testACorruptDashboardFileIsNamedInTheError()

	/**
	 * REQ-EXIM-008 "Dashboard JSON missing required fields": the error names the
	 * UUID the ARCHIVE used.
	 *
	 * With `preserveUuids=false` every dashboard is given a fresh UUID before
	 * validation runs, so a skipped dashboard used to be reported under a UUID
	 * this import had just invented — a row that does not exist, and a name the
	 * admin cannot find in the file they uploaded. Found by the e2e that drives
	 * the admin page.
	 *
	 * @return void
	 */
	public function testASkippedDashboardIsReportedUnderTheArchivesUuid(): void {
		$zipPath = $this->makeZip(entries: [
			'manifest.json' => (string)json_encode(value: ['schemaVersion' => 1, 'scope' => 'site']),
			// No `name`, so validation skips it.
			'dashboards/nameless.json' => (string)json_encode(
				value: ['uuid' => 'archive-uuid', 'widgets' => []]
			),
		]);

		$this->dashboardMapper->method('findByUuid')
			->willThrowException(exception: new DoesNotExistException(msg: 'no'));

		try {
			$result = $this->service->import(zipPath: $zipPath, preserveUuids: false, currentUserId: 'admin');
		} finally {
			@unlink(filename: $zipPath);
		}

		$this->assertSame(expected: 1, actual: $result['skippedDashboardCount']);
		$this->assertSame(
			expected: 'archive-uuid',
			actual: $result['errors'][0]['uuid'],
			message: 'the error must name the UUID the archive used, not a remapped one'
		);
	}//end testASkippedDashboardIsReportedUnderTheArchivesUuid()

	/**
	 * REQ-EXIM-009 "Version mismatch does not corrupt existing data": an
	 * archive from a newer LaunchPad tells the admin to upgrade, and a
	 * nonsense version does not.
	 *
	 * @return void
	 */
	public function testAnArchiveFromANewerVersionSaysToUpgrade(): void {
		$messageFor = function (int $version): string {
			$zipPath = $this->makeZip(entries: [
				'manifest.json' => (string)json_encode(value: ['schemaVersion' => $version, 'scope' => 'site']),
			]);
			try {
				$this->service->import(zipPath: $zipPath, preserveUuids: false, currentUserId: 'admin');
			} catch (InvalidArgumentException $e) {
				return $e->getMessage();
			} finally {
				@unlink(filename: $zipPath);
			}

			return '(no exception)';
		};

		$this->assertSame(
			expected: 'Unsupported manifest schema version: 2. Only version 1 is supported. '
				. 'Upgrade LaunchPad to import archives of version 2.',
			actual: $messageFor(2)
		);
		$this->assertSame(
			expected: 'Unsupported manifest schema version: 0. Only version 1 is supported.',
			actual: $messageFor(0)
		);
	}//end testAnArchiveFromANewerVersionSaysToUpgrade()

	/**
	 * REQ-EXIM-011: a corrupt widget JSON triggers a per-dashboard
	 * rollback while sibling dashboards still import successfully.
	 *
	 * @return void
	 */
	public function testPartialFailureRollsBackOneDashboard(): void {
		$goodUuid = 'good-uuid-001';
		$badUuid = 'bad-uuid-002';

		$zipPath = $this->makeZip(entries: [
			'manifest.json' => (string)json_encode(value: [
				'schemaVersion' => 1,
				'scope' => 'site',
			]),
			'dashboards/' . $goodUuid . '.json' => (string)json_encode(value: [
				'uuid' => $goodUuid,
				'name' => 'Good',
				'widgets' => [],
			]),
			'dashboards/' . $badUuid . '.json' => (string)json_encode(value: [
				'uuid' => $badUuid,
				'name' => 'Bad',
				'widgets' => [],
			]),
		]);

		$this->dashboardMapper
			->method('findByUuid')
			->willThrowException(exception: new DoesNotExistException(msg: 'no'));

		$this->db->expects($this->exactly(2))->method('beginTransaction');
		$this->db->expects($this->once())->method('commit');
		$this->db->expects($this->once())->method('rollBack');

		$okEntity = new Dashboard();
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$okEntity->setId(7);

		$this->dashboardMapper
			->expects($this->exactly(2))
			->method('insert')
			->willReturnOnConsecutiveCalls(
				$okEntity,
				$this->throwException(exception: new \RuntimeException(message: 'boom'))
			);

		$result = $this->service->import(
			zipPath: $zipPath,
			preserveUuids: false,
			currentUserId: 'admin'
		);

		$this->assertSame(expected: 1, actual: $result['importedDashboardCount']);
		$this->assertSame(expected: 1, actual: $result['skippedDashboardCount']);
		$this->assertCount(expectedCount: 1, haystack: $result['errors']);

		@unlink(filename: $zipPath);
	}

	/**
	 * REQ-EXIM-005: remapUuids assigns fresh v4 UUIDs and rewrites
	 * `parentUuid` references to maintain tree integrity.
	 *
	 * @return void
	 */
	public function testRemapUuidsRewritesParentReferences(): void {
		$original = [
			['uuid' => 'parent-aaaa', 'name' => 'Parent', 'widgets' => []],
			[
				'uuid' => 'child-bbbb',
				'parentUuid' => 'parent-aaaa',
				'name' => 'Child',
				'widgets' => [],
			],
		];

		$remapped = $this->service->remapUuids(
			dashboards: $original,
			preserveUuids: false
		);

		$this->assertNotSame(expected: 'parent-aaaa', actual: $remapped[0]['uuid']);
		$this->assertNotSame(expected: 'child-bbbb', actual: $remapped[1]['uuid']);
		$this->assertSame(
			expected: $remapped[0]['uuid'],
			actual: $remapped[1]['parentUuid'],
			message: 'parentUuid must follow the remap'
		);
	}

	/**
	 * An imported dashboard and its placements carry the timestamps the
	 * schema requires.
	 *
	 * `created_at` and `updated_at` are NOT NULL without a default on both
	 * `launchpad_dashboards` (DashboardTableBuilder) and
	 * `launchpad_widget_placements` (PlacementTableBuilder). The importer set
	 * neither, so on a real database every dashboard insert failed and was
	 * reported as skipped: no import had ever landed a dashboard. Measured on
	 * PostgreSQL as SQLSTATE 23502 on created_at. Every other test in this file
	 * mocks the mapper and so accepts a row the database would refuse; this
	 * one looks at the row.
	 *
	 * @return void
	 */
	public function testAnImportedRowCarriesTheTimestampsTheSchemaRequires(): void {
		$zipPath = $this->makeZip(entries: [
			'manifest.json' => (string)json_encode(value: ['schemaVersion' => 1, 'scope' => 'dashboard']),
			'dashboards/ts-uuid.json' => (string)json_encode(value: [
				'uuid' => 'ts-uuid',
				'name' => 'Timestamps',
				'widgets' => [['widgetId' => 'text', 'content' => ['text' => 'x']]],
			]),
		]);

		$this->dashboardMapper->method('findByUuid')
			->willThrowException(exception: new DoesNotExistException(msg: 'no'));

		$rows = [];
		$this->dashboardMapper->method('insert')->willReturnCallback(
			static function (Dashboard $dashboard) use (&$rows): Dashboard {
				$rows[] = $dashboard;
				// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
				$dashboard->setId(5);
				return $dashboard;
			}
		);
		$this->placementMapper->method('insert')->willReturnCallback(
			static function (WidgetPlacement $placement) use (&$rows): WidgetPlacement {
				$rows[] = $placement;
				return $placement;
			}
		);

		try {
			$this->service->import(zipPath: $zipPath, preserveUuids: false, currentUserId: 'admin');
		} finally {
			@unlink(filename: $zipPath);
		}

		$this->assertCount(expectedCount: 2, haystack: $rows, message: 'one dashboard and one placement row');
		foreach ($rows as $row) {
			$this->assertNotEmpty(
				actual: $row->getCreatedAt(),
				message: get_class($row) . ' reached the mapper without created_at, which the schema declares NOT NULL'
			);
			$this->assertNotEmpty(
				actual: $row->getUpdatedAt(),
				message: get_class($row) . ' reached the mapper without updated_at, which the schema declares NOT NULL'
			);
		}
	}//end testAnImportedRowCarriesTheTimestampsTheSchemaRequires()

	/**
	 * REQ-EXIM-002 + REQ-EXIM-004: a dashboard that goes out through export
	 * and back in through import keeps every widget's configuration.
	 *
	 * This is a real round trip: the archive is built from the actual
	 * `ExportService` output and read by the actual `ImportService`. Before
	 * the fix the importer kept each widget's grid, style and title and
	 * dropped the rest, so every text widget came back empty, every
	 * object-list forgot its register, every nc-widget forgot what it
	 * proxies, and every tile lost its type and link. The store install path
	 * (launchpad#607) hands its payload to the same importer, so a dashboard
	 * installed from a registry arrived unconfigured the same way.
	 *
	 * The comparison is everything export writes, minus exactly the fields the
	 * builder deliberately leaves behind (see PlacementPayloadHydrator), so a
	 * field that silently stops travelling fails here by name.
	 *
	 * @return void
	 */
	public function testExportThenImportKeepsEveryWidgetsConfiguration(): void {
		$sources = [
			$this->configuredPlacement(widgetId: 'text', content: ['text' => 'Welcome to the desk', 'fontSize' => 18]),
			$this->configuredPlacement(
				widgetId: 'object-list',
				content: ['register' => 'dossiq', 'schema' => 'case', 'limit' => 10, 'filter' => ['assignee' => '@me']]
			),
			$this->configuredPlacement(widgetId: 'nc-widget', content: ['widgetId' => 'tasks', 'displayMode' => 'vertical']),
			$this->configuredTile(),
		];

		$source = new Dashboard();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$source->setId(7);
		$source->setUuid('round-trip-source');
		$source->setName('Round trip');
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters

		$exportPlacements = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$exportPlacements->method('findByDashboardId')->willReturn($sources);
		$exporter = new ExportService(
			dashboardMapper: $this->createMock(originalClassName: DashboardMapper::class),
			placementMapper: $exportPlacements,
			groupManager: $this->createMock(originalClassName: IGroupManager::class),
			logger: new NullLogger(),
		);

		$payload = $exporter->serializeDashboard(dashboard: $source);
		$zipPath = $this->makeZip(entries: [
			'manifest.json' => (string)json_encode(
				value: $exporter->buildManifest(scope: 'dashboard', dashboardCount: 1, currentUserId: 'admin')
			),
			'dashboards/round-trip-source.json' => (string)json_encode(value: $payload),
			'metadata-fields.json' => '[]',
		]);

		$this->dashboardMapper->method('findByUuid')
			->willThrowException(exception: new DoesNotExistException(msg: 'no'));
		$inserted = new Dashboard();
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$inserted->setId(99);
		$this->dashboardMapper->method('insert')->willReturn($inserted);

		$imported = [];
		$this->placementMapper->method('insert')->willReturnCallback(
			static function (WidgetPlacement $placement) use (&$imported): WidgetPlacement {
				$imported[] = $placement;
				return $placement;
			}
		);

		try {
			$result = $this->service->import(zipPath: $zipPath, preserveUuids: false, currentUserId: 'admin');
		} finally {
			@unlink(filename: $zipPath);
		}

		$this->assertSame(expected: 1, actual: $result['importedDashboardCount']);
		$this->assertCount(expectedCount: 4, haystack: $imported);

		// Spot checks first, so a failure reads as the defect rather than a diff.
		$this->assertSame(expected: 'Welcome to the desk', actual: $imported[0]->getContentArray()['text'] ?? null);
		$this->assertSame(expected: 'dossiq', actual: $imported[1]->getContentArray()['register'] ?? null);
		$this->assertSame(expected: 'tasks', actual: $imported[2]->getContentArray()['widgetId'] ?? null);
		$this->assertSame(expected: 'shortcut', actual: $imported[3]->getTileType());
		$this->assertSame(expected: '/apps/files', actual: $imported[3]->getTileLinkValue());

		$this->assertSame(
			expected: array_map(callback: [$this, 'travelling'], array: $sources),
			actual: array_map(callback: [$this, 'travelling'], array: $imported),
			message: 'a field export writes did not survive import'
		);
	}//end testExportThenImportKeepsEveryWidgetsConfiguration()

	/**
	 * The part of a placement that should survive an export-import round trip.
	 *
	 * Everything `jsonSerialize()` writes, minus the fields that name a row or
	 * a workflow on the exporting instance (PlacementPayloadHydrator's
	 * "deliberately not carried" list) and the timestamps a new row gets.
	 *
	 * @param WidgetPlacement $placement The placement.
	 *
	 * @return array<string, mixed>
	 */
	public function travelling(WidgetPlacement $placement): array {
		$data = json_decode(json: (string)json_encode(value: $placement->jsonSerialize()), associative: true);
		foreach ([
			'id', 'dashboardId', 'createdAt', 'updatedAt', 'templatePlacementId', 'isCompulsory',
			'requiresAcknowledgement', 'acknowledgementPrompt', 'acknowledgementDeadline',
			'reacknowledgeOnChange', 'acknowledgementContentVersion', 'announcementKey',
		] as $instanceBound) {
			unset($data[$instanceBound]);
		}

		return $data;
	}//end travelling()

	/**
	 * A widget placement configured the way a user configures one.
	 *
	 * @param string               $widgetId The widget type.
	 * @param array<string, mixed> $content  Its configuration.
	 *
	 * @return WidgetPlacement
	 */
	private function configuredPlacement(string $widgetId, array $content): WidgetPlacement {
		$placement = new WidgetPlacement();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$placement->setWidgetId($widgetId);
		$placement->setGridX(2);
		$placement->setGridY(3);
		$placement->setGridWidth(6);
		$placement->setGridHeight(5);
		$placement->setIsVisible(1);
		$placement->setShowTitle(1);
		$placement->setSortOrder(4);
		$placement->setCustomTitle('Title of ' . $widgetId);
		$placement->setCustomIcon('Star');
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$placement->setStyleConfigArray(config: ['backgroundColor' => '#123456']);
		$placement->setContentArray(content: $content);
		return $placement;
	}//end configuredPlacement()

	/**
	 * A tile placement with every tile field set.
	 *
	 * @return WidgetPlacement
	 */
	private function configuredTile(): WidgetPlacement {
		$tile = new WidgetPlacement();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$tile->setWidgetId('tile-files');
		$tile->setGridX(0);
		$tile->setGridY(0);
		$tile->setGridWidth(2);
		$tile->setGridHeight(2);
		$tile->setIsVisible(1);
		$tile->setShowTitle(0);
		$tile->setSortOrder(0);
		$tile->setTileType('shortcut');
		$tile->setTileTitle('Files');
		$tile->setTileIcon('Folder');
		$tile->setTileIconType('mdi');
		$tile->setTileBackgroundColor('#283593');
		$tile->setTileTextColor('#ffffff');
		$tile->setTileLinkType('url');
		$tile->setTileLinkValue('/apps/files');
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		return $tile;
	}//end configuredTile()

	/**
	 * Build a temporary ZIP archive containing the provided entries.
	 *
	 * @param array<string, string> $entries Map of archive name to bytes.
	 *
	 * @return string Path to the temp ZIP file.
	 */
	private function makeZip(array $entries): string {
		$path = (string)tempnam(directory: sys_get_temp_dir(), prefix: 'launchpad-import-test-');
		$zip = new ZipArchive();
		$zip->open(filename: $path, flags: ZipArchive::OVERWRITE);
		foreach ($entries as $name => $content) {
			$zip->addFromString(name: $name, content: $content);
		}
		$zip->close();
		return $path;
	}
}
