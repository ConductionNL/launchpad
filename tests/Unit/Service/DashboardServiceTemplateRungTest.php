<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardFactory;
use OCA\LaunchPad\Service\DashboardResolver;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\FooterService;
use OCA\LaunchPad\Service\PersonalLayerService;
use OCA\LaunchPad\Service\TemplateService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * REQ-TMPL-019: a member who owns no dashboard gets the admin template meant
 * for them, from BOTH chains: the page shell (`resolveActiveDashboard`, what
 * decides whether the page shows a dashboard at all) and `GET /api/dashboard`
 * (`getEffectiveDashboard`, what the grid then loads).
 *
 * Why both, in one file: the feature shipped working in the second chain only.
 * Its tests were green and no member ever saw a template, because the page
 * asks the first chain and that one did not know templates exist. Every case
 * here asks both and holds the answers against each other.
 *
 * The resolver is the real DashboardResolver, so a wrong order between "has a
 * personal dashboard" and "gets a template" shows up here.
 */
class DashboardServiceTemplateRungTest extends TestCase {
	private const USER = 'sanne';

	/** @var array<int, Dashboard> Personal dashboards the user owns. */
	private array $owned = [];

	/** @var array<int, array{dashboard: Dashboard, source: string}> Group dashboards the user can see. */
	private array $groupDashboards = [];

	private ?Dashboard $template = null;

	private bool $allowPersonal = true;

	private string $primaryGroup = 'behandelaars';

	/** @var array<string, string> The user's preferences. */
	private array $prefs = [];

	private int $copiesMade = 0;

	private DashboardService $service;

	protected function setUp(): void {
		parent::setUp();

		$dashboardMapper = $this->createMock(DashboardMapper::class);
		$dashboardMapper->method('findByUserId')->willReturnCallback(fn (): array => $this->owned);
		$dashboardMapper->method('find')->willReturnCallback(
			function (int $id): Dashboard {
				if ($this->template !== null && $this->template->getId() === $id) {
					return $this->template;
				}
				throw new \OCP\AppFramework\Db\DoesNotExistException('none');
			}
		);
		$dashboardMapper->method('findVisibleToUser')->willReturnCallback(
			fn (): array => array_merge(
				array_map(
					static fn (Dashboard $d): array => ['dashboard' => $d, 'source' => Dashboard::SOURCE_USER],
					$this->owned
				),
				$this->groupDashboards
			)
		);
		$dashboardMapper->method('findActiveByUserId')->willReturnCallback(
			function (): Dashboard {
				foreach ($this->owned as $dashboard) {
					if ($dashboard->getIsActive() === 1) {
						return $dashboard;
					}
				}
				throw new \OCP\AppFramework\Db\DoesNotExistException('none');
			}
		);

		$placementMapper = $this->createMock(WidgetPlacementMapper::class);
		$placementMapper->method('findByDashboardId')->willReturn([]);

		$settingMapper = $this->createMock(AdminSettingMapper::class);
		$settingMapper->method('getValue')->willReturnCallback(
			fn (string $key, mixed $default = null): mixed => $key === 'allow_user_dashboards'
				? $this->allowPersonal
				: $default
		);

		$templateService = $this->createMock(TemplateService::class);
		$templateService->method('getApplicableTemplate')->willReturnCallback(fn (): ?Dashboard => $this->template);
		$templateService->method('createDashboardFromTemplate')->willReturnCallback(
			function (string $userId, Dashboard $template): Dashboard {
				$this->copiesMade++;
				$copy = new Dashboard();
				$copy->setId(100 + $this->copiesMade);
				$copy->setUuid('copy-' . $this->copiesMade);
				$copy->setType(Dashboard::TYPE_USER);
				$copy->setUserId($userId);
				$copy->setBasedOnTemplate($template->getId());
				$copy->setPermissionLevel($template->getPermissionLevel());
				$copy->setIsActive(1);
				$this->owned[] = $copy;
				return $copy;
			}
		);

		$adminTemplateService = $this->createMock(AdminTemplateService::class);
		$adminTemplateService->method('getUserGroupIdsFor')->willReturnCallback(fn (): array => [$this->primaryGroup]);
		$adminTemplateService->method('resolvePrimaryGroup')->willReturnCallback(fn (): string => $this->primaryGroup);

		$config = $this->createMock(IConfig::class);
		$config->method('getUserValue')->willReturnCallback(
			fn ($uid, $app, $key, $default = ''): string => $this->prefs[$key] ?? (string)$default
		);
		$config->method('setUserValue')->willReturnCallback(
			function ($uid, $app, $key, $value): void {
				$this->prefs[$key] = (string)$value;
			}
		);

		$this->service = new DashboardService(
			dashboardMapper: $dashboardMapper,
			placementMapper: $placementMapper,
			settingMapper: $settingMapper,
			templateService: $templateService,
			dashboardFactory: new DashboardFactory(),
			dashResolver: new DashboardResolver(
				dashboardMapper: $dashboardMapper,
				placementMapper: $placementMapper,
				templateService: $templateService,
			),
			treeService: $this->createMock(DashboardTreeService::class),
			groupManager: $this->createMock(IGroupManager::class),
			adminTemplateService: $adminTemplateService,
			db: $this->createMock(IDBConnection::class),
			config: $config,
			l10nFactory: $this->createMock(IFactory::class),
			logger: $this->createMock(LoggerInterface::class),
			personalLayers: $this->createMock(PersonalLayerService::class),
			footerService: $this->createMock(FooterService::class),
		);
	}

	private function template(array $targetGroups): Dashboard {
		$template = new Dashboard();
		$template->setId(2);
		$template->setUuid('template-uuid');
		$template->setType(Dashboard::TYPE_ADMIN_TEMPLATE);
		$template->setPermissionLevel(Dashboard::PERMISSION_ADD_ONLY);
		$template->setTargetGroupsArray($targetGroups);
		if ($targetGroups === []) {
			$template->setIsDefault(1);
		}
		return $template;
	}

	private function groupDashboard(string $uuid, string $groupId, int $isDefault): array {
		$dashboard = new Dashboard();
		$dashboard->setId(crc32($uuid) % 1000 + 500);
		$dashboard->setUuid($uuid);
		$dashboard->setType(Dashboard::TYPE_GROUP_SHARED);
		$dashboard->setGroupId($groupId);
		$dashboard->setIsDefault($isDefault);
		$source = Dashboard::SOURCE_GROUP;
		if ($groupId === Dashboard::DEFAULT_GROUP_ID) {
			$source = Dashboard::SOURCE_DEFAULT;
		}
		return ['dashboard' => $dashboard, 'source' => $source];
	}

	/** The dashboard everyone starts on, as SeedDefaultDashboard leaves it. */
	private function seedEveryoneDashboard(): void {
		$this->groupDashboards[] = $this->groupDashboard('seeded-everyone', Dashboard::DEFAULT_GROUP_ID, 1);
	}

	/**
	 * Ask the page shell, then the API, as one page load does.
	 *
	 * @return array{page: ?string, api: ?string, apiPermission: ?string}
	 */
	private function openLaunchpad(): array {
		$page = $this->service->resolveActiveDashboard(userId: self::USER, primaryGroupId: $this->primaryGroup);
		$api = $this->service->getEffectiveDashboard(userId: self::USER);

		return [
			'page' => $page === null ? null : (string)$page['dashboard']->getUuid(),
			'pageSource' => $page['source'] ?? null,
			'api' => $api === null ? null : (string)$api['dashboard']->getUuid(),
			'apiPermission' => $api['permissionLevel'] ?? null,
		];
	}

	/**
	 * The coordinator's live failure 1: the seeded dashboard for everyone won.
	 */
	public function testAGroupTemplateBeatsTheSeededDashboardForEveryone(): void {
		$this->seedEveryoneDashboard();
		$this->template = $this->template(['behandelaars']);

		$first = $this->openLaunchpad();

		self::assertSame('copy-1', $first['page'], 'the page shell must hand out the copy, not the seeded dashboard');
		self::assertSame(Dashboard::SOURCE_USER, $first['pageSource']);
		self::assertSame('copy-1', $first['api'], 'the grid must load the dashboard the page chose');
		self::assertSame(Dashboard::PERMISSION_ADD_ONLY, $first['apiPermission']);
		self::assertSame(1, $this->copiesMade);
		self::assertSame('copy-1', $this->prefs[DashboardService::ACTIVE_DASHBOARD_UUID_PREF_KEY]);
	}

	/**
	 * Live failure 2: with no group dashboard at all the page said
	 * "No dashboards available", because the page chain answered null.
	 */
	public function testWithNoOtherDashboardThePageStillShowsTheTemplate(): void {
		$this->template = $this->template(['behandelaars']);

		$first = $this->openLaunchpad();

		self::assertSame('copy-1', $first['page']);
		self::assertSame('copy-1', $first['api']);
	}

	public function testASecondVisitMakesNoSecondCopy(): void {
		$this->seedEveryoneDashboard();
		$this->template = $this->template(['behandelaars']);
		$this->openLaunchpad();

		$second = $this->openLaunchpad();

		self::assertSame(1, $this->copiesMade);
		self::assertSame('copy-1', $second['page']);
		self::assertSame('copy-1', $second['api']);
	}

	/**
	 * Personal dashboards off (the factory default): no copy may be made,
	 * the template itself is shown, view only, by both chains.
	 */
	public function testWithPersonalDashboardsOffTheTemplateItselfIsShownViewOnly(): void {
		$this->allowPersonal = false;
		$this->seedEveryoneDashboard();
		$this->template = $this->template(['behandelaars']);

		$first = $this->openLaunchpad();

		self::assertSame('template-uuid', $first['page']);
		self::assertSame(Dashboard::SOURCE_GROUP, $first['pageSource']);
		self::assertSame('template-uuid', $first['api']);
		self::assertSame(Dashboard::PERMISSION_VIEW_ONLY, $first['apiPermission']);
		self::assertSame(0, $this->copiesMade);
		self::assertArrayNotHasKey(DashboardService::ACTIVE_DASHBOARD_UUID_PREF_KEY, $this->prefs);
	}

	public function testTheDefaultTemplateReplacesTheSeededDashboard(): void {
		$this->seedEveryoneDashboard();
		$this->template = $this->template([]);

		$first = $this->openLaunchpad();

		self::assertSame('copy-1', $first['page']);
		self::assertSame('copy-1', $first['api']);
	}

	/**
	 * The default template is for everyone; a default dashboard of the
	 * user's own group is more specific and keeps its place.
	 */
	public function testTheDefaultTemplateStepsAsideForTheUsersOwnGroupDefault(): void {
		$this->seedEveryoneDashboard();
		$this->groupDashboards[] = $this->groupDashboard('team-default', 'behandelaars', 1);
		$this->template = $this->template([]);

		$first = $this->openLaunchpad();

		self::assertSame('team-default', $first['page']);
		self::assertSame(0, $this->copiesMade);
	}

	public function testAGroupTemplateBeatsEvenTheUsersOwnGroupDefault(): void {
		$this->groupDashboards[] = $this->groupDashboard('team-default', 'behandelaars', 1);
		$this->template = $this->template(['behandelaars']);

		self::assertSame('copy-1', $this->openLaunchpad()['page']);
	}

	/**
	 * What does NOT change for people already using LaunchPad.
	 */
	public function testAUserWhoOwnsADashboardGetsNoTemplate(): void {
		$own = new Dashboard();
		$own->setId(7);
		$own->setUuid('my-own');
		$own->setType(Dashboard::TYPE_USER);
		$own->setUserId(self::USER);
		$own->setIsActive(1);
		$this->owned[] = $own;
		$this->seedEveryoneDashboard();
		$this->template = $this->template(['behandelaars']);

		$visit = $this->openLaunchpad();

		self::assertSame(0, $this->copiesMade);
		self::assertSame('seeded-everyone', $visit['page'], 'unchanged: the page shell ranks group dashboards first');
		self::assertSame('my-own', $visit['api'], 'unchanged: the API returns the active personal dashboard');
	}

	public function testASavedChoiceIsKept(): void {
		$this->seedEveryoneDashboard();
		$this->prefs[DashboardService::ACTIVE_DASHBOARD_UUID_PREF_KEY] = 'seeded-everyone';
		$this->template = $this->template(['behandelaars']);

		$visit = $this->openLaunchpad();

		self::assertSame('seeded-everyone', $visit['page']);
		self::assertSame('seeded-everyone', $visit['api']);
		self::assertSame(0, $this->copiesMade);
	}

	public function testWithoutAnApplicableTemplateNothingChanges(): void {
		$this->seedEveryoneDashboard();

		$visit = $this->openLaunchpad();

		self::assertSame('seeded-everyone', $visit['page']);
		self::assertSame('seeded-everyone', $visit['api']);
		self::assertSame(0, $this->copiesMade);
	}
}
