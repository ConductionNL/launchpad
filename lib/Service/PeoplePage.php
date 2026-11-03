<?php

/**
 * PeoplePage
 *
 * The window of one People widget page: which users count and which
 * fields show (REQ-PPL-003, REQ-PPL-005).
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
 * Whether disabled users are left out, whether birthdates show, the page
 * size and the page offset of one People widget request.
 *
 * @spec openspec/specs/people-widget/spec.md
 */
final class PeoplePage {
	/**
	 * Constructor
	 *
	 * @param bool $excludeDisabled True leaves disabled users out.
	 * @param bool $showBirthdays   False strips the birthdate field.
	 * @param int  $limit           Page size (1..MAX_LIMIT).
	 * @param int  $offset          Page offset (>= 0).
	 */
	public function __construct(
		public readonly bool $excludeDisabled,
		public readonly bool $showBirthdays,
		public readonly int $limit,
		public readonly int $offset,
	) {
	}//end __construct()
}//end class
