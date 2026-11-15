<?php

/**
 * TokenExpiredException
 *
 * An embed token whose expiry has passed, or that is not valid yet.
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

/**
 * The token is outside its validity period.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-002--jwt-validation-on-every-embed-request
 */
class TokenExpiredException extends EmbedTokenException {
}//end class
