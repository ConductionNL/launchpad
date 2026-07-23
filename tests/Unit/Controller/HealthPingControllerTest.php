<?php

/**
 * HealthPingControllerTest
 *
 * Covers REQ-HPING-003 controller behaviour:
 *  - 401 when the caller is anonymous.
 *  - 403 on an unauthorized placement, and the resolver is NEVER called
 *    in that case (the ping must not happen).
 *  - 200 with the resolved badge, and the response shape excludes any
 *    health URL / header / body fields (only the 4 documented keys are
 *    present).
 *  - `validate()` auxiliary endpoint.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\HealthPingController;
use OCA\LaunchPad\Service\HealthPingService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[Small]
class HealthPingControllerTest extends TestCase
{

    /**
     * @var IRequest&MockObject
     */
    private $request;

    /**
     * @var HealthPingService&MockObject
     */
    private $healthPingService;

    /**
     * @var PermissionService&MockObject
     */
    private $permissionService;

    /**
     * @var IUserSession&MockObject
     */
    private $userSession;

    /**
     * @var LoggerInterface&MockObject
     */
    private $logger;

    private HealthPingController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->request           = $this->createMock(originalClassName: IRequest::class);
        $this->healthPingService  = $this->createMock(originalClassName: HealthPingService::class);
        $this->permissionService  = $this->createMock(originalClassName: PermissionService::class);
        $this->userSession        = $this->createMock(originalClassName: IUserSession::class);
        $this->logger             = $this->createMock(originalClassName: LoggerInterface::class);

        $this->controller = new HealthPingController(
            request: $this->request,
            healthPingService: $this->healthPingService,
            permissionService: $this->permissionService,
            userSession: $this->userSession,
            logger: $this->logger,
        );
    }//end setUp()

    private function authAsUser(string $uid): void
    {
        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn($uid);
        $this->userSession->method('getUser')->willReturn($user);
    }//end authAsUser()

    // -------------------------------------------------------------
    // show() — REQ-HPING-003.
    // -------------------------------------------------------------

    public function testShowReturns401WhenAnonymous(): void
    {
        $this->userSession->method('getUser')->willReturn(null);
        $this->healthPingService->expects($this->never())->method('resolveForPlacement');

        $response = $this->controller->show(placementId: 1);

        $this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
    }//end testShowReturns401WhenAnonymous()

    public function testShowReturns403OnUnauthorizedPlacementAndNeverPings(): void
    {
        $this->authAsUser(uid: 'alice');
        $this->permissionService->method('canViewPlacement')
            ->with('alice', 42)
            ->willReturn(false);
        // REQ-HPING-003 "Caller authorization" — the ping MUST NOT be
        // performed when the caller may not view the placement.
        $this->healthPingService->expects($this->never())->method('resolveForPlacement');

        $response = $this->controller->show(placementId: 42);

        $this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
    }//end testShowReturns403OnUnauthorizedPlacementAndNeverPings()

    public function testShowReturns200WithBadgeWhenAuthorized(): void
    {
        $this->authAsUser(uid: 'alice');
        $this->permissionService->method('canViewPlacement')->willReturn(true);
        $this->healthPingService->method('resolveForPlacement')->willReturn([
            'state'     => 'online',
            'checkedAt' => '2026-07-23T10:00:00+00:00',
            'latencyMs' => 42,
            'stale'     => false,
        ]);

        $response = $this->controller->show(placementId: 5);

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $this->assertSame('online', $response->getData()['state']);
    }//end testShowReturns200WithBadgeWhenAuthorized()

    public function testShowResponseNeverContainsHealthUrlOrCredentials(): void
    {
        $this->authAsUser(uid: 'alice');
        $this->permissionService->method('canViewPlacement')->willReturn(true);
        $this->healthPingService->method('resolveForPlacement')->willReturn([
            'state'     => 'offline',
            'checkedAt' => '2026-07-23T10:00:00+00:00',
            'latencyMs' => 9001,
            'stale'     => false,
        ]);

        $response = $this->controller->show(placementId: 6);
        $data     = $response->getData();

        $this->assertSame(
            ['state', 'checkedAt', 'latencyMs', 'stale'],
            array_keys($data),
            'response MUST be exactly {state, checkedAt, latencyMs, stale} — no url/headers/credentials'
        );
        foreach ($data as $key => $value) {
            if (is_string($value) === false) {
                continue;
            }

            $this->assertStringNotContainsStringIgnoringCase('http://', $value);
            $this->assertStringNotContainsStringIgnoringCase('https://', $value);
            $this->assertStringNotContainsStringIgnoringCase('authorization', $value);
            $this->assertStringNotContainsStringIgnoringCase('api_key', $value);
            $this->assertStringNotContainsStringIgnoringCase('apikey', $value);
        }
    }//end testShowResponseNeverContainsHealthUrlOrCredentials()

    public function testShowReturns404WhenPlacementUnresolvable(): void
    {
        $this->authAsUser(uid: 'alice');
        $this->permissionService->method('canViewPlacement')->willReturn(true);
        $this->healthPingService->method('resolveForPlacement')->willReturn(['error' => 'placement_not_found']);

        $response = $this->controller->show(placementId: 999);

        $this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
    }//end testShowReturns404WhenPlacementUnresolvable()

    public function testShowReturns404WhenPingNotConfigured(): void
    {
        $this->authAsUser(uid: 'alice');
        $this->permissionService->method('canViewPlacement')->willReturn(true);
        $this->healthPingService->method('resolveForPlacement')->willReturn(['error' => 'not_configured']);

        $response = $this->controller->show(placementId: 7);

        $this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
    }//end testShowReturns404WhenPingNotConfigured()

    // -------------------------------------------------------------
    // validate() — REQ-HPING-001.
    // -------------------------------------------------------------

    public function testValidateReturns401WhenAnonymous(): void
    {
        $this->userSession->method('getUser')->willReturn(null);

        $response = $this->controller->validate();

        $this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
    }//end testValidateReturns401WhenAnonymous()

    public function testValidateReturnsErrorsFromService(): void
    {
        $this->authAsUser(uid: 'alice');
        $this->request->method('getParam')->with('config')->willReturn([
            'healthPingEnabled' => true,
            'healthUrl'         => 'https://blocked.example.com',
        ]);
        $this->healthPingService->method('validateConfig')->willReturn(['host_not_allowed']);

        $response = $this->controller->validate();
        $data     = $response->getData();

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $this->assertFalse($data['valid']);
        $this->assertSame(['host_not_allowed'], $data['errors']);
    }//end testValidateReturnsErrorsFromService()
}//end class
