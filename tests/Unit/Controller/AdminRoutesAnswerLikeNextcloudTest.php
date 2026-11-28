<?php

/**
 * AdminRoutesAnswerLikeNextcloudTest
 *
 * The admin-only upload and cleanup routes are refused by Nextcloud itself:
 * 401 without a session, 403 with Nextcloud's message for a non-admin. That
 * answer comes from the `AuthorizedAdminSetting` attribute, so the attribute,
 * not LaunchPad's own envelope, is what the specs promise.
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

use OCA\LaunchPad\Controller\AdminCleanupController;
use OCA\LaunchPad\Controller\AdminPageController;
use OCA\LaunchPad\Controller\ResourceController;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Every admin-only route leaves its refusal to Nextcloud.
 */
class AdminRoutesAnswerLikeNextcloudTest extends TestCase {

	/**
	 * The admin-only routes whose refusal the specs describe as Nextcloud's.
	 *
	 * @return array<string, array{0: class-string, 1: string}>
	 */
	public static function adminRoutes(): array {
		return [
			'base64 upload' => [ResourceController::class, 'upload'],
			'multipart upload' => [ResourceController::class, 'uploadMultipart'],
			'cleanup scan' => [AdminCleanupController::class, 'scan'],
			'cleanup purge' => [AdminCleanupController::class, 'purge'],
			'administration page' => [AdminPageController::class, 'show'],
		];
	}//end adminRoutes()

	/**
	 * The route carries `AuthorizedAdminSetting(LaunchPadAdmin)` and nothing
	 * that would let a non-admin or a guest past Nextcloud's check.
	 *
	 * @param class-string $class  The controller.
	 * @param string       $method The route method.
	 *
	 * @return void
	 */
	#[DataProvider('adminRoutes')]
	public function testNextcloudRefusesBeforeLaunchPadRuns(string $class, string $method): void {
		$reflection = new ReflectionMethod(objectOrMethod: $class, method: $method);

		$admin = $reflection->getAttributes(name: AuthorizedAdminSetting::class);
		$this->assertCount(expectedCount: 1, haystack: $admin, message: $class . '::' . $method);
		$this->assertSame(expected: LaunchPadAdmin::class, actual: $admin[0]->newInstance()->getSettings());

		$this->assertSame(expected: [], actual: $reflection->getAttributes(name: NoAdminRequired::class));
		$this->assertSame(expected: [], actual: $reflection->getAttributes(name: PublicPage::class));
	}//end testNextcloudRefusesBeforeLaunchPadRuns()
}//end class
