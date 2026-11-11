<?php

/**
 * PermissionServiceAccessGoldenTest
 *
 * Characterisation test pinning every allowed AND refused outcome of
 * PermissionService's public checks over a fixed matrix of users,
 * dashboards and placements, so the checks can be split into named steps
 * without changing a single decision.
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

use Exception;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardShareService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\RoleService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;
use PHPUnit\Framework\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Mirrors the constructor.
 */
class PermissionServiceAccessGoldenTest extends TestCase {
	private const USERS = ['alice', 'viewer', 'admin', 'ncadmin', 'editor', 'member', 'sharee', 'stranger'];

	private const DASHBOARDS = [1, 2, 3, 4, 5, 6, 8, 99];

	private const PLACEMENTS = [10, 11, 12, 13, 14, 20, 22, 98];

	private PermissionService $service;

	/** @var array<int, Dashboard> */
	private array $dashboards = [];

	protected function setUp(): void {
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$this->dashboards = [
			1 => $this->dashboard(1, Dashboard::TYPE_USER, 'alice', Dashboard::PERMISSION_FULL),
			2 => $this->dashboard(2, Dashboard::TYPE_USER, 'alice', Dashboard::PERMISSION_ADD_ONLY),
			3 => $this->dashboard(3, Dashboard::TYPE_GROUP_SHARED, 'admin', Dashboard::PERMISSION_FULL, 'g1'),
			4 => $this->dashboard(4, Dashboard::TYPE_GROUP_SHARED, 'admin', Dashboard::PERMISSION_FULL, Dashboard::DEFAULT_GROUP_ID),
			5 => $this->dashboard(5, Dashboard::TYPE_ADMIN_TEMPLATE, 'ncadmin', Dashboard::PERMISSION_FULL),
			6 => $this->dashboard(6, Dashboard::TYPE_USER, 'alice', Dashboard::PERMISSION_FULL, null, 7),
			7 => $this->dashboard(7, Dashboard::TYPE_ADMIN_TEMPLATE, 'ncadmin', Dashboard::PERMISSION_VIEW_ONLY),
			8 => $this->dashboard(8, Dashboard::TYPE_USER, 'alice', '', null, 97),
		];
		$placements = [
			10 => $this->placement(10, 1, 0, null),
			11 => $this->placement(11, 2, 1, null),
			12 => $this->placement(12, 2, 0, null),
			13 => $this->placement(13, 99, 0, null),
			14 => $this->placement(14, 3, 0, null),
			20 => $this->placement(20, 1, 0, 'k1'),
			21 => $this->placement(21, 2, 0, 'k1'),
			22 => $this->placement(22, 2, 0, 'k2'),
			23 => $this->placement(23, 5, 0, 'k2'),
			24 => $this->placement(24, 99, 0, 'k1'),
		];
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters

		$dashboards = $this->createMock(DashboardMapper::class);
		$dashboards->method('find')->willReturnCallback(
			fn (int $id): Dashboard => $this->dashboards[$id] ?? throw new DoesNotExistException(msg: 'no dashboard ' . $id)
		);

		$placementMapper = $this->createMock(WidgetPlacementMapper::class);
		$placementMapper->method('find')->willReturnCallback(
			static fn (int $id): WidgetPlacement => $placements[$id] ?? throw new DoesNotExistException(msg: 'no placement ' . $id)
		);
		$placementMapper->method('findByAnnouncementKey')->willReturnCallback(
			static fn (string $key): array => array_values(array_filter(
				[$placements[24], $placements[21], $placements[20], $placements[22], $placements[23]],
				static fn (WidgetPlacement $p): bool => $p->getAnnouncementKey() === $key
			))
		);

		$roles = $this->createMock(RoleService::class);
		$roles->method('isViewer')->willReturnCallback(static fn (string $u): bool => $u === 'viewer');
		$roles->method('isAdmin')->willReturnCallback(static fn (string $u): bool => $u === 'admin');
		$roles->method('isEditorOrHigher')->willReturnCallback(static fn (string $u): bool => in_array($u, ['admin', 'editor'], true));

		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $u): bool => $u === 'ncadmin');

		$templates = $this->createMock(AdminTemplateService::class);
		$templates->method('getUserGroupIdsFor')->willReturnCallback(
			static fn (string $u): array => (in_array($u, ['viewer', 'editor', 'member'], true) === true ? ['g1'] : ['g2'])
		);

		$shares = $this->createMock(DashboardShareService::class);
		$shares->method('resolveSharedDashboards')->willReturnCallback(
			static fn (string $u, array $groupIds): array => ($u === 'sharee' && $groupIds === ['g2'] ? [1 => Dashboard::PERMISSION_VIEW_ONLY, 2 => Dashboard::PERMISSION_ADD_ONLY, 6 => Dashboard::PERMISSION_FULL] : [])
		);

		$settings = $this->createMock(AdminSettingMapper::class);
		$settings->method('getValue')->willReturnCallback(
			static fn (string $key, mixed $default = null): mixed => match ($key) {
				AdminSetting::KEY_DEFAULT_PERMISSION_LEVEL => Dashboard::PERMISSION_ADD_ONLY,
				AdminSetting::KEY_ALLOW_USER_DASHBOARDS => false,
				default => $default,
			}
		);

		$this->service = new PermissionService(
			dashboardMapper: $dashboards,
			placementMapper: $placementMapper,
			settingMapper: $settings,
			shareService: $shares,
			groupManager: $groups,
			adminTemplateService: $templates,
			roleService: $roles,
		);
	}//end setUp()

	// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
	private function dashboard(int $id, string $type, string $owner, string $level, ?string $groupId = null, ?int $template = null): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId($id);
		$dashboard->setType($type);
		$dashboard->setUserId($owner);
		$dashboard->setPermissionLevel($level);
		$dashboard->setGroupId($groupId);
		$dashboard->setBasedOnTemplate($template);
		return $dashboard;
	}

	private function placement(int $id, int $dashboardId, int $compulsory, ?string $key): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setId($id);
		$placement->setDashboardId($dashboardId);
		$placement->setIsCompulsory($compulsory);
		$placement->setAnnouncementKey($key);
		return $placement;
	}
	// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters

	/**
	 * One row per user: the outcome letters of a check per dashboard or placement.
	 *
	 * @param callable(string, int): mixed $check The check.
	 * @param array<int, int>               $ids   The ids to run it on.
	 *
	 * @return array<string, string>
	 */
	private function matrix(callable $check, array $ids): array {
		$rows = [];
		foreach (self::USERS as $user) {
			$cells = [];
			foreach ($ids as $id) {
				$cells[] = $this->letter($check($user, $id));
			}
			$rows[$user] = implode(' ', $cells);
		}
		return $rows;
	}

	private function letter(mixed $outcome): string {
		return match ($outcome) {
			true => 'Y',
			false => 'n',
			null => '-',
			Dashboard::PERMISSION_FULL => 'F',
			Dashboard::PERMISSION_ADD_ONLY => 'A',
			Dashboard::PERMISSION_VIEW_ONLY => 'V',
			default => '?' . (string)$outcome,
		};
	}

	/**
	 * The matrices of every check, keyed by check name.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function allMatrices(): array {
		$s = $this->service;
		return [
			'resolveAccessLevel(id)' => $this->matrix(static fn (string $u, int $d): ?string => $s->resolveAccessLevel(userId: $u, dashboardId: $d), self::DASHBOARDS),
			'resolveAccessLevel(entity)' => $this->matrix(fn (string $u, int $d): ?string => ($d === 99 ? null : $s->resolveAccessLevel(userId: $u, dashboard: $this->dashboards[$d])), self::DASHBOARDS),
			'getEffectivePermissionLevel' => $this->matrix(fn (string $u, int $d): ?string => ($d === 99 ? null : $s->getEffectivePermissionLevel(dashboard: $this->dashboards[$d], userId: $u)), self::DASHBOARDS),
			'canViewDashboard' => $this->matrix(static fn (string $u, int $d): bool => $s->canViewDashboard(userId: $u, dashboardId: $d), self::DASHBOARDS),
			'canEditDashboard' => $this->matrix(static fn (string $u, int $d): bool => $s->canEditDashboard(userId: $u, dashboardId: $d), self::DASHBOARDS),
			'canEditDashboardMetadata' => $this->matrix(static fn (string $u, int $d): bool => $s->canEditDashboardMetadata(userId: $u, dashboardId: $d), self::DASHBOARDS),
			'canAddWidget' => $this->matrix(static fn (string $u, int $d): bool => $s->canAddWidget(userId: $u, dashboardId: $d), self::DASHBOARDS),
			'canRemoveWidget' => $this->matrix(static fn (string $u, int $p): bool => $s->canRemoveWidget(userId: $u, placementId: $p), self::PLACEMENTS),
			'canViewPlacement' => $this->matrix(static fn (string $u, int $p): bool => $s->canViewPlacement(userId: $u, placementId: $p), self::PLACEMENTS),
			'canStyleWidget' => $this->matrix(static fn (string $u, int $p): bool => $s->canStyleWidget(userId: $u, placementId: $p), self::PLACEMENTS),
			'canManageAcknowledgement' => $this->matrix(static fn (string $u, int $p): bool => $s->canManageAcknowledgement(userId: $u, placementId: $p), self::PLACEMENTS),
			'canCreateDashboard' => $this->matrix(static fn (string $u, int $d): bool => $s->canCreateDashboard(userId: $u), [0]),
		];
	}

	public function testEveryOutcomeMatchesTheGolden(): void {
		$this->assertSame(self::golden(), $this->allMatrices());
	}

	public function testEffectiveLevelWithoutUserOnGroupSharedIsViewOnly(): void {
		$this->assertSame(
			Dashboard::PERMISSION_VIEW_ONLY,
			$this->service->getEffectivePermissionLevel(dashboard: $this->dashboards[3])
		);
	}

	public function testOwnershipVerification(): void {
		$this->assertSame($this->dashboards[1], $this->service->verifyDashboardOwnership(userId: 'alice', dashboardId: 1));
		$this->assertSame(10, $this->service->verifyPlacementOwnership(userId: 'alice', placementId: 10)->getId());

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('Access denied');
		$this->service->verifyPlacementOwnership(userId: 'stranger', placementId: 10);
	}

	public function testOwnershipVerificationOfAMissingDashboardThrows(): void {
		$this->expectException(DoesNotExistException::class);
		$this->service->verifyDashboardOwnership(userId: 'alice', dashboardId: 99);
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	private static function golden(): array {
		return require __DIR__ . '/PermissionServiceAccessGolden.php';
	}
}
