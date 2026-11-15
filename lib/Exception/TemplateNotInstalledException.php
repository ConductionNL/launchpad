<?php

/**
 * TemplateNotInstalledException
 *
 * Thrown when an update is asked for a shipped template that is not installed
 * on this instance. There is nothing to update then; the caller says so and
 * points at the install.
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
 *
 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Exception;

use RuntimeException;

/**
 * A shipped template that is not installed cannot be updated.
 *
 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
 */
class TemplateNotInstalledException extends RuntimeException {
}//end class
