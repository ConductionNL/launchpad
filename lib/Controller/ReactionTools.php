<?php

/**
 * ReactionTools
 *
 * The reaction service and the logger of the reaction API.
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
 * @spec openspec/specs/dashboard-reactions/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\ReactionService;
use Psr\Log\LoggerInterface;

/**
 * The reaction service and the logger of the reaction API.
 *
 * @spec openspec/specs/dashboard-reactions/spec.md
 */
class ReactionTools {
	/**
	 * Constructor.
	 *
	 * @param ReactionService $reactionService The reaction service.
	 * @param LoggerInterface $logger PSR logger.
	 */
	public function __construct(
		public readonly ReactionService $reactionService,
		public readonly LoggerInterface $logger,
	) {
	}//end __construct()
}//end class
