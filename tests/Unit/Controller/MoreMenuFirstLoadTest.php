<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * What the workspace page carries for the More menu: the favourite
 * dashboards and whether the viewer may manage announcements.
 *
 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-13
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\MoreMenuFirstLoad;
use OCA\LaunchPad\Controller\ShellBootReads;
use OCA\LaunchPad\Service\AnnouncementService;
use OCA\LaunchPad\Service\FavouriteDashboardService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for MoreMenuFirstLoad.
 */
class MoreMenuFirstLoadTest extends TestCase {
	/**
	 * The favourite dashboards come under `favouriteDashboards`, checked
	 * against the visible entries the page passes, through the shell reads.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-2
	 */
	public function testTheFavouritesComeWithTheVisibleEntries(): void {
		$favourites = $this->createMock(originalClassName: FavouriteDashboardService::class);
		$favourites->expects($this->once())->method('forPage')
			->with('marieke', [['dashboard' => 'x']])
			->willReturn(['u-1']);
		$shell = new ShellBootReads(moreMenu: new MoreMenuFirstLoad(favourites: $favourites));
		$reads = $shell->firstLoad(userId: 'marieke', visible: [['dashboard' => 'x']]);
		$this->assertSame(expected: ['u-1'], actual: $reads['favouriteDashboards']);
	}//end testTheFavouritesComeWithTheVisibleEntries()

	/**
	 * Whether the viewer may manage announcements comes under
	 * `announcementsAccess`, for a logged-in viewer only.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-10
	 */
	public function testTheAnnouncementsAccessComesForALoggedInViewer(): void {
		$announcements = $this->createMock(originalClassName: AnnouncementService::class);
		$announcements->expects($this->exactly(count: 2))->method('isAuthor')
			->with('marieke')
			->willReturnOnConsecutiveCalls(true, false);
		$reads = new MoreMenuFirstLoad(announcements: $announcements);

		$this->assertSame(expected: ['canManage' => true], actual: $reads->firstLoad(userId: 'marieke')['announcementsAccess']);
		$this->assertSame(expected: ['canManage' => false], actual: $reads->firstLoad(userId: 'marieke')['announcementsAccess']);
		$this->assertNull(actual: $reads->firstLoad(userId: '')['announcementsAccess']);
	}//end testTheAnnouncementsAccessComesForALoggedInViewer()

	/**
	 * Unwired reads are null, so the builder leaves their keys out.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-10
	 */
	public function testUnwiredReadsAreNull(): void {
		$this->assertSame(
			expected: ['favouriteDashboards' => null, 'announcementsAccess' => null],
			actual: (new MoreMenuFirstLoad())->firstLoad(userId: 'marieke')
		);
	}//end testUnwiredReadsAreNull()
}//end class
