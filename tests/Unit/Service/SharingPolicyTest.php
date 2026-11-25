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
 * @spec openspec/changes/sharing-policy-board-sync/specs/dashboard-sharing/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Controller\PublicShareController;
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
	 * The limits over the stored settings.
	 *
	 * @return SharingLimits The limits.
	 */
	private function limits(): SharingLimits {
		$mapper = $this->createMock(AdminSettingMapper::class);
		$mapper->method('getValue')->willReturnCallback(
			fn (string $key, mixed $default = null): mixed => array_key_exists($key, $this->stored) ? $this->stored[$key] : $default
		);
		$templates = $this->createMock(AdminTemplateService::class);
		$templates->method('getUserGroupIdsFor')->willReturnCallback(
			fn (string $userId): array => $this->memberships[$userId] ?? []
		);

		return new SharingLimits($mapper, $templates);
	}//end limits()

	/**
	 * Without a stored choice the mode follows the group list, as before.
	 *
	 * @return void
	 */
	public function testTheModeFollowsTheGroupListWithoutAStoredChoice(): void {
		$this->assertSame('everyone', $this->limits()->allowedMode());

		$this->stored = ['sharing_allowed_groups' => ['communicatie']];
		$this->assertSame('groups', $this->limits()->allowedMode());

		$this->stored = ['sharing_allowed' => 'odd', 'sharing_allowed_groups' => []];
		$this->assertSame('everyone', $this->limits()->allowedMode());
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

		$this->assertSame('none', $limits->allowedMode());
		$this->assertFalse($limits->mayShare('pieter'));
		$this->assertTrue($limits->mayShare('noor'));

		$this->expectException(ForbiddenException::class);
		$limits->assertMayShare('pieter', 'user', 'noor');
	}//end testNobodyMayShareButAnAdministrator()

	/**
	 * Everyone, chosen while groups are still stored, lets everyone share.
	 *
	 * @return void
	 */
	public function testEveryoneIgnoresAStoredGroupList(): void {
		$this->stored = ['sharing_allowed' => 'everyone', 'sharing_allowed_groups' => ['communicatie']];
		$this->assertTrue($this->limits()->mayShare('pieter'));

		$this->stored = ['sharing_allowed' => 'groups', 'sharing_allowed_groups' => ['communicatie']];
		$this->assertFalse($this->limits()->mayShare('pieter'));
		$this->assertTrue($this->limits()->mayShare('noor'));
	}//end testEveryoneIgnoresAStoredGroupList()

	/**
	 * Public links are on unless switched off.
	 *
	 * @return void
	 */
	public function testPublicLinksAreOnUnlessSwitchedOff(): void {
		$this->assertTrue($this->limits()->publicLinksAllowed());

		$this->stored = ['public_links_allowed' => false];
		$this->assertFalse($this->limits()->publicLinksAllowed());

		$this->stored = ['public_links_allowed' => true];
		$this->assertTrue($this->limits()->publicLinksAllowed());
	}//end testPublicLinksAreOnUnlessSwitchedOff()

	/**
	 * Scenario "A public link needs an expiry date".
	 *
	 * @return void
	 */
	public function testAPublicLinkWithoutAnExpiryDateIsRefused(): void {
		$this->limits()->assertMayCreatePublicLink('pieter', '2026-12-31');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('expiry date');
		$this->limits()->assertMayCreatePublicLink('pieter', ' ');
	}//end testAPublicLinkWithoutAnExpiryDateIsRefused()

	/**
	 * Scenario "Public links off": no one creates one, an administrator neither.
	 *
	 * @return void
	 */
	public function testNoPublicLinkWhenSwitchedOff(): void {
		$this->stored = ['public_links_allowed' => false];

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('switched off');
		$this->limits()->assertMayCreatePublicLink('noor', '2026-12-31');
	}//end testNoPublicLinkWhenSwitchedOff()

	/**
	 * Only people who may share create a public link.
	 *
	 * @return void
	 */
	public function testAPublicLinkNeedsSomeoneWhoMayShare(): void {
		$this->stored = ['sharing_allowed' => 'none'];

		$this->expectException(OCSForbiddenException::class);
		$this->expectExceptionMessage('not allowed to share');
		$this->limits()->assertMayCreatePublicLink('pieter', '2026-12-31');
	}//end testAPublicLinkNeedsSomeoneWhoMayShare()

	/**
	 * The settings read with their defaults and the stored values.
	 *
	 * @return void
	 */
	public function testTheSettingsAreReadWithTheirDefaults(): void {
		$mapper = $this->createMock(AdminSettingMapper::class);
		$mapper->method('getAllAsArray')->willReturnOnConsecutiveCalls(
			[],
			['sharing_allowed_groups' => ['sales']],
			['sharing_allowed' => 'none', 'public_links_allowed' => false],
		);
		$service = new AdminSettingsService($mapper);

		$unset = $service->getSettings();
		$this->assertSame('everyone', $unset['sharingAllowed']);
		$this->assertTrue($unset['publicLinksAllowed']);

		$this->assertSame('groups', $service->getSettings()['sharingAllowed']);

		$stored = $service->getSettings();
		$this->assertSame('none', $stored['sharingAllowed']);
		$this->assertFalse($stored['publicLinksAllowed']);
	}//end testTheSettingsAreReadWithTheirDefaults()

	/**
	 * Both settings are written when given, left alone when absent, and an
	 * unknown choice is refused.
	 *
	 * @return void
	 */
	public function testTheSettingsAreWrittenWhenGiven(): void {
		$written = [];
		$mapper = $this->createMock(AdminSettingMapper::class);
		$mapper->method('setSetting')->willReturnCallback(function (string $key, mixed $value) use (&$written): AdminSetting {
			$written[$key] = $value;
			return new AdminSetting();
		});
		$service = new AdminSettingsService($mapper);

		$service->updateSettings(['sharingAllowed' => 'none', 'publicLinksAllowed' => false]);
		$this->assertSame('none', $written['sharing_allowed']);
		$this->assertFalse($written['public_links_allowed']);

		$written = [];
		$service->updateSettings(['defaultGridCols' => 12]);
		$this->assertArrayNotHasKey('sharing_allowed', $written);
		$this->assertArrayNotHasKey('public_links_allowed', $written);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('sharingAllowed');
		$service->updateSettings(['sharingAllowed' => 'friends']);
	}//end testTheSettingsAreWrittenWhenGiven()

	/**
	 * The public share create route answers 400 without an expiry date and 403
	 * with public links off, and creates nothing; with both fine it creates.
	 *
	 * @return void
	 */
	public function testTheCreateRouteAppliesThePolicy(): void {
		$service = $this->createMock(PublicShareService::class);
		$share = new PublicShare();
		$service->expects($this->once())->method('createPublicShare')
			->with('dash-uuid', 'pieter', null, '2026-12-31')
			->willReturn($share);
		$controller = new PublicShareController(
			request: $this->createMock(IRequest::class),
			shareService: $service,
			shareContext: new PublicShareContext(),
			logger: $this->createMock(LoggerInterface::class),
			userId: 'pieter',
			limits: $this->limits(),
		);

		$this->assertSame(Http::STATUS_BAD_REQUEST, $controller->create(uuid: 'dash-uuid')->getStatus());
		$this->assertSame(Http::STATUS_CREATED, $controller->create(uuid: 'dash-uuid', expiresAt: '2026-12-31')->getStatus());

		$this->stored = ['public_links_allowed' => false];
		$off = new PublicShareController(
			request: $this->createMock(IRequest::class),
			shareService: $service,
			shareContext: new PublicShareContext(),
			logger: $this->createMock(LoggerInterface::class),
			userId: 'pieter',
			limits: $this->limits(),
		);
		$this->assertSame(Http::STATUS_FORBIDDEN, $off->create(uuid: 'dash-uuid', expiresAt: '2026-12-31')->getStatus());
	}//end testTheCreateRouteAppliesThePolicy()

}//end class
