<?php

/**
 * RdpFileBuilder
 *
 * Writes a remote desktop connection file from a tile's checked connection
 * settings (launcher-tile-launch-types, REQ-TLT-002). It writes only the
 * address, port, published program and gateway, never a user name, domain or
 * password: signing in is the gateway's and the identity provider's work.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * Remote desktop connection files.
 *
 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
 */
class RdpFileBuilder {
	/**
	 * The port a connection uses when the tile names none.
	 *
	 * @var int
	 */
	public const DEFAULT_PORT = 3389;

	/**
	 * The file's content for a connection that TileLaunchValidator accepted.
	 *
	 * @param array $remote The tile's `content.remote` in `rdp` mode.
	 *
	 * @return string Lines separated by CRLF.
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	public function build(array $remote): string {
		$port = (int)($remote['port'] ?? 0);
		if ($port === 0) {
			$port = self::DEFAULT_PORT;
		}

		$lines = [
			'full address:s:' . $remote['host'] . ':' . $port,
			'use redirection server name:i:1',
		];

		$program = (string)($remote['remoteApp'] ?? '');
		if ($program !== '') {
			$lines[] = 'remoteapplicationmode:i:1';
			$lines[] = 'remoteapplicationprogram:s:' . $program;
			$lines[] = 'remoteapplicationname:s:' . ltrim(string: $program, characters: '|');
		}

		$gateway = (string)($remote['gateway'] ?? '');
		if ($gateway !== '') {
			$lines[] = 'gatewayhostname:s:' . $gateway;
			$lines[] = 'gatewayusagemethod:i:1';
			$lines[] = 'gatewayprofileusagemethod:i:1';
		}

		return implode(separator: "\r\n", array: $lines) . "\r\n";
	}//end build()

	/**
	 * A file name from the tile's title, with characters that do not belong
	 * in a file name left out.
	 *
	 * @param string|null $title The tile's title.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md
	 */
	public function fileName(?string $title): string {
		$name = trim(string: (string)preg_replace(pattern: '/[^\p{L}\p{N} ._()-]+/u', replacement: '', subject: (string)$title));
		if ($name === '') {
			$name = 'remote-desktop';
		}

		return mb_substr(string: $name, start: 0, length: 100) . '.rdp';
	}//end fileName()
}//end class
