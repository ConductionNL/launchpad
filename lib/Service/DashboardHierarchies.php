<?php

/**
 * DashboardHierarchies
 *
 * Validation of the hierarchy descriptors a dashboard stores, so the filter
 * bus of every viewer can infer ancestors and clear descendants on them.
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
 *
 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;

/**
 * The hierarchy descriptors of a dashboard: `{id, levels: [{dimension, label, parentDimension}]}`.
 *
 * Levels run from the broadest to the finest. Each level's parent is the
 * level above it, so `parentDimension` is derived; a given one must match.
 * A dimension belongs to one level of one hierarchy only, because the bus
 * resolves a clause on the first level that carries its dimension.
 *
 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
 */
class DashboardHierarchies {

	/**
	 * The most hierarchies one dashboard stores.
	 */
	public const MAX_HIERARCHIES = 8;

	/**
	 * The most levels one hierarchy has.
	 */
	public const MAX_LEVELS = 8;

	/**
	 * The longest level label.
	 */
	public const MAX_LABEL_LENGTH = 128;

	/**
	 * A hierarchy id: lowercase letters, digits and dashes.
	 */
	private const ID_PATTERN = '/^[a-z0-9][a-z0-9-]{0,63}$/';

	/**
	 * A dimension: a letter, then letters, digits, dots and underscores.
	 */
	private const DIMENSION_PATTERN = '/^[A-Za-z][A-Za-z0-9_.]{0,63}$/';

	/**
	 * The descriptors as given, normalised, or the refusal.
	 *
	 * Null and the empty list both mean "no hierarchies".
	 *
	 * @param mixed $value The `hierarchies` of an update.
	 *
	 * @return list<array{id: string, levels: list<array{dimension: string, label: string, parentDimension: string|null}>}>
	 *
	 * @throws InvalidArgumentException When the value is not a valid list of descriptors.
	 *
	 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
	 */
	public static function validated(mixed $value): array {
		if ($value === null) {
			return [];
		}

		if (is_array($value) === false || array_is_list($value) === false) {
			throw new InvalidArgumentException(message: 'hierarchies must be a list');
		}

		if (count($value) > self::MAX_HIERARCHIES) {
			throw new InvalidArgumentException(message: 'hierarchies holds at most '.self::MAX_HIERARCHIES.' entries');
		}

		$result     = [];
		$ids        = [];
		$dimensions = [];
		foreach ($value as $index => $entry) {
			$hierarchy = self::hierarchy(entry: $entry, index: $index);
			if (in_array($hierarchy['id'], $ids, true) === true) {
				throw new InvalidArgumentException(message: 'hierarchies['.$index.'].id '.$hierarchy['id'].' is used twice');
			}

			foreach ($hierarchy['levels'] as $level) {
				if (in_array($level['dimension'], $dimensions, true) === true) {
					throw new InvalidArgumentException(
						message: 'hierarchies['.$index.'] repeats dimension '.$level['dimension']
					);
				}

				$dimensions[] = $level['dimension'];
			}

			$ids[]    = $hierarchy['id'];
			$result[] = $hierarchy;
		}//end foreach

		return $result;
	}//end validated()

	/**
	 * One descriptor, normalised.
	 *
	 * @param mixed $entry The descriptor.
	 * @param int   $index Its position, for the refusal message.
	 *
	 * @return array{id: string, levels: list<array{dimension: string, label: string, parentDimension: string|null}>}
	 *
	 * @throws InvalidArgumentException When the descriptor is malformed.
	 *
	 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
	 */
	private static function hierarchy(mixed $entry, int $index): array {
		$path = 'hierarchies['.$index.']';
		if (is_array($entry) === false) {
			throw new InvalidArgumentException(message: $path.' must be an object');
		}

		$hierarchyId = $entry['id'] ?? null;
		if (is_string($hierarchyId) === false || preg_match(self::ID_PATTERN, $hierarchyId) !== 1) {
			throw new InvalidArgumentException(message: $path.'.id must be lowercase letters, digits and dashes');
		}

		$levels = $entry['levels'] ?? null;
		if (is_array($levels) === false || array_is_list($levels) === false || $levels === []) {
			throw new InvalidArgumentException(message: $path.'.levels must be a non-empty list');
		}

		if (count($levels) > self::MAX_LEVELS) {
			throw new InvalidArgumentException(message: $path.'.levels holds at most '.self::MAX_LEVELS.' levels');
		}

		$normalised = [];
		$parent     = null;
		foreach ($levels as $position => $level) {
			$normalised[] = self::level(level: $level, path: $path.'.levels['.$position.']', parent: $parent);
			$parent       = $normalised[array_key_last($normalised)]['dimension'];
		}

		return ['id' => $hierarchyId, 'levels' => $normalised];
	}//end hierarchy()

	/**
	 * One level, normalised, with its parent set to the level above.
	 *
	 * @param mixed       $level  The level.
	 * @param string      $path   Its path, for the refusal message.
	 * @param string|null $parent The dimension of the level above, or null for the first level.
	 *
	 * @return array{dimension: string, label: string, parentDimension: string|null}
	 *
	 * @throws InvalidArgumentException When the level is malformed.
	 *
	 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
	 */
	private static function level(mixed $level, string $path, ?string $parent): array {
		if (is_array($level) === false) {
			throw new InvalidArgumentException(message: $path.' must be an object');
		}

		$dimension = $level['dimension'] ?? null;
		if (is_string($dimension) === false || preg_match(self::DIMENSION_PATTERN, $dimension) !== 1) {
			throw new InvalidArgumentException(message: $path.'.dimension must be a field name');
		}

		$label = $level['label'] ?? '';
		if (is_string($label) === false || mb_strlen($label) > self::MAX_LABEL_LENGTH) {
			throw new InvalidArgumentException(
				message: $path.'.label must be text of at most '.self::MAX_LABEL_LENGTH.' characters'
			);
		}

		$given = $level['parentDimension'] ?? null;
		if ($given !== null && $given !== $parent) {
			throw new InvalidArgumentException(message: $path.'.parentDimension must be the dimension of the level above');
		}

		return ['dimension' => $dimension, 'label' => trim($label), 'parentDimension' => $parent];
	}//end level()
}//end class
