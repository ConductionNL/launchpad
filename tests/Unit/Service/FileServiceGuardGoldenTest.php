<?php

/**
 * FileService guard golden test
 *
 * Pins, on the code before its split, which file writes are allowed and
 * which are refused (filename, directory and extension checks), the
 * exact storage-failure messages of folder resolution and the write, and
 * how the extension allow-list is normalised on read and on save.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Exception\FileAlreadyExistsException;
use OCA\LaunchPad\Exception\FileTypeNotAllowedException;
use OCA\LaunchPad\Exception\InvalidDirectoryException;
use OCA\LaunchPad\Exception\InvalidFilenameException;
use OCA\LaunchPad\Exception\StorageFailureException;
use OCA\LaunchPad\Service\FileService;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FileServiceGuardGoldenTest extends TestCase {
	private IRootFolder $rootFolder;
	private AdminSettingMapper $settingMapper;
	private Folder $userFolder;
	private FileService $service;

	protected function setUp(): void {
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->settingMapper = $this->createMock(AdminSettingMapper::class);
		$this->userFolder = $this->createMock(Folder::class);
		$this->rootFolder->method('getUserFolder')->willReturn($this->userFolder);
		$urlGenerator = $this->createMock(IURLGenerator::class);
		$urlGenerator->method('linkToRouteAbsolute')->willReturn('https://nc/f');
		$this->service = new FileService($this->rootFolder, $urlGenerator, $this->settingMapper);
	}

	private function storedExtensions(mixed $stored): void {
		$this->settingMapper->method('getValue')->willReturn($stored);
	}

	private function newFileIn(Folder $folder): File {
		$file = $this->createMock(File::class);
		$file->method('getId')->willReturn(9);
		$folder->method('nodeExists')->willReturn(false);
		$folder->method('newFile')->willReturn($file);

		return $file;
	}

	/**
	 * Filenames refused before storage is touched.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function refusedWrites(): array {
		return [
			'empty name'       => ['', '/'],
			'too long'         => [str_repeat('a', 252) . '.txt', '/'],
			'null byte'        => ["a\0.txt", '/'],
			'dot dot'          => ['a..txt', '/'],
			'slash'            => ['a/b.txt', '/'],
			'backslash'        => ['a\\b.txt', '/'],
			'pattern'          => ['a*b.txt', '/'],
		];
	}

	/**
	 * @dataProvider refusedWrites
	 */
	public function testRefusedFilenameNeverReachesStorage(string $filename, string $dir): void {
		$this->rootFolder->expects($this->never())->method('getUserFolder');
		$this->expectException(InvalidFilenameException::class);

		$this->service->createFile('alice', $filename, ['dir' => $dir]);
	}

	/**
	 * Directories refused before storage is touched.
	 *
	 * @return array<string,array{0:string}>
	 */
	public static function refusedDirectories(): array {
		return ['dot dot' => ['/a/../b'], 'null byte' => ["/a\0"]];
	}

	/**
	 * @dataProvider refusedDirectories
	 */
	public function testRefusedDirectoryNeverReachesStorage(string $dir): void {
		$this->rootFolder->expects($this->never())->method('getUserFolder');
		$this->expectException(InvalidDirectoryException::class);

		$this->service->createFile('alice', 'ok.txt', ['dir' => $dir]);
	}

	public function testFilenameIsCheckedBeforeDirectory(): void {
		$this->expectException(InvalidFilenameException::class);

		$this->service->createFile('alice', 'a/b.txt', ['dir' => '/../']);
	}

	public function testExtensionOffTheListIsRefused(): void {
		$this->storedExtensions(['md']);
		$this->rootFolder->expects($this->never())->method('getUserFolder');
		$this->expectException(FileTypeNotAllowedException::class);

		$this->service->createFile('alice', 'a.txt');
	}

	public function testAllowedLongestNameIsWritten(): void {
		$this->storedExtensions(null);
		$this->newFileIn($this->userFolder);

		$result = $this->service->createFile('alice', str_repeat('a', 251) . '.txt');

		$this->assertSame(['status' => 'success', 'fileId' => 9, 'url' => 'https://nc/f'], $result);
	}

	/**
	 * Directories that resolve to the user's own folder.
	 *
	 * @return array<string,array{0:string}>
	 */
	public static function rootDirectories(): array {
		return ['slash' => ['/'], 'empty' => [''], 'slashes' => ['///']];
	}

	/**
	 * @dataProvider rootDirectories
	 */
	public function testRootDirectoryWritesIntoTheUserFolder(string $dir): void {
		$this->storedExtensions(null);
		$this->userFolder->expects($this->never())->method('get');
		$this->newFileIn($this->userFolder);

		$this->assertSame(9, $this->service->createFile('alice', 'a.txt', ['dir' => $dir])['fileId']);
	}

	public function testExistingSubfolderIsUsed(): void {
		$this->storedExtensions(null);
		$sub = $this->createMock(Folder::class);
		$this->userFolder->expects($this->once())->method('get')->with('/a/b')->willReturn($sub);
		$this->newFileIn($sub);

		$this->assertSame(9, $this->service->createFile('alice', 'a.txt', ['dir' => 'a/b/'])['fileId']);
	}

	/**
	 * Folder-resolution failures and their message.
	 *
	 * @return array<string,array{0:string,1:string}>
	 */
	public static function folderFailures(): array {
		return [
			'file in the way' => ['file', 'Failed to resolve target folder'],
			'lookup error'    => ['error', 'Failed to resolve target folder'],
			'create error'    => ['create', 'Failed to create target folder'],
		];
	}

	/**
	 * @dataProvider folderFailures
	 */
	public function testFolderFailureMessage(string $case, string $message): void {
		$this->storedExtensions(null);
		if ($case === 'file') {
			$this->userFolder->method('get')->willReturn($this->createMock(File::class));
		}

		if ($case === 'error') {
			$this->userFolder->method('get')->willThrowException(new RuntimeException('x'));
		}

		if ($case === 'create') {
			$this->userFolder->method('get')->willThrowException(new NotFoundException('x'));
			$this->userFolder->method('newFolder')->willThrowException(new RuntimeException('x'));
		}

		$this->expectException(StorageFailureException::class);
		$this->expectExceptionMessage($message);

		$this->service->createFile('alice', 'a.txt', ['dir' => '/sub']);
	}

	public function testMissingSubfolderIsCreated(): void {
		$this->storedExtensions(null);
		$sub = $this->createMock(Folder::class);
		$this->userFolder->method('get')->willThrowException(new NotFoundException('x'));
		$this->userFolder->expects($this->once())->method('newFolder')->with('/sub')->willReturn($sub);
		$this->newFileIn($sub);

		$this->assertSame(9, $this->service->createFile('alice', 'a.txt', ['dir' => '/sub'])['fileId']);
	}

	public function testRefuseModeAnswersAlreadyExistsUnwrapped(): void {
		$this->storedExtensions(null);
		$this->userFolder->method('nodeExists')->willReturn(true);
		$this->userFolder->expects($this->never())->method('get');
		$this->expectException(FileAlreadyExistsException::class);

		$this->service->createFile('alice', 'a.txt', ['onExisting' => FileService::ON_EXISTING_REFUSE]);
	}

	public function testReplaceModeOverwritesTheExistingFile(): void {
		$this->storedExtensions(null);
		$existing = $this->createMock(File::class);
		$existing->method('getId')->willReturn(4);
		$existing->expects($this->once())->method('putContent')->with('body');
		$this->userFolder->method('nodeExists')->willReturn(true);
		$this->userFolder->method('get')->willReturn($existing);
		$this->userFolder->expects($this->never())->method('newFile');

		$this->assertSame(4, $this->service->createFile('alice', 'a.txt', ['content' => 'body'])['fileId']);
	}

	/**
	 * Write failures, all answered with the same storage message.
	 *
	 * @return array<string,array{0:string}>
	 */
	public static function writeFailures(): array {
		return ['folder in the way' => ['folder'], 'put fails' => ['put'], 'new fails' => ['new'], 'exists check fails' => ['exists']];
	}

	/**
	 * @dataProvider writeFailures
	 */
	public function testWriteFailureMessage(string $case): void {
		$this->storedExtensions(null);
		$this->userFolder->method('nodeExists')->willReturnCallback(static function () use ($case): bool {
			if ($case === 'exists') {
				throw new RuntimeException('x');
			}

			return $case !== 'new';
		});
		$existing = $this->createMock(File::class);
		$existing->method('putContent')->willThrowException(new RuntimeException('x'));
		$this->userFolder->method('get')->willReturn($case === 'folder' ? $this->createMock(Folder::class) : $existing);
		$this->userFolder->method('newFile')->willThrowException(new RuntimeException('x'));
		$this->expectException(StorageFailureException::class);
		$this->expectExceptionMessage('Failed to write file');

		$this->service->createFile('alice', 'a.txt');
	}

	public function testUserFolderFailureMessage(): void {
		$this->storedExtensions(null);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->rootFolder->method('getUserFolder')->willThrowException(new RuntimeException('x'));
		$service = new FileService($this->rootFolder, $this->createMock(IURLGenerator::class), $this->settingMapper);
		$this->expectException(StorageFailureException::class);
		$this->expectExceptionMessage('Failed to resolve user folder');

		$service->createFile('alice', 'a.txt');
	}

	/**
	 * Stored allow-lists and what they read as.
	 *
	 * @return array<string,array{0:mixed,1:list<string>}>
	 */
	public static function storedLists(): array {
		$default = FileService::DEFAULT_ALLOWED_EXTENSIONS;
		return [
			'unset'        => [null, $default],
			'not a list'   => ['txt', $default],
			'empty'        => [[], $default],
			'all invalid'  => [[3, ' . ', 'p-d-f', ''], $default],
			'normalised'   => [[' .TXT ', 'md', 'Md', 7, 'x y', 'pdf2', '..csv'], ['txt', 'md', 'pdf2', 'csv']],
		];
	}

	/**
	 * @dataProvider storedLists
	 */
	public function testStoredListReads(mixed $stored, array $expected): void {
		$this->storedExtensions($stored);

		$this->assertSame($expected, $this->service->getAllowedExtensions());
	}

	/**
	 * Saved allow-lists and what is stored.
	 *
	 * @return array<string,array{0:array<int,mixed>,1:list<string>}>
	 */
	public static function savedLists(): array {
		return [
			'empty'       => [[], FileService::DEFAULT_ALLOWED_EXTENSIONS],
			'all invalid' => [[null, '', '-'], FileService::DEFAULT_ALLOWED_EXTENSIONS],
			'normalised'  => [['.PDF', 'pdf', ' odt '], ['pdf', 'odt']],
		];
	}

	/**
	 * @dataProvider savedLists
	 */
	public function testSavedListIsNormalisedAndStored(array $saved, array $expected): void {
		$this->settingMapper->expects($this->once())->method('setSetting')->with(
			AdminSetting::KEY_LINK_CREATE_FILE_EXTENSIONS,
			$expected
		);

		$this->assertSame($expected, $this->service->setAllowedExtensions(extensions: $saved));
	}
}//end class
