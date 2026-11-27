<?php

/**
 * AnnouncementReminderControllerTest
 *
 * Send reminder (REQ-ANN-009): through the manage check, for the session
 * user; a second one the same day is 400; signed out is the guard's answer.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 */

declare(strict_types=1);

namespace Unit\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\Controller\AnnouncementReminderController;
use OCA\LaunchPad\Service\AnnouncementReminder;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUser;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AnnouncementReminderController.
 *
 * @covers \OCA\LaunchPad\Controller\AnnouncementReminderController
 */
class AnnouncementReminderControllerTest extends TestCase {

	private const UUID = '11111111-2222-4333-8444-555555555555';

	/**
	 * The controller with a reminder double, refusing the listed actions.
	 *
	 * @param AnnouncementReminder $reminder The reminder.
	 * @param list<string>         $refused  Actions the matrix refuses.
	 * @param bool                 $signedIn Whether someone is signed in.
	 *
	 * @return AnnouncementReminderController
	 */
	private function controller(AnnouncementReminder $reminder, array $refused = [], bool $signedIn = true): AnnouncementReminderController {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('femke');
		$guard = $this->createMock(originalClassName: RequestGuard::class);
		$guard->method('actionRefusal')->willReturnCallback(
			static fn (string $action): ?JSONResponse => in_array(needle: $action, haystack: $refused, strict: true) === true ? new JSONResponse([], 403) : null
		);
		$guard->method('sessionUser')->willReturn($signedIn === true ? $user : new JSONResponse([], 401));

		return new AnnouncementReminderController(
			request: $this->createMock(originalClassName: IRequest::class),
			reminder: $reminder,
			guard: $guard
		);
	}//end controller()

	/**
	 * Sent once, refused the second time, 403 without the setting, 401 signed out.
	 *
	 * @return void
	 */
	public function testRemindIsForManagersOnceADay(): void {
		$reminder = $this->createMock(originalClassName: AnnouncementReminder::class);
		$reminder->method('remind')->with('femke', self::UUID)->willReturnOnConsecutiveCalls(
			['uuid' => self::UUID, 'sent' => 3, 'remindedAt' => '2026-11-27T16:00:00+00:00'],
			$this->throwException(exception: new InvalidArgumentException('A reminder was already sent in the last 24 hours'))
		);
		$controller = $this->controller(reminder: $reminder);

		$this->assertSame(expected: 3, actual: $controller->remind(self::UUID)->getData()['sent']);
		$this->assertSame(expected: 400, actual: $controller->remind(self::UUID)->getStatus());
		$this->assertSame(expected: 403, actual: $this->controller(reminder: $reminder, refused: ['announcement.manage'])->remind(self::UUID)->getStatus());
		$this->assertSame(expected: 401, actual: $this->controller(reminder: $reminder, signedIn: false)->remind(self::UUID)->getStatus());
	}//end testRemindIsForManagersOnceADay()
}//end class
