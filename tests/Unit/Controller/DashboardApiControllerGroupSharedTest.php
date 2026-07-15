<?php

/**
 * DashboardApiControllerGroupSharedTest
 *
 * Unit tests for the group-shared controller endpoints added by
 * multi-scope-dashboards: `visible`, `listGroup`, `createGroup`,
 * `getGroup`, `updateGroup`, `deleteGroup`. Covers REQ-DASH-011,
 * REQ-DASH-013, REQ-DASH-014.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/multi-scope-dashboards/tasks.md#task-10
 */

declare(strict_types=1);

namespace Unit\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\Controller\DashboardApiController;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AnalyticsService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for the group-scoped controller actions (REQ-DASH-011, REQ-DASH-013, REQ-DASH-014).
 *
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DashboardApiControllerGroupSharedTest extends TestCase
{

    /**
     * @var IRequest&MockObject
     */
    private $request;

    /**
     * @var DashboardService&MockObject
     */
    private $dashboardService;

    /**
     * @var PermissionService&MockObject
     */
    private $permissionService;

    /**
     * @var DashboardTreeService&MockObject
     */
    private $treeService;

    /**
     * @var DashboardVersionService&MockObject
     */
    private $versionService;

    /**
     * @var AnalyticsService&MockObject
     */
    private $analyticsService;

    /**
     * @var LoggerInterface&MockObject
     */
    private $logger;

    /**
     * @var IUserSession&MockObject
     */
    private $userSession;

    /**
     * @var ActionAuthService&MockObject
     */
    private $actionAuth;

    protected function setUp(): void
    {
        $this->request           = $this->createMock(IRequest::class);
        $this->dashboardService  = $this->createMock(DashboardService::class);
        $this->permissionService = $this->createMock(PermissionService::class);
        $this->treeService       = $this->createMock(DashboardTreeService::class);
        $this->versionService    = $this->createMock(DashboardVersionService::class);
        $this->analyticsService  = $this->createMock(AnalyticsService::class);
        $this->logger            = $this->createMock(LoggerInterface::class);
        $this->userSession       = $this->createMock(IUserSession::class);
        $this->actionAuth        = $this->createMock(ActionAuthService::class);
    }//end setUp()

    /**
     * Build the controller for the given user ID.
     *
     * @param string|null $userId The user ID (null = anonymous).
     *
     * @return DashboardApiController The wired controller.
     */
    private function makeController(?string $userId): DashboardApiController
    {
        $user = null;
        if ($userId !== null) {
            $user = $this->createMock(IUser::class);
            $user->method('getUID')->willReturn($userId);
        }

        $this->userSession->method('getUser')->willReturn($user);

        return new DashboardApiController(
            request: $this->request,
            dashboardService: $this->dashboardService,
            permissionService: $this->permissionService,
            treeService: $this->treeService,
            versionService: $this->versionService,
            analyticsService: $this->analyticsService,
            logger: $this->logger,
            userSession: $this->userSession,
            actionAuth: $this->actionAuth,
            userId: $userId,
        );
    }//end makeController()

    /**
     * Build a minimal Dashboard entity for use in stubs.
     *
     * @param string      $uuid    Dashboard UUID.
     * @param string|null $groupId Group ID (null for personal).
     * @param string      $type    Dashboard type constant.
     *
     * @return Dashboard The entity.
     */
    private function makeDashboard(
        string $uuid,
        ?string $groupId=null,
        string $type=Dashboard::TYPE_USER
    ): Dashboard {
        $d = new Dashboard();
        $d->setUuid($uuid);
        $d->setName('Test Dashboard');
        $d->setType($type);
        $d->setGroupId($groupId);
        $d->setPermissionLevel(Dashboard::PERMISSION_VIEW_ONLY);
        return $d;
    }//end makeDashboard()

    // -------------------------------------------------------------------------
    // REQ-DASH-013 — GET /api/dashboards/visible
    // -------------------------------------------------------------------------

    /**
     * `visible()` returns 200 with source-tagged union when called by an
     * authenticated user (REQ-DASH-013).
     *
     * @return void
     */
    public function testVisibleReturnsSourceTaggedList(): void
    {
        $personal = $this->makeDashboard(uuid: 'personal-1');
        $personal->setUserId('alice');
        $group1   = $this->makeDashboard(
            uuid: 'group-1',
            groupId: 'marketing',
            type: Dashboard::TYPE_GROUP_SHARED
        );
        $default1 = $this->makeDashboard(
            uuid: 'default-1',
            groupId: Dashboard::DEFAULT_GROUP_ID,
            type: Dashboard::TYPE_GROUP_SHARED
        );

        $this->dashboardService
            ->method('getVisibleToUser')
            ->with('alice')
            ->willReturn(
                    [
                        ['dashboard' => $personal, 'source' => Dashboard::SOURCE_USER],
                        ['dashboard' => $group1,   'source' => Dashboard::SOURCE_GROUP],
                        ['dashboard' => $default1, 'source' => Dashboard::SOURCE_DEFAULT],
                    ]
                    );

        $controller = $this->makeController('alice');
        $response   = $controller->visible();

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $data = $response->getData();
        $this->assertCount(3, $data);
        $this->assertSame(Dashboard::SOURCE_USER,    $data[0]['source']);
        $this->assertSame(Dashboard::SOURCE_GROUP,   $data[1]['source']);
        $this->assertSame(Dashboard::SOURCE_DEFAULT, $data[2]['source']);

        // Ownership tag drives frontend activation routing: only the
        // caller-owned personal row is the owner; group/default rows
        // (user_id NULL) are not.
        $this->assertTrue($data[0]['isOwner']);
        $this->assertFalse($data[1]['isOwner']);
        $this->assertFalse($data[2]['isOwner']);
    }//end testVisibleReturnsSourceTaggedList()

    /**
     * `visible()` returns 401 when the session has no user.
     *
     * @return void
     */
    public function testVisibleReturns401ForAnonymous(): void
    {
        $controller = $this->makeController(null);
        $response   = $controller->visible();
        $this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
    }//end testVisibleReturns401ForAnonymous()

    // -------------------------------------------------------------------------
    // REQ-DASH-014 — POST /api/dashboards/group/{groupId} (createGroup)
    // -------------------------------------------------------------------------

    /**
     * `createGroup()` is admin-only via the `#[AuthorizedAdminSetting]`
     * attribute — the framework middleware (not reachable when calling the
     * controller method directly in a unit test) rejects non-admins before
     * the method body runs. See `testGroupEndpointsCarryAuthorizedAdminSettingAttribute()`
     * for the machine-checkable assertion, and
     * `openspec/changes/fix-group-dashboard-admin-auth-attribute/tasks.md#task-15`
     * for the manual curl/Newman verification of the actual 403 behaviour.
     *
     * @return void
     */
    public function testCreateGroupReturns201ForAdmin(): void
    {
        $dashboard = $this->makeDashboard(
            uuid: 'new-uuid',
            groupId: 'marketing',
            type: Dashboard::TYPE_GROUP_SHARED
        );

        $this->dashboardService
            ->method('createGroupShared')
            ->willReturn($dashboard);

        $controller = $this->makeController('admin');
        $response   = $controller->createGroup(
            groupId: 'marketing',
            name: 'Marketing Overview'
        );

        $this->assertSame(Http::STATUS_CREATED, $response->getStatus());
        $data = $response->getData();
        $this->assertSame('new-uuid', $data['dashboard']['uuid']);
    }//end testCreateGroupReturns201ForAdmin()

    /**
     * `createGroup()` returns 401 when the session has no user.
     *
     * @return void
     */
    public function testCreateGroupReturns401ForAnonymous(): void
    {
        $controller = $this->makeController(null);
        $response   = $controller->createGroup(groupId: 'marketing');
        $this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
    }//end testCreateGroupReturns401ForAnonymous()

    // -------------------------------------------------------------------------
    // REQ-DASH-014 — GET /api/dashboards/group/{groupId} (listGroup)
    // -------------------------------------------------------------------------

    /**
     * `listGroup()` returns 200 with dashboards when the user can access the group
     * (REQ-DASH-014).
     *
     * @return void
     */
    public function testListGroupReturnsRows(): void
    {
        $d1 = $this->makeDashboard(
            uuid: 'dash-1',
            groupId: 'marketing',
            type: Dashboard::TYPE_GROUP_SHARED
        );
        $d2 = $this->makeDashboard(
            uuid: 'dash-2',
            groupId: 'marketing',
            type: Dashboard::TYPE_GROUP_SHARED
        );

        $this->dashboardService
            ->method('userCanAccessGroup')
            ->with('bob', 'marketing')
            ->willReturn(true);

        $this->dashboardService
            ->method('listGroupDashboards')
            ->with('marketing')
            ->willReturn([$d1, $d2]);

        $controller = $this->makeController('bob');
        $response   = $controller->listGroup(groupId: 'marketing');

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $data = $response->getData();
        $this->assertCount(2, $data);
    }//end testListGroupReturnsRows()

    /**
     * `listGroup()` returns 403 when the user has no access to the group.
     *
     * @return void
     */
    public function testListGroupForbiddenWhenNoAccess(): void
    {
        $this->dashboardService
            ->method('userCanAccessGroup')
            ->with('bob', 'marketing')
            ->willReturn(false);

        $this->dashboardService->expects($this->never())->method('listGroupDashboards');

        $controller = $this->makeController('bob');
        $response   = $controller->listGroup(groupId: 'marketing');

        $this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
    }//end testListGroupForbiddenWhenNoAccess()

    // -------------------------------------------------------------------------
    // REQ-DASH-014 — GET /api/dashboards/group/{groupId}/{uuid} (getGroup)
    // -------------------------------------------------------------------------

    /**
     * `getGroup()` returns 200 with dashboard data when user has access.
     *
     * @return void
     */
    public function testGetGroupReturns200(): void
    {
        $dashboard = $this->makeDashboard(
            uuid: 'dash-1',
            groupId: 'marketing',
            type: Dashboard::TYPE_GROUP_SHARED
        );

        $this->dashboardService
            ->method('userCanAccessGroup')
            ->willReturn(true);

        $this->dashboardService
            ->method('findGroupDashboard')
            ->with('marketing', 'dash-1')
            ->willReturn($dashboard);

        $controller = $this->makeController('bob');
        $response   = $controller->getGroup(groupId: 'marketing', uuid: 'dash-1');

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $data = $response->getData();
        $this->assertSame('dash-1', $data['dashboard']['uuid']);
    }//end testGetGroupReturns200()

    /**
     * `getGroup()` returns 404 when the dashboard does not belong to the group.
     *
     * @return void
     */
    public function testGetGroupReturns404OnMismatch(): void
    {
        $this->dashboardService
            ->method('userCanAccessGroup')
            ->willReturn(true);

        $this->dashboardService
            ->method('findGroupDashboard')
            ->willThrowException(new DoesNotExistException('not found'));

        $controller = $this->makeController('bob');
        $response   = $controller->getGroup(
            groupId: 'marketing',
            uuid: 'wrong-uuid'
        );

        $this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
    }//end testGetGroupReturns404OnMismatch()

    // -------------------------------------------------------------------------
    // REQ-DASH-014 — PUT /api/dashboards/group/{groupId}/{uuid} (updateGroup)
    // -------------------------------------------------------------------------

    /**
     * `updateGroup()` is admin-only via the `#[AuthorizedAdminSetting]`
     * attribute — see `testCreateGroupReturns201ForAdmin()` docblock for
     * why the non-admin-403 scenario is no longer a controller-body unit
     * test.
     *
     * @return void
     */
    public function testUpdateGroupReturns200ForAdmin(): void
    {
        $updated = $this->makeDashboard(
            uuid: 'dash-1',
            groupId: 'marketing',
            type: Dashboard::TYPE_GROUP_SHARED
        );
        $updated->setName('New Name');

        $this->dashboardService
            ->method('updateGroupShared')
            ->willReturn($updated);

        $controller = $this->makeController('admin');
        $response   = $controller->updateGroup(
            groupId: 'marketing',
            uuid: 'dash-1',
            name: 'New Name'
        );

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
    }//end testUpdateGroupReturns200ForAdmin()

    // -------------------------------------------------------------------------
    // REQ-DASH-014 — DELETE /api/dashboards/group/{groupId}/{uuid} (deleteGroup)
    // -------------------------------------------------------------------------

    /**
     * `deleteGroup()` is admin-only via the `#[AuthorizedAdminSetting]`
     * attribute — see `testCreateGroupReturns201ForAdmin()` docblock for
     * why the non-admin-403 scenario is no longer a controller-body unit
     * test.
     *
     * @return void
     */
    public function testDeleteGroupReturns200ForAdmin(): void
    {
        $this->dashboardService
            ->expects($this->once())
            ->method('deleteGroupShared')
            ->with('admin', 'marketing', 'dash-1');

        $controller = $this->makeController('admin');
        $response   = $controller->deleteGroup(
            groupId: 'marketing',
            uuid: 'dash-1'
        );

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
    }//end testDeleteGroupReturns200ForAdmin()

    /**
     * `deleteGroup()` propagates the last-in-group guard (HTTP 400) from the
     * service layer (REQ-DASH-014).
     *
     * @return void
     */
    public function testDeleteGroupPropagatesLastInGroupError(): void
    {
        $this->dashboardService
            ->method('deleteGroupShared')
            ->willThrowException(
                new \InvalidArgumentException(DashboardService::ERR_LAST_IN_GROUP)
            );

        $controller = $this->makeController('admin');
        $response   = $controller->deleteGroup(
            groupId: 'marketing',
            uuid: 'last-dash'
        );

        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
    }//end testDeleteGroupPropagatesLastInGroupError()

    /**
     * `deleteGroup()` returns 404 when the dashboard does not exist.
     *
     * @return void
     */
    public function testDeleteGroupReturns404ForMissing(): void
    {
        $this->dashboardService
            ->method('deleteGroupShared')
            ->willThrowException(new DoesNotExistException('not found'));

        $controller = $this->makeController('admin');
        $response   = $controller->deleteGroup(
            groupId: 'marketing',
            uuid: 'missing'
        );

        $this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
    }//end testDeleteGroupReturns404ForMissing()

    // -------------------------------------------------------------------------
    // fix-group-dashboard-admin-auth-attribute — machine-checkable auth contract
    // -------------------------------------------------------------------------

    /**
     * `createGroup()`, `updateGroup()`, `deleteGroup()`, and
     * `setGroupDefault()` MUST carry `#[AuthorizedAdminSetting]` rather
     * than relying on an in-body `isAdmin()` check layered on a permissive
     * `#[NoAdminRequired]` attribute (ADR-005 gate-semantic-auth).
     *
     * @return void
     */
    public function testGroupEndpointsCarryAuthorizedAdminSettingAttribute(): void
    {
        $reflection = new \ReflectionClass(DashboardApiController::class);

        foreach (['createGroup', 'updateGroup', 'deleteGroup', 'setGroupDefault'] as $methodName) {
            $method     = $reflection->getMethod($methodName);
            $attributes = $method->getAttributes(
                \OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting::class
            );

            $this->assertNotEmpty(
                $attributes,
                sprintf(
                    '%s() must carry #[AuthorizedAdminSetting] instead of an in-body isAdmin() check',
                    $methodName
                )
            );

            $noAdminRequired = $method->getAttributes(
                \OCP\AppFramework\Http\Attribute\NoAdminRequired::class
            );
            $this->assertEmpty(
                $noAdminRequired,
                sprintf(
                    '%s() must not also carry #[NoAdminRequired] alongside #[AuthorizedAdminSetting]',
                    $methodName
                )
            );
        }
    }//end testGroupEndpointsCarryAuthorizedAdminSettingAttribute()
}//end class
