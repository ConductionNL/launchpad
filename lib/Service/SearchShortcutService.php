<?php

/**
 * SearchShortcutService
 *
 * The administrator's search shortcuts (search-ai-prefix-shortcuts,
 * REQ-QSP-001): a prefix such as `!t`, a name and an https address with
 * `{query}`. A query in a LaunchPad search box that starts with a known
 * prefix goes straight to that site; the query never passes through the
 * LaunchPad server.
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

use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSettingKey;
use OCA\LaunchPad\Db\AdminSettingMapper;

/**
 * Read and validate the search shortcut list.
 *
 * @spec openspec/specs/tile-quick-search/spec.md
 */
class SearchShortcutService {
	/**
	 * Most shortcuts one instance may define.
	 *
	 * @var int
	 */
	public const MAX_SHORTCUTS = 30;

	/**
	 * A prefix: `!` and 1 to 10 letters or digits.
	 *
	 * @var string
	 */
	public const PREFIX_PATTERN = '/^![a-z0-9]{1,10}$/';

	/**
	 * Constructor.
	 *
	 * @param AdminSettingMapper $settingMapper Admin settings store.
	 * @param AdminSettingsService $adminSettings Holds the https `{query}` template check.
	 *
	 * @spec openspec/specs/tile-quick-search/spec.md
	 */
	public function __construct(
		private readonly AdminSettingMapper $settingMapper,
		private readonly AdminSettingsService $adminSettings,
	) {
	}//end __construct()

	/**
	 * The stored shortcuts. A stored list that no longer validates reads as
	 * empty, so the page never receives an address it must not open.
	 *
	 * @return array<int, array{prefix: string, name: string, urlTemplate: string}>
	 *
	 * @spec openspec/specs/tile-quick-search/spec.md
	 */
	public function getShortcuts(): array {
		$raw = $this->settingMapper->getValue(key: AdminSettingKey::SEARCH_SHORTCUTS->value, default: []);
		if (is_array(value: $raw) === false) {
			return [];
		}

		try {
			return $this->validate(raw: $raw);
		} catch (InvalidArgumentException) {
			return [];
		}
	}//end getShortcuts()

	/**
	 * Validate and store the shortcuts.
	 *
	 * @param array $raw Shortcuts as sent by the admin screen.
	 *
	 * @return array<int, array{prefix: string, name: string, urlTemplate: string}> The stored list.
	 *
	 * @throws InvalidArgumentException When an entry is invalid.
	 *
	 * @spec openspec/specs/tile-quick-search/spec.md
	 */
	public function saveShortcuts(array $raw): array {
		$shortcuts = $this->validate(raw: $raw);
		$this->settingMapper->setSetting(key: AdminSettingKey::SEARCH_SHORTCUTS->value, value: $shortcuts);

		return $shortcuts;
	}//end saveShortcuts()

	/**
	 * Check and normalise the list.
	 *
	 * @param array $raw Raw entries.
	 *
	 * @return array<int, array{prefix: string, name: string, urlTemplate: string}>
	 *
	 * @throws InvalidArgumentException When an entry is invalid.
	 */
	private function validate(array $raw): array {
		if (count(value: $raw) > self::MAX_SHORTCUTS) {
			throw new InvalidArgumentException(message: 'At most ' . self::MAX_SHORTCUTS . ' search shortcuts');
		}

		$result = [];
		$seen = [];
		foreach (array_values(array: $raw) as $entry) {
			if (is_array(value: $entry) === false) {
				throw new InvalidArgumentException(message: 'A search shortcut must be an object');
			}

			$prefix = mb_strtolower(string: trim(string: (string)($entry['prefix'] ?? '')));
			if (preg_match(pattern: self::PREFIX_PATTERN, subject: $prefix) !== 1) {
				throw new InvalidArgumentException(message: 'A prefix is ! followed by 1 to 10 letters or digits: ' . $prefix);
			}

			if (isset($seen[$prefix]) === true) {
				throw new InvalidArgumentException(message: 'Prefix used twice: ' . $prefix);
			}

			$name = trim(string: (string)($entry['name'] ?? ''));
			if ($name === '' || mb_strlen(string: $name) > 64) {
				throw new InvalidArgumentException(message: 'A shortcut name must be 1 to 64 characters: ' . $prefix);
			}

			$template = trim(string: (string)($entry['urlTemplate'] ?? ''));
			if ($this->adminSettings->isValidQuicksearchFallbackUrlTemplate(value: $template) === false) {
				throw new InvalidArgumentException(message: 'The address must be an https address containing {query}: ' . $prefix);
			}

			$seen[$prefix] = true;
			$result[] = ['prefix' => $prefix, 'name' => $name, 'urlTemplate' => $template];
		}//end foreach

		return $result;
	}//end validate()
}//end class
