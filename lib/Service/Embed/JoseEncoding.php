<?php

/**
 * JoseEncoding
 *
 * The byte encodings of a JWS (RFC 7515): unpadded base64url, and the
 * conversion between OpenSSL's DER ECDSA signatures and JOSE's fixed-length
 * R||S form (embedded-analytics task 3).
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

use OCA\LaunchPad\Exception\InvalidTokenException;

/**
 * JWS byte encodings, without state.
 *
 * @spec openspec/changes/embedded-analytics/tasks.md#task-3
 */
final class JoseEncoding {

	/**
	 * The length of one ES256 signature half (R or S), in bytes.
	 *
	 * @var integer
	 */
	public const ES256_PART = 32;

	/**
	 * Bytes in unpadded base64url.
	 *
	 * @param string $bytes The bytes.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-3
	 */
	public function base64Url(string $bytes): string {
		return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
	}//end base64Url()

	/**
	 * Unpadded base64url, decoded.
	 *
	 * @param string $segment The text.
	 *
	 * @return string
	 *
	 * @throws InvalidTokenException When it is not base64url.
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-3
	 */
	public function base64UrlDecode(string $segment): string {
		$bytes = base64_decode(strtr($segment, '-_', '+/'), true);
		if ($bytes === false || preg_match('/^[A-Za-z0-9_-]*$/', $segment) !== 1) {
			throw new InvalidTokenException(message: 'The token is not a signed JWT.');
		}

		return $bytes;
	}//end base64UrlDecode()

	/**
	 * An ECDSA DER signature as JOSE's R||S.
	 *
	 * @param string $der The DER signature.
	 *
	 * @return string The 64-byte signature.
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-3
	 */
	public function derToRaw(string $der): string {
		$offset = 2;
		if ((ord($der[1]) & 0x80) !== 0) {
			$offset += (ord($der[1]) & 0x7f);
		}

		$parts = [];
		for ($index = 0; $index < 2; $index++) {
			$length = ord($der[$offset + 1]);
			$value = substr($der, $offset + 2, $length);
			$parts[] = str_pad(ltrim($value, "\0"), self::ES256_PART, "\0", STR_PAD_LEFT);
			$offset += 2 + $length;
		}

		return implode('', $parts);
	}//end derToRaw()

	/**
	 * JOSE's R||S as an ECDSA DER signature.
	 *
	 * @param string $raw The 64-byte signature.
	 *
	 * @return string The DER signature.
	 *
	 * @throws InvalidTokenException When it is not 64 bytes.
	 *
	 * @spec openspec/changes/embedded-analytics/tasks.md#task-3
	 */
	public function rawToDer(string $raw): string {
		if (strlen($raw) !== 2 * self::ES256_PART) {
			throw new InvalidTokenException(message: 'The token signature does not verify.');
		}

		$body = '';
		foreach (str_split($raw, self::ES256_PART) as $part) {
			$part = ltrim($part, "\0");
			if ($part === '' || (ord($part[0]) & 0x80) !== 0) {
				$part = "\0" . $part;
			}

			$body .= "\x02" . chr(strlen($part)) . $part;
		}

		return "\x30" . chr(strlen($body)) . $body;
	}//end rawToDer()
}//end class
