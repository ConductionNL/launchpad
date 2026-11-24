<?php

/**
 * AllowedSitesServiceTest
 *
 * REQ-ASET-021: one list of allowed websites with a purpose per host, read
 * fail-closed, stored normalised, and read by the features for their purpose.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
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

namespace Unit\Service;

use OCA\LaunchPad\Exception\InvalidAllowedSiteException;
use OCA\LaunchPad\Service\AllowedSitesService;
use OCA\LaunchPad\Service\HostAllowList;
use OCA\LaunchPad\Service\IframeService;
use OCA\LaunchPad\Service\ImageHostService;
use OCP\Http\Client\IClientService;
use OCP\IAppConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[Small]
class AllowedSitesServiceTest extends TestCase {

	/**
	 * The board's list: both, embed only, fetch only.
	 *
	 * @var string
	 */
	private const BOARD_LIST = '[{"host":"www.zuiddrecht.nl","embed":true,"fetch":true},'
		. '{"host":"app.powerbi.com","embed":true,"fetch":false},'
		. '{"host":"kcc-api.zuiddrecht.nl","embed":false,"fetch":true}]';

	/**
	 * An app config backed by an in-memory map.
	 *
	 * @param array<string, string> $values The stored values.
	 *
	 * @return IAppConfig
	 */
	private function configWith(array &$values): IAppConfig {
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

		return $config;
	}//end configWith()

	/**
	 * @return array<string, array{string}>
	 */
	public static function failClosedValues(): array {
		return [
			'unset'      => [''],
			'empty'      => ['[]'],
			'malformed'  => ['{not json'],
			'scalar'     => ['"app.powerbi.com"'],
			'no purpose' => ['[{"host":"app.powerbi.com","embed":false,"fetch":false}]'],
			'not a host' => ['[{"host":"https://","embed":true,"fetch":true}]'],
			'bare host'  => ['["app.powerbi.com"]'],
		];
	}//end failClosedValues()

	/**
	 * An unusable list allows nothing.
	 *
	 * @param string $raw From the data provider.
	 *
	 * @return void
	 */
	#[DataProvider('failClosedValues')]
	public function testAnUnusableListAllowsNothing(string $raw): void {
		$values  = ['allowed_sites' => $raw];
		$service = new AllowedSitesService(appConfig: $this->configWith(values: $values));

		$this->assertSame(expected: [], actual: $service->hostsFor(purpose: 'embed'));
		$this->assertSame(expected: [], actual: $service->hostsFor(purpose: 'fetch'));
		$this->assertFalse(condition: $service->permits(url: 'https://app.powerbi.com/r', purpose: 'embed'));
	}//end testAnUnusableListAllowsNothing()

	/**
	 * Each host is allowed for its purpose only.
	 *
	 * @return void
	 */
	public function testEachHostIsAllowedForItsPurposeOnly(): void {
		$values  = ['allowed_sites' => self::BOARD_LIST];
		$service = new AllowedSitesService(appConfig: $this->configWith(values: $values));

		$this->assertSame(expected: ['www.zuiddrecht.nl', 'app.powerbi.com'], actual: $service->hostsFor(purpose: 'embed'));
		$this->assertSame(expected: ['www.zuiddrecht.nl', 'kcc-api.zuiddrecht.nl'], actual: $service->hostsFor(purpose: 'fetch'));
		$this->assertSame(expected: [], actual: $service->hostsFor(purpose: 'other'));

		// Power BI may be embedded, not called.
		$this->assertTrue(condition: $service->permits(url: 'https://APP.powerbi.com/reportEmbed', purpose: 'embed'));
		$this->assertFalse(condition: $service->permits(url: 'https://app.powerbi.com/api', purpose: 'fetch'));
		// The KCC API may be called, not embedded.
		$this->assertTrue(condition: $service->permits(url: 'https://kcc-api.zuiddrecht.nl/wachttijd', purpose: 'fetch'));
		$this->assertFalse(condition: $service->permits(url: 'https://kcc-api.zuiddrecht.nl/', purpose: 'embed'));
		// Exact hosts only, and a URL without a host never passes.
		$this->assertFalse(condition: $service->permits(url: 'https://a.app.powerbi.com/', purpose: 'embed'));
		$this->assertFalse(condition: $service->permits(url: 'not a url', purpose: 'embed'));
	}//end testEachHostIsAllowedForItsPurposeOnly()

	/**
	 * @return array<string, array{string, string|null}>
	 */
	public static function hostSpellings(): array {
		return [
			'upper case'      => [' App.PowerBI.com ', 'app.powerbi.com'],
			'with scheme'     => ['https://app.powerbi.com', 'app.powerbi.com'],
			'with path'       => ['https://app.powerbi.com/groups/me?x=1#y', 'app.powerbi.com'],
			'with port'       => ['kcc-api.zuiddrecht.nl:8443', 'kcc-api.zuiddrecht.nl'],
			'single label'    => ['intranet', 'intranet'],
			'wildcard'        => ['*.zuiddrecht.nl', null],
			'space inside'    => ['www zuiddrecht.nl', null],
			'only scheme'     => ['https://', null],
			'leading hyphen'  => ['-bad.example.com', null],
			'user in address' => ['user@example.com', null],
		];
	}//end hostSpellings()

	/**
	 * Normalises a host.
	 *
	 * @param string  $raw      From the data provider.
	 * @param ?string $expected From the data provider.
	 *
	 * @return void
	 */
	#[DataProvider('hostSpellings')]
	public function testNormalisesAHost(string $raw, ?string $expected): void {
		$this->assertSame(expected: $expected, actual: AllowedSitesService::normaliseHost(raw: $raw));
	}//end testNormalisesAHost()

	/**
	 * Stores the list normalised and merges duplicates.
	 *
	 * @return void
	 */
	public function testStoresTheListNormalisedAndMergesDuplicates(): void {
		$values  = [];
		$service = new AllowedSitesService(appConfig: $this->configWith(values: $values));

		$stored = $service->setSites(sites: [
			['host' => 'https://App.PowerBI.com/x', 'embed' => true, 'fetch' => false],
			['host' => '  ', 'embed' => true, 'fetch' => true],
			['host' => 'app.powerbi.com', 'embed' => false, 'fetch' => true],
			['host' => 'kcc-api.zuiddrecht.nl:443', 'fetch' => true],
		]);

		$expected = [
			['host' => 'app.powerbi.com', 'embed' => true, 'fetch' => true],
			['host' => 'kcc-api.zuiddrecht.nl', 'embed' => false, 'fetch' => true],
		];
		$this->assertSame(expected: $expected, actual: $stored);
		$this->assertSame(expected: $expected, actual: json_decode(json: $values['allowed_sites'], associative: true));
		$this->assertSame(expected: $expected, actual: $service->getSites());
	}//end testStoresTheListNormalisedAndMergesDuplicates()

	/**
	 * @return array<string, array{mixed, string}>
	 */
	public static function refusedLists(): array {
		return [
			'not a list'  => ['app.powerbi.com', 'must be a list'],
			'bare string' => [['app.powerbi.com'], 'needs a host and a purpose'],
			'no host'     => [[['embed' => true]], 'needs a host and a purpose'],
			'not a host'  => [[['host' => '*.zuiddrecht.nl', 'embed' => true]], '"*.zuiddrecht.nl" is not a host name'],
			'no purpose'  => [[['host' => 'app.powerbi.com', 'embed' => false, 'fetch' => false]], 'Choose whether app.powerbi.com'],
		];
	}//end refusedLists()

	/**
	 * Refuses an entry that is not a site.
	 *
	 * @param mixed  $sites   From the data provider.
	 * @param string $message From the data provider.
	 *
	 * @return void
	 */
	#[DataProvider('refusedLists')]
	public function testRefusesAnEntryThatIsNotASite(mixed $sites, string $message): void {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->expects($this->never())->method('setValueString');
		$service = new AllowedSitesService(appConfig: $config);

		$this->expectException(exception: InvalidAllowedSiteException::class);
		$this->expectExceptionMessage(message: $message);
		$service->setSites(sites: $sites);
	}//end testRefusesAnEntryThatIsNotASite()

	/**
	 * Adds hosts for a purpose keeping the list.
	 *
	 * @return void
	 */
	public function testAddsHostsForAPurposeKeepingTheList(): void {
		$values  = ['allowed_sites' => '[{"host":"app.powerbi.com","embed":true,"fetch":false}]'];
		$service = new AllowedSitesService(appConfig: $this->configWith(values: $values));

		$this->assertSame(expected: 2, actual: $service->addHosts(hosts: ['App.PowerBI.com', 'kcc-api.zuiddrecht.nl', 'not a host', 7], purpose: 'fetch'));
		$this->assertSame(expected: 0, actual: $service->addHosts(hosts: ['kcc-api.zuiddrecht.nl'], purpose: 'fetch'));
		$this->assertSame(
			expected: [
				['host' => 'app.powerbi.com', 'embed' => true, 'fetch' => true],
				['host' => 'kcc-api.zuiddrecht.nl', 'embed' => false, 'fetch' => true],
			],
			actual: $service->getSites()
		);
	}//end testAddsHostsForAPurposeKeepingTheList()

	/**
	 * Adding nothing writes nothing.
	 *
	 * @return void
	 */
	public function testAddingNothingWritesNothing(): void {
		$config = $this->createMock(originalClassName: IAppConfig::class);
		$config->method('getValueString')->willReturn('');
		$config->expects($this->never())->method('setValueString');

		$this->assertSame(expected: 0, actual: (new AllowedSitesService(appConfig: $config))->addHosts(hosts: [], purpose: 'embed'));
	}//end testAddingNothingWritesNothing()

	/**
	 * A feature list also admits the hosts for its purpose.
	 *
	 * @return void
	 */
	public function testAFeatureListAlsoAdmitsTheHostsForItsPurpose(): void {
		$values = ['allowed_sites' => self::BOARD_LIST, 'livetile_allowed_hosts' => '["old.example.com"]'];
		$config = $this->configWith(values: $values);

		$fetch = new HostAllowList(appConfig: $config, configKey: 'livetile_allowed_hosts', purpose: 'fetch');
		$this->assertTrue(condition: $fetch->permits(url: 'https://old.example.com/x'));
		$this->assertTrue(condition: $fetch->permits(url: 'https://kcc-api.zuiddrecht.nl/wachttijd'));
		$this->assertFalse(condition: $fetch->permits(url: 'https://app.powerbi.com/x'));
		$this->assertSame(expected: ['old.example.com', 'www.zuiddrecht.nl', 'kcc-api.zuiddrecht.nl'], actual: $fetch->hosts());

		// Without a purpose the list reads only its own key, as before.
		$own = new HostAllowList(appConfig: $config, configKey: 'livetile_allowed_hosts');
		$this->assertFalse(condition: $own->permits(url: 'https://kcc-api.zuiddrecht.nl/'));
		$this->assertSame(expected: ['old.example.com'], actual: $own->hosts());
	}//end testAFeatureListAlsoAdmitsTheHostsForItsPurpose()

	/**
	 * An empty feature list still admits the hosts for its purpose only.
	 *
	 * @return void
	 */
	public function testAnEmptyFeatureListStillAdmitsTheHostsForItsPurposeOnly(): void {
		$values = ['allowed_sites' => self::BOARD_LIST];
		$fetch  = new HostAllowList(appConfig: $this->configWith(values: $values), configKey: 'healthping_allowed_hosts', purpose: 'fetch');

		$this->assertTrue(condition: $fetch->permits(url: 'https://kcc-api.zuiddrecht.nl/'));
		$this->assertFalse(condition: $fetch->permits(url: 'https://app.powerbi.com/'));
		$this->assertFalse(condition: $fetch->permits(url: 'https://evil.example.net/'));
	}//end testAnEmptyFeatureListStillAdmitsTheHostsForItsPurposeOnly()

	/**
	 * Embed and image hosts include the embed sites.
	 *
	 * @return void
	 */
	public function testEmbedAndImageHostsIncludeTheEmbedSites(): void {
		$values = [
			'allowed_sites'        => self::BOARD_LIST,
			'iframe_allowed_hosts' => '["status.example.com"]',
			'image_allowed_hosts'  => '["images.example.com"]',
		];
		$config = $this->configWith(values: $values);

		$iframe = new IframeService(
			appConfig: $config,
			clientService: $this->createMock(originalClassName: IClientService::class),
			logger: $this->createMock(originalClassName: LoggerInterface::class)
		);
		$this->assertSame(expected: ['status.example.com', 'www.zuiddrecht.nl', 'app.powerbi.com'], actual: $iframe->getAllowedHosts());
		$this->assertTrue(condition: $iframe->isHostAllowed(url: 'https://app.powerbi.com/reportEmbed'));
		$this->assertFalse(condition: $iframe->isHostAllowed(url: 'https://kcc-api.zuiddrecht.nl/'));

		$images = new ImageHostService(appConfig: $config);
		$this->assertSame(expected: ['images.example.com'], actual: $images->getAllowedHosts());
		$this->assertSame(expected: ['images.example.com', 'www.zuiddrecht.nl', 'app.powerbi.com'], actual: $images->getPermittedHosts());
	}//end testEmbedAndImageHostsIncludeTheEmbedSites()
}//end class
