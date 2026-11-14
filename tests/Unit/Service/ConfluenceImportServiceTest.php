<?php

/**
 * Confluence import service test
 *
 * Pins the page ordering and the per-page import tally of the Confluence
 * importer: parents before children, sibling order, skipped pages and the
 * root-parent fallback.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\Confluence\ArchiveParser;
use OCA\LaunchPad\Service\Confluence\HtmlSanitizer;
use OCA\LaunchPad\Service\Confluence\LinkRewriter;
use OCA\LaunchPad\Service\Confluence\MacroRenderer;
use OCA\LaunchPad\Service\Confluence\ParsedArchive;
use OCA\LaunchPad\Service\Confluence\ParsedPage;
use OCA\LaunchPad\Service\ConfluenceDashboardWriters;
use OCA\LaunchPad\Service\ConfluenceImportService;
use OCA\LaunchPad\Service\ConfluencePageReaders;
use OCA\LaunchPad\Service\DashboardFactory;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use RuntimeException;

/**
 * The importer's page order and import tally.
 *
 * @covers \OCA\LaunchPad\Service\ConfluenceImportService
 * @uses   \OCA\LaunchPad\Service\DashboardFactory
 * @uses   \OCA\LaunchPad\Service\Confluence\ParsedPage
 * @uses   \OCA\LaunchPad\Service\Confluence\ParsedArchive
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Db\WidgetPlacement
 * @uses   \OCA\LaunchPad\Service\SlugGenerator
 * @uses   \OCA\LaunchPad\Service\ConfluencePageReaders
 * @uses   \OCA\LaunchPad\Service\ConfluenceDashboardWriters
 * @uses   \OCA\LaunchPad\Service\ConfluenceImportRun
 * @uses   \OCA\LaunchPad\Service\ParentFirstPageOrder
 */
class ConfluenceImportServiceTest extends TestCase {

	/**
	 * Parents come before their children and siblings follow sortOrder.
	 *
	 * @return void
	 */
	public function testOrderPutsParentsFirstAndSiblingsBySortOrder(): void {
		$pages = [
			$this->page(id: 'C2', sort: 2, parent: 'b'),
			$this->page(id: 'c1', sort: 1, parent: 'B'),
			$this->page(id: 'orphan', sort: 0, parent: 'missing'),
			$this->page(id: 'b', sort: 5, parent: 'a'),
			$this->page(id: 'self', sort: 4, parent: 'SELF'),
			$this->page(id: 'a', sort: 3, parent: null),
			$this->page(id: 'z', sort: 1, parent: null),
		];

		$this->assertSame(
			['orphan', 'a', 'b', 'c1', 'z', 'C2', 'self'],
			$this->ids(pages: $this->order(pages: $pages))
		);
	}//end testOrderPutsParentsFirstAndSiblingsBySortOrder()

	/**
	 * A repeated pageId is emitted once, as the first copy seen.
	 *
	 * @return void
	 */
	public function testOrderEmitsADuplicatePageIdOnce(): void {
		$first  = $this->page(id: 'dup', sort: 1, parent: null);
		$second = $this->page(id: 'DUP', sort: 0, parent: null);
		$child  = $this->page(id: 'kid', sort: 0, parent: 'dup');

		$ordered = $this->order(pages: [$child, $first, $second]);

		$this->assertSame(['DUP', 'kid'], $this->ids(pages: $ordered));
		$this->assertSame($second, $ordered[0]);
	}//end testOrderEmitsADuplicatePageIdOnce()

	/**
	 * Every page is imported under its resolved parent; failures are tallied.
	 *
	 * @return void
	 */
	public function testImportTalliesCreatedAndSkippedPages(): void {
		$parents = [];
		$tree    = $this->createMock(DashboardTreeService::class);
		$tree->method('validateParent')->willReturnCallback(
			static function (?string $movingUuid, ?string $newParentUuid) use (&$parents): void {
				$parents[] = $newParentUuid;
				if (count($parents) === 3) {
					throw new RuntimeException('depth exceeded');
				}
			}
		);

		$logged = [];
		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('warning')->willReturnCallback(
			static function (string $message, array $context) use (&$logged): void {
				$logged[] = [$message, ($context['pageId'] ?? $context['parentUuid'] ?? null)];
			}
		);

		$result = $this->service(tree: $tree, logger: $logger, parentExists: false)->import(
			zipPath: '/tmp/x.zip',
			currentUserId: 'erin',
			parentUuid: 'gone'
		);

		$this->assertSame(null, $parents[0]);
		$this->assertSame(36, strlen((string) $parents[1]));
		$this->assertSame([null, null], [$parents[2], $parents[3]]);
		$this->assertMatchesRegularExpression('#^LaunchPad/Imports/\d{4}-\d\d-\d\dT\d\d-\d\d-\d\d$#', $result['assetFolder']);
		unset($result['assetFolder']);
		$this->assertSame(
			[
				'createdDashboardCount' => 3,
				'skippedPageCount' => 1,
				'errors' => [['pageId' => 'bad', 'reason' => 'depth exceeded']],
				'warnings' => ['archive warning'],
			],
			$result
		);
		$this->assertSame(
			[
				['Confluence import: parent dashboard not found, falling back to roots', 'gone'],
				['Confluence page skipped during import', 'bad'],
			],
			$logged
		);
	}//end testImportTalliesCreatedAndSkippedPages()

	/**
	 * An existing root parent is used for every root page.
	 *
	 * @return void
	 */
	public function testImportSlotsRootPagesUnderAnExistingParent(): void {
		$parents = [];
		$tree    = $this->createMock(DashboardTreeService::class);
		$tree->method('validateParent')->willReturnCallback(
			static function (?string $movingUuid, ?string $newParentUuid) use (&$parents): void {
				$parents[] = $newParentUuid;
			}
		);

		$result = $this->service(tree: $tree, logger: $this->createMock(LoggerInterface::class), parentExists: true)->import(
			zipPath: '/tmp/x.zip',
			currentUserId: 'erin',
			parentUuid: 'root-uuid'
		);

		$this->assertSame(['root-uuid', 'root-uuid'], [$parents[0], $parents[2]]);
		$this->assertSame(36, strlen((string) $parents[1]));
		$this->assertSame(4, $result['createdDashboardCount']);
		$this->assertSame(0, $result['skippedPageCount']);
	}//end testImportSlotsRootPagesUnderAnExistingParent()

	/**
	 * A taken slug gets the pageId suffix, then a timestamp when that is taken too.
	 *
	 * @return void
	 */
	public function testEnsureUniqueSlugSuffixesThePageIdThenATimestamp(): void {
		$this->assertSame('board', $this->uniqueSlug(slug: 'board', pageId: 'P 7', taken: []));
		$this->assertSame('', $this->uniqueSlug(slug: '', pageId: 'P 7', taken: ['']));
		$this->assertSame('board-p-7', $this->uniqueSlug(slug: 'board', pageId: 'P 7', taken: ['board']));
		$this->assertMatchesRegularExpression('/^board-p-7-\d{9,}$/', $this->uniqueSlug(slug: 'board', pageId: 'P 7', taken: ['board', 'board-p-7']));
		$this->assertMatchesRegularExpression('/^board-\d{4}$/', $this->uniqueSlug(slug: 'board', pageId: '!!!', taken: ['board']));
	}//end testEnsureUniqueSlugSuffixesThePageIdThenATimestamp()

	/**
	 * Each created page's first widget gets the rendered, rewritten, sanitised body.
	 *
	 * @return void
	 */
	public function testBackfillWritesTheSanitisedBodyAndCollectsWarnings(): void {
		$mapper = $this->createMock(DashboardMapper::class);
		$mapper->method('findByUuid')->willReturnCallback(
			static function (string $uuid): Dashboard {
				if ($uuid === 'u-gone') {
					throw new DoesNotExistException('no');
				}

				$dashboard = new Dashboard();
				$dashboard->setId(($uuid === 'u-a') ? 1 : 2);
				return $dashboard;
			}
		);

		$first = new WidgetPlacement();
		$first->setStyleConfigArray(config: ['text' => '', 'color' => 'red']);
		$updated    = [];
		$placements = $this->createMock(WidgetPlacementMapper::class);
		$placements->method('findByDashboardId')->willReturnCallback(
			static fn (int $dashboardId): array => ($dashboardId === 1) ? [$first, new WidgetPlacement()] : []
		);
		$placements->method('update')->willReturnCallback(
			static function (WidgetPlacement $placement) use (&$updated): WidgetPlacement {
				$updated[] = $placement->getStyleConfigArray();
				return $placement;
			}
		);

		$macro = $this->createMock(MacroRenderer::class);
		$macro->method('render')->willReturnCallback(static fn (string $html): string => 'm('.$html.')');
		$links = $this->createMock(LinkRewriter::class);
		$links->method('rewrite')->willReturnCallback(
			static fn (string $html, array $pageIdToUuid): array => ['html' => 'l('.$html.')', 'warnings' => ['w '.count($pageIdToUuid)]]
		);
		$clean = $this->createMock(HtmlSanitizer::class);
		$clean->method('sanitize')->willReturnCallback(static fn (string $html): string => 's('.$html.')');

		$service = new ConfluenceImportService(
			readers: new ConfluencePageReaders(parser: $this->createMock(ArchiveParser::class), sanitizer: $clean, macroRenderer: $macro, linkRewriter: $links),
			writers: new ConfluenceDashboardWriters(
				dashboardFactory: new DashboardFactory(),
				treeService: $this->createMock(DashboardTreeService::class),
				dashboardMapper: $mapper,
				placementMapper: $placements
			),
			db: $this->createMock(IDBConnection::class),
			logger: $this->createMock(LoggerInterface::class)
		);

		$warnings = (new ReflectionMethod(ConfluenceImportService::class, 'backfillWidgetContent'))->invoke(
			$service,
			['A' => $this->page(id: 'A', sort: 0, parent: null), 'gone' => $this->page(id: 'gone', sort: 0, parent: null), 'b' => $this->page(id: 'b', sort: 0, parent: null)],
			['a' => 'u-a', 'gone' => 'u-gone', 'b' => 'u-b']
		);

		$this->assertSame(['w 3'], $warnings);
		$this->assertSame([['text' => 's(l(m(<p>A</p>)))', 'color' => 'red']], $updated);
	}//end testBackfillWritesTheSanitisedBodyAndCollectsWarnings()

	/**
	 * Run the private slug step against a set of taken slugs.
	 *
	 * @param string   $slug   The factory-derived slug.
	 * @param string   $pageId The page id.
	 * @param string[] $taken  Slugs a sibling already owns.
	 *
	 * @return string The slug the dashboard ends with.
	 */
	private function uniqueSlug(string $slug, string $pageId, array $taken): string {
		$tree = $this->createMock(DashboardTreeService::class);
		$tree->method('validateSlugUnique')->willReturnCallback(
			static function (?string $parentUuid, string $slug) use ($taken): void {
				if (in_array($slug, $taken, true) === true) {
					throw new \InvalidArgumentException('taken');
				}
			}
		);

		$dashboard = new Dashboard();
		$dashboard->setSlug($slug);
		$service = $this->service(tree: $tree, logger: $this->createMock(LoggerInterface::class), parentExists: false);
		(new ReflectionMethod(ConfluenceImportService::class, 'ensureUniqueSlug'))->invoke($service, $dashboard, 'p-1', $pageId);

		return (string) $dashboard->getSlug();
	}//end uniqueSlug()

	/**
	 * Run the private ordering step.
	 *
	 * @param ParsedPage[] $pages The pages.
	 *
	 * @return ParsedPage[]
	 */
	private function order(array $pages): array {
		$service = $this->service(tree: $this->createMock(DashboardTreeService::class), logger: $this->createMock(LoggerInterface::class), parentExists: false);
		return (new ReflectionMethod(ConfluenceImportService::class, 'orderParentsBeforeChildren'))->invoke($service, $pages);
	}//end order()

	/**
	 * The page ids, in order.
	 *
	 * @param ParsedPage[] $pages The pages.
	 *
	 * @return string[]
	 */
	private function ids(array $pages): array {
		return array_map(static fn (ParsedPage $page): string => $page->pageId, $pages);
	}//end ids()

	/**
	 * A parsed page.
	 *
	 * @param string      $id     Page id.
	 * @param int         $sort   Sort order.
	 * @param string|null $parent Parent page id.
	 *
	 * @return ParsedPage
	 */
	private function page(string $id, int $sort, ?string $parent): ParsedPage {
		return new ParsedPage(relPath: $id.'.html', pageId: $id, title: 'T '.$id, body: '<p>'.$id.'</p>', sortOrder: $sort, parentPageId: $parent);
	}//end page()

	/**
	 * The importer wired with mocks; the archive holds root, two children and a grandchild.
	 *
	 * @param DashboardTreeService $tree         Tree service mock.
	 * @param LoggerInterface      $logger       Logger mock.
	 * @param bool                 $parentExists Whether the root parent dashboard exists.
	 *
	 * @return ConfluenceImportService
	 */
	private function service(DashboardTreeService $tree, LoggerInterface $logger, bool $parentExists): ConfluenceImportService {
		$parser = $this->createMock(ArchiveParser::class);
		$parser->method('parse')->willReturn(
			new ParsedArchive(
				pages: [
					$this->page(id: 'child', sort: 1, parent: 'root'),
					$this->page(id: 'root', sort: 0, parent: null),
					$this->page(id: 'bad', sort: 2, parent: null),
					$this->page(id: 'under-bad', sort: 3, parent: 'bad'),
				],
				attachments: [],
				images: [],
				warnings: ['archive warning']
			)
		);

		$nextId = 0;
		$mapper = $this->createMock(DashboardMapper::class);
		$mapper->method('insert')->willReturnCallback(
			static function (Dashboard $dashboard) use (&$nextId): Dashboard {
				$dashboard->setId(++$nextId);
				return $dashboard;
			}
		);
		$mapper->method('findByUuid')->willReturnCallback(
			static function (string $uuid) use ($parentExists): Dashboard {
				if ($uuid === 'gone' || ($uuid === 'root-uuid' && $parentExists === false)) {
					throw new DoesNotExistException('no');
				}

				$dashboard = new Dashboard();
				$dashboard->setId(1);
				return $dashboard;
			}
		);

		$placements = $this->createMock(WidgetPlacementMapper::class);
		$placements->method('findByDashboardId')->willReturn([]);

		return new ConfluenceImportService(
			readers: new ConfluencePageReaders(
				parser: $parser,
				sanitizer: $this->createMock(HtmlSanitizer::class),
				macroRenderer: $this->createMock(MacroRenderer::class),
				linkRewriter: $this->createMock(LinkRewriter::class)
			),
			writers: new ConfluenceDashboardWriters(
				dashboardFactory: new DashboardFactory(),
				treeService: $tree,
				dashboardMapper: $mapper,
				placementMapper: $placements
			),
			db: $this->createMock(IDBConnection::class),
			logger: $logger
		);
	}//end service()
}//end class
