<?php

/**
 * DashboardLook
 *
 * Validates the look of a dashboard and derives the colours that keep it readable.
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
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-a-look-must-stay-readable-req-thm-002
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Exception\ContrastTooLowException;

/**
 * Colours are `#rrggbb`. Contrast is the WCAG 2.2 ratio between the relative
 * luminances of two colours. Text on a dashboard background is black or
 * white, whichever contrasts more, so body text always reaches 4.5:1. An
 * accent (buttons, borders, focus rings) must reach 3:1 against the
 * background, WCAG 2.2 SC 1.4.11; the accent's own text colour is derived
 * the same way, so it reaches 4.5:1 by construction.
 *
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-a-look-must-stay-readable-req-thm-002
 */
final class DashboardLook {

	/**
	 * The least contrast an accent needs against the background (SC 1.4.11).
	 */
	public const MIN_ACCENT_CONTRAST = 3.0;

	/**
	 * The background a dashboard without its own background colour is drawn on.
	 *
	 * Nextcloud's default main background; a theme that darkens it is not known
	 * on the server, so an accent is checked against white when the dashboard
	 * sets no background of its own.
	 */
	public const INSTANCE_BACKGROUND = '#ffffff';

	/**
	 * A colour in canonical form: lower-case `#rrggbb`; `#rgb` is expanded.
	 *
	 * @param string $colour The colour as entered.
	 * @param string $field  The field name, for the message.
	 *
	 * @return string
	 *
	 * @throws InvalidArgumentException When the colour is not a hex colour.
	 *
	 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001
	 */
	public static function normaliseHex(string $colour, string $field): string {
		$colour = strtolower(trim($colour));
		if (preg_match('/^#([0-9a-f]{3})$/', $colour, $short) === 1) {
			$colour = '#' . $short[1][0] . $short[1][0] . $short[1][1] . $short[1][1] . $short[1][2] . $short[1][2];
		}

		if (preg_match('/^#[0-9a-f]{6}$/', $colour) !== 1) {
			throw new InvalidArgumentException($field . ' must be a colour like #1f5c3a.');
		}

		return $colour;
	}//end normaliseHex()

	/**
	 * The WCAG relative luminance of a `#rrggbb` colour.
	 *
	 * @param string $colour The colour.
	 *
	 * @return float Between 0 (black) and 1 (white).
	 *
	 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-a-look-must-stay-readable-req-thm-002
	 */
	public static function relativeLuminance(string $colour): float {
		$channels = [];
		foreach ([1, 3, 5] as $offset) {
			$value = hexdec(substr($colour, $offset, 2)) / 255;
			if ($value <= 0.04045) {
				$channels[] = $value / 12.92;
				continue;
			}

			$channels[] = ((($value + 0.055) / 1.055) ** 2.4);
		}

		return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
	}//end relativeLuminance()

	/**
	 * The WCAG contrast ratio between two colours.
	 *
	 * @param string $first  One colour.
	 * @param string $second The other colour.
	 *
	 * @return float From 1 (same) to 21 (black on white).
	 *
	 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-a-look-must-stay-readable-req-thm-002
	 */
	public static function contrast(string $first, string $second): float {
		$lighter = max(self::relativeLuminance(colour: $first), self::relativeLuminance(colour: $second));
		$darker  = min(self::relativeLuminance(colour: $first), self::relativeLuminance(colour: $second));

		return ($lighter + 0.05) / ($darker + 0.05);
	}//end contrast()

	/**
	 * Black or white, whichever reads better on the background.
	 *
	 * @param string $background The background colour.
	 *
	 * @return string `#000000` or `#ffffff`.
	 *
	 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-a-look-must-stay-readable-req-thm-002
	 */
	public static function textColorFor(string $background): string {
		if (self::contrast(first: '#000000', second: $background) >= self::contrast(first: '#ffffff', second: $background)) {
			return '#000000';
		}

		return '#ffffff';
	}//end textColorFor()

	/**
	 * Refuse an accent that would be hard to see on the background.
	 *
	 * @param string      $accent     The accent colour.
	 * @param string|null $background The dashboard's background colour, or null for the instance's.
	 *
	 * @return void
	 *
	 * @throws ContrastTooLowException When the contrast is under 3:1.
	 *
	 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-a-look-must-stay-readable-req-thm-002
	 */
	public static function assertAccentReadable(string $accent, ?string $background): void {
		$ratio = self::contrast(first: $accent, second: ($background ?? self::INSTANCE_BACKGROUND));
		if ($ratio < self::MIN_ACCENT_CONTRAST) {
			throw new ContrastTooLowException(ratio: $ratio);
		}
	}//end assertAccentReadable()

	/**
	 * Whether a value is a LaunchPad resource file name (what the upload returns).
	 *
	 * @param string $name The value.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-each-dashboard-can-have-its-own-look-req-thm-001
	 */
	public static function isResourceName(string $name): bool {
		return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,200}\.(png|jpe?g|gif|webp|svg)$/i', $name) === 1
			&& str_contains($name, '..') === false;
	}//end isResourceName()
}//end class
