<?php

/**
 * RenderPipelineTest
 *
 * One render of an export (tasks.md task 22, REQ-SCH-003): one artefact per
 * target with distinct hashes, CSV/XLSX from the subject's tables, PDF/PNG
 * from the browser with the filter context in the page URL, file names from
 * the pattern, storage in the creator's export folder without overwriting,
 * and a preview that stores nothing.
 *
 * @category  Test
 * @package   Unit\Service\ScheduledExport
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service\ScheduledExport;

use DateTimeImmutable;
use OCA\LaunchPad\Service\ScheduledExport\HeadlessRenderer;
use OCA\LaunchPad\Service\ScheduledExport\RenderException;
use OCA\LaunchPad\Service\ScheduledExport\RenderPipeline;
use OCA\LaunchPad\Service\ScheduledExport\RenderResult;
use OCA\LaunchPad\Service\ScheduledExport\RenderServices;
use OCA\LaunchPad\Service\ScheduledExport\SubjectTables;
use OCA\LaunchPad\Service\ScheduledExport\TabularWriter;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IURLGenerator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\ScheduledExport\RenderPipeline
 * @covers \OCA\LaunchPad\Service\ScheduledExport\RenderResult
 * @covers \OCA\LaunchPad\Service\ScheduledExport\RenderServices
 * @uses   \OCA\LaunchPad\Service\ScheduledExport\TabularWriter
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-22
 */
class RenderPipelineTest extends TestCase {

	private SubjectTables&MockObject $tables;

	private HeadlessRenderer&MockObject $browser;

	private IRootFolder&MockObject $root;

	private RenderPipeline $pipeline;

	/**
	 * The subject has one table; the browser returns fixed bytes; it is Monday 16 November 2026.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->tables = $this->createMock(SubjectTables::class);
		$this->tables->method('tables')->willReturn([['title' => 'T', 'headers' => ['a'], 'rows' => [[1]]]]);
		$this->browser = $this->createMock(HeadlessRenderer::class);
		$this->root    = $this->createMock(IRootFolder::class);
		$urls          = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRouteAbsolute')->with('launchpad.page.index')->willReturn('https://nc/apps/launchpad/');
		$time = $this->createMock(ITimeFactory::class);
		$time->method('now')->willReturn(new DateTimeImmutable('2026-11-16T09:00:00+01:00'));

		$this->pipeline = new RenderPipeline($this->tables, new TabularWriter(), $this->browser, new RenderServices($this->root, $urls, $time));
	}//end setUp()

	/**
	 * An export with the given targets.
	 *
	 * @param list<array<string, mixed>> $targets The render targets.
	 *
	 * @return array<string, mixed>
	 */
	private function export(array $targets): array {
		return ['id' => 'e1', 'name' => 'Weekly', 'createdBy' => 'alice', 'subjectType' => 'dashboard', 'subjectId' => 'd1', 'renderTargets' => $targets];
	}//end export()

	/**
	 * Three targets give three artefacts with distinct hashes; the browser gets the filters in the page URL.
	 *
	 * @return void
	 */
	public function testPreviewRendersEveryTarget(): void {
		$this->browser->expects($this->exactly(2))->method('capture')
			->willReturnCallback(function (string $url, string $format, array $options): string {
				$this->assertSame('https://nc/apps/launchpad/?export=1&subject=dashboard&id=d1&filters=%7B%22gemeenteCode%22%3A%22GM0355%22%7D', $url);
				return $format . '-bytes';
			});
		$this->tables->expects($this->once())->method('tables')->with($this->anything(), ['gemeenteCode' => 'GM0355']);

		$results = $this->pipeline->preview(export: $this->export([['format' => 'pdf'], ['format' => 'png', 'formatOptions' => ['dpi' => 96]], ['format' => 'csv'], ['format' => 'xlsx']]), filterContext: ['gemeenteCode' => 'GM0355']);

		$this->assertSame(['pdf', 'png', 'csv', 'xlsx'], array_map(static fn (RenderResult $r): string => $r->format, $results));
		$this->assertSame('pdf-bytes', $results[0]->content);
		$this->assertSame("a\n1\n", $results[2]->content);
		$this->assertStringStartsWith('PK', $results[3]->content);
		$this->assertCount(4, array_unique(array_map(static fn (RenderResult $r): string => $r->sha256(), $results)));
		$this->assertSame('Weekly-2026-11-16.pdf', $results[0]->filename);
		$this->assertNull($results[0]->storagePath);
		$this->assertSame(['format' => 'csv', 'sizeBytes' => 4, 'sha256' => hash('sha256', "a\n1\n"), 'storagePath' => null], $results[2]->toAudit());
		$this->assertSame('text/csv', $results[2]->mimeType());
	}//end testPreviewRendersEveryTarget()

	/**
	 * An unknown format is refused.
	 *
	 * @return void
	 */
	public function testUnknownFormatIsRefused(): void {
		$this->expectException(RenderException::class);
		$this->expectExceptionMessage('Unknown render format: gif');
		$this->pipeline->preview(export: $this->export([['format' => 'gif']]), filterContext: []);
	}//end testUnknownFormatIsRefused()

	/**
	 * The file name follows the pattern, loses path separators, and always carries the extension.
	 *
	 * @return void
	 */
	public function testFilenameFromPattern(): void {
		$export = ['name' => 'Bezwaren/Q4', 'recipientLabel' => 'GM0355'];
		$this->assertSame('Bezwaren-Q4_GM0355_2026-11-16.csv', $this->pipeline->filename(pattern: '{name}_{recipient}_{date}.{ext}', export: $export, format: 'csv'));
		$this->assertSame('report.pdf', $this->pipeline->filename(pattern: 'report', export: $export, format: 'pdf'));
		$this->assertSame('export.png', $this->pipeline->filename(pattern: '../', export: $export, format: 'png'));
	}//end testFilenameFromPattern()

	/**
	 * A fire stores into Apps/LaunchPad/Exports/{id}/ of the creator, creating missing folders, never overwriting.
	 *
	 * @return void
	 */
	public function testRenderStoresInTheCreatorsExportFolder(): void {
		$leaf = $this->createMock(Folder::class);
		$leaf->method('nodeExists')->willReturnCallback(static fn (string $n): bool => $n === 'Weekly-2026-11-16.csv');
		$file = $this->createMock(File::class);
		$file->method('getName')->willReturn('Weekly-2026-11-16 (2).csv');
		$file->method('getPath')->willReturn('/alice/files/Apps/LaunchPad/Exports/e1/Weekly-2026-11-16 (2).csv');
		$leaf->expects($this->once())->method('newFile')->with('Weekly-2026-11-16 (2).csv', "a\n1\n")->willReturn($file);

		$created = [];
		$make    = function (Folder $child) use (&$created): Folder&MockObject {
			$folder = $this->createMock(Folder::class);
			$folder->method('get')->willThrowException(new NotFoundException());
			$folder->method('newFolder')->willReturnCallback(static function (string $name) use (&$created, $child): Folder {
				$created[] = $name;
				return $child;
			});
			return $folder;
		};
		$this->root->expects($this->once())->method('getUserFolder')->with('alice')->willReturn($make($make($make($make($leaf)))));

		$results = $this->pipeline->render(export: $this->export([['format' => 'csv']]), filterContext: []);

		$this->assertSame(['Apps', 'LaunchPad', 'Exports', 'e1'], $created);
		$this->assertSame('/alice/files/Apps/LaunchPad/Exports/e1/Weekly-2026-11-16 (2).csv', $results[0]->storagePath);
		$this->assertSame('Weekly-2026-11-16 (2).csv', $results[0]->filename);
	}//end testRenderStoresInTheCreatorsExportFolder()

	/**
	 * A creator without Files fails the render with a reason.
	 *
	 * @return void
	 */
	public function testRenderWithoutFilesIsRefused(): void {
		$this->root->method('getUserFolder')->willThrowException(new \RuntimeException('no user'));
		$this->expectException(RenderException::class);
		$this->expectExceptionMessage('The export folder could not be opened in Files');
		$this->pipeline->render(export: $this->export([['format' => 'csv']]), filterContext: []);
	}//end testRenderWithoutFilesIsRefused()
}//end class
