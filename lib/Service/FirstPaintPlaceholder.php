<?php

/**
 * FirstPaintPlaceholder
 *
 * The placeholder the first-paint shell draws in a frame, in nextcloud-vue's
 * own markup (CnWidgetPlaceholder: `cn-widget-placeholder` and its parts) and
 * with the library's own rules (css/widget-placeholder.css, copied to
 * lib/Service/widget-placeholder.css on prebuild and inlined), so the shell
 * and the mounted card draw the same pixels (widget-declared-placeholders
 * task 4).
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/widget-declared-placeholders/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * Markup and rules of the library's widget placeholder for the server shell.
 *
 * @spec openspec/changes/widget-declared-placeholders/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
 */
class FirstPaintPlaceholder {

	/**
	 * One row (32 px icon) plus the 12 px gap, as CnWidgetPlaceholder measures it.
	 */
	private const ROW_PITCH = 44;

	/**
	 * One text line (12 px) plus the 12 px gap.
	 */
	private const LINE_PITCH = 24;

	/**
	 * The gap between items.
	 */
	private const GAP = 12;

	/**
	 * Tiles and counters wrap and the placeholder clips what does not fit,
	 * so the shell prints this many without knowing the frame's width.
	 */
	private const WRAPPED_ITEMS = 24;

	/**
	 * Counters in a row at most (96 px each).
	 */
	private const COUNTERS = 6;

	/**
	 * The placeholder of one shape for a content area $height px high, as
	 * CnWidgetPlaceholder renders it; '' for a heading, which waits as its text.
	 *
	 * Tiles and counters wrap: with the content area's widest width known,
	 * only as many are printed as can show there (one square for a single app
	 * tile), not WRAPPED_ITEMS; each one is an element the page lays out while
	 * its scripts load (live 28 Nov: ~450 placeholder elements on LpVandaag,
	 * 168 of them squares, held the page back by about 100 ms).
	 *
	 * @param string   $shape  rows, lines, block, tiles, counter or heading.
	 * @param int      $height The content area's height in px.
	 * @param int|null $width  The content area's widest width in px, or null when unknown.
	 *
	 * @return string HTML; every part is a fixed class name, nothing from input.
	 *
	 * @spec openspec/changes/widget-declared-placeholders/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
	 */
	public static function markup(string $shape, int $height, ?int $width=null): string {
		$line  = '<span class="cn-widget-placeholder__line"></span>';
		$short = '<span class="cn-widget-placeholder__line cn-widget-placeholder__line--short"></span>';
		$items = match ($shape) {
			'rows'    => str_repeat(
				'<div class="cn-widget-placeholder__row"><span class="cn-widget-placeholder__icon"></span>'.$line.$short.'</div>',
				self::fit(space: $height, pitch: self::ROW_PITCH)
			),
			'lines'   => str_repeat($line, (self::fit(space: $height, pitch: self::LINE_PITCH) - 1)).$short,
			'block'   => '<span class="cn-widget-placeholder__block"></span>',
			'tiles'   => str_repeat('<span class="cn-widget-placeholder__tile"></span>', self::tilesThatFit(width: $width, height: $height)),
			'counter' => str_repeat(
				'<div class="cn-widget-placeholder__counter"><span class="cn-widget-placeholder__number"></span>'.$short.'</div>',
				self::countersThatFit(width: $width)
			),
			default   => null,
		};
		if ($items === null) {
			return '';
		}

		return '<div class="cn-widget-placeholder cn-widget-placeholder--'.$shape.'" aria-hidden="true" data-shape="'.$shape.'">'.$items.'</div>';
	}//end markup()

	/**
	 * The library's placeholder rules without comments and spare whitespace,
	 * for the shell's inline style block.
	 *
	 * @return string CSS.
	 *
	 * @spec openspec/changes/widget-declared-placeholders/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
	 */
	public static function css(): string {
		$css = (string) file_get_contents(__DIR__.'/widget-placeholder.css');
		$css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
		$css = (string) preg_replace('/\s+/', ' ', $css);
		$css = (string) preg_replace('/\s*([{}:;,>])\s*/', '$1', $css);

		return trim(str_replace(';}', '}', $css));
	}//end css()

	/**
	 * How many 64 px squares (12 px apart) show in the area: WRAPPED_ITEMS
	 * when its width is unknown, at least one.
	 *
	 * @param int|null $width  The area's width in px.
	 * @param int      $height The area's height in px.
	 *
	 * @return int
	 */
	private static function tilesThatFit(?int $width, int $height): int {
		if ($width === null) {
			return self::WRAPPED_ITEMS;
		}

		return min(self::WRAPPED_ITEMS, (self::fit(space: $width, pitch: 76) * self::fit(space: $height, pitch: 76)));
	}//end tilesThatFit()

	/**
	 * How many 96 px counters (12 px apart) show in a row: COUNTERS when the
	 * width is unknown, at least one.
	 *
	 * @param int|null $width The area's width in px.
	 *
	 * @return int
	 */
	private static function countersThatFit(?int $width): int {
		if ($width === null) {
			return self::COUNTERS;
		}

		return min(self::COUNTERS, self::fit(space: $width, pitch: 108));
	}//end countersThatFit()

	/**
	 * How many items of $pitch px (gap included) fit $space px: at least one.
	 *
	 * @param int $space The height in px.
	 * @param int $pitch One item plus its gap, in px.
	 *
	 * @return int
	 */
	private static function fit(int $space, int $pitch): int {
		return max(1, intdiv(($space + self::GAP), $pitch));
	}//end fit()
}//end class
