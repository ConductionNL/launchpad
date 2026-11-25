<?php

/**
 * LaunchPad New Colleagues.
 *
 * The people whose start date (the profile field marked as the start date,
 * REQ-PEX-005) lies in a window around today, newest start first
 * (REQ-PPL-014): the "New colleagues" selection of the people widget.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
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
 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\ProfileValueMapper;
use OCP\IUserManager;

/**
 * Who started recently or starts soon.
 *
 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
 */
class NewColleagues {

	/**
	 * Days back by default, and the allowed range.
	 */
	public const SINCE_DEFAULT = 30;
	public const SINCE_MIN = 1;
	public const SINCE_MAX = 90;

	/**
	 * Days ahead by default, and the allowed range.
	 */
	public const UNTIL_DEFAULT = 14;
	public const UNTIL_MIN = 0;
	public const UNTIL_MAX = 30;

	/**
	 * Constructor.
	 *
	 * @param ProfileFieldService $fields The field definitions and who may see a value.
	 * @param ProfileValueMapper  $values The stored values.
	 * @param IUserManager        $users  The accounts.
	 * @param PeopleWidgetService|null $people The people widget's profile (avatar, role, organisation), or none.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly ProfileFieldService $fields,
		private readonly ProfileValueMapper $values,
		private readonly IUserManager $users,
		private readonly ?PeopleWidgetService $people = null,
	) {
	}//end __construct()

	/**
	 * The new colleagues the viewer may see, newest start first. Without a
	 * start-date field `configured` is false and nobody is listed.
	 *
	 * @param string|null $viewerId The viewer.
	 * @param array{since?: int, until?: int, today: string} $window Days back, days ahead and today (`YYYY-MM-DD`).
	 *
	 * @return array{configured: bool, since: int, until: int, users: list<array{id: string, displayName: string, startDate: string, starts: bool}>}
	 *
	 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
	 */
	public function listNew(?string $viewerId, array $window): array {
		$since = min(self::SINCE_MAX, max(self::SINCE_MIN, (int)($window['since'] ?? self::SINCE_DEFAULT)));
		$until = min(self::UNTIL_MAX, max(self::UNTIL_MIN, (int)($window['until'] ?? self::UNTIL_DEFAULT)));
		$answer = ['configured' => false, 'since' => $since, 'until' => $until, 'users' => []];

		$definition = $this->startDateField();
		if ($definition === null) {
			return $answer;
		}

		$answer['configured'] = true;
		$today = strtotime(datetime: $window['today'].' 00:00:00 UTC');
		$range = [
			'from' => gmdate(format: 'Y-m-d', timestamp: $today - ($since * 86400)),
			'to'   => gmdate(format: 'Y-m-d', timestamp: $today + ($until * 86400)),
		];
		foreach ($this->values->findDatesBetween(fieldKey: (string)$definition['key'], range: $range) as $row) {
			// REQ-PEX-004: the value's own scope as well as the field's visibility.
			if ($this->fields->scopeAllows(scope: $row->getScope(), viewerId: $viewerId, ownerId: $row->getUserId()) === false) {
				continue;
			}

			$entry = $this->entryFor(viewerId: $viewerId, ownerId: $row->getUserId(), definition: $definition);
			if ($entry !== null) {
				$answer['users'][] = $entry + ['startDate' => $row->getValue(), 'starts' => $row->getValue() > $window['today']];
			}
		}

		return $answer;
	}//end listNew()

	/**
	 * The field marked as the start date, or null.
	 *
	 * @return array<string, mixed>|null The definition.
	 *
	 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
	 */
	private function startDateField(): ?array {
		foreach ($this->fields->getDefinitions() as $definition) {
			if (($definition['startDate'] ?? false) === true) {
				return $definition;
			}
		}

		return null;
	}//end startDateField()

	/**
	 * One person, when their account is enabled and the viewer may see the field.
	 *
	 * @param string|null          $viewerId   The viewer.
	 * @param string               $ownerId    The person.
	 * @param array<string, mixed> $definition The start-date field.
	 *
	 * @return array{id: string, displayName: string, avatarUrl?: string, role?: string, organisation?: string}|null
	 *
	 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
	 */
	private function entryFor(?string $viewerId, string $ownerId, array $definition): ?array {
		if ($this->fields->canSee(viewerId: $viewerId, ownerId: $ownerId, definition: $definition) === false) {
			return null;
		}

		$user = $this->users->get(uid: $ownerId);
		if ($user === null || $user->isEnabled() === false) {
			return null;
		}

		$entry = ['id' => $ownerId, 'displayName' => $user->getDisplayName()];
		if ($this->people === null) {
			return $entry;
		}

		// The list shows photo, role and team (REQ-PPL-014), read as the people widget reads them.
		$profile = $this->people->buildUserProfile(user: $user, showBirthdays: false);
		foreach (['avatarUrl', 'role', 'organisation'] as $key) {
			if (is_string(value: ($profile[$key] ?? null)) === true && $profile[$key] !== '') {
				$entry[$key] = $profile[$key];
			}
		}

		return $entry;
	}//end entryFor()

}//end class
