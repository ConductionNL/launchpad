<?php

/**
 * KioskServiceTest
 *
 * Unit tests for KioskService covering create/update/list/revoke/render
 * playlist scenarios, the per-entry owner-or-admin authorization guard, and
 * the dwell/refresh clamping rules.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
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

namespace Unit\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\KioskPlaylist;
use OCA\LaunchPad\Db\KioskPlaylistMapper;
use OCA\LaunchPad\Exception\PlaylistNotFoundException;
use OCA\LaunchPad\Service\KioskService;
use OCA\LaunchPad\Service\PublicShareService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class KioskServiceTest extends TestCase
{

    /** @var KioskPlaylistMapper&MockObject */
    private $playlistMapper;

    /** @var DashboardMapper&MockObject */
    private $dashMapper;

    /** @var PublicShareService&MockObject */
    private $shareService;

    /** @var IGroupManager&MockObject */
    private $groupManager;

    /** @var ISecureRandom&MockObject */
    private $secureRandom;

    /** @var LoggerInterface&MockObject */
    private $logger;

    private KioskService $service;

    protected function setUp(): void
    {
        $this->playlistMapper = $this->createMock(KioskPlaylistMapper::class);
        $this->dashMapper     = $this->createMock(DashboardMapper::class);
        $this->shareService   = $this->createMock(PublicShareService::class);
        $this->groupManager   = $this->createMock(IGroupManager::class);
        $this->secureRandom   = $this->createMock(ISecureRandom::class);
        $this->logger         = $this->createMock(LoggerInterface::class);

        $this->service = new KioskService(
            playlistMapper: $this->playlistMapper,
            dashMapper: $this->dashMapper,
            shareService: $this->shareService,
            groupManager: $this->groupManager,
            secureRandom: $this->secureRandom,
            logger: $this->logger,
        );
    }

    /**
     * Build a Dashboard owned by the given user.
     */
    private function dashboard(string $owner='alice'): Dashboard
    {
        $dashboard = new Dashboard();
        $dashboard->setUserId($owner);
        return $dashboard;
    }

    // -------------------------------------------------------------------------
    // createPlaylist
    // -------------------------------------------------------------------------

    public function testCreatePlaylistHappyPath(): void
    {
        $this->dashMapper->method('findByUuid')->willReturn($this->dashboard('alice'));
        // authorizeShareMutation passes (no throw).
        $this->secureRandom
            ->expects($this->once())
            ->method('generate')
            ->willReturn(str_repeat('a', 64));

        $captured = null;
        $this->playlistMapper
            ->expects($this->once())
            ->method('insert')
            ->willReturnCallback(
                function (KioskPlaylist $entity) use (&$captured) {
                    $captured = $entity;
                    return $entity;
                }
            );

        $result = $this->service->createPlaylist(
            name: 'Lobby',
            entries: [['dashboardUuid' => 'uuid-1', 'dwellSeconds' => 20]],
            refresh: 60,
            callerId: 'alice'
        );

        $this->assertInstanceOf(KioskPlaylist::class, $result);
        $this->assertSame(str_repeat('a', 64), $captured->getToken());
        $this->assertSame(60, $captured->getRefreshSeconds());
        $this->assertSame('Lobby', $captured->getName());
        $this->assertSame('alice', $captured->getCreatedBy());
        $entries = $captured->getEntriesArray();
        $this->assertCount(1, $entries);
        $this->assertSame('uuid-1', $entries[0]['dashboardUuid']);
        $this->assertSame(20, $entries[0]['dwellSeconds']);
    }

    public function testCreatePlaylistThrowsForbiddenWhenAuthFails(): void
    {
        $this->dashMapper->method('findByUuid')->willReturn($this->dashboard('alice'));
        $this->shareService
            ->method('authorizeShareMutation')
            ->willThrowException(new OCSForbiddenException('Not authorized'));

        $this->playlistMapper->expects($this->never())->method('insert');

        $this->expectException(OCSForbiddenException::class);

        $this->service->createPlaylist(
            name: 'Lobby',
            entries: [['dashboardUuid' => 'uuid-1', 'dwellSeconds' => 20]],
            refresh: 60,
            callerId: 'bob'
        );
    }

    public function testCreatePlaylistMissingDashboardThrowsForbiddenNoInsert(): void
    {
        $this->dashMapper
            ->method('findByUuid')
            ->willThrowException(new DoesNotExistException('not found'));

        $this->playlistMapper->expects($this->never())->method('insert');

        $this->expectException(OCSForbiddenException::class);

        $this->service->createPlaylist(
            name: 'Lobby',
            entries: [['dashboardUuid' => 'missing', 'dwellSeconds' => 20]],
            refresh: 60,
            callerId: 'alice'
        );
    }

    // -------------------------------------------------------------------------
    // dwell + refresh clamping
    // -------------------------------------------------------------------------

    /**
     * @dataProvider clampProvider
     */
    public function testCreatePlaylistClampsDwellAndRefresh(
        int $dwellIn,
        int $dwellOut,
        int $refreshIn,
        int $refreshOut
    ): void {
        $this->dashMapper->method('findByUuid')->willReturn($this->dashboard('alice'));
        $this->secureRandom->method('generate')->willReturn(str_repeat('a', 64));

        $captured = null;
        $this->playlistMapper
            ->method('insert')
            ->willReturnCallback(
                function (KioskPlaylist $entity) use (&$captured) {
                    $captured = $entity;
                    return $entity;
                }
            );

        $this->service->createPlaylist(
            name: 'Lobby',
            entries: [['dashboardUuid' => 'uuid-1', 'dwellSeconds' => $dwellIn]],
            refresh: $refreshIn,
            callerId: 'alice'
        );

        $this->assertSame($dwellOut, $captured->getEntriesArray()[0]['dwellSeconds']);
        $this->assertSame($refreshOut, $captured->getRefreshSeconds());
    }

    public static function clampProvider(): array
    {
        return [
            // dwell below min -> DWELL_MIN; refresh below min -> REFRESH_MIN.
            'dwell 2 => 10, refresh 5 => 30'   => [2, 10, 5, 30],
            // dwell huge -> DWELL_MAX; refresh 0 -> REFRESH_DEFAULT.
            'dwell huge => 86400, refresh 0 => 300' => [999999, 86400, 0, 300],
        ];
    }

    // -------------------------------------------------------------------------
    // updatePlaylist
    // -------------------------------------------------------------------------

    public function testUpdatePlaylistForbiddenLeavesPlaylistUnchanged(): void
    {
        $playlist = new KioskPlaylist();
        $playlist->setCreatedBy('alice');
        $playlist->setName('Original');
        $playlist->setEntries((string) json_encode([['dashboardUuid' => 'uuid-old', 'dwellSeconds' => 15]]));

        $this->playlistMapper->method('findById')->willReturn($playlist);
        $this->groupManager->method('isAdmin')->willReturn(false);

        $this->dashMapper->method('findByUuid')->willReturn($this->dashboard('alice'));
        $this->shareService
            ->method('authorizeShareMutation')
            ->willThrowException(new OCSForbiddenException('Not authorized'));

        $this->playlistMapper->expects($this->never())->method('update');

        $this->expectException(OCSForbiddenException::class);

        $this->service->updatePlaylist(
            id: 5,
            name: 'Changed',
            entries: [['dashboardUuid' => 'uuid-new', 'dwellSeconds' => 99]],
            refresh: 120,
            callerId: 'alice'
        );
    }

    public function testUpdatePlaylistUnknownIdThrowsNotFound(): void
    {
        $this->playlistMapper
            ->method('findById')
            ->willThrowException(new DoesNotExistException('not found'));

        $this->playlistMapper->expects($this->never())->method('update');

        $this->expectException(PlaylistNotFoundException::class);

        $this->service->updatePlaylist(
            id: 999,
            name: 'Changed',
            entries: [],
            refresh: 120,
            callerId: 'alice'
        );
    }

    public function testUpdatePlaylistHappyPath(): void
    {
        $playlist = new KioskPlaylist();
        $playlist->setCreatedBy('alice');

        $this->playlistMapper->method('findById')->willReturnOnConsecutiveCalls(
            $playlist,
            (function () {
                $updated = new KioskPlaylist();
                $updated->setName('Changed');
                return $updated;
            })()
        );
        $this->groupManager->method('isAdmin')->willReturn(false);
        $this->dashMapper->method('findByUuid')->willReturn($this->dashboard('alice'));

        $this->playlistMapper
            ->expects($this->once())
            ->method('update')
            ->willReturnCallback(
                static function (KioskPlaylist $entity) {
                    return $entity;
                }
            );

        $result = $this->service->updatePlaylist(
            id: 5,
            name: 'Changed',
            entries: [['dashboardUuid' => 'uuid-1', 'dwellSeconds' => 30]],
            refresh: 120,
            callerId: 'alice'
        );

        $this->assertInstanceOf(KioskPlaylist::class, $result);
    }

    // -------------------------------------------------------------------------
    // listPlaylists
    // -------------------------------------------------------------------------

    public function testListPlaylistsAdminSeesAllActive(): void
    {
        $this->groupManager->method('isAdmin')->willReturn(true);

        $all = [new KioskPlaylist(), new KioskPlaylist()];
        $this->playlistMapper->expects($this->once())->method('findAllActive')->willReturn($all);
        $this->playlistMapper->expects($this->never())->method('findByCreator');

        $result = $this->service->listPlaylists(callerId: 'admin');

        $this->assertSame($all, $result);
    }

    public function testListPlaylistsNonAdminSeesOwn(): void
    {
        $this->groupManager->method('isAdmin')->willReturn(false);

        $own = [new KioskPlaylist()];
        $this->playlistMapper
            ->expects($this->once())
            ->method('findByCreator')
            ->with(createdBy: 'alice')
            ->willReturn($own);
        $this->playlistMapper->expects($this->never())->method('findAllActive');

        $result = $this->service->listPlaylists(callerId: 'alice');

        $this->assertSame($own, $result);
    }

    // -------------------------------------------------------------------------
    // revokePlaylist
    // -------------------------------------------------------------------------

    public function testRevokePlaylistOwnerSoftRevokes(): void
    {
        $playlist = new KioskPlaylist();
        $playlist->setCreatedBy('alice');
        $playlist->setId(7);

        $this->playlistMapper->method('findById')->willReturn($playlist);
        $this->groupManager->method('isAdmin')->willReturn(false);

        $this->playlistMapper->expects($this->once())->method('softRevoke')->with(id: 7);

        $this->service->revokePlaylist(id: 7, callerId: 'alice');
    }

    public function testRevokePlaylistNonOwnerNonAdminThrowsForbidden(): void
    {
        $playlist = new KioskPlaylist();
        $playlist->setCreatedBy('alice');

        $this->playlistMapper->method('findById')->willReturn($playlist);
        $this->groupManager->method('isAdmin')->willReturn(false);

        $this->playlistMapper->expects($this->never())->method('softRevoke');

        $this->expectException(OCSForbiddenException::class);

        $this->service->revokePlaylist(id: 7, callerId: 'bob');
    }

    public function testRevokePlaylistIsIdempotent(): void
    {
        // softRevoke is itself a no-op on already-revoked playlists; the
        // service still calls it for an authorized owner.
        $playlist = new KioskPlaylist();
        $playlist->setCreatedBy('alice');
        $playlist->setId(7);
        $playlist->setRevokedAt('2026-01-01 00:00:00');

        $this->playlistMapper->method('findById')->willReturn($playlist);
        $this->groupManager->method('isAdmin')->willReturn(false);

        $this->playlistMapper->expects($this->once())->method('softRevoke')->with(id: 7);

        $this->service->revokePlaylist(id: 7, callerId: 'alice');
    }

    // -------------------------------------------------------------------------
    // renderPlaylist
    // -------------------------------------------------------------------------

    public function testRenderPlaylistUnknownTokenThrowsNotFound(): void
    {
        $this->playlistMapper
            ->method('findByToken')
            ->willThrowException(new DoesNotExistException('not found'));

        $this->expectException(PlaylistNotFoundException::class);

        $this->service->renderPlaylist(token: 'bogus');
    }

    public function testRenderPlaylistSkipsDeletedDashboardAndStripsCreatedBy(): void
    {
        $playlist = new KioskPlaylist();
        $playlist->setToken('tok');
        $playlist->setName('Wall');
        $playlist->setCreatedBy('alice');
        $playlist->setEntries(
            (string) json_encode(
                [
                    ['dashboardUuid' => 'gone', 'dwellSeconds' => 15],
                    ['dashboardUuid' => 'present', 'dwellSeconds' => 25],
                ]
            )
        );

        $this->playlistMapper->method('findByToken')->willReturn($playlist);

        $present = $this->dashboard('alice');
        $this->dashMapper
            ->method('findByUuid')
            ->willReturnCallback(
                function (string $uuid) use ($present) {
                    if ($uuid === 'gone') {
                        throw new DoesNotExistException('deleted');
                    }
                    return $present;
                }
            );

        $result = $this->service->renderPlaylist(token: 'tok');

        // The deleted-dashboard entry is omitted; only the present one remains.
        $this->assertCount(1, $result['entries']);
        $this->assertSame(25, $result['entries'][0]['dwellSeconds']);
        // The public dashboard payload strips owner attribution.
        $this->assertArrayNotHasKey('userId', $result['entries'][0]['dashboard']);
        // The playlist descriptor strips createdBy for anonymous renderers.
        $this->assertArrayNotHasKey('createdBy', $result['playlist']);
        $this->assertSame('Wall', $result['playlist']['name']);
    }
}//end class
