<?php

/**
 * TenantThemeValidator unit tests (REQ-EMB-005, REQ-EMB-010).
 *
 * @category  Test
 * @package   Unit\Service\Embed
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
 */

declare(strict_types=1);

namespace Unit\Service\Embed;

use OCA\LaunchPad\Service\Embed\TenantThemeValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * :root-only CSS, contrast and the rendered variables.
 */
#[CoversClass(TenantThemeValidator::class)]
class TenantThemeValidatorTest extends TestCase {

	/**
	 * Only :root rules pass.
	 *
	 * @return void
	 */
	public function testCustomCssIsRestrictedToRoot(): void {
		$themes = new TenantThemeValidator();

		self::assertNull($themes->validateCss(':root { --spacing: 8px; }'));
		self::assertNull($themes->validateCss("/* brand */ :root { --a: 1px; }\n:root{--b:2px}"));
		self::assertNull($themes->validateCss('   '));

		$refused = $themes->validateCss(':root { --a: 1px; } .widget-title { display: none; }');
		self::assertSame('validation_error', $refused['error']);
		self::assertStringContainsString("Found selector '.widget-title' which targets elements outside :root.", $refused['message']);
		self::assertStringContainsString("Found selector 'body'", $themes->validateCss(':root, body { color: red; }')['message']);
		self::assertNotNull($themes->validateCss('@import url(https://evil.example/x.css);'));
		self::assertNotNull($themes->validateCss(':root { --a: 1px; }</style><script>'));
		self::assertNotNull($themes->validateCss(':root { --a: 1px; '), 'an unclosed rule');
		self::assertNotNull($themes->validateCss(':root { --a: 1px; } stray'), 'text outside a rule');
	}//end testCustomCssIsRestrictedToRoot()

	/**
	 * Contrast follows the WCAG luminance formula and the 4.5:1 floor.
	 *
	 * @return void
	 */
	public function testContrast(): void {
		$themes = new TenantThemeValidator();

		self::assertSame(21.0, $themes->contrastRatio('#000', '#FFFFFF'));
		self::assertSame(1.0, $themes->contrastRatio('#21468B', '#21468b'));
		self::assertSame(9.08, $themes->contrastRatio('#21468B', '#FFFFFF'));
		self::assertNull($themes->contrastRatio('blue', '#fff'));
		self::assertTrue($themes->validateContrastRatio('#1a1a1a', '#FFFFFF'));
		self::assertFalse($themes->validateContrastRatio('#999999', '#FFFFFF'), 'grey on white is 2.85:1');
		self::assertFalse($themes->validateContrastRatio('red', '#FFFFFF'), 'an unreadable colour fails');
	}//end testContrast()

	/**
	 * The :root block carries custom properties and nothing that breaks out.
	 *
	 * @return void
	 */
	public function testTheRootBlock(): void {
		$themes = new TenantThemeValidator();

		self::assertSame(
			":root {\n  --c-primary: #21468B;\n  --font-family: Inter, sans-serif;\n}",
			$themes->rootBlock([
				'--c-primary' => '#21468B',
				'--font-family' => 'Inter, sans-serif',
				'color' => 'red',
				'--evil' => 'red; } body { display:none',
				'--tag' => '</style>',
				'--list' => ['x'],
			])
		);
		self::assertSame('', $themes->rootBlock([]));
	}//end testTheRootBlock()

}//end class
