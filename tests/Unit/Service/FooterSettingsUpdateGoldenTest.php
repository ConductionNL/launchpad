<?php

/**
 * Footer settings update golden test
 *
 * Pins what FooterService::updateGlobalSettings stores, and what it refuses,
 * for a set of patches, against a recorded fixture.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\FooterService;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * The stored settings, patch by patch.
 *
 * @covers \OCA\LaunchPad\Service\FooterService
 * @uses   \OCA\LaunchPad\Db\AdminSetting
 */
class FooterSettingsUpdateGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/footer-settings-update.json';

	/**
	 * Every patch stores the recorded settings or raises the recorded error.
	 *
	 * @return void
	 */
	public function testPatchesMatchTheRecordedFixture(): void {
		$link = ['label' => 'Privacy', 'url' => 'https://example.org/privacy'];
		$patches = [
			'empty' => [],
			'enable' => ['footerEnabled' => 1],
			'disable' => ['footerEnabled' => ''],
			'html' => ['footerHtml' => '<p onclick="x()">Hi <a href="https://example.org">there</a><script>bad()</script></p>'],
			'html null' => ['footerHtml' => null],
			'html variants' => ['footerHtml' => ['nl' => '<b>Hoi</b>', 'en' => '<i>Hi</i>']],
			'html bad variant key' => ['footerHtml' => [0 => '<b>x</b>']],
			'html bad variant value' => ['footerHtml' => ['nl' => 5]],
			'html number' => ['footerHtml' => 12],
			'html oversized' => ['footerHtml' => str_repeat('a', 9000)],
			'config null' => ['footerConfig' => null],
			'config' => ['footerConfig' => ['organisation' => 'ACME', 'links' => [$link], 'copyrightYear' => 2026, 'layoutMode' => 'columns']],
			'config string' => ['footerConfig' => 'nope'],
			'config unknown key' => ['footerConfig' => ['nope' => true]],
			'colours' => ['footerBackgroundColor' => '#112233', 'footerTextColor' => '#abc'],
			'colours null' => ['footerBackgroundColor' => null, 'footerTextColor' => null],
			'background bad' => ['footerBackgroundColor' => 'red'],
			'text bad' => ['footerTextColor' => '#12345'],
			'text not string' => ['footerTextColor' => 5],
			'all' => [
				'footerEnabled' => true,
				'footerHtml' => '<p>x</p>',
				'footerConfig' => [],
				'footerBackgroundColor' => '#000000',
				'footerTextColor' => '#ffffff',
			],
			'bad after good' => ['footerEnabled' => true, 'footerHtml' => '<p>x</p>', 'footerBackgroundColor' => 'bad', 'footerTextColor' => '#ffffff'],
		];

		$actual = [];
		foreach ($patches as $name => $patch) {
			$stored = [];
			$mapper = $this->getMockBuilder(className: AdminSettingMapper::class)->disableOriginalConstructor()->getMock();
			$mapper->method('setSetting')->willReturnCallback(static function (string $key, mixed $value) use (&$stored): AdminSetting {
				$stored[] = [$key, $value];
				return new AdminSetting();
			});
			try {
				(new FooterService(settingMapper: $mapper))->updateGlobalSettings(patch: $patch);
				$outcome = 'ok';
			} catch (Throwable $e) {
				$outcome = get_class($e) . ': ' . $e->getMessage() . ' (' . $e->getCode() . ')';
			}

			$actual[$name] = ['stored' => $stored, 'outcome' => $outcome];
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
			$this->markTestIncomplete(message: 'Fixture recorded.');
		}

		$this->assertSame(expected: json_decode((string)file_get_contents(self::FIXTURE), true), actual: $actual);
	}
}
