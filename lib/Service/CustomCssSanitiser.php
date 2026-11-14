<?php

/**
 * CustomCssSanitiser
 *
 * Cleans administrator CSS for LaunchPad and for one dashboard, and scopes it
 * so it cannot restyle Nextcloud outside LaunchPad (REQ-THM-003).
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
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;

/**
 * Reads CSS as a list of plain rules (and `@media` groups of them) and rebuilds
 * only what it can read and judge safe. It works as an allow-list: anything it
 * cannot read is dropped, never passed through. Every at-rule except `@media`
 * is dropped, so `@import` and `@font-face` never survive. A declaration is
 * dropped when it holds an escape, markup, `expression(`, a script address, a
 * `behavior` or `-moz-binding` property, or a `url()` that is not one of
 * LaunchPad's own resources. Every selector is prefixed with the scope. The
 * result lists what was dropped, so the administrator can see it.
 *
 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
 */
final class CustomCssSanitiser {

	/**
	 * The largest CSS accepted and stored, in bytes.
	 */
	public const MAX_BYTES = 20480;

	/**
	 * The scope of the CSS for all of LaunchPad.
	 */
	public const GLOBAL_SCOPE = '.launchpad-workspace';

	/**
	 * Properties that run code in old browsers.
	 */
	private const SCRIPT_PROPERTIES = ['behavior', '-moz-binding'];

	/**
	 * Value fragments that run code or reach an address.
	 *
	 * @var array<string, string> Fragment => what the administrator is told.
	 */
	/**
	 * What the running {@see self::sanitise()} call dropped, as labels.
	 *
	 * @var list<string>
	 */
	private array $removed = [];

	private const UNSAFE_VALUES = [
		'expression('  => 'expression()',
		'javascript:'  => 'javascript:',
		'vbscript:'    => 'javascript:',
		'image-set('   => 'url() to another site',
		'image('       => 'url() to another site',
		'cross-fade('  => 'url() to another site',
		'element('     => 'url() to another site',
		'src('         => 'url() to another site',
	];

	/**
	 * The scope of the CSS for one dashboard: its region on the page.
	 *
	 * @param string $uuid The dashboard's UUID.
	 *
	 * @return string The selector of that dashboard's region.
	 *
	 * @throws InvalidArgumentException When the UUID could break out of the selector.
	 *
	 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
	 */
	public static function dashboardScope(string $uuid): string {
		if (preg_match('/^[A-Za-z0-9-]{1,64}$/', $uuid) !== 1) {
			throw new InvalidArgumentException('The dashboard has no usable identifier.');
		}

		return '.launchpad-dashboard[data-dashboard-uuid="' . $uuid . '"]';
	}//end dashboardScope()

	/**
	 * Clean and scope CSS.
	 *
	 * @param string $css   The CSS as the administrator entered it.
	 * @param string $scope The selector every rule is scoped to.
	 *
	 * @return array{css: string, removed: list<string>} The CSS to store and what was dropped.
	 *
	 * @throws InvalidArgumentException When the CSS, or its scoped form, is larger than 20 KB.
	 *
	 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
	 */
	public function sanitise(string $css, string $scope): array {
		if (strlen($css) > self::MAX_BYTES) {
			throw new InvalidArgumentException('Custom CSS may be at most 20 KB.');
		}

		$this->removed = [];
		$source  = (string)preg_replace('~/\*.*?(?:\*/|$)~s', '', $css);
		$rules   = $this->readRules(css: $source, scope: $scope, inGroup: false);
		$output  = implode("\n\n", $rules);

		if (strlen($output) > self::MAX_BYTES) {
			throw new InvalidArgumentException('Custom CSS may be at most 20 KB, also after scoping it to LaunchPad.');
		}

		return ['css' => $output, 'removed' => array_values(array_unique($this->removed))];
	}//end sanitise()

	/**
	 * Read a list of rules and rebuild the safe ones.
	 *
	 * @param string       $css     The CSS, without comments.
	 * @param string       $scope   The selector every rule is scoped to.
	 * @param bool         $inGroup Whether this list sits inside an `@media` group.
	 *
	 * @return list<string> The rebuilt rules.
	 */
	private function readRules(string $css, string $scope, bool $inGroup): array {
		$rules  = [];
		$length = strlen($css);
		$pos    = 0;
		while (true) {
			while ($pos < $length && ctype_space($css[$pos]) === true) {
				$pos++;
			}

			if ($pos >= $length) {
				break;
			}

			$end = $this->findTopLevel(css: $css, from: $pos, stops: '{;}');
			if ($end >= $length || $css[$end] !== '{') {
				// A statement without a block (`@import ...;`) or text that is no rule.
				$this->removed[] = $this->label(prelude: substr($css, $pos, ($end - $pos)));
				$pos       = ($end + 1);
				continue;
			}

			$close = $this->matchingBrace(css: $css, open: $end);
			if ($close >= $length) {
				$this->removed[] = 'unreadable CSS';
				break;
			}

			$prelude = trim(substr($css, $pos, ($end - $pos)));
			$body    = substr($css, ($end + 1), ($close - $end - 1));
			$pos     = ($close + 1);

			$rule = $this->readRule(prelude: $prelude, body: $body, scope: $scope, inGroup: $inGroup);
			if ($rule !== null) {
				$rules[] = $rule;
			}
		}//end while

		return $rules;
	}//end readRules()

	/**
	 * Rebuild one rule or `@media` group, or null when nothing of it is kept.
	 *
	 * @param string       $prelude The selector list or at-rule prelude.
	 * @param string       $body    What is between the braces.
	 * @param string       $scope   The selector every rule is scoped to.
	 * @param bool         $inGroup Whether the rule sits inside an `@media` group.
	 *
	 * @return string|null The rebuilt rule.
	 */
	private function readRule(string $prelude, string $body, string $scope, bool $inGroup): ?string {
		if (str_starts_with($prelude, '@') === true) {
			$isMedia = preg_match('/^@media\s+([a-zA-Z0-9\s(),:.\-]+)$/i', $prelude, $match) === 1;
			if ($isMedia === false || $inGroup === true) {
				$this->removed[] = $this->label(prelude: $prelude);
				return null;
			}

			$inner = $this->readRules(css: $body, scope: $scope, inGroup: true);
			if ($inner === []) {
				return null;
			}

			$query = (string)preg_replace('/\s+/', ' ', trim($match[1]));
			return '@media ' . $query . " {\n" . implode("\n\n", $inner) . "\n}";
		}

		if ($this->findTopLevel(css: $body, from: 0, stops: '{') < strlen($body)) {
			$this->removed[] = 'nested rules';
			return null;
		}

		$selectors = $this->scopeSelectors(prelude: $prelude, scope: $scope);
		if ($selectors === null) {
			$this->removed[] = 'unsafe selector';
			return null;
		}

		$declarations = $this->readDeclarations(body: $body);
		if ($declarations === []) {
			return null;
		}

		return $selectors . " {\n  " . implode(";\n  ", $declarations) . ";\n}";
	}//end readRule()

	/**
	 * Prefix every selector of a list with the scope, or null when one cannot be judged.
	 *
	 * @param string $prelude The selector list.
	 * @param string $scope   The scope.
	 *
	 * @return string|null The scoped list.
	 */
	private function scopeSelectors(string $prelude, string $scope): ?string {
		if ($prelude === '' || preg_match('/[<\\\\@;{}]|url\(/i', $prelude) === 1) {
			return null;
		}

		$scoped = [];
		foreach ($this->splitTopLevel(text: $prelude, separator: ',') as $selector) {
			$selector = (string)preg_replace('/\s+/', ' ', trim($selector));
			if ($selector === '') {
				return null;
			}

			$scoped[] = $this->scopeOne(selector: $selector, scope: $scope);
		}

		return implode(', ', $scoped);
	}//end scopeSelectors()

	/**
	 * Prefix one selector with the scope, unless it already starts at the scope itself.
	 *
	 * @param string $selector The selector.
	 * @param string $scope    The scope.
	 *
	 * @return string The scoped selector.
	 */
	private function scopeOne(string $selector, string $scope): string {
		if (str_starts_with($selector, $scope) === true) {
			$next = substr($selector, strlen($scope), 1);
			if ($next === '' || preg_match('/[A-Za-z0-9_-]/', $next) !== 1) {
				return $selector;
			}
		}

		return $scope . ' ' . $selector;
	}//end scopeOne()

	/**
	 * Read the declarations of a rule and keep the safe ones.
	 *
	 * @param string       $body    What is between the braces.
	 *
	 * @return list<string> The kept declarations, as `property: value`.
	 */
	private function readDeclarations(string $body): array {
		$kept = [];
		foreach ($this->splitTopLevel(text: $body, separator: ';') as $declaration) {
			$declaration = trim($declaration);
			if ($declaration === '') {
				continue;
			}

			$colon = strpos($declaration, ':');
			if ($colon === false) {
				$this->removed[] = 'unreadable CSS';
				continue;
			}

			$property = strtolower(trim(substr($declaration, 0, $colon)));
			$value    = (string)preg_replace('/\s+/', ' ', trim(substr($declaration, ($colon + 1))));
			$problem  = $this->declarationProblem(property: $property, value: $value);
			if ($problem !== null) {
				$this->removed[] = $problem;
				continue;
			}

			$kept[] = $property . ': ' . $value;
		}//end foreach

		return $kept;
	}//end readDeclarations()

	/**
	 * Why a declaration is dropped, or null when it is kept.
	 *
	 * @param string $property The property, lower case.
	 * @param string $value    The value.
	 *
	 * @return string|null What the administrator is told.
	 */
	private function declarationProblem(string $property, string $value): ?string {
		if (preg_match('/^-{0,2}[a-z][a-z0-9-]*$/', $property) !== 1 || $value === '') {
			return 'unreadable CSS';
		}

		if (in_array($property, self::SCRIPT_PROPERTIES, true) === true) {
			return $property;
		}

		if (str_contains($value, '\\') === true) {
			return 'escapes';
		}

		if (str_contains($value, '<') === true) {
			return 'markup';
		}

		$lower = strtolower($value);
		foreach (self::UNSAFE_VALUES as $fragment => $label) {
			if (str_contains($lower, $fragment) === true) {
				return $label;
			}
		}

		return $this->urlProblem(value: $value);
	}//end declarationProblem()

	/**
	 * Why the `url()` references of a value are refused, or null when every one is a LaunchPad resource.
	 *
	 * @param string $value The value.
	 *
	 * @return string|null What the administrator is told.
	 */
	private function urlProblem(string $value): ?string {
		$calls = substr_count(strtolower($value), 'url(');
		if ($calls === 0) {
			return null;
		}

		$found = preg_match_all('~url\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'()]*))\s*\)~i', $value, $matches, PREG_SET_ORDER);
		if ($found !== $calls) {
			return 'url() to another site';
		}

		foreach ($matches as $match) {
			$target = (($match[1] ?? '') . ($match[2] ?? '') . ($match[3] ?? ''));
			if ($this->isOwnResource(target: $target) === false) {
				return 'url() to another site';
			}
		}

		return null;
	}//end urlProblem()

	/**
	 * Whether an address is one of LaunchPad's own uploaded resources, on this server.
	 *
	 * @param string $target The address inside `url()`.
	 *
	 * @return bool
	 */
	private function isOwnResource(string $target): bool {
		if (str_contains($target, '..') === true) {
			return false;
		}

		$isOwnPath = preg_match('~^(?:/[A-Za-z0-9_][A-Za-z0-9._-]*)*/apps/launchpad/resource/([^/?#]+)$~', $target, $match) === 1;

		return $isOwnPath === true && DashboardLook::isResourceName(name: $match[1]) === true;
	}//end isOwnResource()

	/**
	 * What the administrator is told about a dropped at-rule or unreadable text.
	 *
	 * @param string $prelude The text that was dropped.
	 *
	 * @return string The label.
	 */
	private function label(string $prelude): string {
		if (preg_match('/^\s*(@[A-Za-z-]+)/', $prelude, $match) === 1) {
			return strtolower($match[1]);
		}

		return 'unreadable CSS';
	}//end label()

	/**
	 * The first position of one of the stop characters outside strings,
	 * parentheses and brackets, or the length of the text when there is none.
	 *
	 * @param string $css   The text.
	 * @param int    $from  Where to start.
	 * @param string $stops The stop characters.
	 *
	 * @return int The position.
	 */
	private function findTopLevel(string $css, int $from, string $stops): int {
		$length  = strlen($css);
		$nesting = ['depth' => 0, 'quote' => null];
		for ($pos = $from; $pos < $length; $pos++) {
			$char = $css[$pos];
			if (self::nestingStep(char: $char, nesting: $nesting) === true) {
				continue;
			}

			if ($nesting['depth'] === 0 && str_contains($stops, $char) === true) {
				return $pos;
			}
		}

		return $length;
	}//end findTopLevel()

	/**
	 * Track one character's effect on string and bracket nesting.
	 *
	 * @param string                                $char    The character.
	 * @param array{depth: int, quote: string|null} $nesting The parenthesis and bracket depth and the
	 *                                                       quote of the open string; updated.
	 *
	 * @return bool True when the character belongs to the nesting (inside a
	 *              string, a quote, or a bracket that opens or closes one), so
	 *              it is no stop character.
	 */
	private static function nestingStep(string $char, array &$nesting): bool {
		return self::quoteStep(char: $char, nesting: $nesting) === true
			|| self::bracketStep(char: $char, nesting: $nesting) === true;
	}//end nestingStep()

	/**
	 * Track a character inside a string, or a quote that opens one.
	 *
	 * @param string                                $char    The character.
	 * @param array{depth: int, quote: string|null} $nesting The nesting; its quote is updated.
	 *
	 * @return bool True when the character is inside a string or a quote.
	 */
	private static function quoteStep(string $char, array &$nesting): bool {
		if ($nesting['quote'] !== null) {
			if ($char === $nesting['quote']) {
				$nesting['quote'] = null;
			}

			return true;
		}

		if ($char === '"' || $char === "'") {
			$nesting['quote'] = $char;
			return true;
		}

		return false;
	}//end quoteStep()

	/**
	 * Track a bracket that opens, or closes an open one.
	 *
	 * @param string                                $char    The character.
	 * @param array{depth: int, quote: string|null} $nesting The nesting; its depth is updated.
	 *
	 * @return bool True when the character opens a parenthesis or bracket, or closes an open one.
	 */
	private static function bracketStep(string $char, array &$nesting): bool {
		$delta = (['(' => 1, '[' => 1, ')' => -1, ']' => -1][$char] ?? 0);
		if ($delta === 0 || ($nesting['depth'] + $delta) < 0) {
			return false;
		}

		$nesting['depth'] += $delta;
		return true;
	}//end bracketStep()

	/**
	 * The position of the brace that closes the one at `$open`, or the length when it is never closed.
	 *
	 * @param string $css  The text.
	 * @param int    $open The position of the opening brace.
	 *
	 * @return int The position.
	 */
	private function matchingBrace(string $css, int $open): int {
		$length = strlen($css);
		$depth  = 0;
		$pos    = $open;
		while ($pos < $length) {
			$pos = $this->findTopLevel(css: $css, from: $pos, stops: '{}');
			if ($pos >= $length) {
				return $length;
			}

			if ($css[$pos] === '{') {
				$depth++;
			}

			if ($css[$pos] === '}') {
				$depth--;
				if ($depth === 0) {
					return $pos;
				}
			}

			$pos++;
		}

		return $length;
	}//end matchingBrace()

	/**
	 * Split text on a separator outside strings, parentheses and brackets.
	 *
	 * @param string $text      The text.
	 * @param string $separator The separator character.
	 *
	 * @return list<string> The parts.
	 */
	private function splitTopLevel(string $text, string $separator): array {
		$parts  = [];
		$length = strlen($text);
		$start  = 0;
		while ($start <= $length) {
			$end     = $this->findTopLevel(css: $text, from: $start, stops: $separator);
			$parts[] = substr($text, $start, ($end - $start));
			$start   = ($end + 1);
		}

		return $parts;
	}//end splitTopLevel()
}//end class
