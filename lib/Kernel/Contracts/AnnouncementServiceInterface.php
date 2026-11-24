<?php

/**
 * Contract of AnnouncementService
 *
 * The public surface of AnnouncementService that other components call, so they depend
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

use OCA\LaunchPad\Db\Announcement;

/**
 * Contract of AnnouncementService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface AnnouncementServiceInterface {
	/**
	 * Whether a user may write announcements: an administrator or a member of an editor group.
	 *
	 * @param string $userId The user.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function isAuthor(string $userId): bool;

	/**
	 * The published announcements a reader sees now, newest first.
	 *
	 * @param string $userId The reader.
	 *
	 * @return list<\OCA\LaunchPad\Db\Announcement>
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function visibleTo(string $userId): array;

	/**
	 * The drafts a user may see: every draft for an administrator, their own for an author, none otherwise.
	 *
	 * @param string $userId The user.
	 *
	 * @return list<\OCA\LaunchPad\Db\Announcement>
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function draftsFor(string $userId): array;

	/**
	 * One announcement as a user may see it; anything they may not see is not found.
	 *
	 * @param string $userId The user.
	 * @param string $uuid   The announcement.
	 *
	 * @return Announcement
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException When it does not exist or the user may not see it.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function findFor(string $userId, string $uuid): Announcement;

	/**
	 * Write a new draft.
	 *
	 * @param string               $userId The author.
	 * @param array<string, mixed> $input  The fields.
	 *
	 * @return Announcement
	 *
	 * @throws \OCA\LaunchPad\Exception\ForbiddenException       When the user may not author.
	 * @throws \InvalidArgumentException When a field is not valid.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function create(string $userId, array $input): Announcement;

	/**
	 * Change an announcement.
	 *
	 * @param string               $userId The user.
	 * @param string               $uuid   The announcement.
	 * @param array<string, mixed> $input  The fields to change.
	 *
	 * @return Announcement
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException    When the user may not see it.
	 * @throws \OCA\LaunchPad\Exception\ForbiddenException       When the user may see it but not change it.
	 * @throws \InvalidArgumentException When a field is not valid.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function update(string $userId, string $uuid, array $input): Announcement;

	/**
	 * Publish an announcement; without a publish time it is published now.
	 *
	 * @param string $userId The user.
	 * @param string $uuid   The announcement.
	 *
	 * @return Announcement
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException    When the user may not see it.
	 * @throws \OCA\LaunchPad\Exception\ForbiddenException       When the user may see it but not publish it.
	 * @throws \InvalidArgumentException When a notice has no end time.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-notices-show-above-every-targeted-dashboard-for-their-period-req-ann-005
	 */
	public function publish(string $userId, string $uuid): Announcement;

	/**
	 * Delete an announcement.
	 *
	 * @param string $userId The user.
	 * @param string $uuid   The announcement.
	 *
	 * @return void
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException When the user may not see it.
	 * @throws \OCA\LaunchPad\Exception\ForbiddenException    When the user may see it but not delete it.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function delete(string $userId, string $uuid): void;

	/**
	 * About how many people the target groups reach: everyone when there are none.
	 *
	 * @param list<string> $groups The target groups.
	 *
	 * @return int
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-preview-before-publishing-req-ann-004
	 */
	public function reach(array $groups): int;

	/**
	 * "Preview as": whether target groups reach one person (D4).
	 *
	 * @param list<string> $groups The target groups; none means everyone.
	 * @param string       $userId The person to check.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-preview-before-publishing-req-ann-004
	 */
	public function reachesUser(array $groups, string $userId): bool;

	/**
	 * Whether a user may change an announcement and read its read
	 * confirmations: an administrator, or its author while still an author.
	 *
	 * @param string       $userId       The user.
	 * @param Announcement $announcement The announcement.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
	 */
	public function mayManage(string $userId, Announcement $announcement): bool;
}//end interface
