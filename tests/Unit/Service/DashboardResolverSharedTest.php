<?php

/**
 * DashboardResolverSharedTest
 *
 * Unit tests for share-aware resolution in
 * {@see \OCA\LaunchPad\Service\DashboardResolver}.
 *
 * REGRESSION GUARD: before these landed, the resolver only ever considered
 * OWNED, group and template dashboards — `findActiveByUserId` /
 * `findByUserId`, both scoped to the caller's own rows. A dashboard merely
 * SHARED with a user was never a resolution candidate, so a recipient with
 * no dashboard of their own landed on the empty state and the share did
 * nothing at all.
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

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\DashboardResolver;
use OCA\LaunchPad\Service\DashboardShareService;
use OCA\LaunchPad\Service\TemplateService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DashboardResolver's share-aware resolution (REQ-SHARE-002).
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DashboardResolverSharedTest extends TestCase
{

    /** @var DashboardMapper&MockObject */
    private $dashboardMapper;

    /** @var WidgetPlacementMapper&MockObject */
    private $placementMapper;

    /** @var DashboardShareService&MockObject */
    private $shareService;

    private DashboardResolver $resolver;

    /**
     * Set up fresh mocks per test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->dashboardMapper = $this->createMock(DashboardMapper::class);
        $this->placementMapper = $this->createMock(WidgetPlacementMapper::class);
        $this->shareService    = $this->createMock(DashboardShareService::class);

        $this->resolver = new DashboardResolver(
            dashboardMapper: $this->dashboardMapper,
            placementMapper: $this->placementMapper,
            templateService: $this->createMock(TemplateService::class),
            shareService: $this->shareService,
        );
    }//end setUp()

    /**
     * Build a Dashboard entity for the fixtures below.
     *
     * @param integer $id     Entity id.
     * @param string  $uuid   Entity uuid.
     * @param string  $name   Display name.
     * @param ?string $owner  Owning user id (null for group_shared rows).
     * @param string  $level  The row's own permission level.
     *
     * @return Dashboard
     */
    private function makeDashboard(
        int $id,
        string $uuid,
        string $name,
        ?string $owner,
        string $level=Dashboard::PERMISSION_FULL
    ): Dashboard {
        $dashboard = new Dashboard();
        $dashboard->setId($id);
        $dashboard->setUuid($uuid);
        $dashboard->setName($name);
        $dashboard->setUserId($owner);
        $dashboard->setType(Dashboard::TYPE_USER);
        $dashboard->setPermissionLevel($level);
        $dashboard->setPublicationStatus(Dashboard::STATUS_PUBLISHED);

        return $dashboard;
    }//end makeDashboard()

    /**
     * REQ-SHARE-002: a dashboard shared with the user IS a resolution
     * candidate. Without the fix `tryGetSharedDashboard()` does not exist
     * at all and the recipient resolves to nothing.
     *
     * @return void
     */
    public function testTryGetSharedDashboardResolvesASharedDashboard(): void
    {
        $shared = $this->makeDashboard(7, 'uuid-shared', 'Team board', 'owner');

        $this->shareService
            ->method('resolveSharedDashboards')
            ->with('recipient', ['staff'])
            ->willReturn([7 => Dashboard::PERMISSION_VIEW_ONLY]);

        $this->dashboardMapper->method('find')->with(7)->willReturn($shared);
        $this->placementMapper->method('findByDashboardId')->willReturn([]);

        $result = $this->resolver->tryGetSharedDashboard(
            userId: 'recipient',
            userGroupIds: ['staff']
        );

        $this->assertNotNull($result, 'A shared dashboard must be resolvable.');
        $this->assertSame($shared, $result['dashboard']);
    }//end testTryGetSharedDashboardResolvesASharedDashboard()

    /**
     * REQ-SHARE-004: the returned permissionLevel comes from the SHARE, not
     * from the dashboard row — the row carries the OWNER's level, which
     * would advertise edit rights to a view-only recipient.
     *
     * @return void
     */
    public function testSharedResultCarriesTheSharePermissionNotTheOwners(): void
    {
        // Owner's row says "full"; the share grants only "view_only".
        $shared = $this->makeDashboard(
            7,
            'uuid-shared',
            'Team board',
            'owner',
            Dashboard::PERMISSION_FULL
        );

        $this->shareService
            ->method('resolveSharedDashboards')
            ->willReturn([7 => Dashboard::PERMISSION_VIEW_ONLY]);
        $this->dashboardMapper->method('find')->willReturn($shared);
        $this->placementMapper->method('findByDashboardId')->willReturn([]);

        $result = $this->resolver->tryGetSharedDashboard(
            userId: 'recipient',
            userGroupIds: []
        );

        $this->assertSame(
            Dashboard::PERMISSION_VIEW_ONLY,
            $result['permissionLevel'],
            'The share level must win over the owning row permission level.'
        );
    }//end testSharedResultCarriesTheSharePermissionNotTheOwners()

    /**
     * A dashboard the user already OWNS is never re-surfaced as "shared" —
     * ownership is the stronger claim.
     *
     * @return void
     */
    public function testOwnDashboardIsNotReportedAsShared(): void
    {
        $own = $this->makeDashboard(7, 'uuid-own', 'My board', 'recipient');

        $this->shareService
            ->method('resolveSharedDashboards')
            ->willReturn([7 => Dashboard::PERMISSION_FULL]);
        $this->dashboardMapper->method('find')->willReturn($own);

        $this->assertNull(
            $this->resolver->tryGetSharedDashboard(
                userId: 'recipient',
                userGroupIds: []
            )
        );
        $this->assertSame(
            [],
            $this->resolver->findSharedDashboards(
                userId: 'recipient',
                userGroupIds: []
            )
        );
    }//end testOwnDashboardIsNotReportedAsShared()

    /**
     * REQ-SHARE-002: shared entries are tagged with the dedicated
     * `shared` source so callers (switcher sidebar, resolver precedence)
     * can tell them apart from owned and group-reached rows.
     *
     * @return void
     */
    public function testFindSharedDashboardsTagsTheSharedSource(): void
    {
        $shared = $this->makeDashboard(9, 'uuid-shared', 'Team board', 'owner');

        $this->shareService
            ->method('resolveSharedDashboards')
            ->willReturn([9 => Dashboard::PERMISSION_VIEW_ONLY]);
        $this->dashboardMapper->method('find')->willReturn($shared);

        $entries = $this->resolver->findSharedDashboards(
            userId: 'recipient',
            userGroupIds: []
        );

        $this->assertCount(1, $entries);
        $this->assertSame(Dashboard::SOURCE_SHARED, $entries[0]['source']);
        $this->assertSame($shared, $entries[0]['dashboard']);
    }//end testFindSharedDashboardsTagsTheSharedSource()

    /**
     * An orphaned share row (dashboard deleted, share row survived) must be
     * skipped rather than taking the whole resolution down.
     *
     * @return void
     */
    public function testOrphanedShareRowIsSkipped(): void
    {
        $this->shareService
            ->method('resolveSharedDashboards')
            ->willReturn([404 => Dashboard::PERMISSION_FULL]);
        $this->dashboardMapper
            ->method('find')
            ->willThrowException(new DoesNotExistException('gone'));

        $this->assertNull(
            $this->resolver->tryGetSharedDashboard(
                userId: 'recipient',
                userGroupIds: []
            )
        );
    }//end testOrphanedShareRowIsSkipped()

    /**
     * With no share service wired (legacy positional construction) the
     * resolver degrades to "nothing is shared" instead of exploding.
     *
     * @return void
     */
    public function testMissingShareServiceYieldsNoSharedDashboards(): void
    {
        $resolver = new DashboardResolver(
            dashboardMapper: $this->dashboardMapper,
            placementMapper: $this->placementMapper,
            templateService: $this->createMock(TemplateService::class),
        );

        $this->assertSame([], $resolver->findSharedLevels('recipient', []));
        $this->assertSame([], $resolver->findSharedDashboards('recipient', []));
        $this->assertNull($resolver->tryGetSharedDashboard('recipient', []));
    }//end testMissingShareServiceYieldsNoSharedDashboards()
}//end class
