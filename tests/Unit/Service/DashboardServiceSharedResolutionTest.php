<?php

/**
 * DashboardServiceSharedResolutionTest
 *
 * Unit tests for how dashboards SHARED with a user enter
 * {@see \OCA\LaunchPad\Service\DashboardService}'s visibility set and its
 * active-dashboard precedence chain (REQ-SHARE-002, REQ-DASH-013,
 * REQ-DASH-018).
 *
 * REGRESSION GUARD: `DashboardMapper::findVisibleToUser()` unions exactly
 * three buckets — owned rows, `group_shared` rows in the user's groups, and
 * the `default` sentinel. Share rows were in none of them, so a share was
 * invisible everywhere: not in the switcher, never a resolution candidate,
 * and never reachable by uuid through `getDashboardForUser()`.
 *
 * PRECEDENCE UNDER TEST: a share is the LAST-RESORT candidate. Anything the
 * user owns, reaches through a group, or explicitly selected wins over it,
 * so a share can never hijack an existing selection.
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

use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardFactory;
use OCA\LaunchPad\Service\DashboardResolver;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\TemplateService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for share-aware visibility and resolution precedence.
 *
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DashboardServiceSharedResolutionTest extends TestCase
{

    /** @var DashboardMapper&MockObject */
    private $dashboardMapper;

    /** @var WidgetPlacementMapper&MockObject */
    private $placementMapper;

    /** @var DashboardResolver&MockObject */
    private $dashResolver;

    /** @var AdminTemplateService&MockObject */
    private $adminTemplateService;

    /** @var IGroupManager&MockObject */
    private $groupManager;

    /** @var IConfig&MockObject */
    private $config;

    private DashboardService $service;

    /**
     * Set up fresh mocks per test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->dashboardMapper      = $this->createMock(DashboardMapper::class);
        $this->placementMapper      = $this->createMock(WidgetPlacementMapper::class);
        $this->dashResolver         = $this->createMock(DashboardResolver::class);
        $this->adminTemplateService = $this->createMock(AdminTemplateService::class);
        $this->groupManager         = $this->createMock(IGroupManager::class);
        $this->config               = $this->createMock(IConfig::class);

        $this->adminTemplateService->method('getUserGroupIdsFor')->willReturn([]);
        $this->groupManager->method('isAdmin')->willReturn(false);
        $this->config->method('getUserValue')->willReturn('');

        $this->service = new DashboardService(
            dashboardMapper: $this->dashboardMapper,
            placementMapper: $this->placementMapper,
            settingMapper: $this->createMock(AdminSettingMapper::class),
            templateService: $this->createMock(TemplateService::class),
            dashboardFactory: new DashboardFactory(),
            dashResolver: $this->dashResolver,
            treeService: $this->createMock(\OCA\LaunchPad\Service\DashboardTreeService::class),
            groupManager: $this->groupManager,
            adminTemplateService: $this->adminTemplateService,
            db: $this->createMock(IDBConnection::class),
            config: $this->config,
            l10nFactory: $this->createMock(IFactory::class),
            logger: $this->createMock(LoggerInterface::class),
        );
    }//end setUp()

    /**
     * Build a published Dashboard entity.
     *
     * @param integer $id    Entity id.
     * @param string  $uuid  Entity uuid.
     * @param ?string $owner Owning user id.
     * @param string  $type  Dashboard type.
     *
     * @return Dashboard
     */
    private function makeDashboard(
        int $id,
        string $uuid,
        ?string $owner,
        string $type=Dashboard::TYPE_USER
    ): Dashboard {
        $dashboard = new Dashboard();
        $dashboard->setId($id);
        $dashboard->setUuid($uuid);
        $dashboard->setName('Board '.$id);
        $dashboard->setUserId($owner);
        $dashboard->setType($type);
        $dashboard->setPermissionLevel(Dashboard::PERMISSION_FULL);
        $dashboard->setPublicationStatus(Dashboard::STATUS_PUBLISHED);

        return $dashboard;
    }//end makeDashboard()

    /**
     * REQ-SHARE-002: a shared dashboard is part of "visible to me", tagged
     * with the `shared` source. Without the fix `getVisibleToUser()` returns
     * only what the mapper's three-bucket union produced — an empty set.
     *
     * @return void
     */
    public function testVisibleToUserIncludesSharedDashboards(): void
    {
        $shared = $this->makeDashboard(7, 'uuid-shared', 'owner');

        $this->dashboardMapper->method('findVisibleToUser')->willReturn([]);
        $this->dashResolver->method('findSharedDashboards')->willReturn(
            [
                [
                    'dashboard' => $shared,
                    'source'    => Dashboard::SOURCE_SHARED,
                ],
            ]
        );

        $visible = $this->service->getVisibleToUser(userId: 'recipient');

        $this->assertCount(1, $visible);
        $this->assertSame('uuid-shared', $visible[0]['dashboard']->getUuid());
        $this->assertSame(Dashboard::SOURCE_SHARED, $visible[0]['source']);
    }//end testVisibleToUserIncludesSharedDashboards()

    /**
     * A dashboard reached BOTH by ownership/group and by a share keeps its
     * stronger source tag — the shared copy is deduped away by uuid.
     *
     * @return void
     */
    public function testSharedEntryIsDedupedAgainstAStrongerSource(): void
    {
        $own = $this->makeDashboard(7, 'uuid-dup', 'recipient');

        $this->dashboardMapper->method('findVisibleToUser')->willReturn(
            [
                [
                    'dashboard' => $own,
                    'source'    => Dashboard::SOURCE_USER,
                ],
            ]
        );
        $this->dashResolver->method('findSharedDashboards')->willReturn(
            [
                [
                    'dashboard' => $own,
                    'source'    => Dashboard::SOURCE_SHARED,
                ],
            ]
        );

        $visible = $this->service->getVisibleToUser(userId: 'recipient');

        $this->assertCount(1, $visible);
        $this->assertSame(Dashboard::SOURCE_USER, $visible[0]['source']);
    }//end testSharedEntryIsDedupedAgainstAStrongerSource()

    /**
     * REQ-DASH-018 step 6b: a recipient with NOTHING of their own resolves
     * to the dashboard shared with them instead of the empty state. This is
     * the whole point of the fix — without it `resolveActiveDashboard()`
     * returns null and the share is inert.
     *
     * @return void
     */
    public function testResolveActiveDashboardLandsOnASharedDashboard(): void
    {
        $shared = $this->makeDashboard(7, 'uuid-shared', 'owner');

        $this->dashboardMapper->method('findVisibleToUser')->willReturn([]);
        $this->dashResolver->method('findSharedDashboards')->willReturn(
            [
                [
                    'dashboard' => $shared,
                    'source'    => Dashboard::SOURCE_SHARED,
                ],
            ]
        );

        $result = $this->service->resolveActiveDashboard(
            userId: 'recipient',
            primaryGroupId: null
        );

        $this->assertNotNull($result, 'A recipient must land on the shared dashboard.');
        $this->assertSame('uuid-shared', $result['dashboard']->getUuid());
        $this->assertSame(Dashboard::SOURCE_SHARED, $result['source']);
    }//end testResolveActiveDashboardLandsOnASharedDashboard()

    /**
     * PRECEDENCE: the user's OWN dashboard beats a shared one. A share must
     * never hijack a selection the user already has.
     *
     * @return void
     */
    public function testOwnDashboardWinsOverASharedOne(): void
    {
        $own    = $this->makeDashboard(1, 'uuid-own', 'recipient');
        $shared = $this->makeDashboard(7, 'uuid-shared', 'owner');

        $this->dashboardMapper->method('findVisibleToUser')->willReturn(
            [
                [
                    'dashboard' => $own,
                    'source'    => Dashboard::SOURCE_USER,
                ],
            ]
        );
        $this->dashResolver->method('findSharedDashboards')->willReturn(
            [
                [
                    'dashboard' => $shared,
                    'source'    => Dashboard::SOURCE_SHARED,
                ],
            ]
        );

        $result = $this->service->resolveActiveDashboard(
            userId: 'recipient',
            primaryGroupId: null
        );

        $this->assertSame('uuid-own', $result['dashboard']->getUuid());
        $this->assertSame(Dashboard::SOURCE_USER, $result['source']);
    }//end testOwnDashboardWinsOverASharedOne()

    /**
     * PRECEDENCE: a group-shared dashboard (steps 2-5) also beats a share.
     *
     * @return void
     */
    public function testGroupSharedDashboardWinsOverASharedOne(): void
    {
        $groupBoard = $this->makeDashboard(
            2,
            'uuid-group',
            null,
            Dashboard::TYPE_GROUP_SHARED
        );
        $groupBoard->setGroupId(Dashboard::DEFAULT_GROUP_ID);
        $shared = $this->makeDashboard(7, 'uuid-shared', 'owner');

        $this->dashboardMapper->method('findVisibleToUser')->willReturn(
            [
                [
                    'dashboard' => $groupBoard,
                    'source'    => Dashboard::SOURCE_DEFAULT,
                ],
            ]
        );
        $this->dashResolver->method('findSharedDashboards')->willReturn(
            [
                [
                    'dashboard' => $shared,
                    'source'    => Dashboard::SOURCE_SHARED,
                ],
            ]
        );

        $result = $this->service->resolveActiveDashboard(
            userId: 'recipient',
            primaryGroupId: null
        );

        $this->assertSame('uuid-group', $result['dashboard']->getUuid());
    }//end testGroupSharedDashboardWinsOverASharedOne()

    /**
     * REQ-SHARE-002: `getEffectiveDashboard()` (the `GET /api/dashboard`
     * path) consults the share BEFORE auto-provisioning a dashboard from a
     * template — minting an empty personal dashboard would permanently bury
     * the share behind step 6.
     *
     * @return void
     */
    public function testEffectiveDashboardPrefersASharedDashboardOverTemplateCreation(): void
    {
        $shared = $this->makeDashboard(7, 'uuid-shared', 'owner');

        $this->dashResolver->method('tryGetActiveDashboard')->willReturn(null);
        $this->dashResolver->method('tryActivateExistingDashboard')->willReturn(null);
        $this->dashResolver->method('tryGetSharedDashboard')->willReturn(
            [
                'dashboard'       => $shared,
                'placements'      => [],
                'permissionLevel' => Dashboard::PERMISSION_VIEW_ONLY,
            ]
        );

        // If the shared step were missing, resolution would fall through to
        // the template path, which asks the mapper for admin templates.
        $this->dashboardMapper
            ->expects($this->never())
            ->method('findAdminTemplates');

        $result = $this->service->getEffectiveDashboard(userId: 'recipient');

        $this->assertNotNull($result);
        $this->assertSame('uuid-shared', $result['dashboard']->getUuid());
        $this->assertSame(
            Dashboard::PERMISSION_VIEW_ONLY,
            $result['permissionLevel']
        );
    }//end testEffectiveDashboardPrefersASharedDashboardOverTemplateCreation()

    /**
     * REQ-SHARE-004: fetching a shared dashboard by id reports the SHARE's
     * permission level, not the owning row's.
     *
     * @return void
     */
    public function testGetDashboardForUserReportsTheSharePermissionLevel(): void
    {
        $shared = $this->makeDashboard(7, 'uuid-shared', 'owner');

        $this->dashboardMapper->method('findVisibleToUser')->willReturn([]);
        $this->dashResolver->method('findSharedDashboards')->willReturn(
            [
                [
                    'dashboard' => $shared,
                    'source'    => Dashboard::SOURCE_SHARED,
                ],
            ]
        );
        $this->dashResolver->method('findSharedLevels')->willReturn(
            [7 => Dashboard::PERMISSION_VIEW_ONLY]
        );
        $this->placementMapper->method('findByDashboardId')->willReturn([]);

        $result = $this->service->getDashboardForUser(
            dashboardId: 7,
            userId: 'recipient'
        );

        $this->assertNotNull($result, 'A shared dashboard must be fetchable by id.');
        $this->assertSame(
            Dashboard::PERMISSION_VIEW_ONLY,
            $result['permissionLevel']
        );
    }//end testGetDashboardForUserReportsTheSharePermissionLevel()
}//end class
