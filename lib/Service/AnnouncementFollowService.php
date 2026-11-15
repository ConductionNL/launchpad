<?php

/**
 * AnnouncementFollowService
 *
 * The categories a reader follows (engagement-announcements, REQ-ANN-003).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\AnnouncementFollow;
use OCA\LaunchPad\Db\AnnouncementFollowMapper;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * Follow and unfollow announcement categories.
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
 */
class AnnouncementFollowService {

	/**
	 * Constructor.
	 *
	 * @param AnnouncementFollowMapper $follows The followed categories.
	 * @param AnnouncementInput        $input   Checks a category name.
	 * @param ITimeFactory             $time    The clock.
	 */
	public function __construct(
		private readonly AnnouncementFollowMapper $follows,
		private readonly AnnouncementInput $input,
		private readonly ITimeFactory $time,
	) {
	}//end __construct()

	/**
	 * Follow a category; following it twice changes nothing.
	 *
	 * @param string $userId   The reader.
	 * @param string $category The category.
	 *
	 * @return list<string> The categories the reader follows.
	 *
	 * @throws InvalidArgumentException When the category is empty or too long.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	public function follow(string $userId, string $category): array {
		$category = $this->input->category(value: $category);
		if (in_array($category, $this->followedCategories(userId: $userId), true) === false) {
			$follow = new AnnouncementFollow();
			$follow->setUserId($userId);
			$follow->setCategory($category);
			$follow->setCreatedAt($this->time->getDateTime());
			$this->follows->insert(entity: $follow);
		}

		return $this->followedCategories(userId: $userId);
	}//end follow()

	/**
	 * Stop following a category.
	 *
	 * @param string $userId   The reader.
	 * @param string $category The category.
	 *
	 * @return list<string> The categories the reader still follows.
	 *
	 * @throws InvalidArgumentException When the category is empty or too long.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	public function unfollow(string $userId, string $category): array {
		$this->follows->unfollow(userId: $userId, category: $this->input->category(value: $category));

		return $this->followedCategories(userId: $userId);
	}//end unfollow()

	/**
	 * The categories a reader follows.
	 *
	 * @param string $userId The reader.
	 *
	 * @return list<string>
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	public function followedCategories(string $userId): array {
		return array_values(
			array_map(static fn (AnnouncementFollow $follow): string => (string) $follow->getCategory(), $this->follows->findByUser(userId: $userId))
		);
	}//end followedCategories()
}//end class
