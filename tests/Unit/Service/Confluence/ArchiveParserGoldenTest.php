<?php

/**
 * ArchiveParser golden test
 *
 * Pins what the parser reads from a mixed Confluence export: pages, the
 * skipped boilerplate and unsafe entries, attachments and images, against
 * a recorded fixture.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service\Confluence
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service\Confluence;

use OCA\LaunchPad\Service\Confluence\ArchiveParser;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * The parsed archive of a mixed export.
 *
 * @covers \OCA\LaunchPad\Service\Confluence\ArchiveParser
 * @uses   \OCA\LaunchPad\Service\Confluence\ParsedArchive
 * @uses   \OCA\LaunchPad\Service\Confluence\ParsedPage
 * @uses   \OCA\LaunchPad\Service\Confluence\ArchiveEntries
 */
class ArchiveParserGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/archive-parser.json';

	/**
	 * A mixed export parses to the recorded archive.
	 *
	 * @return void
	 */
	public function testParsedArchiveMatchesTheRecordedFixture(): void {
		$page = static fn (string $title, string $body, string $crumb=''): string => '<html><head><title>S : '.$title.'</title></head><body>'.$crumb.'<div id="main-content"><p>'.$body.'</p></div></body></html>';
		$entries = [
			'index.html'                    => '<html><body><a href="S/a.html">A</a><a href="S/b.html">B</a><a href="S/deep/c.html">C</a></body></html>',
			'S/a.html'                      => $page('Alpha', 'one'),
			'S/b.html'                      => $page('Beta', 'two', '<ol class="breadcrumbs"><li><a href="a.html">A</a></li><li>B</li></ol>'),
			'S/deep/c.html'                 => $page('Gamma', 'three'),
			'S/overview.html'               => $page('Overview', 'skip'),
			'S/toc.html'                    => $page('Toc', 'skip'),
			'S/nested/index.html'           => $page('Nested index', 'skip'),
			'S/../escape.html'              => $page('Escape', 'skip'),
			'attachments/a/one.pdf'         => 'PDF',
			'Attachments/b/two.txt'         => 'TXT',
			'images/a/pic.png'              => 'PNG',
			'IMAGES/b/pic2.gif'             => 'GIF',
			'readme.md'                     => 'ignored',
			'S/empty/'                      => null,
		];

		$path = tempnam(sys_get_temp_dir(), 'lp-archive-');
		$zip  = new ZipArchive();
		$zip->open($path, ZipArchive::OVERWRITE);
		foreach ($entries as $name => $content) {
			if ($content === null) {
				$zip->addEmptyDir(rtrim($name, '/'));
				continue;
			}

			$zip->addFromString($name, $content);
		}

		$zip->close();

		try {
			$archive = (new ArchiveParser())->parse(zipPath: $path);
		} finally {
			unlink($path);
		}

		$pages = [];
		foreach ($archive->pages as $parsed) {
			$pages[] = [$parsed->relPath, $parsed->pageId, $parsed->title, trim(strip_tags($parsed->body)), $parsed->sortOrder, $parsed->parentPageId];
		}

		$actual = ['pages' => $pages, 'attachments' => $archive->attachments, 'images' => $archive->images, 'warnings' => $archive->warnings];

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
			$this->markTestIncomplete('Fixture recorded.');
		}

		$this->assertSame(json_decode((string) file_get_contents(self::FIXTURE), true), $actual);
	}//end testParsedArchiveMatchesTheRecordedFixture()
}//end class
