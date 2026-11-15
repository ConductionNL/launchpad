<?php

/**
 * ActivitySubject
 *
 * The dashboard an activity row is about: its UUID, its name and the
 * deep link the row opens.
 *
 * @category  Activity
 * @package   OCA\LaunchPad\Activity
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/activity-feed-integration/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Activity;

/**
 * The dashboard an activity row points at.
 */
class ActivitySubject {
	/**
	 * Constructor.
	 *
	 * @param string $uuid The dashboard UUID.
	 * @param string $name The human-readable dashboard name.
	 * @param string $link The absolute deep-link URL, or '' for none.
	 */
	public function __construct(
		public readonly string $uuid,
		public readonly string $name,
		public readonly string $link='',
	) {
	}//end __construct()
}//end class
