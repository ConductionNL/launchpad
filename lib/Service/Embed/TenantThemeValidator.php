<?php

/**
 * TenantThemeValidator
 *
 * The checks a tenant theme passes before an embed may use it (REQ-EMB-005,
 * REQ-EMB-010): custom CSS may only declare inside `:root`, because a rule on
 * any other selector could hide required affordances such as focus rings;
 * text on the surface colour must reach WCAG 2.1 contrast 4.5:1; and the
 * `:root` block of CSS variables is rendered with names and values that
 * cannot break out of the style element.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\Embed
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\Embed;

/**
 * Validates and renders a tenant theme's CSS.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-005--per-tenant-theming-via-css-variables
 */
final class TenantThemeValidator {

	/**
	 * The minimum contrast of normal text (WCAG 1.4.3).
	 *
	 * @var float
	 */
	public const MIN_CONTRAST = 4.5;

	/**
	 * The refusal of custom CSS that reaches outside `:root`, or null when it is accepted.
	 *
	 * @param string $customCss The custom CSS.
	 *
	 * @return array{error: string, message: string}|null
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-005--per-tenant-theming-via-css-variables
	 */
	public function validateCss(string $customCss): ?array {
		$css = trim((string)preg_replace('~/\*.*?\*/~s', '', $customCss));
		if ($css === '') {
			return null;
		}

		if (preg_match('~[<@\\\\]~', $css) === 1) {
			return $this->refusal(message: 'Custom CSS may hold only :root declarations: no at-rules, escapes or markup.');
		}

		if (preg_match_all('~([^{}]*)\{([^{}]*)\}~', $css, $rules, PREG_SET_ORDER) === 0
			|| trim((string)preg_replace('~[^{}]*\{[^{}]*\}~', '', $css)) !== ''
		) {
			return $this->refusal(message: 'Custom CSS could not be read as :root rules.');
		}

		foreach ($rules as $rule) {
			foreach (explode(',', $rule[1]) as $selector) {
				$selector = trim($selector);
				if ($selector !== ':root') {
					return $this->refusal(message: sprintf(
						'CSS rules must be scoped to :root only. Found selector \'%s\' which targets elements outside :root. '
						. 'Non-:root selectors can be used to disable required UI affordances (e.g., hide accessibility focus rings).',
						$selector
					));
				}
			}
		}

		return null;
	}//end validateCss()

	/**
	 * The WCAG contrast ratio of two `#rgb` or `#rrggbb` colours, or null when one is not such a colour.
	 *
	 * @param string $foreground The text colour.
	 * @param string $background The surface colour.
	 *
	 * @return float|null The ratio, from 1 to 21, rounded to two decimals.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-010--wcag-21-aa-compliance-enforced-in-embed-rendering
	 */
	public function contrastRatio(string $foreground, string $background): ?float {
		$one = $this->luminance(colour: $foreground);
		$two = $this->luminance(colour: $background);
		if ($one === null || $two === null) {
			return null;
		}

		return round((max($one, $two) + 0.05) / (min($one, $two) + 0.05), 2);
	}//end contrastRatio()

	/**
	 * Whether text in one colour is readable on the other (at least 4.5:1).
	 *
	 * @param string $foreground The text colour.
	 * @param string $background The surface colour.
	 *
	 * @return bool False also for a colour that cannot be read.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-010--wcag-21-aa-compliance-enforced-in-embed-rendering
	 */
	public function validateContrastRatio(string $foreground, string $background): bool {
		$ratio = $this->contrastRatio(foreground: $foreground, background: $background);

		return $ratio !== null && $ratio >= self::MIN_CONTRAST;
	}//end validateContrastRatio()

	/**
	 * The `:root` block of a theme's CSS variables. A name that is not a
	 * custom property, or a value that could close the declaration or the
	 * style element, is left out.
	 *
	 * @param array<mixed, mixed> $cssVariables Name => value.
	 *
	 * @return string The block, or '' when no variable is usable.
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-005--per-tenant-theming-via-css-variables
	 */
	public function rootBlock(array $cssVariables): string {
		$lines = [];
		foreach ($cssVariables as $name => $value) {
			if (is_string($name) === false || preg_match('~^--[a-zA-Z0-9_-]+$~', $name) !== 1) {
				continue;
			}

			if (is_scalar($value) === false || preg_match('~[;{}<>\\\\]~', (string)$value) === 1) {
				continue;
			}

			$lines[] = '  ' . $name . ': ' . trim((string)$value) . ';';
		}

		if ($lines === []) {
			return '';
		}

		return ":root {\n" . implode("\n", $lines) . "\n}";
	}//end rootBlock()

	/**
	 * The relative luminance of a hex colour, or null.
	 *
	 * @param string $colour The colour.
	 *
	 * @return float|null
	 */
	private function luminance(string $colour): ?float {
		$hex = ltrim(trim($colour), '#');
		if (preg_match('~^([0-9a-f]{3}|[0-9a-f]{6})$~i', $hex) !== 1) {
			return null;
		}

		if (strlen($hex) === 3) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		$channels = [];
		foreach ([0, 2, 4] as $offset) {
			$value = (hexdec(substr($hex, $offset, 2)) / 255);
			if ($value <= 0.03928) {
				$channels[] = ($value / 12.92);
				continue;
			}

			$channels[] = ((($value + 0.055) / 1.055) ** 2.4);
		}

		return ((0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]));
	}//end luminance()

	/**
	 * The validation refusal body.
	 *
	 * @param string $message The message.
	 *
	 * @return array{error: string, message: string}
	 */
	private function refusal(string $message): array {
		return ['error' => 'validation_error', 'message' => $message];
	}//end refusal()

}//end class
