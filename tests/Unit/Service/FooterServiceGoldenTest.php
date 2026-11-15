<?php

/**
 * FooterServiceGoldenTest
 *
 * Characterisation tests that pin the exact outputs and error messages of
 * FooterService's footer resolution, structured-config validation,
 * global-settings read and HTML sanitiser, so the methods can be split
 * into named steps without changing behaviour.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Service\FooterService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FooterServiceGoldenTest extends TestCase {
	/** @var AdminSettingMapper&MockObject */
	private $settingMapper;

	private FooterService $service;

	protected function setUp(): void {
		$this->settingMapper = $this->createMock(AdminSettingMapper::class);
		$this->service = new FooterService(settingMapper: $this->settingMapper);
	}

	/**
	 * Stub the five stored footer settings.
	 *
	 * @param array<string, mixed> $values Stored values keyed by AdminSetting key.
	 *
	 * @return void
	 */
	private function stubSettings(array $values): void {
		$this->settingMapper->method('getValue')->willReturnCallback(
			static fn (string $key, mixed $default = null): mixed => (array_key_exists($key, $values) === true ? $values[$key] : $default)
		);
	}

	private function inheritDashboard(): Dashboard {
		$dash = new Dashboard();
		$dash->setDashboardFooterMode(Dashboard::FOOTER_MODE_INHERIT);
		return $dash;
	}

	// ----- resolveFooterForDashboard -----

	public function testEmptyModeIsTreatedAsInherit(): void {
		$dash = new Dashboard();
		$dash->setDashboardFooterMode('');
		$this->stubSettings([
			AdminSetting::KEY_FOOTER_ENABLED => true,
			AdminSetting::KEY_FOOTER_HTML => '<p>G</p>',
		]);

		$this->assertSame(
			['mode' => 'global', 'html' => '<p>G</p>', 'config' => null, 'backgroundColor' => null, 'textColor' => null],
			$this->service->resolveFooterForDashboard(dashboard: $dash)
		);
	}

	public function testCustomModeWithBlankHtmlReturnsNull(): void {
		$dash = new Dashboard();
		$dash->setDashboardFooterMode(Dashboard::FOOTER_MODE_CUSTOM);
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$dash->setDashboardFooterHtml('   ');
		$this->stubSettings([]);

		$this->assertNull($this->service->resolveFooterForDashboard(dashboard: $dash));
	}

	public function testCustomModeWithoutHtmlReturnsNull(): void {
		$dash = new Dashboard();
		$dash->setDashboardFooterMode(Dashboard::FOOTER_MODE_CUSTOM);
		$this->stubSettings([]);

		$this->assertNull($this->service->resolveFooterForDashboard(dashboard: $dash));
	}

	public function testCustomModeCarriesGlobalColours(): void {
		$dash = new Dashboard();
		$dash->setDashboardFooterMode(Dashboard::FOOTER_MODE_CUSTOM);
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$dash->setDashboardFooterHtml('<p>Mine</p>');
		$this->stubSettings([
			AdminSetting::KEY_FOOTER_BACKGROUND_COLOR => '#000',
			AdminSetting::KEY_FOOTER_TEXT_COLOR => '#fff',
		]);

		$this->assertSame(
			['mode' => 'custom', 'html' => '<p>Mine</p>', 'config' => null, 'backgroundColor' => '#000', 'textColor' => '#fff'],
			$this->service->resolveFooterForDashboard(dashboard: $dash)
		);
	}

	public function testInheritWithVariantMapAndConfig(): void {
		$config = ['organisation' => 'ACME'];
		$this->stubSettings([
			AdminSetting::KEY_FOOTER_ENABLED => 1,
			AdminSetting::KEY_FOOTER_HTML => ['en' => '<p>EN</p>'],
			AdminSetting::KEY_FOOTER_CONFIG => $config,
			AdminSetting::KEY_FOOTER_BACKGROUND_COLOR => '#123456',
		]);

		$this->assertSame(
			['mode' => 'global', 'html' => ['en' => '<p>EN</p>'], 'config' => $config, 'backgroundColor' => '#123456', 'textColor' => null],
			$this->service->resolveFooterForDashboard(dashboard: $this->inheritDashboard())
		);
	}

	public function testInheritWithConfigOnly(): void {
		$config = ['legal' => 'L'];
		$this->stubSettings([
			AdminSetting::KEY_FOOTER_ENABLED => true,
			AdminSetting::KEY_FOOTER_HTML => [],
			AdminSetting::KEY_FOOTER_CONFIG => $config,
		]);

		$this->assertSame(
			['mode' => 'global', 'html' => null, 'config' => $config, 'backgroundColor' => null, 'textColor' => null],
			$this->service->resolveFooterForDashboard(dashboard: $this->inheritDashboard())
		);
	}

	public function testInheritWithMalformedStoredValuesReturnsNull(): void {
		$this->stubSettings([
			AdminSetting::KEY_FOOTER_ENABLED => true,
			AdminSetting::KEY_FOOTER_HTML => 42,
			AdminSetting::KEY_FOOTER_CONFIG => 'nope',
		]);

		$this->assertNull($this->service->resolveFooterForDashboard(dashboard: $this->inheritDashboard()));
	}

	// ----- getGlobalSettings -----

	public function testGlobalSettingsNormaliseMalformedValues(): void {
		$this->stubSettings([
			AdminSetting::KEY_FOOTER_ENABLED => 'yes',
			AdminSetting::KEY_FOOTER_HTML => 3.5,
			AdminSetting::KEY_FOOTER_CONFIG => 7,
			AdminSetting::KEY_FOOTER_BACKGROUND_COLOR => 12,
			AdminSetting::KEY_FOOTER_TEXT_COLOR => ['x'],
		]);

		$this->assertSame(
			['footerEnabled' => true, 'footerHtml' => '', 'footerConfig' => [], 'footerBackgroundColor' => null, 'footerTextColor' => null],
			$this->service->getGlobalSettings()
		);
	}

	public function testGlobalSettingsKeepWellFormedValues(): void {
		$this->stubSettings([
			AdminSetting::KEY_FOOTER_ENABLED => 0,
			AdminSetting::KEY_FOOTER_HTML => ['nl' => '<p>NL</p>'],
			AdminSetting::KEY_FOOTER_CONFIG => ['legal' => 'x'],
			AdminSetting::KEY_FOOTER_BACKGROUND_COLOR => '#abc',
			AdminSetting::KEY_FOOTER_TEXT_COLOR => '#def',
		]);

		$this->assertSame(
			['footerEnabled' => false, 'footerHtml' => ['nl' => '<p>NL</p>'], 'footerConfig' => ['legal' => 'x'], 'footerBackgroundColor' => '#abc', 'footerTextColor' => '#def'],
			$this->service->getGlobalSettings()
		);
	}

	// ----- validateStructuredConfig messages -----

	/**
	 * @return array<string, array{0: array<string, mixed>, 1: string}>
	 */
	public static function invalidConfigProvider(): array {
		return [
			'unknown key' => [['logoUrl' => 'x', 'bogus' => 1], 'footerConfig contains unknown key: bogus'],
			'numeric key' => [[0 => 'x'], 'footerConfig contains unknown key: 0'],
			'layout not string' => [['layoutMode' => 3], 'footerConfig.layoutMode must be one of: columns, inline'],
			'layout unknown' => [['layoutMode' => 'grid'], 'footerConfig.layoutMode must be one of: columns, inline'],
			'links not array' => [['links' => 'x'], 'footerConfig.links must be an array'],
			'link not array' => [['links' => ['x']], 'footerConfig.links entries must be {label, url} string pairs'],
			'link missing url' => [['links' => [['label' => 'a']]], 'footerConfig.links entries must be {label, url} string pairs'],
			'link missing label' => [['links' => [['url' => 'a']]], 'footerConfig.links entries must be {label, url} string pairs'],
			'link label not string' => [['links' => [['label' => 1, 'url' => 'a']]], 'footerConfig.links entries must be {label, url} string pairs'],
			'link url not string' => [['links' => [['label' => 'a', 'url' => 1]]], 'footerConfig.links entries must be {label, url} string pairs'],
			'unknown key wins over bad layout' => [['layoutMode' => 'x', 'zzz' => 1], 'footerConfig contains unknown key: zzz'],
			'bad layout wins over bad links' => [['links' => 'x', 'layoutMode' => 'x'], 'footerConfig.layoutMode must be one of: columns, inline'],
		];
	}

	/**
	 * @dataProvider invalidConfigProvider
	 *
	 * @param array<string, mixed> $config
	 */
	public function testInvalidConfigMessages(array $config, string $message): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);
		$this->service->validateStructuredConfig(config: $config);
	}

	public function testEmptyLinksAndEmptyConfigAreValid(): void {
		$this->service->validateStructuredConfig(config: []);
		$this->service->validateStructuredConfig(config: ['links' => [], 'layoutMode' => 'columns']);
		$this->assertTrue(true);
	}

	// ----- sanitiseHtml exact output -----

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function sanitiseProvider(): array {
		return [
			'empty' => ['', ''],
			'external link' => ['<A HREF="https://e.com/?a=1&b=2" class="x">x</A>', '<a href="https://e.com/?a=1&amp;b=2" rel="noopener noreferrer" target="_blank">x</a>'],
			'single-quoted value reads as empty' => ["<a href='/p'>p</a>", '<a href="">p</a>'],
			'bare value reads as empty' => ['<a href=/p>p</a>', '<a href="">p</a>'],
			'javascript link' => ['<a href="javascript:alert(1)">x</a>', '<a>x</a>'],
			'data img' => ['<img src="data:image/png;base64,AA">', '<img />'],
			'img with src' => ['<img src="/i.png" alt="x">', '<img src="/i.png" />'],
			'br' => ['a<br>b<br/>', 'a<br />b<br />'],
			'disallowed tag' => ['<div><span>t</span></div>', 't'],
			'script removed' => ['<p>a<script type="x">evil()</script>b</p>', '<p>ab</p>'],
			'attributes on p dropped' => ['<p id="x">t</p>', '<p>t</p>'],
			'whitespace attrs on a' => ['<a   >x</a>', '<a>x</a>'],
			'quoted value escaped' => ['<a href="/x\'y">x</a>', '<a href="/x&apos;y">x</a>'],
		];
	}

	/**
	 * @dataProvider sanitiseProvider
	 */
	public function testSanitiseExactOutput(string $input, string $expected): void {
		$this->assertSame($expected, $this->service->sanitiseHtml(html: $input));
	}

	// ----- updateGlobalSettings -----

	public function testUpdateWritesInOrderAndClearsWithNull(): void {
		$writes = [];
		$this->settingMapper->method('setSetting')->willReturnCallback(
			function (string $key, mixed $value) use (&$writes): AdminSetting {
				$writes[] = [$key, $value];
				return new AdminSetting();
			}
		);

		$this->service->updateGlobalSettings(patch: [
			'footerTextColor' => null,
			'footerConfig' => null,
			'footerHtml' => null,
			'footerEnabled' => 'x',
			'footerBackgroundColor' => '#fff',
			'other' => 1,
		]);

		$this->assertSame(
			[
				[AdminSetting::KEY_FOOTER_ENABLED, true],
				[AdminSetting::KEY_FOOTER_HTML, ''],
				[AdminSetting::KEY_FOOTER_CONFIG, []],
				[AdminSetting::KEY_FOOTER_BACKGROUND_COLOR, '#fff'],
				[AdminSetting::KEY_FOOTER_TEXT_COLOR, null],
			],
			$writes
		);
	}

	/**
	 * @return array<string, array{0: array<string, mixed>, 1: string}>
	 */
	public static function invalidPatchProvider(): array {
		return [
			'html wrong type' => [['footerHtml' => 5], 'footerHtml must be a string, NULL, or a variant map'],
			'html variant not string' => [['footerHtml' => ['en' => 5]], 'footerHtml language variants must be string→string'],
			'html variant numeric locale' => [['footerHtml' => ['x']], 'footerHtml language variants must be string→string'],
			'config wrong type' => [['footerConfig' => 'x'], 'footerConfig must be a JSON object'],
		];
	}

	/**
	 * @dataProvider invalidPatchProvider
	 *
	 * @param array<string, mixed> $patch
	 */
	public function testInvalidPatchMessages(array $patch, string $message): void {
		$this->settingMapper->expects($this->never())->method('setSetting');
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);
		$this->service->updateGlobalSettings(patch: $patch);
	}
}
