<?php

/**
 * DashboardServiceRootSlugTest
 *
 * Unit tests for the slug of a new root dashboard: a slug derived from the
 * name that another user's root dashboard holds moves to the first free
 * number; one the owner already holds is refused as before.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\DashboardOutlets;
use OCA\LaunchPad\Service\DashboardPlatform;
use OCA\LaunchPad\Service\DashboardStructure;
use OCA\LaunchPad\Service\DashboardBuilders;
use OCA\LaunchPad\Service\DashboardStores;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for the user-initiated default-widget seeding path.
 */
class DashboardServiceRootSlugTest extends TestCase {

	private $dashboardMapper;

	private $placementMapper;

	private $dashboardFactory;

	private $treeService;

	private DashboardService $service;

	protected function setUp(): void {
		$this->dashboardMapper = $this->createMock(DashboardMapper::class);
		$this->placementMapper = $this->createMock(WidgetPlacementMapper::class);
		$this->dashboardFactory = $this->createMock(DashboardFactory::class);
		$this->treeService = $this->createMock(DashboardTreeService::class);

		$this->service = new DashboardService(
			stores: new DashboardStores(
				dashboardMapper: $this->dashboardMapper,
				placementMapper: $this->placementMapper,
				settingMapper: $this->createMock(AdminSettingMapper::class)
			),
			builders: new DashboardBuilders(
				templateService: $this->createMock(TemplateService::class),
				dashboardFactory: $this->dashboardFactory,
				dashResolver: $this->createMock(DashboardResolver::class),
				adminTemplateService: $this->createMock(AdminTemplateService::class)
			),
			structure: new DashboardStructure(
				treeService: $this->treeService,
				personalLayers: $this->createMock(PersonalLayerService::class),
				footerService: $this->createMock(FooterService::class)
			),
			platform: new DashboardPlatform(
				groupManager: $this->createMock(IGroupManager::class),
				db: $this->createMock(IDBConnection::class),
				config: $this->createMock(IConfig::class),
				l10nFactory: $this->createMock(IFactory::class)
			),
			outlets: new DashboardOutlets(
				logger: $this->createMock(LoggerInterface::class)
			)
		);
	}//end setUp()

	/**
	 * A root dashboard owned by someone, holding a slug.
	 *
	 * @param string $userId The owner.
	 *
	 * @return Dashboard
	 */
	private static function rootOf(string $userId): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setUserId($userId);
		return $dashboard;
	}//end rootOf()

	/**
	 * Create "Mijn werkplek" as bob while the given slugs are held.
	 *
	 * @param array<string, string> $held Slug => owner.
	 *
	 * @return Dashboard The dashboard handed to insert.
	 */
	private function createWhileHeld(array $held): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setUserId('bob');
		$dashboard->setName('Mijn werkplek');
		$dashboard->setSlug('mijn-werkplek');
		$this->dashboardFactory->method('create')->willReturn($dashboard);
		$this->dashboardMapper->method('findChildBySlug')->willReturnCallback(
			static fn (?string $parentUuid, string $slug): ?Dashboard => (isset($held[$slug]) === true) ? self::rootOf(userId: $held[$slug]) : null
		);
		$this->dashboardMapper->method('insert')->willReturnArgument(0);

		return $this->service->createDashboard(userId: 'bob', name: 'Mijn werkplek');
	}//end createWhileHeld()

	/**
	 * Another user's "mijn-werkplek" and "mijn-werkplek-2" leave bob "mijn-werkplek-3".
	 *
	 * @return void
	 */
	public function testADerivedRootSlugAnotherUserHoldsMovesToTheFirstFreeNumber(): void {
		$this->treeService->expects($this->once())
			->method('validateSlugUnique')
			->with(null, 'mijn-werkplek-3');

		$created = $this->createWhileHeld(held: ['mijn-werkplek' => 'alice', 'mijn-werkplek-2' => 'carol']);

		$this->assertSame(expected: 'mijn-werkplek-3', actual: $created->getSlug());
	}//end testADerivedRootSlugAnotherUserHoldsMovesToTheFirstFreeNumber()

	/**
	 * A derived root slug the owner already holds is kept, so the sibling check refuses it.
	 *
	 * @return void
	 */
	public function testADerivedRootSlugTheOwnerHoldsGoesToTheSiblingCheckUnchanged(): void {
		$this->treeService->expects($this->once())
			->method('validateSlugUnique')
			->with(null, 'mijn-werkplek')
			->willThrowException(new \InvalidArgumentException(DashboardTreeService::ERR_SLUG_TAKEN));
		$this->expectException(\InvalidArgumentException::class);

		$this->createWhileHeld(held: ['mijn-werkplek' => 'bob']);
	}//end testADerivedRootSlugTheOwnerHoldsGoesToTheSiblingCheckUnchanged()
}//end class
