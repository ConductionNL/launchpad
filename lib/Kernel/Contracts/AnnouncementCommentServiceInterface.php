<?php

/**
 * Contract of AnnouncementCommentService
 *
 * The public surface of AnnouncementCommentService that other components call, so they depend
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
 * Contract of AnnouncementCommentService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface AnnouncementCommentServiceInterface {
	/**
	 * The like and comment counts of several announcements, and whether the reader liked each.
	 *
	 * @param string                               $userId        The reader.
	 * @param list<\OCA\LaunchPad\Db\Announcement> $announcements Announcements the reader may see.
	 *
	 * @return array<string, array{likes: int, comments: int, liked: bool}> Keyed by uuid.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	public function countsFor(string $userId, array $announcements): array;

	/**
	 * Like an announcement; liking it again changes nothing.
	 *
	 * @param string $userId The reader.
	 * @param string $uuid   The announcement.
	 *
	 * @return array{likes: int, liked: bool}
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException When the announcement does not reach the reader.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	public function like(string $userId, string $uuid): array;

	/**
	 * Take back a like; without one this changes nothing.
	 *
	 * @param string $userId The reader.
	 * @param string $uuid   The announcement.
	 *
	 * @return array{likes: int, liked: bool}
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException When the announcement does not reach the reader.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	public function unlike(string $userId, string $uuid): array;

	/**
	 * The comments on an announcement, oldest first.
	 *
	 * @param string $userId The reader.
	 * @param string $uuid   The announcement.
	 *
	 * @return list<array{id: string, actorId: string, displayName: string, message: string, createdAt: string|null}>
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException When the announcement does not reach the reader.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	public function commentsOn(string $userId, string $uuid): array;

	/**
	 * Comment on an announcement that allows comments.
	 *
	 * @param string $userId  The reader.
	 * @param string $uuid    The announcement.
	 * @param string $message The comment text.
	 *
	 * @return array{id: string, actorId: string, displayName: string, message: string, createdAt: string|null}
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException When the announcement does not reach the reader.
	 * @throws \OCA\LaunchPad\Exception\ForbiddenException       When the author did not allow comments.
	 * @throws \InvalidArgumentException When the text is empty or too long.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	public function comment(string $userId, string $uuid, string $message): array;

	/**
	 * Remove every like and comment of an announcement, after it is deleted.
	 *
	 * @param string $uuid The announcement.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-like-and-comment-through-nextcloud-comments-req-ann-002
	 */
	public function purge(string $uuid): void;
}//end interface
