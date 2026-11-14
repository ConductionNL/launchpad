<?php

/**
 * AnnouncementControllerTest
 *
 * Every endpoint checks its action first, and maps the service's refusals:
 * not visible is 404, not allowed is 403, invalid is 400
 * (engagement-announcements task 4).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */

declare(strict_types=1);

namespace Unit\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\Controller\AnnouncementController;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Service\AnnouncementFollowService;
use OCA\LaunchPad\Service\AnnouncementService;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AnnouncementController.
 *
 * @covers \OCA\LaunchPad\Controller\AnnouncementController
 * @uses   \OCA\LaunchPad\Controller\ResponseHelper
 * @uses   \OCA\LaunchPad\Db\Announcement
 * @uses   \OCA\LaunchPad\Exception\ForbiddenException
 */
class AnnouncementControllerTest extends TestCase {

	/**
	 * The service double.
	 *
	 * @var AnnouncementService&MockObject
	 */
	private AnnouncementService $service;

	/**
	 * The follow service double.
	 *
	 * @var AnnouncementFollowService&MockObject
	 */
	private AnnouncementFollowService $follows;

	/**
	 * The actions checked, in order.
	 *
	 * @var list<string>
	 */
	private array $checked = [];

	/**
	 * The controller for user `pieter`, refusing the listed actions.
	 *
	 * @param array<string, mixed> $params  The request params.
	 * @param list<string>         $refused Actions the matrix refuses.
	 *
	 * @return AnnouncementController
	 */
	private function controller(array $params = [], array $refused = []): AnnouncementController {
		$request = $this->createMock(IRequest::class);
		$request->method('getParams')->willReturn($params);
		$request->method('getParam')->willReturnCallback(static fn (string $key, mixed $default = null): mixed => ($params[$key] ?? $default));

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('pieter');

		$guard = $this->createMock(RequestGuard::class);
		$guard->method('actionRefusal')->willReturnCallback(function (string $action) use ($refused): ?JSONResponse {
			$this->checked[] = $action;
			return in_array($action, $refused, true) ? new JSONResponse(['error' => 'Forbidden'], 403) : null;
		});
		$guard->method('sessionUser')->willReturn($user);

		return new AnnouncementController($request, $this->service, $guard, $this->follows);
	}//end controller()

	/**
	 * A stored announcement.
	 *
	 * @param string $title The title.
	 *
	 * @return Announcement
	 */
	private function announcement(string $title = 'Inloopspreekuur privacy'): Announcement {
		$announcement = new Announcement();
		$announcement->setUuid('11111111-2222-4333-8444-555555555555');
		$announcement->setKind('news');
		$announcement->setTitle($title);
		$announcement->setStatus('published');
		$announcement->setTargetGroupList(['Medewerkers']);
		return $announcement;
	}//end announcement()

	/**
	 * Set up the service double.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->service = $this->createMock(AnnouncementService::class);
		$this->follows = $this->createMock(AnnouncementFollowService::class);
	}//end setUp()

	/**
	 * The list answers what the user sees, their drafts, follows and author right.
	 *
	 * @return void
	 */
	public function testIndexListsWhatTheUserSees(): void {
		$this->service->method('visibleTo')->with('pieter')->willReturn([$this->announcement()]);
		$this->service->method('draftsFor')->willReturn([]);
		$this->follows->method('followedCategories')->willReturn(['Privacy']);
		$this->service->method('isAuthor')->willReturn(false);

		$data = $this->controller()->index()->getData();

		$this->assertSame(['Inloopspreekuur privacy'], array_column($data['announcements'], 'title'));
		$this->assertSame(['Medewerkers'], $data['announcements'][0]['targetGroups']);
		$this->assertSame([], $data['drafts']);
		$this->assertSame(['Privacy'], $data['followedCategories']);
		$this->assertFalse($data['canAuthor']);
		$this->assertSame(['announcement.read'], $this->checked);
	}//end testIndexListsWhatTheUserSees()

	/**
	 * An announcement the user may not see is 404.
	 *
	 * @return void
	 */
	public function testAnInvisibleAnnouncementIsNotFound(): void {
		$this->service->method('findFor')->willThrowException(new DoesNotExistException('none'));

		$response = $this->controller()->show('11111111-2222-4333-8444-555555555555');

		$this->assertSame(404, $response->getStatus());
		$this->assertSame(['error' => 'Announcement not found'], $response->getData());
	}//end testAnInvisibleAnnouncementIsNotFound()

	/**
	 * A reader who may not author gets 403 and nothing is written.
	 *
	 * @return void
	 */
	public function testAReaderCannotAuthor(): void {
		$this->service->method('create')->willThrowException(new ForbiddenException('Only announcement editors may write announcements'));

		$response = $this->controller(['title' => 'Mag ik dit?'])->create();

		$this->assertSame(403, $response->getStatus());
		$this->assertSame(['announcement.manage'], $this->checked);
	}//end testAReaderCannotAuthor()

	/**
	 * Create passes only the author's fields and answers 201.
	 *
	 * @return void
	 */
	public function testCreatePassesOnlyTheAuthorFields(): void {
		$this->service->expects($this->once())->method('create')
			->with('pieter', ['title' => 'Nieuw', 'targetGroups' => ['Medewerkers']])
			->willReturn($this->announcement('Nieuw'));

		$response = $this->controller(['title' => 'Nieuw', 'targetGroups' => ['Medewerkers'], 'status' => 'published', 'authorId' => 'admin'])->create();

		$this->assertSame(201, $response->getStatus());
		$this->assertSame('Nieuw', $response->getData()['title']);
	}//end testCreatePassesOnlyTheAuthorFields()

	/**
	 * Publishing a notice without an end time is a 400 with the reason.
	 *
	 * @return void
	 */
	public function testPublishingANoticeWithoutEndIsRefused(): void {
		$this->service->method('publish')->willThrowException(new InvalidArgumentException('A notice needs an end time'));

		$response = $this->controller()->publish('11111111-2222-4333-8444-555555555555');

		$this->assertSame(400, $response->getStatus());
		$this->assertSame('A notice needs an end time', $response->getData()['message']);
	}//end testPublishingANoticeWithoutEndIsRefused()

	/**
	 * A refused action stops before the service is asked anything.
	 *
	 * @return void
	 */
	public function testARefusedActionStopsFirst(): void {
		$this->service->expects($this->never())->method($this->anything());
		$this->follows->expects($this->never())->method($this->anything());

		$controller = $this->controller([], ['announcement.read', 'announcement.manage', 'announcement.follow']);
		foreach ([
			$controller->index(),
			$controller->show('x'),
			$controller->create(),
			$controller->update('x'),
			$controller->publish('x'),
			$controller->destroy('x'),
			$controller->reach(),
			$controller->follow(),
			$controller->unfollow('Privacy'),
		] as $response) {
			$this->assertSame(403, $response->getStatus());
		}

		$this->assertSame(
			['announcement.read', 'announcement.read', 'announcement.manage', 'announcement.manage', 'announcement.manage', 'announcement.manage', 'announcement.manage', 'announcement.follow', 'announcement.follow'],
			$this->checked
		);
	}//end testARefusedActionStopsFirst()

	/**
	 * The reach is for authors only, and counts the groups asked for.
	 *
	 * @return void
	 */
	public function testReach(): void {
		$this->service->method('isAuthor')->willReturnOnConsecutiveCalls(false, true);
		$this->service->method('reach')->with(['Burgerzaken'])->willReturn(42);

		$this->assertSame(403, $this->controller(['groups' => ['Burgerzaken']])->reach()->getStatus());
		$this->assertSame(['reach' => 42], $this->controller(['groups' => ['Burgerzaken', 7]])->reach()->getData());
	}//end testReach()

	/**
	 * Follow and unfollow answer the categories followed.
	 *
	 * @return void
	 */
	public function testFollowAndUnfollow(): void {
		$this->follows->method('follow')->with('pieter', 'Privacy')->willReturn(['Privacy']);
		$this->follows->method('unfollow')->with('pieter', 'Privacy')->willReturn([]);

		$this->assertSame(['followedCategories' => ['Privacy']], $this->controller(['category' => 'Privacy'])->follow()->getData());
		$this->assertSame(['followedCategories' => []], $this->controller()->unfollow('Privacy')->getData());
	}//end testFollowAndUnfollow()

	/**
	 * Delete answers the uuid it deleted.
	 *
	 * @return void
	 */
	public function testDestroy(): void {
		$this->service->expects($this->once())->method('delete')->with('pieter', 'abc');

		$this->assertSame(['deleted' => 'abc'], $this->controller()->destroy('abc')->getData());
	}//end testDestroy()
}//end class
