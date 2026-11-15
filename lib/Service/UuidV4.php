<?php

/**
 * UuidV4
 *
 * Random (version 4) UUIDs for new dashboards, copies and keys.
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
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * Mints RFC 4122 version 4 UUIDs: 16 random bytes with the version nibble
 * set to 4 and the variant bits to 10, rendered as lower-case hex in
 * 8-4-4-4-12 groups.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
final class UuidV4 {
	/**
	 * Mint one UUID.
	 *
	 * @return string The UUID.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public static function generate(): string {
		$data = random_bytes(length: 16);
		$data[6] = chr(codepoint: ord(character: $data[6]) & 0x0f | 0x40);
		$data[8] = chr(codepoint: ord(character: $data[8]) & 0x3f | 0x80);

		return vsprintf(
			format: '%s%s-%s-%s-%s-%s%s%s',
			values: str_split(string: bin2hex(string: $data), length: 4)
		);
	}//end generate()
}//end class
