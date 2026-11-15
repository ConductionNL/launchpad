<?php

/**
 * EmbedRefusalException
 *
 * An embed request refused before or by the render route (embedded-analytics
 * tasks 6, 9): the status, the JSON body and any headers the answer carries.
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

use RuntimeException;

/**
 * A refusal with the exact answer the embed spec prescribes.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-002--public-render-route-validates-jwt-and-enforces-scope
 */
class EmbedRefusalException extends RuntimeException {

	/**
	 * A refusal.
	 *
	 * @param int                   $status  The HTTP status.
	 * @param array<string, mixed>  $body    The JSON body.
	 * @param array<string, string> $headers Extra response headers.
	 * @param string                $tokenId The token refused, when its signature proved it genuine; '' otherwise.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-002--public-render-route-validates-jwt-and-enforces-scope
	 */
	public function __construct(
		public readonly int $status,
		public readonly array $body,
		public readonly array $headers=[],
		public readonly string $tokenId='',
	) {
		parent::__construct(message: (string)($body['message'] ?? ($body['error'] ?? 'Refused')), code: $status);
	}//end __construct()

}//end class
