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
use OCA\LaunchPad\Support\SeedTileBrands;
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
	 * Every seeded brand tile names a shipped white SVG on its brand colour.
	 * A tile icon that does not exist under `img/`, or a CSS icon class, shows
	 * an empty tile, so this fails on a missing tile icon (REQ-DWB-007).
	 *
	 * @return void
	 *
	 * @spec openspec/specs/default-widget-bundle/spec.md#requirement-req-dwb-007-a-default-tile-is-the-brands-white-logo-on-the-brands-colour
	 */
	public function testEverySeededTileIconIsAShippedWhiteSvg(): void {
		$tiles = array_values(array_filter(
			$this->seeds(urls: null),
			static fn (array $seed): bool => $seed['tile'] !== null
		));

		$this->assertSame(expected: array_keys(SeedTileBrands::BRANDS), actual: array_column(array_column($tiles, 'tile'), 'title'));
		foreach ($tiles as $seed) {
			$tile = $seed['tile'];
			$this->assertSame(expected: 'url', actual: $tile['iconType'], message: $tile['title']);
			$this->assertStringStartsWith(prefix: '/apps/launchpad/img/', string: $tile['icon'], message: $tile['title']);
			$file = dirname(__DIR__, 3).'/img/'.substr($tile['icon'], strlen('/apps/launchpad/img/'));
			$this->assertFileExists(filename: $file, message: $tile['title'].' tile icon is not shipped');
			$this->assertMatchesRegularExpression(pattern: '/-white\.svg$/', string: $file, message: $tile['title']);
			$this->assertStringContainsString(needle: '<svg', haystack: (string) file_get_contents($file), message: $tile['title']);
			$this->assertSame(expected: SeedTileBrands::BRANDS[$tile['title']]['backgroundColor'], actual: $tile['backgroundColor']);
			$this->assertSame(expected: '#ffffff', actual: $tile['textColor']);
		}
	}//end testEverySeededTileIconIsAShippedWhiteSvg()

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
