<?php

/**
 * InvalidWidgetContentException
 *
 * Raised when a widget's content blob breaks its type's save-time rules
 * (menu depth and fields, map sources). The message is written for the
 * person saving the widget, so the API answers it as the 400 error.
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
 * @spec openspec/specs/menu-widget/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Exception;

/**
 * A widget content blob that fails its type's validation.
 *
 * @spec openspec/specs/menu-widget/spec.md
 */
class InvalidWidgetContentException extends \InvalidArgumentException {
}//end class
