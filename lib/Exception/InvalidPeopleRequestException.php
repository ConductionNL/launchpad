<?php

/**
 * InvalidPeopleRequestException
 *
 * Raised when a people-directory request asks for a window or search the
 * endpoint does not serve (limit out of range, negative offset, a search
 * text that is too short). The message is written for the caller, so the
 * API answers it as the 400 error.
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
 * @spec openspec/specs/people-widget/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Exception;

/**
 * A people-directory request parameter the endpoint refuses.
 *
 * @spec openspec/specs/people-widget/spec.md
 */
class InvalidPeopleRequestException extends \InvalidArgumentException {
}//end class
