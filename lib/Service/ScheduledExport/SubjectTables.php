<?php

/**
 * SubjectTables
 *
 * The tabular data behind an export's subject (REQ-SCH-003, REQ-SCH-004):
 * one table per widget of the dashboard, or the one widget, taken from the
 * rows the widget carries (`props.rows`, `props.data` or
 * `dataSource.rows`), with the recipient's filter context applied. A
 * filter on a field no table has is refused. A widget with calculated
 * fields gets a second table of their values, evaluated on the server over
 * the filtered rows, so the export carries what the widget shows.
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
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\ScheduledExport;

/**
 * Widget tables of a dashboard or widget subject.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-003-multi-format-render-from-a-single-fire
 */
class SubjectTables {

	/**
	 * Constructor.
	 *
	 * @param ExportObjectStore      $store      Reads the subject dashboard.
	 * @param ExportCalculatedValues $calculated Evaluates the widgets' calculated fields.
	 */
	public function __construct(
		private readonly ExportObjectStore $store,
		private readonly ExportCalculatedValues $calculated = new ExportCalculatedValues(),
	) {
	}//end __construct()

	/**
	 * The subject's tables, filtered.
	 *
	 * @param array<string, mixed> $export        The export.
	 * @param array<string, mixed> $filterContext The recipient's filter context.
	 *
	 * @return list<array{title: string, headers: list<string>, rows: list<list<mixed>>}>
	 *
	 * @throws RenderException               When the subject does not exist.
	 * @throws InvalidFilterContextException When a filter names a field no table has.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-004-per-recipient-filter-context
	 */
	public function tables(array $export, array $filterContext): array {
		$widgets = $this->widgets(export: $export);
		$tables  = array_map(fn (array $widget): array => $this->table(widget: $widget), $widgets);

		foreach ($filterContext as $field => $value) {
			$field  = (string)$field;
			$hasIt  = array_filter($tables, static fn (array $t): bool => in_array($field, $t['headers'], true));
			if ($hasIt === []) {
				throw new InvalidFilterContextException('Unknown filter field: ' . $field);
			}

			foreach ($tables as $i => $table) {
				$column = array_search($field, $table['headers'], true);
				if ($column === false) {
					continue;
				}

				$keep = static fn (array $row): bool => (string)($row[$column] ?? '') === (string)$value;
				$tables[$i]['rows'] = array_values(array_filter($table['rows'], $keep));
			}
		}

		return $this->withCalculatedTables(widgets: $widgets, tables: $tables);
	}//end tables()

	/**
	 * The tables, each widget's followed by the values of its calculated
	 * fields: one row, a column per field, headed by the field's label.
	 *
	 * A widget's data is its filtered rows, as `rows` (each row by header)
	 * and `total` (their number), which is what a field can read here.
	 *
	 * @param list<array<string, mixed>>                                          $widgets The widgets, in table order.
	 * @param list<array{title: string, headers: list<string>, rows: list<list<mixed>>}> $tables  Their filtered tables.
	 *
	 * @return list<array{title: string, headers: list<string>, rows: list<list<mixed>>}>
	 *
	 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-20
	 */
	private function withCalculatedTables(array $widgets, array $tables): array {
		$dataById = [];
		foreach ($widgets as $i => $widget) {
			$rows = array_map(static fn (array $row): array => array_combine($tables[$i]['headers'], $row), $tables[$i]['rows']);
			$dataById[(string) ($widget['id'] ?? $i)] = ['rows' => $rows, 'total' => count($rows)];
		}

		$values = $this->calculated->values(widgets: $widgets, dataById: $dataById);
		$out    = [];
		foreach ($widgets as $i => $widget) {
			$out[] = $tables[$i];
			$id    = (string) ($widget['id'] ?? $i);
			$known  = ($values[$id] ?? []);
			$fields = array_values(
				array_filter(
					(array) ($widget['calculatedFields'] ?? []),
					static fn (mixed $field): bool => is_array($field) === true && array_key_exists((string) ($field['name'] ?? ''), $known) === true
				)
			);
			if ($fields === []) {
				continue;
			}

			$out[] = [
				'title'   => $tables[$i]['title'] . ': calculated fields',
				'headers' => array_map(static fn (array $field): string => (string) ($field['label'] ?? $field['name']), $fields),
				'rows'    => [array_map(static fn (array $field): mixed => $values[$id][(string) $field['name']], $fields)],
			];
		}

		return $out;
	}//end withCalculatedTables()

	/**
	 * The widgets the subject covers.
	 *
	 * @param array<string, mixed> $export The export.
	 *
	 * @return list<array<string, mixed>>
	 *
	 * @throws RenderException When the subject does not exist or the creator does not own it.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-002-background-runner-fires-schedules-without-an-active-browser-session
	 */
	private function widgets(array $export): array {
		$subjectId = (string)($export['subjectId'] ?? '');
		$owner     = (string)($export['createdBy'] ?? '');
		if (($export['subjectType'] ?? null) === 'dashboard') {
			// REQ-SCH-002: the render runs under the creator's access, so only a
			// dashboard the creator owns is read; any other reads as gone.
			$dashboard = $this->store->find(schema: 'dashboard', uuid: $subjectId);
			if ($dashboard === null || $owner === '' || (string)($dashboard['owner'] ?? '') !== $owner) {
				throw new RenderException('The dashboard of this export no longer exists');
			}

			return array_values(array_filter((array)($dashboard['widgets'] ?? []), 'is_array'));
		}

		foreach ($this->store->findAll(schema: 'dashboard', filters: ['@self' => ['owner' => $owner]]) as $dashboard) {
			foreach ((array)($dashboard['widgets'] ?? []) as $widget) {
				if (is_array($widget) === true && in_array($subjectId, [(string)($widget['id'] ?? ''), (string)($widget['widgetId'] ?? '')], true) === true) {
					return [$widget];
				}
			}
		}

		throw new RenderException('The widget of this export no longer exists');
	}//end widgets()

	/**
	 * One widget's table: the union of its row keys as headers.
	 *
	 * @param array<string, mixed> $widget The widget placement.
	 *
	 * @return array{title: string, headers: list<string>, rows: list<list<mixed>>}
	 */
	private function table(array $widget): array {
		$props = (array)($widget['props'] ?? []);
		$title = (string)($props['title'] ?? $widget['title'] ?? $widget['widgetKey'] ?? 'Widget');
		$data  = $props['rows'] ?? $props['data'] ?? $widget['dataSource']['rows'] ?? [];
		if (is_array($data) === false) {
			$data = [];
		}

		$data = array_values(array_filter($data, 'is_array'));

		$headers = [];
		foreach ($data as $row) {
			foreach (array_keys($row) as $key) {
				$headers[(string)$key] = true;
			}
		}

		$headers = array_keys($headers);
		$rows    = array_map(static fn (array $row): array => array_map(static fn (string $header): mixed => $row[$header] ?? null, $headers), $data);

		return ['title' => $title, 'headers' => array_map('strval', $headers), 'rows' => $rows];
	}//end table()
}//end class
