<?php

/**
 * AnnouncementGroupsController Test
 *
 * The target group picker's list: editors get the groups; a refused action,
 * a reader or no session user get none.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\AnnouncementGroupsController;
use OCA\LaunchPad\Service\AnnouncementService;
use OCA\LaunchPad\Service\AnnouncementTargetGroups;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUser;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Controller\AnnouncementGroupsController
 */
class AnnouncementGroupsControllerTest extends TestCase {

	/**
	 * The controller with a session user (or its refusal), an author answer and a refused action.
	 *
	 * @param bool  $author  Whether `pieter` may author.
	 * @param bool  $refused Whether the action matrix refuses `announcement.manage`.
	 * @param mixed $user    The session user, or the guard's refusal.
	 *
	 * @return AnnouncementGroupsController
	 */
	private function controller(bool $author = true, bool $refused = false, mixed $user = null): AnnouncementGroupsController {
		if ($user === null) {
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn('pieter');
		}

		$guard = $this->createMock(originalClassName: RequestGuard::class);
		$refusal = null;
		if ($refused === true) {
			$refusal = new JSONResponse(['error' => 'Forbidden'], 403);
		}

		$guard->method('actionRefusal')->with('announcement.manage')->willReturn($refusal);
		$guard->method('sessionUser')->willReturn($user);

		$announcements = $this->createMock(originalClassName: AnnouncementService::class);
		$announcements->method('isAuthor')->with('pieter')->willReturn($author);

		$groups = $this->createMock(originalClassName: AnnouncementTargetGroups::class);
		$groups->method('all')->willReturn([['id' => 'burgerzaken', 'displayName' => 'Burgerzaken']]);

		return new AnnouncementGroupsController(
			request: $this->createMock(originalClassName: IRequest::class),
			announcements: $announcements,
			guard: $guard,
			targetGroups: $groups
		);
	}//end controller()

	/**
	 * An editor gets the groups; a reader, a refused action and no user do not.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-24-announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
	 */
	public function testOnlyEditorsGetTheGroups(): void {
		self::assertSame(
			expected: ['groups' => [['id' => 'burgerzaken', 'displayName' => 'Burgerzaken']]],
			actual: $this->controller()->index()->getData()
		);
		self::assertSame(expected: 403, actual: $this->controller(author: false)->index()->getStatus());
		self::assertSame(expected: 403, actual: $this->controller(refused: true)->index()->getStatus());
		$noUser = new JSONResponse(['error' => 'Not signed in'], 401);
		self::assertSame(expected: 401, actual: $this->controller(user: $noUser)->index()->getStatus());
	}//end testOnlyEditorsGetTheGroups()
}//end class
