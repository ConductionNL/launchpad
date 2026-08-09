<?php

/**
 * TileApiControllerTest
 *
 * Covers REQ-TILE-001 (DEPRECATED) — POST/PUT/DELETE return HTTP 410
 * Gone with the documented `{status, message, replacement}` envelope and
 * GET endpoints continue to serve existing rows for backwards compat.
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

use OCA\LaunchPad\Controller\TileApiController;
use OCA\LaunchPad\Db\Tile;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\TileService;
use OCP\AppFramework\Http;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TileApiControllerTest extends TestCase
{
    /** @var TileService&MockObject */
    private TileService $tileService;
    /** @var IRequest&MockObject */
    private IRequest $request;
    /** @var IL10N&MockObject */
    private IL10N $l10n;
    /** @var ActionAuthService&MockObject */
    private ActionAuthService $actionAuth;
    /** @var IUserSession&MockObject */
    private IUserSession $userSession;

    protected function setUp(): void
    {
        $this->tileService  = $this->createMock(TileService::class);
        $this->request      = $this->createMock(IRequest::class);
        $this->l10n         = $this->createMock(IL10N::class);
        $this->actionAuth   = $this->createMock(ActionAuthService::class);
        $this->userSession  = $this->createMock(IUserSession::class);
        $this->l10n->method('t')->willReturnArgument(0);
    }

    private function makeController(?string $userId = 'alice'): TileApiController
    {
        if ($userId !== null) {
            $user = $this->createMock(IUser::class);
            $user->method('getUID')->willReturn($userId);
            $this->userSession->method('getUser')->willReturn($user);
        } else {
            $this->userSession->method('getUser')->willReturn(null);
        }

        return new TileApiController(
            request: $this->request,
            tileService: $this->tileService,
            actionAuth: $this->actionAuth,
            userSession: $this->userSession,
            l10n: $this->l10n,
            userId: $userId,
        );
    }

    public function testCreateReturns410GoneEnvelope(): void
    {
        // Was: `expects($this->never())->method('createTile')`. The write
        // methods no longer exist on TileService — a mock expectation on a
        // method that is absent cannot be written, and would not be worth
        // writing: absence is a stronger guarantee than a per-test
        // expectation that it went uncalled. Asserted directly instead, so
        // re-adding the write path fails a test rather than passing silently.
        $this->assertFalse(
            method_exists(TileService::class, 'createTile'),
            'TileService::createTile was removed with the 410 deprecation; re-adding it '
            .'restores a write path under a permanently-Gone endpoint.'
        );

        $controller = $this->makeController();
        $response   = $controller->create();
        $data       = $response->getData();

        $this->assertSame(Http::STATUS_GONE, $response->getStatus());
        $this->assertIsArray($data);
        $this->assertSame('gone', $data['status']);
        $this->assertNotEmpty($data['message']);
        $this->assertSame(
            'POST /api/dashboards/{uuid}/widgets with type:tile',
            $data['replacement']
        );
    }

    public function testUpdateReturns410GoneEnvelope(): void
    {
        $this->assertFalse(
            method_exists(TileService::class, 'updateTile'),
            'TileService::updateTile was removed with the 410 deprecation.'
        );

        $controller = $this->makeController();
        $response   = $controller->update();
        $data       = $response->getData();

        $this->assertSame(Http::STATUS_GONE, $response->getStatus());
        $this->assertSame('gone', $data['status']);
        $this->assertSame(TileApiController::REPLACEMENT_HINT, $data['replacement']);
    }

    public function testDestroyReturns410GoneEnvelope(): void
    {
        $this->assertFalse(
            method_exists(TileService::class, 'deleteTile'),
            'TileService::deleteTile was removed with the 410 deprecation.'
        );

        $controller = $this->makeController();
        $response   = $controller->destroy();
        $data       = $response->getData();

        $this->assertSame(Http::STATUS_GONE, $response->getStatus());
        $this->assertSame('gone', $data['status']);
        $this->assertNotEmpty($data['message']);
        $this->assertSame(TileApiController::REPLACEMENT_HINT, $data['replacement']);
    }

    public function testIndexStillReturnsExistingRowsForBackwardsCompat(): void
    {
        $tile = new Tile();
        $tile->setId(7);
        $tile->setUserId('alice');
        $tile->setTitle('Files');
        $tile->setIcon('icon-folder');
        $tile->setIconType('class');
        $tile->setBackgroundColor('#3b82f6');
        $tile->setTextColor('#ffffff');
        $tile->setLinkType('app');
        $tile->setLinkValue('/apps/files');

        $this->tileService
            ->expects($this->once())
            ->method('getUserTiles')
            ->with('alice')
            ->willReturn([$tile]);

        $controller = $this->makeController('alice');
        $response   = $controller->index();
        $data       = $response->getData();

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertSame('Files', $data[0]['title']);
    }

    public function testIndexRequiresUser(): void
    {
        $controller = $this->makeController(null);
        $response   = $controller->index();

        $this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
    }
}
