<?php

/**
 * KioskScreenLinkTest
 *
 * Unit tests for the screen link of one dashboard (REQ-KIOSK-006,
 * REQ-KIOSK-007): create, idempotent second create, the may-share and
 * switch checks, revoke by owner and admin, and update refused on a screen
 * link.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-8
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\KioskPlaylist;
use OCA\LaunchPad\Db\KioskPlaylistMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\KioskPlaylistDraft;
use OCA\LaunchPad\Service\KioskService;
use OCA\LaunchPad\Service\KioskStores;
use OCA\LaunchPad\Service\PublicShareService;
use OCA\LaunchPad\Service\SharingLimits;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class KioskScreenLinkTest extends TestCase {

	/** @var KioskPlaylistMapper&MockObject */
	private $playlistMapper;

	/** @var DashboardMapper&MockObject */
	private $dashMapper;

	/** @var PublicShareService&MockObject */
	private $shareService;

	/** @var SharingLimits&MockObject */
	private $sharing;

	/** @var IGroupManager&MockObject */
	private $groupManager;

	private KioskService $service;

	protected function setUp(): void {
		$this->playlistMapper = $this->createMock(KioskPlaylistMapper::class);
		$this->dashMapper     = $this->createMock(DashboardMapper::class);
		$this->shareService   = $this->createMock(PublicShareService::class);
		$this->sharing        = $this->createMock(SharingLimits::class);
		$this->groupManager   = $this->createMock(IGroupManager::class);
		$secureRandom         = $this->createMock(ISecureRandom::class);
		$secureRandom->method('generate')->willReturn(str_repeat('k', 64));

		$this->sharing->method('mayShare')->willReturn(true);

		$this->service = new KioskService(
			stores: new KioskStores(
				playlistMapper: $this->playlistMapper,
				dashMapper: $this->dashMapper,
				placementMapper: $this->createMock(WidgetPlacementMapper::class),
				shareService: $this->shareService,
				sharing: $this->sharing
			),
			groupManager: $this->groupManager,
			secureRandom: $secureRandom,
			logger: $this->createMock(LoggerInterface::class)
		);
	}

	private function kantine(string $owner = 'pieter'): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setUserId($owner);
		$dashboard->setName('Kantine');
		$dashboard->setUuid('dash-kantine');
		return $dashboard;
	}

	private function screenLink(): KioskPlaylist {
		$link = new KioskPlaylist();
		$link->setId(7);
		$link->setToken('existingtoken');
		$link->setCreatedBy('pieter');
		$link->setScreenDashboardUuid('dash-kantine');
		$link->setEntries('[{"dashboardUuid":"dash-kantine","dwellSeconds":10}]');
		return $link;
	}

	public function testCreateMakesAPlaylistOfThatOneDashboard(): void {
		$this->dashMapper->method('findByUuid')->willReturn($this->kantine());
		$this->sharing->method('screenLinksAllowed')->willReturn(true);
		$this->playlistMapper->method('findActiveScreenLink')->willReturn(null);

		$captured = null;
		$this->playlistMapper->expects($this->once())->method('insert')->willReturnCallback(
			function (KioskPlaylist $entity) use (&$captured) {
				$captured = $entity;
				return $entity;
			}
		);

		$result = $this->service->createScreenLink(dashboardUuid: 'dash-kantine', callerId: 'pieter');

		$this->assertTrue($result['created']);
		$this->assertSame('dash-kantine', $captured->getScreenDashboardUuid());
		$this->assertSame('Kantine', $captured->getName());
		$this->assertSame('pieter', $captured->getCreatedBy());
		$this->assertSame(KioskService::REFRESH_DEFAULT, $captured->getRefreshSeconds());
		$this->assertSame(str_repeat('k', 64), $captured->getToken());
		$entries = $captured->getEntriesArray();
		$this->assertCount(1, $entries);
		$this->assertSame('dash-kantine', $entries[0]['dashboardUuid']);
		$this->assertSame('dash-kantine', $captured->jsonSerialize()['screenDashboardUuid']);
	}

	public function testSecondCreateReturnsTheExistingLink(): void {
		$this->dashMapper->method('findByUuid')->willReturn($this->kantine());
		$this->sharing->method('screenLinksAllowed')->willReturn(true);
		$this->playlistMapper->method('findActiveScreenLink')->willReturn($this->screenLink());
		$this->playlistMapper->expects($this->never())->method('insert');

		$result = $this->service->createScreenLink(dashboardUuid: 'dash-kantine', callerId: 'pieter');

		$this->assertFalse($result['created']);
		$this->assertSame('existingtoken', $result['playlist']->getToken());
	}

	public function testCreateIsRefusedForSomeoneWhoMayNotShareTheDashboard(): void {
		$this->dashMapper->method('findByUuid')->willReturn($this->kantine());
		$this->shareService->method('authorizeShareMutation')->willThrowException(new OCSForbiddenException('Not authorized'));
		$this->playlistMapper->expects($this->never())->method('insert');

		$this->expectException(OCSForbiddenException::class);
		$this->service->createScreenLink(dashboardUuid: 'dash-kantine', callerId: 'ella');
	}

	public function testCreateIsRefusedForAMissingDashboard(): void {
		$this->dashMapper->method('findByUuid')->willThrowException(new DoesNotExistException('gone'));
		$this->playlistMapper->expects($this->never())->method('insert');

		$this->expectException(OCSForbiddenException::class);
		$this->service->createScreenLink(dashboardUuid: 'nope', callerId: 'pieter');
	}

	public function testCreateIsRefusedWhenTheSharingPolicyForbidsSharing(): void {
		$sharing = $this->createMock(SharingLimits::class);
		$sharing->method('mayShare')->willReturn(false);
		$sharing->method('screenLinksAllowed')->willReturn(true);
		$service = $this->serviceWith(sharing: $sharing);
		$this->dashMapper->method('findByUuid')->willReturn($this->kantine());
		$this->playlistMapper->expects($this->never())->method('insert');

		$this->expectException(OCSForbiddenException::class);
		$service->createScreenLink(dashboardUuid: 'dash-kantine', callerId: 'pieter');
	}

	public function testCreateIsRefusedWhenScreenLinksAreSwitchedOff(): void {
		$this->dashMapper->method('findByUuid')->willReturn($this->kantine());
		$this->sharing->method('screenLinksAllowed')->willReturn(false);
		$this->playlistMapper->method('findActiveScreenLink')->willReturn(null);
		$this->playlistMapper->expects($this->never())->method('insert');

		$this->expectException(OCSForbiddenException::class);
		$this->service->createScreenLink(dashboardUuid: 'dash-kantine', callerId: 'pieter');
	}

	public function testAnExistingLinkIsStillReturnedWhenSwitchedOff(): void {
		$this->dashMapper->method('findByUuid')->willReturn($this->kantine());
		$this->sharing->method('screenLinksAllowed')->willReturn(false);
		$this->playlistMapper->method('findActiveScreenLink')->willReturn($this->screenLink());

		$result = $this->service->createScreenLink(dashboardUuid: 'dash-kantine', callerId: 'pieter');

		$this->assertFalse($result['created']);
	}

	public function testGetReturnsTheActiveLinkOrNull(): void {
		$this->dashMapper->method('findByUuid')->willReturn($this->kantine());
		$this->playlistMapper->method('findActiveScreenLink')->willReturnOnConsecutiveCalls($this->screenLink(), null);

		$this->assertSame('existingtoken', $this->service->getScreenLink(dashboardUuid: 'dash-kantine', callerId: 'pieter')?->getToken());
		$this->assertNull($this->service->getScreenLink(dashboardUuid: 'dash-kantine', callerId: 'pieter'));
	}

	public function testRevokeByOwnerAndByAdmin(): void {
		$this->dashMapper->method('findByUuid')->willReturn($this->kantine());
		// authorizeShareMutation passes for the owner and for an administrator.
		$this->playlistMapper->expects($this->exactly(2))->method('revokeScreenLinksOf')->with('dash-kantine')->willReturn(1);

		$this->service->revokeScreenLink(dashboardUuid: 'dash-kantine', callerId: 'pieter');
		$this->service->revokeScreenLink(dashboardUuid: 'dash-kantine', callerId: 'noor');
	}

	public function testRevokeIsRefusedForSomeoneElse(): void {
		$this->dashMapper->method('findByUuid')->willReturn($this->kantine());
		$this->shareService->method('authorizeShareMutation')->willThrowException(new OCSForbiddenException('Not authorized'));
		$this->playlistMapper->expects($this->never())->method('revokeScreenLinksOf');

		$this->expectException(OCSForbiddenException::class);
		$this->service->revokeScreenLink(dashboardUuid: 'dash-kantine', callerId: 'ella');
	}

	public function testUpdateIsRefusedOnAScreenLink(): void {
		$this->playlistMapper->method('findById')->willReturn($this->screenLink());
		$this->dashMapper->method('findByUuid')->willReturn($this->kantine());
		$this->playlistMapper->expects($this->never())->method('update');

		$this->expectException(InvalidArgumentException::class);
		$this->service->updatePlaylist(
			id: 7,
			draft: new KioskPlaylistDraft(name: 'Rotation', entries: [['dashboardUuid' => 'dash-kantine'], ['dashboardUuid' => 'dash-other']], refresh: 300),
			callerId: 'pieter'
		);
	}

	private function serviceWith(SharingLimits $sharing): KioskService {
		$secureRandom = $this->createMock(ISecureRandom::class);
		$secureRandom->method('generate')->willReturn(str_repeat('k', 64));
		return new KioskService(
			stores: new KioskStores(
				playlistMapper: $this->playlistMapper,
				dashMapper: $this->dashMapper,
				placementMapper: $this->createMock(WidgetPlacementMapper::class),
				shareService: $this->shareService,
				sharing: $sharing
			),
			groupManager: $this->groupManager,
			secureRandom: $secureRandom,
			logger: $this->createMock(LoggerInterface::class)
		);
	}
}
