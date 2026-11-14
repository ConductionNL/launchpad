<?php

/**
 * MapWidgetValidator
 *
 * Save-time validation of a `map` widget's content blob, against the shape
 * the shared library's CnMapWidget reads.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction b.v.
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use InvalidArgumentException;

/**
 * Refuses a `map` content blob CnMapWidget could not draw as configured.
 *
 * Only malformed shapes are refused. A map of layers only, or one fed by a
 * `url` source, is valid without a register or schema, so nothing here is
 * required; what is present must be well-formed.
 *
 * @spec openspec/changes/map-support/tasks.md
 */
class MapWidgetValidator {

	/**
	 * The widget types this validator applies to: `map` and its alias.
	 */
	public const WIDGET_TYPES = ['map', 'map-viewer'];

	/**
	 * The layer types CnMapWidget draws.
	 */
	public const LAYER_TYPES = ['tile', 'wms', 'wfs', 'geojson', 'image'];

	/**
	 * Whether a widget type is a map.
	 *
	 * @param string $widgetType The placement's widget type.
	 *
	 * @return bool True for `map` and `map-viewer`.
	 *
	 * @spec openspec/changes/map-support/tasks.md
	 */
	public function appliesTo(string $widgetType): bool {
		return in_array(needle: $widgetType, haystack: self::WIDGET_TYPES, strict: true);
	}//end appliesTo()

	/**
	 * Validate a map widget's content blob.
	 *
	 * @param array $content The widget content.
	 *
	 * @return void
	 * @throws InvalidArgumentException When a present field is malformed.
	 *
	 * @spec openspec/changes/map-support/tasks.md
	 */
	public function validate(array $content): void {
		$this->validatePair(owner: $content, label: 'Map');
		$this->validateMarkers(markers: $content['markers'] ?? null);
		$this->validateLayers(layers: $content['layers'] ?? null);
		$this->validateCenter(center: $content['center'] ?? null);
	}//end validate()

	/**
	 * A `markers` block is an object, and its data source is well-formed.
	 *
	 * @param mixed $markers The `markers` value, null when absent.
	 *
	 * @return void
	 * @throws InvalidArgumentException When markers or its data source is malformed.
	 *
	 * @spec openspec/changes/map-support/tasks.md
	 */
	private function validateMarkers(mixed $markers): void {
		if ($markers === null) {
			return;
		}

		$this->requireObject(value: $markers, message: 'Map markers must be an object');
		$this->validateDataSource(source: $markers['dataSource'] ?? null);
	}//end validateMarkers()

	/**
	 * A data source names a `url` or a register and schema, exactly one of the two.
	 *
	 * @param mixed $source The `markers.dataSource` value, null when absent.
	 *
	 * @return void
	 * @throws InvalidArgumentException When the source is not one of the two shapes.
	 *
	 * @spec openspec/changes/map-support/tasks.md
	 */
	private function validateDataSource(mixed $source): void {
		if ($source === null) {
			return;
		}

		$this->requireObject(value: $source, message: 'Map markers.dataSource must be an object');
		$this->validatePair(owner: $source, label: 'Map markers.dataSource');

		$hasUrl    = $this->filled(value: $source['url'] ?? null);
		$hasObject = $this->filled(value: $source['register'] ?? null);
		if ($hasUrl === $hasObject) {
			throw new InvalidArgumentException(
				message: 'Map markers.dataSource must name either a url or a register and schema'
			);
		}
	}//end validateDataSource()

	/**
	 * A register and a schema come together or not at all.
	 *
	 * @param array  $owner The block holding `register` and `schema`.
	 * @param string $label How the block is named in the refusal.
	 *
	 * @return void
	 * @throws InvalidArgumentException When only one half is given.
	 *
	 * @spec openspec/changes/map-support/tasks.md
	 */
	private function validatePair(array $owner, string $label): void {
		$register = $this->filled(value: $owner['register'] ?? null);
		$schema   = $this->filled(value: $owner['schema'] ?? null);
		if ($register !== $schema) {
			throw new InvalidArgumentException(
				message: $label.' needs both a register and a schema'
			);
		}
	}//end validatePair()

	/**
	 * Layers are a list of objects, each of a type CnMapWidget draws.
	 *
	 * @param mixed $layers The `layers` value, null when absent.
	 *
	 * @return void
	 * @throws InvalidArgumentException When the list or a layer is malformed.
	 *
	 * @spec openspec/changes/map-support/tasks.md
	 */
	private function validateLayers(mixed $layers): void {
		if ($layers === null) {
			return;
		}

		if (is_array($layers) === false || array_is_list($layers) === false) {
			throw new InvalidArgumentException(message: 'Map layers must be a list');
		}

		foreach ($layers as $layer) {
			$this->requireObject(value: $layer, message: 'Each map layer must be an object');
			if (in_array(needle: $layer['type'] ?? null, haystack: self::LAYER_TYPES, strict: true) === false) {
				throw new InvalidArgumentException(
					message: 'Map layer type must be one of: '.implode(separator: ', ', array: self::LAYER_TYPES)
				);
			}
		}
	}//end validateLayers()

	/**
	 * A centre is a `[lat, lng]` pair of numbers.
	 *
	 * @param mixed $center The `center` value, null when absent.
	 *
	 * @return void
	 * @throws InvalidArgumentException When it is not two numbers.
	 *
	 * @spec openspec/changes/map-support/tasks.md
	 */
	private function validateCenter(mixed $center): void {
		if ($center === null) {
			return;
		}

		$pair    = is_array($center) === true && array_is_list($center) === true && count($center) === 2;
		$numbers = $pair === true && is_numeric($center[0]) === true && is_numeric($center[1]) === true;
		if ($numbers === false) {
			throw new InvalidArgumentException(message: 'Map center must be [lat, lng]');
		}
	}//end validateCenter()

	/**
	 * Refuse a value that is not a JSON object (an associative array).
	 *
	 * @param mixed  $value   The value.
	 * @param string $message The refusal.
	 *
	 * @return void
	 * @throws InvalidArgumentException When the value is not an object.
	 *
	 * @spec openspec/changes/map-support/tasks.md
	 */
	private function requireObject(mixed $value, string $message): void {
		if (is_array($value) === false || ($value !== [] && array_is_list($value) === true)) {
			throw new InvalidArgumentException(message: $message);
		}
	}//end requireObject()

	/**
	 * Whether a value is a non-empty string.
	 *
	 * @param mixed $value The value.
	 *
	 * @return bool True for a string with content.
	 *
	 * @spec openspec/changes/map-support/tasks.md
	 */
	private function filled(mixed $value): bool {
		return is_string($value) === true && trim($value) !== '';
	}//end filled()
}//end class
