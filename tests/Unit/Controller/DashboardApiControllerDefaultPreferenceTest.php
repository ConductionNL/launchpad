<?php

/**
 * DashboardApiController default-dashboard pin Contract Test
 *
 * Wire-contract coverage for the two routed public endpoints that read and
 * write the user's EXPLICIT default-dashboard pin (wave3.7):
 *
 *   GET  /api/dashboards/default  →  dashboardApi#getDefaultDashboard
 *   POST /api/dashboards/default  →  dashboardApi#setDefaultDashboard
 *
 * The pin is per-user state written only on an explicit "Set as default",
 * and the resolver consults it before the active preference — so the two
 * behaviours the wire has to guarantee are that the write reaches the
 * ACTING user's preference (never a caller-supplied user), and that an
 * empty body CLEARS the pin rather than being rejected.
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
 * Contract tests for the default-dashboard pin endpoints.
 */
class DashboardApiControllerDefaultPreferenceTest extends TestCase
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

        $this->request          = $this->createMock(IRequest::class);
        $this->dashboardService = $this->createMock(DashboardService::class);
        $this->userSession      = $this->createMock(IUserSession::class);
        $this->actionAuth       = $this->createMock(ActionAuthService::class);

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
            permissionService: $this->createMock(PermissionService::class),
            treeService: $this->createMock(DashboardTreeService::class),
            versionService: $this->createMock(DashboardVersionService::class),
            analyticsService: $this->createMock(AnalyticsService::class),
            logger: $this->createMock(LoggerInterface::class),
            userSession: $this->userSession,
            actionAuth: $this->actionAuth,
            userId: $userId,
        );

    }//end makeController()


    /**
     * An anonymous caller MUST get 401 and MUST NOT write a pin.
     *
     * @return void
     */
    public function testSetDefaultDashboardRejectsAnonymousWith401(): void
    {
        $this->dashboardService->expects($this->never())
            ->method('setDefaultPreference');

        $controller = $this->makeController(null);
        $response   = $controller->setDefaultDashboard(uuid: 'uuid-a');

        $this->assertSame(
            expected: Http::STATUS_UNAUTHORIZED,
            actual: $response->getStatus()
        );

    }//end testSetDefaultDashboardRejectsAnonymousWith401()


    /**
     * The pin is written against the SESSION user, not against anything
     * the caller supplied.
     *
     * @return void
     */
    public function testSetDefaultDashboardPinsForTheActingUser(): void
    {
        $this->dashboardService->expects($this->once())
            ->method('setDefaultPreference')
            ->with(userId: 'alice', uuid: 'uuid-a');

        $controller = $this->makeController('alice');
        $response   = $controller->setDefaultDashboard(uuid: 'uuid-a');

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
        $this->assertSame(
            expected: ['status' => 'success'],
            actual: $response->getData()
        );

    }//end testSetDefaultDashboardPinsForTheActingUser()


    /**
     * A missing/NULL uuid CLEARS the pin — it is normalised to the empty
     * string and passed on, not rejected and not silently skipped.
     *
     * @return void
     */
    public function testSetDefaultDashboardWithNullUuidClearsThePin(): void
    {
        $this->dashboardService->expects($this->once())
            ->method('setDefaultPreference')
            ->with(userId: 'alice', uuid: '');

        $controller = $this->makeController('alice');
        $response   = $controller->setDefaultDashboard(uuid: null);

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());

    }//end testSetDefaultDashboardWithNullUuidClearsThePin()


    /**
     * An anonymous caller MUST get 401 on the read side too, and MUST NOT
     * reach anybody's preference.
     *
     * @return void
     */
    public function testGetDefaultDashboardRejectsAnonymousWith401(): void
    {
        $this->dashboardService->expects($this->never())
            ->method('getDefaultPreference');

        $controller = $this->makeController(null);
        $response   = $controller->getDefaultDashboard();

        $this->assertSame(
            expected: Http::STATUS_UNAUTHORIZED,
            actual: $response->getStatus()
        );

    }//end testGetDefaultDashboardRejectsAnonymousWith401()


    /**
     * The read returns the acting user's pin under the `uuid` key.
     *
     * @return void
     */
    public function testGetDefaultDashboardReturnsTheActingUsersPin(): void
    {
        $this->dashboardService->expects($this->once())
            ->method('getDefaultPreference')
            ->with(userId: 'alice')
            ->willReturn('uuid-a');

        $controller = $this->makeController('alice');
        $response   = $controller->getDefaultDashboard();

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
        $this->assertSame(
            expected: ['uuid' => 'uuid-a'],
            actual: $response->getData()
        );

    }//end testGetDefaultDashboardReturnsTheActingUsersPin()


    /**
     * "No pin set" is the empty string, not a 404 — the frontend reads it
     * as "fall through to the resolver".
     *
     * @return void
     */
    public function testGetDefaultDashboardReturnsEmptyStringWhenUnpinned(): void
    {
        $this->dashboardService->method('getDefaultPreference')->willReturn('');

        $controller = $this->makeController('alice');
        $response   = $controller->getDefaultDashboard();

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
        $this->assertSame(expected: ['uuid' => ''], actual: $response->getData());

    }//end testGetDefaultDashboardReturnsEmptyStringWhenUnpinned()


}//end class
