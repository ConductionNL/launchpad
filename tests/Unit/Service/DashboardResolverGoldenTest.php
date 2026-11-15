<?php

/**
 * DashboardResolverGoldenTest
 *
 * Records the mapper calls and the result of the resolver's active,
 * activate and template rungs, including the effective permission level
 * inherited from a template and the view-only template fallback.
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

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\DashboardResolver;
use OCA\LaunchPad\Service\TemplateService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;

class DashboardResolverGoldenTest extends TestCase {

	/** @var list<string> */
	private array $log = [];

	/** @var array<string, list<Dashboard>> Dashboards by owner. */
	private array $owned = [];

	private DashboardResolver $resolver;

	protected function setUp(): void {
		$dashboards = $this->createMock(DashboardMapper::class);
		$dashboards->method('findActiveByUserId')->willReturnCallback(function (string $userId): Dashboard {
			$this->log[] = 'findActive ' . $userId;
			foreach ($this->owned[$userId] ?? [] as $dashboard) {
				if ((int)$dashboard->getIsActive() === 1) {
					return $dashboard;
				}
			}

			throw new DoesNotExistException('none');
		});
		$dashboards->method('findByUserId')->willReturnCallback(function (string $userId): array {
			$this->log[] = 'findByUser ' . $userId;
			return $this->owned[$userId] ?? [];
		});
		$dashboards->method('setActive')->willReturnCallback(function (int $id, string $userId): void {
			$this->log[] = 'setActive ' . $id . ' ' . $userId;
		});
		$dashboards->method('find')->willReturnCallback(function (int $id): Dashboard {
			$this->log[] = 'find ' . $id;
			if ($id !== 90) {
				throw new DoesNotExistException('gone');
			}

			return self::dashboard(90, null, Dashboard::PERMISSION_ADD_ONLY);
		});

		$placements = $this->createMock(WidgetPlacementMapper::class);
		$placements->method('findByDashboardId')->willReturnCallback(function (int $dashboardId): array {
			$this->log[] = 'placements ' . $dashboardId;
			$placement = new WidgetPlacement();
			$placement->setDashboardId($dashboardId);
			return [$placement];
		});

		$templates = $this->createMock(TemplateService::class);
		$templates->method('createDashboardFromTemplate')->willReturnCallback(function (string $userId, Dashboard $template): Dashboard {
			$this->log[] = 'createFromTemplate ' . $userId . ' ' . $template->getId();
			return self::dashboard(55, (int)$template->getId(), Dashboard::PERMISSION_FULL);
		});

		$this->resolver = new DashboardResolver(
			dashboardMapper: $dashboards,
			placementMapper: $placements,
			templateService: $templates
		);
	}

	private static function dashboard(int $id, ?int $basedOn, string $level, int $active = 0): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId($id);
		$dashboard->setBasedOnTemplate($basedOn);
		$dashboard->setPermissionLevel($level);
		$dashboard->setIsActive($active);
		return $dashboard;
	}

	/**
	 * @return array{0: mixed, 1: list<string>}
	 */
	private function record(callable $call): array {
		$this->log = [];
		$result = $call();
		if (is_array($result) === true) {
			$result = [
				$result['dashboard']->getId(),
				$result['dashboard']->getIsActive(),
				array_map(static fn (WidgetPlacement $p): int => (int)$p->getDashboardId(), $result['placements']),
				$result['permissionLevel'],
			];
		}

		return [$result, $this->log];
	}

	public function testActiveAndActivateRungs(): void {
		$this->assertSame([null, ['findActive bob']], $this->record(fn () => $this->resolver->tryGetActiveDashboard('bob')));
		$this->assertSame([null, ['findByUser bob']], $this->record(fn () => $this->resolver->tryActivateExistingDashboard('bob')));

		$this->owned['alice'] = [self::dashboard(3, 90, Dashboard::PERMISSION_FULL), self::dashboard(4, 77, Dashboard::PERMISSION_VIEW_ONLY, 1)];
		$this->assertSame(
			[[4, 1, [4], Dashboard::PERMISSION_VIEW_ONLY], ['findActive alice', 'placements 4', 'find 77']],
			$this->record(fn () => $this->resolver->tryGetActiveDashboard('alice'))
		);
		$this->assertSame(
			[[3, 1, [3], Dashboard::PERMISSION_ADD_ONLY], ['findByUser alice', 'setActive 3 alice', 'placements 3', 'find 90']],
			$this->record(fn () => $this->resolver->tryActivateExistingDashboard('alice'))
		);
	}

	public function testTemplateRung(): void {
		$template = self::dashboard(90, null, Dashboard::PERMISSION_ADD_ONLY);
		$this->assertSame(
			[[55, 0, [55], Dashboard::PERMISSION_ADD_ONLY], ['createFromTemplate alice 90', 'placements 55', 'find 90']],
			$this->record(fn () => $this->resolver->handleTemplateResult(template: $template, allowUserDashboards: true, userId: 'alice'))
		);
		$this->assertSame(
			[[90, 0, [90], Dashboard::PERMISSION_VIEW_ONLY], ['placements 90']],
			$this->record(fn () => $this->resolver->handleTemplateResult(template: $template, allowUserDashboards: false, userId: 'alice'))
		);
	}
}
