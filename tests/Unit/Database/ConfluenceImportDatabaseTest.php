<?php

/**
 * ConfluenceImportDatabaseTest
 *
 * A Confluence import, through ConfluenceImportService, against a real
 * database: each page must land as a dashboard with its placeholder widget.
 *
 * 🔴 WHY. The placeholder widget was built without `created_at` or
 * `updated_at`, both NOT NULL with no default, so on PostgreSQL every page's
 * placement insert failed with SQLSTATE 23502, the page's transaction rolled
 * back, and the import reported "Imported 0 dashboards, skipped 1" while
 * exiting 0. No test wrote an import to a database: the Confluence tests
 * covered parsing only. It is the #612 importer bug again, in the other
 * importer.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Database
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Database;

use OCA\LaunchPad\Service\ConfluenceImportService;
use OCP\Server;
use ZipArchive;

/**
 * @SuppressWarnings(PHPMD.StaticAccess) Server::get() is the only way in from a test.
 */
class ConfluenceImportDatabaseTest extends RealDatabaseTestCase {
	/**
	 * A one-page export lands as one dashboard with its placeholder widget.
	 *
	 * @return void
	 */
	public function testAOnePageExportLandsAsADashboardWithItsWidget(): void {
		$owner = $this->makeUser(prefix: 'db-confluence');
		$this->cleanup(function () use ($owner): void {
			foreach ($this->storedRows('launchpad_dashboards', 'user_id', $owner) as $row) {
				$this->deleteRows('launchpad_widget_placements', 'dashboard_id', (int)$row['id']);
			}

			$this->deleteRows('launchpad_dashboards', 'user_id', $owner);
		});

		$result = Server::get(ConfluenceImportService::class)->import(
			zipPath: $this->onePageExport(),
			currentUserId: $owner
		);

		$this->assertSame([], $result['errors'], 'the import reported errors: ' . json_encode($result['errors']));
		$this->assertSame(1, $result['createdDashboardCount'], 'the page did not land as a dashboard');

		$dashboards = $this->storedRows('launchpad_dashboards', 'user_id', $owner);
		$this->assertCount(1, $dashboards, 'the dashboard row was not stored');
		$this->assertSame('Architecture', $dashboards[0]['name']);

		$placements = $this->storedRows('launchpad_widget_placements', 'dashboard_id', (int)$dashboards[0]['id']);
		$this->assertCount(1, $placements, 'the placeholder widget was not stored');
		$this->assertColumnsFilled(
			row: $placements[0],
			columns: ['dashboard_id', 'widget_id', 'created_at', 'updated_at']
		);
	}//end testAOnePageExportLandsAsADashboardWithItsWidget()

	/**
	 * Write a one-page Confluence HTML export, removed again in tearDown.
	 *
	 * @return string Path to the ZIP.
	 */
	private function onePageExport(): string {
		$path = (string)tempnam(sys_get_temp_dir(), 'launchpad-confluence-');
		$this->cleanup(static function () use ($path): void {
			if (file_exists($path) === true) {
				unlink($path);
			}
		});

		$zip = new ZipArchive();
		$zip->open($path, ZipArchive::OVERWRITE);
		$zip->addFromString('index.html', '<html><body><ul><li><a href="SPACE/page-1.html">P1</a></li></ul></body></html>');
		$zip->addFromString(
			'SPACE/page-1.html',
			'<html><head><title>SPACE : Architecture</title></head><body>'
			. '<div id="main-content"><p>Body of P1</p></div></body></html>'
		);
		$zip->close();

		return $path;
	}//end onePageExport()
}//end class
