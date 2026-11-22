<?php

/**
 * StarterWidgetSeeds
 *
 * The starter widgets of a person's first dashboard, as board LpEersteKeer
 * lists them: apps, calendar, mail, files, weather and the organisation's news.
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
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

/**
 * The starter widget set, in the seed shape of the default widget bundle.
 *
 * Each widget works without configuration: the apps grid lists the person's
 * apps, the calendar reads their personal calendar, mail is Nextcloud Mail's
 * own dashboard widget, the weather follows their weather status location and
 * the news is the organisation's announcements.
 *
 * @spec openspec/specs/runtime-shell/spec.md#req-shell-005
 */
final class StarterWidgetSeeds {
	/**
	 * The seeds, on a 12-column grid.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public const SEEDS = [
		[
			'widgetId' => 'app-tiles',
			'gridX' => 0,
			'gridY' => 0,
			'gridWidth' => 8,
			'gridHeight' => 4,
			'sortOrder' => 0,
			'tile' => null,
			'content' => [
				'pinned' => [],
				'exclude' => ['launchpad'],
				'showFilter' => true,
				'showMostUsed' => true,
				'mostUsedLimit' => 4,
			],
		],
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
}//end class
