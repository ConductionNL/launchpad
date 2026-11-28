<?php

/**
 * ScreenLinkServiceTest
 *
 * The screen link of one dashboard (REQ-KIOSK-006, REQ-KIOSK-007): create,
 * idempotent second create, the may-share and switch checks, revoke by owner
 * and administrator, and update refused on a screen link.
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
use OCA\LaunchPad\Service\ScreenLinkService;
use OCA\LaunchPad\Service\SharingLimits;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Screen link service tests.
 */
class ScreenLinkServiceTest extends TestCase {

	/**
	 * The playlist mapper.
	 *
	 * @var KioskPlaylistMapper&MockObject
	 */
	private $playlistMapper;

	/**
	 * The dashboard mapper.
	 *
	 * @var DashboardMapper&MockObject
	 */
	private $dashMapper;

	/**
	 * The owner-or-admin rule.
	 *
	 * @var PublicShareService&MockObject
	 */
	private $shareService;

	/**
	 * The stores bundle.
	 *
	 * @var KioskStores
	 */
	private KioskStores $stores;

	/**
	 * The kiosk service that makes the playlist.
	 *
	 * @var KioskService
	 */
	private KioskService $kiosk;

	/**
	 * Build the stores and the kiosk service with mocked collaborators.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->playlistMapper = $this->createMock(originalClassName: KioskPlaylistMapper::class);
		$this->dashMapper     = $this->createMock(originalClassName: DashboardMapper::class);
		$this->shareService   = $this->createMock(originalClassName: PublicShareService::class);
		$secureRandom         = $this->createMock(originalClassName: ISecureRandom::class);
		$secureRandom->method('generate')->willReturn(str_repeat(string: 'k', times: 64));

		$this->stores = new KioskStores(
			playlistMapper: $this->playlistMapper,
			dashMapper: $this->dashMapper,
			placementMapper: $this->createMock(originalClassName: WidgetPlacementMapper::class),
			shareService: $this->shareService
		);
		$this->kiosk  = new KioskService(
			stores: $this->stores,
			groupManager: $this->createMock(originalClassName: IGroupManager::class),
			secureRandom: $secureRandom,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end setUp()

	/**
	 * The service under test with the given policy.
	 *
	 * @param bool $mayShare Whether the caller may share under the policy.
	 * @param bool $switchOn Whether screen links are switched on.
	 *
	 * @return ScreenLinkService The service.
	 */
	private function service(bool $mayShare=true, bool $switchOn=true): ScreenLinkService {
		$sharing = $this->createMock(originalClassName: SharingLimits::class);
		$sharing->method('mayShare')->willReturn($mayShare);
		$sharing->method('screenLinksAllowed')->willReturn($switchOn);

		return new ScreenLinkService(
			stores: $this->stores,
			kiosk: $this->kiosk,
			sharing: $sharing,
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
	}//end service()

	/**
	 * Pieter's dashboard "Kantine".
	 *
	 * @return Dashboard The dashboard.
	 */
	private function kantine(): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setUserId('pieter');
		$dashboard->setName('Kantine');
		$dashboard->setUuid('dash-kantine');
		return $dashboard;
	}//end kantine()

	/**
	 * The active screen link of "Kantine".
	 *
	 * @return KioskPlaylist The screen link.
	 */
	private function screenLink(): KioskPlaylist {
		$link = new KioskPlaylist();
		$link->setId(7);
		$link->setToken('existingtoken');
		$link->setCreatedBy('pieter');
		$link->setScreenDashboardUuid('dash-kantine');
		$link->setEntries('[{"dashboardUuid":"dash-kantine","dwellSeconds":10}]');
		return $link;
	}//end screenLink()

	/**
	 * Scenario "The owner puts a dashboard on the canteen screen".
	 *
	 * @return void
	 */
	public function testCreateMakesAPlaylistOfThatOneDashboard(): void {
		$this->dashMapper->method('findByUuid')->willReturn($this->kantine());
		$this->playlistMapper->method('findActiveScreenLink')->willReturn(null);

		$captured = null;
		$this->playlistMapper->expects($this->once())->method('insert')->willReturnCallback(
			function (KioskPlaylist $entity) use (&$captured): KioskPlaylist {
				$captured = $entity;
				return $entity;
			}
		);

		$result = $this->service()->create(dashboardUuid: 'dash-kantine', callerId: 'pieter');

		$this->assertTrue(condition: $result['created']);
		$this->assertSame(expected: 'dash-kantine', actual: $captured->getScreenDashboardUuid());
		$this->assertSame(expected: 'Kantine', actual: $captured->getName());
		$this->assertSame(expected: 'pieter', actual: $captured->getCreatedBy());
		$this->assertSame(expected: KioskService::REFRESH_DEFAULT, actual: $captured->getRefreshSeconds());
		$this->assertSame(expected: str_repeat(string: 'k', times: 64), actual: $captured->getToken());
		$entries = $captured->getEntriesArray();
		$this->assertCount(expectedCount: 1, haystack: $entries);
		$this->assertSame(expected: 'dash-kantine', actual: $entries[0]['dashboardUuid']);
		$this->assertSame(expected: 'dash-kantine', actual: $captured->jsonSerialize()['screenDashboardUuid']);
	}//end testCreateMakesAPlaylistOfThatOneDashboard()

	/**
	 * Scenario "A second create returns the same link".
	 *
	 * @return void
	 */
	public function testSecondCreateReturnsTheExistingLink(): void {
		$this->dashMapper->method('findByUuid')->willReturn($this->kantine());
		$this->playlistMapper->method('findActiveScreenLink')->willReturn($this->screenLink());
		$this->playlistMapper->expects($this->never())->method('insert');

		$result = $this->service()->create(dashboardUuid: 'dash-kantine', callerId: 'pieter');

		$this->assertFalse(condition: $result['created']);
		$this->assertSame(expected: 'existingtoken', actual: $result['playlist']->getToken());
	}//end testSecondCreateReturnsTheExistingLink()

	/**
	 * Scenario "A reader of a shared dashboard cannot put it on a screen".
	 *
	 * @return void
	 */
	public function testCreateIsRefusedForSomeoneWhoMayNotShareTheDashboard(): void {
		$this->dashMapper->method('findByUuid')->willReturn($this->kantine());
		$this->shareService->method('authorizeShareMutation')->willThrowException(new OCSForbiddenException('Not authorized'));
		$this->playlistMapper->expects($this->never())->method('insert');

		$this->expectException(exception: OCSForbiddenException::class);
		$this->service()->create(dashboardUuid: 'dash-kantine', callerId: 'ella');
	}//end testCreateIsRefusedForSomeoneWhoMayNotShareTheDashboard()

	/**
	 * A dashboard that does not exist answers like a refused one.
	 *
	 * @return void
	 */
	public function testCreateIsRefusedForAMissingDashboard(): void {
		$this->dashMapper->method('findByUuid')->willThrowException(new DoesNotExistException('gone'));
		$this->playlistMapper->expects($this->never())->method('insert');

		$this->expectException(exception: OCSForbiddenException::class);
		$this->service()->create(dashboardUuid: 'nope', callerId: 'pieter');
	}//end testCreateIsRefusedForAMissingDashboard()

	/**
	 * Scenario "Sharing set to Nobody".
	 *
	 * @return void
	 */
	public function testCreateIsRefusedWhenTheSharingPolicyForbidsSharing(): void {
		$this->dashMapper->method('findByUuid')->willReturn($this->kantine());
		$this->playlistMapper->expects($this->never())->method('insert');

		$this->expectException(exception: OCSForbiddenException::class);
		$this->service(mayShare: false)->create(dashboardUuid: 'dash-kantine', callerId: 'pieter');
	}//end testCreateIsRefusedWhenTheSharingPolicyForbidsSharing()

	/**
	 * Scenario "The administrator switches screen links off".
	 *
	 * @return void
	 */
	public function testCreateIsRefusedWhenScreenLinksAreSwitchedOff(): void {
		$this->dashMapper->method('findByUuid')->willReturn($this->kantine());
		$this->playlistMapper->method('findActiveScreenLink')->willReturn(null);
		$this->playlistMapper->expects($this->never())->method('insert');

		$this->expectException(exception: OCSForbiddenException::class);
		$this->service(switchOn: false)->create(dashboardUuid: 'dash-kantine', callerId: 'pieter');
	}//end testCreateIsRefusedWhenScreenLinksAreSwitchedOff()

	/**
	 * Scenario "An existing screen link keeps playing".
	 *
	 * @return void
	 */
	public function testAnExistingLinkIsStillReturnedWhenSwitchedOff(): void {
		$this->dashMapper->method('findByUuid')->willReturn($this->kantine());
		$this->playlistMapper->method('findActiveScreenLink')->willReturn($this->screenLink());

		$result = $this->service(switchOn: false)->create(dashboardUuid: 'dash-kantine', callerId: 'pieter');

		$this->assertFalse(condition: $result['created']);
	}//end testAnExistingLinkIsStillReturnedWhenSwitchedOff()

	/**
	 * The read answers the active link, or null.
	 *
	 * @return void
	 */
	public function testGetReturnsTheActiveLinkOrNull(): void {
		$this->dashMapper->method('findByUuid')->willReturn($this->kantine());
		$this->playlistMapper->method('findActiveScreenLink')->willReturnOnConsecutiveCalls($this->screenLink(), null);

		$this->assertSame(expected: 'existingtoken', actual: $this->service()->get(dashboardUuid: 'dash-kantine', callerId: 'pieter')?->getToken());
		$this->assertNull(actual: $this->service()->get(dashboardUuid: 'dash-kantine', callerId: 'pieter'));
	}//end testGetReturnsTheActiveLinkOrNull()

	/**
	 * Scenarios "The owner revokes the screen link" and "Noor revokes it".
	 *
	 * @return void
	 */
	public function testRevokeByOwnerAndByAdmin(): void {
		$this->dashMapper->method('findByUuid')->willReturn($this->kantine());
		$this->playlistMapper->expects($this->exactly(count: 2))->method('revokeScreenLinksOf')->with('dash-kantine')->willReturn(1);

		$this->service()->revoke(dashboardUuid: 'dash-kantine', callerId: 'pieter');
		$this->service()->revoke(dashboardUuid: 'dash-kantine', callerId: 'noor');
	}//end testRevokeByOwnerAndByAdmin()

	/**
	 * Anyone else is refused.
	 *
	 * @return void
	 */
	public function testRevokeIsRefusedForSomeoneElse(): void {
		$this->dashMapper->method('findByUuid')->willReturn($this->kantine());
		$this->shareService->method('authorizeShareMutation')->willThrowException(new OCSForbiddenException('Not authorized'));
		$this->playlistMapper->expects($this->never())->method('revokeScreenLinksOf');

		$this->expectException(exception: OCSForbiddenException::class);
		$this->service()->revoke(dashboardUuid: 'dash-kantine', callerId: 'ella');
	}//end testRevokeIsRefusedForSomeoneElse()

	/**
	 * Scenario "A screen link does not grow into a rotation".
	 *
	 * @return void
	 */
	public function testUpdateIsRefusedOnAScreenLink(): void {
		$this->playlistMapper->method('findById')->willReturn($this->screenLink());
		$this->playlistMapper->expects($this->never())->method('update');

		$this->expectException(exception: InvalidArgumentException::class);
		$this->kiosk->updatePlaylist(
			id: 7,
			draft: new KioskPlaylistDraft(name: 'Rotation', entries: [['dashboardUuid' => 'dash-kantine'], ['dashboardUuid' => 'dash-other']], refresh: 300),
			callerId: 'pieter'
		);
	}//end testUpdateIsRefusedOnAScreenLink()
}//end class
