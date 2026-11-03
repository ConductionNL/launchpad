<?php

/**
 * PeopleQuery
 *
 * One People widget request: the placement filters, the sort, the search
 * text and the page window (REQ-PPL-002, REQ-PPL-003, REQ-PEX-003).
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
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * Filters, sort, search text and page window of a People widget request:
 * whether disabled users are left out, whether birthdates show, the page
 * size and the offset. PeopleWidgetService validates them before it reads
 * the directory.
 *
 * @spec openspec/specs/people-widget/spec.md
 */
final class PeopleQuery {
	/**
	 * Constructor
	 *
	 * @param array       $filters         Filter predicates `{fieldName, operator, values}`.
	 * @param string      $sortBy          One of `displayName`, `group` or `recent-activity`.
	 * @param string|null $search          Search text (2 or more characters), or null.
	 * @param bool        $excludeDisabled True leaves disabled users out.
	 * @param bool        $showBirthdays   False strips the birthdate field.
	 * @param int         $limit           Page size (1..MAX_LIMIT).
	 * @param int         $offset          Page offset (>= 0).
	 */
	public function __construct(
		public readonly array $filters,
		public readonly string $sortBy,
		public readonly ?string $search,
		public readonly bool $excludeDisabled,
		public readonly bool $showBirthdays,
		public readonly int $limit,
		public readonly int $offset,
	) {
	}//end __construct()
}//end class
