<?php

/**
 * InvalidMetadataFieldException
 *
 * Raised when a metadata-field write fails validation: malformed key,
 * missing required value, value outside the configured option set,
 * type-mismatched value, key-rename attempt, etc. (REQ-MDFL-001,
 * REQ-MDFL-002, REQ-MDFL-005, REQ-MDFL-006). Maps to HTTP 400.
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

use Exception;

/**
 * Validation failure on the dashboard-metadata-fields capability.
 */
class InvalidMetadataFieldException extends Exception {
	/**
	 * Stable error code returned in the response envelope.
	 *
	 * @var string
	 */
	public const ERROR_CODE = 'invalid_metadata_field';
}//end class
