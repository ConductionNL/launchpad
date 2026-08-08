<?php

/**
 * DashboardServiceQuotaWiringTest
 *
 * Verifies that the dashboard quota assert is wired into EVERY
 * user-initiated dashboard creation path in DashboardService (orphan-auth:
 * an assert that exists but is not called from every path is no check).
 * Covers REQ-QUOTA-002 for `createDashboard()` and `forkAsPersonal()`.
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

use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Exception\QuotaExceededException;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardFactory;
use OCA\LaunchPad\Service\DashboardResolver;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\QuotaService;
use OCA\LaunchPad\Service\TemplateService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Mirrors the service constructor.
 */
class DashboardServiceQuotaWiringTest extends TestCase
{

    /** @var AdminSettingMapper&MockObject */
    private $settingMapper;

    /** @var DashboardMapper&MockObject */
    private $dashboardMapper;

    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dashboardMapper = $this->createMock(DashboardMapper::class);
        /** @var WidgetPlacementMapper&MockObject $placementMapper */
        $placementMapper       = $this->createMock(WidgetPlacementMapper::class);
        $this->settingMapper   = $this->createMock(AdminSettingMapper::class);

        $quotaService = new QuotaService(
            settingMapper: $this->settingMapper,
            dashboardMapper: $this->dashboardMapper,
            placementMapper: $placementMapper,
        );

        $this->service = new DashboardService(
            dashboardMapper: $this->dashboardMapper,
            placementMapper: $placementMapper,
            settingMapper: $this->settingMapper,
            templateService: $this->createMock(TemplateService::class),
            dashboardFactory: new DashboardFactory(),
            dashResolver: $this->createMock(DashboardResolver::class),
            treeService: $this->createMock(\OCA\LaunchPad\Service\DashboardTreeService::class),
            groupManager: $this->createMock(IGroupManager::class),
            adminTemplateService: $this->createMock(AdminTemplateService::class),
            db: $this->createMock(IDBConnection::class),
            config: $this->createMock(IConfig::class),
            l10nFactory: $this->createMock(IFactory::class),
            logger: $this->createMock(LoggerInterface::class),
            footerService: $this->createMock(\OCA\LaunchPad\Service\FooterService::class),
            quotaService: $quotaService,
        );
    }//end setUp()

    /**
     * Wire the setting mapper so the dashboard quota is `$limit` and
     * allow_multiple_dashboards is true.
     *
     * @param int $limit The dashboard quota.
     *
     * @return void
     */
    private function withDashboardLimit(int $limit): void
    {
        $this->settingMapper->method('getValue')->willReturnCallback(
                function (string $k, $default=null) use ($limit) {
                    if ($k === AdminSetting::KEY_MAX_DASHBOARDS_PER_USER) {
                        return $limit;
                    }

                    if ($k === AdminSetting::KEY_ALLOW_MULTIPLE_DASHBOARDS) {
                        return true;
                    }

                    if ($k === AdminSetting::KEY_ALLOW_USER_DASHBOARDS) {
                        return true;
                    }

                    return $default;
                }
                );
    }//end withDashboardLimit()

    public function testCreateDashboardThrowsAtQuota(): void
    {
        // REQ-QUOTA-002 — create is bound by the quota choke point.
        $this->withDashboardLimit(5);
        $this->dashboardMapper->method('countPersonalByUserId')->willReturn(5);
        // No row is ever inserted when the quota assert fires first.
        $this->dashboardMapper->expects($this->never())->method('insert');

        $this->expectException(QuotaExceededException::class);
        $this->service->createDashboard(userId: 'alice', name: 'Sixth');
    }//end testCreateDashboardThrowsAtQuota()

    public function testForkAsPersonalThrowsAtQuota(): void
    {
        // REQ-QUOTA-002 — fork creates a personal dashboard and is bound
        // by the same quota. The assert fires before the source lookup /
        // transaction, so no DB write happens.
        $this->withDashboardLimit(5);
        $this->dashboardMapper->method('countPersonalByUserId')->willReturn(5);
        $this->dashboardMapper->expects($this->never())->method('insert');

        $this->expectException(QuotaExceededException::class);
        $this->service->forkAsPersonal(userId: 'alice', sourceUuid: 'some-uuid');
    }//end testForkAsPersonalThrowsAtQuota()
}//end class
