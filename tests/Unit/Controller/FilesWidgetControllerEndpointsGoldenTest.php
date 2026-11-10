<?php

/**
 * Files widget controller endpoints golden test
 *
 * Pins what the contents, upload and delete endpoints answer for every
 * guard and every service outcome, and what they log.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\FilesWidgetController;
use OCA\LaunchPad\Controller\FilesWidgetSources;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Exception\FolderNotFoundException;
use OCA\LaunchPad\Exception\NoAccessException;
use OCA\LaunchPad\Service\FilesWidgetService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Guards, outcomes and logs of the three files widget endpoints.
 */
class FilesWidgetControllerEndpointsGoldenTest extends TestCase {

	/**
	 * Log lines written in the current case.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	private array $logged = [];

	/**
	 * Service calls made in the current case.
	 *
	 * @var array<int, array<int, mixed>>
	 */
	private array $served = [];

	/**
	 * Every endpoint × case gives the recorded status, body, service call and log line.
	 *
	 * @return void
	 */
	public function testEveryEndpointAnswersAsRecorded(): void {
		$cases = [
			'no session' => [null, 1, null],
			'unknown placement' => ['alice', 404, null],
			'no permission' => ['alice', 2, null],
			'ok' => ['alice', 1, null],
			'folder gone' => ['alice', 1, new FolderNotFoundException()],
			'no access' => ['alice', 1, new NoAccessException()],
			'crash' => ['alice', 1, new RuntimeException('boom')],
		];

		$actual = [];
		foreach (['contents', 'upload', 'destroy'] as $endpoint) {
			foreach ($cases as $name => [$uid, $placementId, $failure]) {
				$this->logged = [];
				$this->served = [];
				$controller   = $this->controller(uid: $uid, failure: $failure);
				$response     = match ($endpoint) {
					'contents' => $controller->contents($placementId, '/sub', 5, 'c'),
					'upload' => $controller->upload($placementId, '/sub'),
					'destroy' => $controller->destroy($placementId, 77),
				};
				$actual[$endpoint.' / '.$name] = [$response->getStatus(), $response->getData(), $this->served, $this->logged];
			}
		}

		$actual = json_decode((string) json_encode($actual), true);
		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(__DIR__.'/fixtures/files-widget-endpoints.json', json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
			$this->markTestIncomplete('Fixture recorded.');
		}

		$this->assertSame(json_decode((string) file_get_contents(__DIR__.'/fixtures/files-widget-endpoints.json'), true), $actual);
	}//end testEveryEndpointAnswersAsRecorded()

	/**
	 * The uploaded `files[]` entries are normalised from both PHP upload shapes.
	 *
	 * @return void
	 */
	public function testUploadedFilesAreNormalised(): void {
		$normalise = new \ReflectionMethod(FilesWidgetController::class, 'normaliseUploadedFiles');
		$controller = $this->controller(uid: 'alice', failure: null);
		$cases = [
			'none' => null,
			'not an array' => 'x',
			'no name' => ['tmp_name' => '/t'],
			'single' => ['name' => 'a.txt', 'tmp_name' => '/t/a', 'size' => '12', 'error' => '0'],
			'single bare' => ['name' => 'a.txt'],
			'multiple' => ['name' => ['a', 'b'], 'tmp_name' => ['/t/a'], 'size' => [3, 4], 'error' => 'oops'],
			'multiple bare' => ['name' => ['a']],
		];

		$actual = [];
		$saved  = $_FILES;
		foreach ($cases as $name => $files) {
			$_FILES = ($files === null) ? [] : ['files' => $files];
			$actual[$name] = $normalise->invoke($controller);
		}
		$_FILES = $saved;

		$none = UPLOAD_ERR_NO_FILE;
		$this->assertSame(
			[
				'none' => [],
				'not an array' => [],
				'no name' => [],
				'single' => [['name' => 'a.txt', 'tmp_name' => '/t/a', 'size' => 12, 'error' => 0]],
				'single bare' => [['name' => 'a.txt', 'tmp_name' => '', 'size' => 0, 'error' => $none]],
				'multiple' => [
					['name' => 'a', 'tmp_name' => '/t/a', 'size' => 3, 'error' => $none],
					['name' => 'b', 'tmp_name' => '', 'size' => 4, 'error' => $none],
				],
				'multiple bare' => [['name' => 'a', 'tmp_name' => '', 'size' => 0, 'error' => $none]],
			],
			$actual
		);
	}//end testUploadedFilesAreNormalised()

	/**
	 * The controller over a mocked service.
	 *
	 * @param string|null     $uid     The session user, or null for none.
	 * @param \Throwable|null $failure What the service throws, or null to succeed.
	 *
	 * @return FilesWidgetController
	 */
	private function controller(?string $uid, ?\Throwable $failure): FilesWidgetController {
		$service = $this->createMock(FilesWidgetService::class);
		foreach (['getContentsForPlacement', 'uploadFiles', 'deleteFile'] as $method) {
			$service->method($method)->willReturnCallback(
				function (...$args) use ($method, $failure): array {
					$this->served[] = [$method, $args];
					if ($failure !== null) {
						throw $failure;
					}
					return ['done' => $method];
				}
			);
		}

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
			static fn (string $userId, int $dashboardId): bool => $dashboardId !== 20
		);

		$session = $this->createMock(IUserSession::class);
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
			$session->method('getUser')->willReturn($user);
		}

		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('error')->willReturnCallback(
			function (string $message, array $context): void {
				$this->logged[] = [$message, $context];
			}
		);

		return new FilesWidgetController(
			request: $this->createMock(IRequest::class),
			sources: new FilesWidgetSources(service: $service, placementMapper: $placements, logger: $logger),
			permissionService: $permissions,
			userSession: $session
		);
	}//end controller()
}//end class
