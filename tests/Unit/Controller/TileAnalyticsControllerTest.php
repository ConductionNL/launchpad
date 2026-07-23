<?php

/**
 * TileAnalyticsControllerTest
 *
 * Controller-level tests for the tile usage-analytics HTTP entry
 * points (REQ-TANLT-001..005). Verifies the record endpoint's
 * authentication/404 handling, the config endpoint, the admin-only
 * report/CSV shape, and that non-admin ADR-023 authorization failures
 * surface as 403.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\TileAnalyticsController;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\TileAnalyticsService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TileAnalyticsControllerTest extends TestCase
{
    /**
     * @var IRequest&MockObject
     */
    private $request;

    /**
     * @var TileAnalyticsService&MockObject
     */
    private $tileAnalyticsService;

    /**
     * @var ActionAuthService&MockObject
     */
    private $actionAuth;

    /**
     * @var IUserSession&MockObject
     */
    private $userSession;

    private TileAnalyticsController $controller;

    protected function setUp(): void
    {
        $this->request              = $this->createMock(IRequest::class);
        $this->tileAnalyticsService = $this->createMock(TileAnalyticsService::class);
        $this->actionAuth           = $this->createMock(ActionAuthService::class);
        $this->userSession          = $this->createMock(IUserSession::class);

        $this->controller = new TileAnalyticsController(
            request: $this->request,
            tileAnalyticsService: $this->tileAnalyticsService,
            actionAuth: $this->actionAuth,
            userSession: $this->userSession,
        );
    }//end setUp()

    private function loginAs(string $uid): void
    {
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn($uid);
        $this->userSession->method('getUser')->willReturn($user);
    }//end loginAs()

    public function testRecordClickReturns401WhenUnauthenticated(): void
    {
        $this->userSession->method('getUser')->willReturn(null);

        $response = $this->controller->recordClick(placementId: '1');

        $this->assertInstanceOf(JSONResponse::class, $response);
        $this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
    }

    public function testRecordClickReturns204OnSuccess(): void
    {
        $this->loginAs(uid: 'alice');
        $this->tileAnalyticsService->method('recordClick')->willReturn(true);

        $response = $this->controller->recordClick(placementId: '5');

        $this->assertSame(Http::STATUS_NO_CONTENT, $response->getStatus());
    }

    /**
     * REQ-TANLT-003 — even when the service short-circuits to a
     * no-op (globally disabled / opted out), the endpoint still
     * returns 204, never an error.
     */
    public function testRecordClickReturns204EvenWhenServiceNoOps(): void
    {
        $this->loginAs(uid: 'eve');
        $this->tileAnalyticsService->method('recordClick')->willReturn(false);

        $response = $this->controller->recordClick(placementId: '5');

        $this->assertSame(Http::STATUS_NO_CONTENT, $response->getStatus());
    }

    public function testRecordClickReturns404WhenPlacementMissing(): void
    {
        $this->loginAs(uid: 'alice');
        $this->tileAnalyticsService->method('recordClick')
            ->willThrowException(new DoesNotExistException(msg: 'gone'));

        $response = $this->controller->recordClick(placementId: '999');

        $this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
    }

    public function testRecordClickReturns403WhenActionAuthDenies(): void
    {
        $this->loginAs(uid: 'mallory');
        $this->actionAuth->method('requireAction')
            ->willThrowException(new OCSForbiddenException('denied'));

        $response = $this->controller->recordClick(placementId: '5');

        $this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
    }

    public function testConfigReturns401WhenUnauthenticated(): void
    {
        $this->userSession->method('getUser')->willReturn(null);

        $response = $this->controller->config();

        $this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
    }

    public function testConfigReturnsEnabledFlagFromService(): void
    {
        $this->loginAs(uid: 'alice');
        $this->tileAnalyticsService->method('isTrackingActiveFor')
            ->with('alice')
            ->willReturn(false);

        $response = $this->controller->config();

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $this->assertFalse($response->getData()['enabled']);
    }

    public function testTopTilesReturnsSortedRows(): void
    {
        $this->loginAs(uid: 'admin');
        $rows = [
            ['placementUuid' => '1', 'dashboardUuid' => 'd1', 'clickCount' => 120, 'uniqueActorCount' => 80],
            ['placementUuid' => '2', 'dashboardUuid' => 'd1', 'clickCount' => 40, 'uniqueActorCount' => 20],
        ];
        $this->tileAnalyticsService->method('getTopTiles')->willReturn($rows);

        $response = $this->controller->topTiles(period: '7d', limit: 10);

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $this->assertSame($rows, $response->getData());
    }

    public function testTopTilesReturns403WhenActionAuthDenies(): void
    {
        $this->loginAs(uid: 'bob');
        $this->actionAuth->method('requireAction')
            ->willThrowException(new OCSForbiddenException('denied'));

        $response = $this->controller->topTiles();

        $this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
    }

    public function testDashboardBreakdownReturnsRows(): void
    {
        $this->loginAs(uid: 'admin');
        $rows = [
            ['placementUuid' => '3', 'clickCount' => 5, 'uniqueActorCount' => 3],
        ];
        $this->tileAnalyticsService->method('getDashboardBreakdown')->willReturn($rows);

        $response = $this->controller->dashboardBreakdown(uuid: 'dsh-9', period: '30d');

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $this->assertSame($rows, $response->getData());
    }

    // NOTE: the success path of `exportCsv()` constructs a
    // `DataDownloadResponse`, which cannot be instantiated under the
    // OCP stub bootstrap used by this unit-test suite (missing
    // `Symfony\Component\HttpFoundation\HeaderUtils` — mirrors the
    // same documented limitation in `AcknowledgementControllerTest`
    // and `AnalyticsController::exportCsv`). That path is covered by
    // the Playwright e2e / gate-19 spec instead; only the
    // authorization-failure path is unit-tested here.

    public function testExportCsvReturns403WhenActionAuthDenies(): void
    {
        $this->loginAs(uid: 'bob');
        $this->actionAuth->method('requireAction')
            ->willThrowException(new OCSForbiddenException('denied'));

        $response = $this->controller->exportCsv();

        $this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
    }
}
