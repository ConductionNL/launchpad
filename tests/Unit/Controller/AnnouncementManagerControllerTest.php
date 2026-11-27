<?php

/**
 * AnnouncementManagerControllerTest
 *
 * Send reminder (REQ-ANN-009) and the widgets that ask for confirmation
 * (REQ-ACK-008): through the manage check, for the session user; a second
 * reminder the same day is 400; signed out is the guard's answer.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/announcements/spec.md#requirement-seen-and-confirmed-per-announcement-req-ann-009
 */

declare(strict_types=1);

namespace Unit\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\Controller\AnnouncementManagerController;
use OCA\LaunchPad\Kernel\Contracts\AcknowledgementServiceInterface;
use OCA\LaunchPad\Service\AnnouncementReminder;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUser;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AnnouncementManagerController.
 *
 * @covers \OCA\LaunchPad\Controller\AnnouncementManagerController
 */
class AnnouncementManagerControllerTest extends TestCase {

	private const UUID = '11111111-2222-4333-8444-555555555555';

	/**
	 * The acknowledgement service double.
	 *
	 * @var AcknowledgementServiceInterface
	 */
	private AcknowledgementServiceInterface $acknowledgements;

	/**
	 * Set up the double.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->acknowledgements = $this->createMock(originalClassName: AcknowledgementServiceInterface::class);
	}//end setUp()

	/**
	 * The controller with a reminder double, refusing the listed actions.
	 *
	 * @param AnnouncementReminder $reminder The reminder.
	 * @param list<string>         $refused  Actions the matrix refuses.
	 * @param bool                 $signedIn Whether someone is signed in.
	 *
	 * @return AnnouncementManagerController
	 */
	private function controller(AnnouncementReminder $reminder, array $refused = [], bool $signedIn = true): AnnouncementManagerController {
		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('femke');
		$guard = $this->createMock(originalClassName: RequestGuard::class);
		$guard->method('actionRefusal')->willReturnCallback(
			static function (string $action) use ($refused): ?JSONResponse {
				if (in_array(needle: $action, haystack: $refused, strict: true) === true) {
					return new JSONResponse([], 403);
				}

				return null;
			}
		);
		$session = new JSONResponse([], 401);
		if ($signedIn === true) {
			$session = $user;
		}

		$guard->method('sessionUser')->willReturn($session);

		return new AnnouncementManagerController(
			request: $this->createMock(originalClassName: IRequest::class),
			reminder: $reminder,
			guard: $guard,
			acknowledgements: $this->acknowledgements
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

	/**
	 * The widgets that ask for confirmation, through the manage check (REQ-ACK-008).
	 *
	 * @return void
	 */
	public function testWidgets(): void {
		$this->acknowledgements->method('listAcknowledged')->willReturn([['announcementKey' => 'ak-1']]);
		$reminder = $this->createMock(originalClassName: AnnouncementReminder::class);

		$this->assertSame(expected: ['widgets' => [['announcementKey' => 'ak-1']]], actual: $this->controller(reminder: $reminder)->widgets()->getData());
		$this->assertSame(expected: 403, actual: $this->controller(reminder: $reminder, refused: ['announcement.manage'])->widgets()->getStatus());
	}//end testWidgets()
}//end class
