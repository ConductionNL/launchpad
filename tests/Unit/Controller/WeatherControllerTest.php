<?php

/**
 * WeatherController Test
 *
 * Unit tests for the weather-widget endpoint's authorization and
 * response contract (REQ-WEATHER-001).
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

use OCA\LaunchPad\Controller\WeatherController;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\WeatherService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class WeatherControllerTest extends TestCase
{
    private IRequest $request;
    private WeatherService $weatherService;
    private PermissionService $permissionService;
    private IUserSession $userSession;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->request           = $this->createMock(originalClassName: IRequest::class);
        $this->weatherService    = $this->createMock(originalClassName: WeatherService::class);
        $this->permissionService = $this->createMock(originalClassName: PermissionService::class);
        $this->userSession       = $this->createMock(originalClassName: IUserSession::class);
        $this->logger            = $this->createMock(originalClassName: LoggerInterface::class);
    }//end setUp()

    /**
     * Build the controller under test.
     *
     * @return WeatherController
     */
    private function controller(): WeatherController
    {
        return new WeatherController(
            $this->request,
            $this->weatherService,
            $this->permissionService,
            $this->userSession,
            $this->logger,
        );
    }//end controller()

    /**
     * Put a logged-in user on the session mock.
     *
     * @param string $uid The user id.
     *
     * @return void
     */
    private function loginAs(string $uid): void
    {
        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn($uid);
        $this->userSession->method('getUser')->willReturn($user);
    }//end loginAs()

    /**
     * An anonymous caller MUST get 401 and MUST NOT trigger a resolution.
     *
     * @return void
     */
    public function testAnonymousCallerGets401AndNoFetch(): void
    {
        $this->userSession->method('getUser')->willReturn(null);
        $this->weatherService->expects($this->never())->method('resolveForPlacement');

        $response = $this->controller()->show(placementId: 1);

        $this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
    }//end testAnonymousCallerGets401AndNoFetch()

    /**
     * A caller who may not view the placement MUST get 403 and the fetch
     * MUST NOT be performed (REQ-WEATHER-001 "Caller authorization").
     *
     * @return void
     */
    public function testUnauthorizedPlacementGets403AndNoFetch(): void
    {
        $this->loginAs('mallory');
        $this->permissionService->method('canViewPlacement')->willReturn(false);
        $this->weatherService->expects($this->never())->method('resolveForPlacement');

        $response = $this->controller()->show(placementId: 42);

        $this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
    }//end testUnauthorizedPlacementGets403AndNoFetch()

    /**
     * An authorized caller gets 200 and exactly the public reading shape —
     * no credentials, no provider URL.
     *
     * @return void
     */
    public function testAuthorizedCallerGetsReadingWithoutCredentials(): void
    {
        $this->loginAs('alice');
        $this->permissionService->method('canViewPlacement')->willReturn(true);
        $this->weatherService->method('resolveForPlacement')->willReturn(
            [
                'location'      => 'Utrecht',
                'tempValue'     => 12.5,
                'units'         => 'metric',
                'condition'     => 'cloudy',
                'conditionText' => 'Cloudy',
                'language'      => 'nl',
                'fetchedAt'     => '2026-07-23T12:00:00+00:00',
                'stale'         => false,
            ]
        );

        $response = $this->controller()->show(placementId: 7);
        $data     = $response->getData();

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $this->assertSame('Utrecht', $data['location']);
        $this->assertArrayNotHasKey('apiKey', $data);
        $this->assertArrayNotHasKey('providerUrl', $data);
        $this->assertStringNotContainsString('http', json_encode($data) ?: '');
    }//end testAuthorizedCallerGetsReadingWithoutCredentials()

    /**
     * A resolution error (no reading, no cache) surfaces as 502, not a
     * 200 with a broken body.
     *
     * @return void
     */
    public function testResolutionErrorSurfacesAs502(): void
    {
        $this->loginAs('alice');
        $this->permissionService->method('canViewPlacement')->willReturn(true);
        $this->weatherService->method('resolveForPlacement')->willReturn(
            ['error' => 'weather_unavailable']
        );

        $response = $this->controller()->show(placementId: 7);

        $this->assertSame(Http::STATUS_BAD_GATEWAY, $response->getStatus());
        $this->assertSame('weather_unavailable', $response->getData()['error']);
    }//end testResolutionErrorSurfacesAs502()

    /**
     * An unexpected throwable is caught and reported as 500 — the
     * endpoint never leaks a stack trace to the caller.
     *
     * @return void
     */
    public function testUnexpectedFailureIsCaughtAs500(): void
    {
        $this->loginAs('alice');
        $this->permissionService->method('canViewPlacement')->willReturn(true);
        $this->weatherService->method('resolveForPlacement')
            ->willThrowException(new \RuntimeException('boom'));

        $response = $this->controller()->show(placementId: 7);

        $this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
        $this->assertStringNotContainsString('boom', json_encode($response->getData()) ?: '');
    }//end testUnexpectedFailureIsCaughtAs500()
}//end class
