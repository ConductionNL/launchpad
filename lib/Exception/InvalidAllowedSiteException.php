<?php

/**
 * InvalidAllowedSiteException
 *
 * Raised when a submitted allowed websites list holds an entry that is not a
 * host, or a host without a purpose (REQ-ASET-021). Maps to HTTP 400.
 *
 * @category  Exception
 * @package   OCA\LaunchPad\Exception
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

namespace OCA\LaunchPad\Exception;

use InvalidArgumentException;

/**
 * An allowed websites entry that cannot be stored.
 *
 * @spec openspec/changes/allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
 */
class InvalidAllowedSiteException extends InvalidArgumentException {
}//end class
