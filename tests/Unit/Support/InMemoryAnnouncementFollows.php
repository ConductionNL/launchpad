<?php

/**
 * InMemoryAnnouncementFollows
 *
 * The real AnnouncementFollowMapper with an array for storage.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Support
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Support;

use OCA\LaunchPad\Db\AnnouncementFollowMapper;

/**
 * In-memory follows.
 */
class InMemoryAnnouncementFollows extends AnnouncementFollowMapper {
	/**
	 * Followed categories per user.
	 *
	 * @var array<string, array<string, true>>
	 */
	public array $byUser = [];

	/**
	 * No database.
	 */
	public function __construct() {
	}//end __construct()

	/**
	 * Categories of one person.
	 *
	 * @param string $userId The person.
	 *
	 * @return string[]
	 */
	public function findCategoriesOf(string $userId): array {
		$categories = array_keys($this->byUser[$userId] ?? []);
		sort($categories);

		return $categories;
	}//end findCategoriesOf()

	/**
	 * Followers of a category.
	 *
	 * @param string $category The category.
	 *
	 * @return string[]
	 */
	public function findFollowersOf(string $category): array {
		$followers = [];
		foreach ($this->byUser as $userId => $categories) {
			if (isset($categories[$category]) === true) {
				$followers[] = (string) $userId;
			}
		}

		sort($followers);

		return $followers;
	}//end findFollowersOf()

	/**
	 * Follow.
	 *
	 * @param string $userId The person.
	 * @param string $category The category.
	 *
	 * @return void
	 */
	public function follow(string $userId, string $category): void {
		$this->byUser[$userId][$category] = true;
	}//end follow()

	/**
	 * Unfollow.
	 *
	 * @param string $userId The person.
	 * @param string $category The category.
	 *
	 * @return integer
	 */
	public function unfollow(string $userId, string $category): int {
		$had = isset($this->byUser[$userId][$category]);
		unset($this->byUser[$userId][$category]);

		return $had === true ? 1 : 0;
	}//end unfollow()
}//end class
