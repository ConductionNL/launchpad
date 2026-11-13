<?php

/**
 * LaunchPadAdminSectionTest
 *
 * Pins the admin settings section: its id matches the section the admin
 * form registers under, its name is translated and its icon is resolved
 * through the URL generator.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Settings
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Settings;

use OCA\LaunchPad\Settings\LaunchPadAdminSection;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Tests for LaunchPadAdminSection.
 *
 * @covers \OCA\LaunchPad\Settings\LaunchPadAdminSection
 */
class LaunchPadAdminSectionTest extends TestCase {

	/**
	 * The name is the translated app name.
	 *
	 * @return void
	 */
	public function testNameIsTranslated(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->expects($this->once())
			->method('t')
			->with('LaunchPad')
			->willReturn('Startpagina');

		$section = new LaunchPadAdminSection(l: $l10n, urlGenerator: $this->createMock(IURLGenerator::class));

		$this->assertSame('Startpagina', $section->getName());
	}//end testNameIsTranslated()

	/**
	 * The icon is the app's dark icon, resolved by the URL generator.
	 *
	 * @return void
	 */
	public function testIconIsResolvedThroughTheUrlGenerator(): void {
		$urls = $this->createMock(IURLGenerator::class);
		$urls->expects($this->once())
			->method('imagePath')
			->with('launchpad', 'app-dark.svg')
			->willReturn('/apps/launchpad/img/app-dark.svg');

		$section = new LaunchPadAdminSection(l: $this->createMock(IL10N::class), urlGenerator: $urls);

		$this->assertSame('/apps/launchpad/img/app-dark.svg', $section->getIcon());
	}//end testIconIsResolvedThroughTheUrlGenerator()

	/**
	 * The section id and priority are stable values the settings framework sorts by.
	 *
	 * @return void
	 */
	public function testIdAndPriority(): void {
		$section = new LaunchPadAdminSection(
			l: $this->createMock(IL10N::class),
			urlGenerator: $this->createMock(IURLGenerator::class)
		);

		$this->assertSame(['launchpad', 80], [$section->getID(), $section->getPriority()]);
	}//end testIdAndPriority()
}//end class
