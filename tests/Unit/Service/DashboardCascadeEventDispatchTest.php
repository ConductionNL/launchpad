<?php

/**
 * DashboardCascadeEventDispatchTest
 *
 * Regression tests for SB1 (wave-12): DashboardDeletedEvent must be
 * dispatched from every dashboard-delete call site so cascade listeners
 * (comments, reactions, versions, metadata_values, public_shares,
 * view_analytics) clean up child rows. REQ-CSC-001.
 *
 * Covers:
 *   - DashboardService::deleteDashboard dispatches the event
 *   - DashboardService::deleteGroupShared dispatches the event
 *   - AdminTemplateService::deleteTemplate dispatches the event
 *   - DemoShowcasesService::uninstallShowcase dispatches the event
 *
 * DashboardTreeService and BulkOperationService dispatch paths are
 * exercised via their own existing test files; the assertions here
 * verify the trigger-level wiring that was missing in wave-3 C4.
 *
 * @category  Test
 * @package   OCA\MyDash\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\MyDash\Db\AdminSettingMapper;
use OCA\MyDash\Db\Dashboard;
use OCA\MyDash\Db\DashboardMapper;
use OCA\MyDash\Db\WidgetPlacement;
use OCA\MyDash\Db\WidgetPlacementMapper;
use OCA\MyDash\Event\DashboardDeletedEvent;
use OCA\MyDash\Service\AdminSettingsService;
use OCA\MyDash\Service\AdminTemplateService;
use OCA\MyDash\Service\DashboardFactory;
use OCA\MyDash\Service\DashboardResolver;
use OCA\MyDash\Service\DashboardService;
use OCA\MyDash\Service\DashboardTreeService;
use OCA\MyDash\Service\DemoShowcasesService;
use OCA\MyDash\Service\TemplateService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Dashboard\IManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IAppConfig;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Verifies that DashboardDeletedEvent is dispatched at every delete site.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class DashboardCascadeEventDispatchTest extends TestCase
{

    /** @var DashboardMapper&MockObject */
    private $dashboardMapper;

    /** @var WidgetPlacementMapper&MockObject */
    private $placementMapper;

    /** @var IEventDispatcher&MockObject */
    private $eventDispatcher;

    /** @var IGroupManager&MockObject */
    private $groupManager;

    /** @var IDBConnection&MockObject */
    private $db;

    /**
     * Build a dashboard entity with the given UUID, userId, and type.
     *
     * @param string $uuid   Dashboard UUID.
     * @param string $userId Owner user ID.
     * @param string $type   Dashboard type constant.
     *
     * @return Dashboard
     */
    private function makeDashboard(
        string $uuid='test-uuid-1',
        string $userId='alice',
        string $type=Dashboard::TYPE_USER
    ): Dashboard {
        $d = new Dashboard();
        // phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
        $d->setId(42);
        $d->setUuid($uuid);
        $d->setUserId($userId);
        $d->setType($type);
        $d->setName('Test Dashboard');
        // phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
        return $d;
    }//end makeDashboard()

    /**
     * Build a DashboardService with mocked dependencies.
     *
     * @return DashboardService
     */
    private function buildDashboardService(): DashboardService
    {
        return new DashboardService(
            dashboardMapper:      $this->dashboardMapper,
            placementMapper:      $this->placementMapper,
            settingMapper:        $this->createMock(AdminSettingMapper::class),
            templateService:      $this->createMock(TemplateService::class),
            dashboardFactory:     new DashboardFactory(),
            dashResolver:         $this->createMock(DashboardResolver::class),
            treeService:          $this->createMock(DashboardTreeService::class),
            groupManager:         $this->groupManager,
            adminTemplateService: $this->createMock(\OCA\MyDash\Service\AdminTemplateService::class),
            db:                   $this->db,
            config:               $this->createMock(IConfig::class),
            l10nFactory:          $this->createMock(IFactory::class),
            logger:               $this->createMock(LoggerInterface::class),
            eventDispatcher:      $this->eventDispatcher,
        );
    }//end buildDashboardService()

    /**
     * Set up fresh mocks.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->dashboardMapper = $this->createMock(DashboardMapper::class);
        $this->placementMapper = $this->createMock(WidgetPlacementMapper::class);
        $this->eventDispatcher = $this->createMock(IEventDispatcher::class);
        $this->groupManager    = $this->createMock(IGroupManager::class);
        $this->db              = $this->createMock(IDBConnection::class);
    }//end setUp()

    // =========================================================================
    // DashboardService::deleteDashboard
    // =========================================================================

    /**
     * SB1 regression: deleteDashboard dispatches DashboardDeletedEvent.
     *
     * @return void
     */
    public function testDeleteDashboardDispatchesEvent(): void
    {
        $dashboard = $this->makeDashboard(uuid: 'uuid-personal-1', userId: 'alice');
        $this->dashboardMapper->method('find')->willReturn($dashboard);
        $this->dashboardMapper->method('countChildrenByParent')->willReturn(0);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatchTyped')
            ->with($this->callback(
                function ($event) {
                    return $event instanceof DashboardDeletedEvent
                        && $event->getDashboardUuid() === 'uuid-personal-1';
                }
            ));

        $service = $this->buildDashboardService();
        $service->deleteDashboard(dashboardId: 42, userId: 'alice');
    }//end testDeleteDashboardDispatchesEvent()

    /**
     * SB1 regression: deleteDashboard does NOT dispatch when UUID is empty.
     *
     * @return void
     */
    public function testDeleteDashboardSkipsEventWhenUuidEmpty(): void
    {
        $dashboard = $this->makeDashboard(uuid: '', userId: 'alice');
        $this->dashboardMapper->method('find')->willReturn($dashboard);

        $this->eventDispatcher->expects($this->never())->method('dispatchTyped');

        $service = $this->buildDashboardService();
        $service->deleteDashboard(dashboardId: 42, userId: 'alice');
    }//end testDeleteDashboardSkipsEventWhenUuidEmpty()

    // =========================================================================
    // DashboardService::deleteGroupShared
    // =========================================================================

    /**
     * SB1 regression: deleteGroupShared dispatches DashboardDeletedEvent.
     *
     * @return void
     */
    public function testDeleteGroupSharedDispatchesEvent(): void
    {
        $dashboard = $this->makeDashboard(
            uuid: 'uuid-group-1',
            userId: 'alice',
            type: Dashboard::TYPE_GROUP_SHARED
        );
        // phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
        $dashboard->setGroupId('marketing');

        $this->groupManager->method('isAdmin')->with('admin')->willReturn(true);

        $this->dashboardMapper->method('findByUuid')->willReturn($dashboard);
        $this->dashboardMapper->method('countByGroup')->willReturn(5);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatchTyped')
            ->with($this->callback(
                function ($event) {
                    return $event instanceof DashboardDeletedEvent
                        && $event->getDashboardUuid() === 'uuid-group-1'
                        && $event->getOwnerUserId() === 'admin';
                }
            ));

        $service = $this->buildDashboardService();
        $service->deleteGroupShared(
            actorUserId: 'admin',
            groupId: 'marketing',
            uuid: 'uuid-group-1'
        );
    }//end testDeleteGroupSharedDispatchesEvent()

    // =========================================================================
    // AdminTemplateService::deleteTemplate
    // =========================================================================

    /**
     * SB1 regression: deleteTemplate dispatches DashboardDeletedEvent.
     *
     * @return void
     */
    public function testDeleteTemplateDispatchesEvent(): void
    {
        $template = $this->makeDashboard(
            uuid: 'uuid-template-1',
            userId: '',
            type: Dashboard::TYPE_ADMIN_TEMPLATE
        );

        $this->dashboardMapper->method('find')->willReturn($template);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatchTyped')
            ->with($this->callback(
                function ($event) {
                    return $event instanceof DashboardDeletedEvent
                        && $event->getDashboardUuid() === 'uuid-template-1'
                        && $event->getType() === Dashboard::TYPE_ADMIN_TEMPLATE;
                }
            ));

        $service = new AdminTemplateService(
            dashboardMapper:  $this->dashboardMapper,
            placementMapper:  $this->placementMapper,
            settingsService:  $this->createMock(AdminSettingsService::class),
            groupManager:     $this->groupManager,
            userManager:      $this->createMock(IUserManager::class),
            eventDispatcher:  $this->eventDispatcher,
        );

        $service->deleteTemplate(id: 42);
    }//end testDeleteTemplateDispatchesEvent()

    // =========================================================================
    // DemoShowcasesService::uninstallShowcase
    // =========================================================================

    /**
     * SB1 regression: uninstallShowcase dispatches DashboardDeletedEvent.
     *
     * @return void
     */
    public function testUninstallShowcaseDispatchesEvent(): void
    {
        $dashboard = $this->makeDashboard(
            uuid: 'uuid-showcase-1',
            userId: '',
            type: Dashboard::TYPE_GROUP_SHARED
        );

        $this->dashboardMapper->method('findByUuid')->willReturn($dashboard);

        $appConfig = $this->createMock(IAppConfig::class);
        // Simulate the showcase having a recorded UUID.
        $appConfig->method('getValueString')
            ->willReturn('uuid-showcase-1');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatchTyped')
            ->with($this->callback(
                function ($event) {
                    return $event instanceof DashboardDeletedEvent
                        && $event->getDashboardUuid() === 'uuid-showcase-1';
                }
            ));

        $service = new DemoShowcasesService(
            dashboardMapper:  $this->dashboardMapper,
            placementMapper:  $this->placementMapper,
            db:               $this->db,
            appConfig:        $appConfig,
            dashboardManager: $this->createMock(IManager::class),
            logger:           $this->createMock(LoggerInterface::class),
            lockingProvider:  $this->createMock(ILockingProvider::class),
            urlGenerator:     $this->createMock(IURLGenerator::class),
            eventDispatcher:  $this->eventDispatcher,
        );

        $service->uninstallShowcase(showcaseId: 'de-bron');
    }//end testUninstallShowcaseDispatchesEvent()

    // =========================================================================
    // DashboardTreeService::deleteSubtree
    // =========================================================================

    /**
     * SB1 regression: deleteSubtree dispatches DashboardDeletedEvent for
     * each node (root + descendants).
     *
     * @return void
     */
    public function testDeleteSubtreeDispatchesEventForEachNode(): void
    {
        $child1 = $this->makeDashboard(uuid: 'child-uuid-1', userId: 'alice');
        $child2 = $this->makeDashboard(uuid: 'child-uuid-2', userId: 'alice');
        $root   = $this->makeDashboard(uuid: 'root-uuid-1', userId: 'alice');

        // findDescendants returns two children.
        $this->dashboardMapper->method('findDescendants')
            ->with(ancestorUuid: 'root-uuid-1')
            ->willReturn([$child1, $child2]);
        $this->dashboardMapper->method('delete');

        $this->db->method('beginTransaction');
        $this->db->method('commit');

        // Expect 3 dispatchTyped calls (child1, child2, root).
        $this->eventDispatcher->expects($this->exactly(3))
            ->method('dispatchTyped')
            ->with($this->isInstanceOf(DashboardDeletedEvent::class));

        $service = new DashboardTreeService(
            dashboardMapper:  $this->dashboardMapper,
            placementMapper:  $this->placementMapper,
            db:               $this->db,
            eventDispatcher:  $this->eventDispatcher,
        );

        $service->deleteSubtree(dashboard: $root);
    }//end testDeleteSubtreeDispatchesEventForEachNode()
}//end class
