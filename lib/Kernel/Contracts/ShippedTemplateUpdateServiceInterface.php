<?php

/**
 * Contract of ShippedTemplateUpdateService
 *
 * The public surface of ShippedTemplateUpdateService that other components call, so they depend
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
 * Contract of ShippedTemplateUpdateService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface ShippedTemplateUpdateServiceInterface {
	/**
	 * Update the installed template to the shipped version, or say what
	 * that would change.
	 *
	 * Nothing happens when the installed version is the shipped one or
	 * newer: the result says so (`upToDate`) and its lists are empty.
	 *
	 * @param string $templateId The shipped template id.
	 * @param bool   $dryRun     Report only, write nothing.
	 * @param string $userId     Who runs the update (for the re-sync's audit record).
	 *
	 * @return array{templateId:string, uuid:string, id:int, name:string, installedVersion:int, version:int,
	 *               upToDate:bool, dryRun:bool, applied:bool, added:array<int,string>, removed:array<int,string>,
	 *               changed:array<int,array{widget:string, fields:array<int,string>}>, unchanged:int, copies:int,
	 *               resync:array{async:bool, affectedCount:int, totalCopies:int}|null}
	 *
	 * @throws \InvalidArgumentException When the id is not a shipped template.
	 * @throws \OCA\LaunchPad\Exception\TemplateNotInstalledException When the template is not installed here.
	 * @throws \RuntimeException When the definition is malformed or the write fails.
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
	 *      The flag is the command's `--dry-run` and the request's `dryRun`, one to one.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
	 */
	public function update(string $templateId, bool $dryRun = false, string $userId = 'cli'): array;
}//end interface
