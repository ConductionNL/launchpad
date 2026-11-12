<?php

/**
 * DashboardService creation golden test
 *
 * Pins, on the code before its split, the exact order of every check
 * and write `createDashboard()` makes (public-share and quota refusals
 * before anything else, parent check, factory, icon, slug uniqueness,
 * deactivation, insert, translation seed, default widgets), and every
 * branch of the first-visit template rung and the automatic
 * provisioning path.
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
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Exception\QuotaExceededException;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardBuilders;
use OCA\LaunchPad\Service\DashboardFactory;
use OCA\LaunchPad\Service\DashboardLimits;
use OCA\LaunchPad\Service\DashboardOutlets;
use OCA\LaunchPad\Service\DashboardPlatform;
use OCA\LaunchPad\Service\DashboardResolver;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardStores;
use OCA\LaunchPad\Service\DashboardStructure;
use OCA\LaunchPad\Service\DashboardTranslationService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\FooterService;
use OCA\LaunchPad\Service\PersonalLayerService;
use OCA\LaunchPad\Service\PublicShareContext;
use OCA\LaunchPad\Service\QuotaService;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCA\LaunchPad\Service\TemplateService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use RuntimeException;

class DashboardServiceCreationGoldenTest extends TestCase {
	/** @var list<string> */
	private array $log = [];

	private DashboardMapper $dashboardMapper;
	private WidgetPlacementMapper $placementMapper;
	private AdminSettingMapper $settingMapper;
	private TemplateService $templateService;
	private DashboardFactory $factory;
	private DashboardResolver $resolver;
	private DashboardTreeService $treeService;
	private DashboardTranslationService $translations;
	private LoggerInterface $logger;
	private PublicShareContext $shareContext;
	private QuotaService $quota;
	private ?RoleFeaturePermissionService $roles;
	private IConfig $config;

	protected function setUp(): void {
		$this->dashboardMapper = $this->createMock(DashboardMapper::class);
		$this->placementMapper = $this->createMock(WidgetPlacementMapper::class);
		$this->settingMapper = $this->createMock(AdminSettingMapper::class);
		$this->templateService = $this->createMock(TemplateService::class);
		$this->factory = $this->createMock(DashboardFactory::class);
		$this->resolver = $this->createMock(DashboardResolver::class);
		$this->treeService = $this->createMock(DashboardTreeService::class);
		$this->translations = $this->createMock(DashboardTranslationService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->shareContext = $this->createMock(PublicShareContext::class);
		$this->quota = $this->createMock(QuotaService::class);
		$this->roles = $this->createMock(RoleFeaturePermissionService::class);
		$this->config = $this->createMock(IConfig::class);

		$this->shareContext->method('requireMutable')->willReturnCallback(fn () => $this->log[] = 'mutable');
		$this->quota->method('assertCanCreateDashboard')->willReturnCallback(fn (string $userId) => $this->log[] = 'quota:' . $userId);
		$this->treeService->method('validateParent')->willReturnCallback(fn (?string $movingUuid, ?string $newParentUuid) => $this->log[] = 'parent:' . var_export($newParentUuid, true));
		$this->treeService->method('validateSlugUnique')->willReturnCallback(fn (?string $parentUuid, string $slug) => $this->log[] = 'slug:' . var_export($parentUuid, true) . ':' . $slug);
		$this->factory->method('create')->willReturnCallback(function (string $userId, string $name, array $options): Dashboard {
			$this->log[] = 'factory:' . $userId . ':' . $name . ':' . json_encode($options);
			$dashboard = new Dashboard();
			$dashboard->setName($name);
			$dashboard->setSlug($options['slug'] ?? 'derived');
			return $dashboard;
		});
		$this->dashboardMapper->method('deactivateAllForUser')->willReturnCallback(fn (string $userId) => $this->log[] = 'deactivate:' . $userId);
		$this->dashboardMapper->method('insert')->willReturnCallback(function (Dashboard $dashboard): Dashboard {
			$this->log[] = 'insert:icon=' . var_export($dashboard->getIcon(), true);
			$dashboard->setId(42);
			return $dashboard;
		});
		$this->translations->method('seedPrimaryFor')->willReturnCallback(function (Dashboard $dashboard) {
			$this->log[] = 'translation:' . $dashboard->getId();
			if ($dashboard->getName() === 'Untranslatable') {
				throw new RuntimeException('no locale');
			}

			return new \OCA\LaunchPad\Db\DashboardTranslation();
		});
		$this->placementMapper->method('insert')->willReturnCallback(function (WidgetPlacement $placement): WidgetPlacement {
			$this->log[] = 'placement';
			return $placement;
		});
	}

	private function service(bool $withLimits = true, bool $withTranslations = true): DashboardService {
		return new DashboardService(
			new DashboardStores($this->dashboardMapper, $this->placementMapper, $this->settingMapper),
			new DashboardBuilders($this->templateService, $this->factory, $this->resolver, $this->createMock(AdminTemplateService::class)),
			new DashboardStructure(
				$this->treeService,
				$this->createMock(PersonalLayerService::class),
				$withTranslations ? $this->translations : null,
				$this->createMock(FooterService::class),
			),
			new DashboardPlatform($this->createMock(IGroupManager::class), $this->createMock(IDBConnection::class), $this->config, $this->createMock(IFactory::class)),
			new DashboardOutlets($this->logger),
			$withLimits ? new DashboardLimits($this->roles, $this->shareContext, $this->quota) : new DashboardLimits(),
		);
	}

	public function testFullCreateOrder(): void {
		$dashboard = $this->service()->createDashboard('alice', 'Board', [
			'description' => 'D',
			'icon' => 'star',
			'parentUuid' => 'p-1',
			'slug' => 'board',
			'sortOrder' => 4,
			'seedDefaults' => true,
		]);

		$this->assertSame(42, $dashboard->getId());
		$this->assertSame([
			'mutable',
			'quota:alice',
			"parent:'p-1'",
			'factory:alice:Board:{"description":"D","parentUuid":"p-1","slug":"board","sortOrder":4}',
			"slug:'p-1':board",
			'deactivate:alice',
			"insert:icon='star'",
			'translation:42',
		], array_slice($this->log, 0, 8));
		$this->assertGreaterThan(8, count($this->log));
		$this->assertSame(['placement'], array_unique(array_slice($this->log, 8)));
	}

	public function testDefaultsWithoutIconSlugTranslationOrSeed(): void {
		$this->factory = $this->createMock(DashboardFactory::class);
		$this->factory->method('create')->willReturnCallback(function (string $userId, string $name, array $options): Dashboard {
			$this->log[] = 'factory:' . json_encode($options);
			$dashboard = new Dashboard();
			$dashboard->setSlug(null);
			return $dashboard;
		});

		$this->service(false, false)->createDashboard('bob', 'B', ['icon' => '']);

		$this->assertSame([
			'parent:NULL',
			'factory:{"description":null,"parentUuid":null,"slug":null,"sortOrder":0}',
			'deactivate:bob',
			'insert:icon=NULL',
		], $this->log);
	}

	public function testEmptySlugSkipsTheUniquenessCheck(): void {
		$this->service()->createDashboard('bob', 'B', ['slug' => '']);

		$this->assertNotContains("slug:NULL:", $this->log);
		$this->assertSame([], array_values(array_filter($this->log, static fn (string $l): bool => str_starts_with($l, 'slug:'))));
	}

	public function testQuotaRefusalStopsBeforeAnyCheckOrWrite(): void {
		$this->quota = $this->createMock(QuotaService::class);
		$this->quota->method('assertCanCreateDashboard')->willThrowException(new QuotaExceededException(quota: QuotaExceededException::QUOTA_DASHBOARDS, limit: 1, current: 1));

		try {
			$this->service()->createDashboard('alice', 'B');
			$this->fail('expected the quota refusal');
		} catch (QuotaExceededException) {
			$this->assertSame(['mutable'], $this->log);
		}
	}

	public function testSharedContextRefusalStopsFirst(): void {
		$this->shareContext = $this->createMock(PublicShareContext::class);
		$this->shareContext->method('requireMutable')->willThrowException(new RuntimeException('read only'));

		try {
			$this->service()->createDashboard('alice', 'B');
			$this->fail('expected the refusal');
		} catch (RuntimeException $e) {
			$this->assertSame(['read only', []], [$e->getMessage(), $this->log]);
		}
	}

	public function testFailedTranslationSeedIsLoggedAndCreationGoesOn(): void {
		$this->logger->expects($this->once())->method('warning')->with(
			'launchpad: failed to seed primary translation: {message}',
			['message' => 'no locale']
		);

		$dashboard = $this->service()->createDashboard('alice', 'Untranslatable');

		$this->assertSame(42, $dashboard->getId());
		$this->assertSame('translation:42', end($this->log));
	}

	private function call(DashboardService $service, string $method, string $userId): mixed {
		return (new ReflectionMethod(DashboardService::class, $method))->invoke($service, $userId);
	}

	public function testProvisionWithATemplateHandsItToTheResolver(): void {
		$template = new Dashboard();
		$this->settingMapper->method('getValue')->willReturn(false);
		$this->templateService->method('getApplicableTemplate')->willReturn($template);
		$this->resolver->expects($this->once())->method('handleTemplateResult')->with($template, false, 'alice')->willReturn(['from' => 'template']);

		$this->assertSame(['from' => 'template'], $this->call($this->service(), 'tryCreateFromTemplate', 'alice'));
	}

	public function testNoTemplateAndNoPersonalDashboardsProvisionsNothing(): void {
		$this->settingMapper->method('getValue')->willReturn(false);
		$this->dashboardMapper->expects($this->never())->method('insert');

		$this->assertNull($this->call($this->service(), 'tryCreateFromTemplate', 'alice'));
	}

	/**
	 * Role seeding results and whether the default bundle is placed.
	 *
	 * @return array<string,array{0:?int,1:string}>
	 */
	public static function roleSeeds(): array {
		return ['role defaults' => [2, 'found'], 'nothing seeded' => [0, 'defaults'], 'no role service' => [null, 'defaults']];
	}

	/**
	 * @dataProvider roleSeeds
	 */
	public function testProvisionedDashboardPlacements(?int $seeded, string $placements): void {
		$this->settingMapper->method('getValue')->willReturn(true);
		if ($seeded === null) {
			$this->roles = null;
		} else {
			$this->roles->method('seedLayoutFromRoleDefaults')->willReturnCallback(function (string $userId, Dashboard $dashboard) use ($seeded): int {
				$this->log[] = 'roles:' . $userId . ':' . $dashboard->getId();
				return $seeded;
			});
		}

		$found = [new WidgetPlacement()];
		$this->placementMapper->method('findByDashboardId')->willReturnCallback(function (int $dashboardId) use ($found): array {
			$this->log[] = 'find:' . $dashboardId;
			return $found;
		});

		$result = $this->call($this->service(), 'tryCreateFromTemplate', 'alice');

		$this->assertSame(Dashboard::PERMISSION_FULL, $result['permissionLevel']);
		$this->assertSame(42, $result['dashboard']->getId());
		$this->assertSame('My Dashboard', $result['dashboard']->getName());
		$this->assertSame("slug:NULL:my-dashboard", array_values(array_filter($this->log, static fn (string $l): bool => str_starts_with($l, 'slug:')))[0]);
		if ($placements === 'found') {
			$this->assertSame($found, $result['placements']);
			$this->assertSame(['roles:alice:42', 'find:42'], array_slice($this->log, -2));
		} else {
			$this->assertNotContains('find:42', $this->log);
			$this->assertNotSame([], $result['placements']);
		}
	}
}//end class
