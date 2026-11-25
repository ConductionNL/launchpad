<?php

/**
 * PermissionServiceDefaultLevelTest
 *
 * With no default permission level stored, a dashboard without a level of its
 * own resolves to the factory default add_only, the value
 * GET /api/admin/settings reports (REQ-ASET-001 "Factory default permission
 * (add_only)"). It used to resolve to full, so a compulsory widget could be
 * removed while the settings page said add_only.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/admin-settings/spec.md#requirement-retrieve-admin-settings-req-aset-001
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardShareService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\RoleService;
use OCP\IGroupManager;
use PHPUnit\Framework\TestCase;

/**
 * The default level a dashboard without a level of its own resolves to.
 */
class PermissionServiceDefaultLevelTest extends TestCase {

	/**
	 * A service whose setting store answers $stored for the default level, or its default when null.
	 *
	 * @param string|null $stored The stored default level, or null for no row.
	 *
	 * @return PermissionService The service.
	 */
	private function serviceWith(?string $stored): PermissionService {
		$settings = $this->createMock(originalClassName: AdminSettingMapper::class);
		$settings->method('getValue')->willReturnCallback(
			static function (string $key, mixed $default = null) use ($stored): mixed {
				if ($key === AdminSetting::KEY_DEFAULT_PERMISSION_LEVEL && $stored !== null) {
					return $stored;
				}

				return $default;
			}
		);

		return new PermissionService(
			dashboardMapper: $this->createMock(originalClassName: DashboardMapper::class),
			placementMapper: $this->createMock(originalClassName: WidgetPlacementMapper::class),
			settingMapper: $settings,
			shareService: $this->createMock(originalClassName: DashboardShareService::class),
			groupManager: $this->createMock(originalClassName: IGroupManager::class),
			adminTemplateService: $this->createMock(originalClassName: AdminTemplateService::class),
			roleService: $this->createMock(originalClassName: RoleService::class),
		);
	}//end serviceWith()

	/**
	 * A personal dashboard with no level of its own and no template.
	 *
	 * @return Dashboard The dashboard.
	 */
	private function levelless(): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setType(Dashboard::TYPE_USER);
		$dashboard->setPermissionLevel('');
		return $dashboard;
	}//end levelless()

	/**
	 * No stored default: add_only, not full.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/admin-settings/spec.md#requirement-retrieve-admin-settings-req-aset-001
	 */
	public function testWithNoStoredDefaultTheLevelIsAddOnly(): void {
		$level = $this->serviceWith(stored: null)->getEffectivePermissionLevel(dashboard: $this->levelless());
		$this->assertSame(expected: Dashboard::PERMISSION_ADD_ONLY, actual: $level);
	}//end testWithNoStoredDefaultTheLevelIsAddOnly()

	/**
	 * A stored default still wins.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/admin-settings/spec.md#requirement-retrieve-admin-settings-req-aset-001
	 */
	public function testAStoredDefaultWins(): void {
		$level = $this->serviceWith(stored: Dashboard::PERMISSION_FULL)->getEffectivePermissionLevel(dashboard: $this->levelless());
		$this->assertSame(expected: Dashboard::PERMISSION_FULL, actual: $level);
	}//end testAStoredDefaultWins()
}//end class
