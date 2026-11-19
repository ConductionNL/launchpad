<?php

/**
 * DashboardLook Test
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
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-a-look-must-stay-readable-req-thm-002
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Exception\ContrastTooLowException;
use OCA\LaunchPad\Service\DashboardLook;
use PHPUnit\Framework\TestCase;

class DashboardLookTest extends TestCase {

	public function testContrastFollowsWcag(): void {
		$this->assertEqualsWithDelta(21.0, DashboardLook::contrast(first: '#000000', second: '#ffffff'), 0.001);
		$this->assertEqualsWithDelta(1.0, DashboardLook::contrast(first: '#777777', second: '#777777'), 0.001);
		// The WebAIM reference value for #777777 on white.
		$this->assertEqualsWithDelta(4.48, DashboardLook::contrast(first: '#777777', second: '#ffffff'), 0.01);
		$this->assertSame(DashboardLook::contrast(first: '#1f5c3a', second: '#ffffff'), DashboardLook::contrast(first: '#ffffff', second: '#1f5c3a'));
	}

	public function testTextIsBlackOrWhiteWhicheverReadsBetter(): void {
		$this->assertSame('#ffffff', DashboardLook::textColorFor(background: '#12301f'));
		$this->assertSame('#000000', DashboardLook::textColorFor(background: '#f5f5dc'));
		$this->assertSame('#000000', DashboardLook::textColorFor(background: '#ffffff'));
		foreach (['#12301f', '#f5f5dc', '#777777', '#ff0000', '#0000ff'] as $background) {
			$this->assertGreaterThanOrEqual(4.5, DashboardLook::contrast(first: DashboardLook::textColorFor(background: $background), second: $background), $background);
		}
	}

	public function testAnAccentNeedsThreeToOne(): void {
		DashboardLook::assertAccentReadable(accent: '#1f5c3a', background: null);
		DashboardLook::assertAccentReadable(accent: '#ffffff', background: '#12301f');

		$this->expectException(ContrastTooLowException::class);
		DashboardLook::assertAccentReadable(accent: '#cccccc', background: '#ffffff');
	}

	public function testColoursAreNormalised(): void {
		$this->assertSame('#1f5c3a', DashboardLook::normaliseHex(colour: ' #1F5C3A ', field: 'x'));
		$this->assertSame('#aabbcc', DashboardLook::normaliseHex(colour: '#abc', field: 'x'));
		$this->expectExceptionMessage('The accent must be a colour like #1f5c3a.');
		DashboardLook::normaliseHex(colour: 'rgb(0,0,0)', field: 'The accent');
	}

	public function testOnlyResourceFileNamesAreImages(): void {
		$this->assertTrue(DashboardLook::isResourceName(name: 'resource_6735a1b2c3d4e5.12345678.png'));
		$this->assertTrue(DashboardLook::isResourceName(name: 'resource_x.svg'));
		foreach (['', '../x.png', 'a/b.png', '.hidden.png', 'x.png.php', 'x..png', 'https://e.example/x.png'] as $bad) {
			$this->assertFalse(DashboardLook::isResourceName(name: $bad), $bad);
		}
	}
}
