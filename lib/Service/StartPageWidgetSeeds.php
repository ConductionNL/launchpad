<?php

/**
 * StartPageWidgetSeeds
 *
 * The widgets of the organisation's start page, as board LpVandaag draws it:
 * the greeting with the weather, what needs attention today, the working day
 * (mail, calendar, where you left off), news and colleagues, and the
 * tiles.
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
 * @spec openspec/specs/default-widget-bundle/spec.md#requirement-req-dwb-008-the-start-page-is-the-vandaag-layout
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\IL10N;

/**
 * The start page seed, in the seed shape of the default widget bundle.
 *
 * Every widget works on a plain Nextcloud without demo data or a case app:
 * no widget names a register, a wiki space or an organisation's own text.
 * The brand tiles are the default bundle's own tiles, so a change to their
 * look (REQ-DWB-007) reaches the start page as well.
 *
 * @spec openspec/specs/default-widget-bundle/spec.md#requirement-req-dwb-008-the-start-page-is-the-vandaag-layout
 */
final class StartPageWidgetSeeds {
	/**
	 * The slug of the start page, which marks it as seeded by LaunchPad.
	 *
	 * @var string
	 */
	public const SLUG = 'start';

	/**
	 * The start page seeds on a 12-column grid, in sort order.
	 *
	 * @param IL10N                            $l10n       Translates the titles into the instance language.
	 * @param array<int, array<string, mixed>> $brandTiles The default bundle's tile seeds (their `tile` set).
	 *
	 * @return array<int, array<string, mixed>> The seeds.
	 *
	 * @spec openspec/specs/default-widget-bundle/spec.md#requirement-req-dwb-008-the-start-page-is-the-vandaag-layout
	 */
	public static function seeds(IL10N $l10n, array $brandTiles): array {
		$seeds = array_merge(
			self::today(l10n: $l10n),
			self::workingDay(l10n: $l10n),
			self::newsAndColleagues(l10n: $l10n),
			self::apps(l10n: $l10n, brandTiles: $brandTiles)
		);
		foreach (array_keys($seeds) as $order) {
			$seeds[$order]['sortOrder'] = $order;
		}

		return $seeds;
	}//end seeds()

	/**
	 * The greeting with the weather, then what needs attention today.
	 *
	 * @param IL10N $l10n Translates the titles.
	 *
	 * @return array<int, array<string, mixed>> The seeds.
	 */
	private static function today(IL10N $l10n): array {
		return [
			self::widget(type: 'header', grid: [0, 0, 10, 2], content: [
				'title' => $l10n->t('Today'),
				'greeting' => true,
				'showDate' => true,
				'showWeek' => true,
				'greetingLine' => true,
			]),
			self::widget(type: 'weather', grid: [10, 0, 2, 2], content: ['location' => '', 'showActions' => false]),
			self::widget(type: 'attention', grid: [0, 2, 12, 3], content: [
				'title' => $l10n->t('First thing today'),
				'layout' => 'columns',
				'columns' => 2,
				'limit' => 2,
				'showActions' => false,
				'itemCards' => true,
			]),
		];
	}//end today()

	/**
	 * The working day: unread mail, today's calendar and recent work.
	 *
	 * @param IL10N $l10n Translates the titles.
	 *
	 * @return array<int, array<string, mixed>> The seeds.
	 */
	private static function workingDay(IL10N $l10n): array {
		return [
			self::heading(grid: [0, 5, 12, 1], title: $l10n->t('At work today'), description: $l10n->t('Mail, calendar and where you left off')),
			self::widget(type: 'nc-widget', grid: [0, 6, 4, 7], title: $l10n->t('Unread mail'), content: [
				'widgetId' => 'mail-unread',
				'displayMode' => 'vertical',
				'showActions' => false,
				'showHeader' => false,
			]),
			self::widget(type: 'calendar', grid: [4, 6, 4, 7], title: $l10n->t('Calendar today'), content: [
				'viewMode' => 'agenda',
				'daysAhead' => 0,
				'internalCalendars' => ['personal'],
				'externalIcsUrls' => [],
				'showActions' => false,
				'agendaLayout' => 'list',
			]),
			self::widget(type: 'recent-work', grid: [8, 6, 4, 7], title: $l10n->t('Pick up where you left off'), content: [
				'limit' => 6,
				'showActions' => false,
				'apps' => ['files', 'deck', 'dav'],
			]),
		];
	}//end workingDay()

	/**
	 * The organisation's news and its people.
	 *
	 * @param IL10N $l10n Translates the titles.
	 *
	 * @return array<int, array<string, mixed>> The seeds.
	 */
	private static function newsAndColleagues(IL10N $l10n): array {
		$description = $l10n->t('The organisation\'s news and who works here');

		return [
			self::heading(grid: [0, 13, 12, 1], title: $l10n->t('News and colleagues'), description: $description),
			self::widget(
				type: 'announcements',
				grid: [0, 14, 6, 5],
				title: $l10n->t('News'),
				content: ['category' => '', 'limit' => 4, 'showActions' => false]
			),
			self::widget(type: 'people', grid: [6, 14, 6, 5], title: $l10n->t('Find a colleague'), content: [
				'layout' => 'list',
				'filters' => [],
				'excludeDisabled' => true,
				'sortBy' => 'displayName',
				'columns' => 2,
				'showActions' => false,
			]),
		];
	}//end newsAndColleagues()

	/**
	 * The app tiles beside the organisation's brand tiles.
	 *
	 * @param IL10N                            $l10n       Translates the titles.
	 * @param array<int, array<string, mixed>> $brandTiles The default bundle's seeds; those with a `tile` set are placed.
	 *
	 * @return array<int, array<string, mixed>> The seeds.
	 */
	private static function apps(IL10N $l10n, array $brandTiles): array {
		$seeds = [
			self::heading(grid: [0, 19, 6, 1], title: $l10n->t('My apps'), description: $l10n->t('One tile per app')),
			self::heading(grid: [6, 19, 6, 1], title: $l10n->t('Working together'), description: $l10n->t('The organisation\'s tiles')),
			self::widget(type: 'app-tiles', grid: [0, 20, 6, 4], content: [
				'pinned' => [],
				'exclude' => ['launchpad'],
				'showFilter' => false,
				'showMostUsed' => true,
				'mostUsedLimit' => 6,
			]),
		];

		$column = 6;
		foreach ($brandTiles as $brand) {
			if (($brand['tile'] ?? null) === null || $column > 10) {
				continue;
			}

			$seeds[] = [
				'widgetId' => 'tile',
				'gridX' => $column,
				'gridY' => 20,
				'gridWidth' => 2,
				'gridHeight' => 3,
				'sortOrder' => 0,
				'tile' => $brand['tile'],
			];
			$column += 2;
		}

		return $seeds;
	}//end apps()

	/**
	 * One widget seed.
	 *
	 * @param string               $type    The widget type.
	 * @param array<int, int>      $grid    x, y, width, height.
	 * @param array<string, mixed> $content The widget content.
	 * @param string|null          $title   The card title, or null for none.
	 *
	 * @return array<string, mixed> The seed.
	 */
	private static function widget(string $type, array $grid, array $content, ?string $title=null): array {
		$seed = [
			'widgetId' => $type,
			'gridX' => $grid[0],
			'gridY' => $grid[1],
			'gridWidth' => $grid[2],
			'gridHeight' => $grid[3],
			'sortOrder' => 0,
			'tile' => null,
			'showTitle' => 0,
			'content' => $content,
		];
		if ($title !== null) {
			$seed['showTitle'] = 1;
			$seed['customTitle'] = $title;
			$seed['content']['title'] = $title;
		}

		return $seed;
	}//end widget()

	/**
	 * A section heading seed.
	 *
	 * @param array<int, int> $grid        x, y, width, height.
	 * @param string          $title       The heading.
	 * @param string          $description The line under it.
	 *
	 * @return array<string, mixed> The seed.
	 */
	private static function heading(array $grid, string $title, string $description): array {
		return self::widget(type: 'section-heading', grid: $grid, content: ['title' => $title, 'description' => $description]);
	}//end heading()
}//end class
