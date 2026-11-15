<?php

/**
 * TileLinkValidator Test
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/launcher-tile-launch-types/tasks.md#task-2
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\TileLaunchSettings;
use OCA\LaunchPad\Service\TileLinkValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * The program, remote-desktop and sso link types, checked on the server.
 */
#[CoversClass(TileLinkValidator::class)]
#[UsesClass(TileLaunchSettings::class)]
class TileLinkValidatorTest extends TestCase {

	/**
	 * A validator over the given admin settings.
	 *
	 * @param array<int, string> $schemes   The allowed schemes.
	 * @param array<int, mixed>  $templates The launch templates.
	 *
	 * @return TileLinkValidator
	 */
	private function validator(array $schemes = [], array $templates = []): TileLinkValidator {
		$settings = $this->createMock(AdminSettingsService::class);
		$settings->method('getSettings')->willReturn(['tileAllowedSchemes' => $schemes, 'ssoLaunchTemplates' => $templates]);
		return new TileLinkValidator(settings: $settings);
	}//end validator()

	/**
	 * The Entra ID template of the spec's scenario.
	 *
	 * @return array{key: string, name: string, urlTemplate: string}
	 */
	private static function entra(): array {
		return ['key' => 'entra', 'name' => 'Microsoft Entra ID', 'urlTemplate' => 'https://launcher.myapps.microsoft.com/api/signin/{appId}?tenantId=contoso'];
	}//end entra()

	/**
	 * Only the three launch types are this validator's.
	 *
	 * @return void
	 */
	public function testKnowsTheThreeLaunchTypes(): void {
		$this->assertTrue(TileLinkValidator::isLaunchType(type: 'program'));
		$this->assertTrue(TileLinkValidator::isLaunchType(type: 'remote-desktop'));
		$this->assertTrue(TileLinkValidator::isLaunchType(type: 'sso'));
		$this->assertFalse(TileLinkValidator::isLaunchType(type: 'url'));
		$this->assertFalse(TileLinkValidator::isLaunchType(type: 'app'));
		$this->assertFalse(TileLinkValidator::isLaunchType(type: null));
	}//end testKnowsTheThreeLaunchTypes()

	/**
	 * An allowed scheme is kept as typed (the Word scenario).
	 *
	 * @return void
	 */
	public function testAnAllowedProgramSchemeIsAccepted(): void {
		$address = 'ms-word:ofe|u|https://docs.example.nl/sjabloon.docx';
		$this->assertSame($address, $this->validator(schemes: ['ms-word'])->check(type: 'program', value: '  ' . $address . ' ', content: []));
		$this->assertSame('MS-WORD:ofe', $this->validator(schemes: ['ms-word'])->check(type: 'program', value: 'MS-WORD:ofe', content: []));
	}//end testAnAllowedProgramSchemeIsAccepted()

	/**
	 * A scheme the administrator did not allow is refused; the list starts empty.
	 *
	 * @return void
	 */
	public function testASchemeNotOnTheListIsRefused(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('"vscode" is not on the allowed list');
		$this->validator()->check(type: 'program', value: 'vscode://file/x', content: []);
	}//end testASchemeNotOnTheListIsRefused()

	/**
	 * Every forbidden scheme.
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
	 * A forbidden scheme is refused even when the stored list names it.
	 *
	 * @param string $scheme The forbidden scheme.
	 *
	 * @return void
	 */
	#[DataProvider('forbiddenSchemes')]
	public function testAForbiddenSchemeIsRefusedEvenWhenListed(string $scheme): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('"' . $scheme . '" is never allowed');
		$this->validator(schemes: [$scheme])->check(type: 'program', value: strtoupper($scheme) . ':alert(1)', content: []);
	}//end testAForbiddenSchemeIsRefusedEvenWhenListed()

	/**
	 * Addresses that are not a scheme address are refused.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function notProgramAddresses(): array {
		return [
			'empty' => [''],
			'no scheme' => ['word.exe'],
			'space inside' => ['ms-word:ofe u'],
			'newline inside' => ["ms-word:ofe\nx"],
			'too long' => ['ms-word:' . str_repeat('a', 2050)],
		];
	}//end notProgramAddresses()

	/**
	 * A program tile needs a single-line scheme address.
	 *
	 * @param string $address The address.
	 *
	 * @return void
	 */
	#[DataProvider('notProgramAddresses')]
	public function testAnAddressWithoutAUsableSchemeIsRefused(string $address): void {
		$this->expectException(InvalidArgumentException::class);
		$this->validator(schemes: ['ms-word'])->check(type: 'program', value: $address, content: []);
	}//end testAnAddressWithoutAUsableSchemeIsRefused()

	/**
	 * A gateway tile keeps its https address as the link value.
	 *
	 * @return void
	 */
	public function testAGatewayAddressBecomesTheLink(): void {
		$url = 'https://desktop.gemeente.nl/guacamole/#/client/werkplek';
		$this->assertSame($url, $this->validator()->check(type: 'remote-desktop', value: 'ignored', content: ['remote' => ['mode' => 'gateway', 'url' => $url]]));
	}//end testAGatewayAddressBecomesTheLink()

	/**
	 * Gateway addresses that are refused.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function badGateways(): array {
		return [
			'http' => ['http://desktop.gemeente.nl/'],
			'credentials' => ['https://pieter:geheim@desktop.gemeente.nl/'],
			'script' => ['javascript:alert(1)'],
			'no host' => ['https:///guacamole'],
			'space' => ['https://desktop.gemeente.nl/a b'],
		];
	}//end badGateways()

	/**
	 * A gateway must be https without credentials.
	 *
	 * @param string $url The address.
	 *
	 * @return void
	 */
	#[DataProvider('badGateways')]
	public function testAGatewayMustBeHttpsWithoutCredentials(string $url): void {
		$this->expectException(InvalidArgumentException::class);
		$this->validator()->check(type: 'remote-desktop', value: '', content: ['remote' => ['mode' => 'gateway', 'url' => $url]]);
	}//end testAGatewayMustBeHttpsWithoutCredentials()

	/**
	 * An RDP tile has no address of its own and returns its checked connection.
	 *
	 * @return void
	 */
	public function testAnRdpConnectionIsChecked(): void {
		$content = ['remote' => ['mode' => 'rdp', 'host' => 'rds01.gemeente.local', 'port' => '3390', 'remoteApp' => '||Belastingen', 'gateway' => 'rdgw.gemeente.nl']];
		$validator = $this->validator();

		$this->assertSame('', $validator->check(type: 'remote-desktop', value: 'https://elsewhere.example', content: $content));
		$this->assertSame(
			['host' => 'rds01.gemeente.local', 'port' => 3390, 'remoteApp' => '||Belastingen', 'gateway' => 'rdgw.gemeente.nl'],
			$validator->rdpConnection(content: $content)
		);
		$this->assertSame(
			['host' => 'pc12', 'port' => 3389, 'remoteApp' => '', 'gateway' => ''],
			$validator->rdpConnection(content: ['remote' => ['mode' => 'rdp', 'host' => 'pc12']])
		);
	}//end testAnRdpConnectionIsChecked()

	/**
	 * RDP settings that are refused.
	 *
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function badRdp(): array {
		return [
			'no remote' => [[]],
			'unknown mode' => [['remote' => ['mode' => 'vnc', 'host' => 'pc12']]],
			'no host' => [['remote' => ['mode' => 'rdp']]],
			'host with newline' => [['remote' => ['mode' => 'rdp', 'host' => "pc12\nusername:s:admin"]]],
			'host with port' => [['remote' => ['mode' => 'rdp', 'host' => 'pc12:3389']]],
			'port zero' => [['remote' => ['mode' => 'rdp', 'host' => 'pc12', 'port' => 0]]],
			'port too high' => [['remote' => ['mode' => 'rdp', 'host' => 'pc12', 'port' => 70000]]],
			'port text' => [['remote' => ['mode' => 'rdp', 'host' => 'pc12', 'port' => '33a']]],
			'program with colon' => [['remote' => ['mode' => 'rdp', 'host' => 'pc12', 'remoteApp' => '||App:s:x']]],
			'gateway url' => [['remote' => ['mode' => 'rdp', 'host' => 'pc12', 'gateway' => 'https://rdgw.gemeente.nl']]],
		];
	}//end badRdp()

	/**
	 * Host, port, program and gateway must match their patterns.
	 *
	 * @param array<string, mixed> $content The placement content.
	 *
	 * @return void
	 */
	#[DataProvider('badRdp')]
	public function testBadRdpSettingsAreRefused(array $content): void {
		$this->expectException(InvalidArgumentException::class);
		$this->validator()->check(type: 'remote-desktop', value: '', content: $content);
	}//end testBadRdpSettingsAreRefused()

	/**
	 * A gateway tile has no RDP connection to write.
	 *
	 * @return void
	 */
	public function testAGatewayTileHasNoRdpConnection(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->validator()->rdpConnection(content: ['remote' => ['mode' => 'gateway', 'url' => 'https://desktop.gemeente.nl/']]);
	}//end testAGatewayTileHasNoRdpConnection()

	/**
	 * An sso tile opens its template with the encoded app id (the Entra ID scenario).
	 *
	 * @return void
	 */
	public function testAnSsoTileOpensItsTemplate(): void {
		$validator = $this->validator(templates: [self::entra()]);

		$this->assertSame(
			'https://launcher.myapps.microsoft.com/api/signin/a1b2c3?tenantId=contoso',
			$validator->check(type: 'sso', value: 'https://evil.example/', content: ['sso' => ['template' => 'entra', 'appId' => 'a1b2c3']])
		);
		$this->assertSame(
			'https://launcher.myapps.microsoft.com/api/signin/urn%3Aapp.1?tenantId=contoso',
			$validator->check(type: 'sso', value: '', content: ['sso' => ['template' => 'entra', 'appId' => 'urn:app.1']])
		);
	}//end testAnSsoTileOpensItsTemplate()

	/**
	 * Single sign-on settings that are refused.
	 *
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function badSso(): array {
		return [
			'no sso' => [[]],
			'unknown template' => [['sso' => ['template' => 'keycloak', 'appId' => 'a1']]],
			'no app id' => [['sso' => ['template' => 'entra']]],
			'app id with slash' => [['sso' => ['template' => 'entra', 'appId' => '../admin']]],
			'app id too long' => [['sso' => ['template' => 'entra', 'appId' => str_repeat('a', 129)]]],
		];
	}//end badSso()

	/**
	 * The template must exist and the app id must match its pattern.
	 *
	 * @param array<string, mixed> $content The placement content.
	 *
	 * @return void
	 */
	#[DataProvider('badSso')]
	public function testBadSsoSettingsAreRefused(array $content): void {
		$this->expectException(InvalidArgumentException::class);
		$this->validator(templates: [self::entra()])->check(type: 'sso', value: '', content: $content);
	}//end testBadSsoSettingsAreRefused()

	/**
	 * A type that is not a launch type is not checked here.
	 *
	 * @return void
	 */
	public function testAnotherTypeIsRefused(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->validator()->check(type: 'url', value: 'https://example.org', content: []);
	}//end testAnotherTypeIsRefused()
}//end class
