<?php

/**
 * DashboardVersionApiController Contract Test
 *
 * Wire-contract coverage for the two snapshot endpoints of the
 * `dashboard-versioning` capability (REQ-VERS-002 / REQ-VERS-004):
 *
 *   - `POST /api/dashboards/{uuid}/versions`                — createVersion
 *   - `GET  /api/dashboards/{uuid}/versions/{versionNumber}` — fetchVersion
 *
 * Both are `#[NoAdminRequired]`, so every authenticated account can address
 * them with an arbitrary `{uuid}`. The contract asserted here is the full
 * refusal ladder in the order the controller applies it — anonymous 401,
 * ADR-023 action denial 403, unknown dashboard 404, unknown snapshot 404,
 * non-owner 403 (mapped from the service sentinel) — plus the success
 * envelopes, whose exact shape the frontend depends on: a `201` carrying
 * `{version}` for a create, a `200` carrying `{version, snapshot}` for a
 * fetch.
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

use Exception;
use OCA\LaunchPad\Controller\DashboardVersionApiController;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardVersion;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Contract tests for createVersion + fetchVersion.
 */
class DashboardVersionApiControllerTest extends TestCase
{

    /**
     * HTTP request mock.
     *
     * @var IRequest&MockObject
     */
    private $request;

    /**
     * Dashboard row lookup mock.
     *
     * @var DashboardMapper&MockObject
     */
    private $dashboardMapper;

    /**
     * Version service mock.
     *
     * @var DashboardVersionService&MockObject
     */
    private $versionService;

    /**
     * ADR-023 action authorization mock.
     *
     * @var ActionAuthService&MockObject
     */
    private $actionAuth;

    /**
     * User session mock.
     *
     * @var IUserSession&MockObject
     */
    private $userSession;


    /**
     * Set up shared mocks.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->request         = $this->createMock(IRequest::class);
        $this->dashboardMapper = $this->createMock(DashboardMapper::class);
        $this->versionService  = $this->createMock(DashboardVersionService::class);
        $this->actionAuth      = $this->createMock(ActionAuthService::class);
        $this->userSession     = $this->createMock(IUserSession::class);

    }//end setUp()


    /**
     * Build the controller for the supplied user (NULL = anonymous).
     *
     * @param string|null $userId The acting user ID.
     *
     * @return DashboardVersionApiController
     */
    private function makeController(?string $userId): DashboardVersionApiController
    {
        $user = null;
        if ($userId !== null) {
            $user = $this->createMock(IUser::class);
            $user->method('getUID')->willReturn($userId);
        }

        $this->userSession->method('getUser')->willReturn($user);

        return new DashboardVersionApiController(
            request: $this->request,
            dashboardMapper: $this->dashboardMapper,
            versionService: $this->versionService,
            actionAuth: $this->actionAuth,
            userSession: $this->userSession,
            logger: $this->createMock(LoggerInterface::class),
            userId: $userId,
        );

    }//end makeController()


    /**
     * Build a persisted-looking dashboard fixture.
     *
     * @param string $uuid The dashboard UUID.
     *
     * @return Dashboard
     */
    private function makeDashboard(string $uuid): Dashboard
    {
        $dashboard = new Dashboard();
        $dashboard->setId(11);
        $dashboard->setUuid($uuid);
        $dashboard->setName('Ops');

        return $dashboard;

    }//end makeDashboard()


    /**
     * Build a snapshot fixture.
     *
     * @param integer $number The version number.
     * @param string  $body   The snapshot JSON body.
     *
     * @return DashboardVersion
     */
    private function makeVersion(int $number, string $body='{"widgets":[]}'): DashboardVersion
    {
        $version = new DashboardVersion();
        $version->setId(500 + $number);
        $version->setDashboardUuid('uuid-ops');
        $version->setVersionNumber($number);
        $version->setSnapshotJson($body);
        $version->setCreatedBy('alice');
        $version->setCreatedAt('2026-08-11T09:00:00+00:00');
        $version->setNote('before the rollout');

        return $version;

    }//end makeVersion()


    // -----------------------------------------------------------------------
    // createVersion — POST /api/dashboards/{uuid}/versions
    // -----------------------------------------------------------------------


    /**
     * An anonymous caller MUST get 401 and MUST NOT reach the mapper.
     *
     * @return void
     */
    public function testCreateVersionRejectsAnonymousWith401(): void
    {
        $this->dashboardMapper->expects($this->never())->method('findByUuid');
        $this->versionService->expects($this->never())->method('createExplicitSnapshot');

        $controller = $this->makeController(null);
        $response   = $controller->createVersion(uuid: 'uuid-ops');

        $this->assertSame(
            expected: Http::STATUS_UNAUTHORIZED,
            actual: $response->getStatus()
        );

    }//end testCreateVersionRejectsAnonymousWith401()


    /**
     * ADR-023: a caller whose role does not carry
     * `dashboard-version.create-version` MUST get 403 before any row is read.
     *
     * @return void
     */
    public function testCreateVersionRejectsDeniedActionWith403(): void
    {
        $this->actionAuth->expects($this->once())
            ->method('requireAction')
            ->willThrowException(new OCSForbiddenException('denied'));

        $this->dashboardMapper->expects($this->never())->method('findByUuid');
        $this->versionService->expects($this->never())->method('createExplicitSnapshot');

        $controller = $this->makeController('alice');
        $response   = $controller->createVersion(uuid: 'uuid-ops');

        $this->assertSame(
            expected: Http::STATUS_FORBIDDEN,
            actual: $response->getStatus()
        );

    }//end testCreateVersionRejectsDeniedActionWith403()


    /**
     * An unknown dashboard UUID MUST come back as 404 without a snapshot
     * ever being attempted.
     *
     * @return void
     */
    public function testCreateVersionReturns404ForUnknownDashboard(): void
    {
        $this->dashboardMapper->expects($this->once())
            ->method('findByUuid')
            ->with(uuid: 'nope')
            ->willThrowException(new DoesNotExistException('missing'));

        $this->versionService->expects($this->never())->method('createExplicitSnapshot');

        $controller = $this->makeController('alice');
        $response   = $controller->createVersion(uuid: 'nope');

        $this->assertSame(
            expected: Http::STATUS_NOT_FOUND,
            actual: $response->getStatus()
        );

    }//end testCreateVersionReturns404ForUnknownDashboard()


    /**
     * REQ-VERS-002: the owner gets 201 with the persisted row, and the
     * caller-supplied note reaches the service verbatim.
     *
     * @return void
     */
    public function testCreateVersionReturns201WithThePersistedRow(): void
    {
        $this->dashboardMapper->method('findByUuid')
            ->willReturn($this->makeDashboard(uuid: 'uuid-ops'));

        $this->versionService->expects($this->once())
            ->method('createExplicitSnapshot')
            ->with(
                dashboard: $this->isInstanceOf(Dashboard::class),
                requestingUser: 'alice',
                note: 'before the rollout'
            )
            ->willReturn($this->makeVersion(number: 3));

        $controller = $this->makeController('alice');
        $response   = $controller->createVersion(
            uuid: 'uuid-ops',
            note: 'before the rollout'
        );

        $this->assertSame(
            expected: Http::STATUS_CREATED,
            actual: $response->getStatus()
        );

        $version = $response->getData()['version'];
        $this->assertSame(expected: 3, actual: $version['versionNumber']);
        $this->assertSame(expected: 'before the rollout', actual: $version['note']);
        // REQ-VERS-003: the list serialisation carries a size hint, never
        // the body itself.
        $this->assertArrayNotHasKey(key: 'snapshotJson', array: $version);
        $this->assertSame(expected: strlen('{"widgets":[]}'), actual: $version['sizeBytes']);

    }//end testCreateVersionReturns201WithThePersistedRow()


    /**
     * The service's owner-or-admin sentinel MUST be mapped to 403 — not to
     * the generic 500 every other service failure produces.
     *
     * @return void
     */
    public function testCreateVersionMapsTheOwnerSentinelTo403(): void
    {
        $this->dashboardMapper->method('findByUuid')
            ->willReturn($this->makeDashboard(uuid: 'uuid-ops'));

        $this->versionService->method('createExplicitSnapshot')
            ->willThrowException(
                new Exception(DashboardVersionService::ERR_FORBIDDEN_NOT_OWNER_OR_ADMIN)
            );

        $controller = $this->makeController('mallory');
        $response   = $controller->createVersion(uuid: 'uuid-ops');

        $this->assertSame(
            expected: Http::STATUS_FORBIDDEN,
            actual: $response->getStatus()
        );
        $this->assertSame(expected: 'forbidden', actual: $response->getData()['error']);

    }//end testCreateVersionMapsTheOwnerSentinelTo403()


    /**
     * ADR-005: an unexpected service failure MUST NOT leak its message to
     * the wire — 500 with the generic envelope.
     *
     * @return void
     */
    public function testCreateVersionHidesUnexpectedFailureDetail(): void
    {
        $this->dashboardMapper->method('findByUuid')
            ->willReturn($this->makeDashboard(uuid: 'uuid-ops'));

        $this->versionService->method('createExplicitSnapshot')
            ->willThrowException(new Exception('SQLSTATE[42P01] relation does not exist'));

        $controller = $this->makeController('alice');
        $response   = $controller->createVersion(uuid: 'uuid-ops');

        $this->assertSame(
            expected: Http::STATUS_INTERNAL_SERVER_ERROR,
            actual: $response->getStatus()
        );
        $this->assertSame(expected: 'Operation failed', actual: $response->getData()['error']);
        $this->assertStringNotContainsString(
            needle: 'SQLSTATE',
            haystack: json_encode($response->getData())
        );

    }//end testCreateVersionHidesUnexpectedFailureDetail()


    // -----------------------------------------------------------------------
    // fetchVersion — GET /api/dashboards/{uuid}/versions/{versionNumber}
    // -----------------------------------------------------------------------


    /**
     * An anonymous caller MUST get 401 and MUST NOT reach the mapper.
     *
     * @return void
     */
    public function testFetchVersionRejectsAnonymousWith401(): void
    {
        $this->dashboardMapper->expects($this->never())->method('findByUuid');
        $this->versionService->expects($this->never())->method('fetchSnapshot');

        $controller = $this->makeController(null);
        $response   = $controller->fetchVersion(uuid: 'uuid-ops', versionNumber: 1);

        $this->assertSame(
            expected: Http::STATUS_UNAUTHORIZED,
            actual: $response->getStatus()
        );

    }//end testFetchVersionRejectsAnonymousWith401()


    /**
     * ADR-023: a denied `dashboard-version.fetch-version` action MUST be a
     * 403 taken before the dashboard row is read.
     *
     * @return void
     */
    public function testFetchVersionRejectsDeniedActionWith403(): void
    {
        $this->actionAuth->method('requireAction')
            ->willThrowException(new OCSForbiddenException('denied'));

        $this->dashboardMapper->expects($this->never())->method('findByUuid');

        $controller = $this->makeController('alice');
        $response   = $controller->fetchVersion(uuid: 'uuid-ops', versionNumber: 1);

        $this->assertSame(
            expected: Http::STATUS_FORBIDDEN,
            actual: $response->getStatus()
        );

    }//end testFetchVersionRejectsDeniedActionWith403()


    /**
     * REQ-VERS-004: a version number that does not exist for an existing
     * dashboard MUST be 404 with the version-specific message.
     *
     * @return void
     */
    public function testFetchVersionReturns404ForUnknownVersionNumber(): void
    {
        $this->dashboardMapper->method('findByUuid')
            ->willReturn($this->makeDashboard(uuid: 'uuid-ops'));

        $this->versionService->expects($this->once())
            ->method('fetchSnapshot')
            ->with(
                dashboard: $this->isInstanceOf(Dashboard::class),
                versionNumber: 99,
                requestingUser: 'alice'
            )
            ->willThrowException(new DoesNotExistException('no such version'));

        $controller = $this->makeController('alice');
        $response   = $controller->fetchVersion(uuid: 'uuid-ops', versionNumber: 99);

        $this->assertSame(
            expected: Http::STATUS_NOT_FOUND,
            actual: $response->getStatus()
        );
        $this->assertSame(expected: 'Version not found', actual: $response->getData()['error']);

    }//end testFetchVersionReturns404ForUnknownVersionNumber()


    /**
     * REQ-VERS-004: unlike the list endpoint, a single fetch DOES carry the
     * raw snapshot body alongside the metadata row.
     *
     * @return void
     */
    public function testFetchVersionReturnsMetadataAndTheRawSnapshotBody(): void
    {
        $body = '{"widgets":[{"id":"clock"}]}';

        $this->dashboardMapper->method('findByUuid')
            ->willReturn($this->makeDashboard(uuid: 'uuid-ops'));

        $this->versionService->method('fetchSnapshot')
            ->willReturn($this->makeVersion(number: 7, body: $body));

        $controller = $this->makeController('alice');
        $response   = $controller->fetchVersion(uuid: 'uuid-ops', versionNumber: 7);

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());

        $data = $response->getData();
        $this->assertSame(expected: 7, actual: $data['version']['versionNumber']);
        $this->assertSame(expected: $body, actual: $data['snapshot']);

    }//end testFetchVersionReturnsMetadataAndTheRawSnapshotBody()


    /**
     * A non-owner reaching a snapshot MUST get the sentinel-mapped 403 —
     * the same refusal `createVersion` produces, from the same service rule.
     *
     * @return void
     */
    public function testFetchVersionMapsTheOwnerSentinelTo403(): void
    {
        $this->dashboardMapper->method('findByUuid')
            ->willReturn($this->makeDashboard(uuid: 'uuid-ops'));

        $this->versionService->method('fetchSnapshot')
            ->willThrowException(
                new Exception(DashboardVersionService::ERR_FORBIDDEN_NOT_OWNER_OR_ADMIN)
            );

        $controller = $this->makeController('mallory');
        $response   = $controller->fetchVersion(uuid: 'uuid-ops', versionNumber: 7);

        $this->assertSame(
            expected: Http::STATUS_FORBIDDEN,
            actual: $response->getStatus()
        );
        $this->assertSame(expected: 'forbidden', actual: $response->getData()['error']);

    }//end testFetchVersionMapsTheOwnerSentinelTo403()


}//end class
