<?php

/**
 * AreaAggregation
 *
 * Counts and rates per area over a period, for objects that carry a location
 * (REQ-DWMS-009). The area is read from a declared property; an object
 * without one is counted in an explicit "no location" bucket and never
 * dropped, so the areas plus that bucket always add up to the total.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service\Reporting
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\Reporting;

use DateTimeImmutable;
use Exception;

/**
 * Groups object rows by an area property within a period.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */
class AreaAggregation {

	/**
	 * The key of the bucket for objects without an area.
	 */
	public const NO_LOCATION = 'no-location';

	/**
	 * Count rows per area, with the rate of each against the period total.
	 *
	 * @param array<int, array<string, mixed>> $rows         The objects, as arrays.
	 * @param string                           $areaProperty The property naming the area; a dot reads into an object (`address.district`).
	 * @param string|null                      $dateProperty The property dating each object; null counts every row.
	 * @param DateTimeImmutable|null           $from         The first moment of the period, inclusive.
	 * @param DateTimeImmutable|null           $until        The end of the period, exclusive.
	 *
	 * @return array{total: int, areas: list<array{area: string, count: int, rate: float}>, noLocation: array{count: int, rate: float}}
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-009-geographic-trend-reporting
	 */
	public function aggregate(
		array $rows,
		string $areaProperty,
		?string $dateProperty=null,
		?DateTimeImmutable $from=null,
		?DateTimeImmutable $until=null
	): array {
		$counts = [];
		$noLocation = 0;
		$total = 0;
		foreach ($rows as $row) {
			if (is_array($row) === false || $this->inPeriod(row: $row, dateProperty: $dateProperty, from: $from, until: $until) === false) {
				continue;
			}

			$total++;
			$area = $this->areaOf(row: $row, areaProperty: $areaProperty);
			if ($area === null) {
				$noLocation++;
				continue;
			}

			$counts[$area] = (($counts[$area] ?? 0) + 1);
		}

		ksort($counts, SORT_NATURAL | SORT_FLAG_CASE);
		$areas = [];
		foreach ($counts as $area => $count) {
			$areas[] = ['area' => (string)$area, 'count' => $count, 'rate' => $this->rate(count: $count, total: $total)];
		}

		return [
			'total'      => $total,
			'areas'      => $areas,
			'noLocation' => ['count' => $noLocation, 'rate' => $this->rate(count: $noLocation, total: $total)],
		];
	}//end aggregate()

	/**
	 * The area of one row, or null when it has none.
	 *
	 * @param array<string, mixed> $row          The object.
	 * @param string               $areaProperty The property path.
	 *
	 * @return string|null
	 */
	private function areaOf(array $row, string $areaProperty): ?string {
		$value = $this->read(row: $row, path: $areaProperty);
		if (is_string($value) === true) {
			$value = trim($value);
			if ($value === '') {
				return null;
			}

			return $value;
		}

		if (is_int($value) === true) {
			return (string)$value;
		}

		return null;
	}//end areaOf()

	/**
	 * Whether a row falls in the period; a row without a readable date falls outside a bounded one.
	 *
	 * @param array<string, mixed>   $row          The object.
	 * @param string|null            $dateProperty The date property.
	 * @param DateTimeImmutable|null $from         Inclusive start.
	 * @param DateTimeImmutable|null $until        Exclusive end.
	 *
	 * @return bool
	 */
	private function inPeriod(array $row, ?string $dateProperty, ?DateTimeImmutable $from, ?DateTimeImmutable $until): bool {
		if ($dateProperty === null || ($from === null && $until === null)) {
			return true;
		}

		$raw = $this->read(row: $row, path: $dateProperty);
		if (is_string($raw) === false || trim($raw) === '') {
			return false;
		}

		try {
			$date = new DateTimeImmutable($raw);
		} catch (Exception) {
			return false;
		}

		if ($from !== null && $date < $from) {
			return false;
		}

		return ($until === null || $date < $until);
	}//end inPeriod()

	/**
	 * Read a dot path from a row.
	 *
	 * @param array<string, mixed> $row  The object.
	 * @param string               $path The path.
	 *
	 * @return mixed
	 */
	private function read(array $row, string $path): mixed {
		$value = $row;
		foreach (explode('.', $path) as $key) {
			if (is_array($value) === false || array_key_exists($key, $value) === false) {
				return null;
			}

			$value = $value[$key];
		}

		return $value;
	}//end read()

	/**
	 * A share of the total, to four decimals; zero when there is nothing to share.
	 *
	 * @param int $count The part.
	 * @param int $total The whole.
	 *
	 * @return float
	 */
	private function rate(int $count, int $total): float {
		if ($total === 0) {
			return 0.0;
		}

		return round(($count / $total), 4);
	}//end rate()
}//end class
