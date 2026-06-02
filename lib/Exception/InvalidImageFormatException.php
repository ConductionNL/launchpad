<?php

/**
 * InvalidImageFormatException
 *
 * Raised when the data URL declares a type that is not in the
 * allowed list (jpeg, jpg, png, gif, svg, webp). Maps to HTTP 400 +
 * error code `invalid_image_format`.
 *
 * @category  Exception
 * @package   OCA\MyDash\Exception
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/resource-uploads/tasks.md#task-2
 * @spec openspec/changes/resource-uploads/tasks.md#task-6
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\MyDash\Exception;

/**
 * Declared image type is not in the allowed list.
 *
 * @spec openspec/changes/resource-uploads/tasks.md#task-2
 */
class InvalidImageFormatException extends ResourceException
{

    /**
     * Stable error code.
     *
     * @var string
     */
    protected string $errorCode = 'invalid_image_format';

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
    public function __construct(
        string $message='Allowed image formats: jpeg, jpg, png, gif, svg, webp'
    ) {
        parent::__construct(message: $message);
    }//end __construct()
}//end class
