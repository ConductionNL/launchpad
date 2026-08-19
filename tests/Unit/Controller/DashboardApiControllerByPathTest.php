<?php

/**
 * DashboardApiController by-path Contract Test
 *
 * Wire-contract coverage for `dashboardApi#byPath`
 * (GET /api/dashboards/by-path/{path}).
 *
 * The endpoint is `#[NoAdminRequired]` and its `{path}` placeholder is
 * regex-allowed to contain slashes, so any authenticated account can ask it
 * to resolve an arbitrary slug chain. The contract that matters is the C2
 * fix (REQ-DASH-027 / REQ-PERM-001): a slug the caller may not view MUST
 * come back as the SAME 404 an unknown slug produces, because a 403 would
 * confirm the slug exists. That indistinguishability is asserted directly —
 * both refusals are compared field by field.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\DashboardApiController;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AnalyticsService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Contract tests for slug-chain dashboard resolution.
 */
class DashboardApiControllerByPathTest extends TestCase
{

    /**
     * HTTP request mock.
     *
     * @var IRequest&MockObject
     */
    private $request;

    /**
     * Dashboard service mock.
     *
     * @var DashboardService&MockObject
     */
    private $dashboardService;

    /**
     * Permission service mock.
     *
     * @var PermissionService&MockObject
     */
    private $permissionService;

    /**
     * Tree service mock.
     *
     * @var DashboardTreeService&MockObject
     */
    private $treeService;

    /**
     * User session mock.
     *
     * @var IUserSession&MockObject
     */
    private $userSession;

    /**
     * Action authorization mock.
     *
     * @var ActionAuthService&MockObject
     */
    private $actionAuth;


    /**
     * Set up shared mocks.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->request           = $this->createMock(IRequest::class);
        $this->dashboardService  = $this->createMock(DashboardService::class);
        $this->permissionService = $this->createMock(PermissionService::class);
        $this->treeService       = $this->createMock(DashboardTreeService::class);
        $this->userSession       = $this->createMock(IUserSession::class);
        $this->actionAuth        = $this->createMock(ActionAuthService::class);

    }//end setUp()


    /**
     * Build the controller for the supplied user (NULL = anonymous).
     *
     * @param string|null $userId The acting user ID.
     *
     * @return DashboardApiController
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
            versionService: $this->createMock(DashboardVersionService::class),
            analyticsService: $this->createMock(AnalyticsService::class),
            logger: $this->createMock(LoggerInterface::class),
            userSession: $this->userSession,
            actionAuth: $this->actionAuth,
            userId: $userId,
        );

    }//end makeController()


    /**
     * Build a persisted-looking dashboard fixture.
     *
     * @param integer $id   The row id.
     * @param string  $uuid The dashboard UUID.
     *
     * @return Dashboard
     */
    private function makeDashboard(int $id, string $uuid): Dashboard
    {
        $dashboard = new Dashboard();
        $dashboard->setId($id);
        $dashboard->setUuid($uuid);
        $dashboard->setName('Campaigns');

        return $dashboard;

    }//end makeDashboard()


    /**
     * An anonymous caller MUST get 401 and MUST NOT reach the resolver.
     *
     * @return void
     */
    public function testByPathRejectsAnonymousWith401(): void
    {
        $this->treeService->expects($this->never())->method('resolvePath');

        $controller = $this->makeController(null);
        $response   = $controller->byPath(path: 'marketing/campaigns');

        $this->assertSame(
            expected: Http::STATUS_UNAUTHORIZED,
            actual: $response->getStatus()
        );

    }//end testByPathRejectsAnonymousWith401()


    /**
     * An unresolvable slug chain returns the 404 envelope.
     *
     * @return void
     */
    public function testByPathReturns404ForUnknownPath(): void
    {
        $this->treeService->expects($this->once())
            ->method('resolvePath')
            ->with(path: 'marketing/no-such-thing')
            ->willReturn(null);

        $controller = $this->makeController('alice');
        $response   = $controller->byPath(path: 'marketing/no-such-thing');

        $this->assertSame(
            expected: Http::STATUS_NOT_FOUND,
            actual: $response->getStatus()
        );
        $this->assertSame(expected: 'not_found', actual: $response->getData()['error']);

    }//end testByPathReturns404ForUnknownPath()


    /**
     * C2 / REQ-PERM-001: a slug that RESOLVES but which the caller may not
     * view MUST be indistinguishable from a slug that does not exist —
     * same status AND same body. A 403, or a differently-worded 404, would
     * confirm the slug to an unauthorised caller.
     *
     * @return void
     */
    public function testByPathHidesExistenceFromUnauthorisedCaller(): void
    {
        $this->treeService->method('resolvePath')
            ->willReturnCallback(
                function (string $path) {
                    if ($path === 'marketing/secret') {
                        return $this->makeDashboard(id: 42, uuid: 'uuid-secret');
                    }

                    return null;
                }
            );

        $this->permissionService->expects($this->once())
            ->method('canViewDashboard')
            ->with(userId: 'mallory', dashboardId: 42)
            ->willReturn(false);

        // Nothing about the resolved dashboard may leak into the response.
        $this->treeService->expects($this->never())->method('computePath');
        $this->treeService->expects($this->never())->method('computeBreadcrumbs');

        $controller = $this->makeController('mallory');
        $denied     = $controller->byPath(path: 'marketing/secret');
        $missing    = $controller->byPath(path: 'marketing/does-not-exist');

        $this->assertSame(
            expected: Http::STATUS_NOT_FOUND,
            actual: $denied->getStatus()
        );
        $this->assertSame(
            expected: $missing->getStatus(),
            actual: $denied->getStatus()
        );
        $this->assertSame(
            expected: $missing->getData(),
            actual: $denied->getData()
        );

    }//end testByPathHidesExistenceFromUnauthorisedCaller()


    /**
     * REQ-DASH-025: a visible dashboard comes back with its computed
     * canonical path and breadcrumb chain attached to the payload.
     *
     * @return void
     */
    public function testByPathReturnsDashboardWithPathAndBreadcrumbs(): void
    {
        $this->treeService->method('resolvePath')
            ->with(path: 'marketing/campaigns')
            ->willReturn($this->makeDashboard(id: 7, uuid: 'uuid-campaigns'));

        $this->permissionService->method('canViewDashboard')
            ->with(userId: 'alice', dashboardId: 7)
            ->willReturn(true);

        $this->treeService->expects($this->once())
            ->method('computePath')
            ->with(uuid: 'uuid-campaigns')
            ->willReturn('/marketing/campaigns');

        $breadcrumbs = [
            ['uuid' => 'uuid-marketing', 'slug' => 'marketing'],
            ['uuid' => 'uuid-campaigns', 'slug' => 'campaigns'],
        ];
        $this->treeService->expects($this->once())
            ->method('computeBreadcrumbs')
            ->with(uuid: 'uuid-campaigns')
            ->willReturn($breadcrumbs);

        $controller = $this->makeController('alice');
        $response   = $controller->byPath(path: 'marketing/campaigns');

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());

        $dashboard = $response->getData()['dashboard'];
        $this->assertSame(expected: 'uuid-campaigns', actual: $dashboard['uuid']);
        $this->assertSame(expected: '/marketing/campaigns', actual: $dashboard['path']);
        $this->assertSame(expected: $breadcrumbs, actual: $dashboard['breadcrumbs']);

    }//end testByPathReturnsDashboardWithPathAndBreadcrumbs()


    /**
     * With no path bound by the router the controller falls back to the
     * `path` request parameter rather than resolving the empty string.
     *
     * @return void
     */
    public function testByPathFallsBackToTheRequestParameter(): void
    {
        $this->request->method('getParam')
            ->willReturn('marketing/campaigns');

        $this->treeService->expects($this->once())
            ->method('resolvePath')
            ->with(path: 'marketing/campaigns')
            ->willReturn(null);

        $controller = $this->makeController('alice');
        $response   = $controller->byPath(path: '');

        $this->assertSame(
            expected: Http::STATUS_NOT_FOUND,
            actual: $response->getStatus()
        );

    }//end testByPathFallsBackToTheRequestParameter()


}//end class
