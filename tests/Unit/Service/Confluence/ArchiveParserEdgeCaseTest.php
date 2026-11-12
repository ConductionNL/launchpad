<?php

/**
 * ArchiveParserEdgeCaseTest
 *
 * Pins the edges of {@see \OCA\LaunchPad\Service\Confluence\ArchiveParser}
 * that ArchiveParserTest leaves open: rejected inputs, skipped and capped
 * entries, title and body fallbacks, chrome stripping, breadcrumb parents,
 * directory hierarchy and the page order read from index.html.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service\Confluence
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service\Confluence;

use InvalidArgumentException;
use OCA\LaunchPad\Service\Confluence\ArchiveParser;
use OCA\LaunchPad\Service\Confluence\ParsedArchive;
use OCA\LaunchPad\Service\Confluence\ParsedPage;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Edge cases of the Confluence archive parser.
 *
 * @covers \OCA\LaunchPad\Service\Confluence\ArchiveParser
 * @uses   \OCA\LaunchPad\Service\Confluence\ArchiveEntries
 * @uses   \OCA\LaunchPad\Service\Confluence\ParsedArchive
 * @uses   \OCA\LaunchPad\Service\Confluence\ParsedPage
 */
class ArchiveParserEdgeCaseTest extends TestCase {

	private const INDEX = '<html><body><p>index</p></body></html>';

	/**
	 * Temporary files created by the tests.
	 *
	 * @var array<int, string>
	 */
	private array $temp = [];

	/**
	 * Remove the temporary files.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ($this->temp as $path) {
			if (file_exists(filename: $path) === true) {
				unlink(filename: $path);
			}
		}

		parent::tearDown();
	}//end tearDown()

	/**
	 * A path that does not exist is rejected with the path in the message.
	 *
	 * @return void
	 */
	public function testAMissingFileIsRejected(): void {
		$path = sys_get_temp_dir() . '/cflitest-does-not-exist.zip';

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Confluence export not found: ' . $path);

		(new ArchiveParser())->parse(zipPath: $path);
	}//end testAMissingFileIsRejected()

	/**
	 * A file that is not a ZIP is rejected.
	 *
	 * @return void
	 */
	public function testANonZipFileIsRejected(): void {
		$path = (string)tempnam(directory: sys_get_temp_dir(), prefix: 'cflitest_');
		$this->temp[] = $path;
		file_put_contents($path, 'plain text, not a zip');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Uploaded file is not a valid ZIP archive');

		(new ArchiveParser())->parse(zipPath: $path);
	}//end testANonZipFileIsRejected()

	/**
	 * Directories, traversal names, navigation pages and unrelated files are
	 * skipped; a blank page becomes a warning instead of a page.
	 *
	 * @return void
	 */
	public function testSkippedEntriesAndABlankPage(): void {
		$archive = $this->parse(
			entries: [
				'index.html'           => self::INDEX,
				'SPACE/overview.html'  => '<html><title>Overview</title></html>',
				'SPACE/toc.html'       => '<html><title>Toc</title></html>',
				'SPACE/a..b.html'      => '<html><title>Sneaky</title></html>',
				'SPACE/empty.html'     => "  \n ",
				'SPACE/real.html'      => '<html><title>Real</title><body><p>x</p></body></html>',
				'styles/site.css'      => 'body {}',
			],
			dirs: ['SPACE/']
		);

		$this->assertSame(['Real'], $this->titles(archive: $archive));
		$this->assertSame(['Failed to parse SPACE/empty.html'], $archive->warnings);
		$this->assertSame([], $archive->attachments);
		$this->assertSame([], $archive->images);
	}//end testSkippedEntriesAndABlankPage()

	/**
	 * A page larger than the per-entry cap is skipped silently.
	 *
	 * @return void
	 */
	public function testAnOversizedPageIsSkipped(): void {
		$archive = $this->parse(
			entries: [
				'index.html'      => self::INDEX,
				'SPACE/huge.html' => str_repeat('a', (26_214_400 + 1)),
				'SPACE/ok.html'   => '<html><title>Ok</title><body>ok</body></html>',
			]
		);

		$this->assertSame(['Ok'], $this->titles(archive: $archive));
		$this->assertSame([], $archive->warnings);
	}//end testAnOversizedPageIsSkipped()

	/**
	 * Once the pages read exceed the total cap, collection stops, so an
	 * index.html after them is never reached.
	 *
	 * @return void
	 */
	public function testCollectionStopsWhenPagesExceedTheTotalCap(): void {
		$entries = [];
		for ($i = 1; $i <= 5; $i++) {
			$entries['SPACE/p' . $i . '.html'] = str_repeat('b', 21_000_000);
		}

		$entries['index.html'] = self::INDEX;

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('index.html not found in archive');

		$this->parse(entries: $entries);
	}//end testCollectionStopsWhenPagesExceedTheTotalCap()

	/**
	 * An index.html that pushes the total over the cap is not collected.
	 *
	 * @return void
	 */
	public function testAnIndexOverTheTotalCapIsNotCollected(): void {
		$entries = [];
		for ($i = 1; $i <= 4; $i++) {
			$entries['SPACE/p' . $i . '.html'] = str_repeat('c', 21_000_000);
		}

		$entries['index.html'] = str_repeat('d', 21_000_000);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('index.html not found in archive');

		$this->parse(entries: $entries);
	}//end testAnIndexOverTheTotalCapIsNotCollected()

	/**
	 * Without a title the page id is used; entities in the title are decoded
	 * and the space prefix is stripped.
	 *
	 * @return void
	 */
	public function testTitleFallbackAndDecoding(): void {
		$archive = $this->parse(
			entries: [
				'index.html'          => self::INDEX,
				'SPACE/untitled.html' => '<html><body><p>no title</p></body></html>',
				'SPACE/rd.html'       => '<html><title>Docs : R&amp;D &quot;plan&quot;</title><body>x</body></html>',
			]
		);

		$this->assertSame(['untitled', 'R&D "plan"'], $this->titles(archive: $archive));
	}//end testTitleFallbackAndDecoding()

	/**
	 * Navigation chrome inside the content container is removed.
	 *
	 * @return void
	 */
	public function testNavigationChromeIsStrippedFromTheBody(): void {
		$html = '<html><body><div id="main-content">'
			. '<div class="breadcrumbs">crumbs</div>'
			. '<nav>menu</nav>'
			. '<div class="page-metadata">meta</div>'
			. '<p>Keep me</p>'
			. '</div></body></html>';

		$body = $this->onlyPage(html: $html)->body;

		$this->assertSame('<p>Keep me</p>', $body);
	}//end testNavigationChromeIsStrippedFromTheBody()

	/**
	 * A selector that is absent or empty falls through to the next one.
	 *
	 * @return void
	 */
	public function testAnEmptyContainerFallsThroughToTheNextSelector(): void {
		$html = '<html><body><div id="main-content">   </div><article><p>From article</p></article></body></html>';

		$this->assertSame('<p>From article</p>', $this->onlyPage(html: $html)->body);
	}//end testAnEmptyContainerFallsThroughToTheNextSelector()

	/**
	 * Without any content container the whole body is used.
	 *
	 * @return void
	 */
	public function testWithoutAContainerTheBodyIsUsed(): void {
		$html = '<html><head><title>T</title></head><body> <p>Plain body</p> </body></html>';

		$this->assertSame('<p>Plain body</p>', $this->onlyPage(html: $html)->body);
	}//end testWithoutAContainerTheBodyIsUsed()

	/**
	 * Without a container or a body tag the trimmed source is used.
	 *
	 * @return void
	 */
	public function testWithoutABodyTagTheSourceIsUsed(): void {
		$this->assertSame('just text', $this->onlyPage(html: "  just text \n")->body);
	}//end testWithoutABodyTagTheSourceIsUsed()

	/**
	 * The last link in the breadcrumb is the parent; a breadcrumb without
	 * links, or ending at the space index, gives no parent.
	 *
	 * @return void
	 */
	public function testBreadcrumbParent(): void {
		$crumb = static fn (string $items): string => '<html><title>T</title><body>'
			. '<ol id="breadcrumbs">' . $items . '</ol><div id="main-content"><p>x</p></div></body></html>';

		$archive = $this->parse(
			entries: [
				'index.html'       => self::INDEX,
				'linked.html'      => $crumb('<li><a href="Home.html">H</a></li><li><a href="Parent.html#x">P</a></li><li>Me</li>'),
				'unlinked.html'    => $crumb('<li>Home</li><li>Me</li>'),
				'top.html'         => $crumb('<li><a href="index.html">Space</a></li>'),
				'nocrumb.html'     => '<html><title>T</title><body><p>x</p></body></html>',
			]
		);

		$this->assertSame(
			['linked' => 'Parent', 'unlinked' => null, 'top' => null, 'nocrumb' => null],
			$this->parents(archive: $archive)
		);
	}//end testBreadcrumbParent()

	/**
	 * A page in a directory gets the same-named page next to that directory
	 * as parent, unless its breadcrumb already named one.
	 *
	 * @return void
	 */
	public function testDirectoryHierarchy(): void {
		$archive = $this->parse(
			entries: [
				'index.html'         => self::INDEX,
				'SPACE.html'         => '<html><title>Space home</title><body>x</body></html>',
				'SPACE/child.html'   => '<html><title>Child</title><body>x</body></html>',
				'SPACE/crumbed.html' => '<html><title>C</title><body><ol class="breadcrumbs"><li><a href="Other.html">O</a></li></ol></body></html>',
				'LOOSE/orphan.html'  => '<html><title>Orphan</title><body>x</body></html>',
			]
		);

		$this->assertSame(
			['SPACE' => null, 'child' => 'SPACE', 'crumbed' => 'Other', 'orphan' => null],
			$this->parents(archive: $archive)
		);
	}//end testDirectoryHierarchy()

	/**
	 * The sort order is the first position of a page's link in index.html;
	 * links to index, overview and toc are not pages, and unlisted pages sort 0.
	 *
	 * @return void
	 */
	public function testSortOrderComesFromTheFirstIndexLink(): void {
		$index = '<html><body>'
			. '<a href="index.html">Home</a>'
			. '<a href="SPACE/overview.html">Overview</a>'
			. '<a href="SPACE/Beta.html?x=1">B</a>'
			. '<a href="SPACE/alpha.html">A</a>'
			. '<a href="SPACE/beta.html">B again</a>'
			. '</body></html>';

		$archive = $this->parse(
			entries: [
				'index.html'          => $index,
				'SPACE/alpha.html'    => '<html><title>alpha</title><body>x</body></html>',
				'SPACE/beta.html'     => '<html><title>beta</title><body>x</body></html>',
				'SPACE/unlisted.html' => '<html><title>unlisted</title><body>x</body></html>',
			]
		);

		$orders = [];
		foreach ($archive->pages as $page) {
			$orders[$page->pageId] = $page->sortOrder;
		}

		$this->assertSame(['alpha' => 3, 'beta' => 2, 'unlisted' => 0], $orders);
	}//end testSortOrderComesFromTheFirstIndexLink()

	/**
	 * Parse an archive with only index.html and one page, and return that page.
	 *
	 * @param string $html The page source.
	 *
	 * @return ParsedPage
	 */
	private function onlyPage(string $html): ParsedPage {
		$archive = $this->parse(entries: ['index.html' => self::INDEX, 'SPACE/page.html' => $html]);
		$this->assertCount(1, $archive->pages);
		return $archive->pages[0];
	}//end onlyPage()

	/**
	 * Build a ZIP from the entries and parse it.
	 *
	 * @param array<string, string> $entries Path to bytes, in archive order.
	 * @param array<int, string>    $dirs    Empty directory entries to add.
	 *
	 * @return ParsedArchive
	 */
	private function parse(array $entries, array $dirs = []): ParsedArchive {
		$path = (string)tempnam(directory: sys_get_temp_dir(), prefix: 'cflitest_');
		unlink(filename: $path);
		$path .= '.zip';
		$this->temp[] = $path;

		$zip = new ZipArchive();
		$zip->open($path, (ZipArchive::CREATE | ZipArchive::OVERWRITE));
		foreach ($dirs as $dir) {
			$zip->addEmptyDir($dir);
		}

		foreach ($entries as $name => $body) {
			$zip->addFromString($name, $body);
		}

		$zip->close();

		return (new ArchiveParser())->parse(zipPath: $path);
	}//end parse()

	/**
	 * Page titles in archive order.
	 *
	 * @param ParsedArchive $archive The parsed archive.
	 *
	 * @return array<int, string>
	 */
	private function titles(ParsedArchive $archive): array {
		return array_map(static fn (ParsedPage $page): string => $page->title, $archive->pages);
	}//end titles()

	/**
	 * Parent page id per page id.
	 *
	 * @param ParsedArchive $archive The parsed archive.
	 *
	 * @return array<string, string|null>
	 */
	private function parents(ParsedArchive $archive): array {
		$parents = [];
		foreach ($archive->pages as $page) {
			$parents[$page->pageId] = $page->parentPageId;
		}

		return $parents;
	}//end parents()
}//end class
