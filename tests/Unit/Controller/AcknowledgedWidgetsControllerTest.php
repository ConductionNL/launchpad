<?php

/**
 * AcknowledgedWidgetsControllerTest
 *
 * The list of widgets that ask for confirmation (REQ-ACK-008) goes through
 * the manage check; signed out is the guard's answer.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/dashboard-acknowledgements/spec.md#requirement-an-editor-can-see-who-has-confirmed-req-ack-008
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\AcknowledgedWidgetsController;
use OCA\LaunchPad\Service\AcknowledgedWidgets;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUser;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AcknowledgedWidgetsController.
 *
 * @covers \OCA\LaunchPad\Controller\AcknowledgedWidgetsController
 */
class AcknowledgedWidgetsControllerTest extends TestCase {

	/**
	 * The controller.
	 *
	 * @param JSONResponse|null $refusal  What the action check answers.
	 * @param bool              $signedIn Whether someone is signed in.
	 *
	 * @return AcknowledgedWidgetsController
	 */
	private function controller(?JSONResponse $refusal = null, bool $signedIn = true): AcknowledgedWidgetsController {
		$widgets = $this->createMock(originalClassName: AcknowledgedWidgets::class);
		$widgets->method('list')->willReturn([['announcementKey' => 'ak-1']]);
		$guard = $this->createMock(originalClassName: RequestGuard::class);
		$guard->method('actionRefusal')->with('announcement.manage')->willReturn($refusal);
		$session = new JSONResponse([], 401);
		if ($signedIn === true) {
			$session = $this->createMock(originalClassName: IUser::class);
		}

		$guard->method('sessionUser')->willReturn($session);

		return new AcknowledgedWidgetsController(
			request: $this->createMock(originalClassName: IRequest::class),
			widgets: $widgets,
			guard: $guard
		);
	}//end controller()

	/**
	 * The list for a manager, 403 without the setting, 401 signed out.
	 *
	 * @return void
	 */
	public function testIndex(): void {
		$this->assertSame(expected: ['widgets' => [['announcementKey' => 'ak-1']]], actual: $this->controller()->index()->getData());
		$this->assertSame(expected: 403, actual: $this->controller(refusal: new JSONResponse([], 403))->index()->getStatus());
		$this->assertSame(expected: 401, actual: $this->controller(signedIn: false)->index()->getStatus());
	}//end testIndex()
}//end class
