<?php

/**
 * Contract of ConfluenceImportService
 *
 * The public surface of ConfluenceImportService that other components call, so they depend
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
 * Contract of ConfluenceImportService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface ConfluenceImportServiceInterface {
	/**
	 * Inspect a Confluence archive without committing anything.
	 *
	 * REQ-CFLI-007: returns the page count, attachment count, parsing
	 * warnings, and the asset-folder timestamp the importer would use
	 * if invoked for real.
	 *
	 * @param string $zipPath Path to the uploaded ZIP.
	 *
	 * @return array{
	 *     pageCount:int,
	 *     attachmentCount:int,
	 *     estimatedDashboards:int,
	 *     warnings:array<int,string>,
	 *     assetFolder:string
	 * }
	 *
	 * @spec openspec/specs/confluence-html-import/spec.md
	 */
	public function dryRun(string $zipPath): array;

	/**
	 * Import a Confluence HTML export into LaunchPad dashboards.
	 *
	 * @param string $zipPath Path to the uploaded ZIP.
	 * @param string $currentUserId Importing user UID.
	 * @param string|null $parentUuid Optional parent dashboard UUID
	 *                                (REQ-CFLI-011) — root pages are
	 *                                slotted under this dashboard.
	 *
	 * @return array{
	 *     createdDashboardCount:int,
	 *     skippedPageCount:int,
	 *     errors:array<int, array{pageId:string, reason:string}>,
	 *     warnings:array<int,string>,
	 *     assetFolder:string
	 * }
	 *
	 * @spec openspec/specs/confluence-html-import/spec.md
	 */
	public function import(
		string $zipPath,
		string $currentUserId,
		?string $parentUuid = null,
	): array;
}//end interface
