<?php

/**
 * AttentionDeclarationValidator
 *
 * Checks one app's `appinfo/attention.json` against the attention feed
 * contract (REQ-ATT-001) and fills in the defaults. Pure: no state, no
 * dependencies, no I/O.
 *
 * Every refusal names the item and the field, because the reader is the
 * developer of ANOTHER app, looking at a list that does not show their item.
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
 * Check an attention declaration.
 *
 * @spec openspec/specs/attention-feed/spec.md#req-att-001
 */
class AttentionDeclarationValidator {
	/**
	 * The declaration version this service reads.
	 *
	 * @var int
	 */
	public const VERSION = 1;

	/**
	 * The most items one app may declare.
	 *
	 * @var int
	 */
	public const MAX_ITEMS = 10;

	/**
	 * Severities, most urgent first.
	 *
	 * @var array<int, string>
	 */
	public const SEVERITIES = ['error', 'warning', 'info'];

	/**
	 * Comparison operators an item may use.
	 *
	 * @var array<int, string>
	 */
	public const OPERATORS = ['gt', 'gte', 'lt', 'lte', 'eq', 'neq'];

	/**
	 * Check one declaration and return its items with defaults filled in.
	 *
	 * @param string $appId The declaring app.
	 * @param string $raw   The file's contents.
	 *
	 * @return array<int, array<string, mixed>> The checked items.
	 *
	 * @throws InvalidArgumentException With the reason, when the file is not valid.
	 *
	 * @spec openspec/specs/attention-feed/spec.md#req-att-001
	 */
	public function check(string $appId, string $raw): array {
		$items = $this->readItems(raw: $raw);

		$checked = [];
		foreach ($items as $index => $item) {
			$label = 'item ' . (string)$index;
			if (is_array($item) === false) {
				throw new InvalidArgumentException(message: $label . ' must be an object');
			}

			$id = $this->checkId(label: $label, id: ($item['id'] ?? null));
			if (isset($checked[$id]) === true) {
				throw new InvalidArgumentException(message: 'item "' . $id . '" is declared twice');
			}

			$checked[$id] = $this->checkItem(appId: $appId, id: $id, item: $item);
		}//end foreach

		return array_values(array: $checked);
	}//end check()

	/**
	 * Decode the file and return its list of items, unchecked.
	 *
	 * @param string $raw The file's contents.
	 *
	 * @return array<int, mixed> The items.
	 *
	 * @throws InvalidArgumentException With the reason.
	 */
	private function readItems(string $raw): array {
		$decoded = json_decode(json: $raw, associative: true);
		if (is_array($decoded) === false) {
			throw new InvalidArgumentException(message: 'attention.json is not valid JSON');
		}

		if (($decoded['version'] ?? null) !== self::VERSION) {
			throw new InvalidArgumentException(message: 'attention.json must say "version": ' . (string)self::VERSION);
		}

		$items = ($decoded['items'] ?? null);
		if (is_array($items) === false || array_is_list(array: $items) === false) {
			throw new InvalidArgumentException(message: '"items" must be a list');
		}

		if (count($items) > self::MAX_ITEMS) {
			throw new InvalidArgumentException(
				message: 'at most ' . (string)self::MAX_ITEMS . ' items, found ' . (string)count($items)
			);
		}

		return $items;
	}//end readItems()

	/**
	 * Check an item id.
	 *
	 * @param string $label How to name the item in an error.
	 * @param mixed  $id    The decoded `id`.
	 *
	 * @return string The id.
	 *
	 * @throws InvalidArgumentException With the reason.
	 */
	private function checkId(string $label, mixed $id): string {
		if (is_string($id) === false || preg_match(pattern: '/^[a-z0-9][a-z0-9-]{0,63}$/', subject: $id) !== 1) {
			throw new InvalidArgumentException(
				message: $label . ': "id" must be lower-case letters, digits and hyphens'
			);
		}

		return $id;
	}//end checkId()

	/**
	 * Check one item and fill in its defaults.
	 *
	 * @param string               $appId The declaring app.
	 * @param string               $id    The item id, already checked.
	 * @param array<string, mixed> $item  The decoded item.
	 *
	 * @return array<string, mixed> The item, with defaults.
	 *
	 * @throws InvalidArgumentException With the reason.
	 */
	private function checkItem(string $appId, string $id, array $item): array {
		$label = 'item "' . $id . '"';

		$texts = [
			'title' => ($item['title'] ?? null),
			'reason' => ($item['reason'] ?? null),
			'action.label' => ($item['action']['label'] ?? null),
		];
		foreach ($texts as $field => $text) {
			if (is_string($text) === false || trim(string: $text) === '') {
				throw new InvalidArgumentException(message: $label . ': "' . $field . '" must be a text');
			}
		}

		$severity = ($item['severity'] ?? 'info');
		if (in_array(needle: $severity, haystack: self::SEVERITIES, strict: true) === false) {
			throw new InvalidArgumentException(message: $label . ': "severity" must be error, warning or info');
		}

		$operator = ($item['op'] ?? 'gt');
		if (in_array(needle: $operator, haystack: self::OPERATORS, strict: true) === false) {
			throw new InvalidArgumentException(message: $label . ': "op" must be gt, gte, lt, lte, eq or neq');
		}

		$value = ($item['value'] ?? 0);
		if (is_int($value) === false && is_float($value) === false) {
			throw new InvalidArgumentException(message: $label . ': "value" must be a number');
		}

		return [
			'id' => $id,
			'title' => $texts['title'],
			'reason' => $texts['reason'],
			'severity' => $severity,
			'source' => $this->checkSource(label: $label, source: ($item['source'] ?? null)),
			'op' => $operator,
			'value' => $value,
			'action' => [
				'label' => $texts['action.label'],
				'path' => '/apps/' . $appId . $this->checkPath(label: $label, path: ($item['action']['path'] ?? null)),
			],
		];
	}//end checkItem()

	/**
	 * Check an item's count source.
	 *
	 * @param string $label  How to name the item in an error.
	 * @param mixed  $source The decoded `source`.
	 *
	 * @return array{register: string, schema: string, filter: array<string, mixed>}
	 *
	 * @throws InvalidArgumentException With the reason.
	 */
	private function checkSource(string $label, mixed $source): array {
		$slug = '/^[A-Za-z0-9][A-Za-z0-9_-]{0,127}$/';
		foreach (['register', 'schema'] as $field) {
			$name = ($source[$field] ?? null);
			if (is_string($name) === false || preg_match(pattern: $slug, subject: $name) !== 1) {
				throw new InvalidArgumentException(message: $label . ': "source.' . $field . '" must be a slug');
			}
		}

		$filter = ($source['filter'] ?? []);
		if (is_array($filter) === false || ($filter !== [] && array_is_list(array: $filter) === true)) {
			throw new InvalidArgumentException(message: $label . ': "source.filter" must be an object');
		}

		foreach ($filter as $key => $value) {
			// The count and the link are written from this one filter. A nested
			// operator ({"deadline": {"lt": ...}}) cannot be written into a
			// link, and it is the form the app lanes hit as a live defect.
			if ($this->isFlatValue(value: $value) === false) {
				throw new InvalidArgumentException(
					message: $label . ': filter "' . (string)$key
						. '" must be a text, number, boolean or a list of those.'
						. ' Write an operator in the key, as in "deadline[lt]"'
				);
			}
		}

		return ['register' => $source['register'], 'schema' => $source['schema'], 'filter' => $filter];
	}//end checkSource()

	/**
	 * Whether a filter value can be written into a query string as it is.
	 *
	 * @param mixed $value The filter value.
	 *
	 * @return bool True for a scalar, or a non-empty list of scalars.
	 */
	private function isFlatValue(mixed $value): bool {
		if (is_scalar($value) === true) {
			return true;
		}

		if (is_array($value) === false || $value === [] || array_is_list(array: $value) === false) {
			return false;
		}

		foreach ($value as $entry) {
			if (is_scalar($entry) === false) {
				return false;
			}
		}

		return true;
	}//end isFlatValue()

	/**
	 * Check that a link path stays inside the declaring app.
	 *
	 * @param string $label How to name the item in an error.
	 * @param mixed  $path  The decoded `action.path`.
	 *
	 * @return string The path.
	 *
	 * @throws InvalidArgumentException With the reason.
	 */
	private function checkPath(string $label, mixed $path): string {
		if (is_string($path) === false
			|| preg_match(pattern: '#^(/[A-Za-z0-9._~-]+)+/?$#', subject: $path) !== 1
			|| str_contains(haystack: $path, needle: '..') === true
		) {
			throw new InvalidArgumentException(
				message: $label . ': "action.path" must be a path inside the app, such as "/cases"'
			);
		}

		return $path;
	}//end checkPath()
}//end class
