<?php

/**
 * MapContentValidator
 *
 * Server-side validator for the `map` widget content blob, against the
 * shape the shared map widget (CnMapWidget) reads.
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
 * Validates `map` widget content before a placement is saved.
 *
 * A map of `layers` only, or of inline or `url` markers, needs no register.
 * What is checked is what would draw a wrong or empty map without an error:
 * a register source missing its register or schema, a spatial filter that
 * does not read (OpenRegister would answer 422 and the map would stay
 * empty), a spatial filter without a register source to narrow, and a
 * detail link with an unsafe scheme.
 *
 * @spec openspec/changes/map-support/tasks.md#backend
 */
class MapContentValidator {

	/**
	 * Validate a map placement's content.
	 *
	 * @param array $content The placement content.
	 *
	 * @return void
	 * @throws InvalidArgumentException When the content would draw a wrong map.
	 *
	 * @spec openspec/changes/map-support/tasks.md#backend
	 */
	public function validate(array $content): void {
		$markers = ($content['markers'] ?? null);
		if (is_array($markers) === false) {
			return;
		}

		$this->checkDetailLink(link: ($markers['detailLink'] ?? null));
		$this->checkGeoProperty(property: ($markers['geoProperty'] ?? null));

		$source = ($markers['dataSource'] ?? null);
		if (is_array($source) === false) {
			return;
		}

		$this->checkRegisterSource(source: $source);
		if (isset($source['spatialFilter']) === true) {
			$this->checkSpatialFilter(filter: $source['spatialFilter']);
		}
	}//end validate()

	/**
	 * A register source names both its register and its schema, and a
	 * spatial filter needs one.
	 *
	 * @param array $source The `markers.dataSource` block.
	 *
	 * @return void
	 * @throws InvalidArgumentException When half a register source is given.
	 *
	 * @spec openspec/changes/map-support/tasks.md#backend
	 */
	private function checkRegisterSource(array $source): void {
		$hasRegister = (isset($source['register']) === true && $source['register'] !== '');
		$hasSchema   = (isset($source['schema']) === true && $source['schema'] !== '');
		if ($hasRegister !== $hasSchema) {
			throw new InvalidArgumentException(message: 'A map source needs both a register and a schema');
		}

		if (isset($source['spatialFilter']) === true && $hasRegister === false) {
			throw new InvalidArgumentException(message: 'A spatial filter needs a register and schema source');
		}
	}//end checkRegisterSource()

	/**
	 * A spatial filter is exactly one readable bbox, radius or polygon.
	 *
	 * @param mixed $filter The `spatialFilter` block.
	 *
	 * @return void
	 * @throws InvalidArgumentException When the filter does not read.
	 *
	 * @spec openspec/changes/map-support/tasks.md#backend
	 */
	private function checkSpatialFilter(mixed $filter): void {
		if (is_array($filter) === false) {
			throw new InvalidArgumentException(message: 'A spatial filter must be an object');
		}

		$kinds = array_values(array_intersect(['bbox', 'near', 'within'], array_keys($filter)));
		if (count($kinds) !== 1) {
			throw new InvalidArgumentException(message: 'A spatial filter has exactly one of bbox, near or within');
		}

		$valid = match ($kinds[0]) {
			'bbox' => $this->isBbox(bbox: $filter['bbox']),
			'near' => $this->isPoint(point: $filter['near']) && is_numeric($filter['radius'] ?? null) && (float) $filter['radius'] > 0,
			default => $this->isPolygon(geometry: $filter['within']),
		};
		if ($valid === false) {
			throw new InvalidArgumentException(message: 'The spatial filter '.$kinds[0].' does not read');
		}
	}//end checkSpatialFilter()

	/**
	 * Whether a value is `[west, south, east, north]` in degrees, west of east and south of north.
	 *
	 * @param mixed $bbox The value.
	 *
	 * @return bool
	 */
	private function isBbox(mixed $bbox): bool {
		if (is_array($bbox) === false || count($bbox) !== 4 || $this->allNumeric(values: $bbox) === false) {
			return false;
		}

		[$west, $south, $east, $north] = array_map(floatval(...), array_values($bbox));
		return $this->isPoint(point: [$west, $south]) && $this->isPoint(point: [$east, $north]) && $west < $east && $south < $north;
	}//end isBbox()

	/**
	 * Whether a value is `[lon, lat]` in degrees.
	 *
	 * @param mixed $point The value.
	 *
	 * @return bool
	 */
	private function isPoint(mixed $point): bool {
		if (is_array($point) === false || count($point) !== 2 || $this->allNumeric(values: $point) === false) {
			return false;
		}

		[$lon, $lat] = array_map(floatval(...), array_values($point));
		return abs($lon) <= 180 && abs($lat) <= 90;
	}//end isPoint()

	/**
	 * Whether a value is a GeoJSON Polygon or MultiPolygon with coordinates.
	 *
	 * @param mixed $geometry The value.
	 *
	 * @return bool
	 */
	private function isPolygon(mixed $geometry): bool {
		return is_array($geometry) === true
			&& in_array(needle: ($geometry['type'] ?? null), haystack: ['Polygon', 'MultiPolygon'], strict: true) === true
			&& is_array($geometry['coordinates'] ?? null) === true
			&& $geometry['coordinates'] !== [];
	}//end isPolygon()

	/**
	 * Whether every value is a number.
	 *
	 * @param array $values The values.
	 *
	 * @return bool
	 */
	private function allNumeric(array $values): bool {
		return array_filter($values, static fn (mixed $value): bool => is_numeric($value) === false) === [];
	}//end allNumeric()

	/**
	 * A detail link is a string, an app path or an http(s) address.
	 *
	 * @param mixed $link The `markers.detailLink` value.
	 *
	 * @return void
	 * @throws InvalidArgumentException When it is not.
	 *
	 * @spec openspec/changes/map-support/tasks.md#backend
	 */
	private function checkDetailLink(mixed $link): void {
		if ($link === null || $link === '') {
			return;
		}

		$safe = is_string($link) === true
			&& (preg_match('#^/(?![/\\\\])#', $link) === 1 || preg_match('#^https?://#i', $link) === 1);
		if ($safe === false) {
			throw new InvalidArgumentException(message: 'A detail link must be an app path or an http(s) address');
		}
	}//end checkDetailLink()

	/**
	 * A geo property is a property name.
	 *
	 * @param mixed $property The `markers.geoProperty` value.
	 *
	 * @return void
	 * @throws InvalidArgumentException When it is not a non-empty string.
	 *
	 * @spec openspec/changes/map-support/tasks.md#backend
	 */
	private function checkGeoProperty(mixed $property): void {
		if ($property !== null && (is_string($property) === false || $property === '')) {
			throw new InvalidArgumentException(message: 'The geo property must be a property name');
		}
	}//end checkGeoProperty()
}//end class
