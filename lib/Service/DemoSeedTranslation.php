<?php

/**
 * DemoSeedTranslation
 *
 * A demo seed in another language: `data/demo-seed/<seed>.<lang>.json` maps
 * each text of the seed to its translation, and this class swaps them in.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 * @spec      openspec/specs/demo-data-showcases/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * Translate the texts of a demo seed definition.
 *
 * @spec openspec/specs/demo-data-showcases/spec.md
 */
final class DemoSeedTranslation {
	/**
	 * Keys whose value is an identifier, a link, a filter or data that other
	 * apps match on. Their values, and everything under them, stay as written.
	 *
	 * @var array<int, string>
	 */
	public const KEEP_KEYS = [
		'_comment', 'seedId', 'seedVersion', 'walkthroughSeenVersion', 'groups', 'users', 'files', 'appConfig',
		'uuid', 'key', 'board', 'widgetId', 'tile', 'icon', 'url', 'link', 'appId', 'owner', 'group', 'targetGroups',
		'author', 'shares', 'source', 'filter', 'status', 'values', 'with', 'internalCalendars', 'space', 'project',
		'scope', 'feedUrls', 'videoUrl', 'colors', 'location', 'timezone',
	];

	/**
	 * Replace every text of the seed that the map translates. A text is
	 * replaced only when its whole value is a key of the map.
	 *
	 * @param array<string, mixed>  $seed    The seed definition.
	 * @param array<string, string> $strings Each original text and its translation.
	 *
	 * @return array<string, mixed> The translated definition.
	 *
	 * @spec openspec/specs/demo-data-showcases/spec.md
	 */
	public function apply(array $seed, array $strings): array {
		if ($strings === []) {
			return $seed;
		}

		return $this->translateArray(values: $seed, strings: $strings);
	}//end apply()

	/**
	 * The language tags to try for a requested language, most specific first:
	 * `en_GB` tries `en_GB`, then `en`.
	 *
	 * @param string $language The requested language, '' for none.
	 *
	 * @return array<int, string> The candidate tags.
	 *
	 * @spec openspec/specs/demo-data-showcases/spec.md
	 */
	public function candidates(string $language): array {
		$language = str_replace(search: '-', replace: '_', subject: trim(string: $language));
		if (preg_match(pattern: '/^[A-Za-z]{2,3}(_[A-Za-z0-9]{2,8})?$/', subject: $language) !== 1) {
			return [];
		}

		$base = strtolower(string: explode(separator: '_', string: $language)[0]);
		return array_values(array: array_unique(array: [$language, $base]));
	}//end candidates()

	/**
	 * Translate one level of the definition.
	 *
	 * @param array<array-key, mixed> $values  The values.
	 * @param array<string, string>   $strings The translation map.
	 *
	 * @return array<array-key, mixed> The translated values.
	 */
	private function translateArray(array $values, array $strings): array {
		foreach ($values as $key => $value) {
			if (is_string(value: $key) === true && in_array(needle: $key, haystack: self::KEEP_KEYS, strict: true) === true) {
				continue;
			}

			if (is_array(value: $value) === true) {
				$values[$key] = $this->translateArray(values: $value, strings: $strings);
				continue;
			}

			if (is_string(value: $value) === true && isset($strings[$value]) === true) {
				$values[$key] = $strings[$value];
			}
		}

		return $values;
	}//end translateArray()
}//end class
