<?php

/**
 * Contract of AnnouncementFollowNotifier
 *
 * The public surface of AnnouncementFollowNotifier that other components call, so they depend
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
 * Contract of AnnouncementFollowNotifier.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface AnnouncementFollowNotifierInterface {
	/**
	 * Notify every live announcement that has not been notified yet.
	 *
	 * @return int How many notifications were sent.
	 *
	 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-readers-follow-categories-req-ann-003
	 */
	public function notifyDue(): int;
}//end interface
