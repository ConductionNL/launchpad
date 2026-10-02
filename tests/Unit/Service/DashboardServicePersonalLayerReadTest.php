<?php

/**
 * DashboardServicePersonalLayerReadTest
 *
 * The personal layer had a writer with fifteen tests and a reader with none.
 * `PersonalLayerServiceTest` proves `applyTo()` rearranges a list of
 * placements. Nothing proved the reader ever calls it, so the whole feature
 * could have stored layers and shown none of them while every test stayed
 * green: `DashboardService::withPersonalLayer()` returns the owner's
 * placements unchanged when the collaborator is missing, which is
 * indistinguishable from a reader who has no layer.
 *
 * These tests assert from the reader. They save a layer through the real
 * `PersonalLayerService`, then ask `DashboardService::getDashboardForUser()`
 * for the dashboard and check that what comes back is the reader's
 * arrangement, not the owner's. Both read paths are covered: the share path
 * (REQ-SHARE-004) and the ordinary `buildResult` path.
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

use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\PersonalLayer;
use OCA\LaunchPad\Db\PersonalLayerMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardFactory;
use OCA\LaunchPad\Service\DashboardResolver;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\PersonalLayerService;
use OCA\LaunchPad\Service\TemplateService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Reader-side tests for the personal layer.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) The service under test
 *                                                 wires three dashboard
 *                                                 scopes, so a test that
 *                                                 constructs it honestly
 *                                                 names every collaborator.
 */
class DashboardServicePersonalLayerReadTest extends TestCase {
	/**
	 * The dashboard id every fixture here uses.
	 *
	 * @var integer
	 */
	private const DASHBOARD_ID = 7;

	/**
	 * The person who composed the dashboard.
	 *
	 * @var string
	 */
	private const OWNER = 'owner';

	/**
	 * The person reading it, who owns nothing.
	 *
	 * @var string
	 */
	private const READER = 'reader';

	/**
	 * The fake layer store, keyed `userId|dashboardId`.
	 *
	 * @var array<string, PersonalLayer>
	 */
	private array $stored = [];

	/** @var DashboardMapper&MockObject */
	private $dashboardMapper;

	/** @var WidgetPlacementMapper&MockObject */
	private $placementMapper;

	/** @var DashboardResolver&MockObject */
	private $dashResolver;

	private PersonalLayerService $layers;

	private DashboardService $service;

	/**
	 * Build the service over doubles, with the REAL personal layer service
	 * on top of a fake store, so a saved layer travels the whole way from
	 * the writer to the reader.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->stored = [];

		$this->dashboardMapper = $this->getMockBuilder(DashboardMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findVisibleToUser'])
			->getMock();
		$this->placementMapper = $this->getMockBuilder(WidgetPlacementMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findByDashboardId'])
			->getMock();
		$this->dashResolver = $this->getMockBuilder(DashboardResolver::class)
			->disableOriginalConstructor()
			->onlyMethods(
				[
					'findSharedDashboards',
					'findSharedLevels',
					'buildResult',
					'tryGetActiveDashboard',
					'tryActivateExistingDashboard',
					'tryGetSharedDashboard',
				]
			)
			->getMock();

		$adminTemplateService = $this->getMockBuilder(AdminTemplateService::class)
			->disableOriginalConstructor()
			->onlyMethods(['getUserGroupIdsFor'])
			->getMock();
		$adminTemplateService->method('getUserGroupIdsFor')->willReturn([]);

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->willReturn(false);

		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturn('');

		// `buildResult` is the non-share read path. Echo the placements back
		// so the assertion reads what the SERVICE handed the resolver, which
		// is the value the layer has to have changed.
		$this->dashResolver->method('buildResult')->willReturnCallback(
			static function (Dashboard $dashboard, array $placements): array {
				return [
					'dashboard' => $dashboard,
					'placements' => $placements,
					'permissionLevel' => Dashboard::PERMISSION_FULL,
				];
			}
		);

		$this->layers = new PersonalLayerService($this->layerMapper());

		$this->service = new DashboardService(
			dashboardMapper: $this->dashboardMapper,
			placementMapper: $this->placementMapper,
			settingMapper: $this->createMock(AdminSettingMapper::class),
			templateService: $this->createMock(TemplateService::class),
			dashboardFactory: new DashboardFactory(),
			dashResolver: $this->dashResolver,
			treeService: $this->createMock(DashboardTreeService::class),
			groupManager: $groupManager,
			adminTemplateService: $adminTemplateService,
			db: $this->createMock(IDBConnection::class),
			config: $config,
			l10nFactory: $this->createMock(IFactory::class),
			logger: $this->createMock(LoggerInterface::class),
			personalLayers: $this->layers,
		);
	}//end setUp()

	/**
	 * The point of the whole feature: a reader saved an arrangement, and the
	 * reader's own read gives that arrangement back.
	 *
	 * The layer moves placement 3 to the front and hides placement 2. Both
	 * halves are asserted, because a reader who sees the reorder but not the
	 * hide has half a feature.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function testASavedLayerChangesWhatTheReaderGetsBack(): void {
		$this->shareWithReader();
		$this->placementMapper->method('findByDashboardId')
			->willReturnCallback(fn (): array => $this->dashboardOfThree());

		$owners = $this->service->getDashboardForUser(
			dashboardId: self::DASHBOARD_ID,
			userId: self::READER
		);
		$this->assertSame(
			[1, 2, 3],
			$this->idsOf($owners['placements']),
			'Premise: with no layer the reader sees the owner\'s arrangement.'
		);

		$saved = $this->layers->save(
			userId: self::READER,
			dashboardId: self::DASHBOARD_ID,
			overrides: [3 => ['sortOrder' => 0, 'gridWidth' => 4]],
			hide: [2],
			placements: $this->dashboardOfThree()
		);
		$this->assertSame(['saved' => true], $saved, 'Premise: the layer was stored.');

		$result = $this->service->getDashboardForUser(
			dashboardId: self::DASHBOARD_ID,
			userId: self::READER
		);

		$this->assertSame(
			[3, 1],
			$this->idsOf($result['placements']),
			'The reader\'s saved layer must change what the reader reads back: '
			. 'placement 3 moved to the front and placement 2 was hidden. '
			. 'Unchanged owner order here means the layer is stored and ignored.'
		);
		$this->assertSame(
			4,
			(int)$result['placements'][0]->getGridWidth(),
			'The size the reader set must survive the read too, not just the order.'
		);
	}//end testASavedLayerChangesWhatTheReaderGetsBack()

	/**
	 * The same assertion on the ordinary read path. `getDashboardForUser()`
	 * has two exits — the share branch above and `buildResult` — and an
	 * earlier shape of the code laid the layer over only one of them.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function testTheLayerIsAppliedOnTheNonShareReadPathToo(): void {
		$dashboard = $this->dashboard();
		$this->dashboardMapper->method('findVisibleToUser')->willReturn(
			[
				[
					'dashboard' => $dashboard,
					'source' => Dashboard::SOURCE_DEFAULT,
				],
			]
		);
		$this->dashResolver->method('findSharedDashboards')->willReturn([]);
		$this->placementMapper->method('findByDashboardId')
			->willReturnCallback(fn (): array => $this->dashboardOfThree());

		$this->layers->save(
			userId: self::READER,
			dashboardId: self::DASHBOARD_ID,
			overrides: [3 => ['sortOrder' => 0]],
			hide: [],
			placements: $this->dashboardOfThree()
		);

		$result = $this->service->getDashboardForUser(
			dashboardId: self::DASHBOARD_ID,
			userId: self::READER
		);

		$this->assertSame(
			[3, 1, 2],
			$this->idsOf($result['placements']),
			'The non-share read path must lay the layer over the placements as well.'
		);
	}//end testTheLayerIsAppliedOnTheNonShareReadPathToo()

	/**
	 * A layer belongs to one person. Another reader of the same dashboard
	 * reads the owner's arrangement, which is what makes the layer personal
	 * rather than a second way to edit somebody else's dashboard.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function testAnotherReadersLayerIsInvisible(): void {
		$this->shareWithReader();
		$this->placementMapper->method('findByDashboardId')
			->willReturnCallback(fn (): array => $this->dashboardOfThree());

		$this->layers->save(
			userId: self::READER,
			dashboardId: self::DASHBOARD_ID,
			overrides: [3 => ['sortOrder' => 0]],
			hide: [2],
			placements: $this->dashboardOfThree()
		);

		$result = $this->service->getDashboardForUser(
			dashboardId: self::DASHBOARD_ID,
			userId: 'somebody-else'
		);

		$this->assertSame(
			[1, 2, 3],
			$this->idsOf($result['placements']),
			'One reader\'s layer must never reach another reader.'
		);
	}//end testAnotherReadersLayerIsInvisible()

	/**
	 * The owner is left alone. On their own dashboard the arrangement IS the
	 * dashboard, so a layer over it would be a second place the same thing
	 * is stored.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function testTheOwnersOwnReadIsNeverLayered(): void {
		$dashboard = $this->dashboard();
		$this->dashboardMapper->method('findVisibleToUser')->willReturn(
			[
				[
					'dashboard' => $dashboard,
					'source' => Dashboard::SOURCE_USER,
				],
			]
		);
		$this->dashResolver->method('findSharedDashboards')->willReturn([]);
		$this->placementMapper->method('findByDashboardId')
			->willReturnCallback(fn (): array => $this->dashboardOfThree());

		// A layer the owner somehow has must not be honoured on their own read.
		$this->layers->save(
			userId: self::OWNER,
			dashboardId: self::DASHBOARD_ID,
			overrides: [3 => ['sortOrder' => 0]],
			hide: [2],
			placements: $this->dashboardOfThree()
		);

		$result = $this->service->getDashboardForUser(
			dashboardId: self::DASHBOARD_ID,
			userId: self::OWNER
		);

		$this->assertSame(
			[1, 2, 3],
			$this->idsOf($result['placements']),
			'The owner reads what the owner composed.'
		);
	}//end testTheOwnersOwnReadIsNeverLayered()

	/**
	 * REQ-DWMS-002 from the reader's side: after the reset the owner's
	 * arrangement is what comes back, in full.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function testResetPutsTheOwnersArrangementBackOnTheRead(): void {
		$this->shareWithReader();
		$this->placementMapper->method('findByDashboardId')
			->willReturnCallback(fn (): array => $this->dashboardOfThree());

		$this->layers->save(
			userId: self::READER,
			dashboardId: self::DASHBOARD_ID,
			overrides: [3 => ['sortOrder' => 0]],
			hide: [2],
			placements: $this->dashboardOfThree()
		);
		$this->assertTrue(
			$this->layers->reset(userId: self::READER, dashboardId: self::DASHBOARD_ID),
			'Premise: there was a layer to reset.'
		);

		$result = $this->service->getDashboardForUser(
			dashboardId: self::DASHBOARD_ID,
			userId: self::READER
		);

		$this->assertSame(
			[1, 2, 3],
			$this->idsOf($result['placements']),
			'After the reset the reader is back on the owner\'s arrangement.'
		);
	}//end testResetPutsTheOwnersArrangementBackOnTheRead()

	/**
	 * `GET /api/dashboard` is what the grid loads from, so it is the read
	 * that decides whether anybody ever sees their layer. It resolves
	 * through `getEffectiveDashboard()`, which used to hand back the
	 * owner's placements untouched: the layer was stored, saved without
	 * error, and never rendered on the landing view.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function testTheLandingReadAppliesTheLayerToo(): void {
		$dashboard = $this->dashboard();
		$this->dashResolver->method('tryGetActiveDashboard')->willReturn(null);
		$this->dashResolver->method('tryActivateExistingDashboard')->willReturn(null);
		$this->dashResolver->method('tryGetSharedDashboard')->willReturnCallback(
			fn (): array => [
				'dashboard' => $dashboard,
				'placements' => $this->dashboardOfThree(),
				'permissionLevel' => Dashboard::PERMISSION_VIEW_ONLY,
			]
		);
		$this->dashboardMapper->method('findVisibleToUser')->willReturn([]);
		$this->dashResolver->method('findSharedDashboards')->willReturn([]);

		$this->layers->save(
			userId: self::READER,
			dashboardId: self::DASHBOARD_ID,
			overrides: [3 => ['sortOrder' => 0]],
			hide: [2],
			placements: $this->dashboardOfThree()
		);

		$result = $this->service->getEffectiveDashboard(userId: self::READER);

		$this->assertNotNull($result, 'Premise: the reader resolves to the shared dashboard.');
		$this->assertSame(
			[3, 1],
			$this->idsOf($result['placements']),
			'The landing read must apply the layer: placement 3 to the front, '
			. 'placement 2 hidden. The owner\'s order here means the layer is '
			. 'saved and never rendered.'
		);
	}//end testTheLandingReadAppliesTheLayerToo()

	/**
	 * Make the dashboard reachable by the reader through a share, which is
	 * the REQ-SHARE-004 branch of `getDashboardForUser()`.
	 *
	 * @return void
	 */
	private function shareWithReader(): void {
		$dashboard = $this->dashboard();
		$this->dashboardMapper->method('findVisibleToUser')->willReturn([]);
		$this->dashResolver->method('findSharedDashboards')->willReturn(
			[
				[
					'dashboard' => $dashboard,
					'source' => Dashboard::SOURCE_SHARED,
				],
			]
		);
		$this->dashResolver->method('findSharedLevels')->willReturn(
			[self::DASHBOARD_ID => Dashboard::PERMISSION_VIEW_ONLY]
		);
	}//end shareWithReader()

	/**
	 * A published dashboard owned by somebody else.
	 *
	 * @return Dashboard
	 */
	private function dashboard(): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId(self::DASHBOARD_ID);
		$dashboard->setUuid('uuid-layered');
		$dashboard->setName('Team board');
		$dashboard->setUserId(self::OWNER);
		$dashboard->setType(Dashboard::TYPE_USER);
		$dashboard->setPermissionLevel(Dashboard::PERMISSION_FULL);
		$dashboard->setPublicationStatus(Dashboard::STATUS_PUBLISHED);

		return $dashboard;
	}//end dashboard()

	/**
	 * Three placements in the owner's order. Rebuilt per call, because the
	 * layer writes onto the entities it is handed.
	 *
	 * @return array<int, WidgetPlacement>
	 */
	private function dashboardOfThree(): array {
		$placements = [];
		for ($id = 1; $id <= 3; $id++) {
			$placement = new WidgetPlacement();
			$placement->setId($id);
			$placement->setDashboardId(self::DASHBOARD_ID);
			$placement->setSortOrder($id);
			$placement->setGridWidth(2);
			$placement->setIsCompulsory(0);
			$placements[] = $placement;
		}

		return $placements;
	}//end dashboardOfThree()

	/**
	 * The placement ids in the order they came back.
	 *
	 * @param array<int, WidgetPlacement> $placements The placements read.
	 *
	 * @return array<int, int>
	 */
	private function idsOf(array $placements): array {
		return array_map(
			static function (WidgetPlacement $placement): int {
				return (int)$placement->getId();
			},
			$placements
		);
	}//end idsOf()

	/**
	 * An in-memory layer store. `onlyMethods`, so the double cannot answer a
	 * method the real mapper does not have.
	 *
	 * @return PersonalLayerMapper&MockObject
	 */
	private function layerMapper()
	{
		$mapper = $this->getMockBuilder(PersonalLayerMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findForUser', 'deleteForUser', 'insert', 'update'])
			->getMock();

		$mapper->method('findForUser')->willReturnCallback(
			function (string $userId, int $dashboardId): ?PersonalLayer {
				return ($this->stored[$userId . '|' . $dashboardId] ?? null);
			}
		);
		$mapper->method('deleteForUser')->willReturnCallback(
			function (string $userId, int $dashboardId): int {
				$key = ($userId . '|' . $dashboardId);
				if (isset($this->stored[$key]) === false) {
					return 0;
				}

				unset($this->stored[$key]);
				return 1;
			}
		);
		$mapper->method('insert')->willReturnCallback(
			function (PersonalLayer $layer): PersonalLayer {
				$layer->setId((count($this->stored) + 1));
				$this->stored[$layer->getUserId() . '|' . $layer->getDashboardId()] = $layer;
				return $layer;
			}
		);
		$mapper->method('update')->willReturnCallback(
			function (PersonalLayer $layer): PersonalLayer {
				$this->stored[$layer->getUserId() . '|' . $layer->getDashboardId()] = $layer;
				return $layer;
			}
		);

		return $mapper;
	}//end layerMapper()
}//end class
