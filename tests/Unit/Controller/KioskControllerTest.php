<?php

/**
 * KioskControllerTest
 *
 * Unit tests for KioskController covering the 5 endpoints: create
 * (201/401/403), index (200), update (404/403), destroy (204/404), and the
 * public render endpoint (200 with bearer-marking, 404 with throttle).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/dashboard-kiosk-mode/tasks.md#task-8
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\KioskController;
use OCA\LaunchPad\Db\KioskPlaylist;
use OCA\LaunchPad\Exception\PlaylistNotFoundException;
use OCA\LaunchPad\Service\KioskService;
use OCA\LaunchPad\Service\PublicShareContext;
use OCA\LaunchPad\Service\PublicShareService;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class KioskControllerTest extends TestCase
{

    /** @var IRequest&MockObject */
    private $request;

    /** @var KioskService&MockObject */
    private $kioskService;

    /** @var PublicShareContext&MockObject */
    private $shareContext;

    /** @var LoggerInterface&MockObject */
    private $logger;

    protected function setUp(): void
    {
        $this->request      = $this->createMock(IRequest::class);
        $this->kioskService = $this->createMock(KioskService::class);
        $this->shareContext = $this->createMock(PublicShareContext::class);
        $this->logger       = $this->createMock(LoggerInterface::class);
    }

    private function makeController(?string $userId='alice'): KioskController
    {
        return new KioskController(
            request: $this->request,
            kioskService: $this->kioskService,
            shareContext: $this->shareContext,
            logger: $this->logger,
            userId: $userId,
        );
    }

    private function playlist(): KioskPlaylist
    {
        $playlist = new KioskPlaylist();
        $playlist->setName('Wall');
        $playlist->setToken(str_repeat('a', 64));
        return $playlist;
    }

    // -------------------------------------------------------------------------
    // create
    // -------------------------------------------------------------------------

    public function testCreateReturns201OnSuccess(): void
    {
        $this->kioskService->method('createPlaylist')->willReturn($this->playlist());

        $response = $this->makeController()->create(
            name: 'Wall',
            entries: [['dashboardUuid' => 'uuid-1', 'dwellSeconds' => 20]],
            refreshSeconds: 60
        );

        $this->assertSame(Http::STATUS_CREATED, $response->getStatus());
    }

    public function testCreateReturns401WhenNotLoggedIn(): void
    {
        $controller = $this->makeController(userId: null);

        $response = $controller->create(name: 'Wall', entries: [], refreshSeconds: 60);

        $this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
    }

    public function testCreateReturns403WhenForbidden(): void
    {
        $this->kioskService
            ->method('createPlaylist')
            ->willThrowException(new OCSForbiddenException('Not authorized'));

        $response = $this->makeController()->create(
            name: 'Wall',
            entries: [['dashboardUuid' => 'uuid-1']],
            refreshSeconds: 60
        );

        $this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
    }

    // -------------------------------------------------------------------------
    // index
    // -------------------------------------------------------------------------

    public function testIndexReturns200WithPlaylists(): void
    {
        $this->kioskService
            ->method('listPlaylists')
            ->willReturn([$this->playlist()]);

        $response = $this->makeController()->index();

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $this->assertCount(1, $response->getData());
    }

    // -------------------------------------------------------------------------
    // update
    // -------------------------------------------------------------------------

    public function testUpdateReturns404WhenPlaylistMissing(): void
    {
        $this->kioskService
            ->method('updatePlaylist')
            ->willThrowException(new PlaylistNotFoundException());

        $response = $this->makeController()->update(
            id: 99,
            name: 'Wall',
            entries: [],
            refreshSeconds: 60
        );

        $this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
    }

    public function testUpdateReturns403WhenForbidden(): void
    {
        $this->kioskService
            ->method('updatePlaylist')
            ->willThrowException(new OCSForbiddenException('Not authorized'));

        $response = $this->makeController()->update(
            id: 5,
            name: 'Wall',
            entries: [],
            refreshSeconds: 60
        );

        $this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
    }

    // -------------------------------------------------------------------------
    // destroy (revoke)
    // -------------------------------------------------------------------------

    public function testDestroyReturns204OnSuccess(): void
    {
        $this->kioskService->method('revokePlaylist');

        $response = $this->makeController()->destroy(id: 7);

        $this->assertSame(Http::STATUS_NO_CONTENT, $response->getStatus());
    }

    public function testDestroyReturns404WhenPlaylistMissing(): void
    {
        $this->kioskService
            ->method('revokePlaylist')
            ->willThrowException(new PlaylistNotFoundException());

        $response = $this->makeController()->destroy(id: 99);

        $this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
    }

    // -------------------------------------------------------------------------
    // render (public)
    // -------------------------------------------------------------------------

    public function testRenderReturns200AndMarksBearer(): void
    {
        $controller = $this->makeController(userId: null);

        $payload = [
            'playlist' => ['name' => 'Wall'],
            'entries'  => [],
        ];
        $this->kioskService->method('renderPlaylist')->willReturn($payload);

        // The render path marks the request as a read-only bearer.
        $this->shareContext
            ->expects($this->once())
            ->method('markBearer')
            ->with(token: 'tok');

        $response = $controller->render(token: 'tok');

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $this->assertArrayHasKey('playlist', $response->getData());
        $this->assertArrayHasKey('entries', $response->getData());
    }

    public function testRenderReturns404WithThrottleOnUnknownToken(): void
    {
        $controller = $this->makeController(userId: null);

        $this->kioskService
            ->method('renderPlaylist')
            ->willThrowException(new PlaylistNotFoundException());

        // No bearer is marked when the token is unknown.
        $this->shareContext->expects($this->never())->method('markBearer');

        $response = $controller->render(token: 'bogus');

        $this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
    }
}//end class
