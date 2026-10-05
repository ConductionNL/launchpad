<?php

/**
 * AnnouncementControllerTest
 *
 * engagement-announcements through the controller into the real service:
 * the status codes the scenarios name (403 for a reader who writes, 404 for
 * a non-member who asks by id, 400 for a notice without an end time) and the
 * ADR-023 action each endpoint checks.
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

use OCA\LaunchPad\Controller\AnnouncementController;
use OCA\LaunchPad\Service\ActionAuthService;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Unit\Support\AnnouncementWorld;

/**
 * Tests for AnnouncementController.
 */
class AnnouncementControllerTest extends TestCase {
	use AnnouncementWorld;

	/**
	 * Actions checked, in order.
	 *
	 * @var string[]
	 */
	private array $checked = [];

	/**
	 * Actions the matrix refuses.
	 *
	 * @var string[]
	 */
	private array $refused = [];

	/**
	 * Build the world.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->buildAnnouncementWorld();
	}//end setUp()

	/**
	 * A controller for one caller.
	 *
	 * @param string $uid The caller.
	 * @param array $params Request body.
	 *
	 * @return AnnouncementController
	 */
	private function as(string $uid, array $params = []): AnnouncementController {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$auth = $this->createMock(ActionAuthService::class);
		$auth->method('requireAction')->willReturnCallback(function (IUser $caller, string $action): void {
			$this->checked[] = $action;
			if (in_array($action, $this->refused, true) === true) {
				throw new OCSForbiddenException('no');
			}
		});

		$request = $this->createMock(IRequest::class);
		$request->method('getParams')->willReturn($params);
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $id): bool => $id === 'admin');

		return new AnnouncementController(
			request: $request,
			announcements: $this->service,
			actionAuth: $auth,
			userSession: $session,
			groupManager: $groups,
		);
	}//end as()

	/**
	 * REQ-ANN-001 "Reader cannot author": POST /api/announcements is 403.
	 *
	 * @return void
	 */
	public function testAReaderWhoPostsGets403(): void {
		$response = $this->as(uid: 'pieter', params: ['title' => 'Mag ik dit?'])->create();

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame([], $this->rows->rows, 'nothing is stored');
		$this->assertSame(['announcement.manage'], $this->checked);
	}//end testAReaderWhoPostsGets403()

	/**
	 * REQ-ANN-001 "News for one group": Sanne gets 404 by id, Pieter 200.
	 *
	 * @return void
	 */
	public function testANonMemberGets404ById(): void {
		$created = $this->as(uid: 'karin', params: ['title' => 'Inloopspreekuur privacy', 'category' => 'Privacy', 'targetGroups' => ['Medewerkers']])->create();
		$this->assertSame(Http::STATUS_CREATED, $created->getStatus());
		$uuid = $created->getData()['uuid'];
		$this->assertSame(Http::STATUS_OK, $this->as(uid: 'karin')->publish(uuid: $uuid)->getStatus());

		$this->assertSame(Http::STATUS_NOT_FOUND, $this->as(uid: 'sanne')->show(uuid: $uuid)->getStatus());
		$this->assertSame(Http::STATUS_NOT_FOUND, $this->as(uid: 'sanne')->comment(uuid: $uuid, message: 'Hallo')->getStatus());
		$this->assertSame('Inloopspreekuur privacy', $this->as(uid: 'pieter')->show(uuid: $uuid)->getData()['title']);
		$this->assertSame([], $this->as(uid: 'sanne')->index()->getData()['announcements']);
	}//end testANonMemberGets404ById()

	/**
	 * REQ-ANN-005 "Notice without an end time": publishing is 400 with the message.
	 *
	 * @return void
	 */
	public function testPublishingANoticeWithoutEndTimeIs400(): void {
		$uuid     = $this->as(uid: 'karin', params: ['kind' => 'notice', 'title' => 'Storing'])->create()->getData()['uuid'];
		$response = $this->as(uid: 'karin')->publish(uuid: $uuid);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame('A notice needs an end time', $response->getData()['error']);
	}//end testPublishingANoticeWithoutEndTimeIs400()

	/**
	 * The action matrix is consulted before anything else, per endpoint.
	 *
	 * @return void
	 */
	public function testEachEndpointChecksItsAction(): void {
		$this->refused = ['announcement.read', 'announcement.follow', 'announcement.comment'];

		$this->assertSame(Http::STATUS_FORBIDDEN, $this->as(uid: 'pieter')->index()->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->as(uid: 'pieter')->follow(category: 'Privacy')->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->as(uid: 'pieter')->like(uuid: 'x')->getStatus());
		$this->assertSame(['announcement.read', 'announcement.follow', 'announcement.comment'], $this->checked);
		$this->assertSame([], $this->follows->byUser, 'a refused follow stores nothing');
	}//end testEachEndpointChecksItsAction()

	/**
	 * Reach and "Preview as" for an author; a reader is refused.
	 *
	 * @return void
	 */
	public function testReachAndPreviewAs(): void {
		$data = $this->as(uid: 'karin')->reach(targetGroups: ['Burgerzaken'], previewUserId: 'sanne')->getData();
		$this->assertFalse($data['reachesPreviewUser']);
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->as(uid: 'pieter')->reach(targetGroups: [])->getStatus());
	}//end testReachAndPreviewAs()

	/**
	 * The editor-groups setting is for administrators only.
	 *
	 * @return void
	 */
	public function testSettingsAreAdminOnly(): void {
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->as(uid: 'karin')->saveSettings(groups: ['Redactie'])->getStatus());
		$this->assertSame(['Redactie'], $this->as(uid: 'admin')->getSettings()->getData()['groups']);
	}//end testSettingsAreAdminOnly()
}//end class
