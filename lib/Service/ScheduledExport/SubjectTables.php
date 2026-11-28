<?php

/**
 * SubjectTables
 *
 * The tabular data behind an export's subject (REQ-SCH-003, REQ-SCH-004):
 * one table per visible widget of a LaunchPad dashboard, or the one widget,
 * read from LaunchPad's own dashboards and placements: a text widget's
 * table, or the rows of objects its content carries (`rows` or `data`),
 * with the recipient's filter context applied. A
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

use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Widget tables of a dashboard or widget subject.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-003-multi-format-render-from-a-single-fire
 */
class SubjectTables {

	/**
	 * Constructor.
	 *
	 * @param DashboardMapper        $dashboards  LaunchPad's dashboards.
	 * @param WidgetPlacementMapper  $placements  Their widget placements.
	 * @param PermissionService      $permissions Whether the creator may see the subject.
	 * @param ExportCalculatedValues $calculated  Evaluates the widgets' calculated fields.
	 */
	public function __construct(
		private readonly DashboardMapper $dashboards,
		private readonly WidgetPlacementMapper $placements,
		private readonly PermissionService $permissions,
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
	 * The widgets the subject covers, as `{id, title, props, calculatedFields}`:
	 * the visible placements of a dashboard, or one placement. The render runs
	 * under the creator's access (REQ-SCH-002): a subject the creator may not
	 * see reads as gone.
	 *
	 * @param array<string, mixed> $export The export.
	 *
	 * @return list<array<string, mixed>>
	 *
	 * @throws RenderException When the subject does not exist or the creator may not see it.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-002-background-runner-fires-schedules-without-an-active-browser-session
	 */
	private function widgets(array $export): array {
		$subjectId = (string)($export['subjectId'] ?? '');
		$creator   = (string)($export['createdBy'] ?? '');
		if (($export['subjectType'] ?? null) === 'dashboard') {
			$dashboardId = $this->dashboardId(uuid: $subjectId);
			if ($dashboardId === null || $creator === '' || $this->permissions->canViewDashboard(userId: $creator, dashboardId: $dashboardId) === false) {
				throw new RenderException('The dashboard of this export no longer exists');
			}

			$visible = array_filter(
				$this->placements->findByDashboardId(dashboardId: $dashboardId),
				static fn (WidgetPlacement $placement): bool => $placement->getIsVisible() !== 0
			);

			return array_values(array_map(static fn (WidgetPlacement $placement): array => self::widgetOf(placement: $placement), $visible));
		}

		$placement = $this->placement(id: $subjectId);
		if ($placement === null || $creator === '' || $this->permissions->canViewPlacement(userId: $creator, placementId: $placement->getId()) === false) {
			throw new RenderException('The widget of this export no longer exists');
		}

		return [self::widgetOf(placement: $placement)];
	}//end widgets()

	/**
	 * The id of a dashboard by uuid, or null when there is none.
	 *
	 * @param string $uuid The dashboard uuid.
	 *
	 * @return int|null
	 */
	private function dashboardId(string $uuid): ?int {
		if ($uuid === '') {
			return null;
		}

		try {
			return $this->dashboards->findByUuid(uuid: $uuid)->getId();
		} catch (DoesNotExistException) {
			return null;
		}
	}//end dashboardId()

	/**
	 * A widget placement by its numeric id, or null when there is none.
	 *
	 * @param string $id The placement id.
	 *
	 * @return WidgetPlacement|null
	 */
	private function placement(string $id): ?WidgetPlacement {
		if (ctype_digit($id) === false) {
			return null;
		}

		try {
			return $this->placements->find(id: (int)$id);
		} catch (DoesNotExistException) {
			return null;
		}
	}//end placement()

	/**
	 * A placement as the tables read it: its id, the title it shows, its
	 * content and its calculated fields.
	 *
	 * @param WidgetPlacement $placement The placement.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/specs/tiles/spec.md#requirement-req-tile-placement-inline-content-placement-model-promoted-to-canonical
	 */
	private static function widgetOf(WidgetPlacement $placement): array {
		$title = (string)($placement->getCustomTitle() ?? '');
		if ($title === '') {
			// REQ-TILE-PLACEMENT: a link tile made with the form keeps its title in content.
			$title = (string)($placement->linkTileField(field: 'title') ?? '');
		}

		if ($title === '') {
			$title = $placement->getWidgetId();
		}

		return [
			'id' => (string)$placement->getId(),
			'title' => $title,
			'props' => $placement->getContentArray(),
			'calculatedFields' => $placement->getCalculatedFieldsArray(),
		];
	}//end widgetOf()

	/**
	 * One widget's table: a text widget's table (its first row the headers
	 * when it has a header row), or rows of objects with the union of their
	 * keys as headers.
	 *
	 * @param array<string, mixed> $widget The widget.
	 *
	 * @return array{title: string, headers: list<string>, rows: list<list<mixed>>}
	 */
	private function table(array $widget): array {
		$props = (array)($widget['props'] ?? []);
		$title = (string)($props['title'] ?? $widget['title'] ?? 'Widget');
		if (($props['tableMode'] ?? false) === true && is_array($props['tableData']['rows'] ?? null) === true) {
			return ['title' => $title] + self::textTable(tableData: (array)$props['tableData']);
		}

		$data = $props['rows'] ?? $props['data'] ?? [];
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

	/**
	 * The headers and rows of a text widget's table: cell texts, the first
	 * row as headers when the table has a header row, else `Column 1` and on.
	 * Every row is cut or padded to the header count.
	 *
	 * @param array<string, mixed> $tableData The widget's `tableData`.
	 *
	 * @return array{headers: list<string>, rows: list<list<string>>}
	 */
	private static function textTable(array $tableData): array {
		$texts = [];
		foreach ((array)$tableData['rows'] as $row) {
			if (is_array($row) === true) {
				$texts[] = array_map(static fn (mixed $cell): string => self::cellText(cell: $cell), array_values($row));
			}
		}

		$width   = max(array_merge([0], array_map('count', $texts)));
		$headers = array_map(static fn (int $index): string => 'Column ' . ($index + 1), range(0, max(0, $width - 1)));
		if (($tableData['headerRow'] ?? false) === true && $texts !== []) {
			$headers = array_pad(array_shift($texts), $width, '');
		}

		if ($width === 0) {
			$headers = [];
		}

		$rows = array_map(static fn (array $row): array => array_slice(array_pad($row, $width, ''), 0, $width), $texts);

		return ['headers' => array_values($headers), 'rows' => array_values($rows)];
	}//end textTable()

	/**
	 * The text of one table cell (`{text}`, or a bare value).
	 *
	 * @param mixed $cell The cell.
	 *
	 * @return string
	 */
	private static function cellText(mixed $cell): string {
		if (is_array($cell) === true) {
			$cell = $cell['text'] ?? '';
		}

		return trim((string)$cell);
	}//end cellText()
}//end class
