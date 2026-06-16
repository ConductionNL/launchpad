<?php

/**
 * PlacementServiceQuotaWiringTest
 *
 * Verifies the widget quota assert is wired into every user-initiated
 * placement-creation path in PlacementService (REQ-QUOTA-003): a placement
 * is rejected (never truncated) once the dashboard is at the limit, and the
 * mapper insert never runs.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Exception\QuotaExceededException;
use OCA\LaunchPad\Service\PlacementService;
use OCA\LaunchPad\Service\PlacementUpdater;
use OCA\LaunchPad\Service\QuotaService;
use OCA\LaunchPad\Service\TileUpdater;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PlacementServiceQuotaWiringTest extends TestCase
{

    /** @var WidgetPlacementMapper&MockObject */
    private $placementMapper;

    /** @var AdminSettingMapper&MockObject */
    private $settingMapper;

    private PlacementService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->placementMapper = $this->createMock(WidgetPlacementMapper::class);
        $this->settingMapper   = $this->createMock(AdminSettingMapper::class);
        /** @var DashboardMapper&MockObject $dashboardMapper */
        $dashboardMapper       = $this->createMock(DashboardMapper::class);

        $quotaService = new QuotaService(
            settingMapper: $this->settingMapper,
            dashboardMapper: $dashboardMapper,
            placementMapper: $this->placementMapper,
        );

        $this->service = new PlacementService(
            placementMapper: $this->placementMapper,
            tileUpdater: $this->createMock(TileUpdater::class),
            placementUpdater: $this->createMock(PlacementUpdater::class),
            publicShareContext: null,
            quotaService: $quotaService,
        );
    }//end setUp()

    /**
     * Wire the widget quota to `$limit`.
     *
     * @param int $limit The per-dashboard widget quota.
     *
     * @return void
     */
    private function withWidgetLimit(int $limit): void
    {
        $this->settingMapper->method('getValue')->willReturnCallback(
                function (string $k, $default=null) use ($limit) {
                    if ($k === AdminSetting::KEY_MAX_WIDGETS_PER_DASHBOARD) {
                        return $limit;
                    }

                    return $default;
                }
                );
    }//end withWidgetLimit()

    public function testAddWidgetThrowsAtQuota(): void
    {
        $this->withWidgetLimit(40);
        $this->placementMapper->method('countByDashboardId')->willReturn(40);
        $this->placementMapper->expects($this->never())->method('insert');

        $this->expectException(QuotaExceededException::class);
        $this->service->addWidget(dashboardId: 7, widgetId: 'clock');
    }//end testAddWidgetThrowsAtQuota()

    public function testAddTileThrowsAtQuota(): void
    {
        $this->withWidgetLimit(40);
        $this->placementMapper->method('countByDashboardId')->willReturn(40);
        $this->placementMapper->expects($this->never())->method('insert');

        $this->expectException(QuotaExceededException::class);
        $this->service->addTileFromArray(dashboardId: 7, tileData: ['title' => 'X']);
    }//end testAddTileThrowsAtQuota()

    public function testAddWidgetAllowedBelowQuota(): void
    {
        $this->withWidgetLimit(40);
        $this->placementMapper->method('countByDashboardId')->willReturn(39);
        $this->placementMapper->expects($this->once())
            ->method('insert')
            ->willReturnArgument(0);

        $this->service->addWidget(dashboardId: 7, widgetId: 'clock');
    }//end testAddWidgetAllowedBelowQuota()
}//end class
