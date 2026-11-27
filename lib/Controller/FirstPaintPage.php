<?php

/**
 * FirstPaintPage
 *
 * What the workspace template needs to print the first-paint shell
 * (first-paint-skeleton D1): the shell's view model, built from the dashboard
 * and placements the mounted page will draw first, and the reader's name and
 * time zone for the greeting row. The mounted page renders the
 * `dashboardFirstLoad.active` envelope (its placements after the role filter
 * and the visibility rules), so the shell is built from that envelope when it
 * is the opened dashboard; a deep link to another dashboard falls back to
 * that dashboard's `layout`. Nothing is read here that the page did not read.
 *
 * @category Controller
 * @package  OCA\LaunchPad\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use JsonSerializable;
use OCA\LaunchPad\Service\FirstPaintShell;
use OCP\IUser;

/**
 * Builds the template's first-paint parameter.
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
 */
class FirstPaintPage {

	/**
	 * The widget types that read through another app, and that app, as
	 * `requiresApp` in src/constants/widgetRegistry.js: the mounted page
	 * leaves them out for a viewer without the app (`offeredForViewer`), so
	 * the shell does too. FirstPaintPageTest keeps the two lists equal.
	 */
	public const REQUIRES_APP = [
		'wiki-pages'     => 'openregister',
		'work-packages'  => 'integration_openproject',
		'assigned-cases' => 'dossiq',
		'connections'    => 'integriq',
	];

	/**
	 * The shell the template prints, or null when the page opens without a
	 * dashboard (REQ-FPS-006).
	 *
	 * @param array|null           $firstLoad   The `dashboardFirstLoad` the page carries, or null.
	 * @param array<string, mixed> $activeState The page's active-dashboard state (activeDashboardId, dashboardSource, layout).
	 * @param array|null           $active      The opened dashboard as the page resolved it (`dashboard` entity), or null.
	 * @param array                $widgets     The widget catalogue the page carries.
	 * @param array<string, mixed> $reader      The reader: `user` (IUser or null) and `timezone`.
	 * @param array<string, mixed> $apps        The `integrationApps` the page carries: app id to true, or to its installed id.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @spec openspec/changes/first-paint-skeleton/tasks.md#task-3
	 */
	public static function model(
		?array $firstLoad,
		array $activeState,
		?array $active,
		array $widgets,
		array $reader,
		array $apps=[]
	): ?array {
		$uuid = (string) ($activeState['activeDashboardId'] ?? '');
		if ($firstLoad === null || $uuid === '') {
			return null;
		}

		$dashboard = [];
		if (($active['dashboard'] ?? null) instanceof JsonSerializable) {
			$dashboard = (array) $active['dashboard']->jsonSerialize();
		}

		$placements = (array) ($activeState['layout'] ?? []);
		$envelope   = $firstLoad['active'] ?? null;
		if (is_array($envelope) === true && (string) ($envelope['dashboard']['uuid'] ?? '') === $uuid) {
			$dashboard  = (array) $envelope['dashboard'];
			$placements = (array) ($envelope['placements'] ?? []);
		}

		if ($dashboard === []) {
			return null;
		}

		$placements = array_values(
			array_filter(
				$placements,
				static fn (mixed $placement): bool => is_array($placement) === true
					&& self::offered(widgetId: (string) ($placement['widgetId'] ?? ''), apps: $apps)
			)
		);

		$shell = (new FirstPaintShell())->build(
			page: ['dashboard' => $dashboard, 'placements' => $placements],
			catalogue: array_values(array_filter($widgets, 'is_array'))
		);

		return $shell + [
			'source'    => (string) ($activeState['dashboardSource'] ?? ''),
			'isDefault' => (int) ($dashboard['isDefault'] ?? 0) === 1,
			'reader'    => [
				'name'     => self::displayName(user: $reader['user'] ?? null),
				'timezone' => (string) ($reader['timezone'] ?? ''),
			],
		];
	}//end model()

	/**
	 * The reader's display name, '' without a user.
	 *
	 * @param mixed $user The IUser, or null.
	 *
	 * @return string
	 */
	private static function displayName(mixed $user): string {
		if ($user instanceof IUser) {
			return $user->getDisplayName();
		}

		return '';
	}//end displayName()

	/**
	 * Whether the mounted page shows a widget type to this viewer: always,
	 * unless it reads through an app that is not enabled for them.
	 *
	 * @param string               $widgetId The widget type.
	 * @param array<string, mixed> $apps     The `integrationApps` state.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/first-paint-skeleton/tasks.md#task-3
	 */
	public static function offered(string $widgetId, array $apps): bool {
		$app = self::REQUIRES_APP[$widgetId] ?? null;
		if ($app === null) {
			return true;
		}

		$enabled = $apps[$app] ?? false;

		return $enabled === true || (is_string($enabled) === true && $enabled !== '');
	}//end offered()
}//end class
