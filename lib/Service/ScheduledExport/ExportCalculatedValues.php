<?php

/**
 * ExportCalculatedValues
 *
 * The calculated fields of an export's widgets, evaluated on the server at
 * export time with the same evaluator the client mirrors (widget-calculated-
 * fields task 20), so an export carries the values the widgets show.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\ScheduledExport
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-20
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\ScheduledExport;

use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldCycleDetector;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldCycleError;
use OCA\LaunchPad\Service\CalculatedFields\CalculatedFieldEvaluator;

/**
 * Evaluates every calculated field of a set of widgets, in dependency order.
 *
 * Each field reads its widget's data (`row`), the widget's earlier values
 * (`calculated`), and the data and values of the widgets it references
 * (`widgets`, `widgetCalculated`), exactly as the client's
 * `evaluateDashboard()` hands them. A field that fails, or a cycle, gives
 * null, as an empty cell; the other fields still evaluate.
 *
 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-20
 */
class ExportCalculatedValues {

	/**
	 * Constructor.
	 *
	 * @param CalculatedFieldEvaluator     $evaluator     Evaluates one compiled expression.
	 * @param CalculatedFieldCycleDetector $cycleDetector Orders the fields, dependencies first.
	 */
	public function __construct(
		private readonly CalculatedFieldEvaluator $evaluator = new CalculatedFieldEvaluator(),
		private readonly CalculatedFieldCycleDetector $cycleDetector = new CalculatedFieldCycleDetector(),
	) {
	}//end __construct()

	/**
	 * The values of every calculated field, by widget id and field name.
	 *
	 * @param list<array<string, mixed>>          $widgets  The widgets, each `{id, calculatedFields}`.
	 * @param array<string, array<string, mixed>> $dataById Each widget's data, by widget id.
	 * @param int|float|null                      $nowMs    The moment `now()` reads; the clock when null.
	 *
	 * @return array<string, array<string, mixed>>
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-20
	 */
	public function values(array $widgets, array $dataById, int|float|null $nowMs=null): array {
		$byId   = $this->compiledWidgets(widgets: $widgets);
		$values = array_map(static fn (): array => [], $byId);
		try {
			$order = $this->cycleDetector->assertAcyclic(widgets: array_values($byId));
		} catch (CalculatedFieldCycleError) {
			return $this->allNull(byId: $byId);
		}

		foreach ($order as $node) {
			$dot      = (int) strrpos($node, '.');
			$widgetId = substr($node, 0, $dot);
			$name     = substr($node, $dot + 1);
			$field    = ($byId[$widgetId]['fields'][$name] ?? null);
			if ($field === null) {
				continue;
			}

			$outcome = $this->evaluator->evaluateField(
				ast: (array) $field['compiledExpression'],
				expression: (string) ($field['expression'] ?? ''),
				context: [
					'row'              => ($dataById[$widgetId] ?? []),
					'calculated'       => $values[$widgetId],
					'widgets'          => $dataById,
					'widgetCalculated' => $values,
				],
				nowMs: $nowMs
			);
			$values[$widgetId][$name] = $outcome['value'];
		}//end foreach

		return $values;
	}//end values()

	/**
	 * The widgets with compiled calculated fields, by id, each with its fields by name.
	 *
	 * @param list<array<string, mixed>> $widgets The widgets.
	 *
	 * @return array<string, array{id: string, calculatedFields: list<array<string, mixed>>, fields: array<string, array<string, mixed>>}>
	 */
	private function compiledWidgets(array $widgets): array {
		$byId = [];
		foreach ($widgets as $widget) {
			$fields = array_values(
				array_filter(
					(array) ($widget['calculatedFields'] ?? []),
					static fn (mixed $field): bool => is_array($field) === true
						&& is_string($field['name'] ?? null) === true
						&& is_array($field['compiledExpression'] ?? null) === true
				)
			);
			if ($fields === [] || isset($widget['id']) === false) {
				continue;
			}

			$id        = (string) $widget['id'];
			$byId[$id] = ['id' => $id, 'calculatedFields' => $fields, 'fields' => array_column($fields, null, 'name')];
		}

		return $byId;
	}//end compiledWidgets()

	/**
	 * Every field null, for a set of fields that cannot be ordered.
	 *
	 * @param array<string, array{fields: array<string, mixed>}> $byId The widgets by id.
	 *
	 * @return array<string, array<string, null>>
	 */
	private function allNull(array $byId): array {
		return array_map(static fn (array $widget): array => array_fill_keys(array_keys($widget['fields']), null), $byId);
	}//end allNull()
}//end class
