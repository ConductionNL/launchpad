<?php

/**
 * Contract of ResourceServeService
 *
 * The public surface of ResourceServeService that other components call, so they depend
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

use OCP\Files\SimpleFS\ISimpleFile;

/**
 * Contract of ResourceServeService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface ResourceServeServiceInterface {
	/**
	 * Resolve a filename to an ISimpleFile, or null on miss.
	 *
	 * @param string $filename The leaf filename.
	 *
	 * @return ISimpleFile|null The file, or null if absent / unreadable.
	 *
	 * @spec openspec/changes/resource-serving/tasks.md#task-1
	 */
	public function findFile(string $filename): ?ISimpleFile;

	/**
	 * Load the resources directory listing as ISimpleFile entries.
	 *
	 * Returns an empty array when the folder does not yet exist —
	 * matching REQ-RES-007's "never a 404" contract.
	 *
	 * @return array<int, ISimpleFile> The file entries.
	 *
	 * @spec openspec/changes/resource-serving/tasks.md#task-3
	 */
	public function listFiles(): array;

	/**
	 * Pick the Content-Type for a filename via its extension.
	 *
	 * Falls back to `application/octet-stream` for unknown extensions.
	 *
	 * @param string $filename The leaf filename.
	 *
	 * @return string The MIME type to send.
	 *
	 * @spec openspec/changes/resource-serving/tasks.md#task-1
	 */
	public function contentTypeForFilename(string $filename): string;

	/**
	 * Format a Unix epoch as an ISO-8601 UTC timestamp.
	 *
	 * @param int $epoch The Unix epoch (e.g. from ISimpleFile::getMTime()).
	 *
	 * @return string The ISO-8601 timestamp.
	 *
	 * @spec openspec/changes/resource-serving/tasks.md#task-3
	 */
	public function formatTimestamp(int $epoch): string;
}//end interface
