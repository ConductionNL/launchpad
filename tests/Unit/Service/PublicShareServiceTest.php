<?php

/**
 * PublicShareServiceTest
 *
 * Unit tests for PublicShareService covering create/unlock/render/expired/revoked
 * scenarios and the owner-or-admin authorization guard.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-10
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\PublicShare;
use OCA\LaunchPad\Db\PublicShareMapper;
use OCA\LaunchPad\Exception\ShareNotFoundException;
use OCA\LaunchPad\Exception\SharePasswordRequiredException;
use OCA\LaunchPad\Service\PublicShareService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\Security\Bruteforce\IThrottler;
use OCP\Security\IHasher;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PublicShareServiceTest extends TestCase
{

    /** @var PublicShareMapper&MockObject */
    private $shareMapper;

    /** @var DashboardMapper&MockObject */
    private $dashMapper;

    /** @var IGroupManager&MockObject */
    private $groupManager;

    /** @var IHasher&MockObject */
    private $hasher;

    /** @var ISecureRandom&MockObject */
    private $secureRandom;

    /** @var IThrottler&MockObject */
    private $throttler;

    /** @var LoggerInterface&MockObject */
    private $logger;

    private PublicShareService $service;

    protected function setUp(): void
    {
        $this->shareMapper  = $this->createMock(PublicShareMapper::class);
        $this->dashMapper   = $this->createMock(DashboardMapper::class);
        $this->groupManager = $this->createMock(IGroupManager::class);
        $this->hasher       = $this->createMock(IHasher::class);
        $this->secureRandom = $this->createMock(ISecureRandom::class);
        $this->throttler    = $this->createMock(IThrottler::class);
        $this->logger       = $this->createMock(LoggerInterface::class);

        $this->service = new PublicShareService(
            shareMapper: $this->shareMapper,
            dashMapper: $this->dashMapper,
            groupManager: $this->groupManager,
            hasher: $this->hasher,
            secureRandom: $this->secureRandom,
            throttler: $this->throttler,
            logger: $this->logger,
        );
    }

    // -------------------------------------------------------------------------
    // createPublicShare
    // -------------------------------------------------------------------------

    public function testCreateShareOwnerCanCreate(): void
    {
        $dashboard = new Dashboard();
        $dashboard->setUserId('alice');

        $this->dashMapper->method('findByUuid')->willReturn($dashboard);
        $this->groupManager->method('isAdmin')->willReturn(false);
        $this->secureRandom->method('generate')->willReturn(str_repeat('a', 64));

        $saved = new PublicShare();
        $saved->setToken(str_repeat('a', 64));
        $this->shareMapper->method('insert')->willReturn($saved);

        $result = $this->service->createPublicShare(
            dashboardUuid: 'some-uuid',
            callerId: 'alice'
        );

        $this->assertInstanceOf(PublicShare::class, $result);
    }

    public function testCreateShareNonOwnerThrowsForbidden(): void
    {
        $dashboard = new Dashboard();
        $dashboard->setUserId('alice');

        $this->dashMapper->method('findByUuid')->willReturn($dashboard);
        $this->groupManager->method('isAdmin')->willReturn(false);

        $this->expectException(OCSForbiddenException::class);

        $this->service->createPublicShare(
            dashboardUuid: 'some-uuid',
            callerId: 'bob'
        );
    }

    public function testCreateShareAdminCanCreate(): void
    {
        $dashboard = new Dashboard();
        $dashboard->setUserId('alice');

        $this->dashMapper->method('findByUuid')->willReturn($dashboard);
        $this->groupManager->method('isAdmin')->willReturn(true);
        $this->secureRandom->method('generate')->willReturn(str_repeat('x', 64));

        $saved = new PublicShare();
        $saved->setToken(str_repeat('x', 64));
        $this->shareMapper->method('insert')->willReturn($saved);

        $result = $this->service->createPublicShare(
            dashboardUuid: 'some-uuid',
            callerId: 'admin'
        );

        $this->assertInstanceOf(PublicShare::class, $result);
    }

    public function testCreateShareHashesPassword(): void
    {
        $dashboard = new Dashboard();
        $dashboard->setUserId('alice');

        $this->dashMapper->method('findByUuid')->willReturn($dashboard);
        $this->groupManager->method('isAdmin')->willReturn(false);
        $this->secureRandom->method('generate')->willReturn(str_repeat('t', 64));

        $this->hasher
            ->expects($this->once())
            ->method('hash')
            ->with('SecurePass123!')
            ->willReturn('$2y$hashed');

        $saved = new PublicShare();
        $saved->setPasswordHash('$2y$hashed');
        $this->shareMapper->method('insert')->willReturn($saved);

        $this->service->createPublicShare(
            dashboardUuid: 'some-uuid',
            callerId: 'alice',
            password: 'SecurePass123!'
        );
    }

    // -------------------------------------------------------------------------
    // renderShareContent
    // -------------------------------------------------------------------------

    public function testRenderInvalidTokenThrowsNotFound(): void
    {
        $this->shareMapper
            ->method('findByToken')
            ->willThrowException(new DoesNotExistException('not found'));

        $this->expectException(ShareNotFoundException::class);

        $this->service->renderShareContent(token: 'invalid', ip: '127.0.0.1');
    }

    public function testRenderRevokedShareThrowsNotFound(): void
    {
        $share = new PublicShare();
        $share->setToken('tok');
        $share->setRevokedAt('2026-01-01 00:00:00');

        $this->shareMapper->method('findByToken')->willReturn($share);

        $this->expectException(ShareNotFoundException::class);

        $this->service->renderShareContent(token: 'tok', ip: '127.0.0.1');
    }

    public function testRenderExpiredShareThrowsNotFound(): void
    {
        $share = new PublicShare();
        $share->setToken('tok');
        $share->setRevokedAt(null);
        // Past date.
        $share->setExpiresAt('2020-01-01 00:00:00');

        $this->shareMapper->method('findByToken')->willReturn($share);

        $this->expectException(ShareNotFoundException::class);

        $this->service->renderShareContent(token: 'tok', ip: '127.0.0.1');
    }

    public function testRenderPasswordProtectedWithoutPasswordThrowsRequired(): void
    {
        $share = new PublicShare();
        $share->setToken('tok');
        $share->setRevokedAt(null);
        $share->setExpiresAt(null);
        $share->setPasswordHash('$2y$hash');
        $share->setDashboardUuid('some-uuid');

        $this->shareMapper->method('findByToken')->willReturn($share);

        $this->expectException(SharePasswordRequiredException::class);

        $this->service->renderShareContent(token: 'tok', ip: '127.0.0.1');
    }

    public function testRenderValidTokenWithoutPasswordSucceeds(): void
    {
        $share = new PublicShare();
        $share->setToken('tok');
        $share->setRevokedAt(null);
        $share->setExpiresAt(null);
        $share->setPasswordHash(null);
        $share->setDashboardUuid('some-uuid');

        $this->shareMapper->method('findByToken')->willReturn($share);
        $this->shareMapper->method('incrementViewCount');

        $dashboard = new Dashboard();
        $dashboard->setUserId('alice');
        $this->dashMapper->method('findByUuid')->willReturn($dashboard);

        $result = $this->service->renderShareContent(token: 'tok', ip: '127.0.0.1');

        $this->assertArrayHasKey('share', $result);
        $this->assertArrayHasKey('dashboard', $result);
    }

    // -------------------------------------------------------------------------
    // unlockShare
    // -------------------------------------------------------------------------

    public function testUnlockCorrectPasswordReturnsTrue(): void
    {
        $share = new PublicShare();
        $share->setToken('tok');
        $share->setRevokedAt(null);
        $share->setExpiresAt(null);
        $share->setPasswordHash('$2y$hash');

        $this->shareMapper->method('findByToken')->willReturn($share);
        $this->hasher->method('verify')->willReturn(true);

        $result = $this->service->unlockShare(
            token: 'tok',
            password: 'SecurePass123!',
            ip: '127.0.0.1'
        );

        $this->assertTrue($result);
    }

    public function testUnlockWrongPasswordReturnsFalseAndRegistersAttempt(): void
    {
        $share = new PublicShare();
        $share->setToken('tok');
        $share->setRevokedAt(null);
        $share->setExpiresAt(null);
        $share->setPasswordHash('$2y$hash');

        $this->shareMapper->method('findByToken')->willReturn($share);
        $this->hasher->method('verify')->willReturn(false);

        $this->throttler
            ->expects($this->once())
            ->method('registerAttempt')
            ->with(PublicShareService::ACTION_SHARE_PASSWORD, '127.0.0.1');

        $result = $this->service->unlockShare(
            token: 'tok',
            password: 'WrongPassword',
            ip: '127.0.0.1'
        );

        $this->assertFalse($result);
    }
}//end class
