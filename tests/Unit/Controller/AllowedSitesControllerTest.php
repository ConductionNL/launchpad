<?php

/**
 * AllowedSitesControllerTest
 *
 * REQ-ASET-021: the allowed websites are read and replaced through the admin
 * API; an entry that is not a host is refused with a message.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-24-allowed-websites-list/specs/admin-settings/spec.md#requirement-one-list-of-allowed-websites-with-a-purpose-per-site-req-aset-021
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\AllowedSitesController;
use OCA\LaunchPad\Service\AllowedSitesService;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\IAppConfig;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AllowedSitesControllerTest extends TestCase {

	/**
	 * A controller over an in-memory config.
	 *
	 * @param array<string, string> $values The stored values.
	 *
	 * @return AllowedSitesController
	 */
	private function controllerWith(array &$values): AllowedSitesController {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturnCallback(
			static function (string $app, string $key, string $default = '') use (&$values): string {
				return $values[$key] ?? $default;
			}
		);
		$config->method('setValueString')->willReturnCallback(
			static function (string $app, string $key, string $value) use (&$values): bool {
				$values[$key] = $value;
				return true;
			}
		);

		return new AllowedSitesController(
			request: $this->createMock(originalClassName: IRequest::class),
			allowedSites: new AllowedSitesService(appConfig: $config)
		);
	}//end controllerWith()

	/**
	 * Shows and replaces the list.
	 *
	 * @return void
	 */
	public function testShowsAndReplacesTheList(): void {
		$values     = [];
		$controller = $this->controllerWith(values: $values);

		$saved = $controller->update(sites: [['host' => 'App.PowerBI.com', 'embed' => true, 'fetch' => false]]);
		$this->assertSame(expected: 200, actual: $saved->getStatus());
		$this->assertSame(expected: ['sites' => [['host' => 'app.powerbi.com', 'embed' => true, 'fetch' => false]]], actual: $saved->getData());
		$this->assertSame(expected: $saved->getData(), actual: $controller->show()->getData());
	}//end testShowsAndReplacesTheList()

	/**
	 * Refuses an entry that is not a host.
	 *
	 * @return void
	 */
	public function testRefusesAnEntryThatIsNotAHost(): void {
		$values   = [];
		$response = $this->controllerWith(values: $values)->update(sites: [['host' => '*.example.com', 'embed' => true]]);

		$this->assertSame(expected: 400, actual: $response->getStatus());
		$this->assertStringContainsString(needle: 'is not a host name', haystack: $response->getData()['message']);
		$this->assertArrayNotHasKey(key: 'allowed_sites', array: $values);
	}//end testRefusesAnEntryThatIsNotAHost()

	/**
	 * Both endpoints are for administrators only.
	 *
	 * @return void
	 */
	public function testBothEndpointsAreForAdministratorsOnly(): void {
		foreach (['show', 'update'] as $method) {
			$attributes = (new ReflectionMethod(AllowedSitesController::class, $method))->getAttributes(AuthorizedAdminSetting::class);
			$this->assertCount(expectedCount: 1, haystack: $attributes, message: $method);
		}
	}//end testBothEndpointsAreForAdministratorsOnly()
}//end class
