<?php

/**
 * StarterWidgetSeeds
 *
 * The starter widgets of a person's first dashboard, as board LpEersteKeer
 * lists them: a tile per most-used app, calendar, mail, files, weather and the
 * organisation's news.
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
 *
 * @spec openspec/specs/runtime-shell/spec.md#req-shell-005
 * @spec openspec/changes/app-tile-widget/specs/app-tile-widget/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * The starter widget set, in the seed shape of the default widget bundle.
 *
 * Each widget works without configuration: an app tile opens one of the
 * person's apps, the calendar reads their personal calendar, mail is Nextcloud Mail's
 * own dashboard widget, the weather follows their weather status location and
 * the news is the organisation's announcements.
 *
 * @spec openspec/specs/runtime-shell/spec.md#req-shell-005
 */
final class StarterWidgetSeeds {
	/**
	 * The apps the starter set gives a tile, most used first. Only the ones in
	 * the person's own app menu get one.
	 *
	 * @var array<int, string>
	 */
	public const MOST_USED_APPS = ['files', 'mail', 'calendar', 'spreed', 'contacts', 'deck', 'tasks', 'notes'];

	/**
	 * How many app tiles the starter set places, in the eight columns left of
	 * the calendar.
	 *
	 * @var int
	 */
	public const APP_TILE_COUNT = 4;

	/**
	 * The app grid the starter set falls back to when the person's app menu is
	 * unknown, such as a dashboard created from the command line.
	 *
	 * @var array<string, mixed>
	 */
	public const APP_GRID_SEED = [
		// Layout-widgets-replace-multi-tile D3: a well that lists each viewer's apps.
		'widgetId' => 'container',
		'gridX' => 0,
		'gridY' => 0,
		'gridWidth' => 8,
		'gridHeight' => 4,
		'sortOrder' => 0,
		'tile' => null,
		'content' => [
			'placements' => [],
			'appList' => [
				'pinned' => [],
				'exclude' => ['launchpad'],
				'mostUsedLimit' => 4,
				'showFilter' => true,
			],
		],
	];

	/**
	 * The seeds after the apps, on a 12-column grid.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public const SEEDS = [
		[
			'widgetId' => 'calendar',
			'gridX' => 8,
			'gridY' => 0,
			'gridWidth' => 4,
			'gridHeight' => 4,
			'sortOrder' => 1,
			'tile' => null,
			'content' => [
				'viewMode' => 'agenda',
				'daysAhead' => 7,
				'internalCalendars' => ['personal'],
				'externalIcsUrls' => [],
			],
		],
		[
			'widgetId' => 'nc-widget',
			'gridX' => 0,
			'gridY' => 4,
			'gridWidth' => 6,
			'gridHeight' => 4,
			'sortOrder' => 2,
			'tile' => null,
			'content' => [
				'widgetId' => 'mail',
				'displayMode' => 'vertical',
			],
		],
		[
			'widgetId' => 'files',
			'gridX' => 6,
			'gridY' => 4,
			'gridWidth' => 6,
			'gridHeight' => 4,
			'sortOrder' => 3,
			'tile' => null,
		],
		[
			'widgetId' => 'weather',
			'gridX' => 0,
			'gridY' => 8,
			'gridWidth' => 4,
			'gridHeight' => 3,
			'sortOrder' => 4,
			'tile' => null,
		],
		[
			'widgetId' => 'announcements',
			'gridX' => 4,
			'gridY' => 8,
			'gridWidth' => 8,
			'gridHeight' => 3,
			'sortOrder' => 5,
			'tile' => null,
		],
	];

	/**
	 * The starter seeds for a person whose app menu holds these apps: a tile
	 * for each of the most-used apps in it, then the other starter widgets.
	 * Apps outside {@see self::MOST_USED_APPS} fill the row in menu order.
	 * With no app menu the apps are one app grid instead.
	 *
	 * @param array<int, string> $menuAppIds The app ids in the person's app menu, in menu order.
	 *
	 * @return array<int, array<string, mixed>> The seeds, in sort order.
	 *
	 * @spec openspec/changes/app-tile-widget/specs/app-tile-widget/spec.md#requirement-a-first-dashboard-starts-with-a-tile-per-most-used-app-req-apptile-003
	 */
	public static function seeds(array $menuAppIds): array {
		$apps = self::tileApps(menuAppIds: $menuAppIds);

		$seeds = [self::APP_GRID_SEED];
		if ($apps !== []) {
			$seeds = [];
			foreach ($apps as $index => $appId) {
				$seeds[] = [
					// REQ-APPTILE-001: an App or link tile in app mode.
					'widgetId' => 'tile',
					'gridX' => $index * 2,
					'gridY' => 0,
					'gridWidth' => 2,
					'gridHeight' => 2,
					'sortOrder' => 0,
					'tile' => null,
					'content' => ['mode' => 'app', 'appId' => $appId, 'colour' => '', 'textColour' => 'white', 'showCount' => true],
				];
			}
		}

		$seeds = array_merge($seeds, self::SEEDS);
		foreach (array_keys($seeds) as $order) {
			$seeds[$order]['sortOrder'] = $order;
		}

		return $seeds;
	}//end seeds()

	/**
	 * The apps that get a tile: the most-used ones in the menu first, then the
	 * rest of the menu, at most {@see self::APP_TILE_COUNT}.
	 *
	 * @param array<int, string> $menuAppIds The app ids in the person's app menu.
	 *
	 * @return array<int, string> The app ids.
	 */
	private static function tileApps(array $menuAppIds): array {
		$menu = array_values(array_unique(array_filter($menuAppIds, static fn ($id): bool => $id !== '' && $id !== 'launchpad')));
		$preferred = array_values(array_intersect(self::MOST_USED_APPS, $menu));
		$apps = array_merge($preferred, array_values(array_diff($menu, $preferred)));

		return array_slice($apps, 0, self::APP_TILE_COUNT);
	}//end tileApps()
}//end class
