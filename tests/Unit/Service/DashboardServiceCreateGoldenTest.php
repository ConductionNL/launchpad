<?php

/**
 * DashboardService create-paths golden test
 *
 * Pins the rows that createDashboard, createGroupShared,
 * ensureDefaultDashboard and the fork path write through a real
 * DashboardFactory, and the tree checks they run, against a recorded
 * fixture.
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

use OCA\LaunchPad\Controller\DashboardApiServices;
use OCA\LaunchPad\Controller\DashboardApiHistory;
use OCA\LaunchPad\Controller\RequestActor;
use OCA\LaunchPad\Service\DashboardOutlets;
use OCA\LaunchPad\Service\DashboardLimits;
use OCA\LaunchPad\Service\DashboardPlatform;
use OCA\LaunchPad\Service\DashboardStructure;
use OCA\LaunchPad\Service\DashboardBuilders;
use OCA\LaunchPad\Service\DashboardStores;
use OCA\LaunchPad\Controller\DashboardApiController;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\AnalyticsService;
use OCA\LaunchPad\Service\DashboardFactory;
use OCA\LaunchPad\Service\DashboardResolver;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\PersonalLayerService;
use OCA\LaunchPad\Service\TemplateService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * The create paths' rows, case by case.
 *
 * @covers \OCA\LaunchPad\Service\DashboardService
 * @covers \OCA\LaunchPad\Controller\DashboardApiController
 * @uses   \OCA\LaunchPad\Service\DashboardFactory
 * @uses   \OCA\LaunchPad\Controller\ResponseHelper
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Db\WidgetPlacement
 * @uses   \OCA\LaunchPad\Service\SlugGenerator
 * @uses   \OCA\LaunchPad\Service\DashboardStores
 * @uses   \OCA\LaunchPad\Service\DashboardBuilders
 * @uses   \OCA\LaunchPad\Service\DashboardStructure
 * @uses   \OCA\LaunchPad\Service\DashboardPlatform
 * @uses   \OCA\LaunchPad\Service\DashboardLimits
 * @uses   \OCA\LaunchPad\Service\DashboardOutlets
 * @uses   \OCA\LaunchPad\Controller\RequestActor
 * @uses   \OCA\LaunchPad\Controller\DashboardApiHistory
 * @uses   \OCA\LaunchPad\Controller\DashboardApiServices
 */
class DashboardServiceCreateGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/dashboard-service-create.json';

	/**
	 * What the doubles saw.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $log = [];

	/**
	 * The createDashboard() argument sets, by case name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function inputs(): array {
		return [
			'minimal'     => ['userId' => 'alice', 'name' => 'Board'],
			'description' => ['userId' => 'alice', 'name' => 'Board', 'description' => 'About'],
			'icon'        => ['userId' => 'alice', 'name' => 'Board', 'icon' => 'star'],
			'empty icon'  => ['userId' => 'alice', 'name' => 'Board', 'icon' => ''],
			'tree'        => ['userId' => 'alice', 'name' => 'Child', 'parentUuid' => 'p-1', 'slug' => 'kid', 'sortOrder' => 3],
			'no slug'     => ['userId' => 'alice', 'name' => '***'],
			'seeded'      => ['userId' => 'bob', 'name' => 'Seeded', 'seedDefaults' => true],
			'not seeded'  => ['userId' => 'bob', 'name' => 'Plain', 'seedDefaults' => false],
		];
	}//end inputs()

	/**
	 * Call createDashboard() with one argument set.
	 *
	 * @param DashboardService     $service The service.
	 * @param array<string, mixed> $args    The case's named arguments.
	 *
	 * @return Dashboard
	 */
	private static function create(DashboardService $service, array $args): Dashboard {
		return $service->createDashboard(
			userId: $args['userId'],
			name: $args['name'],
			options: array_diff_key($args, ['userId' => true, 'name' => true])
		);
	}//end create()

	/**
	 * A service over a real factory and recording doubles.
	 *
	 * @return DashboardService
	 */
	private function service(): DashboardService {
		$mapper = $this->createMock(DashboardMapper::class);
		$mapper->method('insert')->willReturnCallback(
			function (Dashboard $dashboard): Dashboard {
				$row = $dashboard->jsonSerialize();
				unset($row['uuid'], $row['createdAt'], $row['updatedAt']);
				$this->log[] = ['insert' => $row];
				$dashboard->setId(50 + count($this->log));
				return $dashboard;
			}
		);
		$mapper->method('deactivateAllForUser')->willReturnCallback(
			function (string $userId): void {
				$this->log[] = ['deactivateAllForUser' => $userId];
			}
		);
		$mapper->method('findByGroup')->willReturn([]);

		$placements = $this->createMock(WidgetPlacementMapper::class);
		$placements->method('insert')->willReturnCallback(
			function (WidgetPlacement $placement): WidgetPlacement {
				$this->log[] = ['placement' => [$placement->getDashboardId(), $placement->getWidgetId(), $placement->getGridX(), $placement->getGridY(), $placement->getGridWidth(), $placement->getGridHeight()]];
				return $placement;
			}
		);
		$placements->method('cloneToDashboard')->willReturnCallback(
			function (int $sourceDashboardId, int $targetDashboardId): int {
				$this->log[] = ['clone' => [$sourceDashboardId, $targetDashboardId]];
				return 0;
			}
		);

		$tree = $this->createMock(DashboardTreeService::class);
		$tree->method('validateParent')->willReturnCallback(
			function (?string $movingUuid, ?string $newParentUuid): void {
				$this->log[] = ['validateParent' => [$movingUuid, $newParentUuid]];
			}
		);
		$tree->method('validateSlugUnique')->willReturnCallback(
			function (?string $parentUuid, string $slug): void {
				$this->log[] = ['validateSlugUnique' => [$parentUuid, $slug]];
			}
		);

		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturn(true);
		$settings = $this->createMock(AdminSettingMapper::class);
		$settings->method('getValue')->willReturn(true);

		return new DashboardService(
			stores: new DashboardStores(
				dashboardMapper: $mapper,
				placementMapper: $placements,
				settingMapper: $settings
			),
			builders: new DashboardBuilders(
				templateService: $this->createMock(TemplateService::class),
				dashboardFactory: new DashboardFactory(),
				dashResolver: $this->createMock(DashboardResolver::class),
				adminTemplateService: $this->createMock(AdminTemplateService::class)
			),
			structure: new DashboardStructure(
				treeService: $tree,
				personalLayers: $this->createMock(PersonalLayerService::class)
			),
			platform: new DashboardPlatform(
				groupManager: $groups,
				db: $this->createMock(IDBConnection::class),
				config: $this->createMock(IConfig::class),
				l10nFactory: $this->createMock(IFactory::class)
			),
			outlets: new DashboardOutlets(
				logger: $this->createMock(LoggerInterface::class)
			)
		);
	}//end service()

	/**
	 * The dashboard API controller for alice over the recording service.
	 *
	 * @return DashboardApiController
	 */
	private function controller(): DashboardApiController {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('canCreateDashboard')->willReturn(true);
		$permissions->method('canHaveMultipleDashboards')->willReturn(true);

		return new DashboardApiController(
			request: $this->createMock(IRequest::class),
			services: new DashboardApiServices(
				dashboardService: $this->service()
			),
			permissionService: $permissions,
			history: new DashboardApiHistory(
				treeService: $this->createMock(DashboardTreeService::class),
				versionService: $this->createMock(DashboardVersionService::class),
				analyticsService: $this->createMock(AnalyticsService::class),
				logger: $this->createMock(LoggerInterface::class)
			),
			actor: new RequestActor(
				userSession: $session,
				actionAuth: $this->createMock(ActionAuthService::class)
			),
			userId: 'alice'
		);
	}//end controller()

	/**
	 * Every path writes the recorded rows in the recorded order.
	 *
	 * @return void
	 */
	public function testRowsMatchTheRecordedFixture(): void {
		$actual = [];
		foreach (self::inputs() as $case => $args) {
			$this->log = [];
			$returned  = self::create(service: $this->service(), args: $args);
			$actual['createDashboard '.$case] = ['log' => $this->log, 'icon' => $returned->getIcon()];
		}

		$this->log = [];
		$response  = $this->controller()->create(name: 'Posted', description: 'p', icon: 'i', parentUuid: 'p-9', slug: 'posted', sortOrder: 2);
		$actual['controller create'] = ['status' => $response->getStatus(), 'log' => $this->log];

		$this->log = [];
		$response  = $this->controller()->create(name: ['name' => 'Body', 'sortOrder' => '5', 'icon' => 7]);
		$actual['controller create body'] = ['status' => $response->getStatus(), 'log' => $this->log];

		$this->log = [];
		$this->service()->createGroupShared(actorUserId: 'root', groupId: 'staff', name: 'Team', description: 'd', gridColumns: 8);
		$actual['createGroupShared'] = $this->log;

		$this->log = [];
		$this->service()->createGroupShared(actorUserId: 'root', groupId: 'staff', name: 'Team');
		$actual['createGroupShared defaults'] = $this->log;

		$this->log = [];
		$this->service()->ensureDefaultDashboard();
		$actual['ensureDefaultDashboard'] = $this->log;

		$source = new Dashboard();
		$source->setId(9);
		$source->setDescription('src');
		$source->setGridColumns(6);
		$this->log = [];
		$fork      = new ReflectionMethod(DashboardService::class, 'persistFork');
		$fork->invoke($this->service(), 'carol', $source, 'My copy');
		$actual['persistFork'] = $this->log;

		$this->log = [];
		$bootstrap = new ReflectionMethod(DashboardService::class, 'tryCreateFromTemplate');
		$bootstrap->invoke($this->service(), 'dave');
		$actual['tryCreateFromTemplate'] = $this->log;

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, self::compact(rows: $actual));
			$this->markTestIncomplete('Fixture recorded.');
		}

		$expected = json_decode((string) file_get_contents(self::FIXTURE));
		foreach ($expected as $case => $result) {
			$this->assertSame(json_encode($result), json_encode($actual[$case]), $case);
		}

		$this->assertSame(array_keys((array) $expected), array_keys($actual));
	}//end testRowsMatchTheRecordedFixture()

	/**
	 * The fixture text: one line per case.
	 *
	 * @param array<string, mixed> $rows The recorded cases.
	 *
	 * @return string
	 */
	private static function compact(array $rows): string {
		$lines = [];
		foreach ($rows as $case => $row) {
			$lines[] = json_encode((string) $case, JSON_UNESCAPED_UNICODE).': '.json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}

		return "{\n".implode(",\n", $lines)."\n}\n";
	}//end compact()
}//end class
