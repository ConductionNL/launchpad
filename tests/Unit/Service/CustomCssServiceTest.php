<?php

/**
 * CustomCssService Test
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
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Service\CustomCssSanitiser;
use OCA\LaunchPad\Service\CustomCssService;
use OCA\LaunchPad\Service\DashboardLook;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CustomCssService::class)]
#[UsesClass(CustomCssSanitiser::class)]
#[UsesClass(DashboardLook::class)]
#[UsesClass(Dashboard::class)]
class CustomCssServiceTest extends TestCase {

	/** @var AdminSettingMapper&MockObject */
	private $settings;

	/** @var DashboardMapper&MockObject */
	private $dashboards;

	private CustomCssService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->settings   = $this->createMock(AdminSettingMapper::class);
		$this->dashboards = $this->createMock(DashboardMapper::class);
		$this->service    = new CustomCssService(
			settings: $this->settings,
			dashboards: $this->dashboards,
			sanitiser: new CustomCssSanitiser()
		);
	}

	public function testTheGlobalCssIsStoredCleanedAndScoped(): void {
		$this->settings->expects($this->once())->method('setSetting')->with(
			'custom_css',
			".launchpad-workspace .tile-widget__title {\n  font-weight: 700;\n}"
		);

		$result = $this->service->setGlobalCss(css: '@import url(https://evil.example/x.css); .tile-widget__title { font-weight: 700; }');

		$this->assertSame(
			['customCss' => ".launchpad-workspace .tile-widget__title {\n  font-weight: 700;\n}", 'removed' => ['@import']],
			$result
		);
	}

	public function testOversizedGlobalCssIsRefusedAndNothingStored(): void {
		$this->settings->expects($this->never())->method('setSetting');

		$this->expectException(InvalidArgumentException::class);
		$this->service->setGlobalCss(css: str_repeat('a', (CustomCssSanitiser::MAX_BYTES + 1)));
	}

	public function testTheGlobalCssIsReadBackAndNonStringsReadAsEmpty(): void {
		$this->settings->method('getValue')->with('custom_css', '')->willReturnOnConsecutiveCalls('.launchpad-workspace p {}', ['x']);

		$this->assertSame('.launchpad-workspace p {}', $this->service->getGlobalCss());
		$this->assertSame('', $this->service->getGlobalCss());
	}

	public function testADashboardsCssIsScopedToItsRegionAndSaved(): void {
		$dashboard = new Dashboard();
		$dashboard->setUuid('uuid-7');
		$this->dashboards->method('findByUuid')->with('uuid-7')->willReturn($dashboard);
		$this->dashboards->expects($this->once())->method('update')->with($dashboard)->willReturnArgument(0);

		$result = $this->service->setDashboardCss(uuid: 'uuid-7', css: 'body { display: none; }');

		$expected = ".launchpad-dashboard[data-dashboard-uuid=\"uuid-7\"] body {\n  display: none;\n}";
		$this->assertSame(['customCss' => $expected, 'removed' => []], $result);
		$this->assertSame($expected, $dashboard->getCustomCss());
		$this->assertNotNull($dashboard->getUpdatedAt());
	}

	public function testEmptyDashboardCssClearsTheColumn(): void {
		$dashboard = new Dashboard();
		$dashboard->setUuid('uuid-7');
		$dashboard->setCustomCss('.x { color: red; }');
		$this->dashboards->method('findByUuid')->willReturn($dashboard);

		$this->service->setDashboardCss(uuid: 'uuid-7', css: '');

		$this->assertNull($dashboard->getCustomCss());
	}

	public function testAnUnknownDashboardIsPassedOn(): void {
		$this->dashboards->method('findByUuid')->willThrowException(new DoesNotExistException('none'));
		$this->dashboards->expects($this->never())->method('update');

		$this->expectException(DoesNotExistException::class);
		$this->service->setDashboardCss(uuid: 'nope', css: 'p { margin: 0 }');
	}
}
