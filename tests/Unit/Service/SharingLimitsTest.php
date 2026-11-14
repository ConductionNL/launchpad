<?php

/**
 * Unit tests for SharingLimits: who may share a dashboard, and with whom.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/record-parity-requirements/specs/dashboard-sharing/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Controller\DashboardShareApiController;
use OCA\LaunchPad\Controller\ShareApiTools;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Db\DashboardShareMapper;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardShareService;
use OCA\LaunchPad\Service\SharingLimits;
use OCA\LaunchPad\Service\ShareGrant;
use OCA\LaunchPad\Service\ShareNotifier;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * REQ-SHARE-014: the server enforces both limits on every share it creates,
 * and the sharee search offers only allowed recipients.
 */
class SharingLimitsTest extends TestCase {

	/** @var array<string, mixed> The stored settings. */
	private array $stored = [];

	/** @var array<string, list<string>> Group ids per user. */
	private array $memberships = [
		'pieter' => ['sales'],
		'noor' => ['communicatie', 'admin'],
		'ella' => ['finance'],
		'sanne' => ['sales', 'finance'],
	];

	/** @var IGroupManager&MockObject */
	private $groupManager;

	/** @var IUserManager&MockObject */
	private $userManager;

	/**
	 * Set up the directory doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->groupManager->method('isInGroup')->willReturnCallback(
			fn (string $user, string $group): bool => in_array($group, $this->memberships[$user] ?? [], true)
		);
		$this->groupManager->method('getUserGroupIds')->willReturnCallback(
			fn (IUser $user): array => $this->memberships[$user->getUID()] ?? []
		);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->userManager->method('get')->willReturnCallback(function (string $uid): ?IUser {
			if (isset($this->memberships[$uid]) === false) {
				return null;
			}

			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
			$user->method('getDisplayName')->willReturn(ucfirst($uid));
			return $user;
		});

	}//end setUp()

	/**
	 * The limits over the stored settings.
	 *
	 * @return SharingLimits The limits.
	 */
	private function limits(): SharingLimits {
		$mapper = $this->createMock(AdminSettingMapper::class);
		$mapper->method('getValue')->willReturnCallback(
			fn (string $key, mixed $default = null): mixed => $this->stored[$key] ?? $default
		);
		$templates = $this->createMock(AdminTemplateService::class);
		$templates->method('getUserGroupIdsFor')->willReturnCallback(
			fn (string $userId): array => $this->memberships[$userId] ?? []
		);

		return new SharingLimits($mapper, $templates);

	}//end limits()

	/**
	 * Scenario "No limits set": everyone may share with anyone.
	 *
	 * @return void
	 */
	public function testNoLimitsKeepTodaysBehaviour(): void {
		$limits = $this->limits();

		$this->assertSame([], $limits->allowedGroups());
		$this->assertSame('everyone', $limits->recipientScope());
		$this->assertTrue($limits->mayShare('pieter'));
		$this->assertTrue($limits->mayReceive('pieter', 'user', 'ella'));
		$limits->assertMayShare('pieter', 'user', 'ella');
	}//end testNoLimitsKeepTodaysBehaviour()

	/**
	 * Scenario "A member outside the sharing groups cannot share".
	 *
	 * @return void
	 */
	public function testOnlyMembersOfTheSharingGroupsMayShare(): void {
		$this->stored = ['sharing_allowed_groups' => ['communicatie', '']];
		$limits = $this->limits();

		$this->assertSame(['communicatie'], $limits->allowedGroups());
		$this->assertTrue($limits->mayShare('noor'));
		$this->assertFalse($limits->mayShare('pieter'));

		$this->expectException(ForbiddenException::class);
		$this->expectExceptionMessage('not allowed to share');
		$limits->assertMayShare('pieter', 'user', 'sanne');
	}//end testOnlyMembersOfTheSharingGroupsMayShare()

	/**
	 * Scenario "Recipients limited to shared groups", for user and group recipients.
	 *
	 * @return void
	 */
	public function testRecipientsLimitedToSharedGroups(): void {
		$this->stored = ['sharing_recipient_scope' => 'shared-groups'];
		$limits = $this->limits();

		$this->assertTrue($limits->mayReceive('pieter', 'user', 'sanne'), 'both in sales');
		$this->assertFalse($limits->mayReceive('pieter', 'user', 'ella'), 'no group in common');
		$this->assertFalse($limits->mayReceive('pieter', 'user', 'nobody'), 'an unknown user shares nothing');
		$this->assertTrue($limits->mayReceive('pieter', 'group', 'sales'), 'a group the sharer is in');
		$this->assertFalse($limits->mayReceive('pieter', 'group', 'finance'), 'a group the sharer is not in');

		$this->expectException(ForbiddenException::class);
		$this->expectExceptionMessage('share a group with you');
		$limits->assertMayShare('pieter', 'user', 'ella');
	}//end testRecipientsLimitedToSharedGroups()

	/**
	 * Stored values that are not a list or a known scope read as no limit.
	 *
	 * @return void
	 */
	public function testUnreadableStoredValuesReadAsNoLimit(): void {
		$this->stored = ['sharing_allowed_groups' => 'communicatie', 'sharing_recipient_scope' => 'friends'];
		$limits = $this->limits();

		$this->assertSame([], $limits->allowedGroups());
		$this->assertSame('everyone', $limits->recipientScope());
	}//end testUnreadableStoredValuesReadAsNoLimit()

	/**
	 * The share service the limits are injected into.
	 *
	 * @param DashboardShareMapper&MockObject $shares The share mapper.
	 *
	 * @return DashboardShareService The service.
	 */
	private function shareService(DashboardShareMapper $shares): DashboardShareService {
		$dashboard = $this->getMockBuilder(Dashboard::class)->addMethods(['getUserId', 'getId', 'getName'])->getMock();
		$dashboard->method('getUserId')->willReturn('pieter');
		$dashboard->method('getId')->willReturn(7);
		$dashboard->method('getName')->willReturn('Mine');
		$dashboards = $this->createMock(DashboardMapper::class);
		$dashboards->method('find')->willReturn($dashboard);

		return new DashboardShareService(
			shareMapper: $shares,
			dashboardMapper: $dashboards,
			db: $this->createMock(IDBConnection::class),
			notifier: $this->createMock(ShareNotifier::class),
			limits: $this->limits(),
		);
	}//end shareService()

	/**
	 * A share request the limits refuse writes nothing (HTTP 403 through the controller).
	 *
	 * @return void
	 */
	public function testAddShareIsRefusedBeforeAnythingIsWritten(): void {
		$this->stored = ['sharing_recipient_scope' => 'shared-groups'];
		$shares = $this->createMock(DashboardShareMapper::class);
		$shares->expects($this->never())->method('insert');
		$shares->expects($this->never())->method('update');
		$shares->expects($this->never())->method('findShare');

		$this->expectException(ForbiddenException::class);
		$this->shareService($shares)->addShare(7, new ShareGrant('user', 'ella', 'view_only'), 'pieter');
	}//end testAddShareIsRefusedBeforeAnythingIsWritten()

	/**
	 * Saving the share list checks only shares the dashboard does not have yet.
	 *
	 * @return void
	 */
	public function testReplaceChecksOnlyNewShares(): void {
		$this->stored = ['sharing_allowed_groups' => ['communicatie']];
		$existing = new DashboardShare();
		$existing->setShareType('user');
		$existing->setShareWith('ella');
		$shares = $this->createMock(DashboardShareMapper::class);
		$shares->method('findByDashboardId')->willReturn([$existing]);
		$shares->expects($this->never())->method('deleteNotIn');

		$this->expectException(ForbiddenException::class);
		$this->shareService($shares)->replaceShares(
			7,
			[
				['shareType' => 'user', 'shareWith' => 'ella', 'permissionLevel' => 'view_only'],
				['shareType' => 'user', 'shareWith' => 'sanne', 'permissionLevel' => 'view_only'],
			],
			'pieter'
		);
	}//end testReplaceChecksOnlyNewShares()

	/**
	 * Re-saving only the shares a dashboard already has passes a limit set later.
	 *
	 * @return void
	 */
	public function testReplaceWithOnlyExistingSharesPasses(): void {
		$this->stored = ['sharing_allowed_groups' => ['communicatie']];
		$existing = new DashboardShare();
		$existing->setShareType('user');
		$existing->setShareWith('ella');
		$shares = $this->createMock(DashboardShareMapper::class);
		$shares->method('findByDashboardId')->willReturn([$existing]);
		$shares->method('findShare')->willReturn($existing);
		$shares->expects($this->once())->method('deleteNotIn');

		$this->shareService($shares)->replaceShares(
			7,
			[['shareType' => 'user', 'shareWith' => 'ella', 'permissionLevel' => 'view_only']],
			'pieter'
		);
	}//end testReplaceWithOnlyExistingSharesPasses()

	/**
	 * The sharee search offers only allowed recipients, and nobody to a user who may not share.
	 *
	 * @return void
	 */
	public function testTheShareeSearchOffersOnlyAllowedRecipients(): void {
		$this->stored = ['sharing_recipient_scope' => 'shared-groups'];
		$users = [];
		foreach (['pieter', 'ella', 'sanne'] as $uid) {
			$users[] = $this->userManager->get($uid);
		}
		$this->userManager->method('search')->willReturn($users);
		$groups = [];
		foreach (['sales', 'finance'] as $gid) {
			$group = $this->createMock(IGroup::class);
			$group->method('getGID')->willReturn($gid);
			$group->method('getDisplayName')->willReturn(ucfirst($gid));
			$groups[] = $group;
		}
		$this->groupManager->method('search')->willReturn($groups);

		$controller = new DashboardShareApiController(
			request: $this->createMock(IRequest::class),
			shareService: $this->createMock(DashboardShareService::class),
			tools: new ShareApiTools(
				userManager: $this->userManager,
				groupManager: $this->groupManager,
				logger: $this->createMock(LoggerInterface::class),
				limits: $this->limits(),
			),
			userId: 'pieter',
		);

		$this->assertSame(
			['users' => [['id' => 'sanne', 'displayName' => 'Sanne']], 'groups' => [['id' => 'sales', 'displayName' => 'Sales']]],
			$controller->searchSharees('an')->getData()
		);

		$this->stored = ['sharing_allowed_groups' => ['communicatie']];
		$blocked = new DashboardShareApiController(
			request: $this->createMock(IRequest::class),
			shareService: $this->createMock(DashboardShareService::class),
			tools: new ShareApiTools(
				userManager: $this->userManager,
				groupManager: $this->groupManager,
				logger: $this->createMock(LoggerInterface::class),
				limits: $this->limits(),
			),
			userId: 'pieter',
		);
		$this->assertSame(['users' => [], 'groups' => []], $blocked->searchSharees('an')->getData());
	}//end testTheShareeSearchOffersOnlyAllowedRecipients()

}//end class
