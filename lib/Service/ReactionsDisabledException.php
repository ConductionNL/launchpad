<?php

/**
 * ReactionsDisabledException
 *
 * Raised by {@see ReactionService::addReaction} when reactions are
 * globally or per-dashboard disabled. Maps to HTTP 403 in the
 * controller. REQ-RXN-005, REQ-RXN-006.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use RuntimeException;

/**
 * Reactions are disabled (globally or per-dashboard).
 */
class ReactionsDisabledException extends RuntimeException {
}//end class
