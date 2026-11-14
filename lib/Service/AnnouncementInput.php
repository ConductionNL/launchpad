<?php

/**
 * AnnouncementInput
 *
 * Checks an author's fields and copies them onto an announcement
 * (engagement-announcements, REQ-ANN-001, REQ-ANN-005). Pure: it reads and
 * writes nothing but the announcement it is given.
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
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTime;
use Exception;
use InvalidArgumentException;
use OCA\LaunchPad\Db\Announcement;

/**
 * The fields an author may set, checked.
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */
class AnnouncementInput {

	/**
	 * The longest category name.
	 */
	public const MAX_CATEGORY = 64;

	/**
	 * Copy the accepted fields of the input onto the announcement.
	 *
	 * Only the fields present are written; a news item loses any level, and a
	 * notice without one gets `info`.
	 *
	 * @param Announcement         $announcement The announcement.
	 * @param array<string, mixed> $input        The fields.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When a field is not valid.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function apply(Announcement $announcement, array $input): void {
		if (array_key_exists('kind', $input) === true) {
			if (in_array($input['kind'], [Announcement::KIND_NEWS, Announcement::KIND_NOTICE], true) === false) {
				throw new InvalidArgumentException('kind must be news or notice');
			}

			$announcement->setKind($input['kind']);
		}

		$this->applyTextFields(announcement: $announcement, input: $input);
		$this->applyNoticeFields(announcement: $announcement, input: $input);
		$this->applyTargetingFields(announcement: $announcement, input: $input);
	}//end apply()

	/**
	 * The title, text and category.
	 *
	 * @param Announcement         $announcement The announcement.
	 * @param array<string, mixed> $input        The fields.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the title is missing or too long, or the category is too long.
	 */
	private function applyTextFields(Announcement $announcement, array $input): void {
		if (array_key_exists('title', $input) === true) {
			$title = trim((string) $input['title']);
			if ($title === '' || mb_strlen($title) > 255) {
				throw new InvalidArgumentException('A title of 1 to 255 characters is required');
			}

			$announcement->setTitle($title);
		}

		if ($announcement->getTitle() === null) {
			throw new InvalidArgumentException('A title of 1 to 255 characters is required');
		}

		if (array_key_exists('body', $input) === true) {
			$body = null;
			if ($input['body'] !== null) {
				$body = (string) $input['body'];
			}

			$announcement->setBody($body);
		}

		$this->applyCategory(announcement: $announcement, input: $input);
	}//end applyTextFields()

	/**
	 * The category: empty clears it.
	 *
	 * @param Announcement         $announcement The announcement.
	 * @param array<string, mixed> $input        The fields.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the category is too long.
	 */
	private function applyCategory(Announcement $announcement, array $input): void {
		if (array_key_exists('category', $input) === true) {
			$category = null;
			if ($input['category'] !== null && trim((string) $input['category']) !== '') {
				$category = $this->category(value: (string) $input['category']);
			}

			$announcement->setCategory($category);
		}
	}//end applyCategory()

	/**
	 * The notice-only fields: level and dismissible.
	 *
	 * @param Announcement         $announcement The announcement.
	 * @param array<string, mixed> $input        The fields.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the level is not info or warning.
	 */
	private function applyNoticeFields(Announcement $announcement, array $input): void {
		if (array_key_exists('level', $input) === true) {
			if ($input['level'] !== null && in_array($input['level'], ['info', 'warning'], true) === false) {
				throw new InvalidArgumentException('level must be info or warning');
			}

			$announcement->setLevel($input['level']);
		}

		if ($announcement->getKind() === Announcement::KIND_NOTICE && $announcement->getLevel() === null) {
			$announcement->setLevel('info');
		}

		if ($announcement->getKind() === Announcement::KIND_NEWS) {
			$announcement->setLevel(null);
		}

		if (array_key_exists('dismissible', $input) === true) {
			$announcement->setDismissible((int) ($input['dismissible'] === true));
		}

		if (array_key_exists('allowComments', $input) === true) {
			$announcement->setAllowComments((int) ($input['allowComments'] === true));
		}
	}//end applyNoticeFields()

	/**
	 * Target groups and the time window.
	 *
	 * @param Announcement         $announcement The announcement.
	 * @param array<string, mixed> $input        The fields.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the groups are not a list of names or a time is not a date.
	 */
	private function applyTargetingFields(Announcement $announcement, array $input): void {
		if (array_key_exists('targetGroups', $input) === true) {
			$groups = $input['targetGroups'];
			if (is_array($groups) === false || array_is_list($groups) === false || array_filter($groups, 'is_string') !== $groups) {
				throw new InvalidArgumentException('targetGroups must be a list of group ids');
			}

			$announcement->setTargetGroupList(array_values(array_filter(array_map('trim', $groups), static fn (string $group): bool => $group !== '')));
		}

		foreach (['publishAt' => 'setPublishAt', 'expiresAt' => 'setExpiresAt'] as $field => $setter) {
			if (array_key_exists($field, $input) === true) {
				$announcement->$setter($this->date(value: $input[$field], field: $field));
			}
		}
	}//end applyTargetingFields()

	/**
	 * An ISO 8601 date, or null.
	 *
	 * @param mixed  $value The value.
	 * @param string $field The field, for the message.
	 *
	 * @return DateTime|null
	 *
	 * @throws InvalidArgumentException When the value is not a date.
	 */
	private function date(mixed $value, string $field): ?DateTime {
		if ($value === null || $value === '') {
			return null;
		}

		if (is_string($value) === false) {
			throw new InvalidArgumentException($field . ' must be an ISO 8601 date');
		}

		try {
			return new DateTime($value);
		} catch (Exception) {
			throw new InvalidArgumentException($field . ' must be an ISO 8601 date');
		}
	}//end date()

	/**
	 * A category name, trimmed.
	 *
	 * @param string $value The name.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When it is empty or too long.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	public function category(string $value): string {
		$category = trim($value);
		if ($category === '' || mb_strlen($category) > self::MAX_CATEGORY) {
			throw new InvalidArgumentException('A category of 1 to ' . self::MAX_CATEGORY . ' characters is required');
		}

		return $category;
	}//end category()
}//end class
