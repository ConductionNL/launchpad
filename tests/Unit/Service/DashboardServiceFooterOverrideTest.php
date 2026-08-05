<?php

/**
 * DashboardService Footer-Override Test
 *
 * Covers the per-dashboard footer override path added by the
 * `footer-customization` change (REQ-FTR-006). Ensures the
 * `applyDashboardUpdates()` flow validates the mode, sanitises the
 * dashboard-specific HTML, clears stale HTML on mode flip, and rejects
 * `custom` without an HTML body.
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

use InvalidArgumentException;
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
use OCA\LaunchPad\Service\TemplateService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DashboardServiceFooterOverrideTest extends TestCase
{
    /** @var DashboardMapper&MockObject */
    private $dashboardMapper;

    /** @var FooterService&MockObject */
    private $footerService;

    private DashboardService $service;

    protected function setUp(): void
    {
        $this->dashboardMapper = $this->createMock(DashboardMapper::class);
        $this->footerService   = $this->createMock(FooterService::class);

        $this->service = new DashboardService(
            dashboardMapper: $this->dashboardMapper,
            placementMapper: $this->createMock(WidgetPlacementMapper::class),
            settingMapper: $this->createMock(AdminSettingMapper::class),
            templateService: $this->createMock(TemplateService::class),
            dashboardFactory: new DashboardFactory(),
            dashResolver: $this->createMock(DashboardResolver::class),
            treeService: $this->createMock(DashboardTreeService::class),
            groupManager: $this->createMock(IGroupManager::class),
            adminTemplateService: $this->createMock(AdminTemplateService::class),
            db: $this->createMock(IDBConnection::class),
            config: $this->createMock(IConfig::class),
            l10nFactory: $this->createMock(IFactory::class),
            logger: $this->createMock(LoggerInterface::class),
            footerService: $this->footerService,
        );
    }

    private function makeDashboard(string $userId = 'alice'): Dashboard
    {
        $dashboard = new Dashboard();
        $dashboard->setUserId($userId);
        $dashboard->setUuid('uuid-1');
        $dashboard->setName('Test');
        return $dashboard;
    }

    public function testCustomModePersistsSanitisedHtml(): void
    {
        $dashboard = $this->makeDashboard();
        $this->dashboardMapper->method('find')->willReturn($dashboard);
        $this->dashboardMapper->method('update')->willReturnArgument(0);

        $this->footerService
            ->expects($this->once())
            ->method('sanitiseHtml')
            ->with('<p>Mine</p>')
            ->willReturn('<p>Mine</p>');

        $result = $this->service->updateDashboard(
            dashboardId: 1,
            userId: 'alice',
            data: [
                'dashboardFooterMode' => Dashboard::FOOTER_MODE_CUSTOM,
                'dashboardFooterHtml' => '<p>Mine</p>',
            ]
        );

        $this->assertSame(
            Dashboard::FOOTER_MODE_CUSTOM,
            $result->getDashboardFooterMode()
        );
        $this->assertSame('<p>Mine</p>', $result->getDashboardFooterHtml());
    }

    public function testCustomModeWithoutHtmlIsRejected(): void
    {
        $dashboard = $this->makeDashboard();
        $this->dashboardMapper->method('find')->willReturn($dashboard);
        // Sanitiser MUST NOT be reached.
        $this->footerService->expects($this->never())->method('sanitiseHtml');

        $this->expectException(InvalidArgumentException::class);
        $this->service->updateDashboard(
            dashboardId: 1,
            userId: 'alice',
            data: ['dashboardFooterMode' => Dashboard::FOOTER_MODE_CUSTOM]
        );
    }

    public function testInheritModeClearsStaleHtml(): void
    {
        $dashboard = $this->makeDashboard();
        $dashboard->setDashboardFooterMode(Dashboard::FOOTER_MODE_CUSTOM);
        // phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
        $dashboard->setDashboardFooterHtml('<p>Old</p>');
        $this->dashboardMapper->method('find')->willReturn($dashboard);
        $this->dashboardMapper->method('update')->willReturnArgument(0);

        $this->footerService->expects($this->never())->method('sanitiseHtml');

        $result = $this->service->updateDashboard(
            dashboardId: 1,
            userId: 'alice',
            data: ['dashboardFooterMode' => Dashboard::FOOTER_MODE_INHERIT]
        );

        $this->assertSame(
            Dashboard::FOOTER_MODE_INHERIT,
            $result->getDashboardFooterMode()
        );
        $this->assertNull($result->getDashboardFooterHtml());
    }

    public function testHiddenModeClearsStaleHtml(): void
    {
        $dashboard = $this->makeDashboard();
        $dashboard->setDashboardFooterMode(Dashboard::FOOTER_MODE_CUSTOM);
        // phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
        $dashboard->setDashboardFooterHtml('<p>Old</p>');
        $this->dashboardMapper->method('find')->willReturn($dashboard);
        $this->dashboardMapper->method('update')->willReturnArgument(0);

        $result = $this->service->updateDashboard(
            dashboardId: 1,
            userId: 'alice',
            data: ['dashboardFooterMode' => Dashboard::FOOTER_MODE_HIDDEN]
        );

        $this->assertSame(
            Dashboard::FOOTER_MODE_HIDDEN,
            $result->getDashboardFooterMode()
        );
        $this->assertNull($result->getDashboardFooterHtml());
    }

    public function testInvalidModeRejected(): void
    {
        $dashboard = $this->makeDashboard();
        $this->dashboardMapper->method('find')->willReturn($dashboard);

        $this->expectException(InvalidArgumentException::class);
        $this->service->updateDashboard(
            dashboardId: 1,
            userId: 'alice',
            data: ['dashboardFooterMode' => 'spiral']
        );
    }

    public function testResolveFooterDelegatesToFooterService(): void
    {
        $dashboard = $this->makeDashboard();
        $this->footerService
            ->expects($this->once())
            ->method('resolveFooterForDashboard')
            ->with($dashboard)
            ->willReturn(['mode' => 'global', 'html' => 'x', 'config' => null, 'backgroundColor' => null, 'textColor' => null]);

        $resolved = $this->service->resolveFooterForDashboard(dashboard: $dashboard);
        $this->assertNotNull($resolved);
        $this->assertSame('global', $resolved['mode']);
    }
}
