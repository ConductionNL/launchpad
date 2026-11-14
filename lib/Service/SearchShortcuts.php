<?php

/**
 * SearchShortcuts
 *
 * The admin list of quick-search prefix shortcuts (search-ai-prefix-shortcuts
 * design D1): up to 30 entries `{prefix, name, urlTemplate}`, where a prefix
 * is `!` and 1 to 10 letters or digits, unique without regard to case, and
 * each template is an `https` URL holding `{query}`, the same rule as the
 * quick-search fallback template (REQ-QSEARCH-004).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
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

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;

/**
 * Validates and normalises the search shortcut list.
 *
 * @spec openspec/changes/search-ai-prefix-shortcuts/specs/tile-quick-search/spec.md#requirement-administrators-define-search-shortcuts-req-spx-001
 */
class SearchShortcuts {

	/**
	 * The most shortcuts the list holds.
	 *
	 * @var int
	 */
	public const MAX_SHORTCUTS = 30;

	/**
	 * A prefix: `!` followed by 1 to 10 letters or digits.
	 *
	 * @var string
	 */
	public const PREFIX_PATTERN = '/^![A-Za-z0-9]{1,10}$/';

	/**
	 * The longest name a shortcut shows.
	 *
	 * @var int
	 */
	private const MAX_NAME_LENGTH = 60;

	/**
	 * The list as given, validated: each entry trimmed to `{prefix, name, urlTemplate}`.
	 *
	 * @param mixed $value The list to store.
	 *
	 * @return array<int, array{prefix: string, name: string, urlTemplate: string}> The normalised list.
	 *
	 * @throws InvalidArgumentException Naming the first problem: not a list, too many, a bad
	 *                                  prefix, a repeated prefix, an empty name, or a bad template.
	 *
	 * @spec openspec/changes/search-ai-prefix-shortcuts/specs/tile-quick-search/spec.md#requirement-administrators-define-search-shortcuts-req-spx-001
	 */
	public function normalise(mixed $value): array {
		if (is_array($value) === false || array_is_list($value) === false) {
			throw new InvalidArgumentException(message: 'Search shortcuts must be a list.');
		}

		if (count($value) > self::MAX_SHORTCUTS) {
			throw new InvalidArgumentException(message: 'At most ' . self::MAX_SHORTCUTS . ' search shortcuts are allowed.');
		}

		$shortcuts = [];
		$seen = [];
		foreach ($value as $entry) {
			$shortcut = $this->entry(entry: $entry);
			$key = strtolower($shortcut['prefix']);
			if (isset($seen[$key]) === true) {
				throw new InvalidArgumentException(message: 'The search shortcut prefix ' . $shortcut['prefix'] . ' is used twice.');
			}

			$seen[$key] = true;
			$shortcuts[] = $shortcut;
		}

		return $shortcuts;
	}//end normalise()

	/**
	 * The stored list for reading: the valid list, or an empty one when a
	 * stored value no longer validates (an edited database row), so the page
	 * never gets a shortcut it cannot safely open.
	 *
	 * @param mixed $stored The stored value.
	 *
	 * @return array<int, array{prefix: string, name: string, urlTemplate: string}> The list.
	 *
	 * @spec openspec/changes/search-ai-prefix-shortcuts/specs/tile-quick-search/spec.md#requirement-administrators-define-search-shortcuts-req-spx-001
	 */
	public function read(mixed $stored): array {
		if ($stored === null) {
			return [];
		}

		try {
			return $this->normalise(value: $stored);
		} catch (InvalidArgumentException) {
			return [];
		}
	}//end read()

	/**
	 * Whether a URL template is `https` and holds `{query}`.
	 *
	 * The placeholder is replaced by a probe first, so a template such as
	 * `https://example.org/search?q={query}` (not a valid URL as written)
	 * validates.
	 *
	 * @param string $template The template.
	 *
	 * @return bool True when it may be opened.
	 *
	 * @spec openspec/specs/tile-quick-search/spec.md
	 */
	public function isValidUrlTemplate(string $template): bool {
		if (trim($template) === '' || str_contains(haystack: $template, needle: '{query}') === false) {
			return false;
		}

		$probe = str_replace(search: '{query}', replace: 'quicksearch-validation-probe', subject: $template);
		if (parse_url(url: $probe, component: PHP_URL_SCHEME) !== 'https') {
			return false;
		}

		return filter_var(value: $probe, filter: FILTER_VALIDATE_URL) !== false;
	}//end isValidUrlTemplate()

	/**
	 * One entry, validated and trimmed.
	 *
	 * @param mixed $entry The entry.
	 *
	 * @return array{prefix: string, name: string, urlTemplate: string}
	 *
	 * @throws InvalidArgumentException Naming what is wrong with it.
	 */
	private function entry(mixed $entry): array {
		if (is_array($entry) === false) {
			throw new InvalidArgumentException(message: 'Each search shortcut must be an object with a prefix, a name and a URL template.');
		}

		$prefix = trim((string)($entry['prefix'] ?? ''));
		$name = trim((string)($entry['name'] ?? ''));
		$template = trim((string)($entry['urlTemplate'] ?? ''));

		if (preg_match(self::PREFIX_PATTERN, $prefix) !== 1) {
			throw new InvalidArgumentException(message: 'A search shortcut prefix is ! followed by 1 to 10 letters or digits.');
		}

		if ($name === '' || mb_strlen($name) > self::MAX_NAME_LENGTH) {
			throw new InvalidArgumentException(
				message: 'The search shortcut ' . $prefix . ' needs a name of at most ' . self::MAX_NAME_LENGTH . ' characters.'
			);
		}

		if ($this->isValidUrlTemplate(template: $template) === false) {
			throw new InvalidArgumentException(message: 'Use an https address that contains {query} for the search shortcut ' . $prefix . '.');
		}

		return ['prefix' => $prefix, 'name' => $name, 'urlTemplate' => $template];
	}//end entry()
}//end class
