<?php

/**
 * ReportLibrary
 *
 * The report gallery, a report run, and "save as dashboard" (REQ-DWMS-003).
 *
 * A report runs without anything being composed first: it reads its source
 * through ObjectRows and aggregates in memory. A report whose register or
 * schema this instance lacks is listed as unavailable with those names and,
 * when opened, answers the same instead of an error. Saving a result creates
 * an ordinary dashboard owned by the saver, holding one chart of the result.
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
use InvalidArgumentException;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\PlacementService;

/**
 * Lists, runs and saves the shipped reports.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */
class ReportLibrary {

	/**
	 * Constructor.
	 *
	 * @param ReportDefinitions $definitions The shipped definitions.
	 * @param ObjectRows        $objects     Reads a report's source.
	 * @param AreaAggregation   $areas       The `area` aggregation.
	 * @param DashboardService  $dashboards  Creates the saved dashboard.
	 * @param PlacementService  $placements  Places the result on it.
	 */
	public function __construct(
		private readonly ReportDefinitions $definitions,
		private readonly ObjectRows $objects,
		private readonly AreaAggregation $areas,
		private readonly DashboardService $dashboards,
		private readonly PlacementService $placements,
	) {
	}//end __construct()

	/**
	 * Every shipped report with whether it can run here and, if not, what is missing.
	 *
	 * @return list<array<string, mixed>>
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-003-reports-the-product-ships
	 */
	public function gallery(): array {
		$entries = [];
		foreach ($this->definitions->all() as $definition) {
			$missing = $this->missing(definition: $definition);
			$entries[] = $this->summary(definition: $definition) + ['available' => ($missing === []), 'missing' => $missing];
		}

		return $entries;
	}//end gallery()

	/**
	 * Run one report, or null for an unknown id.
	 *
	 * An unavailable report answers with `available: false` and the missing
	 * names, never an exception.
	 *
	 * @param string               $id         The report id.
	 * @param array<string, mixed> $parameters Values for the declared parameters; others are ignored.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @throws InvalidArgumentException When a parameter value is not of its declared type.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-003-reports-the-product-ships
	 */
	public function run(string $id, array $parameters=[]): ?array {
		$definition = $this->definitions->find(id: $id);
		if ($definition === null) {
			return null;
		}

		$values = $this->parameterValues(definition: $definition, given: $parameters);
		$rows = $this->objects->rows(
			register: $definition['source']['register'],
			schema: $definition['source']['schema'],
			filters: $definition['filters']
		);
		$answer = $this->summary(definition: $definition) + ['parameterValues' => $values];
		if ($rows === null) {
			return $answer + ['available' => false, 'missing' => $definition['requires']['schemas'], 'result' => null];
		}

		return $answer + ['available' => true, 'missing' => [], 'result' => $this->aggregate(definition: $definition, rows: $rows, values: $values)];
	}//end run()

	/**
	 * Save a report's result as an ordinary dashboard owned by the saver.
	 *
	 * @param string               $id         The report id.
	 * @param array<string, mixed> $parameters The parameter values.
	 * @param string               $userId     The saver, who owns the dashboard.
	 *
	 * @return Dashboard The new dashboard.
	 *
	 * @throws InvalidArgumentException When the report is unknown or cannot run here.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-003-reports-the-product-ships
	 */
	public function saveAsDashboard(string $id, array $parameters, string $userId): Dashboard {
		$run = $this->run(id: $id, parameters: $parameters);
		if ($run === null) {
			throw new InvalidArgumentException("There is no report {$id}.");
		}

		if ($run['available'] !== true) {
			throw new InvalidArgumentException("Report {$id} cannot run here: missing " . implode(', ', $run['missing']) . '.');
		}

		$dashboard = $this->dashboards->createDashboard(userId: $userId, name: $run['title'], options: ['description' => $run['description']]);
		$this->placements->addWidget(
			dashboardId: (int)$dashboard->getId(),
			widgetId: 'chart',
			layout: ['gridWidth' => 8, 'gridHeight' => 6, 'content' => $this->chartContent(run: $run)]
		);

		return $dashboard;
	}//end saveAsDashboard()

	/**
	 * The chart a saved report shows: the result as series and categories, and where it came from.
	 *
	 * @param array<string, mixed> $run A successful run.
	 *
	 * @return array<string, mixed>
	 */
	private function chartContent(array $run): array {
		$categories = [];
		$data = [];
		foreach ($run['result']['groups'] as $group) {
			$categories[] = $group['label'];
			$data[] = $group['count'];
		}

		return [
			'title'     => $run['title'],
			'chartKind' => ($run['rendering']['chartKind'] ?? 'bar'),
			'props'     => ['series' => [['name' => $run['title'], 'data' => $data]], 'categories' => $categories],
			'report'    => ['id' => $run['id'], 'parameters' => $run['parameterValues'], 'savedTotal' => $run['result']['total']],
		];
	}//end chartContent()

	/**
	 * The names this instance lacks for a definition.
	 *
	 * @param array<string, mixed> $definition The definition.
	 *
	 * @return list<string>
	 */
	private function missing(array $definition): array {
		$probe = $this->objects->rows(
			register: $definition['source']['register'],
			schema: $definition['source']['schema'],
			limit: 1
		);
		if ($probe === null) {
			return $definition['requires']['schemas'];
		}

		return [];
	}//end missing()

	/**
	 * Run the definition's aggregation; both kinds answer `groups` and `total`.
	 *
	 * @param array<string, mixed>       $definition The definition.
	 * @param list<array<string, mixed>> $rows       The source rows.
	 * @param array<string, mixed>       $values     The parameter values.
	 *
	 * @return array<string, mixed>
	 */
	private function aggregate(array $definition, array $rows, array $values): array {
		$aggregation = $definition['aggregation'];
		if ($aggregation['kind'] === 'area') {
			$areas = $this->areas->aggregate(
				rows: $rows,
				areaProperty: $aggregation['property'],
				dateProperty: ($aggregation['dateProperty'] ?? null),
				from: $this->date(value: ($values['from'] ?? null)),
				until: $this->date(value: ($values['until'] ?? null))
			);
			$groups = array_map(
				static fn (array $area): array => ['label' => $area['area'], 'count' => $area['count'], 'rate' => $area['rate']],
				$areas['areas']
			);
			$groups[] = ['label' => AreaAggregation::NO_LOCATION, 'count' => $areas['noLocation']['count'], 'rate' => $areas['noLocation']['rate']];

			return ['total' => $areas['total'], 'groups' => $groups];
		}

		return $this->countBy(rows: $rows, property: $aggregation['property'], emptyLabel: (string)($aggregation['emptyLabel'] ?? '-'));
	}//end aggregate()

	/**
	 * Rows per value of one property, the largest group first.
	 *
	 * @param list<array<string, mixed>> $rows       The rows.
	 * @param string                     $property   The property.
	 * @param string                     $emptyLabel The label for rows without a value.
	 *
	 * @return array{total: int, groups: list<array{label: string, count: int}>}
	 */
	private function countBy(array $rows, string $property, string $emptyLabel): array {
		$counts = [];
		foreach ($rows as $row) {
			$value = ($row[$property] ?? null);
			$label = $emptyLabel;
			if ((is_string($value) === true && trim($value) !== '') || is_int($value) === true || is_bool($value) === true) {
				$label = trim((string)$value);
				if (is_bool($value) === true) {
					$label = var_export($value, true);
				}
			}

			$counts[$label] = (($counts[$label] ?? 0) + 1);
		}

		uksort(
			$counts,
			static function (string|int $first, string|int $second) use ($counts): int {
				return [$counts[$second], (string)$first] <=> [$counts[$first], (string)$second];
			}
		);
		$groups = [];
		foreach ($counts as $label => $count) {
			$groups[] = ['label' => (string)$label, 'count' => $count];
		}

		return ['total' => count($rows), 'groups' => $groups];
	}//end countBy()

	/**
	 * The parameter values: given ones of the declared type, defaults otherwise.
	 *
	 * @param array<string, mixed> $definition The definition.
	 * @param array<string, mixed> $given      The values asked for.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws InvalidArgumentException When a value is not of its type.
	 */
	private function parameterValues(array $definition, array $given): array {
		$values = [];
		foreach ($definition['parameters'] as $parameter) {
			$value = ($given[$parameter['name']] ?? $parameter['default']);
			if ($value !== null && (is_string($value) === false || ($parameter['type'] === 'date' && $this->date(value: $value) === null))) {
				throw new InvalidArgumentException("Parameter {$parameter['name']} must be a {$parameter['type']}.");
			}

			$values[$parameter['name']] = $value;
		}

		return $values;
	}//end parameterValues()

	/**
	 * A date parameter as a moment, or null.
	 *
	 * @param mixed $value The value.
	 *
	 * @return DateTimeImmutable|null
	 */
	private function date(mixed $value): ?DateTimeImmutable {
		if (is_string($value) === false || trim($value) === '') {
			return null;
		}

		try {
			return new DateTimeImmutable($value);
		} catch (Exception) {
			return null;
		}
	}//end date()

	/**
	 * What every listing shows of a definition.
	 *
	 * @param array<string, mixed> $definition The definition.
	 *
	 * @return array<string, mixed>
	 */
	private function summary(array $definition): array {
		return [
			'id'          => $definition['id'],
			'title'       => $definition['title'],
			'description' => $definition['description'],
			'parameters'  => $definition['parameters'],
			'rendering'   => $definition['rendering'],
			'requires'    => $definition['requires'],
		];
	}//end summary()
}//end class
