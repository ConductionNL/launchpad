<?php

/**
 * ConfluenceImportService
 *
 * Top-level orchestrator for the `confluence-html-import` capability
 * (REQ-CFLI-001..012). Reads a Confluence HTML export ZIP via
 * {@see ArchiveParser}, sanitises page bodies, rewrites internal links,
 * and creates one LaunchPad dashboard per page (with a single full-width
 * text-display widget) preserving the source page hierarchy via the
 * existing `dashboard-tree` capability. Creation runs per-page in its
 * own transaction so a single bad page never aborts the whole batch
 * (REQ-CFLI-009).
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

use DateTime;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Service\Confluence\ArchiveParser;
use OCA\LaunchPad\Service\Confluence\ParsedPage;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Orchestrates Confluence HTML export → LaunchPad dashboard imports.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 *      Top-level orchestrator must collaborate with parser, sanitiser,
 *      macro renderer, link rewriter, factory, and two mappers.
 */
class ConfluenceImportService {

	/**
	 * Default text-display widget identifier (matches the built-in
	 * registration in `WidgetService`).
	 *
	 * @var string
	 */
	private const TEXT_WIDGET_ID = 'text';

	/**
	 * Default grid width for the imported text widget.
	 *
	 * @var integer
	 */
	private const WIDGET_GRID_WIDTH = 12;

	/**
	 * Default grid height for the imported text widget.
	 *
	 * @var integer
	 */
	private const WIDGET_GRID_HEIGHT = 12;

	/**
	 * Constructor.
	 *
	 * @param ConfluencePageReaders      $readers The archive parser, HTML sanitizer, macro renderer and link rewriter.
	 * @param ConfluenceDashboardWriters $writers The dashboard factory, tree service and dashboard and
	 *                                            placement mappers.
	 * @param IDBConnection              $db DB connection.
	 * @param LoggerInterface            $logger PSR-3 logger. @SuppressWarnings(PHPMD.ExcessiveParameterList)
	 *                                           Top-level orchestrator must wire every collaborator.
	 *
	 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
	 */
	public function __construct(
		private readonly ConfluencePageReaders $readers,
		private readonly ConfluenceDashboardWriters $writers,
		private readonly IDBConnection $db,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

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
	public function dryRun(string $zipPath): array {
		$archive = $this->readers->parser->parse(zipPath: $zipPath);
		$timestamp = $this->buildTimestamp();

		return [
			'pageCount' => $archive->pageCount(),
			'attachmentCount' => $archive->attachmentCount(),
			'estimatedDashboards' => $archive->pageCount(),
			'warnings' => $archive->warnings,
			'assetFolder' => 'LaunchPad/Imports/' . $timestamp,
		];
	}//end dryRun()

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
	): array {
		$archive = $this->readers->parser->parse(zipPath: $zipPath);
		$timestamp = $this->buildTimestamp();

		$run = new ConfluenceImportRun(
			currentUserId: $currentUserId,
			rootParentUuid: $this->existingRootParent(parentUuid: $parentUuid)
		);

		$ordered = $this->orderParentsBeforeChildren(pages: $archive->pages);
		$archiveWarnings = $archive->warnings;
		foreach ($ordered as $page) {
			$this->importPage(page: $page, run: $run);
		}

		$warnings = array_merge(
			$archiveWarnings,
			$this->backfillWidgetContent(
				pages: $run->createdPages,
				pageIdToUuid: $run->pageIdToUuid
			)
		);

		return $run->result(warnings: $warnings, assetFolder: 'LaunchPad/Imports/' . $timestamp);
	}//end import()

	/**
	 * The requested root parent UUID when that dashboard exists.
	 *
	 * A missing parent is logged and the pages import as roots (REQ-CFLI-011).
	 *
	 * @param string|null $parentUuid The requested parent dashboard UUID.
	 *
	 * @return string|null The parent UUID to slot root pages under, or null.
	 */
	private function existingRootParent(?string $parentUuid): ?string {
		if ($parentUuid === null || $parentUuid === '') {
			return $parentUuid;
		}

		try {
			$this->writers->dashboardMapper->findByUuid(uuid: $parentUuid);
		} catch (DoesNotExistException) {
			$this->logger->warning(
				message: 'Confluence import: parent dashboard not found, falling back to roots',
				context: ['parentUuid' => $parentUuid]
			);
			return null;
		}

		return $parentUuid;
	}//end existingRootParent()

	/**
	 * Create the dashboard for one page and tally the outcome.
	 *
	 * @param ParsedPage          $page The page to import.
	 * @param ConfluenceImportRun $run  The import in progress.
	 *
	 * @return void
	 */
	private function importPage(ParsedPage $page, ConfluenceImportRun $run): void {
		$resolvedParent = $this->resolveParentUuid(
			page: $page,
			pageIdToUuid: $run->pageIdToUuid,
			rootParentUuid: $run->rootParentUuid
		);

		try {
			$dashboardUuid = $this->createDashboardForPage(
				page: $page,
				parentUuid: $resolvedParent,
				currentUserId: $run->currentUserId
			);

			$run->recordCreated(page: $page, dashboardUuid: $dashboardUuid);
		} catch (Throwable $e) {
			$run->recordSkipped(page: $page, reason: $e);
			$this->logger->warning(
				message: 'Confluence page skipped during import',
				context: ['pageId' => $page->pageId, 'exception' => $e]
			);
		}//end try
	}//end importPage()

	/**
	 * Topologically order pages so every parent is created before its
	 * children. Pages whose declared parent never resolves are emitted
	 * at the end so they end up as orphans (root-level imports).
	 *
	 * @param ParsedPage[] $pages The pages to sort.
	 *
	 * @return ParsedPage[] Ordered list (parents first).
	 *
	 */
	private function orderParentsBeforeChildren(array $pages): array {
		$byPageId = [];
		foreach ($pages as $page) {
			$byPageId[strtolower(string: $page->pageId)] = $page;
		}

		$resolved = (new ParentFirstPageOrder(byPageId: $byPageId))->order(pages: $pages);

		// Stable secondary sort within siblings by parent + sortOrder.
		usort(
			array: $resolved,
			callback: static function (ParsedPage $a, ParsedPage $b): int {
				return ($a->sortOrder <=> $b->sortOrder);
			}
		);

		// Re-apply parent-before-children after the sort.
		return (new ParentFirstPageOrder(byPageId: $byPageId))->order(pages: $resolved);
	}//end orderParentsBeforeChildren()

	/**
	 * Resolve the parent dashboard UUID for a given page.
	 *
	 * @param ParsedPage $page The page being created.
	 * @param array<string, string> $pageIdToUuid Map of created pages.
	 * @param string|null $rootParentUuid Optional CLI-supplied root.
	 *
	 * @return string|null The dashboard UUID to use as `parentUuid`.
	 */
	private function resolveParentUuid(
		ParsedPage $page,
		array $pageIdToUuid,
		?string $rootParentUuid,
	): ?string {
		if ($page->parentPageId === null) {
			return $rootParentUuid;
		}

		$key = strtolower(string: $page->parentPageId);
		if (isset($pageIdToUuid[$key]) === true) {
			return $pageIdToUuid[$key];
		}

		// Parent did not import — fall back to the optional CLI root.
		return $rootParentUuid;
	}//end resolveParentUuid()

	/**
	 * Create a dashboard + text widget for a single Confluence page.
	 *
	 * Wrapped in its own transaction so a malformed widget never leaves
	 * the DB with a half-imported dashboard.
	 *
	 * @param ParsedPage $page The parsed page.
	 * @param string|null $parentUuid Resolved parent dashboard UUID.
	 * @param string $currentUserId Importing user UID.
	 *
	 * @return string The new dashboard UUID.
	 */
	private function createDashboardForPage(
		ParsedPage $page,
		?string $parentUuid,
		string $currentUserId,
	): string {
		$this->db->beginTransaction();
		try {
			// Validate parent + depth. NULL parents always pass.
			$this->writers->treeService->validateParent(
				movingUuid: null,
				newParentUuid: $parentUuid
			);

			$dashboard = $this->writers->dashboardFactory->create(
				userId: $currentUserId,
				name: $page->title,
				options: [
					'description'     => null,
					'type'            => Dashboard::TYPE_USER,
					'groupId'         => null,
					'gridColumns'     => 12,
					'permissionLevel' => Dashboard::PERMISSION_FULL,
					'parentUuid'      => $parentUuid,
					'slug'            => null,
					'sortOrder'       => $page->sortOrder,
				]
			);

			// Suffix slug with pageId on collision so re-import always
			// succeeds (REQ-CFLI-010 — no dedup, new dashboards each run).
			$dashboard = $this->ensureUniqueSlug(
				dashboard: $dashboard,
				parentUuid: $parentUuid,
				pageId: $page->pageId
			);

			$persisted = $this->writers->dashboardMapper->insert(entity: $dashboard);

			$placement = $this->buildPlaceholderWidget(
				dashboardId: (int)$persisted->getId(),
				page: $page
			);

			$this->writers->placementMapper->insert(entity: $placement);

			$this->db->commit();

			return (string)$persisted->getUuid();
		} catch (Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}//end try
	}//end createDashboardForPage()

	/**
	 * Build the placeholder text widget for a freshly-inserted dashboard.
	 *
	 * Body content is rewritten in {@see backfillWidgetContent()} after
	 * every page UUID is known so internal `<a href="page-X.html">`
	 * links can be re-pointed at the new dashboards.
	 *
	 * @param int $dashboardId The new dashboard ID.
	 * @param ParsedPage $page The source page (only used for context).
	 *
	 * @return WidgetPlacement A new placement entity (unsaved).
	 */
	private function buildPlaceholderWidget(int $dashboardId, ParsedPage $page): WidgetPlacement {
		unset($page);
		$placement = new WidgetPlacement();
		$placement->setDashboardId($dashboardId);
		$placement->setWidgetId(self::TEXT_WIDGET_ID);
		$placement->setGridX(0);
		$placement->setGridY(0);
		$placement->setGridWidth(self::WIDGET_GRID_WIDTH);
		$placement->setGridHeight(self::WIDGET_GRID_HEIGHT);
		$placement->setIsVisible(1);
		$placement->setShowTitle(1);
		$placement->setSortOrder(0);

		// `created_at` and `updated_at` are NOT NULL with no default. Without
		// these two lines every page's placement insert failed with SQLSTATE
		// 23502, the page rolled back, and the import reported it as skipped:
		// the same defect #612 fixed in ImportService.
		$now = (new DateTime())->format(format: 'Y-m-d H:i:s');
		$placement->setCreatedAt($now);
		$placement->setUpdatedAt($now);

		$placement->setStyleConfigArray(
			config: [
				'text' => '',
				'fontSize' => '14px',
				'color' => 'var(--color-main-text)',
				'backgroundColor' => 'transparent',
				'textAlign' => 'left',
			]
		);

		return $placement;
	}//end buildPlaceholderWidget()

	/**
	 * Backfill widget content with sanitised + rewritten HTML.
	 *
	 * Done as a second pass so internal Confluence links can be
	 * resolved against the freshly-created dashboard UUIDs.
	 *
	 * @param array<string, ParsedPage> $pages pageId → ParsedPage.
	 * @param array<string, string> $pageIdToUuid pageId (lower) → uuid.
	 *
	 * @return array<int, string> Warnings collected during link rewriting.
	 */
	private function backfillWidgetContent(array $pages, array $pageIdToUuid): array {
		$warnings = [];

		foreach ($pages as $page) {
			try {
				$dashboard = $this->writers->dashboardMapper->findByUuid(
					uuid: $pageIdToUuid[strtolower(string: $page->pageId)]
				);
			} catch (DoesNotExistException) {
				continue;
			}

			$placements = $this->writers->placementMapper->findByDashboardId(
				dashboardId: (int)$dashboard->getId()
			);

			if ($placements === []) {
				continue;
			}

			$expanded = $this->readers->macroRenderer->render(html: $page->body);
			$rewriteRes = $this->readers->linkRewriter->rewrite(
				html: $expanded,
				pageIdToUuid: $pageIdToUuid
			);
			$sanitised = $this->readers->sanitizer->sanitize(html: $rewriteRes['html']);

			$placement = $placements[0];
			$config = $placement->getStyleConfigArray();
			$config['text'] = $sanitised;
			$placement->setStyleConfigArray(config: $config);
			$placement->setUpdatedAt((new DateTime())->format(format: 'Y-m-d H:i:s'));

			$this->writers->placementMapper->update(entity: $placement);

			$warnings = array_merge($warnings, $rewriteRes['warnings']);
		}//end foreach

		return $warnings;
	}//end backfillWidgetContent()

	/**
	 * Append `-<pageId>` to the slug when a sibling already owns it.
	 *
	 * Each Confluence import always creates new dashboards (REQ-CFLI-010
	 * — no dedup), so on the second run the slug derived from the page
	 * title would collide. Suffixing with the original pageId keeps the
	 * collision check happy without forcing the admin to clean up the
	 * first run.
	 *
	 * @param Dashboard $dashboard The dashboard with caller-derived slug.
	 * @param string|null $parentUuid The resolved parent UUID.
	 * @param string $pageId The Confluence page identifier.
	 *
	 * @return Dashboard The (possibly mutated) dashboard.
	 */
	private function ensureUniqueSlug(
		Dashboard $dashboard,
		?string $parentUuid,
		string $pageId,
	): Dashboard {
		$slug = (string)$dashboard->getSlug();
		if ($slug === '') {
			return $dashboard;
		}

		try {
			$this->writers->treeService->validateSlugUnique(
				parentUuid: $parentUuid,
				slug: $slug
			);
			return $dashboard;
		} catch (\InvalidArgumentException) {
			$suffix = SlugGenerator::slugify(name: $pageId);
			if ($suffix === '') {
				$suffix = (string)random_int(min: 1000, max: 9999);
			}

			$candidate = ($slug . '-' . $suffix);
			// Final fallback: append timestamp segment (always unique).
			try {
				$this->writers->treeService->validateSlugUnique(
					parentUuid: $parentUuid,
					slug: $candidate
				);
			} catch (\InvalidArgumentException) {
				$candidate = ($candidate . '-' . (string)time());
			}

			$dashboard->setSlug($candidate);
			return $dashboard;
		}//end try
	}//end ensureUniqueSlug()

	/**
	 * Build the timestamp segment for the asset folder.
	 *
	 * @return string The ISO 8601 segment (e.g. `2026-05-02T10-30-00`).
	 */
	private function buildTimestamp(): string {
		return (new DateTime())->format(format: 'Y-m-d\TH-i-s');
	}//end buildTimestamp()
}//end class
