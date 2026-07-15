<?php

/**
 * InvalidFilenameException
 *
 * Raised when the supplied filename for `POST /api/files/create` fails the
 * strict regex validation (REQ-LBN-004): empty, too long, contains `..`,
 * `/`, `\`, null byte, or fails the `^[a-zA-Z0-9_\-. ]+$` pattern. Maps
 * to HTTP 400 + error code `invalid_filename`.
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

/**
 * Filename failed strict validation (empty, too long, or disallowed characters).
 */
class InvalidFilenameException extends ResourceException
{

    /**
     * Stable error code.
     *
     * @var string
     */
    protected string $errorCode = 'invalid_filename';

    /**
     * HTTP status.
     *
     * @var integer
     */
    protected int $httpStatus = 400;

    /**
     * Constructor.
     *
     * @param string $message Display message.
     */
    public function __construct(string $message='Invalid filename')
    {
        parent::__construct(message: $message);
    }//end __construct()
}//end class
