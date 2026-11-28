<?php

/**
 * PublicShareRules
 *
 * The rules the public share API applies besides the share service: the
 * widget visibility rules on the public render, and the public-link policy
 * on create (REQ-SHARE-015).
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Kernel\Contracts\ConditionalServiceInterface;
use OCA\LaunchPad\Service\SharingLimits;

/**
 * The visibility rules and the public-link policy of the public share API.
 *
 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-can-stop-sharing-and-limit-public-links-req-share-015
 */
class PublicShareRules {
	/**
	 * Constructor.
	 *
	 * @param ConditionalServiceInterface|null $visibility Applies the widget visibility rules.
	 * @param SharingLimits|null               $limits     Whether public links may be created, by whom, and that they expire.
	 *
	 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-can-stop-sharing-and-limit-public-links-req-share-015
	 */
	public function __construct(
		public readonly ?ConditionalServiceInterface $visibility = null,
		public readonly ?SharingLimits $limits = null,
	) {
	}//end __construct()
}//end class
