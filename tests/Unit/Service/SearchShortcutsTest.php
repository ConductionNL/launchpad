<?php

/**
 * Unit tests for the quick-search prefix shortcut list.
 *
 * @category Test
 * @package  OCA\LaunchPad\Tests\Unit\Service
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
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
use OCA\LaunchPad\Service\SearchShortcuts;
use PHPUnit\Framework\TestCase;

/**
 * Prefix format, uniqueness, the https template rule and the 30-entry limit (REQ-SPX-001).
 *
 * @covers \OCA\LaunchPad\Service\SearchShortcuts
 * @covers \OCA\LaunchPad\Service\AdminSettingsService
 */
class SearchShortcutsTest extends TestCase {

	/**
	 * The TOPdesk shortcut of the spec.
	 *
	 * @var array<string, string>
	 */
	private const TOPDESK = ['prefix' => '!t', 'name' => 'TOPdesk', 'urlTemplate' => 'https://topdesk.gemeente.nl/tas/secure/search?q={query}'];

	/**
	 * The message the save fails with for a value.
	 *
	 * @param mixed $value The list.
	 *
	 * @return string The message, or '' when it is accepted.
	 */
	private function refusal(mixed $value): string {
		try {
			(new SearchShortcuts())->normalise(value: $value);
		} catch (InvalidArgumentException $exception) {
			return $exception->getMessage();
		}

		return '';
	}//end refusal()

	/**
	 * The TOPdesk shortcut is accepted and trimmed.
	 *
	 * @return void
	 */
	public function testAValidListIsKeptTrimmed(): void {
		$list = (new SearchShortcuts())->normalise(value: [
			['prefix' => ' !t ', 'name' => ' TOPdesk ', 'urlTemplate' => self::TOPDESK['urlTemplate'] . ' ', 'extra' => 'dropped'],
			['prefix' => '!Z9', 'name' => 'Zaaksysteem', 'urlTemplate' => 'https://zaken.example.nl/?q={query}'],
		]);

		$this->assertSame([self::TOPDESK, ['prefix' => '!Z9', 'name' => 'Zaaksysteem', 'urlTemplate' => 'https://zaken.example.nl/?q={query}']], $list);
	}//end testAValidListIsKeptTrimmed()

	/**
	 * Plain http is refused with the spec's message.
	 *
	 * @return void
	 */
	public function testPlainHttpIsRefused(): void {
		$this->assertStringContainsString(
			'Use an https address that contains {query}',
			$this->refusal([['prefix' => '!i', 'name' => 'Intranet', 'urlTemplate' => 'http://intranet.gemeente.nl/zoek?q={query}']])
		);
		$this->assertStringContainsString(
			'Use an https address that contains {query}',
			$this->refusal([['prefix' => '!i', 'name' => 'Intranet', 'urlTemplate' => 'https://intranet.gemeente.nl/zoek']])
		);
	}//end testPlainHttpIsRefused()

	/**
	 * A prefix is ! and 1 to 10 letters or digits.
	 *
	 * @return void
	 */
	public function testBadPrefixesAreRefused(): void {
		foreach (['t', '!', '!abcdefghijk', '!t-x', '!t x', '?t', ''] as $prefix) {
			$this->assertStringContainsString(
				'prefix is ! followed by 1 to 10 letters or digits',
				$this->refusal([['prefix' => $prefix] + self::TOPDESK]),
				'prefix ' . $prefix
			);
		}

		$this->assertSame('', $this->refusal([['prefix' => '!abcdefghij'] + self::TOPDESK]));
	}//end testBadPrefixesAreRefused()

	/**
	 * A prefix is unique regardless of case.
	 *
	 * @return void
	 */
	public function testAPrefixIsUniqueRegardlessOfCase(): void {
		$this->assertSame(
			'The search shortcut prefix !T is used twice.',
			$this->refusal([self::TOPDESK, ['prefix' => '!T'] + self::TOPDESK])
		);
	}//end testAPrefixIsUniqueRegardlessOfCase()

	/**
	 * Thirty shortcuts are allowed, thirty-one are not.
	 *
	 * @return void
	 */
	public function testAtMostThirtyShortcuts(): void {
		$list = [];
		for ($i = 1; $i <= 30; $i++) {
			$list[] = ['prefix' => '!s' . $i] + self::TOPDESK;
		}

		$this->assertSame('', $this->refusal($list));
		$list[] = ['prefix' => '!s31'] + self::TOPDESK;
		$this->assertSame('At most 30 search shortcuts are allowed.', $this->refusal($list));
	}//end testAtMostThirtyShortcuts()

	/**
	 * Not a list, an entry that is not an object, or an empty name is refused.
	 *
	 * @return void
	 */
	public function testMalformedInputIsRefused(): void {
		$this->assertSame('Search shortcuts must be a list.', $this->refusal('!t'));
		$this->assertSame('Search shortcuts must be a list.', $this->refusal(['a' => self::TOPDESK]));
		$this->assertStringContainsString('must be an object', $this->refusal(['!t']));
		$this->assertStringContainsString('needs a name', $this->refusal([['name' => ' '] + self::TOPDESK]));
	}//end testMalformedInputIsRefused()

	/**
	 * The admin save validates and stores the normalised list; an invalid one is refused and not stored.
	 *
	 * @return void
	 */
	public function testTheAdminSaveStoresOnlyAValidList(): void {
		$mapper = $this->createMock(AdminSettingMapper::class);
		$mapper->expects($this->once())->method('setSetting')
			->with(AdminSetting::KEY_SEARCH_SHORTCUTS, [self::TOPDESK]);
		$service = new AdminSettingsService(settingMapper: $mapper);

		$service->updateSettings(settings: ['searchShortcuts' => [[' prefix' => 'x'] + self::TOPDESK]]);

		$this->expectException(InvalidArgumentException::class);
		$service->updateSettings(settings: ['searchShortcuts' => [['urlTemplate' => 'http://x/{query}'] + self::TOPDESK]]);
	}//end testTheAdminSaveStoresOnlyAValidList()

	/**
	 * Reading gives the stored list, or none when the stored value no longer validates.
	 *
	 * @return void
	 */
	public function testReadingFallsBackToNoShortcuts(): void {
		$mapper = $this->createMock(AdminSettingMapper::class);
		$mapper->method('getValue')->with(AdminSetting::KEY_SEARCH_SHORTCUTS)->willReturnOnConsecutiveCalls(
			[self::TOPDESK],
			[['prefix' => '!t', 'name' => 'Old', 'urlTemplate' => 'http://old/{query}']],
			null
		);
		$service = new AdminSettingsService(settingMapper: $mapper);

		$this->assertSame([self::TOPDESK], $service->getSearchShortcuts());
		$this->assertSame([], $service->getSearchShortcuts());
		$this->assertSame([], $service->getSearchShortcuts());
	}//end testReadingFallsBackToNoShortcuts()

	/**
	 * The fallback template keeps the same rule it had.
	 *
	 * @return void
	 */
	public function testTheFallbackTemplateRuleIsUnchanged(): void {
		$service = new AdminSettingsService(settingMapper: $this->createMock(AdminSettingMapper::class));

		$this->assertTrue($service->isValidQuicksearchFallbackTarget(value: 'https://example.org/search?q={query}'));
		$this->assertFalse($service->isValidQuicksearchFallbackTarget(value: 'http://example.org/search?q={query}'));
		$this->assertFalse($service->isValidQuicksearchFallbackTarget(value: 'https://example.org/search'));
		$this->assertTrue($service->isValidQuicksearchFallbackTarget(value: 'unified-search'));
	}//end testTheFallbackTemplateRuleIsUnchanged()
}//end class
