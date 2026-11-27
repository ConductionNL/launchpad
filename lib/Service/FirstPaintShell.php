<?php

/**
 * FirstPaintShell
 *
 * The view model of the first-paint shell (first-paint-skeleton D1, D2, D3,
 * D5): the page header the mounted page will draw, and one frame per widget
 * the grid will draw, at its position for each column count, with its title
 * and the kind of placeholder its content has. It is built from the values
 * the page already reads (the opened dashboard, its placements after the role
 * and visibility filter, the personal layer's hidden ids and the widget
 * catalogue), so the shell and the mounted page name the same widgets in the
 * same places; it reads nothing itself.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-the-page-header-as-text-req-fps-001
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * Builds the first-paint shell's view model.
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
 */
class FirstPaintShell {

	/**
	 * The placeholder kinds a frame can have.
	 */
	public const KINDS = ['rows', 'lines', 'block', 'tiles', 'counter', 'heading'];

	/**
	 * The catalogue entries of the current build, by widget type.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $entries = [];

	/**
	 * The fallback kinds, read once per request.
	 *
	 * @var array{default: string, kinds: array<string, string>}|null
	 */
	private ?array $fallback = null;

	/**
	 * The placeholder declarations of nextcloud-vue's widget registry, read once per request.
	 *
	 * @var array<string, mixed>|null
	 */
	private ?array $registry = null;

	/**
	 * Constructor.
	 *
	 * @param FirstPaintLayout $layout Where the grid puts each frame per column count.
	 */
	public function __construct(
		private readonly FirstPaintLayout $layout=new FirstPaintLayout(),
	) {
	}//end __construct()

	/**
	 * The view model.
	 *
	 * @param array<string, mixed>       $page      The opened page: `dashboard` (name, description, gridColumns),
	 *                                              `placements` as the page's layout carries them, and `hidden`,
	 *                                              the placement ids the personal layer hides for this reader.
	 * @param list<array<string, mixed>> $catalogue The widget catalogue the page carries (id, title, placeholder, mobileReady).
	 *
	 * @return array{header: array<string, mixed>, columns: list<int>, frames: list<array<string, mixed>>}
	 *
	 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
	 */
	public function build(array $page, array $catalogue): array {
		$dashboard     = (array) ($page['dashboard'] ?? []);
		$placements    = (array) ($page['placements'] ?? []);
		$columns       = (int) ($dashboard['gridColumns'] ?? 12);
		$this->entries = [];
		foreach ($catalogue as $entry) {
			$this->entries[(string) ($entry['id'] ?? '')] = $entry;
		}

		$hiddenIds = array_map('strval', (array) ($page['hidden'] ?? []));
		$shown     = array_values(
			array_filter(
				$placements,
				static fn (array $placement): bool => (int) ($placement['isVisible'] ?? 1) !== 0
					&& in_array((string) ($placement['id'] ?? ''), $hiddenIds, true) === false
			)
		);

		$positions = $this->layout->layout(
			nodes: array_map(
				static fn (array $placement): array => [
					'id' => (string) ($placement['id'] ?? ''),
					'x'  => (int) ($placement['gridX'] ?? 0),
					'y'  => (int) ($placement['gridY'] ?? 0),
					'w'  => (int) ($placement['gridWidth'] ?? 1),
					'h'  => (int) ($placement['gridHeight'] ?? 1),
				],
				$shown
			),
			columns: $columns
		);

		$frames = [];
		foreach ($shown as $placement) {
			$frames[] = $this->frame(placement: $placement, positions: $positions);
		}

		return [
			'header'  => self::header(dashboard: $dashboard, placements: $shown),
			'columns' => array_keys($positions),
			'frames'  => $frames,
		];
	}//end build()

	/**
	 * The placeholder kind of a placement, in the order of REQ-FPS-009: the
	 * manifest declaration the placement carries, the declaration of its type
	 * in nextcloud-vue's widget registry (widget-placeholders.json), the
	 * Nextcloud widget's `placeholder` in the catalogue, the fallback table,
	 * and otherwise its default. An invalid declaration at any step is skipped.
	 *
	 * @param string               $widgetId  The widget type.
	 * @param array<string, mixed> $entry     Its catalogue entry, if any.
	 * @param array<string, mixed> $placement The placement, if any.
	 *
	 * @return string One of KINDS.
	 *
	 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
	 * @spec openspec/changes/widget-declared-placeholders/tasks.md#task-3
	 */
	public function kindOf(string $widgetId, array $entry=[], array $placement=[]): string {
		$registry = $this->registry();
		foreach ([$placement['placeholder'] ?? null, $registry[$widgetId] ?? null, $entry['placeholder'] ?? null] as $declared) {
			$shape = self::shapeOf(declared: $declared);
			if ($shape !== null) {
				return $shape;
			}
		}

		$fallback = $this->fallback();

		return $fallback['kinds'][$widgetId] ?? $fallback['default'];
	}//end kindOf()

	/**
	 * The shape of a declaration: a shape name or `['shape' => <name>, 'count' => n]`.
	 *
	 * @param mixed $declared The declaration.
	 *
	 * @return string|null One of KINDS, or null when the declaration is not a valid one.
	 *
	 * @spec openspec/changes/widget-declared-placeholders/tasks.md#task-3
	 */
	private static function shapeOf(mixed $declared): ?string {
		if (is_array($declared) === true) {
			$declared = $declared['shape'] ?? null;
		}

		if (is_string($declared) === true && in_array($declared, self::KINDS, true) === true) {
			return $declared;
		}

		return null;
	}//end shapeOf()

	/**
	 * The registry's declarations: the committed copy of nextcloud-vue's
	 * widget-placeholders.json, by widget type.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/widget-declared-placeholders/tasks.md#task-2
	 */
	private function registry(): array {
		if ($this->registry === null) {
			$file           = __DIR__.'/widget-placeholders.json';
			$read           = is_file($file) === true ? json_decode((string) file_get_contents($file), true) : [];
			$this->registry = is_array($read) === true ? $read : [];
		}

		return $this->registry;
	}//end registry()

	/**
	 * One frame.
	 *
	 * @param array<string, mixed>                                             $placement The placement.
	 * @param array<int, array<string, array{x: int, y: int, w: int, h: int}>> $positions Positions per column count.
	 *
	 * @return array<string, mixed>
	 */
	private function frame(array $placement, array $positions): array {
		$id        = (string) ($placement['id'] ?? '');
		$widgetId  = (string) ($placement['widgetId'] ?? '');
		$entry     = $this->entries[$widgetId] ?? [];
		$byColumns = [];
		foreach ($positions as $count => $byId) {
			$byColumns[$count] = $byId[$id];
		}

		return [
			'id'          => $id,
			'widgetId'    => $widgetId,
			'title'       => self::titleOf(placement: $placement, entry: $entry),
			'kind'        => $this->kindOf(widgetId: $widgetId, entry: $entry, placement: $placement),
			'positions'   => $byColumns,
			'phoneHidden' => ($entry['mobileReady'] ?? true) === false,
		];
	}//end frame()

	/**
	 * The page header: the greeting row when a header placement sets
	 * `content.ground` (LpVandaag), else the dashboard's name and line.
	 *
	 * @param array<string, mixed>       $dashboard  The dashboard.
	 * @param list<array<string, mixed>> $placements The shown placements.
	 *
	 * @return array{mode: string, name: string, description: string, groundId: string}
	 */
	private static function header(array $dashboard, array $placements): array {
		$header = [
			'mode'        => 'page',
			'name'        => (string) ($dashboard['name'] ?? ''),
			'description' => (string) ($dashboard['description'] ?? ''),
			'groundId'    => '',
		];
		foreach ($placements as $placement) {
			if (($placement['widgetId'] ?? '') === 'header' && ($placement['content']['ground'] ?? false) === true) {
				$header['mode']     = 'greeting';
				$header['groundId'] = (string) ($placement['id'] ?? '');
				break;
			}
		}

		return $header;
	}//end header()

	/**
	 * The frame's title as the mounted card shows it: none when the title
	 * is switched off, else the custom title, else the catalogue's.
	 *
	 * @param array<string, mixed> $placement The placement.
	 * @param array<string, mixed> $entry     Its catalogue entry.
	 *
	 * @return string
	 */
	private static function titleOf(array $placement, array $entry): string {
		if ((int) ($placement['showTitle'] ?? 1) === 0) {
			return '';
		}

		$custom = trim((string) ($placement['customTitle'] ?? ''));
		if ($custom !== '') {
			return $custom;
		}

		return (string) ($entry['title'] ?? '');
	}//end titleOf()

	/**
	 * The fallback table.
	 *
	 * @return array{default: string, kinds: array<string, string>}
	 */
	private function fallback(): array {
		if ($this->fallback === null) {
			$read = json_decode((string) file_get_contents(__DIR__.'/first-paint-kinds.json'), true);
			$this->fallback = [
				'default' => (string) ($read['default'] ?? 'rows'),
				'kinds'   => (array) ($read['kinds'] ?? []),
			];
		}

		return $this->fallback;
	}//end fallback()
}//end class
