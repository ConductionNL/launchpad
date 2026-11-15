<?php

/**
 * Admin settings update golden test
 *
 * Pins the setting rows updateSettings writes, in order, for each
 * combination of given settings, and where an invalid value stops the
 * write, against a recorded fixture.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\AdminSettingsService;
use PHPUnit\Framework\TestCase;

/**
 * The written setting rows, case by case.
 *
 * @covers \OCA\LaunchPad\Service\AdminSettingsService
 * @uses   \OCA\LaunchPad\Db\AdminSetting
 */
class AdminSettingsUpdateGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/admin-settings-update.json';

	/**
	 * Run one update with the given settings.
	 *
	 * @param AdminSettingsService $service  The service.
	 * @param array<string, mixed> $settings The settings.
	 *
	 * @return void
	 */
	private static function update(AdminSettingsService $service, array $settings): void {
		$service->updateSettings(settings: $settings);
	}//end update()

	/**
	 * Every case writes the recorded rows.
	 *
	 * @return void
	 */
	public function testWritesMatchTheRecordedFixture(): void {
		$all = [
			'defaultPermLevel'            => 'view_only',
			'allowUserDash'               => true,
			'allowMultiDash'              => false,
			'defaultGridCols'             => 6,
			'linkCreateFileExts'          => [' .TXT', 'md', 'md', '', 'Docx'],
			'defaultSharePermissionLevel' => 'full',
			'forcedShareGroups'           => ['staff', ' ', 'staff', 'board'],
			'legacyWidgetBridgeEnabled'   => true,
			'maxDashboardsPerUser'        => 20000,
			'maxWidgetsPerDashboard'      => -4,
			'quicksearchFallbackTarget'   => 'unified-search',
			'startPageWithoutNavigation'  => true,
		];
		$cases = [
			'nothing'            => [],
			'all'                => $all,
			'all false and zero' => ['allowUserDash' => false, 'allowMultiDash' => true, 'defaultGridCols' => 0, 'legacyWidgetBridgeEnabled' => false, 'startPageWithoutNavigation' => false, 'maxDashboardsPerUser' => 0],
			'nulls'              => array_map(static fn ($v) => null, $all),
			'empty lists'        => ['linkCreateFileExts' => [], 'forcedShareGroups' => []],
			'url fallback'       => ['quicksearchFallbackTarget' => 'https://example.org/?q={query}'],
			'bad fallback'       => ['defaultPermLevel' => 'full', 'quicksearchFallbackTarget' => 'http://x', 'startPageWithoutNavigation' => true],
			'bad share level'    => ['allowUserDash' => true, 'defaultSharePermissionLevel' => 'owner', 'startPageWithoutNavigation' => true],
			'only last'          => ['startPageWithoutNavigation' => true],
		];

		$actual = [];
		foreach ($cases as $name => $settings) {
			$writes = [];
			$mapper = $this->createMock(AdminSettingMapper::class);
			$mapper->method('setSetting')->willReturnCallback(
				static function (string $key, mixed $value) use (&$writes): AdminSetting {
					$writes[] = [$key, $value];
					return new AdminSetting();
				}
			);
			$error = null;
			try {
				self::update(new AdminSettingsService(settingMapper: $mapper), $settings);
			} catch (InvalidArgumentException $e) {
				$error = $e->getMessage();
			}
			$actual[$name] = ['writes' => $writes, 'error' => $error];
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
			$this->markTestIncomplete('Fixture recorded.');
		}

		$this->assertSame(json_decode((string) file_get_contents(self::FIXTURE), true), $actual);
	}//end testWritesMatchTheRecordedFixture()
}//end class
