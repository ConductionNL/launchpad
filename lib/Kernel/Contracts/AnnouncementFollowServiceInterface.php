<?php

/**
 * Contract of AnnouncementFollowService
 *
 * The public surface of AnnouncementFollowService that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Kernel\Contracts;

/**
 * Contract of AnnouncementFollowService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface AnnouncementFollowServiceInterface {
	/**
	 * Follow a category; following it twice changes nothing.
	 *
	 * @param string $userId   The reader.
	 * @param string $category The category.
	 *
	 * @return list<string> The categories the reader follows.
	 *
	 * @throws \InvalidArgumentException When the category is empty or too long.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	public function follow(string $userId, string $category): array;

	/**
	 * Stop following a category.
	 *
	 * @param string $userId   The reader.
	 * @param string $category The category.
	 *
	 * @return list<string> The categories the reader still follows.
	 *
	 * @throws \InvalidArgumentException When the category is empty or too long.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	public function unfollow(string $userId, string $category): array;

	/**
	 * The categories a reader follows.
	 *
	 * @param string $userId The reader.
	 *
	 * @return list<string>
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	public function followedCategories(string $userId): array;
}//end interface
