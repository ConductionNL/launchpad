<?php

/**
 * AnnouncementManagersTest
 *
 * Pins who manages announcements (REQ-ADM-034): an administrator, a holder of
 * Launchpad's admin settings or of the delegated announcements setting, and
 * nobody else.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Controller\AnnouncementController;
use OCA\LaunchPad\Controller\AnnouncementReadController;
use OCA\LaunchPad\Service\AnnouncementManagers;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCA\LaunchPad\Settings\LaunchPadAnnouncementsAdmin;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Settings\IManager;
use OCP\Settings\ISettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Tests for AnnouncementManagers and the management endpoints' authorization.
 *
 * @covers \OCA\LaunchPad\Service\AnnouncementManagers
 */
class AnnouncementManagersTest extends TestCase {

	/**
	 * The check, with the settings each user holds by delegation.
	 *
	 * @param array<string, list<ISettings>> $held Settings by user id.
	 *
	 * @return AnnouncementManagers
	 */
	private function managers(array $held): AnnouncementManagers {
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $uid): bool => $uid === 'admin');
		$users = $this->createMock(IUserManager::class);
		$users->method('get')->willReturnCallback(function (string $uid): ?IUser {
			if ($uid === 'ghost') {
				return null;
			}
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);
			return $user;
		});
		$settings = $this->createMock(IManager::class);
		$settings->method('getAllowedAdminSettings')->willReturnCallback(
			static fn (string $section, IUser $user): array => [10 => ($held[$user->getUID()] ?? [])]
		);

		return new AnnouncementManagers(groupManager: $groups, userManager: $users, settingsManager: $settings);
	}//end managers()

	/**
	 * Administrators and holders of either setting manage; others do not.
	 *
	 * @return void
	 */
	public function testWhoManages(): void {
		$managers = $this->managers([
			'femke' => [$this->createMock(LaunchPadAnnouncementsAdmin::class)],
			'dirk' => [$this->createMock(LaunchPadAdmin::class)],
			'pieter' => [$this->createMock(ISettings::class)],
		]);

		$this->assertTrue($managers->manages('admin'));
		$this->assertTrue($managers->manages('femke'));
		$this->assertTrue($managers->manages('dirk'));
		$this->assertFalse($managers->manages('pieter'));
		$this->assertFalse($managers->manages('sanne'));
		$this->assertFalse($managers->manages('ghost'));
	}//end testWhoManages()

	/**
	 * The management endpoints.
	 *
	 * @return array<string, array{0: class-string, 1: string}>
	 */
	public static function managementEndpoints(): array {
		return [
			'create' => [AnnouncementController::class, 'create'],
			'update' => [AnnouncementController::class, 'update'],
			'publish' => [AnnouncementController::class, 'publish'],
			'destroy' => [AnnouncementController::class, 'destroy'],
			'reach' => [AnnouncementController::class, 'reach'],
			'manage' => [AnnouncementController::class, 'manage'],
			'receipts' => [AnnouncementReadController::class, 'receipts'],
		];
	}//end managementEndpoints()

	/**
	 * Nextcloud checks the announcements setting before a management endpoint runs.
	 *
	 * @param class-string $class  The controller.
	 * @param string       $method The endpoint.
	 *
	 * @return void
	 */
	#[DataProvider('managementEndpoints')]
	public function testManagementEndpointsNameTheSetting(string $class, string $method): void {
		$reflection = new ReflectionMethod($class, $method);
		$classes = array_map(
			static fn ($attribute): string => $attribute->getArguments()['settings'],
			$reflection->getAttributes(AuthorizedAdminSetting::class)
		);

		$this->assertContains(LaunchPadAnnouncementsAdmin::class, $classes);
		$this->assertContains(LaunchPadAdmin::class, $classes);
		$this->assertSame([], $reflection->getAttributes(NoAdminRequired::class));
	}//end testManagementEndpointsNameTheSetting()
}//end class
