<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The org navigation the workspace page carries in its initial state.
 *
 * @spec openspec/changes/org-navigation-from-initial-state/tasks.md#task-4
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\OrgNavigationFirstLoad;
use OCA\LaunchPad\Controller\OrgNavigationSources;
use OCA\LaunchPad\Controller\RequestActor;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\OrgNavigationService;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for {@see OrgNavigationFirstLoad}.
 */
class OrgNavigationFirstLoadTest extends TestCase {
	/** @var OrgNavigationService&MockObject */
	private OrgNavigationService $service;

	/** @var AdminSettingMapper&MockObject */
	private AdminSettingMapper $settings;

	/** @var ActionAuthService&MockObject */
	private ActionAuthService $actionAuth;

	/** @var IUserSession&MockObject */
	private IUserSession $userSession;

	/**
	 * The services the builder reads, as doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->service = $this->createMock(originalClassName: OrgNavigationService::class);
		$this->settings = $this->createMock(originalClassName: AdminSettingMapper::class);
		$this->actionAuth = $this->createMock(originalClassName: ActionAuthService::class);
		$this->userSession = $this->createMock(originalClassName: IUserSession::class);
		$this->userSession->method('getUser')->willReturn($this->createMock(originalClassName: IUser::class));
	}//end setUp()

	/**
	 * The builder under test.
	 *
	 * @param bool $withActor Whether the action check is there.
	 *
	 * @return OrgNavigationFirstLoad The builder.
	 */
	private function firstLoad(bool $withActor=true): OrgNavigationFirstLoad {
		$actor = null;
		if ($withActor === true) {
			$actor = new RequestActor(userSession: $this->userSession, actionAuth: $this->actionAuth);
		}

		return new OrgNavigationFirstLoad(
			sources: new OrgNavigationSources(service: $this->service, settings: $this->settings),
			actor: $actor
		);
	}//end firstLoad()

	/**
	 * The first load carries the stored position and the tree filtered for
	 * the caller, in the default language, as the two endpoints answer.
	 *
	 * @return void
	 */
	public function testFirstLoadCarriesThePositionAndTheCallersTree(): void {
		$tree = [['id' => 'n1'], ['id' => 'n2']];
		$this->settings->method('getValue')->willReturn('left');
		$this->service->expects(self::once())->method('getTree')->with('nl')->willReturn($tree);
		$this->service->expects(self::once())->method('filterTreeByUserGroups')
			->with($tree, 'pieter')
			->willReturn([['id' => 'n1']]);

		self::assertSame(
			expected: ['position' => 'left', 'tree' => [['id' => 'n1']], 'language' => 'nl'],
			actual: $this->firstLoad()->firstLoad(userId: 'pieter')
		);
	}//end testFirstLoadCarriesThePositionAndTheCallersTree()

	/**
	 * A position that is not one of the allowed ones reads as the default.
	 *
	 * @return void
	 */
	public function testAnUnknownPositionReadsAsHidden(): void {
		$this->settings->method('getValue')->willReturn('sideways');
		$this->service->method('getTree')->willReturn([]);
		$this->service->method('filterTreeByUserGroups')->willReturn([]);

		self::assertSame(expected: 'hidden', actual: $this->firstLoad()->firstLoad(userId: 'pieter')['position']);
	}//end testAnUnknownPositionReadsAsHidden()

	/**
	 * A caller the action matrix refuses gets no first load, so the shell
	 * asks the API and meets the same refusal there.
	 *
	 * @return void
	 */
	public function testFirstLoadIsNullWhenTheActionIsRefused(): void {
		$this->actionAuth->method('requireAction')->willThrowException(new OCSForbiddenException());
		$this->service->expects(self::never())->method('getTree');

		self::assertNull(actual: $this->firstLoad()->firstLoad(userId: 'pieter'));
	}//end testFirstLoadIsNullWhenTheActionIsRefused()

	/**
	 * Without the action check, or without a user, there is no first load.
	 *
	 * @return void
	 */
	public function testFirstLoadIsNullWithoutActorOrUser(): void {
		self::assertNull(actual: $this->firstLoad(withActor: false)->firstLoad(userId: 'pieter'));
		self::assertNull(actual: $this->firstLoad()->firstLoad(userId: ''));
	}//end testFirstLoadIsNullWithoutActorOrUser()
}//end class
