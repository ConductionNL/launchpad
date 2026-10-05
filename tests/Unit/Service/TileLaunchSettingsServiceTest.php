<?php

/**
 * TileLaunchSettingsServiceTest
 *
 * The administrator's allowed program schemes and single sign-on launch
 * templates (launcher-tile-launch-types, REQ-TLT-001, REQ-TLT-003).
 *
 * @category  Test
 * @package   Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\TileLaunchSettingsService;
use PHPUnit\Framework\TestCase;

class TileLaunchSettingsServiceTest extends TestCase {
	/**
	 * Stored admin settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	private function service(): TileLaunchSettingsService {
		$mapper = $this->createMock(AdminSettingMapper::class);
		$mapper->method('getValue')->willReturnCallback(
			fn (string $key, mixed $default = null): mixed => $this->settings[$key] ?? $default
		);
		$mapper->method('setSetting')->willReturnCallback(
			function (string $key, mixed $value): AdminSetting {
				$this->settings[$key] = $value;
				return new AdminSetting();
			}
		);

		return new TileLaunchSettingsService(settingMapper: $mapper);
	}//end service()

	public function testNoSchemeIsAllowedUntilAnAdministratorListsOne(): void {
		$this->assertSame([], $this->service()->getAllowedSchemes());
	}//end testNoSchemeIsAllowedUntilAnAdministratorListsOne()

	public function testSchemesAreStoredLowerCaseWithoutColonAndOnce(): void {
		$service = $this->service();

		$this->assertSame(['ms-word', 'vscode'], $service->saveAllowedSchemes(raw: [' MS-Word: ', '', 'vscode', 'ms-word']));
		$this->assertSame(['ms-word', 'vscode'], $service->getAllowedSchemes());
		$this->assertSame(['ms-word', 'vscode'], $this->settings['tile_allowed_schemes']);
	}//end testSchemesAreStoredLowerCaseWithoutColonAndOnce()

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function forbiddenSchemes(): array {
		return [
			'javascript' => ['javascript'],
			'data' => ['DATA:'],
			'file' => ['file'],
			'vbscript' => ['vbscript'],
			'about' => ['about'],
			'blob' => ['blob'],
			'not a scheme' => ['ms word'],
			'starts with a digit' => ['1password'],
		];
	}//end forbiddenSchemes()

	/**
	 * @dataProvider forbiddenSchemes
	 */
	public function testAForbiddenOrMalformedSchemeIsRefusedAndNothingStored(string $scheme): void {
		try {
			$this->service()->saveAllowedSchemes(raw: ['ms-word', $scheme]);
			$this->fail('accepted ' . $scheme);
		} catch (InvalidArgumentException) {
			$this->assertSame([], $this->settings);
		}
	}//end testAForbiddenOrMalformedSchemeIsRefusedAndNothingStored()

	public function testAForbiddenSchemeStoredByHandIsStillNeverAllowed(): void {
		$this->settings['tile_allowed_schemes'] = ['javascript', 'ms-excel', 42];

		$this->assertSame(['ms-excel'], $this->service()->getAllowedSchemes());
	}//end testAForbiddenSchemeStoredByHandIsStillNeverAllowed()

	public function testATemplateGetsAKeyFromItsNameAndResolvesWithAnEncodedAppId(): void {
		$service = $this->service();
		$saved = $service->saveSsoTemplates(raw: [
			['name' => ' Microsoft Entra ID ', 'urlTemplate' => 'https://launcher.myapps.microsoft.com/api/signin/{appId}?tenantId=contoso'],
			['key' => 'keycloak', 'name' => 'Keycloak', 'urlTemplate' => 'https://sso.example.nl/realms/gemeente/protocol/saml/clients/{appId}'],
		]);

		$this->assertSame('microsoft-entra-id', $saved[0]['key']);
		$this->assertSame('Microsoft Entra ID', $saved[0]['name']);
		$this->assertSame($saved, $service->getSsoTemplates());
		$this->assertSame(
			'https://launcher.myapps.microsoft.com/api/signin/a1b2c3?tenantId=contoso',
			$service->resolveSsoUrl(templateKey: 'microsoft-entra-id', appId: 'a1b2c3')
		);
		$this->assertSame(
			'https://sso.example.nl/realms/gemeente/protocol/saml/clients/urn%3Abelasting',
			$service->resolveSsoUrl(templateKey: 'keycloak', appId: 'urn:belasting')
		);
		$this->assertNull($service->resolveSsoUrl(templateKey: 'okta', appId: 'a1b2c3'));
	}//end testATemplateGetsAKeyFromItsNameAndResolvesWithAnEncodedAppId()

	/**
	 * @return array<string, array{0: array}>
	 */
	public static function invalidTemplates(): array {
		return [
			'http' => [['name' => 'Entra', 'urlTemplate' => 'http://login.example.nl/{appId}']],
			'no placeholder' => [['name' => 'Entra', 'urlTemplate' => 'https://login.example.nl/app']],
			'placeholder in the host' => [['name' => 'Entra', 'urlTemplate' => 'https://{appId}.example.nl/']],
			'script' => [['name' => 'Entra', 'urlTemplate' => 'javascript:alert("{appId}")']],
			'no name' => [['name' => ' ', 'urlTemplate' => 'https://login.example.nl/{appId}']],
			'not a list entry' => ['https://login.example.nl/{appId}'],
		];
	}//end invalidTemplates()

	/**
	 * @dataProvider invalidTemplates
	 */
	public function testAnInvalidTemplateIsRefusedAndNothingStored(mixed $template): void {
		try {
			$this->service()->saveSsoTemplates(raw: [$template]);
			$this->fail('accepted an invalid template');
		} catch (InvalidArgumentException) {
			$this->assertSame([], $this->settings);
		}
	}//end testAnInvalidTemplateIsRefusedAndNothingStored()

	public function testTwoTemplatesWithTheSameKeyAreRefused(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service()->saveSsoTemplates(raw: [
			['name' => 'Entra', 'urlTemplate' => 'https://a.example.nl/{appId}'],
			['name' => 'entra', 'urlTemplate' => 'https://b.example.nl/{appId}'],
		]);
	}//end testTwoTemplatesWithTheSameKeyAreRefused()
}//end class
