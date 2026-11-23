<?php

/**
 * DashboardHierarchiesCopyTest
 *
 * A copy of a dashboard keeps its hierarchies (REQ-CWF-011): a dashboard made
 * from a template, and a dashboard that went out through export and came back
 * through import. A malformed list in an archive is left out, not fatal. The
 * fork path is covered in DashboardServiceForkTest.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\ExportService;
use OCA\LaunchPad\Service\ImportService;
use OCA\LaunchPad\Service\TemplateService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IAppConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ZipArchive;

/**
 * Tests for the hierarchies of copied dashboards.
 *
 * @covers \OCA\LaunchPad\Service\TemplateService
 * @covers \OCA\LaunchPad\Service\ImportService
 */
class DashboardHierarchiesCopyTest extends TestCase {

	/**
	 * The nl-administrative descriptor as a dashboard stores it.
	 *
	 * @return array<string, mixed>
	 */
	private static function nlAdministrative(): array {
		return [
			'id'     => 'nl-administrative',
			'levels' => [
				['dimension' => 'provincieCode', 'label' => 'Province', 'parentDimension' => null],
				['dimension' => 'gemeenteCode', 'label' => 'Municipality', 'parentDimension' => 'provincieCode'],
				['dimension' => 'wijkCode', 'label' => 'District', 'parentDimension' => 'gemeenteCode'],
				['dimension' => 'buurtCode', 'label' => 'Neighbourhood', 'parentDimension' => 'wijkCode'],
			],
		];
	}//end nlAdministrative()

	/**
	 * A dashboard with the nl-administrative hierarchy.
	 *
	 * @param int    $dashboardId Its id.
	 * @param string $uuid        Its uuid.
	 *
	 * @return Dashboard
	 */
	private static function withHierarchy(int $dashboardId, string $uuid): Dashboard {
		$dashboard = new Dashboard();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$dashboard->setId($dashboardId);
		$dashboard->setUuid($uuid);
		$dashboard->setName('Wijken');
		$dashboard->setGridColumns(12);
		$dashboard->setPermissionLevel(Dashboard::PERMISSION_ADD_ONLY);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$dashboard->setHierarchiesArray(hierarchies: [self::nlAdministrative()]);
		return $dashboard;
	}//end withHierarchy()

	/**
	 * A dashboard made from a template drills down as the template does.
	 *
	 * @return void
	 */
	public function testADashboardFromATemplateKeepsItsHierarchies(): void {
		$mapper = $this->createMock(originalClassName: DashboardMapper::class);
		$mapper->method('insert')->willReturnCallback(
			static function (Dashboard $dashboard): Dashboard {
				// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
				$dashboard->setId(12);
				return $dashboard;
			}
		);
		$service = new TemplateService(
			dashboardMapper: $mapper,
			placementMapper: $this->createMock(originalClassName: WidgetPlacementMapper::class),
			adminTemplateService: $this->createMock(originalClassName: AdminTemplateService::class),
			appConfig: $this->createMock(originalClassName: IAppConfig::class),
		);

		$copy = $service->createDashboardFromTemplate(userId: 'alice', template: self::withHierarchy(dashboardId: 4, uuid: 'tpl'));

		$this->assertSame(expected: [self::nlAdministrative()], actual: $copy->getHierarchiesArray());
	}//end testADashboardFromATemplateKeepsItsHierarchies()

	/**
	 * Import the given dashboard payload and answer the inserted dashboard.
	 *
	 * @param array<string, mixed> $payload The dashboard file of the archive.
	 *
	 * @return Dashboard
	 */
	private function importPayload(array $payload): Dashboard {
		$dashboards = $this->createMock(originalClassName: DashboardMapper::class);
		$dashboards->method('findByUuid')->willThrowException(exception: new DoesNotExistException(msg: 'no'));
		$inserted = [];
		$dashboards->method('insert')->willReturnCallback(
			static function (Dashboard $dashboard) use (&$inserted): Dashboard {
				$inserted[] = $dashboard;
				// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
				$dashboard->setId(99);
				return $dashboard;
			}
		);
		$service = new ImportService(
			dashboardMapper: $dashboards,
			placementMapper: $this->createMock(originalClassName: WidgetPlacementMapper::class),
			db: $this->createMock(originalClassName: IDBConnection::class),
			logger: new NullLogger(),
		);

		$path = (string)tempnam(directory: sys_get_temp_dir(), prefix: 'launchpad-hierarchies-');
		$zip  = new ZipArchive();
		$zip->open(filename: $path, flags: ZipArchive::OVERWRITE);
		$zip->addFromString(name: 'manifest.json', content: (string)json_encode(value: ['schemaVersion' => 1, 'scope' => 'dashboard']));
		$zip->addFromString(name: 'dashboards/'.$payload['uuid'].'.json', content: (string)json_encode(value: $payload));
		$zip->close();
		try {
			$service->import(zipPath: $path, preserveUuids: false, currentUserId: 'admin');
		} finally {
			@unlink(filename: $path);
		}

		$this->assertCount(expectedCount: 1, haystack: $inserted);
		return $inserted[0];
	}//end importPayload()

	/**
	 * A dashboard exported and imported again keeps its hierarchies.
	 *
	 * @return void
	 */
	public function testAnExportedDashboardImportedAgainKeepsItsHierarchies(): void {
		$placements = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$placements->method('findByDashboardId')->willReturn([]);
		$exporter = new ExportService(
			dashboardMapper: $this->createMock(originalClassName: DashboardMapper::class),
			placementMapper: $placements,
			groupManager: $this->createMock(originalClassName: IGroupManager::class),
			logger: new NullLogger(),
		);

		$imported = $this->importPayload(payload: $exporter->serializeDashboard(dashboard: self::withHierarchy(dashboardId: 7, uuid: 'src')));

		$this->assertSame(expected: [self::nlAdministrative()], actual: $imported->getHierarchiesArray());
	}//end testAnExportedDashboardImportedAgainKeepsItsHierarchies()

	/**
	 * A malformed list in an archive is left out, and the dashboard still lands.
	 *
	 * @return void
	 */
	public function testAMalformedListInAnArchiveIsLeftOut(): void {
		$imported = $this->importPayload(payload: ['uuid' => 'bad', 'name' => 'Bad', 'hierarchies' => [['id' => 'x']], 'widgets' => []]);

		$this->assertSame(expected: [], actual: $imported->getHierarchiesArray());
		$this->assertSame(expected: 'Bad', actual: $imported->getName());
	}//end testAMalformedListInAnArchiveIsLeftOut()
}//end class
