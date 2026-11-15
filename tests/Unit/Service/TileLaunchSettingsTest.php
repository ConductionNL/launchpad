<?php

/**
 * TileLaunchSettings Test
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md#requirement-a-tile-can-start-a-program-on-the-users-computer-req-tlt-001
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\TileLaunchSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The allowed program schemes and the SSO launch templates, checked on write and on read.
 */
#[CoversClass(TileLaunchSettings::class)]
class TileLaunchSettingsTest extends TestCase {

	/**
	 * The Entra ID template of the spec's scenario.
	 *
	 * @return array{key: string, name: string, urlTemplate: string}
	 */
	private static function entra(): array {
		return ['key' => 'entra', 'name' => 'Microsoft Entra ID', 'urlTemplate' => 'https://launcher.myapps.microsoft.com/api/signin/{appId}?tenantId=contoso'];
	}//end entra()

	/**
	 * Lower-case schemes are kept once each; a trailing colon is dropped.
	 *
	 * @return void
	 */
	public function testAllowedSchemesAreKeptOnce(): void {
		$this->assertSame(['ms-word', 'ms-excel', 'tel'], (new TileLaunchSettings())->schemes(input: ['ms-word', 'ms-excel:', ' tel ', 'ms-word']));
		$this->assertSame([], (new TileLaunchSettings())->schemes(input: []));
	}//end testAllowedSchemesAreKeptOnce()

	/**
	 * Every forbidden scheme is refused, even when an administrator lists it.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function forbiddenSchemes(): array {
		$cases = [];
		foreach (TileLaunchSettings::FORBIDDEN_SCHEMES as $scheme) {
			$cases[$scheme] = [$scheme];
		}

		return $cases;
	}//end forbiddenSchemes()

	/**
	 * A forbidden scheme is refused by name.
	 *
	 * @param string $scheme The scheme.
	 *
	 * @return void
	 */
	#[DataProvider('forbiddenSchemes')]
	public function testAForbiddenSchemeIsRefused(string $scheme): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('The scheme "' . $scheme . '" is never allowed on a tile.');

		(new TileLaunchSettings())->schemes(input: ['ms-word', $scheme]);
	}//end testAForbiddenSchemeIsRefused()

	/**
	 * Upper case, a leading digit, punctuation and a non-string are not schemes.
	 *
	 * @return void
	 */
	public function testAMalformedSchemeIsRefused(): void {
		foreach (['MS-WORD', '1abc', 'ms word', 'a/b', '', 42] as $scheme) {
			try {
				(new TileLaunchSettings())->schemes(input: [$scheme]);
				$this->fail('Accepted ' . var_export($scheme, true));
			} catch (InvalidArgumentException $e) {
				$this->assertStringContainsString('is not a lower-case URL scheme', $e->getMessage());
			}
		}

		$this->expectException(InvalidArgumentException::class);
		(new TileLaunchSettings())->schemes(input: 'ms-word');
	}//end testAMalformedSchemeIsRefused()

	/**
	 * An https template with {appId} once is kept, trimmed.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md#requirement-a-tile-can-start-single-sign-on-into-a-company-app-req-tlt-003
	 */
	public function testAnHttpsTemplateWithTheAppIdIsKept(): void {
		$keycloak = ['key' => 'keycloak', 'name' => ' Keycloak ', 'urlTemplate' => 'https://sso.example.nl/realms/gemeente/protocol/saml/clients/{appId}'];

		$this->assertSame(
			[self::entra(), ['key' => 'keycloak', 'name' => 'Keycloak', 'urlTemplate' => $keycloak['urlTemplate']]],
			(new TileLaunchSettings())->templates(input: [self::entra(), $keycloak])
		);
	}//end testAnHttpsTemplateWithTheAppIdIsKept()

	/**
	 * Templates that are not https, lack or repeat {appId}, carry another placeholder or credentials, or reuse a key, are refused.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/launcher-tile-launch-types/specs/tiles/spec.md#requirement-a-tile-can-start-single-sign-on-into-a-company-app-req-tlt-003
	 */
	public function testABadTemplateIsRefused(): void {
		$bad = [
			'http' => ['key' => 'a', 'name' => 'A', 'urlTemplate' => 'http://idp.example.nl/{appId}'],
			'no placeholder' => ['key' => 'a', 'name' => 'A', 'urlTemplate' => 'https://idp.example.nl/start'],
			'twice' => ['key' => 'a', 'name' => 'A', 'urlTemplate' => 'https://idp.example.nl/{appId}/{appId}'],
			'other placeholder' => ['key' => 'a', 'name' => 'A', 'urlTemplate' => 'https://idp.example.nl/{appId}?u={user}'],
			'credentials' => ['key' => 'a', 'name' => 'A', 'urlTemplate' => 'https://admin:secret@idp.example.nl/{appId}'],
			'javascript' => ['key' => 'a', 'name' => 'A', 'urlTemplate' => 'javascript:alert({appId})'],
			'bad key' => ['key' => 'Entra ID', 'name' => 'A', 'urlTemplate' => 'https://idp.example.nl/{appId}'],
			'no name' => ['key' => 'a', 'name' => ' ', 'urlTemplate' => 'https://idp.example.nl/{appId}'],
		];
		foreach ($bad as $case => $template) {
			try {
				(new TileLaunchSettings())->templates(input: [$template]);
				$this->fail('Accepted ' . $case);
			} catch (InvalidArgumentException $e) {
				$this->assertNotSame('', $e->getMessage(), $case);
			}
		}

		$this->expectExceptionMessage('The launch template key "entra" is used twice.');
		(new TileLaunchSettings())->templates(input: [self::entra(), self::entra()]);
	}//end testABadTemplateIsRefused()

	/**
	 * A stored value that no longer validates reads as empty; nothing stored reads as empty.
	 *
	 * @return void
	 */
	public function testAStoredValueThatNoLongerValidatesReadsAsEmpty(): void {
		$settings = new TileLaunchSettings();

		$this->assertSame([], $settings->stored(setting: 'schemes', stored: null));
		$this->assertSame([], $settings->stored(setting: 'schemes', stored: ['ms-word', 'javascript']));
		$this->assertSame(['ms-word'], $settings->stored(setting: 'schemes', stored: ['ms-word']));
		$this->assertSame([], $settings->stored(setting: 'templates', stored: [['key' => 'x', 'name' => 'X', 'urlTemplate' => 'http://x/{appId}']]));
		$this->assertSame([self::entra()], $settings->stored(setting: 'templates', stored: [self::entra()]));
	}//end testAStoredValueThatNoLongerValidatesReadsAsEmpty()

	/**
	 * Through the settings service: both lists are empty by default, written when valid, and a refusal writes neither.
	 *
	 * @return void
	 */
	public function testTheSettingsServiceReadsAndWritesBothLists(): void {
		$stored = [];
		$mapper = $this->createMock(AdminSettingMapper::class);
		$mapper->method('getAllAsArray')->willReturnCallback(static function () use (&$stored): array {
			return $stored;
		});
		$mapper->method('setSetting')->willReturnCallback(static function (string $key, mixed $value) use (&$stored): AdminSetting {
			$stored[$key] = $value;
			return new AdminSetting();
		});
		$service = new AdminSettingsService(settingMapper: $mapper);

		$this->assertSame([[], []], [$service->getSettings()['tileAllowedSchemes'], $service->getSettings()['ssoLaunchTemplates']]);

		$service->updateSettings(settings: ['tileAllowedSchemes' => ['ms-word'], 'ssoLaunchTemplates' => [self::entra()]]);
		$this->assertSame(['ms-word'], $stored[AdminSetting::KEY_TILE_ALLOWED_SCHEMES]);
		$this->assertSame([['ms-word'], [self::entra()]], [$service->getSettings()['tileAllowedSchemes'], $service->getSettings()['ssoLaunchTemplates']]);

		try {
			$service->updateSettings(settings: ['tileAllowedSchemes' => ['ms-excel'], 'ssoLaunchTemplates' => [['key' => 'x', 'name' => 'X', 'urlTemplate' => 'http://x/{appId}']]]);
			$this->fail('Accepted an http template');
		} catch (InvalidArgumentException $e) {
			$this->assertSame(['ms-word'], $stored[AdminSetting::KEY_TILE_ALLOWED_SCHEMES], 'a refused write leaves both lists as they were');
		}
	}//end testTheSettingsServiceReadsAndWritesBothLists()
}//end class
