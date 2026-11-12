<?php

/**
 * FilesWidgetService access golden test
 *
 * Pins, on the code before its split, which reads, uploads and deletes
 * the files widget allows and which it refuses (and with which
 * exception or error code): configured-folder resolution, sub-path
 * descent, the upload screening, the delete containment walk, plus the
 * listing order, MIME filter, paging and upload naming those paths use.
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

namespace OCA\LaunchPad\Service {
	/**
	 * Test seam: answers true for the temp files a test registered as
	 * genuine uploads, and defers to PHP for every other path.
	 *
	 * @param string $filename The temp path.
	 *
	 * @return bool
	 */
	function is_uploaded_file(string $filename): bool {
		return in_array($filename, $GLOBALS['launchpadTestUploads'] ?? [], true) || \is_uploaded_file($filename);
	}
}

namespace Unit\Service {
	use OCA\LaunchPad\Exception\FolderNotFoundException;
	use OCA\LaunchPad\Exception\NoAccessException;
	use OCA\LaunchPad\Service\FilesWidgetService;
	use OCP\Files\File;
	use OCP\Files\Folder;
	use OCP\Files\IRootFolder;
	use OCP\Files\Node;
	use OCP\Files\NotFoundException;
	use OCP\Files\NotPermittedException;
	use OCP\IPreview;
	use OCP\IURLGenerator;
	use PHPUnit\Framework\TestCase;
	use RuntimeException;

	class FilesWidgetAccessGoldenTest extends TestCase {
		private IRootFolder $rootFolder;
		private Folder $userFolder;
		private FilesWidgetService $service;

		/** @var list<string> */
		private array $tmpFiles = [];

		protected function setUp(): void {
			$this->rootFolder = $this->createMock(IRootFolder::class);
			$this->userFolder = $this->folder(id: 1, path: '/alice/files');
			$this->rootFolder->method('getUserFolder')->willReturn($this->userFolder);
			$this->service = new FilesWidgetService(
				$this->rootFolder,
				$this->createMock(IURLGenerator::class),
				$this->createMock(IPreview::class),
			);
		}

		protected function tearDown(): void {
			foreach ($this->tmpFiles as $tmp) {
				@unlink($tmp);
			}

			$GLOBALS['launchpadTestUploads'] = [];
		}

		private function folder(int $id, string $path = '/x', bool $readable = true, bool $updateable = true): Folder {
			$folder = $this->createMock(Folder::class);
			$folder->method('getId')->willReturn($id);
			$folder->method('getPath')->willReturn($path);
			$folder->method('isReadable')->willReturn($readable);
			$folder->method('isUpdateable')->willReturn($updateable);
			$folder->method('getName')->willReturn('f' . $id);
			$folder->method('getMimetype')->willReturn('httpd/unix-directory');

			return $folder;
		}

		private function file(int $id, string $name, string $mime = 'text/plain', int $size = 1, int $mtime = 0): File {
			$file = $this->createMock(File::class);
			$file->method('getId')->willReturn($id);
			$file->method('getName')->willReturn($name);
			$file->method('getMimetype')->willReturn($mime);
			$file->method('getSize')->willReturn($size);
			$file->method('getMTime')->willReturn($mtime);
			$file->method('isReadable')->willReturn(true);

			return $file;
		}

		private function names(array $result): array {
			return array_column($result['items'], 'name');
		}

		/**
		 * fileId configs and whether they resolve by id.
		 *
		 * @return array<string,array{0:mixed,1:bool}>
		 */
		public static function fileIds(): array {
			return [
				'int'          => [12, true],
				'digit string' => ['12', true],
				'zero'         => [0, false],
				'zero string'  => ['0', false],
				'negative'     => [-12, false],
				'text'         => ['12a', false],
				'float'        => [12.0, false],
			];
		}

		/**
		 * @dataProvider fileIds
		 */
		public function testFileIdResolution(mixed $fileId, bool $byId): void {
			$configured = $this->folder(id: 12);
			$configured->method('getDirectoryListing')->willReturn([$this->file(5, 'byid.txt')]);
			$this->userFolder->method('getById')->willReturnCallback(
				static fn (int $id): array => $id === 12 ? [$configured] : []
			);
			$this->userFolder->method('getDirectoryListing')->willReturn([$this->file(6, 'home.txt')]);

			$result = $this->service->getContentsForPlacement('alice', ['fileId' => $fileId]);

			$this->assertSame([$byId ? 'byid.txt' : 'home.txt'], $this->names($result));
		}

		public function testFileIdOfAFileFallsBackToThePath(): void {
			$docs = $this->folder(id: 30);
			$docs->method('getDirectoryListing')->willReturn([$this->file(7, 'docs.txt')]);
			$this->userFolder->method('getById')->willReturn([$this->file(12, 'x.txt')]);
			$this->userFolder->expects($this->once())->method('get')->with('/Docs')->willReturn($docs);

			$result = $this->service->getContentsForPlacement('alice', ['fileId' => 12, 'folderPath' => '/Docs/']);

			$this->assertSame(['docs.txt'], $this->names($result));
		}

		public function testUnresolvedFileIdWithoutPathIsNotFound(): void {
			$this->userFolder->method('getById')->willReturn([]);
			$this->expectException(FolderNotFoundException::class);

			$this->service->getContentsForPlacement('alice', ['fileId' => 99]);
		}

		/**
		 * Paths that do not resolve to a folder.
		 *
		 * @return array<string,array{0:string}>
		 */
		public static function unresolvedPaths(): array {
			return ['missing' => ['missing'], 'file' => ['file']];
		}

		/**
		 * @dataProvider unresolvedPaths
		 */
		public function testUnresolvedPathIsNotFound(string $case): void {
			if ($case === 'missing') {
				$this->userFolder->method('get')->willThrowException(new NotFoundException('x'));
			} else {
				$this->userFolder->method('get')->willReturn($this->file(3, 'a'));
			}

			$this->expectException(FolderNotFoundException::class);

			$this->service->getContentsForPlacement('alice', ['folderPath' => 'Docs']);
		}

		public function testSlashPathIsTheHomeFolder(): void {
			$this->userFolder->expects($this->never())->method('get');
			$this->userFolder->method('getDirectoryListing')->willReturn([]);

			$this->assertSame(['items' => [], 'nextCursor' => null], $this->service->getContentsForPlacement('alice', ['folderPath' => '//']));
		}

		public function testUserFolderFailureIsNotFound(): void {
			$rootFolder = $this->createMock(IRootFolder::class);
			$rootFolder->method('getUserFolder')->willThrowException(new RuntimeException('x'));
			$service = new FilesWidgetService($rootFolder, $this->createMock(IURLGenerator::class), $this->createMock(IPreview::class));
			$this->expectException(FolderNotFoundException::class);

			$service->resolveConfiguredFolder('alice', []);
		}

		public function testUnreadableConfiguredFolderIsNoAccess(): void {
			$this->userFolder->method('get')->willReturn($this->folder(id: 30, readable: false));
			$this->expectException(NoAccessException::class);

			$this->service->resolveConfiguredFolder('alice', ['folderPath' => 'Docs']);
		}

		/**
		 * Sub-paths that are refused as not found.
		 *
		 * @return array<string,array{0:string}>
		 */
		public static function refusedSubPaths(): array {
			return [
				'traversal'     => ['a/../b'],
				'not found'     => ['nf'],
				'not permitted' => ['np'],
				'a file'        => ['file'],
				'unreadable'    => ['locked'],
			];
		}

		/**
		 * @dataProvider refusedSubPaths
		 */
		public function testRefusedSubPathIsNotFound(string $subPath): void {
			$this->userFolder->method('get')->willReturnCallback(function (string $path) use ($subPath): Node {
				$this->assertNotSame('a/../b', $subPath, 'a traversal must not be looked up');
				return match ($path) {
					'nf' => throw new NotFoundException('x'),
					'np' => throw new NotPermittedException('x'),
					'file' => $this->file(3, 'a'),
					default => $this->folder(id: 4, readable: false),
				};
			});
			$this->expectException(FolderNotFoundException::class);

			$this->service->getContentsForPlacement('alice', [], ['currentSubPath' => $subPath]);
		}

		public function testOtherLookupErrorsPropagate(): void {
			$this->userFolder->method('get')->willThrowException(new RuntimeException('boom'));
			$this->expectException(RuntimeException::class);
			$this->expectExceptionMessage('boom');

			$this->service->getContentsForPlacement('alice', [], ['currentSubPath' => 'x']);
		}

		public function testSubPathIsLookedUpRelativeToTheRoot(): void {
			$sub = $this->folder(id: 8);
			$sub->method('getDirectoryListing')->willReturn([$this->file(1, 'in-sub.txt')]);
			$this->userFolder->expects($this->once())->method('get')->with('a/b')->willReturn($sub);

			$this->assertSame(['in-sub.txt'], $this->names($this->service->getContentsForPlacement('alice', [], ['currentSubPath' => '/a/b/'])));
		}

		public function testUnreadableTargetIsNoAccess(): void {
			$this->userFolder->method('get')->willReturn($this->folder(id: 8));
			$sub = $this->folder(id: 9, readable: true);
			$root = $this->createMock(Folder::class);
			$root->method('isReadable')->willReturnOnConsecutiveCalls(true, false);
			$this->userFolder->method('getById')->willReturn([$root]);
			$this->expectException(NoAccessException::class);

			$this->service->getContentsForPlacement('alice', ['fileId' => 4]);
		}

		/**
		 * Sort configs and the order they give.
		 *
		 * @return array<string,array{0:array<string,mixed>,1:list<string>}>
		 */
		public static function sorts(): array {
			return [
				'default name'    => [[], ['a10.txt', 'B2.png', 'b3.txt', 'f50']],
				'unknown'         => [['sortBy' => 'owner'], ['a10.txt', 'B2.png', 'b3.txt', 'f50']],
				'name desc'       => [['sortDescending' => true], ['f50', 'b3.txt', 'B2.png', 'a10.txt']],
				'modified'        => [['sortBy' => 'modified'], ['f50', 'b3.txt', 'a10.txt', 'B2.png']],
				'size'            => [['sortBy' => 'size'], ['b3.txt', 'B2.png', 'f50', 'a10.txt']],
				'type'            => [['sortBy' => 'type'], ['f50', 'B2.png', 'b3.txt', 'a10.txt']],
				'mime filter'     => [['mimeTypeFilter' => [' IMAGE/* ', 7, '']], ['B2.png', 'f50']],
				'exact mime'      => [['mimeTypeFilter' => ['text/plain']], ['a10.txt', 'b3.txt', 'f50']],
			];
		}

		/**
		 * @dataProvider sorts
		 */
		public function testListingOrderAndFilter(array $config, array $expected): void {
			$folder = $this->folder(id: 50);
			$folder->method('getSize')->willReturn(5);
			$folder->method('getMTime')->willReturn(10);
			$hidden = $this->createMock(File::class);
			$hidden->method('isReadable')->willReturn(false);
			$this->userFolder->method('getDirectoryListing')->willReturn([
				$this->file(1, 'b3.txt', 'text/plain', 1, 200),
				$hidden,
				$this->file(2, 'B2.png', 'image/png', 3, 400),
				$folder,
				$this->file(3, 'a10.txt', 'text/plain', 9, 300),
			]);

			$this->assertSame($expected, $this->names($this->service->getContentsForPlacement('alice', $config)));
		}

		/**
		 * Page windows and the names / cursor they give over five items.
		 *
		 * @return array<string,array{0:array<string,mixed>,1:list<string>,2:string|null}>
		 */
		public static function pages(): array {
			return [
				'first two'  => [['limit' => 2], ['a', 'b'], '2'],
				'from two'   => [['limit' => 2, 'cursor' => '2'], ['c', 'd'], '4'],
				'last'       => [['limit' => 2, 'cursor' => '4'], ['e'], null],
				'bad cursor' => [['limit' => 2, 'cursor' => '-1'], ['a', 'b'], '2'],
				'zero limit' => [['limit' => 0], ['a', 'b', 'c', 'd', 'e'], null],
				'past end'   => [['cursor' => '9'], [], null],
			];
		}

		/**
		 * @dataProvider pages
		 */
		public function testPaging(array $page, array $names, ?string $cursor): void {
			$this->userFolder->method('getDirectoryListing')->willReturn(array_map(
				fn (string $name): File => $this->file(1, $name),
				['e', 'd', 'c', 'b', 'a']
			));

			$result = $this->service->getContentsForPlacement('alice', [], $page);

			$this->assertSame([$names, $cursor], [$this->names($result), $result['nextCursor']]);
		}

		private function upload(string $name, string $contents = 'hello', bool $genuine = true, int $error = UPLOAD_ERR_OK, ?int $size = null): array {
			$tmp = tempnam(sys_get_temp_dir(), 'lpup');
			file_put_contents($tmp, $contents);
			$this->tmpFiles[] = $tmp;
			if ($genuine === true) {
				$GLOBALS['launchpadTestUploads'][] = $tmp;
			}

			return ['name' => $name, 'tmp_name' => $tmp, 'size' => $size ?? strlen($contents), 'error' => $error];
		}

		public function testUploadNotAllowedIsRefusedBeforeAnyLookup(): void {
			$this->rootFolder->expects($this->never())->method('getUserFolder');
			$this->expectException(NoAccessException::class);

			$this->service->uploadFiles('alice', ['allowUpload' => 0], null, []);
		}

		public function testReadOnlyTargetRefusesUpload(): void {
			$this->userFolder->method('get')->willReturn($this->folder(id: 8, updateable: false));
			$this->expectException(NoAccessException::class);

			$this->service->uploadFiles('alice', ['allowUpload' => true], 'sub', []);
		}

		public function testUploadScreeningCodesAndNaming(): void {
			$this->userFolder = $this->folder(id: 1, path: '/alice/files');
			$rootFolder = $this->createMock(IRootFolder::class);
			$rootFolder->method('getUserFolder')->willReturn($this->userFolder);
			$service = new FilesWidgetService($rootFolder, $this->createMock(IURLGenerator::class), $this->createMock(IPreview::class));
			$taken = ['taken.txt', 'taken (1).txt', '.env', 'noext'];
			$this->userFolder->method('nodeExists')->willReturnCallback(static fn (string $path): bool => in_array($path, $taken, true));
			$written = [];
			$this->userFolder->method('newFile')->willReturnCallback(function (string $path) use (&$written): File {
				$written[] = $path;
				if ($path === 'boom.txt') {
					throw new RuntimeException('disk full');
				}

				$file = $this->file(100 + count($written), $path);
				$file->method('fopen')->willReturn(fopen('php://memory', 'w'));
				return $file;
			});

			$result = $service->uploadFiles('alice', ['allowUpload' => true, 'mimeTypeFilter' => ['text/*']], null, [
				$this->upload(''),
				$this->upload('err.txt', 'x', true, UPLOAD_ERR_PARTIAL),
				['name' => 'notmp.txt', 'tmp_name' => '', 'size' => 1, 'error' => UPLOAD_ERR_OK],
				$this->upload('crafted.txt', 'x', false),
				$this->upload('huge.txt', 'x', true, UPLOAD_ERR_OK, FilesWidgetService::MAX_UPLOAD_BYTES + 1),
				$this->upload('image.txt', "\x89PNG\r\n\x1a\n" . str_repeat("\0", 32)),
				$this->upload('taken.txt'),
				$this->upload('a/b\\c.txt'),
				$this->upload('..'),
				$this->upload('.env'),
				$this->upload('noext'),
				$this->upload('boom.txt'),
			]);

			$this->assertSame([
				['name' => '', 'error' => 'upload_failed'],
				['name' => 'err.txt', 'error' => 'upload_failed'],
				['name' => 'notmp.txt', 'error' => 'upload_failed'],
				['name' => 'crafted.txt', 'error' => 'upload_failed'],
				['name' => 'huge.txt', 'error' => 'file_too_large'],
				['name' => 'image.txt', 'error' => 'file_type_not_allowed'],
				['name' => 'boom.txt', 'error' => 'storage_failed'],
			], $result['errors']);
			$this->assertSame(['taken (2).txt', 'a_b_c.txt', 'upload', '.env (1)', 'noext (1)', 'boom.txt'], $written);
			$this->assertSame(['taken (2).txt', 'a_b_c.txt', 'upload', '.env (1)', 'noext (1)'], array_column($result['uploaded'], 'name'));
		}

		public function testUploadWithoutFilterAcceptsAnyType(): void {
			$this->userFolder->method('nodeExists')->willReturn(false);
			$file = $this->file(5, 'image.png');
			$file->method('fopen')->willReturn(fopen('php://memory', 'w'));
			$this->userFolder->method('newFile')->willReturn($file);

			$result = $this->service->uploadFiles('alice', ['allowUpload' => true], null, [$this->upload('image.png', "\x89PNG\r\n\x1a\n")]);

			$this->assertSame([], $result['errors']);
			$this->assertCount(1, $result['uploaded']);
		}

		private function node(int $id, string $path, ?Node $parent, bool $deletable = true): File {
			$node = $this->createMock(File::class);
			$node->method('getId')->willReturn($id);
			$node->method('getPath')->willReturn($path);
			$node->method('isDeletable')->willReturn($deletable);
			if ($parent === null) {
				$node->method('getParent')->willThrowException(new NotFoundException('top'));
			} else {
				$node->method('getParent')->willReturn($parent);
			}

			return $node;
		}

		public function testDeleteNotAllowedIsRefusedBeforeAnyLookup(): void {
			$this->rootFolder->expects($this->never())->method('getUserFolder');
			$this->expectException(NoAccessException::class);

			$this->service->deleteFile('alice', [], 5);
		}

		/**
		 * Delete cases and their outcome.
		 *
		 * @return array<string,array{0:string,1:string}>
		 */
		public static function deletes(): array {
			return [
				'missing id'       => ['missing', FolderNotFoundException::class],
				'lookup error'     => ['lookup', FolderNotFoundException::class],
				'outside root'     => ['outside', NoAccessException::class],
				'parent error'     => ['orphan', NoAccessException::class],
				'too deep'         => ['deep', NoAccessException::class],
				'not deletable'    => ['locked', NoAccessException::class],
				'delete error'     => ['fails', NoAccessException::class],
				'direct child'     => ['child', 'ok'],
				'nested'           => ['nested', 'ok'],
				'the root itself'  => ['root', 'ok'],
			];
		}

		/**
		 * @dataProvider deletes
		 */
		public function testDelete(string $case, string $outcome): void {
			$root = $this->folder(id: 40, path: '/alice/files/Shared');
			$this->userFolder->method('get')->willReturn($root);
			$top = $this->folder(id: 1, path: '/');
			$other = $this->folder(id: 41, path: '/alice/files/Other');
			$other->method('getParent')->willReturn($top);
			$sub = $this->folder(id: 42, path: '/alice/files/Shared/sub');
			$sub->method('getParent')->willReturn($root);
			$deep = $this->node(500, '/d', null);
			for ($i = 0; $i < 33; $i++) {
				$deep = $this->node(600 + $i, '/d' . $i, $deep);
			}

			$target = match ($case) {
				'outside' => $this->node(5, '/alice/files/Other/a', $other),
				'orphan' => $this->node(5, '/a', null),
				'deep' => $deep,
				'locked' => $this->node(5, '/alice/files/Shared/a', $root, false),
				'nested' => $this->node(5, '/alice/files/Shared/sub/a', $sub),
				'root' => $root,
				default => $this->node(5, '/alice/files/Shared/a', $root),
			};
			if ($case === 'fails') {
				$target->method('delete')->willThrowException(new RuntimeException('x'));
			}

			$this->userFolder->method('getById')->willReturnCallback(static function () use ($case, $target): array {
				if ($case === 'lookup') {
					throw new RuntimeException('x');
				}

				return $case === 'missing' ? [] : [$target];
			});

			if ($outcome !== 'ok') {
				$this->expectException($outcome);
			}

			$result = $this->service->deleteFile('alice', ['allowDelete' => true, 'folderPath' => 'Shared'], 5);

			$this->assertSame(['status' => 'success', 'fileId' => 5], $result);
		}
	}//end class
}
