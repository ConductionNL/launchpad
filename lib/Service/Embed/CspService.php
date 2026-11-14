<?php

/**
 * CspService
 *
 * The Content-Security-Policy of an embed: `frame-ancestors` from the token's
 * hostOrigins (REQ-EMB-003). An origin is a scheme and a host (and an optional
 * port), nothing else; a wildcard is refused at token creation, so an embed
 * can never be framed by any site.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\Embed
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

namespace OCA\LaunchPad\Service\Embed;

/**
 * Validates host origins and builds the frame-ancestors header.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-003--csp-frame-ancestors-enforced-from-token-hostorigins
 */
final class CspService {

	/**
	 * The header an embed answers with.
	 *
	 * @var string
	 */
	public const HEADER = 'Content-Security-Policy';

	/**
	 * The value of the CSP header for a token's host origins. Without a valid
	 * origin the embed may be framed by nobody (`'none'`).
	 *
	 * @param array<int, mixed> $hostOrigins The token's host origins.
	 *
	 * @return string The header value, e.g. `frame-ancestors https://www.zeist.nl`.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-003--csp-frame-ancestors-enforced-from-token-hostorigins
	 */
	public function generateCspHeader(array $hostOrigins): string {
		$valid = [];
		foreach ($hostOrigins as $origin) {
			if (is_string($origin) === true && $this->validateOriginFormat(origin: $origin) === true) {
				$valid[] = $this->normalise(origin: $origin);
			}
		}

		$valid = array_values(array_unique($valid));
		if ($valid === []) {
			return "frame-ancestors 'none'";
		}

		return 'frame-ancestors ' . implode(' ', $valid);
	}//end generateCspHeader()

	/**
	 * Why a list of host origins is refused, or null when it is accepted.
	 *
	 * @param mixed $origins The submitted host origins.
	 *
	 * @return array{error: string, message: string, details: array<string, string>}|null
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-003--csp-frame-ancestors-enforced-from-token-hostorigins
	 */
	public function validateOrigins(mixed $origins): ?array {
		if (is_array($origins) === false || $origins === []) {
			return $this->refusal(
				message: 'Specify at least one origin that may embed this content, e.g., ["https://www.example.com"]',
				reason: 'origin_required'
			);
		}

		foreach ($origins as $origin) {
			if (is_string($origin) === true && str_contains($origin, '*') === true) {
				return $this->refusal(
					message: 'Wildcard origin \'*\' is not permitted. Specify explicit origins, e.g., ["https://www.example.com"]',
					reason: 'wildcard_not_permitted'
				);
			}

			if (is_string($origin) === false || $this->validateOriginFormat(origin: $origin) === false) {
				return $this->refusal(
					message: 'An origin is a scheme and a host, e.g., https://www.example.com, without a path.',
					reason: 'invalid_origin'
				);
			}
		}

		return null;
	}//end validateOrigins()

	/**
	 * Whether one origin is `http(s)://host[:port]` (RFC 6454) with nothing else.
	 *
	 * @param string $origin The origin.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-003--csp-frame-ancestors-enforced-from-token-hostorigins
	 */
	public function validateOriginFormat(string $origin): bool {
		$origin = rtrim(trim($origin), '/');
		$pattern = '~^https?://(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?::[0-9]{1,5})?$~i';

		return preg_match($pattern, $origin) === 1;
	}//end validateOriginFormat()

	/**
	 * An origin as it goes into the header: lower case, no trailing slash.
	 *
	 * @param string $origin The origin.
	 *
	 * @return string
	 */
	private function normalise(string $origin): string {
		return strtolower(rtrim(trim($origin), '/'));
	}//end normalise()

	/**
	 * The validation refusal body.
	 *
	 * @param string $message The message.
	 * @param string $reason  The reason code for hostOrigins.
	 *
	 * @return array{error: string, message: string, details: array<string, string>}
	 */
	private function refusal(string $message, string $reason): array {
		return ['error' => 'validation_error', 'message' => $message, 'details' => ['hostOrigins' => $reason]];
	}//end refusal()

}//end class
