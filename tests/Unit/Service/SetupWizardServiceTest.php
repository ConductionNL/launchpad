<?php

/**
 * SetupWizardService Test
 *
 * Covers REQ-WIZ-001 (state flag), REQ-WIZ-008 (wizard state heuristic),
 * REQ-WIZ-009 (idempotent completion) and the retired storage step
 * (decision 131: six steps, a stored content_storage value stays unread).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\RoleAssignment;
use OCA\LaunchPad\Db\RoleAssignmentMapper;
use OCA\LaunchPad\Service\SetupWizardService;
use OCP\IAppConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SetupWizardServiceTest extends TestCase {
	private SetupWizardService $service;

	/** @var AdminSettingMapper&MockObject */
	private $settingMapper;

	protected function setUp(): void {
		$this->settingMapper = $this->createMock(AdminSettingMapper::class);
		$this->service = new SetupWizardService(
			settingMapper: $this->settingMapper
		);
	}

	public function testGetWizardStateOnFreshInstance(): void {
		$this->settingMapper->method('getValue')
			->with(AdminSetting::KEY_SETUP_WIZARD_COMPLETE, false)
			->willReturn(false);
		$this->settingMapper->method('getAllAsArray')->willReturn([]);

		$state = $this->service->getWizardState();

		$this->assertFalse($state['complete']);
		// A first run opens at Step 1, Welcome (REQ-WIZ-008).
		$this->assertSame(expected: 1, actual: $state['currentRecommendedStep']);
		$this->assertSame(
			[
				1 => 'done',
				2 => 'pending',
				3 => 'pending',
				4 => 'pending',
				5 => 'pending',
			],
			$state['stepStatuses']
		);
	}

	/**
	 * The wizard has five steps and no storage step.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-24-setup-five-steps-and-admin-tour/specs/setup-wizard/spec.md#requirement-req-wiz-012-multi-step-wizard-flow-with-5-steps
	 */
	public function testWizardHasFiveStepsWithoutAStorageStep(): void {
		$this->assertSame(expected: 5, actual: SetupWizardService::STEP_COUNT);
		$this->assertFalse(method_exists(SetupWizardService::class, 'setContentStorage'));
		$this->assertFalse(method_exists(SetupWizardService::class, 'getContentStorage'));
		$this->assertFalse(method_exists(SetupWizardService::class, 'hasGroupfolderApp'));
	}

	public function testAStoredContentStorageValueIsLeftUnread(): void {
		$this->settingMapper->method('getValue')
			->with(AdminSetting::KEY_SETUP_WIZARD_COMPLETE, false)
			->willReturn(false);
		// A row written by the retired step stays in the table; the state
		// must not change because of it.
		$this->settingMapper->method('getAllAsArray')->willReturn([
			'content_storage' => 'groupfolder',
		]);

		$state = $this->service->getWizardState();

		$this->assertSame(expected: 1, actual: $state['currentRecommendedStep']);
		$this->assertCount(expectedCount: 5, haystack: $state['stepStatuses']);
		$this->assertSame('pending', $state['stepStatuses']['2']);
	}

	public function testGetWizardStateAfterGroupOrderWritten(): void {
		$this->settingMapper->method('getValue')
			->with(AdminSetting::KEY_SETUP_WIZARD_COMPLETE, false)
			->willReturn(false);
		$this->settingMapper->method('getAllAsArray')->willReturn([
			AdminSetting::KEY_GROUP_ORDER => ['engineering'],
		]);

		$state = $this->service->getWizardState();

		$this->assertSame('done', $state['stepStatuses']['2']);
		// No demo installed yet, so the first step left to do is 3.
		$this->assertSame(3, $state['currentRecommendedStep']);
	}

	/**
	 * A re-run where a later step is done but the group order is not set
	 * opens at Step 2, the first step left to do (REQ-WIZ-008).
	 *
	 * @return void
	 *
	 * @spec openspec/specs/setup-wizard/spec.md
	 */
	public function testAReRunWithoutAGroupOrderOpensAtStepTwo(): void {
		$this->settingMapper->method('getValue')
			->with(AdminSetting::KEY_SETUP_WIZARD_COMPLETE, false)
			->willReturn(true);
		$this->settingMapper->method('getAllAsArray')->willReturn([]);

		$state = $this->service->getWizardState();

		$this->assertSame(expected: 'pending', actual: $state['stepStatuses']['2']);
		$this->assertSame(expected: 2, actual: $state['currentRecommendedStep']);
	}//end testAReRunWithoutAGroupOrderOpensAtStepTwo()

	public function testGetWizardStateAfterCompletion(): void {
		$this->settingMapper->method('getValue')
			->with(AdminSetting::KEY_SETUP_WIZARD_COMPLETE, false)
			->willReturn(true);
		$this->settingMapper->method('getAllAsArray')->willReturn([
			AdminSetting::KEY_GROUP_ORDER => ['engineering'],
			AdminSetting::KEY_FOOTER_CONFIG => ['layout' => 'structured'],
		]);

		$state = $this->service->getWizardState();

		$this->assertTrue($state['complete']);
		$this->assertSame('done', $state['stepStatuses']['5']);
		// Finished without an admin role: Step 4 was skipped.
		$this->assertSame('skipped', $state['stepStatuses']['4']);
		$this->assertSame('pending', $state['stepStatuses']['3']);
		$this->assertCount(expectedCount: 5, haystack: $state['stepStatuses']);
		// No demo installed, so the first non-'done' status is Step 3.
		// The wizard "complete" flag
		// is the source of truth for hiding the banner; the recommended
		// step is purely a UX hint per REQ-WIZ-008.
		$this->assertSame(3, $state['currentRecommendedStep']);
	}

	/**
	 * A stored footer has no step of its own any more: the state has five steps and the footer changes none of them.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-24-setup-five-steps-and-admin-tour/specs/setup-wizard/spec.md#requirement-req-wiz-012-multi-step-wizard-flow-with-5-steps
	 */
	public function testAStoredFooterHasNoStepOfItsOwn(): void {
		$this->settingMapper->method('getValue')
			->with(AdminSetting::KEY_SETUP_WIZARD_COMPLETE, false)
			->willReturn(false);
		$this->settingMapper->method('getAllAsArray')->willReturn([
			AdminSetting::KEY_FOOTER_CONFIG => ['layout' => 'structured'],
		]);

		$state = $this->service->getWizardState();

		$this->assertSame(
			expected: [1 => 'done', 2 => 'pending', 3 => 'pending', 4 => 'pending', 5 => 'pending'],
			actual: $state['stepStatuses']
		);
	}

	public function testMarkWizardCompleteSetsFlagAndReturnsState(): void {
		$this->settingMapper
			->expects($this->once())
			->method('setSetting')
			->with(AdminSetting::KEY_SETUP_WIZARD_COMPLETE, true);
		$this->settingMapper->method('getValue')
			->with(AdminSetting::KEY_SETUP_WIZARD_COMPLETE, false)
			->willReturn(true);
		$this->settingMapper->method('getAllAsArray')->willReturn([]);

		$state = $this->service->markWizardComplete();

		$this->assertTrue($state['complete']);
	}

	public function testMarkWizardCompleteIsIdempotent(): void {
		// Even when already true the service still writes (idempotent
		// semantics — the controller doesn't need a defensive guard).
		$this->settingMapper
			->expects($this->exactly(2))
			->method('setSetting');
		$this->settingMapper->method('getValue')->willReturn(true);
		$this->settingMapper->method('getAllAsArray')->willReturn([]);

		$first = $this->service->markWizardComplete();
		$second = $this->service->markWizardComplete();

		$this->assertSame($first, $second);
	}

	/**
	 * An installed demo showcase marks Step 3 done, and a LaunchPad admin
	 * role assignment marks Step 4 done.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/setup-wizard/spec.md
	 */
	public function testAnInstalledDemoAndAnAdminRoleMarkStepsThreeAndFourDone(): void {
		$this->settingMapper->method('getValue')->willReturn(false);
		$this->settingMapper->method('getAllAsArray')->willReturn([
			AdminSetting::KEY_GROUP_ORDER => ['engineering'],
		]);
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getKeys')->willReturn(['allow_user_dash', 'showcase_installed_intranet']);
		$appConfig->method('getValueString')->willReturn('a77d8c2a-f12e-44fd-9cc2-c5ee32a6c40c');
		$admin = new RoleAssignment();
		$admin->setRole(RoleAssignment::ROLE_ADMIN);
		$roles = $this->createMock(RoleAssignmentMapper::class);
		$roles->method('findAll')->willReturn([$admin]);
		$service = new SetupWizardService(
			settingMapper: $this->settingMapper,
			appConfig: $appConfig,
			roleAssignments: $roles
		);

		$state = $service->getWizardState();

		$this->assertSame(
			expected: [1 => 'done', 2 => 'done', 3 => 'done', 4 => 'done', 5 => 'pending'],
			actual: $state['stepStatuses']
		);
		$this->assertSame(expected: 5, actual: $state['currentRecommendedStep']);
	}//end testAnInstalledDemoAndAnAdminRoleMarkStepsThreeAndFourDone()

	/**
	 * An emptied install marker and an editor-only role leave Steps 3 and 4 pending.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/setup-wizard/spec.md
	 */
	public function testAnEmptyMarkerAndAnEditorRoleLeaveStepsThreeAndFourPending(): void {
		$this->settingMapper->method('getValue')->willReturn(false);
		$this->settingMapper->method('getAllAsArray')->willReturn([]);
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getKeys')->willReturn(['showcase_installed_intranet']);
		$appConfig->method('getValueString')->willReturn('');
		$editor = new RoleAssignment();
		$editor->setRole(RoleAssignment::ROLE_EDITOR);
		$roles = $this->createMock(RoleAssignmentMapper::class);
		$roles->method('findAll')->willReturn([$editor]);
		$service = new SetupWizardService(
			settingMapper: $this->settingMapper,
			appConfig: $appConfig,
			roleAssignments: $roles
		);

		$state = $service->getWizardState();

		$this->assertSame(expected: 'pending', actual: $state['stepStatuses']['3']);
		$this->assertSame(expected: 'pending', actual: $state['stepStatuses']['4']);
		$this->assertSame(expected: 1, actual: $state['currentRecommendedStep']);
	}//end testAnEmptyMarkerAndAnEditorRoleLeaveStepsThreeAndFourPending()
}
