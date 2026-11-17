<?php

/**
 * DeliveryRefusedException
 *
 * A scheduled-export delivery that cannot succeed by trying again: no valid
 * address, a filter context the subject cannot satisfy, a file that exists
 * with overwrite off, a route that is not available. The dispatcher records
 * it as failed at once instead of spending retries on it.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-5
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use RuntimeException;

/**
 * A delivery failure that retries cannot fix.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-004-per-recipient-filter-context
 */
class DeliveryRefusedException extends RuntimeException {
}//end class
