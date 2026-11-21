<?php

/**
 * VideoFileServiceTest
 *
 * What the file of a `video` placement resolves to for its viewer
 * (REQ-VID-006): a readable video gets a WebDAV address in the viewer's own
 * space, and a deleted file, a non-video and an unreadable file each get
 * their own status without an address.
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

use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\VideoFileService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\VideoFileService
 */
class VideoFileServiceTest extends TestCase {

	/**
	 * Build the service around one placement and what the filesystem holds.
	 *
	 * @param array<string, mixed> $content    The placement's content.
	 * @param File|null            $viewerFile The file in the viewer's folder, or null.
	 * @param string|null          $anywhere   The path the file has anywhere, or null.
	 * @param string               $widgetId   The placement's widget type.
	 *
	 * @return VideoFileService
	 */
	private function service(array $content, ?File $viewerFile, ?string $anywhere = null, string $widgetId = 'video'): VideoFileService {
		$placement = new WidgetPlacement();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$placement->setWidgetId($widgetId);
		$placement->setContent(json_encode($content));
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters

		$placements = $this->createMock(WidgetPlacementMapper::class);
		$placements->method('find')->willReturnCallback(
			static function (int $id) use ($placement): WidgetPlacement {
				if ($id !== 9) {
					throw new DoesNotExistException(msg: 'no placement');
				}
				return $placement;
			}
		);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getFirstNodeById')->with(42)->willReturn($viewerFile);
		$userFolder->method('getRelativePath')->willReturnCallback(
			static fn (string $path): string => substr($path, strlen('/bob/files'))
		);

		$elsewhere = null;
		if ($anywhere !== null) {
			$elsewhere = $this->createMock(File::class);
			$elsewhere->method('getPath')->willReturn($anywhere);
		}

		$root = $this->createMock(IRootFolder::class);
		$root->method('getUserFolder')->with('bob')->willReturn($userFolder);
		$root->method('getFirstNodeById')->with(42)->willReturn($elsewhere);

		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('getWebroot')->willReturn('/nc');

		return new VideoFileService(rootFolder: $root, placements: $placements, urls: $urls);
	}//end service()

	/**
	 * A file as the viewer sees it.
	 *
	 * @param string $mime     Its MIME type.
	 * @param bool   $readable Whether the viewer may read it.
	 *
	 * @return File
	 */
	private function file(string $mime, bool $readable = true): File {
		$file = $this->createMock(File::class);
		$file->method('getMimetype')->willReturn($mime);
		$file->method('isReadable')->willReturn($readable);
		$file->method('getPath')->willReturn('/bob/files/Shared/My clip #1.webm');
		return $file;
	}//end file()

	/** The content of a Nextcloud-file placement. */
	private const NC_FILE = ['sourceType' => 'nc-file', 'fileId' => 42];

	public function testEveryVideoTypeTheSpecListsGetsAnAddressInTheViewersSpace(): void {
		foreach (['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo'] as $mime) {
			$this->assertSame(
				['status' => 'ok', 'streamingUrl' => '/nc/remote.php/dav/files/bob/Shared/My%20clip%20%231.webm'],
				$this->service(content: self::NC_FILE, viewerFile: $this->file(mime: $mime))->resolve(viewerId: 'bob', placementId: 9),
				$mime
			);
		}
	}

	public function testANonVideoIsRefusedWithoutAnAddress(): void {
		$this->assertSame(
			['status' => 'not_video'],
			$this->service(content: self::NC_FILE, viewerFile: $this->file(mime: 'application/pdf'))->resolve(viewerId: 'bob', placementId: 9)
		);
	}

	public function testAnUnreadableFileIsNotAccessible(): void {
		$this->assertSame(
			['status' => 'not_accessible'],
			$this->service(content: self::NC_FILE, viewerFile: $this->file(mime: 'video/webm', readable: false))->resolve(viewerId: 'bob', placementId: 9)
		);
	}

	public function testSomeoneElsesFileIsNotAccessible(): void {
		$this->assertSame(
			['status' => 'not_accessible'],
			$this->service(content: self::NC_FILE, viewerFile: null, anywhere: '/admin/files/clip.webm')->resolve(viewerId: 'bob', placementId: 9)
		);
	}

	public function testADeletedFileIsNotFoundEvenWhileInATrashBin(): void {
		foreach ([null, '/admin/files_trashbin/files/clip.webm.d1'] as $anywhere) {
			$this->assertSame(
				['status' => 'not_found'],
				$this->service(content: self::NC_FILE, viewerFile: null, anywhere: $anywhere)->resolve(viewerId: 'bob', placementId: 9),
				(string)$anywhere
			);
		}
	}

	public function testAPlacementWithoutANextcloudFileHasNoFile(): void {
		foreach ([
			[['sourceType' => 'youtube', 'fileId' => 42], 'video', 9],
			[['sourceType' => 'nc-file', 'fileId' => null], 'video', 9],
			[self::NC_FILE, 'text', 9],
			[self::NC_FILE, 'video', 10],
		] as [$content, $widgetId, $placementId]) {
			$this->assertSame(
				['status' => 'no_file'],
				$this->service(content: $content, viewerFile: $this->file(mime: 'video/webm'), widgetId: $widgetId)->resolve(viewerId: 'bob', placementId: $placementId)
			);
		}
	}
}//end class
