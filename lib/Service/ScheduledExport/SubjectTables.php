<?php

/**
 * SubjectTables
 *
 * The tabular data behind an export's subject (REQ-SCH-003, REQ-SCH-004):
 * one table per widget of the dashboard, or the one widget, taken from the
 * rows the widget carries (`props.rows`, `props.data` or
 * `dataSource.rows`), with the recipient's filter context applied. A
 * filter on a field no table has is refused.
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
	 * @param ExportObjectStore $store Reads the subject dashboard.
	 */
	public function __construct(
		private readonly ExportObjectStore $store,
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
		$tables = array_map(fn (array $widget): array => $this->table(widget: $widget), $this->widgets(export: $export));

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

		return $tables;
	}//end tables()

	/**
	 * The widgets the subject covers.
	 *
	 * @param array<string, mixed> $export The export.
	 *
	 * @return list<array<string, mixed>>
	 *
	 * @throws RenderException When the subject does not exist.
	 */
	private function widgets(array $export): array {
		$subjectId = (string)($export['subjectId'] ?? '');
		if (($export['subjectType'] ?? null) === 'dashboard') {
			$dashboard = $this->store->find(schema: 'dashboard', uuid: $subjectId, system: true);
			if ($dashboard === null) {
				throw new RenderException('The dashboard of this export no longer exists');
			}

			return array_values(array_filter((array)($dashboard['widgets'] ?? []), 'is_array'));
		}

		$owner = (string)($export['createdBy'] ?? '');
		foreach ($this->store->findAll(schema: 'dashboard', filters: ['@self' => ['owner' => $owner]], system: true) as $dashboard) {
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
