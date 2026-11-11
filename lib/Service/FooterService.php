<?php

/**
 * FooterService
 *
 * Resolves the global instance footer + per-dashboard overrides for
 * the footer-customization capability. Owns the shared HTML
 * sanitisation allow-list (REQ-FTR-005, design D4) and the structured
 * config schema validator (REQ-FTR-003).
 *
 * Storage:
 *  - Global settings live in `launchpad_admin_settings` under five keys
 *    on {@see \OCA\LaunchPad\Db\AdminSetting} (`KEY_FOOTER_*`).
 *  - Per-dashboard override lives on the {@see \OCA\LaunchPad\Db\Dashboard}
 *    entity (`dashboardFooterMode`, `dashboardFooterHtml`).
 *
 * Public surface:
 *  - {@see FooterService::getGlobalSettings()} — read settings (returns
 *    camelCase array suitable for the `GET /api/admin/footer-settings`
 *    response).
 *  - {@see FooterService::updateGlobalSettings()} — patch settings,
 *    sanitises HTML / validates structured config / validates colour
 *    hex strings before persisting.
 *  - {@see FooterService::sanitiseHtml()} — public so
 *    `DashboardService` (and tests) can apply identical sanitisation
 *    to the per-dashboard `dashboardFooterHtml` field.
 *  - {@see FooterService::resolveFooterForDashboard()} — produces the
 *    `effectiveFooter` payload for a dashboard's API response.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;

/**
 * Resolve the instance footer + per-dashboard overrides.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 *      Sanitiser + structured-config validator + colour validator +
 *      resolution all live here intentionally so the footer surface
 *      has one canonical implementation.
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 *      `sanitiseHtml`, `updateGlobalSettings`, and
 *      `resolveFooterForDashboard` all need explicit branching for
 *      each schema-validated input — splitting hides the contract.
 * @SuppressWarnings(PHPMD.NPathComplexity)
 *      Same — every conditional in the sanitiser is documented in
 *      REQ-FTR-005 and the test suite covers each branch.
 * @spec                                             openspec/specs/dashboards/spec.md
 */
class FooterService {
	/**
	 * Colour fields of the settings patch and the setting each is stored in.
	 */
	private const COLOUR_SETTINGS = [
		'footerBackgroundColor' => AdminSetting::KEY_FOOTER_BACKGROUND_COLOR,
		'footerTextColor' => AdminSetting::KEY_FOOTER_TEXT_COLOR,
	];

	/**
	 * Maximum allowed footer HTML length (REQ-FTR-002 — 8 KB cap).
	 * Inputs that exceed this MUST be rejected with HTTP 413 by the
	 * controller — `sanitiseHtml()` throws
	 * {@see \InvalidArgumentException} so the caller can map it.
	 *
	 * @var integer
	 */
	public const MAX_HTML_BYTES = 8192;

	/**
	 * Tag allow-list for the footer HTML (REQ-FTR-005, design D4).
	 * Mirrors the text-display widget's allow-list — a single
	 * canonical definition that both surfaces SHOULD reference.
	 *
	 * @var array<int, string>
	 */
	public const ALLOWED_TAGS = [
		'a',
		'p',
		'strong',
		'em',
		'br',
		'ul',
		'ol',
		'li',
		'img',
	];

	/**
	 * Per-tag attribute allow-list (REQ-FTR-005). Tags not listed in
	 * {@see FooterService::ALLOWED_TAGS} are stripped wholesale; tags
	 * listed but missing from this map keep no attributes.
	 *
	 * @var array<string, array<int, string>>
	 */
	public const ALLOWED_ATTRIBUTES = [
		'a' => ['href'],
		'img' => ['src'],
	];

	/**
	 * Allowed structured-config top-level keys (REQ-FTR-003). Schema
	 * payloads with extra keys MUST be rejected with HTTP 400 — the
	 * service throws {@see \InvalidArgumentException}.
	 *
	 * @var array<int, string>
	 */
	public const STRUCTURED_CONFIG_KEYS = [
		'logoUrl',
		'organisation',
		'address',
		'links',
		'legal',
		'copyrightYear',
		'layoutMode',
	];

	/**
	 * Allowed structured layout modes (REQ-FTR-003).
	 *
	 * @var array<int, string>
	 */
	public const LAYOUT_MODES = ['columns', 'inline'];

	/**
	 * Constructor.
	 *
	 * @param AdminSettingMapper $settingMapper Persisted-settings mapper.
	 */
	public function __construct(
		private readonly AdminSettingMapper $settingMapper,
	) {
	}//end __construct()

	/**
	 * Read the five footer settings into a camelCase response payload.
	 *
	 * Defaults:
	 *  - `footerEnabled` — false (REQ-FTR-001 default-off scenario).
	 *  - `footerHtml` — empty string.
	 *  - `footerConfig` — empty stdClass-like array (`[]`).
	 *  - `footerBackgroundColor` / `footerTextColor` — null (theme
	 *    fallback).
	 *
	 * @return array<string, mixed> Settings keyed by camelCase
	 *                              (`footerEnabled`, `footerHtml`,
	 *                              `footerConfig`,
	 *                              `footerBackgroundColor`,
	 *                              `footerTextColor`).
	 *
	 * @spec openspec/specs/footer-customization/spec.md
	 */
	public function getGlobalSettings(): array {
		return [
			'footerEnabled' => (bool)$this->settingMapper->getValue(
				key: AdminSetting::KEY_FOOTER_ENABLED,
				default: false
			),
			'footerHtml' => $this->storedFooterHtml(),
			'footerConfig' => $this->storedFooterConfig(),
			'footerBackgroundColor' => $this->storedColour(key: AdminSetting::KEY_FOOTER_BACKGROUND_COLOR),
			'footerTextColor' => $this->storedColour(key: AdminSetting::KEY_FOOTER_TEXT_COLOR),
		];
	}//end getGlobalSettings()

	/**
	 * Read the stored footer HTML; anything but a string or a variant map
	 * reads as the empty string.
	 *
	 * @return string|array<string, string> The stored footer HTML.
	 */
	private function storedFooterHtml(): string|array {
		$html = $this->settingMapper->getValue(
			key: AdminSetting::KEY_FOOTER_HTML,
			default: ''
		);

		if (is_string($html) === false && is_array($html) === false) {
			return '';
		}

		return $html;
	}//end storedFooterHtml()

	/**
	 * Read the stored structured footer config; a non-array reads as empty.
	 *
	 * @return array<string, mixed> The stored config.
	 */
	private function storedFooterConfig(): array {
		$config = $this->settingMapper->getValue(
			key: AdminSetting::KEY_FOOTER_CONFIG,
			default: []
		);

		if (is_array($config) === false) {
			return [];
		}

		return $config;
	}//end storedFooterConfig()

	/**
	 * Read one stored footer colour; a non-string reads as NULL.
	 *
	 * @param string $key The AdminSetting key of the colour.
	 *
	 * @return string|null The stored colour.
	 */
	private function storedColour(string $key): ?string {
		$colour = $this->settingMapper->getValue(key: $key, default: null);

		if (is_string($colour) === false) {
			return null;
		}

		return $colour;
	}//end storedColour()

	/**
	 * Patch one or more global footer settings (REQ-FTR-001..003,
	 * REQ-FTR-009, REQ-FTR-010).
	 *
	 * Only keys present in `$patch` are updated; untouched keys
	 * retain their previous values. Each value is validated before
	 * persistence:
	 *  - `footerEnabled` — coerced to bool.
	 *  - `footerHtml` — sanitised via {@see FooterService::sanitiseHtml()}
	 *    (throws on > 8 KB input).
	 *  - `footerConfig` — validated via {@see FooterService::validateStructuredConfig()}.
	 *  - `footerBackgroundColor` / `footerTextColor` — validated via
	 *    {@see FooterService::assertHexColour()} (or NULL to clear).
	 *
	 * @param array<string, mixed> $patch Partial payload from the
	 *                                    HTTP body.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When validation fails. The
	 *                                  controller maps the message to
	 *                                  HTTP 400 / 413 as appropriate.
	 *
	 * @spec openspec/specs/footer-customization/spec.md
	 */
	public function updateGlobalSettings(array $patch): void {
		if (array_key_exists(key: 'footerEnabled', array: $patch) === true) {
			$this->settingMapper->setSetting(
				key: AdminSetting::KEY_FOOTER_ENABLED,
				value: (bool)$patch['footerEnabled']
			);
		}

		$this->storeFooterContent(patch: $patch);
		$this->storeFooterColours(patch: $patch);
	}//end updateGlobalSettings()

	/**
	 * Store the footerHtml and footerConfig values of a patch, when present.
	 *
	 * @param array<string, mixed> $patch Partial payload from the HTTP body.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When a value fails validation.
	 */
	private function storeFooterContent(array $patch): void {
		if (array_key_exists(key: 'footerHtml', array: $patch) === true) {
			$this->settingMapper->setSetting(
				key: AdminSetting::KEY_FOOTER_HTML,
				value: $this->sanitiseFooterHtml(raw: $patch['footerHtml'])
			);
		}

		if (array_key_exists(key: 'footerConfig', array: $patch) === true) {
			$this->settingMapper->setSetting(
				key: AdminSetting::KEY_FOOTER_CONFIG,
				value: $this->validatedFooterConfig(rawConfig: $patch['footerConfig'])
			);
		}
	}//end storeFooterContent()

	/**
	 * Store the colour values of a patch, when present; NULL clears one.
	 *
	 * @param array<string, mixed> $patch Partial payload from the HTTP body.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When a colour is not a hex string.
	 */
	private function storeFooterColours(array $patch): void {
		foreach (self::COLOUR_SETTINGS as $field => $key) {
			if (array_key_exists(key: $field, array: $patch) === true) {
				$colour = $patch[$field];
				if ($colour !== null) {
					$this->assertHexColour(value: $colour, fieldName: $field);
				}

				$this->settingMapper->setSetting(key: $key, value: $colour);
			}
		}
	}//end storeFooterColours()

	/**
	 * Sanitise the footerHtml value of a patch: NULL clears it, a string is
	 * sanitised, a language-tagged variant map (REQ-FTR-007) is sanitised
	 * per variant.
	 *
	 * @param mixed $raw The footerHtml value.
	 *
	 * @return string|array<string, string> The value to store.
	 *
	 * @throws InvalidArgumentException When the value has the wrong shape.
	 */
	private function sanitiseFooterHtml(mixed $raw): string|array {
		$this->assertFooterHtmlShape(raw: $raw);

		if (is_string($raw) === true) {
			return $this->sanitiseHtml(html: $raw);
		}

		if (is_array($raw) === true) {
			return $this->sanitiseHtmlVariants(variants: $raw);
		}

		return '';
	}//end sanitiseFooterHtml()

	/**
	 * Refuse a footerHtml value that is neither NULL, a string nor a map.
	 *
	 * @param mixed $raw The footerHtml value.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the value has the wrong shape.
	 */
	private function assertFooterHtmlShape(mixed $raw): void {
		if ($raw !== null && is_array($raw) === false && is_string($raw) === false) {
			throw new InvalidArgumentException(
				message: 'footerHtml must be a string, NULL, or a variant map'
			);
		}
	}//end assertFooterHtmlShape()

	/**
	 * Sanitise each variant of a language-tagged footer HTML map.
	 *
	 * @param array<mixed, mixed> $variants The locale => HTML map.
	 *
	 * @return array<string, string> The sanitised map.
	 *
	 * @throws InvalidArgumentException When a locale or variant is not a string.
	 */
	private function sanitiseHtmlVariants(array $variants): array {
		$sanitised = [];
		foreach ($variants as $locale => $variant) {
			if (is_string($locale) === false || is_string($variant) === false) {
				throw new InvalidArgumentException(
					message: 'footerHtml language variants must be string→string'
				);
			}

			$sanitised[$locale] = $this->sanitiseHtml(html: $variant);
		}

		return $sanitised;
	}//end sanitiseHtmlVariants()

	/**
	 * Validate the footerConfig value of a patch; NULL stores an empty config.
	 *
	 * @param mixed $rawConfig The footerConfig value.
	 *
	 * @return array<string, mixed> The config to store.
	 *
	 * @throws InvalidArgumentException When it is not a valid config object.
	 */
	private function validatedFooterConfig(mixed $rawConfig): array {
		if ($rawConfig === null) {
			$rawConfig = [];
		}

		if (is_array($rawConfig) === false) {
			throw new InvalidArgumentException(
				message: 'footerConfig must be a JSON object'
			);
		}

		$this->validateStructuredConfig(config: $rawConfig);

		return $rawConfig;
	}//end validatedFooterConfig()

	/**
	 * Sanitise raw footer HTML against the allow-list (REQ-FTR-002,
	 * REQ-FTR-005). Strips disallowed tags + attributes, normalises
	 * external links with `rel="noopener noreferrer"` and
	 * `target="_blank"`, and rejects oversized payloads.
	 *
	 * Implementation note: a tiny regex-based sanitiser is sufficient
	 * here because the allow-list is closed and small. The DOM-based
	 * fallback used by the text-display widget is overkill for the
	 * footer's narrow surface — any payload > 8 KB is rejected before
	 * processing so worst-case complexity is bounded.
	 *
	 * @param string $html The raw HTML input.
	 *
	 * @return string The sanitised HTML (always safe to render).
	 *
	 * @throws InvalidArgumentException When the input exceeds 8 KB.
	 *
	 * @spec openspec/specs/footer-customization/spec.md
	 */
	public function sanitiseHtml(string $html): string {
		if (strlen(string: $html) > self::MAX_HTML_BYTES) {
			throw new InvalidArgumentException(
				message: 'footerHtml exceeds 8 KB limit'
			);
		}

		if ($html === '') {
			return '';
		}

		$stripped = $this->stripScriptElements(html: $html);
		if ($stripped === null) {
			return '';
		}

		return $this->sanitiseTags(html: $stripped);
	}//end sanitiseHtml()

	/**
	 * Strip <script>...</script> bodies entirely (content + tags).
	 *
	 * @param string $html The raw HTML input.
	 *
	 * @return string|null The HTML without script elements, NULL on a regex failure.
	 */
	private function stripScriptElements(string $html): ?string {
		return preg_replace(
			pattern: '#<script\b[^>]*>.*?</script>#is',
			replacement: '',
			subject: $html
		);
	}//end stripScriptElements()

	/**
	 * Replace any tag with a sanitised version or empty string. The
	 * callback inspects each match, validates the tag name, drops
	 * disallowed attributes, and force-tags external <a> elements
	 * with rel/target.
	 *
	 * @param string $html HTML without script elements.
	 *
	 * @return string The sanitised HTML, '' on a regex failure.
	 */
	private function sanitiseTags(string $html): string {
		$result = preg_replace_callback(
			pattern: '#<(/?)\s*([a-zA-Z0-9]+)\b([^>]*)>#s',
			callback: fn (array $matches): string => $this->sanitiseTag(matches: $matches),
			subject: $html
		);

		if ($result === null) {
			return '';
		}

		return $result;
	}//end sanitiseTags()

	/**
	 * Rebuild one matched tag in its sanitised form (REQ-FTR-002).
	 *
	 * A tag outside {@see self::ALLOWED_TAGS} collapses to the empty
	 * string. A closing tag is re-emitted bare — it carries no attributes
	 * to sanitise. An opening tag is rebuilt from scratch out of the
	 * surviving attributes, so nothing from the raw source can leak
	 * through.
	 *
	 * @param array<int, string> $matches The regex match: [full, slash, tag, attrs].
	 *
	 * @return string The sanitised tag, or '' when the tag is disallowed.
	 */
	private function sanitiseTag(array $matches): string {
		$tag = strtolower(string: $matches[2]);

		if (in_array(needle: $tag, haystack: self::ALLOWED_TAGS, strict: true) === false) {
			return '';
		}

		if ($matches[1] === '/') {
			return '</' . $tag . '>';
		}

		$kept = $this->sanitiseTagAttributes(tag: $tag, attrs: $matches[3]);

		return '<' . $tag . $this->renderAttributes(kept: $kept) . $this->externalLinkAttributes(tag: $tag, kept: $kept) . $this->tagEnd(tag: $tag);
	}//end sanitiseTag()

	/**
	 * Render kept attributes as ` name="value"` pairs.
	 *
	 * @param array<string, string> $kept The escaped name => value pairs.
	 *
	 * @return string The rendered attributes.
	 */
	private function renderAttributes(array $kept): string {
		$rendered = '';
		foreach ($kept as $name => $value) {
			$rendered .= ' ' . $name . '="' . $value . '"';
		}

		return $rendered;
	}//end renderAttributes()

	/**
	 * External <a> elements get rel + target automatically
	 * (REQ-FTR-002 external-link scenario).
	 *
	 * @param string                $tag  The lower-cased tag name.
	 * @param array<string, string> $kept The kept attributes.
	 *
	 * @return string The extra attributes, or ''.
	 */
	private function externalLinkAttributes(string $tag, array $kept): string {
		if ($tag === 'a'
			&& isset($kept['href']) === true
			&& preg_match(
				pattern: '#^https?://#i',
				subject: $kept['href']
			) === 1
		) {
			return ' rel="noopener noreferrer" target="_blank"';
		}

		return '';
	}//end externalLinkAttributes()

	/**
	 * The end of an opening tag: self-closing for void elements.
	 *
	 * @param string $tag The lower-cased tag name.
	 *
	 * @return string The tag end.
	 */
	private function tagEnd(string $tag): string {
		if (in_array(needle: $tag, haystack: ['br', 'img'], strict: true) === true) {
			return ' />';
		}

		return '>';
	}//end tagEnd()

	/**
	 * Extract the attributes of one opening tag that survive the
	 * allow-list (REQ-FTR-005).
	 *
	 * An attribute is kept only when its name is allow-listed for this
	 * tag AND its value does not carry a `javascript:` / `data:` /
	 * `vbscript:` scheme. Surviving values are HTML-escaped before they
	 * are handed back, so the caller can interpolate them directly.
	 *
	 * @param string $tag The lower-cased tag name.
	 * @param string $attrs The raw attribute blob from the source tag.
	 *
	 * @return array<string, string> The escaped name => value pairs to keep.
	 */
	private function sanitiseTagAttributes(string $tag, string $attrs): array {
		$allowedAttrs = $this->allowedAttributesFor(tag: $tag);
		$kept = [];
		if ($allowedAttrs === [] || trim(string: $attrs) === '') {
			return $kept;
		}

		foreach ($this->parseAttributes(attrs: $attrs) as $attr) {
			$value = $this->keptAttributeValue(attr: $attr, allowedAttrs: $allowedAttrs);
			if ($value !== null) {
				$kept[strtolower(string: $attr[1])] = $value;
			}
		}

		return $kept;
	}//end sanitiseTagAttributes()

	/**
	 * The attribute names allow-listed for a tag.
	 *
	 * @param string $tag The lower-cased tag name.
	 *
	 * @return array<int, string> The allowed attribute names.
	 */
	private function allowedAttributesFor(string $tag): array {
		return (self::ALLOWED_ATTRIBUTES[$tag] ?? []);
	}//end allowedAttributesFor()

	/**
	 * Split a raw attribute blob into name/value matches.
	 *
	 * @param string $attrs The raw attribute blob from the source tag.
	 *
	 * @return array<int, array<int, string>> The matches, in source order.
	 */
	private function parseAttributes(string $attrs): array {
		if (preg_match_all(
			pattern: '#([a-zA-Z][a-zA-Z0-9_-]*)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))#',
			subject: $attrs,
			matches: $found,
			flags: PREG_SET_ORDER
		) > 0
		) {
			return $found;
		}

		return [];
	}//end parseAttributes()

	/**
	 * The escaped value of one attribute match, or NULL when the attribute
	 * is not allow-listed or carries a `javascript:` / `data:` /
	 * `vbscript:` scheme.
	 *
	 * @param array<int, string> $attr         One attribute match.
	 * @param array<int, string> $allowedAttrs The names allowed on this tag.
	 *
	 * @return string|null The escaped value, or NULL to drop it.
	 */
	private function keptAttributeValue(array $attr, array $allowedAttrs): ?string {
		if (in_array(needle: strtolower(string: $attr[1]), haystack: $allowedAttrs, strict: true) === false) {
			return null;
		}

		$value = $this->attributeValue(attr: $attr);
		// Reject data: / javascript: schemes on URL attributes (href / src).
		// The allow-list already restricted us to those names so a scheme
		// check is enough.
		if (preg_match(pattern: '#^\s*(javascript|data|vbscript):#i', subject: $value) === 1) {
			return null;
		}

		// Keep simple value escaping for HTML context.
		return htmlspecialchars(
			string: $value,
			flags: (ENT_QUOTES | ENT_HTML5),
			encoding: 'UTF-8'
		);
	}//end keptAttributeValue()

	/**
	 * The raw value of one attribute match, whichever quoting it used.
	 *
	 * @param array<int, string> $attr One attribute match.
	 *
	 * @return string The raw value.
	 */
	private function attributeValue(array $attr): string {
		return ($attr[3] ?? ($attr[4] ?? ($attr[5] ?? '')));
	}//end attributeValue()

	/**
	 * Validate a structured-mode config payload against the documented
	 * schema (REQ-FTR-003). Throws on extra keys, malformed `links`,
	 * or an unknown `layoutMode`.
	 *
	 * @param array<string, mixed> $config The structured config.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException On schema mismatch.
	 *
	 * @spec openspec/specs/footer-customization/spec.md
	 */
	public function validateStructuredConfig(array $config): void {
		$this->assertKnownConfigKeys(config: $config);

		if (array_key_exists(key: 'layoutMode', array: $config) === true) {
			$this->assertLayoutMode(mode: $config['layoutMode']);
		}

		if (array_key_exists(key: 'links', array: $config) === true) {
			$this->assertFooterLinks(links: $config['links']);
		}
	}//end validateStructuredConfig()

	/**
	 * Refuse a structured config that carries a key outside the schema.
	 *
	 * @param array<string, mixed> $config The structured config.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException On an unknown key.
	 */
	private function assertKnownConfigKeys(array $config): void {
		foreach (array_keys(array: $config) as $key) {
			if (in_array(needle: $key, haystack: self::STRUCTURED_CONFIG_KEYS, strict: true) === false) {
				throw new InvalidArgumentException(
					message: 'footerConfig contains unknown key: ' . (string)$key
				);
			}
		}
	}//end assertKnownConfigKeys()

	/**
	 * Refuse a layoutMode outside {@see self::LAYOUT_MODES}.
	 *
	 * @param mixed $mode The layoutMode value.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException On an unknown layout mode.
	 */
	private function assertLayoutMode(mixed $mode): void {
		if (is_string($mode) === false
			|| in_array(needle: $mode, haystack: self::LAYOUT_MODES, strict: true) === false
		) {
			throw new InvalidArgumentException(
				message: 'footerConfig.layoutMode must be one of: ' . implode(separator: ', ', array: self::LAYOUT_MODES)
			);
		}
	}//end assertLayoutMode()

	/**
	 * Refuse a links value that is not a list of {label, url} string pairs.
	 *
	 * @param mixed $links The links value.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException On a malformed links value.
	 */
	private function assertFooterLinks(mixed $links): void {
		if (is_array($links) === false) {
			throw new InvalidArgumentException(
				message: 'footerConfig.links must be an array'
			);
		}

		foreach ($links as $entry) {
			if ($this->isFooterLink(entry: $entry) === false) {
				throw new InvalidArgumentException(
					message: 'footerConfig.links entries must be {label, url} string pairs'
				);
			}
		}
	}//end assertFooterLinks()

	/**
	 * Whether a links entry is a {label, url} string pair.
	 *
	 * @param mixed $entry One links entry.
	 *
	 * @return bool TRUE for a well-formed entry.
	 */
	private function isFooterLink(mixed $entry): bool {
		if (is_array($entry) === false) {
			return false;
		}

		return isset($entry['label']) === true
			&& isset($entry['url']) === true
			&& is_string($entry['label']) === true
			&& is_string($entry['url']) === true;
	}//end isFooterLink()

	/**
	 * Resolve the effective footer payload for a single dashboard
	 * (REQ-FTR-004, REQ-FTR-006). Returns NULL when no footer should
	 * render, or an associative array suitable for serialising as
	 * the dashboard API response's `effectiveFooter` field.
	 *
	 * Resolution order (matches DashboardService.resolveFooterForDashboard
	 * tasks 6.5):
	 *  - dashboard mode = `hidden` → NULL.
	 *  - dashboard mode = `custom` → render the dashboard HTML.
	 *  - dashboard mode = `inherit` → check global `footerEnabled`;
	 *    if false → NULL; otherwise render the global footer HTML or
	 *    structured config.
	 *
	 * @param Dashboard $dashboard The dashboard whose footer to resolve.
	 *
	 * @return array<string, mixed>|null Effective footer payload keyed
	 *                                   by `mode`, `html`, `config`,
	 *                                   `backgroundColor`, `textColor`.
	 *
	 * @spec openspec/specs/footer-customization/spec.md
	 */
	public function resolveFooterForDashboard(Dashboard $dashboard): ?array {
		$mode = $this->effectiveFooterMode(dashboard: $dashboard);
		if ($mode === Dashboard::FOOTER_MODE_HIDDEN) {
			return null;
		}

		$globals = $this->getGlobalSettings();

		if ($mode === Dashboard::FOOTER_MODE_CUSTOM) {
			return $this->customFooter(dashboard: $dashboard, globals: $globals);
		}

		return $this->inheritedFooter(globals: $globals);
	}//end resolveFooterForDashboard()

	/**
	 * The dashboard's footer mode; an empty mode means inherit.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 *
	 * @return string The footer mode.
	 */
	private function effectiveFooterMode(Dashboard $dashboard): string {
		$mode = $dashboard->getDashboardFooterMode();
		if ($mode === '') {
			return Dashboard::FOOTER_MODE_INHERIT;
		}

		return $mode;
	}//end effectiveFooterMode()

	/**
	 * The payload for a dashboard with its own footer HTML; NULL when the
	 * HTML is blank.
	 *
	 * @param Dashboard            $dashboard The dashboard.
	 * @param array<string, mixed> $globals   The global footer settings.
	 *
	 * @return array<string, mixed>|null The effective footer payload.
	 */
	private function customFooter(Dashboard $dashboard, array $globals): ?array {
		$html = $dashboard->getDashboardFooterHtml();
		if ($html === null || trim(string: $html) === '') {
			return null;
		}

		return $this->withFooterColours(
			payload: ['mode' => Dashboard::FOOTER_MODE_CUSTOM, 'html' => $html, 'config' => null],
			globals: $globals
		);
	}//end customFooter()

	/**
	 * The payload for a dashboard that inherits the global footer; NULL
	 * when the global footer is off or has no content.
	 *
	 * @param array<string, mixed> $globals The global footer settings.
	 *
	 * @return array<string, mixed>|null The effective footer payload.
	 */
	private function inheritedFooter(array $globals): ?array {
		if ($globals['footerEnabled'] !== true) {
			return null;
		}

		$htmlValue = $this->nonEmptyFooterHtml(html: $globals['footerHtml']);
		$configValue = $this->nonEmptyFooterConfig(config: $globals['footerConfig']);
		if ($htmlValue === null && $configValue === null) {
			// Footer enabled but nothing configured — skip rendering.
			return null;
		}

		return $this->withFooterColours(
			payload: ['mode' => 'global', 'html' => $htmlValue, 'config' => $configValue],
			globals: $globals
		);
	}//end inheritedFooter()

	/**
	 * The global footer HTML when it is a non-empty string or a non-empty
	 * language-variant map (the frontend selects the locale), else NULL.
	 *
	 * @param mixed $html The global footer HTML.
	 *
	 * @return string|array<string, string>|null The HTML to render.
	 */
	private function nonEmptyFooterHtml(mixed $html): string|array|null {
		if (is_string($html) === true && $html !== '') {
			return $html;
		}

		if (is_array($html) === true && $html !== []) {
			return $html;
		}

		return null;
	}//end nonEmptyFooterHtml()

	/**
	 * The global structured config when it is a non-empty array, else NULL.
	 *
	 * @param mixed $config The global structured config.
	 *
	 * @return array<string, mixed>|null The config to render.
	 */
	private function nonEmptyFooterConfig(mixed $config): ?array {
		if (is_array($config) === true && $config !== []) {
			return $config;
		}

		return null;
	}//end nonEmptyFooterConfig()

	/**
	 * Append the global background and text colours to a footer payload.
	 *
	 * @param array<string, mixed> $payload The mode, html and config.
	 * @param array<string, mixed> $globals The global footer settings.
	 *
	 * @return array<string, mixed> The complete footer payload.
	 */
	private function withFooterColours(array $payload, array $globals): array {
		$payload['backgroundColor'] = $globals['footerBackgroundColor'];
		$payload['textColor'] = $globals['footerTextColor'];

		return $payload;
	}//end withFooterColours()

	/**
	 * Validate that a value is a `#rgb` / `#rrggbb` hex colour string
	 * (REQ-FTR-009). Throws on anything else.
	 *
	 * @param mixed $value The value to validate.
	 * @param string $fieldName The field name for the error message.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the value is not a hex string.
	 */
	private function assertHexColour(mixed $value, string $fieldName): void {
		if (is_string($value) === false
			|| preg_match(pattern: '/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', subject: $value) !== 1
		) {
			throw new InvalidArgumentException(
				message: $fieldName . ' must be a hex colour string (e.g. #1a1a1a)'
			);
		}
	}//end assertHexColour()
}//end class
