<?php

/**
 * PublisherTools
 *
 * The debounce helper and the logger of the activity publisher.
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

use Psr\Log\LoggerInterface;

/**
 * The debounce helper and the logger of the activity publisher.
 *
 * @spec openspec/specs/activity-feed-integration/spec.md
 */
class PublisherTools {
	/**
	 * Constructor.
	 *
	 * @param DebounceHelper  $debounce The debounce guard.
	 * @param LoggerInterface $logger The logger.
	 */
	public function __construct(
		public readonly DebounceHelper $debounce,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
