<?php

/**
 * The two Zuiddrecht showcases are the group dashboards the LpIntranet and
 * LpManagement boards draw: the intranet every colleague may add widgets to,
 * and the management information everybody may only look at.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/demo-data-showcases/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Service;

use OCA\LaunchPad\Service\DemoShowcasesService;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Reads the shipped Zuiddrecht showcase archives from disk.
 */
class ZuiddrechtShowcasesTest extends TestCase {

	/**
	 * The one dashboard payload of a bundled showcase.
	 *
	 * @param string $showcaseId The showcase id.
	 *
	 * @return array<string, mixed> The dashboard payload
	 */
	private static function dashboard(string $showcaseId): array {
		$path = dirname(path: __DIR__, levels: 3) . '/data/demo-showcases/' . $showcaseId . '/' . $showcaseId . '.zip';
		self::assertFileExists(filename: $path);

		$zip = new ZipArchive();
		self::assertTrue(condition: $zip->open(filename: $path) === true);
		$payload = null;
		for ($i = 0; $i < $zip->numFiles; $i++) {
			$name = (string)$zip->getNameIndex(index: $i);
			if (str_starts_with(haystack: $name, needle: 'dashboards/') === true) {
				$payload = json_decode(json: (string)$zip->getFromName(name: $name), associative: true);
			}
		}

		$zip->close();
		self::assertIsArray(actual: $payload);
		return $payload;
	}//end dashboard()

	/**
	 * Both showcases are bundled.
	 *
	 * @return void
	 */
	public function testBothShowcasesAreBundled(): void {
		$this->assertContains(needle: 'zuiddrecht-intranet', haystack: DemoShowcasesService::BUNDLED_IDS);
		$this->assertContains(needle: 'zuiddrecht-management', haystack: DemoShowcasesService::BUNDLED_IDS);
	}//end testBothShowcasesAreBundled()

	/**
	 * The intranet is a group dashboard everyone may add widgets to, with
	 * the announcement and the word from the town clerk.
	 *
	 * @return void
	 */
	public function testTheIntranetLetsColleaguesAddWidgets(): void {
		$intranet = self::dashboard(showcaseId: 'zuiddrecht-intranet');

		$this->assertSame(
			expected: ['Intranet Zuiddrecht', 'group_shared', 'default', 'add_only'],
			actual: [$intranet['name'], $intranet['type'], $intranet['groupId'], $intranet['permissionLevel']]
		);
		$this->assertNull(actual: $intranet['userId']);
		$titles = array_column($intranet['widgets'], 'customTitle');
		$this->assertContains(needle: 'Aankondiging', haystack: $titles);
		$this->assertContains(needle: 'Van de gemeentesecretaris', haystack: $titles);
	}//end testTheIntranetLetsColleaguesAddWidgets()

	/**
	 * The management dashboard is view only and counts live cases.
	 *
	 * @return void
	 */
	public function testTheManagementDashboardIsViewOnlyAndCountsCases(): void {
		$management = self::dashboard(showcaseId: 'zuiddrecht-management');

		$this->assertSame(
			expected: ['Managementinformatie', 'group_shared', 'view_only'],
			actual: [$management['name'], $management['type'], $management['permissionLevel']]
		);
		$sources = [];
		foreach ($management['widgets'] as $widget) {
			if ($widget['widgetId'] === 'stat') {
				$sources[] = $widget['content']['source']['register'] . '/' . $widget['content']['source']['schema'];
			}
		}

		$this->assertContains(needle: 'dossiq/case', haystack: $sources);
	}//end testTheManagementDashboardIsViewOnlyAndCountsCases()
}//end class
