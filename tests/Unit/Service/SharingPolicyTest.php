<?php

/**
 * Unit tests for the sharing policy of REQ-SHARE-015: "Sharing allowed for"
 * Everyone, Only these groups or Nobody, and the public-link switch with its
 * required expiry date, in SharingLimits, the admin settings and the public
 * share create route.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Controller\PublicShareController;
use OCA\LaunchPad\Controller\PublicShareRules;
use OCA\LaunchPad\Controller\ResponseHelper;
use OCA\LaunchPad\Controller\SharingPolicyController;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\PublicShare;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\PublicShareContext;
use OCA\LaunchPad\Service\PublicShareService;
use OCA\LaunchPad\Service\SharingLimits;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Nobody may share but an administrator; public links can be switched off
 * and always carry an expiry date.
 */
class SharingPolicyTest extends TestCase {

	/** @var array<string, mixed> The stored settings. */
	private array $stored = [];

	/** @var array<string, list<string>> Group ids per user. */
	private array $memberships = [
		'pieter' => ['sales'],
		'noor' => ['communicatie', 'admin'],
	];

	/**
	 * A stored setting, or the default when it is not stored.
	 *
	 * @param string $key     The setting key.
	 * @param mixed  $default The default.
	 *
	 * @return mixed The value.
	 */
	private function storedValue(string $key, mixed $default): mixed {
		if (array_key_exists(key: $key, array: $this->stored) === true) {
			return $this->stored[$key];
		}

		return $default;
	}//end storedValue()

	/**
	 * The limits over the stored settings.
	 *
	 * @return SharingLimits The limits.
	 */
	private function limits(): SharingLimits {
		$mapper = $this->createMock(originalClassName: AdminSettingMapper::class);
		$mapper->method('getValue')->willReturnCallback(
			fn (string $key, mixed $default = null): mixed => $this->storedValue(key: $key, default: $default)
		);
		$templates = $this->createMock(originalClassName: AdminTemplateService::class);
		$templates->method('getUserGroupIdsFor')->willReturnCallback(
			fn (string $userId): array => $this->memberships[$userId] ?? []
		);

		return new SharingLimits(settings: $mapper, templates: $templates);
	}//end limits()

	/**
	 * Without a stored choice the mode follows the group list, as before.
	 *
	 * @return void
	 */
	public function testTheModeFollowsTheGroupListWithoutAStoredChoice(): void {
		$this->assertSame(expected: 'everyone', actual: $this->limits()->allowedMode());

		$this->stored = ['sharing_allowed_groups' => ['communicatie']];
		$this->assertSame(expected: 'groups', actual: $this->limits()->allowedMode());

		$this->stored = ['sharing_allowed' => 'odd', 'sharing_allowed_groups' => []];
		$this->assertSame(expected: 'everyone', actual: $this->limits()->allowedMode());
	}//end testTheModeFollowsTheGroupListWithoutAStoredChoice()

	/**
	 * Scenario "Nobody may share": only an administrator may share, and a
	 * share request from anyone else is refused.
	 *
	 * @return void
	 */
	public function testNobodyMayShareButAnAdministrator(): void {
		$this->stored = ['sharing_allowed' => 'none', 'sharing_allowed_groups' => ['sales']];
		$limits = $this->limits();

		$this->assertSame(expected: 'none', actual: $limits->allowedMode());
		$this->assertFalse(condition: $limits->mayShare(userId: 'pieter'));
		$this->assertTrue(condition: $limits->mayShare(userId: 'noor'));

		$this->expectException(exception: ForbiddenException::class);
		$limits->assertMayShare(sharerId: 'pieter', shareType: 'user', shareWith: 'noor');
	}//end testNobodyMayShareButAnAdministrator()

	/**
	 * Only Nobody overrides the group list; a stored Everyone or Only these
	 * groups follows it, so the REQ-SHARE-014 setting keeps working alone.
	 *
	 * @return void
	 */
	public function testOnlyNobodyOverridesTheGroupList(): void {
		$this->stored = ['sharing_allowed' => 'everyone', 'sharing_allowed_groups' => ['communicatie']];
		$this->assertSame(expected: 'groups', actual: $this->limits()->allowedMode());
		$this->assertFalse(condition: $this->limits()->mayShare(userId: 'pieter'));
		$this->assertTrue(condition: $this->limits()->mayShare(userId: 'noor'));

		$this->stored = ['sharing_allowed' => 'groups', 'sharing_allowed_groups' => []];
		$this->assertSame(expected: 'everyone', actual: $this->limits()->allowedMode());
		$this->assertTrue(condition: $this->limits()->mayShare(userId: 'pieter'));
	}//end testOnlyNobodyOverridesTheGroupList()

	/**
	 * Public links are on unless switched off.
	 *
	 * @return void
	 */
	public function testPublicLinksAreOnUnlessSwitchedOff(): void {
		$this->assertTrue(condition: $this->limits()->publicLinksAllowed());

		$this->stored = ['public_links_allowed' => false];
		$this->assertFalse(condition: $this->limits()->publicLinksAllowed());

		$this->stored = ['public_links_allowed' => true];
		$this->assertTrue(condition: $this->limits()->publicLinksAllowed());
	}//end testPublicLinksAreOnUnlessSwitchedOff()

	/**
	 * REQ-KIOSK-007: screen links are on unless switched off.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-8
	 */
	public function testScreenLinksAreOnUnlessSwitchedOff(): void {
		$this->assertTrue(condition: $this->limits()->screenLinksAllowed());

		$this->stored = ['kiosk_screen_links_allowed' => false];
		$this->assertFalse(condition: $this->limits()->screenLinksAllowed());

		$this->stored = ['kiosk_screen_links_allowed' => true];
		$this->assertTrue(condition: $this->limits()->screenLinksAllowed());
	}//end testScreenLinksAreOnUnlessSwitchedOff()

	/**
	 * Scenario "A public link needs an expiry date".
	 *
	 * @return void
	 */
	public function testAPublicLinkWithoutAnExpiryDateIsRefused(): void {
		$this->limits()->assertMayCreatePublicLink(callerId: 'pieter', expiresAt: '2026-12-31');

		$this->expectException(exception: InvalidArgumentException::class);
		$this->expectExceptionMessage(message: 'expiry date');
		$this->limits()->assertMayCreatePublicLink(callerId: 'pieter', expiresAt: ' ');
	}//end testAPublicLinkWithoutAnExpiryDateIsRefused()

	/**
	 * Scenario "Public links off": no one creates one, an administrator neither.
	 *
	 * @return void
	 */
	public function testNoPublicLinkWhenSwitchedOff(): void {
		$this->stored = ['public_links_allowed' => false];

		$this->expectException(exception: OCSForbiddenException::class);
		$this->expectExceptionMessage(message: 'switched off');
		$this->limits()->assertMayCreatePublicLink(callerId: 'noor', expiresAt: '2026-12-31');
	}//end testNoPublicLinkWhenSwitchedOff()

	/**
	 * Only people who may share create a public link.
	 *
	 * @return void
	 */
	public function testAPublicLinkNeedsSomeoneWhoMayShare(): void {
		$this->stored = ['sharing_allowed' => 'none'];

		$this->expectException(exception: OCSForbiddenException::class);
		$this->expectExceptionMessage(message: 'not allowed to share');
		$this->limits()->assertMayCreatePublicLink(callerId: 'pieter', expiresAt: '2026-12-31');
	}//end testAPublicLinkNeedsSomeoneWhoMayShare()

	/**
	 * The settings read with their defaults and the stored values.
	 *
	 * @return void
	 */
	public function testTheSettingsAreReadWithTheirDefaults(): void {
		$mapper = $this->createMock(originalClassName: AdminSettingMapper::class);
		$mapper->method('getAllAsArray')->willReturnOnConsecutiveCalls(
			[],
			['sharing_allowed_groups' => ['sales']],
			['sharing_allowed' => 'none', 'public_links_allowed' => false],
		);
		$service = new AdminSettingsService(settingMapper: $mapper);

		$unset = $service->getSettings();
		$this->assertSame(expected: 'everyone', actual: $unset['sharingAllowed']);
		$this->assertTrue(condition: $unset['publicLinksAllowed']);

		$this->assertSame(expected: 'groups', actual: $service->getSettings()['sharingAllowed']);

		$stored = $service->getSettings();
		$this->assertSame(expected: 'none', actual: $stored['sharingAllowed']);
		$this->assertFalse(condition: $stored['publicLinksAllowed']);
	}//end testTheSettingsAreReadWithTheirDefaults()

	/**
	 * Both settings are written when given, left alone when absent, and an
	 * unknown choice is refused.
	 *
	 * @return void
	 */
	public function testTheSettingsAreWrittenWhenGiven(): void {
		$written = [];
		$mapper = $this->createMock(originalClassName: AdminSettingMapper::class);
		$mapper->method('setSetting')->willReturnCallback(function (string $key, mixed $value) use (&$written): AdminSetting {
			$written[$key] = $value;
			return new AdminSetting();
		});
		$service = new AdminSettingsService(settingMapper: $mapper);

		$service->updateSettings(settings: ['sharingAllowed' => 'none', 'publicLinksAllowed' => false]);
		$this->assertSame(expected: 'none', actual: $written['sharing_allowed']);
		$this->assertFalse(condition: $written['public_links_allowed']);

		$written = [];
		$service->updateSettings(settings: ['defaultGridCols' => 12]);
		$this->assertArrayNotHasKey(key: 'sharing_allowed', array: $written);
		$this->assertArrayNotHasKey(key: 'public_links_allowed', array: $written);

		$this->expectException(exception: InvalidArgumentException::class);
		$this->expectExceptionMessage(message: 'sharingAllowed');
		$service->updateSettings(settings: ['sharingAllowed' => 'friends']);
	}//end testTheSettingsAreWrittenWhenGiven()

	/**
	 * The public share create route answers 400 without an expiry date and 403
	 * with public links off, and creates nothing; with both fine it creates.
	 *
	 * @return void
	 */
	public function testTheCreateRouteAppliesThePolicy(): void {
		$service = $this->createMock(originalClassName: PublicShareService::class);
		$share = new PublicShare();
		$service->expects($this->once())->method('createPublicShare')
			->with('dash-uuid', 'pieter', null, '2026-12-31')
			->willReturn($share);
		$controller = new PublicShareController(
			request: $this->createMock(originalClassName: IRequest::class),
			shareService: $service,
			shareContext: new PublicShareContext(),
			logger: $this->createMock(originalClassName: LoggerInterface::class),
			userId: 'pieter',
			rules: new PublicShareRules(limits: $this->limits()),
		);

		$this->assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $controller->create(uuid: 'dash-uuid')->getStatus());
		$this->assertSame(expected: Http::STATUS_CREATED, actual: $controller->create(uuid: 'dash-uuid', expiresAt: '2026-12-31')->getStatus());

		$this->stored = ['public_links_allowed' => false];
		$off = new PublicShareController(
			request: $this->createMock(originalClassName: IRequest::class),
			shareService: $service,
			shareContext: new PublicShareContext(),
			logger: $this->createMock(originalClassName: LoggerInterface::class),
			userId: 'pieter',
			rules: new PublicShareRules(limits: $this->limits()),
		);
		$this->assertSame(expected: Http::STATUS_FORBIDDEN, actual: $off->create(uuid: 'dash-uuid', expiresAt: '2026-12-31')->getStatus());
	}//end testTheCreateRouteAppliesThePolicy()

	/**
	 * The admin endpoint passes both values to the settings and answers 400
	 * for an unknown choice.
	 *
	 * @return void
	 */
	public function testTheAdminEndpointStoresThePolicy(): void {
		$written = [];
		$mapper = $this->createMock(originalClassName: AdminSettingMapper::class);
		$mapper->method('setSetting')->willReturnCallback(function (string $key, mixed $value) use (&$written): AdminSetting {
			$written[$key] = $value;
			return new AdminSetting();
		});
		$controller = new SharingPolicyController(
			request: $this->createMock(originalClassName: IRequest::class),
			settings: new AdminSettingsService(settingMapper: $mapper),
			responseHelper: new ResponseHelper(logger: new NullLogger()),
		);

		$ok = $controller->update(sharingAllowed: 'none', publicLinksAllowed: false);
		$this->assertSame(expected: Http::STATUS_OK, actual: $ok->getStatus());
		$this->assertSame(expected: ['sharing_allowed' => 'none', 'public_links_allowed' => false], actual: $written);

		$refused = $controller->update(sharingAllowed: 'friends');
		$this->assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $refused->getStatus());
	}//end testTheAdminEndpointStoresThePolicy()

	/**
	 * REQ-KIOSK-007: the same endpoint stores the screen link switch.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-27-dashboard-show-on-a-screen/tasks.md#task-8
	 */
	public function testTheAdminEndpointStoresTheScreenLinkSwitch(): void {
		$written = [];
		$mapper = $this->createMock(originalClassName: AdminSettingMapper::class);
		$mapper->method('setSetting')->willReturnCallback(function (string $key, mixed $value) use (&$written): AdminSetting {
			$written[$key] = $value;
			return new AdminSetting();
		});
		$controller = new SharingPolicyController(
			request: $this->createMock(originalClassName: IRequest::class),
			settings: new AdminSettingsService(settingMapper: $mapper),
			responseHelper: new ResponseHelper(logger: new NullLogger()),
		);

		$ok = $controller->update(kioskScreenLinksAllowed: false);
		$this->assertSame(expected: Http::STATUS_OK, actual: $ok->getStatus());
		$this->assertSame(expected: ['kiosk_screen_links_allowed' => false], actual: $written);
	}//end testTheAdminEndpointStoresTheScreenLinkSwitch()

}//end class
