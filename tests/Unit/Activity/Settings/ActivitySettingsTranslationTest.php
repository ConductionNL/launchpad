<?php

/**
 * ActivitySettingsTranslationTest
 *
 * Pins what ActivitySettingsTest only bounds: the exact English source
 * string each activity setting sends through the translator, so the
 * translated label reaches the user, and the order the settings are listed
 * in on the personal activity page.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Activity\Settings
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Activity\Settings;

use OCA\LaunchPad\Activity\Extension;
use OCA\LaunchPad\Activity\Settings\DashboardAcknowledgedSetting;
use OCA\LaunchPad\Activity\Settings\DashboardPublishedSetting;
use OCA\LaunchPad\Activity\Settings\DashboardSharedSetting;
use OCA\LaunchPad\Activity\Settings\DashboardUpdatedSetting;
use OCA\LaunchPad\Activity\Settings\LaunchPadActivitySetting;
use OCP\IL10N;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Translation and ordering of the LaunchPad activity settings.
 *
 * @covers \OCA\LaunchPad\Activity\Settings\LaunchPadActivitySetting
 * @covers \OCA\LaunchPad\Activity\Settings\DashboardSharedSetting
 * @covers \OCA\LaunchPad\Activity\Settings\DashboardPublishedSetting
 * @covers \OCA\LaunchPad\Activity\Settings\DashboardUpdatedSetting
 * @covers \OCA\LaunchPad\Activity\Settings\DashboardAcknowledgedSetting
 */
class ActivitySettingsTranslationTest extends TestCase {

	/**
	 * Each setting with the English source of its label.
	 *
	 * @return array<string, array{0: class-string<LaunchPadActivitySetting>, 1: string}>
	 */
	public static function labels(): array {
		return [
			'shared'       => [DashboardSharedSetting::class, 'A dashboard is shared with you'],
			'published'    => [DashboardPublishedSetting::class, 'A dashboard for your group is published'],
			'updated'      => [DashboardUpdatedSetting::class, 'A dashboard shared with you is updated'],
			'acknowledged' => [DashboardAcknowledgedSetting::class, 'You acknowledge an announcement on a dashboard'],
		];
	}//end labels()

	/**
	 * A translator that marks what it translated.
	 *
	 * @return IL10N
	 */
	private function marking(): IL10N {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text): string => '[nl] ' . $text);
		return $l10n;
	}//end marking()

	/**
	 * The label and the group name are the translator's output for the exact source strings.
	 *
	 * @param class-string<LaunchPadActivitySetting> $class  The setting.
	 * @param string                                 $source The English label.
	 *
	 * @return void
	 */
	#[DataProvider('labels')]
	public function testLabelsAreTranslatedFromTheirSource(string $class, string $source): void {
		$setting = new $class($this->marking());

		$this->assertSame('[nl] ' . $source, $setting->getName());
		$this->assertSame('[nl] LaunchPad', $setting->getGroupName());
	}//end testLabelsAreTranslatedFromTheirSource()

	/**
	 * Listed in event order: shared, published, updated, acknowledged; each
	 * with its own priority and identifier.
	 *
	 * @return void
	 */
	public function testSettingsAreListedInEventOrder(): void {
		$byPriority = [];
		foreach (self::labels() as [$class]) {
			$setting = new $class($this->marking());
			$byPriority[$setting->getPriority()] = $setting->getIdentifier();
		}

		ksort($byPriority);

		$this->assertSame(
			[
				50 => Extension::EVENT_SHARED,
				51 => Extension::EVENT_PUBLISHED,
				52 => Extension::EVENT_UPDATED,
				53 => Extension::EVENT_ACKNOWLEDGED,
			],
			$byPriority
		);
	}//end testSettingsAreListedInEventOrder()
}//end class
