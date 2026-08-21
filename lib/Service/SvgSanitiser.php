<?php

/**
 * SvgSanitiser
 *
 * DOM-based whitelist sanitiser for uploaded SVG bytes. Parses input
 * via `DOMDocument::loadXML($bytes, LIBXML_NONET | LIBXML_NOENT)` so
 * the parser cannot fetch external entities or DTDs (XXE protection),
 * walks the resulting tree, and strips:
 *
 * - any element whose lowercased localName is not in `ALLOWED_ELEMENTS`
 *   (24 element types — geometry + structure + decoration);
 * - any attribute whose lowercased name is not in `ALLOWED_ATTRIBUTES`
 *   (50 attribute types — geometry, styling, transform, gradient, href);
 * - ALL `on*` attributes regardless of whitelist (defence in depth);
 * - `href` / `xlink:href` values starting with `javascript:` or `data:`
 *   (after trim + lowercase);
 * - `style` attribute values containing `expression(`, `javascript:`,
 *   or `url(data:`.
 *
 * Returns `null` on parse failure or an empty serialised result.
 *
 * The whitelist is intentionally conservative — adding a new element
 * or attribute is a deliberate code change with a security review
 * checkbox. See REQ-RES-010 / REQ-RES-011 in the resource-uploads
 * capability and the note in CONTRIBUTING.md.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Whitelist-based SVG sanitiser. No Nextcloud dependencies — pure PHP.
 */
class SvgSanitiser {

	/**
	 * Allowed element local-names (lowercase). Anything not in this
	 * list is removed from the tree along with its children. See
	 * REQ-RES-010 in the resource-uploads capability.
	 *
	 * @var array<int,string>
	 */
	private const ALLOWED_ELEMENTS = [
		'svg',
		'g',
		'path',
		'rect',
		'circle',
		'ellipse',
		'line',
		'polyline',
		'polygon',
		'text',
		'tspan',
		'defs',
		'clippath',
		'use',
		'image',
		'style',
		'lineargradient',
		'radialgradient',
		'stop',
		'mask',
		'pattern',
		'symbol',
		'title',
		'desc',
	];

	/**
	 * Allowed attribute names (lowercase). Anything not in this list
	 * is removed from each element regardless of element name. See
	 * REQ-RES-011 in the resource-uploads capability.
	 *
	 * @var array<int,string>
	 */
	private const ALLOWED_ATTRIBUTES = [
		'id',
		'class',
		'style',
		'd',
		'x',
		'y',
		'x1',
		'y1',
		'x2',
		'y2',
		'cx',
		'cy',
		'r',
		'rx',
		'ry',
		'width',
		'height',
		'viewbox',
		'fill',
		'stroke',
		'stroke-width',
		'stroke-linecap',
		'stroke-linejoin',
		'stroke-dasharray',
		'stroke-dashoffset',
		'stroke-opacity',
		'fill-opacity',
		'opacity',
		'transform',
		'points',
		'font-size',
		'font-family',
		'font-weight',
		'text-anchor',
		'dominant-baseline',
		'dx',
		'dy',
		'clip-path',
		'mask',
		'filter',
		'gradientunits',
		'gradienttransform',
		'offset',
		'stop-color',
		'stop-opacity',
		'patternunits',
		'preserveaspectratio',
		'xmlns',
		'xmlns:xlink',
		'version',
		'href',
		'xlink:href',
	];

	/**
	 * Sanitise SVG bytes; return the sanitised serialisation or `null`
	 * when the input is unparseable / fully stripped to an empty tree.
	 *
	 * Parse flags `LIBXML_NONET | LIBXML_NOENT` ensure the libxml
	 * parser cannot fetch external DTDs / entities (XXE) and that
	 * entity expansion is bounded by libxml's internal limits
	 * (defends against billion-laughs).
	 *
	 * @param string $bytes The raw uploaded SVG bytes.
	 *
	 * @return string|null The sanitised SVG, or null when unparseable
	 *                     or sanitised to an empty result.
	 *
	 * @spec openspec/specs/resource-uploads/spec.md
	 */
	public function sanitize(string $bytes): ?string {
		if ($bytes === '') {
			return null;
		}

		$previousErrors = libxml_use_internal_errors(use_errors: true);

		$document = new DOMDocument();
		$document->preserveWhiteSpace = false;
		$document->formatOutput = false;

		// C2: LIBXML_NOENT removed — it resolves (not disables) entities,
		// enabling XXE. LIBXML_NONET blocks external DTD/entity fetches.
		$loaded = $document->loadXML(
			source: $bytes,
			options: LIBXML_NONET
		);

		libxml_clear_errors();
		libxml_use_internal_errors(use_errors: $previousErrors);

		if ($loaded === false) {
			return null;
		}

		$root = $document->documentElement;
		if ($root === null) {
			return null;
		}

		// Validate the root element itself — reject if it is not in
		// the whitelist (the recursive walker only inspects children).
		if (in_array(
			needle: strtolower(string: $root->localName),
			haystack: self::ALLOWED_ELEMENTS,
			strict: true
		) === false
		) {
			return null;
		}

		$this->cleanElement(element: $root);
		$this->walkChildren(node: $root);

		$serialised = $document->saveXML($root);
		if ($serialised === false || $serialised === '') {
			return null;
		}

		return $serialised;
	}//end sanitize()

	/**
	 * Recursively walk children of $node, removing disallowed elements
	 * and cleaning the attributes of allowed ones. Snapshots the child
	 * list before mutation so removals during iteration are safe.
	 *
	 * @param DOMNode $node The parent node whose children to walk.
	 *
	 * @return void
	 */
	private function walkChildren(DOMNode $node): void {
		$children = [];
		foreach ($node->childNodes as $child) {
			$children[] = $child;
		}

		foreach ($children as $child) {
			if ($child instanceof DOMElement === false) {
				continue;
			}

			$localName = strtolower(string: $child->localName);
			if (in_array(
				needle: $localName,
				haystack: self::ALLOWED_ELEMENTS,
				strict: true
			) === false
			) {
				$node->removeChild(child: $child);
				continue;
			}

			$this->cleanElement(element: $child);
			$this->walkChildren(node: $child);
		}
	}//end walkChildren()

	/**
	 * Strip every disallowed attribute from $element, plus any `on*`
	 * attribute (defence in depth) and any `href` / `xlink:href` /
	 * `style` whose value is on the URL or CSS denylist.
	 *
	 * @param DOMElement $element The element whose attributes to clean.
	 *
	 * @return void
	 */
	private function cleanElement(DOMElement $element): void {
		if ($element->hasAttributes() === false) {
			return;
		}

		// No instanceof DOMAttr filter: DOMElement::$attributes is a
		// DOMNamedNodeMap of DOMAttr, so the check is always true (PHPStan 2:
		// instanceof.alwaysTrue). Kept as a copy into a plain array because the
		// caller mutates attributes while iterating, and mutating a live
		// DOMNamedNodeMap mid-loop skips nodes.
		$attributes = [];
		foreach ($element->attributes as $attribute) {
			$attributes[] = $attribute;
		}

		foreach ($attributes as $attribute) {
			$lowerName = strtolower(string: $attribute->nodeName);

			if ($this->isDisallowedAttribute(
				lowerName: $lowerName,
				value: $attribute->value
			) === true
			) {
				$element->removeAttributeNode(attr: $attribute);
			}
		}//end foreach
	}//end cleanElement()

	/**
	 * Decide whether a single attribute must be stripped.
	 *
	 * The checks are evaluated in strict order and the FIRST match wins —
	 * this ordering is load-bearing for REQ-RES-011 / REQ-RES-012 and must
	 * not be rearranged:
	 *
	 * 1. Any `on*` attribute is removed regardless of the whitelist
	 *    (defence in depth).
	 * 2. Anything absent from {@see self::ALLOWED_ATTRIBUTES} is removed.
	 * 3. A whitelisted `href` / `xlink:href` is removed when its value is
	 *    on the URL denylist.
	 * 4. A whitelisted `style` is removed when its value is on the CSS
	 *    denylist.
	 *
	 * Anything reaching the end is whitelisted and carries no dangerous
	 * payload, so it is kept.
	 *
	 * @param string $lowerName The lower-cased attribute name.
	 * @param string $value The raw attribute value.
	 *
	 * @return boolean True when the attribute must be removed.
	 */
	private function isDisallowedAttribute(string $lowerName, string $value): bool {
		// Defence in depth — strip every `on*` attribute regardless
		// of whitelist (REQ-RES-011).
		if (str_starts_with(haystack: $lowerName, needle: 'on') === true) {
			return true;
		}

		if (in_array(
			needle: $lowerName,
			haystack: self::ALLOWED_ATTRIBUTES,
			strict: true
		) === false
		) {
			return true;
		}

		if ($lowerName === 'href' || $lowerName === 'xlink:href') {
			return $this->isDangerousUrl(value: $value);
		}

		if ($lowerName === 'style') {
			return $this->isDangerousStyle(value: $value);
		}

		return false;
	}//end isDisallowedAttribute()

	/**
	 * Whether the supplied URL value is on the denylist. Trims +
	 * lowercases before comparing; matches `javascript:` and `data:`
	 * prefixes (REQ-RES-012).
	 *
	 * @param string $value The raw attribute value.
	 *
	 * @return boolean True when the value should be rejected.
	 */
	private function isDangerousUrl(string $value): bool {
		$normalised = strtolower(string: trim(string: $value));
		if (str_starts_with(haystack: $normalised, needle: 'javascript:') === true) {
			return true;
		}

		if (str_starts_with(haystack: $normalised, needle: 'data:') === true) {
			return true;
		}

		return false;
	}//end isDangerousUrl()

	/**
	 * Whether the supplied style value contains a forbidden CSS
	 * construct: `expression(`, `javascript:`, or `url(data:`. Match
	 * is case-insensitive with optional whitespace per REQ-RES-012.
	 *
	 * @param string $value The raw style attribute value.
	 *
	 * @return boolean True when the style attribute should be removed.
	 */
	private function isDangerousStyle(string $value): bool {
		$pattern = '/expression\s*\(|javascript\s*:|url\s*\(\s*["\']?\s*data\s*:/i';
		return (preg_match(pattern: $pattern, subject: $value) === 1);
	}//end isDangerousStyle()
}//end class
