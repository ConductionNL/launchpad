<?php

/**
 * AnnouncementTargetGroups Test
 *
 * The groups the announcement form offers as the target group, sorted by
 * display name.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\AnnouncementTargetGroups;
use OCP\IGroup;
use OCP\IGroupManager;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\AnnouncementTargetGroups
 */
class AnnouncementTargetGroupsTest extends TestCase {

	/**
	 * Every group, by display name, sorted without regard to case.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
	 */
	public function testListsEveryGroupSortedByDisplayName(): void {
		$manager = $this->createMock(originalClassName: IGroupManager::class);
		$manager->expects($this->once())->method('search')->with('')->willReturn(
			[
				$this->group(gid: 'wonen', displayName: 'Wonen'),
				$this->group(gid: 'burgerzaken', displayName: 'burgerzaken'),
				$this->group(gid: 'medewerkers', displayName: 'Medewerkers'),
			]
		);

		self::assertSame(
			expected: [
				['id' => 'burgerzaken', 'displayName' => 'burgerzaken'],
				['id' => 'medewerkers', 'displayName' => 'Medewerkers'],
				['id' => 'wonen', 'displayName' => 'Wonen'],
			],
			actual: (new AnnouncementTargetGroups(groupManager: $manager))->all()
		);
	}//end testListsEveryGroupSortedByDisplayName()

	/**
	 * A group double with an id and display name.
	 *
	 * @param string $gid         The group id.
	 * @param string $displayName The display name.
	 *
	 * @return IGroup
	 */
	private function group(string $gid, string $displayName): IGroup {
		$group = $this->createMock(originalClassName: IGroup::class);
		$group->method('getGID')->willReturn($gid);
		$group->method('getDisplayName')->willReturn($displayName);
		return $group;
	}//end group()
}//end class
