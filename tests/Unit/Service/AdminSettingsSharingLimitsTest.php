<?php

/**
 * Unit tests for the two sharing-limit admin settings (REQ-SHARE-014).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/record-parity-requirements/specs/dashboard-sharing/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\AdminSettingsService;
use PHPUnit\Framework\TestCase;

/**
 * The settings are read with safe defaults and written only when valid.
 */
class AdminSettingsSharingLimitsTest extends TestCase {

	/**
	 * Unset settings read as everyone may share with anyone; stored ones as stored.
	 *
	 * @return void
	 */
	public function testTheSettingsAreReadWithSafeDefaults(): void {
		$mapper = $this->createMock(AdminSettingMapper::class);
		$mapper->method('getAllAsArray')->willReturnOnConsecutiveCalls(
			[],
			['sharing_allowed_groups' => ['communicatie', ''], 'sharing_recipient_scope' => 'shared-groups'],
			['sharing_allowed_groups' => 'communicatie', 'sharing_recipient_scope' => 'friends'],
		);
		$service = new AdminSettingsService($mapper);

		$unset = $service->getSettings();
		$this->assertSame([], $unset['sharingAllowedGroups']);
		$this->assertSame('everyone', $unset['sharingRecipientScope']);

		$stored = $service->getSettings();
		$this->assertSame(['communicatie'], $stored['sharingAllowedGroups']);
		$this->assertSame('shared-groups', $stored['sharingRecipientScope']);

		$odd = $service->getSettings();
		$this->assertSame([], $odd['sharingAllowedGroups']);
		$this->assertSame('everyone', $odd['sharingRecipientScope']);
	}//end testTheSettingsAreReadWithSafeDefaults()

	/**
	 * Both settings are written when given and left alone when absent.
	 *
	 * @return void
	 */
	public function testTheSettingsAreWrittenWhenGiven(): void {
		$written = [];
		$mapper = $this->createMock(AdminSettingMapper::class);
		$mapper->method('setSetting')->willReturnCallback(function (string $key, mixed $value) use (&$written) {
			$written[$key] = $value;
			return new \OCA\LaunchPad\Db\AdminSetting();
		});
		$service = new AdminSettingsService($mapper);

		$service->updateSettings(['sharingAllowedGroups' => ['communicatie', 'communicatie', ' staff '], 'sharingRecipientScope' => 'shared-groups']);
		$this->assertSame('shared-groups', $written['sharing_recipient_scope']);
		$this->assertContains('communicatie', $written['sharing_allowed_groups']);
		$this->assertSame(count(array_unique($written['sharing_allowed_groups'])), count($written['sharing_allowed_groups']));

		$written = [];
		$service->updateSettings(['defaultGridCols' => 12]);
		$this->assertArrayNotHasKey('sharing_allowed_groups', $written);
		$this->assertArrayNotHasKey('sharing_recipient_scope', $written);
	}//end testTheSettingsAreWrittenWhenGiven()

	/**
	 * An unknown recipient scope is refused and nothing of it is stored.
	 *
	 * @return void
	 */
	public function testAnUnknownScopeIsRefused(): void {
		$mapper = $this->createMock(AdminSettingMapper::class);
		$mapper->expects($this->never())->method('setSetting')->with('sharing_recipient_scope', $this->anything());

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('sharingRecipientScope');
		(new AdminSettingsService($mapper))->updateSettings(['sharingRecipientScope' => 'friends']);
	}//end testAnUnknownScopeIsRefused()

}//end class
