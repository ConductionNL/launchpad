<?php

/**
 * LaunchPad activity settings test
 *
 * REQ-DIGE-001: four settings in one "LaunchPad" group, each changeable
 * for stream and mail; mail on for shared/published, off for updated and
 * acknowledged. Also pins that info.xml registers all four, because an
 * unregistered setting never reaches the activity app's digest.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Activity\Settings
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Activity\Settings;

use OCA\LaunchPad\Activity\Extension;
use OCA\LaunchPad\Activity\Settings\DashboardAcknowledgedSetting;
use OCA\LaunchPad\Activity\Settings\DashboardPublishedSetting;
use OCA\LaunchPad\Activity\Settings\DashboardSharedSetting;
use OCA\LaunchPad\Activity\Settings\DashboardUpdatedSetting;
use OCP\Activity\ActivitySettings;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ActivitySettingsTest extends TestCase {
	/**
	 * @return array<string, array{class-string<ActivitySettings>, string, bool}>
	 */
	public static function settings(): array {
		return [
			'shared' => [DashboardSharedSetting::class, Extension::EVENT_SHARED, true],
			'published' => [DashboardPublishedSetting::class, Extension::EVENT_PUBLISHED, true],
			'updated' => [DashboardUpdatedSetting::class, Extension::EVENT_UPDATED, false],
			'acknowledged' => [DashboardAcknowledgedSetting::class, Extension::EVENT_ACKNOWLEDGED, false],
		];
	}

	private function l10n(): IL10N {
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $text): string => $text);
		return $l;
	}

	/**
	 * @param class-string<ActivitySettings> $class
	 */
	#[DataProvider('settings')]
	public function testDefaultsAndGroup(string $class, string $type, bool $mail): void {
		$setting = new $class($this->l10n());

		$this->assertInstanceOf(ActivitySettings::class, $setting);
		$this->assertSame($type, $setting->getIdentifier());
		$this->assertSame('launchpad', $setting->getGroupIdentifier());
		$this->assertSame('LaunchPad', $setting->getGroupName());
		$this->assertNotSame('', $setting->getName());
		$this->assertTrue($setting->canChangeStream());
		$this->assertTrue($setting->isDefaultEnabledStream());
		$this->assertTrue($setting->canChangeMail());
		$this->assertSame($mail, $setting->isDefaultEnabledMail());
		$this->assertGreaterThanOrEqual(0, $setting->getPriority());
		$this->assertLessThanOrEqual(100, $setting->getPriority());
	}

	public function testInfoXmlRegistersEverySetting(): void {
		// Read the file ourselves: under the Nextcloud bootstrap (CI) the
		// external entity loader is disabled, so simplexml_load_file() on a
		// path returns false. Loading the string needs no entity loader.
		$xml = simplexml_load_string((string)file_get_contents(__DIR__ . '/../../../../appinfo/info.xml'));
		$this->assertNotFalse($xml);
		$registered = [];
		foreach ($xml->activity->settings->setting ?? [] as $node) {
			$registered[] = (string)$node;
		}

		$expected = array_map(static fn (array $row): string => $row[0], self::settings());
		sort($expected);
		sort($registered);
		$this->assertSame($expected, $registered);
		foreach ($registered as $class) {
			$this->assertTrue(class_exists($class), $class . ' is registered in info.xml but does not exist');
		}
	}

	public function testIdentifiersAreInTheProviderCatalogue(): void {
		foreach (self::settings() as [$class, $type]) {
			$this->assertContains($type, Extension::ALL_EVENTS);
		}
	}
}
