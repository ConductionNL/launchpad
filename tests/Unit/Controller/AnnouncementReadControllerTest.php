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
 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-an-announcement-asks-for-a-read-confirmation-req-ann-007
 */

declare(strict_types=1);

namespace Unit\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\Controller\AnnouncementReadController;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Service\AnnouncementReadConfirmations;
use OCA\LaunchPad\Service\AnnouncementReceiptReport;
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
	 * The report double.
	 *
	 * @var AnnouncementReceiptReport&MockObject
	 */
	private AnnouncementReceiptReport $report;

	/**
	 * The request parameters.
	 *
	 * @var array<string, mixed>
	 */
	private array $params = [];

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
		$this->confirmations = $this->createMock(originalClassName: AnnouncementReadConfirmations::class);
		$this->report        = $this->createMock(originalClassName: AnnouncementReceiptReport::class);
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
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('pieter');

		$guard = $this->createMock(originalClassName: RequestGuard::class);
		$guard->method('actionRefusal')->willReturnCallback(function (string $action) use ($refused): ?JSONResponse {
			$this->checked[] = $action;
			if (in_array(needle: $action, haystack: $refused, strict: true) === true) {
				return new JSONResponse(['error' => 'Forbidden'], 403);
			}

			return null;
		});
		$session = new JSONResponse(['error' => 'Not signed in'], 401);
		if ($signedIn === true) {
			$session = $user;
		}

		$guard->method('sessionUser')->willReturn($session);

		$request = $this->createMock(originalClassName: IRequest::class);
		$request->method('getParam')->willReturnCallback(fn (string $key, mixed $default = null): mixed => $this->params[$key] ?? $default);

		return new AnnouncementReadController(
			request: $request,
			confirmations: $this->confirmations,
			report: $this->report,
			guard: $guard
		);
	}//end controller()

	/**
	 * "I have read this" confirms for the session user; a refusal maps to 400, 403 or 404.
	 *
	 * @return void
	 */
	public function testConfirmAnswersTheReceiptOrTheRefusal(): void {
		$this->confirmations->method('confirm')->with('pieter', self::UUID, 'check')->willReturnOnConsecutiveCalls(
			['uuid' => self::UUID, 'confirmed' => true, 'confirmedAt' => '2026-10-27 09:15:00'],
			$this->throwException(exception: new InvalidArgumentException('This announcement does not ask for a read confirmation')),
			$this->throwException(exception: new ForbiddenException(message: 'This announcement is not addressed to you')),
			$this->throwException(exception: new DoesNotExistException('gone'))
		);
		$controller = $this->controller();

		$this->assertTrue(condition: $controller->confirm(self::UUID)->getData()['confirmed']);
		$this->assertSame(expected: 400, actual: $controller->confirm(self::UUID)->getStatus());
		$this->assertSame(expected: 403, actual: $controller->confirm(self::UUID)->getStatus());
		$this->assertSame(expected: 404, actual: $controller->confirm(self::UUID)->getStatus());
		$this->assertSame(expected: ['announcement.read', 'announcement.read', 'announcement.read', 'announcement.read'], actual: $this->checked);
	}//end testConfirmAnswersTheReceiptOrTheRefusal()

	/**
	 * The receipts report goes through the manage check; signed out is the guard's answer.
	 *
	 * @return void
	 */
	public function testReceiptsAreTheAuthorsReport(): void {
		$this->report->method('report')->with('pieter', self::UUID)->willReturn(['pendingCount' => 2, 'departmentLabel' => '']);

		$this->assertSame(expected: ['pendingCount' => 2, 'departmentLabel' => ''], actual: $this->controller()->receipts(self::UUID)->getData());
		$this->assertSame(expected: ['announcement.manage'], actual: $this->checked);
		$this->assertSame(expected: 403, actual: $this->controller(refused: ['announcement.manage'])->receipts(self::UUID)->getStatus());
		$this->assertSame(expected: 401, actual: $this->controller(signedIn: false)->receipts(self::UUID)->getStatus());
	}//end testReceiptsAreTheAuthorsReport()

	/**
	 * The read state of what the reader sees, through the read check.
	 *
	 * @return void
	 */
	public function testStateAnswersWhatTheReaderConfirmed(): void {
		$state = ['seen' => ['u1'], 'confirmed' => ['u1'], 'closed' => [], 'receipts' => []];
		$this->confirmations->method('stateOfVisible')->with('pieter')->willReturn($state);

		$this->assertSame(expected: $state, actual: $this->controller()->state()->getData());
		$this->assertSame(expected: ['announcement.read'], actual: $this->checked);
		$this->assertSame(expected: 403, actual: $this->controller(refused: ['announcement.read'])->state()->getStatus());
	}//end testStateAnswersWhatTheReaderConfirmed()

	/**
	 * Confirm passes `via` (the check or the x) through; without it, the check.
	 *
	 * @return void
	 */
	public function testConfirmViaPassesTheCheckOrTheX(): void {
		$this->confirmations->expects($this->exactly(count: 2))->method('confirm')->willReturnCallback(
			static fn (string $userId, string $uuid, string $via): array => ['uuid' => $uuid, 'confirmed' => true, 'confirmedVia' => $via]
		);

		$this->params = ['via' => 'x'];
		$this->assertSame(expected: 'x', actual: $this->controller()->confirmVia(self::UUID)->getData()['confirmedVia']);
		$this->params = [];
		$this->assertSame(expected: 'check', actual: $this->controller()->confirmVia(self::UUID)->getData()['confirmedVia']);
		$this->assertSame(expected: 403, actual: $this->controller(refused: ['announcement.read'])->confirmVia(self::UUID)->getStatus());
	}//end testConfirmViaPassesTheCheckOrTheX()

	/**
	 * Seen records the uuids of the request; a non-list is an empty list.
	 *
	 * @return void
	 */
	public function testSeenRecordsTheBatch(): void {
		$this->confirmations->expects($this->exactly(count: 2))->method('markSeen')->willReturnCallback(
			static fn (string $userId, array $uuids): array => $uuids
		);

		$this->params = ['uuids' => ['a', 'b']];
		$this->assertSame(expected: ['recorded' => ['a', 'b']], actual: $this->controller()->seen()->getData());
		$this->params = ['uuids' => 'a'];
		$this->assertSame(expected: ['recorded' => []], actual: $this->controller()->seen()->getData());
		$this->assertSame(expected: ['announcement.read', 'announcement.read'], actual: $this->checked);
	}//end testSeenRecordsTheBatch()

	/**
	 * The list counts go through the manage check and take the uuids of the request.
	 *
	 * @return void
	 */
	public function testCountsAreForManagers(): void {
		$this->report->method('countsFor')->willReturnCallback(
			static fn (array $uuids): array => array_fill_keys($uuids, ['seen' => 1, 'confirmed' => 0])
		);

		$this->params = ['uuids' => ['a']];
		$this->assertEquals(expected: ['counts' => (object) ['a' => ['seen' => 1, 'confirmed' => 0]]], actual: $this->controller()->counts()->getData());
		$this->params = ['uuids' => 'a'];
		$this->assertEquals(expected: ['counts' => (object) []], actual: $this->controller()->counts()->getData());
		$this->assertSame(expected: 403, actual: $this->controller(refused: ['announcement.manage'])->counts()->getStatus());
	}//end testCountsAreForManagers()
}//end class
