<?php

/**
 * ShowcaseNotFoundException
 *
 * Thrown when a demo showcase ID cannot be resolved against the bundled
 * archives on disk. Mapped by the controller layer to HTTP 404 per
 * REQ-DEMO-003.
 *
 * @category  Exception
 * @package   OCA\LaunchPad\Exception
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

namespace OCA\LaunchPad\Exception;

use RuntimeException;

/**
 * Raised when a showcase ID is unknown.
 */
class ShowcaseNotFoundException extends RuntimeException {
}//end class
