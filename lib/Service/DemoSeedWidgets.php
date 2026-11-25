<?php

/**
 * DemoSeedWidgets
 *
 * Turns the widget entries of a demo seed dashboard into placement
 * payloads. A `tile` entry expands from the seed's shared tile catalogue:
 * an `app-tile` when the app is enabled and that widget type exists, a
 * coloured launcher tile otherwise. An entry whose type is not registered
 * falls back to its `fallback` entry, or is skipped and reported.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 * @spec      openspec/specs/demo-data-showcases/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * Expand demo seed widget entries into placement payloads.
 *
 * @spec openspec/specs/demo-data-showcases/spec.md
 */
class DemoSeedWidgets {
	/**
	 * Settings a tile entry may carry into its placement's content, with the
	 * type each must have: a tile that shows only its glyph, one whose rows
	 * take its own size on a dashboard that fills the screen, its pin, its
	 * count and its outage dot (LpVandaag, LpWerkplek).
	 *
	 * @var array<string, string>
	 */
	private const TILE_CONTENT_SETTINGS = [
		'iconOnly' => 'boolean',
		'fitRows'  => 'boolean',
		'pinned'   => 'boolean',
	];

	/**
	 * The widget type of a single app tile.
	 *
	 * @var string
	 */
	public const APP_TILE_TYPE = 'app-tile';

	/**
	 * The Zuiddrecht colour of each tile colour token. A launcher tile stores
	 * a hex colour (seven characters), so it gets this value and keeps the
	 * token in its content.
	 *
	 * @var array<string, string>
	 */
	public const TOKEN_FALLBACKS = [
		'primary'      => '#3669a5',
		'primary-deep' => '#234a78',
		'accent'       => '#cc0000',
		'accent-deep'  => '#a30000',
		'work-ink'     => '#1b1c1d',
		'work-text'    => '#3d4047',
		'work-muted'   => '#5e6168',
		'work-field'   => '#828a95',
	];

	/**
	 * Constructor.
	 *
	 * @param array<string, array<string, mixed>> $tiles       The seed's tile catalogue, keyed by tile key.
	 * @param array<string, true>                 $knownTypes  The widget types this instance renders.
	 * @param array<string, true>                 $enabledApps The app ids enabled on this instance.
	 */
	public function __construct(
		private readonly array $tiles,
		private readonly array $knownTypes,
		private readonly array $enabledApps,
	) {
	}//end __construct()

	/**
	 * Expand a dashboard's widget entries.
	 *
	 * Sort order follows the entry order, so a re-run places the same
	 * widgets in the same order.
	 *
	 * @param array<int, array<string, mixed>> $entries The seed entries.
	 *
	 * @return array{placed: array<int, array<string, mixed>>, skipped: array<int, string>, fallbacks: array<int, string>}
	 *
	 * @spec openspec/changes/archive/2026-11-25-launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	public function expand(array $entries): array {
		$placed    = [];
		$skipped   = [];
		$fallbacks = [];
		$gaps      = [];
		$tiles     = [];
		foreach (array_values(array: $entries) as $order => $entry) {
			$payload = $this->resolve(entry: $entry);
			if ($payload === null) {
				$skipped[] = $this->labelOf(entry: $entry);
				if (isset($entry['tile']) === true) {
					$gaps[] = ['x' => (int)($entry['gridX'] ?? 0), 'y' => (int)($entry['gridY'] ?? 0), 'w' => (int)($entry['gridWidth'] ?? 2)];
				}

				continue;
			}

			if (isset($entry['tile']) === true) {
				$tiles[] = count($placed);
			}

			if ($payload['widgetId'] !== ($entry['widgetId'] ?? $payload['widgetId'])) {
				$fallbacks[] = (string)$entry['widgetId'] . ' -> ' . $payload['widgetId'];
			}

			$payload['sortOrder'] = $order;
			$placed[] = $payload;
		}

		$placed = (new DemoSeedTileGaps())->close(placed: $placed, tiles: $tiles, gaps: $gaps);

		return ['placed' => $placed, 'skipped' => $skipped, 'fallbacks' => $fallbacks];
	}//end expand()

	/**
	 * The payload one entry places, null when nothing it names can render.
	 *
	 * @param array<string, mixed> $entry The seed entry.
	 *
	 * @return array<string, mixed>|null The payload.
	 */
	private function resolve(array $entry): ?array {
		if (isset($entry['tile']) === true) {
			return $this->tilePayload(entry: $entry);
		}

		if ($this->isRenderable(entry: $entry) === true) {
			unset($entry['fallback']);
			return $entry;
		}

		$fallback = $entry['fallback'] ?? null;
		if (is_array($fallback) === true && $this->isRenderable(entry: $fallback) === true) {
			return $fallback;
		}

		return null;
	}//end resolve()

	/**
	 * Whether an entry's widget type is registered. An `nc-widget` also needs
	 * the Nextcloud dashboard widget it shows, otherwise it only says that
	 * the widget is unavailable.
	 *
	 * @param array<string, mixed> $entry The seed entry.
	 *
	 * @return bool True when the entry can render.
	 */
	private function isRenderable(array $entry): bool {
		$widgetId = (string)($entry['widgetId'] ?? '');
		if (isset($this->knownTypes[$widgetId]) === false) {
			return false;
		}

		if ($widgetId !== 'nc-widget') {
			return true;
		}

		return isset($this->knownTypes[(string)($entry['content']['widgetId'] ?? '')]) === true;
	}//end isRenderable()

	/**
	 * The payload of a tile entry: an app tile for an enabled app, a
	 * coloured launcher tile otherwise.
	 *
	 * @param array<string, mixed> $entry The seed entry, `{tile, gridX, gridY}`.
	 *
	 * @return array<string, mixed>|null The payload, null for an unknown tile key.
	 */
	private function tilePayload(array $entry): ?array {
		$tile = $this->tiles[(string)$entry['tile']] ?? null;
		if (is_array($tile) === false) {
			return null;
		}

		$layout = [
			'gridX'      => (int)($entry['gridX'] ?? 0),
			'gridY'      => (int)($entry['gridY'] ?? 0),
			'gridWidth'  => (int)($entry['gridWidth'] ?? 2),
			'gridHeight' => (int)($entry['gridHeight'] ?? 2),
			'showTitle'  => 0,
		];

		$fields = $this->tileFields(tile: $tile);
		if ($fields === null) {
			return null;
		}

		$payload = [...$layout, ...$fields];
		foreach (self::TILE_CONTENT_SETTINGS as $key => $type) {
			if (isset($entry[$key]) === true && gettype($entry[$key]) === $type) {
				$payload['content'][$key] = $entry[$key];
			}
		}

		return $payload;
	}//end tilePayload()

	/**
	 * The type-specific fields of a tile: an app tile for an enabled app when
	 * that type exists, a launcher tile otherwise.
	 *
	 * A tile whose address opens an app that is not enabled is left out: as a
	 * launcher tile it would link to a page that does not exist, and as an app
	 * tile it would show "not available" on a demo dashboard.
	 *
	 * @param array<string, mixed> $tile The catalogue tile.
	 *
	 * @return array<string, mixed>|null The placement fields, null when the tile cannot work here.
	 */
	private function tileFields(array $tile): ?array {
		$colour   = (string)($tile['colour'] ?? 'primary');
		$appId    = (string)($tile['appId'] ?? '');
		$enabled  = $appId !== '' && isset($this->enabledApps[$appId]) === true;
		$missing  = $appId !== '' && $enabled === false && str_contains((string)($tile['url'] ?? ''), '/apps/' . $appId . '/') === true;
		$appTiles = isset($this->knownTypes[self::APP_TILE_TYPE]);
		if ($missing === true) {
			return null;
		}

		if ($enabled === true && $appTiles === true) {
			$content = ['appId' => $appId, 'colour' => $colour, 'showCount' => true];
			// The board's glyph and the app's own counter (LpVandaag).
			if (is_string($tile['icon'] ?? null) === true) {
				$content['icon'] = $tile['icon'];
			}

			if (is_array($tile['countSource'] ?? null) === true) {
				$content['countSource'] = $tile['countSource'];
			}

			return [
				'widgetId'    => self::APP_TILE_TYPE,
				'customTitle' => (string)$tile['name'],
				'content'     => $content,
			];
		}

		return $this->launcherTileFields(tile: $tile, colour: $colour);
	}//end tileFields()

	/**
	 * The fields of a launcher tile in the tile's colour.
	 *
	 * @param array<string, mixed> $tile   The catalogue tile.
	 * @param string               $colour The colour token.
	 *
	 * @return array<string, mixed> The placement fields.
	 */
	private function launcherTileFields(array $tile, string $colour): array {
		// REQ-ICT-004: the tile renders through its theme token; REQ-ICT-002:
		// it reads its count live from its source, never a static value.
		$content = ['colour' => $colour, 'colourToken' => '--sc-' . $colour];
		$source  = $this->countSourceOf(source: $tile['countSource'] ?? null);
		if ($source !== null) {
			$content['countSource'] = $source;
		}

		return [
			'widgetId'            => 'tile',
			'tileType'            => 'custom',
			'tileTitle'           => (string)$tile['name'],
			'tileIcon'            => (string)($tile['icon'] ?? ''),
			'tileIconType'        => 'mdi',
			'tileBackgroundColor' => self::TOKEN_FALLBACKS[$colour] ?? self::TOKEN_FALLBACKS['primary'],
			'tileTextColor'       => '#ffffff',
			'tileLinkType'        => 'url',
			'tileLinkValue'       => (string)($tile['url'] ?? ''),
			'content'             => $content,
		];
	}//end launcherTileFields()

	/**
	 * A catalogue count source in the shape a launcher tile reads: a
	 * register source with its register, schema and filter, or the items of
	 * a Nextcloud dashboard widget.
	 *
	 * @param mixed $source The catalogue's count source.
	 *
	 * @return array<string, mixed>|null The tile's count source, null for none.
	 *
 * @spec openspec/changes/archive/2026-11-25-icon-only-app-tiles/specs/tiles/spec.md#requirement-an-app-tile-shows-a-live-count-req-ict-002
	 */
	private function countSourceOf(mixed $source): ?array {
		if (is_array($source) === false) {
			return null;
		}

		if (is_string($source['dashboardWidget'] ?? null) === true && $source['dashboardWidget'] !== '') {
			return ['type' => 'nc-widget', 'widgetId' => $source['dashboardWidget']];
		}

		if (is_string($source['register'] ?? null) === true && is_string($source['schema'] ?? null) === true) {
			$filter = [];
			if (is_array($source['filter'] ?? null) === true) {
				$filter = $source['filter'];
			}

			return ['type' => 'register', 'register' => $source['register'], 'schema' => $source['schema'], 'filter' => $filter];
		}

		return null;
	}//end countSourceOf()

	/**
	 * A readable name for a skipped entry.
	 *
	 * @param array<string, mixed> $entry The seed entry.
	 *
	 * @return string The widget type, with its title when it has one.
	 */
	private function labelOf(array $entry): string {
		if (isset($entry['tile']) === true) {
			return 'tile ' . (string)$entry['tile'];
		}

		$label = (string)($entry['widgetId'] ?? '?');
		if (isset($entry['customTitle']) === true) {
			$label .= ' (' . (string)$entry['customTitle'] . ')';
		}

		return $label;
	}//end labelOf()
}//end class
