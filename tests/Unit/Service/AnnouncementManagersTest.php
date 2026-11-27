<?php

/**
 * AnnouncementManagersTest
 *
 * Pins who manages announcements (REQ-ADM-034): a holder of Launchpad's
 * admin settings or of the delegated announcements setting, a member of a
 * former editor group until the move, and nobody else.
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
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\AnnouncementManagers;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCA\LaunchPad\Settings\LaunchPadAnnouncementsAdmin;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
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
		$users = $this->createMock(originalClassName: IUserManager::class);
		$users->method('get')->willReturnCallback(function (string $uid): ?IUser {
			if ($uid === 'ghost') {
				return null;
			}
			$user = $this->createMock(originalClassName: IUser::class);
			$user->method('getUID')->willReturn($uid);
			return $user;
		});
		$settings = $this->createMock(originalClassName: IManager::class);
		$settings->method('getAllowedAdminSettings')->willReturnCallback(
			static fn (string $section, IUser $user): array => [10 => ($held[$user->getUID()] ?? [])]
		);

		$stored = $this->createMock(originalClassName: AdminSettingMapper::class);
		$stored->method('getValue')->willReturn(['redactie']);
		$groups = $this->createMock(originalClassName: AdminTemplateService::class);
		$groups->method('getUserGroupIdsFor')->willReturnMap([['karin', ['redactie']], ['femke', []], ['dirk', []], ['pieter', []], ['sanne', []]]);

		return new AnnouncementManagers(userManager: $users, settingsManager: $settings, settings: $stored, userGroups: $groups);
	}//end managers()

	/**
	 * Administrators and holders of either setting manage; others do not.
	 *
	 * @return void
	 */
	public function testWhoManages(): void {
		$managers = $this->managers(held: [
			'femke' => [$this->createMock(originalClassName: LaunchPadAnnouncementsAdmin::class)],
			'dirk' => [$this->createMock(originalClassName: LaunchPadAdmin::class)],
			'pieter' => [$this->createMock(originalClassName: ISettings::class)],
		]);

		$this->assertTrue(condition: $managers->manages('femke'));
		$this->assertTrue(condition: $managers->manages('karin'), message: 'a former editor group counts until the move');
		$this->assertTrue(condition: $managers->manages('dirk'));
		$this->assertFalse(condition: $managers->manages('pieter'));
		$this->assertFalse(condition: $managers->manages('sanne'));
		$this->assertFalse(condition: $managers->manages('ghost'));
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

		$this->assertContains(needle: LaunchPadAnnouncementsAdmin::class, haystack: $classes);
		$this->assertContains(needle: LaunchPadAdmin::class, haystack: $classes);
		$this->assertSame(expected: [], actual: $reflection->getAttributes(NoAdminRequired::class));
	}//end testManagementEndpointsNameTheSetting()
}//end class
