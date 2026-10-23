<?php

/**
 * SearchShortcutServiceTest
 *
 * Search shortcuts (search-ai-prefix-shortcuts, REQ-SPX-001): the prefix
 * pattern, case-insensitive uniqueness, the https `{query}` template check
 * reused from the quick-search fallback, and the limit of 30.
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
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\SearchShortcutService;
use PHPUnit\Framework\TestCase;

class SearchShortcutServiceTest extends TestCase {
	/**
	 * Stored admin settings.
	 *
	 * @var array<string, mixed>
	 */
	private array $settings = [];

	private SearchShortcutService $service;

	protected function setUp(): void {
		parent::setUp();

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

		// The real template check, the one the quick-search fallback uses.
		$adminSettings = new AdminSettingsService(settingMapper: $mapper);

		$this->service = new SearchShortcutService(settingMapper: $mapper, adminSettings: $adminSettings);
	}//end setUp()

	public function testAValidListIsStoredWithLowerCasePrefixes(): void {
		$saved = $this->service->saveShortcuts(
			raw: [
				['prefix' => '!T', 'name' => ' TOPdesk ', 'urlTemplate' => 'https://topdesk.example.nl/search?q={query}'],
				['prefix' => '!wiki', 'name' => 'Wikipedia', 'urlTemplate' => 'https://nl.wikipedia.org/w/index.php?search={query}'],
			]
		);

		$this->assertSame('!t', $saved[0]['prefix']);
		$this->assertSame('TOPdesk', $saved[0]['name']);
		$this->assertSame($saved, $this->service->getShortcuts());
		$this->assertSame($saved, $this->settings['search_shortcuts']);
	}//end testAValidListIsStoredWithLowerCasePrefixes()

	/**
	 * @return array<string, array{0: array<string, string>}>
	 */
	public static function invalidEntries(): array {
		$template = 'https://example.nl/?q={query}';
		return [
			'no bang' => [['prefix' => 't', 'name' => 'T', 'urlTemplate' => $template]],
			'too long' => [['prefix' => '!abcdefghijk', 'name' => 'T', 'urlTemplate' => $template]],
			'space in prefix' => [['prefix' => '!a b', 'name' => 'T', 'urlTemplate' => $template]],
			'no name' => [['prefix' => '!t', 'name' => ' ', 'urlTemplate' => $template]],
			'http' => [['prefix' => '!t', 'name' => 'T', 'urlTemplate' => 'http://example.nl/?q={query}']],
			'no placeholder' => [['prefix' => '!t', 'name' => 'T', 'urlTemplate' => 'https://example.nl/']],
			'javascript' => [['prefix' => '!t', 'name' => 'T', 'urlTemplate' => 'javascript:alert({query})']],
		];
	}//end invalidEntries()

	/**
	 * @dataProvider invalidEntries
	 */
	public function testAnInvalidEntryIsRefused(array $entry): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service->saveShortcuts(raw: [$entry]);
	}//end testAnInvalidEntryIsRefused()

	public function testPrefixesAreUniqueRegardlessOfCase(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service->saveShortcuts(
			raw: [
				['prefix' => '!t', 'name' => 'A', 'urlTemplate' => 'https://a.nl/?q={query}'],
				['prefix' => '!T', 'name' => 'B', 'urlTemplate' => 'https://b.nl/?q={query}'],
			]
		);
	}//end testPrefixesAreUniqueRegardlessOfCase()

	public function testAtMostThirtyShortcuts(): void {
		$raw = [];
		for ($i = 0; $i < 31; $i++) {
			$raw[] = ['prefix' => '!s' . $i, 'name' => 'S' . $i, 'urlTemplate' => 'https://s.nl/?q={query}'];
		}

		$this->expectException(InvalidArgumentException::class);
		$this->service->saveShortcuts(raw: $raw);
	}//end testAtMostThirtyShortcuts()

	public function testAStoredListThatNoLongerValidatesReadsAsEmpty(): void {
		$this->settings['search_shortcuts'] = [['prefix' => '!t', 'name' => 'T', 'urlTemplate' => 'http://insecure.nl/?q={query}']];

		$this->assertSame([], $this->service->getShortcuts());
	}//end testAStoredListThatNoLongerValidatesReadsAsEmpty()
}//end class
