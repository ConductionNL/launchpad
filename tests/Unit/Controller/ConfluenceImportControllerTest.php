<?php

/**
 * Unit tests for ConfluenceImportController: upload checks, dry-run and import error mapping.
 *
 * @category Test
 * @package  Unit\Controller
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\Controller\ConfluenceImportController;
use OCA\LaunchPad\Service\ConfluenceImportService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\Controller\ConfluenceImportController
 */
class ConfluenceImportControllerTest extends TestCase {
	private ConfluenceImportService&MockObject $service;
	private IUserSession&MockObject $session;
	private ConfluenceImportController $controller;
	private array $savedFiles;

	protected function setUp(): void {
		$this->savedFiles = $_FILES;
		$this->service = $this->createMock(ConfluenceImportService::class);
		$this->session = $this->createMock(IUserSession::class);
		$this->controller = new ConfluenceImportController($this->createMock(IRequest::class), $this->service, $this->session);
	}

	protected function tearDown(): void {
		$_FILES = $this->savedFiles;
	}

	private function upload(string $tmp = '/tmp/phpUpload'): void {
		$_FILES = ['file' => ['tmp_name' => $tmp, 'name' => 'space.zip']];
	}

	public function testDryRunWithoutUploadIsABadRequest(): void {
		$_FILES = [];
		$this->service->expects($this->never())->method('dryRun');

		$response = $this->controller->dryRun();

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'No file uploaded under field "file".'], $response->getData());
	}

	public function testEmptyTmpNameIsABadRequest(): void {
		$this->upload('');
		$this->service->expects($this->never())->method('import');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $this->controller->import()->getStatus());
	}

	public function testNonArrayUploadIsABadRequest(): void {
		$_FILES = ['file' => 'nope'];

		$this->assertSame(Http::STATUS_BAD_REQUEST, $this->controller->dryRun()->getStatus());
	}

	public function testDryRunReturnsTheServiceReport(): void {
		$this->upload();
		$this->service->expects($this->once())->method('dryRun')->with('/tmp/phpUpload')->willReturn(['pages' => 3]);

		$response = $this->controller->dryRun();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['pages' => 3], $response->getData());
	}

	public function testDryRunInvalidArchiveIsABadRequestWithTheMessage(): void {
		$this->upload();
		$this->service->method('dryRun')->willThrowException(new InvalidArgumentException('Not a zip'));

		$response = $this->controller->dryRun();

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Not a zip'], $response->getData());
	}

	public function testDryRunUnexpectedFailureIsA500(): void {
		$this->upload();
		$this->service->method('dryRun')->willThrowException(new RuntimeException('disk'));

		$response = $this->controller->dryRun();

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame(['error' => 'Confluence dry-run failed: disk'], $response->getData());
	}

	public function testImportPassesTheUserAndParent(): void {
		$this->upload();
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$this->session->method('getUser')->willReturn($user);
		$this->service->expects($this->once())
			->method('import')
			->with('/tmp/phpUpload', 'admin', 'parent-uuid')
			->willReturn(['created' => 2]);

		$response = $this->controller->import(parentUuid: 'parent-uuid');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['created' => 2], $response->getData());
	}

	public function testImportTreatsAnEmptyParentAsNoneAndNoSessionAsEmptyUser(): void {
		$this->upload();
		$this->session->method('getUser')->willReturn(null);
		$this->service->expects($this->once())
			->method('import')
			->with('/tmp/phpUpload', '', null)
			->willReturn([]);

		$this->controller->import(parentUuid: '');
	}

	public function testImportInvalidArchiveIsABadRequest(): void {
		$this->upload();
		$this->service->method('import')->willThrowException(new InvalidArgumentException('Bad export'));

		$response = $this->controller->import();

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Bad export'], $response->getData());
	}

	public function testImportUnexpectedFailureIsA500(): void {
		$this->upload();
		$this->service->method('import')->willThrowException(new RuntimeException('db'));

		$response = $this->controller->import();

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame(['error' => 'Confluence import failed: db'], $response->getData());
	}
}
