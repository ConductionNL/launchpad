<?php

/**
 * ScheduledExportArtefactStorageTest
 *
 * Artefact files in the export owner's Files area (design D3): written under
 * `Apps/LaunchPad/Exports/{exportId}/`, read back for a retry, deleted for
 * retention, and nothing outside that folder reachable through a path.
 *
 * @category  Test
 * @package   Unit\Service
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

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Service\ScheduledExportArtefactStorage;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\ScheduledExportArtefactStorage
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-007-retention-policy-enforced-by-janitor
 */
class ScheduledExportArtefactStorageTest extends TestCase {

	/**
	 * A write creates the missing folders and the file, and answers its path.
	 *
	 * @return void
	 */
	public function testAWriteCreatesTheExportFolderAndTheFile(): void {
		$created = [];
		$leaf    = $this->createMock(Folder::class);
		$leaf->method('nodeExists')->with('report.pdf')->willReturn(false);
		$leaf->expects($this->once())->method('newFile')->with('report.pdf', '%PDF');

		$folderFor = function (string $name) use (&$created, $leaf): Folder {
			$created[] = $name;
			if ($name === 'e-1') {
				return $leaf;
			}

			$next = $this->createMock(Folder::class);
			$next->method('nodeExists')->willReturn(false);
			$next->method('newFolder')->willReturnCallback(fn (string $n): Folder => ($this->folderFactory)($n));
			return $next;
		};
		$this->folderFactory = $folderFor;

		$home = $this->createMock(Folder::class);
		$home->method('nodeExists')->willReturn(false);
		$home->method('newFolder')->willReturnCallback(fn (string $n): Folder => $folderFor($n));
		$root = $this->createMock(IRootFolder::class);
		$root->expects($this->once())->method('getUserFolder')->with('anna')->willReturn($home);

		$path = (new ScheduledExportArtefactStorage($root))->write(owner: 'anna', exportId: 'e-1', filename: 'report.pdf', content: '%PDF');

		$this->assertSame('Apps/LaunchPad/Exports/e-1/report.pdf', $path);
		$this->assertSame(['Apps', 'LaunchPad', 'Exports', 'e-1'], $created);
	}//end testAWriteCreatesTheExportFolderAndTheFile()

	/**
	 * Creates nested folders for the write test.
	 *
	 * @var callable
	 */
	private $folderFactory;

	/**
	 * A name with a path in it is refused before Files is touched.
	 *
	 * @return void
	 */
	public function testANameWithAPathIsRefused(): void {
		$root = $this->createMock(IRootFolder::class);
		$root->expects($this->never())->method('getUserFolder');

		$this->expectException(InvalidArgumentException::class);
		(new ScheduledExportArtefactStorage($root))->write(owner: 'anna', exportId: 'e-1', filename: '../evil.pdf', content: 'x');
	}//end testANameWithAPathIsRefused()

	/**
	 * A read answers the bytes; a missing file is null; a path outside the export folder is never read.
	 *
	 * @return void
	 */
	public function testReadAnswersTheBytesOnlyForArtefactPaths(): void {
		$file = $this->createMock(File::class);
		$file->method('getContent')->willReturn('%PDF');
		$home = $this->createMock(Folder::class);
		$home->method('get')->willReturnCallback(
			static function (string $path) use ($file): File {
				if ($path === 'Apps/LaunchPad/Exports/e-1/report.pdf') {
					return $file;
				}

				throw new NotFoundException();
			}
		);
		$root = $this->createMock(IRootFolder::class);
		$root->method('getUserFolder')->willReturn($home);
		$storage = new ScheduledExportArtefactStorage($root);

		$this->assertSame('%PDF', $storage->read(owner: 'anna', path: 'Apps/LaunchPad/Exports/e-1/report.pdf'));
		$this->assertNull($storage->read(owner: 'anna', path: 'Apps/LaunchPad/Exports/e-1/gone.pdf'));
		$this->assertNull($storage->read(owner: 'anna', path: 'Documents/secret.pdf'));
		$this->assertNull($storage->read(owner: 'anna', path: 'Apps/LaunchPad/Exports/e-1/../../../x'));
	}//end testReadAnswersTheBytesOnlyForArtefactPaths()

	/**
	 * A delete removes the file, counts a missing one as deleted, and refuses other paths.
	 *
	 * @return void
	 */
	public function testDeleteRemovesOnlyArtefacts(): void {
		$file = $this->createMock(File::class);
		$file->expects($this->once())->method('delete');
		$home = $this->createMock(Folder::class);
		$home->method('get')->willReturnCallback(
			static function (string $path) use ($file): File {
				if ($path === 'Apps/LaunchPad/Exports/e-1/a.pdf') {
					return $file;
				}

				throw new NotFoundException();
			}
		);
		$root = $this->createMock(IRootFolder::class);
		$root->method('getUserFolder')->willReturn($home);
		$storage = new ScheduledExportArtefactStorage($root);

		$this->assertTrue($storage->delete(owner: 'anna', path: 'Apps/LaunchPad/Exports/e-1/a.pdf'));
		$this->assertTrue($storage->delete(owner: 'anna', path: 'Apps/LaunchPad/Exports/e-1/b.pdf'));
		$this->assertFalse($storage->delete(owner: 'anna', path: 'Documents/a.pdf'));
	}//end testDeleteRemovesOnlyArtefacts()
}//end class
