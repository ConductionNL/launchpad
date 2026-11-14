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
use OCA\LaunchPad\Service\SetupWizardService;
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
		// Decision 131: the storage step is retired, so Step 2 is the
		// group order and it is the first step left to do.
		$this->assertSame(2, $state['currentRecommendedStep']);
		$this->assertSame(
			[
				1 => 'done',
				2 => 'pending',
				3 => 'skipped',
				4 => 'skipped',
				5 => 'skipped',
				6 => 'pending',
			],
			$state['stepStatuses']
		);
	}

	public function testWizardHasSixStepsWithoutAStorageStep(): void {
		$this->assertSame(6, SetupWizardService::STEP_COUNT);
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

		$this->assertSame(2, $state['currentRecommendedStep']);
		$this->assertCount(6, $state['stepStatuses']);
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
		// Steps 3 and 4 are 'skipped' (sibling capabilities pending), so
		// the first non-'done' step is 3.
		$this->assertSame(3, $state['currentRecommendedStep']);
	}

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
		$this->assertSame('done', $state['stepStatuses']['6']);
		$this->assertSame('done', $state['stepStatuses']['5']);
		// Steps 3/4 are 'skipped' (sibling capabilities pending), so the
		// first non-'done' status is Step 3. The wizard "complete" flag
		// is the source of truth for hiding the banner; the recommended
		// step is purely a UX hint per REQ-WIZ-008.
		$this->assertSame(3, $state['currentRecommendedStep']);
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
}
