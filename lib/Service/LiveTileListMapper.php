<?php

/**
 * LaunchPad LiveTileListMapper.
 *
 * Turns what a live tile's source returned into a list, a table or key-value
 * pairs. The author maps up to six fields (a label, a path and a role:
 * title, link, date or field); only those fields leave the server, and a
 * list or table is cut to the tile's limit. A link that is not an https
 * address is dropped. A tile without a display keeps its single value.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-mapping-happens-on-the-server-and-drops-unmapped-data-req-ltc-002
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * Validates a tile's display, mapping and limit, and maps a source node onto them.
 *
 * @spec openspec/changes/integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-a-live-tile-can-show-a-list-a-table-or-key-value-pairs-req-ltc-001
 */
class LiveTileListMapper {

	/**
	 * The displays a tile can have; `value` is the single value of old tiles.
	 *
	 * @var array<int,string>
	 */
	public const DISPLAYS = ['value', 'list', 'table', 'keyvalue'];

	/**
	 * What a mapped field is for.
	 *
	 * @var array<int,string>
	 */
	public const ROLES = ['title', 'link', 'date', 'field'];

	/**
	 * The most fields a tile maps.
	 *
	 * @var integer
	 */
	public const MAX_FIELDS = 6;

	/**
	 * The rows a list or table shows when the tile names no limit.
	 *
	 * @var integer
	 */
	public const DEFAULT_LIMIT = 10;

	/**
	 * The most rows a list or table shows.
	 *
	 * @var integer
	 */
	public const MAX_LIMIT = 50;

	/**
	 * The display a config asks for; a config without one shows a single value.
	 *
	 * @param array<string,mixed> $config The tile config.
	 *
	 * @return string One of DISPLAYS.
	 *
	 * @spec openspec/changes/integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-a-live-tile-can-show-a-list-a-table-or-key-value-pairs-req-ltc-001
	 */
	public function displayOf(array $config): string {
		$display = ($config['display'] ?? 'value');
		if (is_string($display) === true && in_array($display, self::DISPLAYS, true) === true) {
			return $display;
		}

		return 'value';

	}//end displayOf()

	/**
	 * The errors in a config's display, mapping and limit. An old config has none.
	 *
	 * @param array<string,mixed> $config The tile config.
	 *
	 * @return array<int,string> Error codes.
	 *
	 * @spec openspec/changes/integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-a-live-tile-can-show-a-list-a-table-or-key-value-pairs-req-ltc-001
	 */
	public function configErrors(array $config): array {
		if (array_key_exists('display', $config) === true && $this->displayOf(config: $config) !== $config['display']) {
			return ['invalid_display'];
		}

		$errors = [];
		if (array_key_exists('limit', $config) === true && $this->isValidLimit(limit: $config['limit']) === false) {
			$errors[] = 'invalid_limit';
		}

		if ($this->displayOf(config: $config) === 'value') {
			return $errors;
		}

		$mapping = ($config['mapping'] ?? []);
		if (is_array($mapping) === false || $mapping === []) {
			$errors[] = 'mapping_required';
			return $errors;
		}

		if (count($mapping) > self::MAX_FIELDS || $this->allFieldsValid(mapping: $mapping) === false) {
			$errors[] = 'invalid_mapping';
		}

		return $errors;

	}//end configErrors()

	/**
	 * Map a source node onto the tile's display.
	 *
	 * @param mixed               $node    What the tile's expression selected.
	 * @param array<string,mixed> $config  The tile config, already valid.
	 * @param callable            $extract Evaluates a `$.path` expression on one item: fn (mixed $item, string $expr): mixed.
	 *
	 * @return array<string,mixed>|null `{items: [[{label, role, value}]]}` for a list or table,
	 *                                  `{pairs: [{label, role, value}]}` for key-value, or null when the node does not fit.
	 *
	 * @spec openspec/changes/integrations-live-tile-lists/specs/live-data-tile-widget/spec.md#requirement-mapping-happens-on-the-server-and-drops-unmapped-data-req-ltc-002
	 */
	public function map(mixed $node, array $config, callable $extract): ?array {
		$mapping = array_values((array)($config['mapping'] ?? []));
		if (is_array($node) === false) {
			return null;
		}

		if ($this->displayOf(config: $config) === 'keyvalue') {
			if (array_is_list($node) === true && $node !== []) {
				return null;
			}

			return ['pairs' => $this->mapItem(item: $node, mapping: $mapping, extract: $extract)];
		}

		if (array_is_list($node) === false) {
			return null;
		}

		$items = [];
		foreach (array_slice($node, 0, $this->limitOf(config: $config)) as $item) {
			$items[] = $this->mapItem(item: $item, mapping: $mapping, extract: $extract);
		}

		return ['items' => $items];

	}//end map()

	/**
	 * The rows a list or table shows.
	 *
	 * @param array<string,mixed> $config The tile config.
	 *
	 * @return integer
	 */
	private function limitOf(array $config): int {
		if ($this->isValidLimit(limit: ($config['limit'] ?? null)) === false) {
			return self::DEFAULT_LIMIT;
		}

		return (int)$config['limit'];

	}//end limitOf()

	/**
	 * Whether a limit is a whole number from 1 to MAX_LIMIT.
	 *
	 * @param mixed $limit The limit.
	 *
	 * @return boolean
	 */
	private function isValidLimit(mixed $limit): bool {
		if (is_int($limit) === false && (is_string($limit) === false || ctype_digit($limit) === false)) {
			return false;
		}

		return (int)$limit >= 1 && (int)$limit <= self::MAX_LIMIT;

	}//end isValidLimit()

	/**
	 * Whether every mapped field has a string label, a path and a known role.
	 *
	 * @param array<mixed> $mapping The mapping.
	 *
	 * @return boolean
	 */
	private function allFieldsValid(array $mapping): bool {
		foreach ($mapping as $field) {
			if (is_array($field) === false
				|| is_string($field['label'] ?? null) === false
				|| $this->expressionOf(path: ($field['path'] ?? null)) === null
				|| in_array(($field['role'] ?? null), self::ROLES, true) === false
			) {
				return false;
			}
		}

		return true;

	}//end allFieldsValid()

	/**
	 * A mapping path as a `$.` expression: `name`, `.name`, `$.name` and `$name` all read `name`.
	 *
	 * @param mixed $path The mapped path.
	 *
	 * @return string|null The expression, or null when the path is not a usable string.
	 */
	private function expressionOf(mixed $path): ?string {
		if (is_string($path) === false) {
			return null;
		}

		$path = ltrim(trim($path), '$');
		if ($path === '' || preg_match('/^(?:\.?[A-Za-z0-9_]+|\[\d+\])(?:\.[A-Za-z0-9_]+|\[\d+\])*$/', $path) !== 1) {
			return null;
		}

		if ($path[0] !== '.' && $path[0] !== '[') {
			$path = '.'.$path;
		}

		return '$'.$path;

	}//end expressionOf()

	/**
	 * One item's mapped fields, in mapping order.
	 *
	 * @param mixed                            $item    One item of the source.
	 * @param array<int,array<string,mixed>>   $mapping The mapping.
	 * @param callable                         $extract The path evaluator.
	 *
	 * @return array<int,array{label: string, role: string, value: string|int|float|bool|null}>
	 */
	private function mapItem(mixed $item, array $mapping, callable $extract): array {
		$fields = [];
		foreach ($mapping as $field) {
			$expression = $this->expressionOf(path: ($field['path'] ?? null));
			$value = null;
			if ($expression !== null) {
				$value = $this->scalar(value: $extract($item, $expression));
			}

			if (($field['role'] ?? '') === 'link' && $this->isHttps(value: $value) === false) {
				$value = null;
			}

			$fields[] = ['label' => (string)($field['label'] ?? ''), 'role' => (string)($field['role'] ?? 'field'), 'value' => $value];
		}

		return $fields;

	}//end mapItem()

	/**
	 * A value a tile can show: a scalar, or null for anything nested.
	 *
	 * @param mixed $value The extracted value.
	 *
	 * @return string|integer|float|boolean|null
	 */
	private function scalar(mixed $value): string|int|float|bool|null {
		if (is_scalar($value) === true) {
			return $value;
		}

		return null;

	}//end scalar()

	/**
	 * Whether a value is an https address.
	 *
	 * @param mixed $value The mapped link.
	 *
	 * @return boolean
	 */
	private function isHttps(mixed $value): bool {
		return is_string($value) === true
			&& strtolower((string)parse_url($value, PHP_URL_SCHEME)) === 'https'
			&& (string)parse_url($value, PHP_URL_HOST) !== '';

	}//end isHttps()
}//end class
