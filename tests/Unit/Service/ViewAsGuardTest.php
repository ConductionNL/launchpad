<?php

/**
 * ViewAsGuardTest
 *
 * Who may preview a widget's rules as another person (REQ-CVUI-006).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/visibility-dialog-board-sync/specs/conditional-visibility-editor/spec.md#requirement-the-rules-dialog-as-lpzichtbaarheid-draws-it-req-cvui-006
 */

declare(strict_types=1);

namespace Unit\Service;

use Exception;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\ViewAsGuard;
use PHPUnit\Framework\TestCase;

/**
 * The View as guard.
 *
 * @covers \OCA\LaunchPad\Service\ViewAsGuard
 */
class ViewAsGuardTest extends TestCase {

	/**
	 * The guard for a signed-in user, with alice owning placement 10.
	 *
	 * @param string|null $caller The signed-in user.
	 *
	 * @return ViewAsGuard
	 */
	private function guard(?string $caller): ViewAsGuard {
		$permissions = $this->createMock(originalClassName: PermissionService::class);
		$permissions->method('verifyPlacementOwnership')->willReturnCallback(
			static function (string $userId, int $placementId): WidgetPlacement {
				if ($userId !== 'alice' || $placementId !== 10) {
					throw new Exception(message: 'denied');
				}

				return new WidgetPlacement();
			}
		);
		return new ViewAsGuard(permissionService: $permissions, userId: $caller);
	}//end guard()

	/**
	 * Yourself and no one are always allowed; another person needs the rules editor.
	 *
	 * @return void
	 */
	public function testWhoMayViewAsWhom(): void {
		$alice = $this->guard(caller: 'alice');
		$bob = $this->guard(caller: 'bob');

		$this->assertNull(actual: $alice->refusal(subject: null, placementId: null));
		$this->assertNull(actual: $bob->refusal(subject: 'bob', placementId: null));
		$this->assertNull(actual: $alice->refusal(subject: 'sanne', placementId: 10));
		$this->assertSame(expected: ViewAsGuard::FORBIDDEN, actual: $alice->refusal(subject: 'sanne', placementId: null));
		$this->assertSame(expected: ViewAsGuard::FORBIDDEN, actual: $bob->refusal(subject: 'sanne', placementId: 10));
		$this->assertSame(expected: ViewAsGuard::FORBIDDEN, actual: $this->guard(caller: null)->refusal(subject: 'sanne', placementId: 10));
	}//end testWhoMayViewAsWhom()
}//end class
