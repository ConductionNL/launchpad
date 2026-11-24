<?php

/**
 * AnnouncementReadControllerTest
 *
 * "I have read this" and the author's read confirmations go through the
 * action check and the session user; a refusal maps to 400, 403 or 404
 * (REQ-ANN-007).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 */

declare(strict_types=1);

namespace Unit\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\Controller\AnnouncementReadController;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Service\AnnouncementReadConfirmations;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AnnouncementReadController.
 *
 * @covers \OCA\LaunchPad\Controller\AnnouncementReadController
 * @uses   \OCA\LaunchPad\Exception\ForbiddenException
 */
class AnnouncementReadControllerTest extends TestCase {

	private const UUID = '11111111-2222-4333-8444-555555555555';

	/**
	 * The confirmations double.
	 *
	 * @var AnnouncementReadConfirmations&MockObject
	 */
	private AnnouncementReadConfirmations $confirmations;

	/**
	 * The actions checked, in order.
	 *
	 * @var list<string>
	 */
	private array $checked = [];

	/**
	 * Set up the double.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->confirmations = $this->createMock(AnnouncementReadConfirmations::class);
	}//end setUp()

	/**
	 * The controller for user `pieter`, refusing the listed actions.
	 *
	 * @param list<string> $refused Actions the matrix refuses.
	 * @param bool         $signedIn Whether someone is signed in.
	 *
	 * @return AnnouncementReadController
	 */
	private function controller(array $refused = [], bool $signedIn = true): AnnouncementReadController {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('pieter');

		$guard = $this->createMock(RequestGuard::class);
		$guard->method('actionRefusal')->willReturnCallback(function (string $action) use ($refused): ?JSONResponse {
			$this->checked[] = $action;
			return in_array($action, $refused, true) ? new JSONResponse(['error' => 'Forbidden'], 403) : null;
		});
		$guard->method('sessionUser')->willReturn($signedIn ? $user : new JSONResponse(['error' => 'Not signed in'], 401));

		return new AnnouncementReadController($this->createMock(IRequest::class), $this->confirmations, $guard);
	}//end controller()

	/**
	 * "I have read this" confirms for the session user; a refusal maps to 400, 403 or 404.
	 *
	 * @return void
	 */
	public function testConfirmAnswersTheReceiptOrTheRefusal(): void {
		$this->confirmations->method('confirm')->with('pieter', self::UUID)->willReturnOnConsecutiveCalls(
			['uuid' => self::UUID, 'confirmed' => true, 'confirmedAt' => '2026-10-27 09:15:00'],
			$this->throwException(new InvalidArgumentException('This announcement does not ask for a read confirmation')),
			$this->throwException(new ForbiddenException(message: 'This announcement is not addressed to you')),
			$this->throwException(new DoesNotExistException('gone'))
		);
		$controller = $this->controller();

		$this->assertTrue($controller->confirm(self::UUID)->getData()['confirmed']);
		$this->assertSame(400, $controller->confirm(self::UUID)->getStatus());
		$this->assertSame(403, $controller->confirm(self::UUID)->getStatus());
		$this->assertSame(404, $controller->confirm(self::UUID)->getStatus());
		$this->assertSame(['announcement.read', 'announcement.read', 'announcement.read', 'announcement.read'], $this->checked);
	}//end testConfirmAnswersTheReceiptOrTheRefusal()

	/**
	 * The receipts report goes through the manage check; signed out is the guard's answer.
	 *
	 * @return void
	 */
	public function testReceiptsAreTheAuthorsReport(): void {
		$this->confirmations->method('report')->with('pieter', self::UUID)->willReturn(['pendingCount' => 2]);

		$this->assertSame(['pendingCount' => 2], $this->controller()->receipts(self::UUID)->getData());
		$this->assertSame(['announcement.manage'], $this->checked);
		$this->assertSame(403, $this->controller(refused: ['announcement.manage'])->receipts(self::UUID)->getStatus());
		$this->assertSame(401, $this->controller(signedIn: false)->receipts(self::UUID)->getStatus());
	}//end testReceiptsAreTheAuthorsReport()
}//end class
