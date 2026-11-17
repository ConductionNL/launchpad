<?php

/**
 * QueryUrlTemplate
 *
 * The shape check for an address template with a `{query}` placeholder,
 * shared by the quick-search web fallback (REQ-QSEARCH-004) and the search
 * shortcuts (REQ-SPX-001).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * Validates `https` address templates that contain `{query}`.
 *
 * @spec openspec/specs/tile-quick-search/spec.md
 */
final class QueryUrlTemplate {
	/**
	 * Validate the `https` + `{query}`-placeholder shape of an address
	 * template. Substitutes a harmless probe string for `{query}` before
	 * validating so a template like `https://example.org/search?q={query}`
	 * — not a valid URL as written — validates correctly.
	 *
	 * @param string $value the candidate URL template.
	 *
	 * @return bool true when `$value` is a valid `https` URL template
	 *              containing `{query}`.
	 *
	 * @spec openspec/specs/tile-quick-search/spec.md
	 */
	public static function isValid(string $value): bool {
		if (trim($value) === '' || str_contains(haystack: $value, needle: '{query}') === false) {
			return false;
		}

		$probe = str_replace(search: '{query}', replace: 'quicksearch-validation-probe', subject: $value);

		if (parse_url(url: $probe, component: PHP_URL_SCHEME) !== 'https') {
			return false;
		}

		return filter_var(value: $probe, filter: FILTER_VALIDATE_URL) !== false;
	}//end isValid()
}//end class
