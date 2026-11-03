<?php

/**
 * Files widget contents golden test
 *
 * Pins the answer of the contents endpoint: no session, an unknown
 * placement, a placement the caller may not add widgets to, and the pages
 * that a path, page size and cursor select from a real files widget
 * service, against a recorded fixture.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\FilesWidgetController;
use OCA\LaunchPad\Controller\FilesWidgetSources;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\FilesWidgetService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\IPreview;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The endpoint answers, case by case.
 *
 * @covers \OCA\LaunchPad\Controller\FilesWidgetController
 * @uses   \OCA\LaunchPad\Controller\FilesWidgetSources
 * @uses   \OCA\LaunchPad\Service\FilesWidgetService
 * @uses   \OCA\LaunchPad\Db\WidgetPlacement
 * @uses   \OCA\LaunchPad\Exception\FolderNotFoundException
 * @uses   \OCA\LaunchPad\Exception\NoAccessException
 * @uses   \OCA\LaunchPad\Exception\ResourceException
 */
class FilesWidgetControllerContentsGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/files-widget-contents.json';

	/**
	 * A controller over a real service, a folder of 30 files and one
	 * sub-folder, for the given session user.
	 *
	 * @param string|null $uid The session user, or null for none.
	 *
	 * @return FilesWidgetController
	 */
	private function controller(?string $uid): FilesWidgetController {
		$files = [];
		for ($i = 1; $i <= 30; $i++) {
			$file = $this->createMock(File::class);
			$file->method('getId')->willReturn($i);
			$file->method('getName')->willReturn(sprintf('f%02d.txt', $i));
			$file->method('getMimetype')->willReturn('text/plain');
			$file->method('isReadable')->willReturn(true);
			$file->method('getInternalPath')->willReturn(sprintf('f%02d.txt', $i));
			$file->method('getSize')->willReturn(100 + $i);
			$file->method('getMTime')->willReturn(1700000000 + $i);
			$file->method('getPermissions')->willReturn(1);
			$files[] = $file;
		}
		$sub = $this->createMock(Folder::class);
		$sub->method('isReadable')->willReturn(true);
		$sub->method('getDirectoryListing')->willReturn([]);
		$folder = $this->createMock(Folder::class);
		$folder->method('isReadable')->willReturn(true);
		$folder->method('getDirectoryListing')->willReturn($files);
		$folder->method('get')->willReturnCallback(
			static function (string $path) use ($sub): Folder {
				if ($path === 'sub') {
					return $sub;
				}
				throw new NotFoundException($path);
			}
		);
		$userFolder = $this->createMock(Folder::class);
		$userFolder->method('getById')->willReturn([$folder]);
		$root = $this->createMock(IRootFolder::class);
		$root->method('getUserFolder')->willReturn($userFolder);

		$placements = $this->createMock(WidgetPlacementMapper::class);
		$placements->method('find')->willReturnCallback(
			static function (int $id): WidgetPlacement {
				if ($id === 404) {
					throw new DoesNotExistException('gone');
				}
				$placement = new WidgetPlacement();
				$placement->setId($id);
				$placement->setDashboardId($id * 10);
				$placement->setContentArray(['fileId' => 9]);
				return $placement;
			}
		);
		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('canAddWidget')->willReturnCallback(
			static fn (string $userId, int $dashboardId): bool => $userId === 'alice' && $dashboardId !== 20
		);
		$session = $this->createMock(IUserSession::class);
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
			$session->method('getUser')->willReturn($user);
		}

		return new FilesWidgetController(
			request: $this->createMock(IRequest::class),
			sources: new FilesWidgetSources(
				service: new FilesWidgetService(
					rootFolder: $root,
					urlGenerator: $this->createMock(IURLGenerator::class),
					previewManager: $this->createMock(IPreview::class)
				),
				placementMapper: $placements,
				logger: $this->createMock(LoggerInterface::class)
			),
			permissionService: $permissions,
			userSession: $session
		);
	}//end controller()

	/**
	 * Every case answers as recorded.
	 *
	 * @return void
	 */
	public function testAnswersMatchTheRecordedFixture(): void {
		$cases = [
			'no session'        => [null, ['placementId' => 1]],
			'unknown placement' => ['alice', ['placementId' => 404]],
			'no permission'     => ['alice', ['placementId' => 2]],
			'other user'        => ['bob', ['placementId' => 1]],
			'defaults'          => ['alice', ['placementId' => 1]],
			'page and cursor'   => ['alice', ['placementId' => 1, 'limit' => 4, 'cursor' => '8']],
			'sub folder'        => ['alice', ['placementId' => 1, 'currentPath' => '/sub']],
			'missing folder'    => ['alice', ['placementId' => 1, 'currentPath' => '/none']],
			'all of them'       => ['alice', ['placementId' => 1, 'currentPath' => '/', 'limit' => 25, 'cursor' => '25']],
		];
		$actual = [];
		foreach ($cases as $name => [$uid, $args]) {
			$response      = $this->controller($uid)->contents(...$args);
			$data          = $response->getData();
			$data['items'] = array_map(static fn (array $item): string => $item['name'], $data['items'] ?? []);
			$actual[$name] = ['status' => $response->getStatus(), 'data' => $data];
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
			$this->markTestIncomplete('Fixture recorded.');
		}

		$this->assertSame(json_decode((string) file_get_contents(self::FIXTURE), true), $actual);
	}//end testAnswersMatchTheRecordedFixture()
}//end class
