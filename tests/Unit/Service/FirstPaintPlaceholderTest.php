<?php

/**
 * FirstPaintPlaceholderTest
 *
 * The first-paint shell draws nextcloud-vue's placeholder markup and inlines
 * the library's rules inside the 6 KB style budget (widget-declared-placeholders task 4).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/widget-declared-placeholders/tasks.md#task-4
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\FirstPaintPlaceholder;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FirstPaintPlaceholder.
 *
 * @covers \OCA\LaunchPad\Service\FirstPaintPlaceholder
 */
class FirstPaintPlaceholderTest extends TestCase {

	/**
	 * Every shape in CnWidgetPlaceholder's markup, hidden from assistive technology.
	 *
	 * @return void
	 */
	public function testEveryShapeUsesTheLibraryMarkup(): void {
		foreach (['rows', 'lines', 'block', 'tiles', 'counter'] as $shape) {
			$html = FirstPaintPlaceholder::markup(shape: $shape, height: 200);
			$this->assertStringStartsWith(
				prefix: '<div class="cn-widget-placeholder cn-widget-placeholder--'.$shape.'" aria-hidden="true" data-shape="'.$shape.'">',
				string: $html
			);
			$this->assertStringEndsWith(suffix: '</div>', string: $html);
		}
	}//end testEveryShapeUsesTheLibraryMarkup()

	/**
	 * Rows and lines fill the content height as the library measures it; the last line is short.
	 *
	 * @return void
	 */
	public function testRowsAndLinesFillTheHeight(): void {
		// (200 + 12) / 44 = 4 rows; (200 + 12) / 24 = 8 lines.
		$this->assertSame(expected: 4, actual: substr_count(FirstPaintPlaceholder::markup(shape: 'rows', height: 200), 'cn-widget-placeholder__row'));
		$lines = FirstPaintPlaceholder::markup(shape: 'lines', height: 200);
		$this->assertSame(expected: 8, actual: substr_count($lines, '<span class="cn-widget-placeholder__line'));
		$this->assertSame(expected: 1, actual: substr_count($lines, 'cn-widget-placeholder__line--short'));
		$this->assertStringEndsWith(suffix: 'cn-widget-placeholder__line--short"></span></div>', string: $lines);
	}//end testRowsAndLinesFillTheHeight()

	/**
	 * A frame too low for one item still shows one.
	 *
	 * @return void
	 */
	public function testALowFrameShowsOneItem(): void {
		$this->assertSame(expected: 1, actual: substr_count(FirstPaintPlaceholder::markup(shape: 'rows', height: 0), 'cn-widget-placeholder__row'));
		$this->assertSame(expected: 1, actual: substr_count(FirstPaintPlaceholder::markup(shape: 'lines', height: -40), '<span'));
	}//end testALowFrameShowsOneItem()

	/**
	 * A block is one block; tiles and counters wrap and are clipped.
	 *
	 * @return void
	 */
	public function testBlockTilesAndCounters(): void {
		$this->assertSame(expected: 1, actual: substr_count(FirstPaintPlaceholder::markup(shape: 'block', height: 500), 'cn-widget-placeholder__block'));
		$tiles    = FirstPaintPlaceholder::markup(shape: 'tiles', height: 100);
		$counters = FirstPaintPlaceholder::markup(shape: 'counter', height: 100);
		$this->assertGreaterThan(expected: 1, actual: substr_count($tiles, 'cn-widget-placeholder__tile'));
		$this->assertGreaterThan(expected: 1, actual: substr_count($counters, 'cn-widget-placeholder__number'));
	}//end testBlockTilesAndCounters()

	/**
	 * A heading waits as its own text, and an unknown shape draws nothing.
	 *
	 * @return void
	 */
	public function testHeadingAndUnknownDrawNothing(): void {
		$this->assertSame(expected: '', actual: FirstPaintPlaceholder::markup(shape: 'heading', height: 100));
		$this->assertSame(expected: '', actual: FirstPaintPlaceholder::markup(shape: '<script>', height: 100));
	}//end testHeadingAndUnknownDrawNothing()

	/**
	 * The library's rules, without comments, with reduced motion kept.
	 *
	 * @return void
	 */
	public function testTheLibraryRulesAreInlinedCompact(): void {
		$css = FirstPaintPlaceholder::css();
		$this->assertStringContainsString(needle: '.cn-widget-placeholder{display:flex;', haystack: $css);
		$this->assertStringContainsString(needle: '@media (prefers-reduced-motion:reduce){.cn-widget-placeholder *{animation:none}}', haystack: $css);
		$this->assertStringNotContainsString(needle: '/*', haystack: $css);
		$this->assertStringNotContainsString(needle: "\n", haystack: $css);
	}//end testTheLibraryRulesAreInlinedCompact()

	/**
	 * The shell's whole inline style block, with the library rules in it, stays under 6 KB.
	 *
	 * @return void
	 */
	public function testTheStyleBlockStaysInsideTheBudget(): void {
		$template = (string) file_get_contents(__DIR__.'/../../../templates/index.php');
		$this->assertSame(expected: 1, actual: preg_match('#<style>(.*?)</style>#s', $template, $match));
		$block = str_replace('<?php print_unescaped(FirstPaintPlaceholder::css()); ?>', FirstPaintPlaceholder::css(), $match[1]);
		$this->assertStringNotContainsString(needle: '<?php', haystack: $block);
		$this->assertLessThan(expected: 6144, actual: strlen($block));
	}//end testTheStyleBlockStaysInsideTheBudget()
}//end class
