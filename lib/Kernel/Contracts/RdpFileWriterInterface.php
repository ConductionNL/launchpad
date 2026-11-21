<?php

/**
 * Contract of RdpFileWriter
 *
 * The public surface of RdpFileWriter that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Kernel\Contracts;

/**
 * Contract of RdpFileWriter.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface RdpFileWriterInterface {
	/**
	 * The media type of an RDP connection file.
	 */
	public const MEDIA_TYPE = 'application/x-rdp';

	/**
	 * The contents of the file, one setting per line (CRLF, as Windows writes them).
	 *
	 * @param array{host: string, port: int, remoteApp: string, gateway: string} $connection The checked connection.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md#requirement-a-tile-can-start-a-remote-desktop-or-a-published-program-req-tlt-002
	 */
	public function contents(array $connection): string;

	/**
	 * The download name: the tile's title with characters a file name cannot hold left out.
	 *
	 * @param string|null $title The tile's title.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md#requirement-a-tile-can-start-a-remote-desktop-or-a-published-program-req-tlt-002
	 */
	public function fileName(?string $title): string;

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
	public function disposition(string $fileName): string;
}//end interface
