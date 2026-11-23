<?php

/**
 * DemoSeedFilesTest
 *
 * Unit tests for DemoSeedFiles: the demo team folder is created once in the
 * owner's home, only missing files are added, the folder is shared with the
 * group once, and any failure is logged and reported instead of thrown.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\DemoSeedFiles;
use OCP\Constants;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\Service\DemoSeedFiles
 */
class DemoSeedFilesTest extends TestCase {

	/**
	 * Root folder double.
	 *
	 * @var IRootFolder&MockObject
	 */
	private $rootFolder;

	/**
	 * Share manager double.
	 *
	 * @var IShareManager&MockObject
	 */
	private $shares;

	/**
	 * Logger double.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private $logger;

	/**
	 * The owner's home folder.
	 *
	 * @var Folder&MockObject
	 */
	private $home;

	/**
	 * Build the doubles; pieter's home is the user folder.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->rootFolder = $this->createMock(originalClassName: IRootFolder::class);
		$this->shares     = $this->createMock(originalClassName: IShareManager::class);
		$this->logger     = $this->createMock(originalClassName: LoggerInterface::class);
		$this->home       = $this->createMock(originalClassName: Folder::class);
		$this->rootFolder->method('getUserFolder')->with('pieter')->willReturn($this->home);
	}//end setUp()

	/**
	 * The service under test.
	 *
	 * @return DemoSeedFiles
	 */
	private function service(): DemoSeedFiles {
		return new DemoSeedFiles(rootFolder: $this->rootFolder, shareManager: $this->shares, logger: $this->logger);
	}//end service()

	/**
	 * A new team folder with the given files.
	 *
	 * @param list<string> $existing Names already in the folder.
	 *
	 * @return Folder&MockObject
	 */
	private function teamFolder(array $existing = []): Folder {
		$folder = $this->createMock(originalClassName: Folder::class);
		$folder->method('nodeExists')->willReturnCallback(callback: static fn (string $path): bool => in_array($path, $existing, true));
		return $folder;
	}//end teamFolder()

	/**
	 * A fresh run creates the folder, every file, and the group share with full permissions.
	 *
	 * @return void
	 */
	public function testAFreshRunCreatesTheFolderTheFilesAndTheShare(): void {
		$folder = $this->teamFolder();
		$this->home->method('nodeExists')->with('Team')->willReturn(false);
		$this->home->expects($this->once())->method('newFolder')->with('Team')->willReturn($folder);
		$folder->expects($this->exactly(2))->method('newFile')->willReturn($this->createMock(originalClassName: File::class));

		$share = $this->createMock(originalClassName: IShare::class);
		$this->shares->method('getSharesBy')->with('pieter', IShare::TYPE_GROUP, $folder)->willReturn([]);
		$this->shares->method('newShare')->willReturn($share);
		$share->expects($this->once())->method('setNode')->with($folder)->willReturnSelf();
		$share->expects($this->once())->method('setShareType')->with(IShare::TYPE_GROUP)->willReturnSelf();
		$share->expects($this->once())->method('setSharedWith')->with('medewerkers')->willReturnSelf();
		$share->expects($this->once())->method('setSharedBy')->with('pieter')->willReturnSelf();
		$share->expects($this->once())->method('setShareOwner')->with('pieter')->willReturnSelf();
		$share->expects($this->once())->method('setPermissions')->with(Constants::PERMISSION_ALL)->willReturnSelf();
		$this->shares->expects($this->once())->method('createShare')->with($share);

		$result = $this->service()->ensure(
			definition: ['owner' => 'pieter', 'folder' => 'Team', 'names' => ['Plan.md', 'Notulen.md'], 'shareWithGroup' => 'medewerkers']
		);

		$this->assertSame(expected: ['createdFiles' => 2, 'shared' => true], actual: $result);
	}//end testAFreshRunCreatesTheFolderTheFilesAndTheShare()

	/**
	 * A second run reuses the folder, adds only the missing file and does not share twice.
	 *
	 * @return void
	 */
	public function testASecondRunAddsOnlyWhatIsMissing(): void {
		$folder = $this->teamFolder(existing: ['Plan.md']);
		$this->home->method('nodeExists')->with('Team')->willReturn(true);
		$this->home->method('get')->with('Team')->willReturn($folder);
		$this->home->expects($this->never())->method('newFolder');
		$folder->expects($this->once())->method('newFile')->with('Notulen.md', '');

		$existingShare = $this->createMock(originalClassName: IShare::class);
		$existingShare->method('getSharedWith')->willReturn('medewerkers');
		$this->shares->method('getSharesBy')->willReturn([$existingShare]);
		$this->shares->expects($this->never())->method('createShare');

		$result = $this->service()->ensure(
			definition: ['owner' => 'pieter', 'folder' => 'Team', 'names' => ['Plan.md', 'Notulen.md'], 'shareWithGroup' => 'medewerkers']
		);

		$this->assertSame(expected: ['createdFiles' => 1, 'shared' => false], actual: $result);
	}//end testASecondRunAddsOnlyWhatIsMissing()

	/**
	 * A file where the folder should be is not reused: a folder is created.
	 *
	 * @return void
	 */
	public function testAFileWithTheFolderNameIsNotUsedAsTheFolder(): void {
		$folder = $this->teamFolder();
		$this->home->method('nodeExists')->willReturn(true);
		$this->home->method('get')->willReturn($this->createMock(originalClassName: File::class));
		$this->home->expects($this->once())->method('newFolder')->with('Team')->willReturn($folder);
		$this->shares->expects($this->never())->method('getSharesBy');

		$result = $this->service()->ensure(definition: ['owner' => 'pieter', 'folder' => 'Team']);

		$this->assertSame(expected: ['createdFiles' => 0, 'shared' => false], actual: $result);
	}//end testAFileWithTheFolderNameIsNotUsedAsTheFolder()

	/**
	 * A definition that is no array is skipped without touching the files.
	 *
	 * @return void
	 */
	public function testEnsureSafelySkipsAMissingDefinition(): void {
		$this->rootFolder->expects($this->never())->method('getUserFolder');

		$this->assertSame(expected: ['skipped' => true], actual: $this->service()->ensureSafely(definition: null));
	}//end testEnsureSafelySkipsAMissingDefinition()

	/**
	 * A failure is logged as a warning and returned, so the rest of the seed goes on.
	 *
	 * @return void
	 */
	public function testEnsureSafelyReportsAFailure(): void {
		$this->home->method('nodeExists')->willReturn(false);
		$this->home->method('newFolder')->willThrowException(new RuntimeException(message: 'disk full'));
		$this->logger->expects($this->once())->method('warning')
			->with('Demo seed could not create the team folder: disk full', $this->anything());

		$this->assertSame(
			expected: ['error' => 'disk full'],
			actual: $this->service()->ensureSafely(definition: ['owner' => 'pieter', 'folder' => 'Team'])
		);
	}//end testEnsureSafelyReportsAFailure()
}//end class
