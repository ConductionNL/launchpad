<?php

/**
 * Unit tests for the office tiles endpoints.
 *
 * @category Test
 * @package  OCA\LaunchPad\Tests\Unit\Controller
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\OfficeTilesController;
use OCA\LaunchPad\Exception\InvalidFilenameException;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\OfficeTilesService;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * GET /api/office-tiles and POST /api/office-tiles/create (REQ-OFT-001, REQ-OFT-002).
 *
 * @covers \OCA\LaunchPad\Controller\OfficeTilesController
 * @uses   \OCA\LaunchPad\Service\RequestGuard
 * @uses   \OCA\LaunchPad\Exception\InvalidFilenameException
 * @uses   \OCA\LaunchPad\Exception\ResourceException
 */
class OfficeTilesControllerTest extends TestCase {

	/**
	 * The service double.
	 *
	 * @var OfficeTilesService&MockObject
	 */
	private OfficeTilesService&MockObject $tiles;

	/**
	 * The actions the caller was checked for.
	 *
	 * @var array<int, string>
	 */
	private array $checked = [];

	/**
	 * The controller with the given session user, granted or refused every action.
	 *
	 * @param string|null $uid     The session user, or null for none.
	 * @param bool        $granted Whether the action is granted.
	 *
	 * @return OfficeTilesController
	 */
	private function controller(?string $uid, bool $granted = true): OfficeTilesController {
		$this->tiles = $this->createMock(OfficeTilesService::class);
		$session = $this->createMock(IUserSession::class);
		$user = null;
		if ($uid !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
		}

		$session->method('getUser')->willReturn($user);
		$actionAuth = $this->createMock(ActionAuthService::class);
		$actionAuth->method('requireAction')->willReturnCallback(
			function (IUser $user, string $action) use ($granted): void {
				$this->checked[] = $action;
				if ($granted === false) {
					throw new OCSForbiddenException();
				}
			}
		);

		return new OfficeTilesController(
			$this->createMock(IRequest::class),
			$this->tiles,
			new RequestGuard($session, $this->createMock(IGroupManager::class), $actionAuth)
		);
	}//end controller()

	/**
	 * The pack is the signed-in person's own.
	 *
	 * @return void
	 */
	public function testListReturnsThePackOfTheSessionUser(): void {
		$controller = $this->controller(uid: 'pieter');
		$this->tiles->expects($this->once())->method('packFor')->with('pieter')
			->willReturn([['title' => 'Files', 'linkType' => 'app', 'linkValue' => 'files']]);

		$response = $controller->list();

		$this->assertSame(200, $response->getStatus());
		$this->assertSame(['tiles' => [['title' => 'Files', 'linkType' => 'app', 'linkValue' => 'files']]], $response->getData());
		$this->assertSame(['office-tiles.list'], $this->checked);
	}//end testListReturnsThePackOfTheSessionUser()

	/**
	 * A create goes to the session user's files and answers 201 with the file.
	 *
	 * @return void
	 */
	public function testCreateAnswersTheNewFile(): void {
		$controller = $this->controller(uid: 'pieter');
		$this->tiles->expects($this->once())->method('create')
			->with('pieter', 'richdocuments:.docx', 'Notulen', '/Documents')
			->willReturn(['fileId' => 42, 'path' => '/Documents/Notulen.docx', 'url' => '/f/42']);

		$response = $controller->create(creatorId: 'richdocuments:.docx', name: 'Notulen');

		$this->assertSame(201, $response->getStatus());
		$this->assertSame(42, $response->getData()['fileId']);
		$this->assertSame(['office-tiles.create'], $this->checked);
	}//end testCreateAnswersTheNewFile()

	/**
	 * An unknown creator is a 400 and nothing is created.
	 *
	 * @return void
	 */
	public function testAnUnknownCreatorIsA400(): void {
		$controller = $this->controller(uid: 'pieter');
		$this->tiles->method('create')->willThrowException(new InvalidFilenameException(message: 'Unknown file creator'));

		$response = $controller->create(creatorId: 'nope:.docx', name: 'Notulen');

		$this->assertSame(400, $response->getStatus());
		$this->assertSame('invalid_filename', $response->getData()['error']);
	}//end testAnUnknownCreatorIsA400()

	/**
	 * Without a session the endpoints answer 401 and touch nothing.
	 *
	 * @return void
	 */
	public function testNoSessionIsA401(): void {
		$controller = $this->controller(uid: null);
		$this->tiles->expects($this->never())->method('packFor');
		$this->tiles->expects($this->never())->method('create');

		$this->assertSame(401, $controller->list()->getStatus());
		$this->assertSame(401, $controller->create(creatorId: 'a:.docx', name: 'x')->getStatus());
	}//end testNoSessionIsA401()

	/**
	 * An action narrowed away from the caller is a 403.
	 *
	 * @return void
	 */
	public function testARefusedActionIsA403(): void {
		$controller = $this->controller(uid: 'pieter', granted: false);
		$this->tiles->expects($this->never())->method('create');

		$this->assertSame(403, $controller->list()->getStatus());
		$this->assertSame(403, $controller->create(creatorId: 'a:.docx', name: 'x')->getStatus());
	}//end testARefusedActionIsA403()
}//end class
