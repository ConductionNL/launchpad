<?php

/**
 * RdpFileWriter
 *
 * Writes the `.rdp` file of a remote-desktop tile from its checked
 * connection: host, port, an optional published program and an optional
 * gateway. No user name, password or other credential field is ever written.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-3
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * Builds an RDP connection file and its download name (design D3).
 *
 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-3
 */
class RdpFileWriter {

	/**
	 * The media type of an RDP connection file.
	 */
	public const MEDIA_TYPE = 'application/x-rdp';

	/**
	 * The file name when the tile has no usable title.
	 */
	private const FALLBACK_NAME = 'remote-desktop';

	/**
	 * The contents of the file, one setting per line (CRLF, as Windows writes them).
	 *
	 * @param array{host: string, port: int, remoteApp: string, gateway: string} $connection The checked connection.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md#requirement-a-tile-can-start-a-remote-desktop-or-a-published-program-req-tlt-002
	 */
	public function contents(array $connection): string {
		$lines = [
			'full address:s:' . $connection['host'],
			'server port:i:' . $connection['port'],
		];
		if ($connection['remoteApp'] !== '') {
			$lines[] = 'remoteapplicationmode:i:1';
			$lines[] = 'remoteapplicationprogram:s:' . $connection['remoteApp'];
		}

		if ($connection['gateway'] !== '') {
			$lines[] = 'gatewayhostname:s:' . $connection['gateway'];
			$lines[] = 'gatewayusagemethod:i:1';
		}

		$lines[] = 'use redirection server name:i:1';
		return implode("\r\n", $lines) . "\r\n";
	}//end contents()

	/**
	 * The download name: the tile's title with characters a file name cannot hold left out.
	 *
	 * @param string|null $title The tile's title.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md#requirement-a-tile-can-start-a-remote-desktop-or-a-published-program-req-tlt-002
	 */
	public function fileName(?string $title): string {
		$name = trim((string)preg_replace('/[\x00-\x1f\x7f\/\\\\:*?"<>|]+/', '', (string)$title), " .");
		if ($name === '') {
			$name = self::FALLBACK_NAME;
		}

		return mb_substr($name, 0, 120) . '.rdp';
	}//end fileName()

	/**
	 * The Content-Disposition header that saves the file under its name.
	 *
	 * The plain `filename` holds an ASCII copy for old clients; `filename*`
	 * holds the name itself, percent-encoded (RFC 6266).
	 *
	 * @param string $fileName A name from fileName().
	 *
	 * @return string
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md#requirement-a-tile-can-start-a-remote-desktop-or-a-published-program-req-tlt-002
	 */
	public function disposition(string $fileName): string {
		$ascii = (string)preg_replace('/[^\x20-\x7e]|["\\%]/', '_', $fileName);
		return 'attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($fileName);
	}//end disposition()
}//end class
