<?php

/**
 * ScreenLinkControllerTest
 *
 * The three screen link endpoints and their status codes (REQ-KIOSK-006),
 * and the 400 for a playlist update on a screen link.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-8
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\Controller\KioskController;
use OCA\LaunchPad\Controller\ScreenLinkController;
use OCA\LaunchPad\Db\KioskPlaylist;
use OCA\LaunchPad\Service\KioskService;
use OCA\LaunchPad\Service\PublicShareContext;
use OCA\LaunchPad\Service\ScreenLinkService;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Screen link endpoint tests.
 */
class ScreenLinkControllerTest extends TestCase {

	/**
	 * The screen link service.
	 *
	 * @var ScreenLinkService&MockObject
	 */
	private $screenLinks;

	/**
	 * Mock the service.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->screenLinks = $this->createMock(originalClassName: ScreenLinkService::class);
	}//end setUp()

	/**
	 * The controller for a caller.
	 *
	 * @param string|null $userId The signed-in user, null for none.
	 *
	 * @return ScreenLinkController The controller.
	 */
	private function controller(?string $userId='pieter'): ScreenLinkController {
		return new ScreenLinkController(
			request: $this->createMock(originalClassName: IRequest::class),
			screenLinks: $this->screenLinks,
			logger: $this->createMock(originalClassName: LoggerInterface::class),
			userId: $userId
		);
	}//end controller()

	/**
	 * A screen link of "Kantine".
	 *
	 * @return KioskPlaylist The screen link.
	 */
	private function link(): KioskPlaylist {
		$link = new KioskPlaylist();
		$link->setToken('tok');
		$link->setScreenDashboardUuid('dash-kantine');
		return $link;
	}//end link()

	/**
	 * GET answers 200 with the link, 204 without one.
	 *
	 * @return void
	 */
	public function testShowAnswers200WithTheLinkAnd204WithoutOne(): void {
		$this->screenLinks->method('get')->willReturnOnConsecutiveCalls($this->link(), null);

		$with = $this->controller()->show(uuid: 'dash-kantine');
		$this->assertSame(expected: Http::STATUS_OK, actual: $with->getStatus());
		$this->assertSame(expected: 'tok', actual: $with->getData()['token']);
		$this->assertSame(expected: Http::STATUS_NO_CONTENT, actual: $this->controller()->show(uuid: 'dash-kantine')->getStatus());
	}//end testShowAnswers200WithTheLinkAnd204WithoutOne()

	/**
	 * POST answers 201 when the link is made and 200 when it existed.
	 *
	 * @return void
	 */
	public function testCreateAnswers201WhenMadeAnd200WhenItExisted(): void {
		$this->screenLinks->method('create')->willReturnOnConsecutiveCalls(
			['playlist' => $this->link(), 'created' => true],
			['playlist' => $this->link(), 'created' => false]
		);

		$this->assertSame(expected: Http::STATUS_CREATED, actual: $this->controller()->create(uuid: 'dash-kantine')->getStatus());
		$this->assertSame(expected: Http::STATUS_OK, actual: $this->controller()->create(uuid: 'dash-kantine')->getStatus());
	}//end testCreateAnswers201WhenMadeAnd200WhenItExisted()

	/**
	 * A refusal answers 403.
	 *
	 * @return void
	 */
	public function testCreateAnswers403WhenRefused(): void {
		$this->screenLinks->method('create')->willThrowException(new OCSForbiddenException('no'));

		$this->assertSame(expected: Http::STATUS_FORBIDDEN, actual: $this->controller(userId: 'ella')->create(uuid: 'dash-kantine')->getStatus());
	}//end testCreateAnswers403WhenRefused()

	/**
	 * DELETE answers 204, and 403 when refused.
	 *
	 * @return void
	 */
	public function testDestroyAnswers204And403(): void {
		$this->screenLinks->expects($this->exactly(count: 2))->method('revoke')->willReturnOnConsecutiveCalls(
			null,
			$this->throwException(exception: new OCSForbiddenException('no'))
		);

		$this->assertSame(expected: Http::STATUS_NO_CONTENT, actual: $this->controller()->destroy(uuid: 'dash-kantine')->getStatus());
		$this->assertSame(expected: Http::STATUS_FORBIDDEN, actual: $this->controller()->destroy(uuid: 'dash-kantine')->getStatus());
	}//end testDestroyAnswers204And403()

	/**
	 * A caller without a session answers 401; an unexpected error 500.
	 *
	 * @return void
	 */
	public function testAnonymousAnswers401AndAnErrorAnswers500(): void {
		$this->screenLinks->method('get')->willThrowException(new RuntimeException('db down'));

		$this->assertSame(expected: Http::STATUS_UNAUTHORIZED, actual: $this->controller(userId: null)->create(uuid: 'dash-kantine')->getStatus());
		$this->assertSame(expected: Http::STATUS_INTERNAL_SERVER_ERROR, actual: $this->controller()->show(uuid: 'dash-kantine')->getStatus());
	}//end testAnonymousAnswers401AndAnErrorAnswers500()

	/**
	 * Scenario "A screen link does not grow into a rotation": PUT answers 400.
	 *
	 * @return void
	 */
	public function testPlaylistUpdateOnAScreenLinkAnswers400(): void {
		$kiosk = $this->createMock(originalClassName: KioskService::class);
		$kiosk->method('updatePlaylist')->willThrowException(new InvalidArgumentException('A screen link cannot be edited.'));
		$controller = new KioskController(
			request: $this->createMock(originalClassName: IRequest::class),
			kioskService: $kiosk,
			shareContext: $this->createMock(originalClassName: PublicShareContext::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class),
			userId: 'pieter'
		);

		$this->assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $controller->update(id: 7, name: 'x', entries: [])->getStatus());
	}//end testPlaylistUpdateOnAScreenLinkAnswers400()
}//end class
