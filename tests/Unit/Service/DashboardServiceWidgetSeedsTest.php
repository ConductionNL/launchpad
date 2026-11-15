<?php

/**
 * DashboardServiceWidgetSeedsTest
 *
 * Pins the default widget set a brand-new dashboard is seeded with,
 * with and without a URL generator for the bundled logos.
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
use OCP\IURLGenerator;
use ReflectionMethod;

/**
 * The seed table, exactly.
 */
class DashboardServiceWidgetSeedsTest extends TestCase {

	/**
	 * The seeds with the fallback image path and with the URL generator.
	 *
	 * @return void
	 */
	public function testSeedsMatchTheRecording(): void {
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('imagePath')->willReturnCallback(static fn (string $app, string $image): string => '/custom/'.$app.'/'.$image);

		$actual = [
			'fallback' => $this->seeds(urls: null),
			'generator' => $this->seeds(urls: $urls),
		];

		$fixture = __DIR__.'/fixtures/dashboard-widget-seeds.json';
		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents($fixture, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
		}

		$this->assertSame(json_decode((string) file_get_contents($fixture), true), $actual);
	}//end testSeedsMatchTheRecording()

	/**
	 * Run the private seed table.
	 *
	 * @param IURLGenerator|null $urls The URL generator, or none.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function seeds(?IURLGenerator $urls): array {
		$service = new DashboardService(
			stores: new DashboardStores(
				dashboardMapper: $this->createMock(DashboardMapper::class),
				placementMapper: $this->createMock(WidgetPlacementMapper::class),
				settingMapper: $this->createMock(AdminSettingMapper::class)
			),
			builders: new DashboardBuilders(
				templateService: $this->createMock(TemplateService::class),
				dashboardFactory: new DashboardFactory(),
				dashResolver: $this->createMock(DashboardResolver::class),
				adminTemplateService: $this->createMock(AdminTemplateService::class)
			),
			structure: new DashboardStructure(
				treeService: $this->createMock(DashboardTreeService::class),
				personalLayers: $this->createMock(PersonalLayerService::class),
				footerService: $this->createMock(FooterService::class)
			),
			platform: new DashboardPlatform(
				groupManager: $this->createMock(IGroupManager::class),
				db: $this->createMock(IDBConnection::class),
				config: $this->createMock(IConfig::class),
				l10nFactory: $this->createMock(IFactory::class)
			),
			outlets: new DashboardOutlets(logger: $this->createMock(LoggerInterface::class), urlGenerator: $urls)
		);

		return (new ReflectionMethod(DashboardService::class, 'defaultWidgetSeeds'))->invoke($service);
	}//end seeds()
}//end class
