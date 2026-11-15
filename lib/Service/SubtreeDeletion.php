<?php

/**
 * SubtreeDeletion
 *
 * One subtree delete: the dashboard the delete started from and the shared deletion time.
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

use DateTimeImmutable;
use OCA\LaunchPad\Db\Dashboard;

/**
 * The root and deletion time of one subtree delete.
 *
 * @spec openspec/specs/dashboard-switcher/spec.md
 */
final class SubtreeDeletion {
	/**
	 * Constructor.
	 *
	 * @param Dashboard         $root      The dashboard the delete started from; its owner and type
	 *                                     fill in for descendants that carry none.
	 * @param DateTimeImmutable $deletedAt The deletion time stamped on every event.
	 */
	public function __construct(
		public readonly Dashboard $root,
		public readonly DateTimeImmutable $deletedAt,
	) {
	}//end __construct()
}//end class
