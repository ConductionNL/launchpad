<?php

/**
 * DashboardService Look Test
 *
 * The look of a dashboard through updateDashboard(): accent, background
 * colour and background image are validated and stored, an empty value
 * clears them, and an accent under 3:1 against the background is refused
 * (platform-dashboard-theme-and-css REQ-THM-001/002).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-a-look-must-stay-readable-req-thm-002
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\DashboardOutlets;
use OCA\LaunchPad\Service\DashboardPlatform;
use OCA\LaunchPad\Service\DashboardStructure;
use OCA\LaunchPad\Service\DashboardBuilders;
use OCA\LaunchPad\Service\DashboardStores;
use InvalidArgumentException;
use OCA\LaunchPad\Exception\ContrastTooLowException;
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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DashboardServiceLookTest extends TestCase {
	/** @var DashboardMapper&MockObject */
	private $dashboardMapper;

	/** @var FooterService&MockObject */
	private $footerService;

	private DashboardService $service;

	protected function setUp(): void {
		$this->dashboardMapper = $this->createMock(DashboardMapper::class);
		$this->footerService = $this->createMock(FooterService::class);

		$this->service = new DashboardService(
			stores: new DashboardStores(
				dashboardMapper: $this->dashboardMapper,
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
				footerService: $this->footerService
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
	}

	private function makeDashboard(string $userId = 'alice'): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setUserId($userId);
		$dashboard->setUuid('uuid-1');
		$dashboard->setName('Test');
		return $dashboard;
	}

	private function update(Dashboard $dashboard, array $data): Dashboard {
		$this->dashboardMapper->method('find')->willReturn($dashboard);
		$this->dashboardMapper->method('update')->willReturnArgument(0);

		return $this->service->updateDashboard(dashboardId: 1, userId: 'alice', data: $data);
	}

	public function testAGreenTeamDashboardStoresItsLook(): void {
		$result = $this->update(
			dashboard: $this->makeDashboard(),
			data: ['themeAccent' => '#1F5C3A', 'themeBackgroundResource' => 'resource_6735a1b2c3d4e5.12345678.jpg']
		);

		$this->assertSame('#1f5c3a', $result->getThemeAccent(), 'stored lower-case');
		$this->assertNull($result->getThemeBackgroundColor(), 'no background of its own: follows the theme');
		$this->assertSame('resource_6735a1b2c3d4e5.12345678.jpg', $result->getThemeBackgroundResource());
	}

	public function testAPaleAccentOnTheWhiteInstanceBackgroundIsRefusedWithTheRatio(): void {
		$dashboard = $this->makeDashboard();
		$this->dashboardMapper->expects($this->never())->method('update');

		try {
			$this->update(dashboard: $dashboard, data: ['themeAccent' => '#f5f5dc']);
			$this->fail('A pale accent was saved.');
		} catch (ContrastTooLowException $refusal) {
			$this->assertSame(1.1, $refusal->getRatio());
			$this->assertSame('Buttons in this colour are hard to see (contrast 1.1 to 1, needs 3 to 1)', $refusal->getMessage());
		}

		$this->assertNull($dashboard->getThemeAccent(), 'nothing was set');
	}

	public function testTheAccentIsCheckedAgainstTheBackgroundThatResults(): void {
		$dashboard = $this->makeDashboard();
		$dashboard->setThemeAccent('#ffffff');
		$dashboard->setThemeBackgroundColor('#12301f');

		// White buttons on dark green are fine; clearing the background puts them on white.
		$this->expectException(ContrastTooLowException::class);
		$this->update(dashboard: $dashboard, data: ['themeBackgroundColor' => '']);
	}

	public function testAnEmptyValueClearsTheLook(): void {
		$dashboard = $this->makeDashboard();
		$dashboard->setThemeAccent('#1f5c3a');
		$dashboard->setThemeBackgroundColor('#ffffff');
		$dashboard->setThemeBackgroundResource('resource_a.png');

		$result = $this->update(dashboard: $dashboard, data: ['themeAccent' => '', 'themeBackgroundColor' => ' ', 'themeBackgroundResource' => '']);

		$this->assertNull($result->getThemeAccent());
		$this->assertNull($result->getThemeBackgroundColor());
		$this->assertNull($result->getThemeBackgroundResource());
	}

	public function testAnUpdateWithoutLookFieldsLeavesTheLookAlone(): void {
		$dashboard = $this->makeDashboard();
		$dashboard->setThemeAccent('#f5f5dc');

		$result = $this->update(dashboard: $dashboard, data: ['name' => 'Renamed']);

		$this->assertSame('#f5f5dc', $result->getThemeAccent(), 'a rename does not re-judge an old accent');
	}

	public function testMalformedColoursAndImagesAreRefused(): void {
		foreach ([
			['themeAccent' => 'green'],
			['themeBackgroundColor' => '#12301'],
			['themeBackgroundColor' => 'url(x)'],
			['themeBackgroundResource' => '../../config/config.php'],
			['themeBackgroundResource' => 'https://evil.example/x.png'],
			['themeBackgroundResource' => 'resource_a.php'],
		] as $data) {
			$dashboard = $this->makeDashboard();
			try {
				$this->update(dashboard: $dashboard, data: $data);
				$this->fail('Accepted ' . json_encode($data));
			} catch (InvalidArgumentException $refusal) {
				$this->assertNotInstanceOf(ContrastTooLowException::class, $refusal);
			}
		}
	}

	public function testAShortHexIsExpanded(): void {
		$result = $this->update(dashboard: $this->makeDashboard(), data: ['themeAccent' => '#123']);

		$this->assertSame('#112233', $result->getThemeAccent());
	}
}
