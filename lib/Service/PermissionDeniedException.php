<?php

/**
 * PermissionDeniedException
 *
 * Raised by {@see ReactionService} when the calling user does not have
 * VIEW permission on the dashboard targeted by the reaction request.
 * Maps to HTTP 403 in the controller.
 *
 * @category  Service
 * @package   OCA\MyDash\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\MyDash\Service;

use RuntimeException;

/**
 * Caller cannot VIEW the targeted dashboard.
 */
class PermissionDeniedException extends RuntimeException
{
}//end class
