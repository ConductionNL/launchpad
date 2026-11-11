<?php

/**
 * ConfluenceImportRun
 *
 * The state of one Confluence import: who imports, under which root
 * parent, and the tally of created and skipped pages. Built and used only
 * by {@see ConfluenceImportService::import()}.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Service\Confluence\ParsedPage;
use Throwable;

/**
 * Tally of one Confluence import.
 *
 * @spec openspec/specs/confluence-html-import/spec.md
 */
final class ConfluenceImportRun {

	/**
	 * Lower-cased pageId to the created dashboard UUID.
	 *
	 * @var array<string, string>
	 */
	public array $pageIdToUuid = [];

	/**
	 * PageId to the created page.
	 *
	 * @var array<string, ParsedPage>
	 */
	public array $createdPages = [];

	/**
	 * Number of dashboards created.
	 *
	 * @var integer
	 */
	public int $createdCount = 0;

	/**
	 * Number of pages skipped.
	 *
	 * @var integer
	 */
	public int $skippedCount = 0;

	/**
	 * One entry per skipped page.
	 *
	 * @var array<int, array{pageId:string, reason:string}>
	 */
	public array $errors = [];

	/**
	 * Constructor.
	 *
	 * @param string      $currentUserId  Importing user UID.
	 * @param string|null $rootParentUuid Dashboard UUID root pages are slotted under, or null.
	 */
	public function __construct(
		public readonly string $currentUserId,
		public readonly ?string $rootParentUuid,
	) {
	}//end __construct()

	/**
	 * Record a page that became a dashboard.
	 *
	 * @param ParsedPage $page          The page.
	 * @param string     $dashboardUuid The new dashboard UUID.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/confluence-html-import/spec.md
	 */
	public function recordCreated(ParsedPage $page, string $dashboardUuid): void {
		$this->pageIdToUuid[strtolower(string: $page->pageId)] = $dashboardUuid;
		$this->createdPages[$page->pageId] = $page;
		$this->createdCount++;
	}//end recordCreated()

	/**
	 * Record a page that could not be imported.
	 *
	 * @param ParsedPage $page   The page.
	 * @param Throwable  $reason Why it failed.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/confluence-html-import/spec.md
	 */
	public function recordSkipped(ParsedPage $page, Throwable $reason): void {
		$this->skippedCount++;
		$this->errors[] = [
			'pageId' => $page->pageId,
			'reason' => $reason->getMessage(),
		];
	}//end recordSkipped()

	/**
	 * The import result.
	 *
	 * @param array<int, string> $warnings    Archive and link-rewrite warnings.
	 * @param string             $assetFolder The asset folder path.
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
	public function result(array $warnings, string $assetFolder): array {
		return [
			'createdDashboardCount' => $this->createdCount,
			'skippedPageCount' => $this->skippedCount,
			'errors' => $this->errors,
			'warnings' => $warnings,
			'assetFolder' => $assetFolder,
		];
	}//end result()
}//end class
