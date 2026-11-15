<?php

/**
 * TreeLevel
 *
 * One level of a dashboard tree walk: the parent whose children are read, and how deep it sits.
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

use OCA\LaunchPad\Db\Dashboard;

/**
 * The parent UUID and depth of one level of a tree walk.
 *
 * @spec openspec/specs/dashboard-switcher/spec.md
 */
final class TreeLevel {
	/**
	 * Constructor.
	 *
	 * @param string|null $parentUuid The parent UUID (null for roots).
	 * @param int         $depth      The depth of the children read at this level.
	 */
	public function __construct(
		public readonly string|null $parentUuid,
		public readonly int $depth,
	) {
	}//end __construct()

	/**
	 * Whether this level is past the depth cap and reads nothing.
	 *
	 * @return bool True at or beyond Dashboard::MAX_DEPTH.
	 *
	 * @spec openspec/specs/dashboard-switcher/spec.md
	 */
	public function isBeyondCap(): bool {
		return $this->depth >= Dashboard::MAX_DEPTH;
	}//end isBeyondCap()

	/**
	 * The level below a child of this level.
	 *
	 * @param string $childUuid The child UUID.
	 *
	 * @return self The child's level.
	 *
	 * @spec openspec/specs/dashboard-switcher/spec.md
	 */
	public function below(string $childUuid): self {
		return new self(parentUuid: $childUuid, depth: ($this->depth + 1));
	}//end below()
}//end class
