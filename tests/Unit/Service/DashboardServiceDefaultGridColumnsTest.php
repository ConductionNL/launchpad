<?php

/**
 * DashboardService default grid columns test.
 *
 * A new dashboard takes the administrator's default grid columns when the
 * create names none (REQ-ASET-006).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction Development Team <dev@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardBuilders;
use OCA\LaunchPad\Service\DashboardFactory;
use OCA\LaunchPad\Service\DashboardOutlets;
use OCA\LaunchPad\Service\DashboardPlatform;
use OCA\LaunchPad\Service\DashboardResolver;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardStores;
use OCA\LaunchPad\Service\DashboardStructure;
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
 * The grid columns DashboardService hands the factory.
 */
class DashboardServiceDefaultGridColumnsTest extends TestCase {

	/**
	 * The grid columns the factory is asked for, given the stored default and the create options.
	 *
	 * @param mixed                $stored  The stored admin default.
	 * @param array<string, mixed> $options The create options.
	 *
	 * @return int|null The column count the factory received.
	 */
	private function columnsFor(mixed $stored, array $options = []): ?int {
		$settings = $this->createMock(originalClassName: AdminSettingMapper::class);
		$settings->method('getValue')->willReturn($stored);

		$received = null;
		$factory = $this->createMock(originalClassName: DashboardFactory::class);
		$factory->method('create')->willReturnCallback(
			static function (?string $userId, string $name, array $factoryOptions) use (&$received): Dashboard {
				$received = $factoryOptions['gridColumns'] ?? null;
				$dashboard = new Dashboard();
				$dashboard->setId(1);
				return $dashboard;
			}
		);
		$mapper = $this->createMock(originalClassName: DashboardMapper::class);
		$mapper->method('insert')->willReturnArgument(0);

		$service = new DashboardService(
			stores: new DashboardStores(
				dashboardMapper: $mapper,
				placementMapper: $this->createMock(originalClassName: WidgetPlacementMapper::class),
				settingMapper: $settings
			),
			builders: new DashboardBuilders(
				templateService: $this->createMock(originalClassName: TemplateService::class),
				dashboardFactory: $factory,
				dashResolver: $this->createMock(originalClassName: DashboardResolver::class),
				adminTemplateService: $this->createMock(originalClassName: AdminTemplateService::class)
			),
			structure: new DashboardStructure(
				treeService: $this->createMock(originalClassName: DashboardTreeService::class),
				personalLayers: $this->createMock(originalClassName: PersonalLayerService::class),
				footerService: $this->createMock(originalClassName: FooterService::class)
			),
			platform: new DashboardPlatform(
				groupManager: $this->createMock(originalClassName: IGroupManager::class),
				db: $this->createMock(originalClassName: IDBConnection::class),
				config: $this->createMock(originalClassName: IConfig::class),
				l10nFactory: $this->createMock(originalClassName: IFactory::class)
			),
			outlets: new DashboardOutlets(logger: $this->createMock(originalClassName: LoggerInterface::class))
		);
		$service->createDashboard(userId: 'alice', name: 'My Dashboard', options: $options);

		return $received;
	}//end columnsFor()

	/**
	 * The administrator's default applies when the create names no count.
	 *
	 * @return void
	 */
	public function testTheAdministratorsDefaultApplies(): void {
		$this->assertSame(expected: 8, actual: $this->columnsFor(stored: 8));
		$this->assertSame(expected: 6, actual: $this->columnsFor(stored: '6'));
	}//end testTheAdministratorsDefaultApplies()

	/**
	 * An explicit count wins over the default.
	 *
	 * @return void
	 */
	public function testAnExplicitCountWins(): void {
		$this->assertSame(expected: 12, actual: $this->columnsFor(stored: 8, options: ['gridColumns' => 12]));
	}//end testAnExplicitCountWins()

	/**
	 * An unset or unoffered default falls back to 12.
	 *
	 * @return void
	 */
	public function testAnUnusableDefaultFallsBackToTwelve(): void {
		$this->assertSame(expected: 12, actual: $this->columnsFor(stored: null));
		$this->assertSame(expected: 12, actual: $this->columnsFor(stored: 7));
	}//end testAnUnusableDefaultFallsBackToTwelve()
}//end class
