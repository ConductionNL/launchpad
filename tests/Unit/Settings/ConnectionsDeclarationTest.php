<?php

/**
 * The connection declaration integriq reads.
 *
 * `lib/Settings/connections.json` is static JSON that integriq turns into the
 * rows of LaunchPad's Integrations page. Nothing in LaunchPad reads it at
 * runtime, so a broken file fails nowhere in this repo: integriq skips it whole
 * and the page goes empty on some other instance. Every assertion here is a way
 * that file could go wrong without a sound.
 *
 * @category Tests
 * @package  Unit\Settings
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/adopt-connection-registry/specs/app-connections/spec.md#requirement-req-lp-conn-001-launchpad-declares-its-outside-connections-in-one-static-file
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Settings;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guards lib/Settings/connections.json against hydra connection-registry D2 and D12.
 *
 * @coversNothing
 */
class ConnectionsDeclarationTest extends TestCase {

	/**
	 * Integriq's schema, fetched with `gh api` from integriq `development` on
	 * 2026-09-14, where the file was last changed in
	 * a93665880f7f552d8280b84a1f5ce402507c4466. It carries the hydra#673 and
	 * hydra#676 amendments (`reportedOnly`, `adapter.jsonPath`,
	 * `adapter.simulatedValues`, `{configKey, jsonPath}` in `requiredConfig`).
	 *
	 * @var string
	 */
	private const SCHEMA = '/tests/Fixtures/Integriq/connections.schema.json';

	/**
	 * The keys the file declares, in declared order.
	 *
	 * @var array<int, string>
	 */
	private const DECLARED_KEYS = ['dashboard-registry', 'weather', 'news-feeds', 'ics-calendars', 'live-tiles', 'health-ping'];

	/**
	 * The rows only LaunchPad can judge: a widget or tile names the address.
	 *
	 * @var array<int, string>
	 */
	private const REPORTED_ONLY_KEYS = ['weather', 'news-feeds', 'ics-calendars', 'live-tiles', 'health-ping'];

	/**
	 * The repository root.
	 *
	 * @return string
	 */
	private function root(): string {
		return dirname(__DIR__, 3);
	}//end root()

	/**
	 * The raw declaration file.
	 *
	 * @return string
	 */
	private function raw(): string {
		$raw = file_get_contents($this->root() . '/lib/Settings/connections.json');
		$this->assertIsString(actual: $raw, message: 'lib/Settings/connections.json must exist');

		return $raw;
	}//end raw()

	/**
	 * The decoded declaration.
	 *
	 * @return array<string, mixed>
	 */
	private function declaration(): array {
		$decoded = json_decode($this->raw(), true, 512, JSON_THROW_ON_ERROR);
		$this->assertIsArray(actual: $decoded);

		return $decoded;
	}//end declaration()

	/**
	 * The declared connections, keyed by connection key.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function connectionsByKey(): array {
		$byKey = [];
		foreach ($this->declaration()['connections'] as $connection) {
			$byKey[(string) $connection['key']] = $connection;
		}

		return $byKey;
	}//end connectionsByKey()

	/**
	 * The vendored schema.
	 *
	 * @return string
	 */
	private function schema(): string {
		$schema = file_get_contents($this->root() . self::SCHEMA);
		$this->assertIsString(actual: $schema);

		return $schema;
	}//end schema()

	/**
	 * The file validates against integriq's JSON Schema.
	 *
	 * @return void
	 */
	public function testTheFileValidatesAgainstIntegriqsSchema(): void {
		$result = (new Validator())->validate(json_decode($this->raw()), $this->schema());

		$errors = [];
		if ($result->hasError() === true) {
			$errors = (new ErrorFormatter())->format($result->error());
		}

		$this->assertTrue(condition: $result->isValid(), message: (string) json_encode($errors, JSON_PRETTY_PRINT));
	}//end testTheFileValidatesAgainstIntegriqsSchema()

	/**
	 * The schema check can fail: a misspelled field is refused.
	 *
	 * Without this control a validator that accepts everything would pass the
	 * test above as well.
	 *
	 * @return void
	 */
	public function testTheSchemaRefusesAnUnknownField(): void {
		$declaration = json_decode($this->raw());
		$declaration->connections[0]->setingsUrl = '/settings/admin/launchpad#section-dashboard-registry';

		$this->assertFalse(condition: (new Validator())->validate($declaration, $this->schema())->isValid());
	}//end testTheSchemaRefusesAnUnknownField()

	/**
	 * The file names the app it ships in.
	 *
	 * Integriq refuses a file whose `app` differs from the app it was read from.
	 *
	 * @return void
	 */
	public function testTheFileNamesThisApp(): void {
		// Deliberately file_get_contents() + simplexml_load_string() rather than
		// simplexml_load_file(). Under the Nextcloud bootstrap lib/base.php calls
		// libxml_set_external_entity_loader() with a loader returning null, and
		// that resolver also handles the primary document, so load_file() returns
		// false for a well-formed info.xml. Parsing a string never touches it.
		$infoXml = simplexml_load_string(
			(string)file_get_contents($this->root() . '/appinfo/info.xml')
		);

		$this->assertNotFalse(condition: $infoXml);
		$this->assertSame(expected: (string) $infoXml->id, actual: $this->declaration()['app']);
		$this->assertSame(expected: 'launchpad', actual: $this->declaration()['app']);
	}//end testTheFileNamesThisApp()

	/**
	 * The keys are unique and in rising order.
	 *
	 * A row is keyed by app and key, so a second entry with the same key would
	 * overwrite the first.
	 *
	 * @return void
	 */
	public function testTheKeysAreUniqueAndOrdered(): void {
		$connections = $this->declaration()['connections'];
		$keys        = array_column($connections, 'key');

		$this->assertSame(expected: array_values(array_unique($keys)), actual: $keys, message: 'a key is declared twice');
		$this->assertSame(expected: self::DECLARED_KEYS, actual: $keys);

		$orders = array_column($connections, 'order');
		$sorted = $orders;
		sort($sorted);
		$this->assertSame(expected: $sorted, actual: $orders);
		$this->assertCount(expectedCount: count($keys), haystack: array_unique($orders));
	}//end testTheKeysAreUniqueAndOrdered()

	/**
	 * No text a reader sees carries an em-dash, and no title is Title Case (voice rule 8).
	 *
	 * @return void
	 */
	public function testNoTextBreaksTheVoiceRules(): void {
		$this->assertStringNotContainsString(needle: "\u{2014}", haystack: $this->raw());
		$this->assertStringNotContainsString(needle: '--', haystack: $this->raw());

		foreach ($this->declaration()['connections'] as $connection) {
			$words = explode(' ', (string) $connection['title']);
			foreach (array_slice($words, 1) as $word) {
				$this->assertSame(expected: mb_strtolower($word), actual: $word, message: $connection['key'] . ' title is not sentence case');
			}
		}
	}//end testNoTextBreaksTheVoiceRules()

	/**
	 * Every settings link lands on an element id that exists under src/ or templates/.
	 *
	 * A link into a section that does not exist scrolls nowhere and logs
	 * nothing. A copy of the link itself does not count as the element, and
	 * neither does an id that only starts with the anchor. The admin section is
	 * `launchpad` (LaunchPadAdmin::getSection()).
	 *
	 * @return void
	 */
	public function testEverySettingsLinkPointsAtAnExistingElement(): void {
		$sources = $this->sourcesUnder(dirs: ['src', 'templates']);
		$admin   = (string) file_get_contents($this->root() . '/lib/Settings/LaunchPadAdmin.php');
		$this->assertMatchesRegularExpression(pattern: "/function getSection\(\): string \{\s+return 'launchpad';/", string: $admin);

		$linked = [];
		foreach ($this->declaration()['connections'] as $connection) {
			if (array_key_exists('settingsUrl', $connection) === false) {
				continue;
			}

			$url = (string) $connection['settingsUrl'];
			$this->assertStringStartsWith(prefix: '/settings/admin/launchpad', string: $url, message: $connection['key']);
			$anchor = substr($url, ((int) strpos($url, '#') + 1));
			$this->assertMatchesRegularExpression(pattern: '/^section-[a-z0-9-]+$/', string: $anchor, message: $connection['key']);
			$this->assertMatchesRegularExpression(
				pattern: '/\bid="' . preg_quote($anchor, '/') . '"/',
				string: $sources,
				message: $connection['key'] . ' links to a missing element #' . $anchor
			);
			$linked[] = $connection['key'];
		}

		$this->assertSame(expected: ['dashboard-registry'], actual: $linked);
	}//end testEverySettingsLinkPointsAtAnExistingElement()

	/**
	 * The registry link opens the tab that renders its anchor.
	 *
	 * BeheerTabs renders only the active tab, so an anchor on the Sharing tab
	 * does not exist in the page while another tab is open.
	 *
	 * @return void
	 */
	public function testTheRegistryLinkOpensTheSharingTab(): void {
		$url      = (string) $this->connectionsByKey()['dashboard-registry']['settingsUrl'];
		$admin    = (string) file_get_contents($this->root() . '/src/components/admin/AdminSettings.vue');
		$sharing  = (string) file_get_contents($this->root() . '/src/components/admin/tabs/SharingTab.vue');
		$registry = (string) file_get_contents($this->root() . '/src/components/admin/DashboardRegistrySettings.vue');

		$this->assertSame(expected: '/settings/admin/launchpad?tab=sharing#section-dashboard-registry', actual: $url);
		$this->assertStringContainsString(needle: "slug: 'sharing'", haystack: $admin);
		$this->assertStringContainsString(needle: '<DashboardRegistrySettings />', haystack: $sharing);
		$this->assertStringContainsString(needle: 'id="section-dashboard-registry"', haystack: $registry);
		$this->assertStringContainsString(
			needle: "params.get('tab')",
			haystack: (string) file_get_contents($this->root() . '/src/components/admin/BeheerTabs.vue')
		);
	}//end testTheRegistryLinkOpensTheSharingTab()

	/**
	 * The registry requires the one key a search needs, and that key is saved by the registry form.
	 *
	 * `GenericStoreService` builds the search URL from `registry_url`. The token
	 * is optional, so requiring it would keep a public registry on Not configured.
	 *
	 * @return void
	 */
	public function testTheRegistryRequiresTheRegistryUrl(): void {
		$registry = $this->connectionsByKey()['dashboard-registry'];
		$service  = (string) file_get_contents($this->root() . '/lib/Service/StoreService.php');

		$this->assertSame(expected: ['registry_url'], actual: $registry['requiredConfig']);
		$this->assertStringContainsString(needle: "CONFIG_URL = 'registry_url'", haystack: $service);
		$this->assertArrayNotHasKey(key: 'reportedOnly', array: $registry);
		$this->assertArrayNotHasKey(key: 'adapter', array: $registry);
	}//end testTheRegistryRequiresTheRegistryUrl()

	/**
	 * The rows only LaunchPad can judge are reported only, and carry nothing for integriq to guess from.
	 *
	 * Their keys are set with occ, so they carry no settings link either.
	 *
	 * @return void
	 */
	public function testTheWidgetFamiliesAreReportedOnly(): void {
		$byKey = $this->connectionsByKey();

		foreach (self::REPORTED_ONLY_KEYS as $key) {
			$this->assertTrue(condition: $byKey[$key]['reportedOnly'], message: $key);
			$this->assertArrayNotHasKey(key: 'requiredConfig', array: $byKey[$key], message: $key);
			$this->assertArrayNotHasKey(key: 'adapter', array: $byKey[$key], message: $key);
			$this->assertArrayNotHasKey(key: 'settingsUrl', array: $byKey[$key], message: $key);
			$this->assertStringStartsWith(prefix: 'Not checked yet.', string: $byKey[$key]['unconfiguredMessage'], message: $key);
		}
	}//end testTheWidgetFamiliesAreReportedOnly()

	/**
	 * Every config key a message tells an admin to set is a key the code reads.
	 *
	 * A message naming a key nothing reads sends the admin to set the wrong thing.
	 *
	 * @return void
	 */
	public function testEveryNamedConfigKeyIsOneTheCodeReads(): void {
		$byKey = $this->connectionsByKey();
		$named = [
			'weather'     => ['weather_provider_url', '/lib/Service/WeatherService.php'],
			'live-tiles'  => ['livetile_allowed_hosts', '/lib/Service/LiveTileService.php'],
			'health-ping' => ['healthping_allowed_hosts', '/lib/Service/HealthPingService.php'],
		];

		foreach ($named as $key => [$configKey, $file]) {
			$this->assertStringContainsString(needle: $configKey, haystack: (string) $byKey[$key]['unconfiguredMessage'], message: $key);
			$this->assertStringContainsString(
				needle: "= '" . $configKey . "';",
				haystack: (string) file_get_contents($this->root() . $file),
				message: $key . ' names ' . $configKey . ', which ' . $file . ' does not read'
			);
		}
	}//end testEveryNamedConfigKeyIsOneTheCodeReads()

	/**
	 * The contents of every .vue, .js and .php file under the given directories.
	 *
	 * @param array<int, string> $dirs Directories relative to the repository root.
	 *
	 * @return string
	 */
	private function sourcesUnder(array $dirs): string {
		$contents = '';
		foreach ($dirs as $dir) {
			if (is_dir($this->root() . '/' . $dir) === false) {
				continue;
			}

			$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root() . '/' . $dir));
			foreach ($iterator as $file) {
				if ($file->isFile() === false || preg_match('/\.(vue|js|php)$/', $file->getFilename()) !== 1) {
					continue;
				}

				$contents .= (string) file_get_contents($file->getPathname()) . "\n";
			}
		}

		return $contents;
	}//end sourcesUnder()
}//end class
