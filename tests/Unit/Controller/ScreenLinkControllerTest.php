<?php

/**
 * ScreenLinkControllerTest
 *
 * The three screen link endpoints and their status codes (REQ-KIOSK-006).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/dashboard-show-on-a-screen/tasks.md#task-8
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\KioskController;
use OCA\LaunchPad\Controller\ScreenLinkController;
use OCA\LaunchPad\Db\KioskPlaylist;
use OCA\LaunchPad\Service\KioskService;
use OCA\LaunchPad\Service\PublicShareContext;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ScreenLinkControllerTest extends TestCase {

	/** @var KioskService&MockObject */
	private $kioskService;

	protected function setUp(): void {
		$this->kioskService = $this->createMock(KioskService::class);
	}

	private function controller(?string $userId = 'pieter'): ScreenLinkController {
		return new ScreenLinkController(
			request: $this->createMock(IRequest::class),
			kioskService: $this->kioskService,
			logger: $this->createMock(LoggerInterface::class),
			userId: $userId
		);
	}

	private function link(): KioskPlaylist {
		$link = new KioskPlaylist();
		$link->setToken('tok');
		$link->setScreenDashboardUuid('dash-kantine');
		return $link;
	}

	public function testShowAnswers200WithTheLink(): void {
		$this->kioskService->method('getScreenLink')->willReturn($this->link());

		$response = $this->controller()->show(uuid: 'dash-kantine');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame('tok', $response->getData()['token']);
	}

	public function testShowAnswers204WithoutALink(): void {
		$this->kioskService->method('getScreenLink')->willReturn(null);

		$this->assertSame(Http::STATUS_NO_CONTENT, $this->controller()->show(uuid: 'dash-kantine')->getStatus());
	}

	public function testCreateAnswers201WhenMadeAnd200WhenItExisted(): void {
		$this->kioskService->method('createScreenLink')->willReturnOnConsecutiveCalls(
			['playlist' => $this->link(), 'created' => true],
			['playlist' => $this->link(), 'created' => false]
		);

		$this->assertSame(Http::STATUS_CREATED, $this->controller()->create(uuid: 'dash-kantine')->getStatus());
		$this->assertSame(Http::STATUS_OK, $this->controller()->create(uuid: 'dash-kantine')->getStatus());
	}

	public function testCreateAnswers403WhenRefused(): void {
		$this->kioskService->method('createScreenLink')->willThrowException(new OCSForbiddenException('no'));

		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller(userId: 'ella')->create(uuid: 'dash-kantine')->getStatus());
	}

	public function testDestroyAnswers204And403(): void {
		$this->kioskService->expects($this->exactly(2))->method('revokeScreenLink')->willReturnOnConsecutiveCalls(
			null,
			$this->throwException(new OCSForbiddenException('no'))
		);

		$this->assertSame(Http::STATUS_NO_CONTENT, $this->controller()->destroy(uuid: 'dash-kantine')->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller()->destroy(uuid: 'dash-kantine')->getStatus());
	}

	public function testAnonymousCallerAnswers401(): void {
		$this->kioskService->expects($this->never())->method('createScreenLink');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $this->controller(userId: null)->create(uuid: 'dash-kantine')->getStatus());
	}

	public function testUnexpectedErrorAnswers500(): void {
		$this->kioskService->method('getScreenLink')->willThrowException(new RuntimeException('db down'));

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $this->controller()->show(uuid: 'dash-kantine')->getStatus());
	}

	public function testPlaylistUpdateOnAScreenLinkAnswers400(): void {
		$this->kioskService->method('updatePlaylist')->willThrowException(new \InvalidArgumentException('A screen link cannot be edited.'));
		$controller = new KioskController(
			request: $this->createMock(IRequest::class),
			kioskService: $this->kioskService,
			shareContext: $this->createMock(PublicShareContext::class),
			logger: $this->createMock(LoggerInterface::class),
			userId: 'pieter'
		);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $controller->update(id: 7, name: 'x', entries: [])->getStatus());
	}
}
