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
use OCA\LaunchPad\Service\InitialStateBuilder;
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
	 * The template's parameters: the shell, drawn from the values the page's
	 * initial state carries, and the page's own address for the
	 * could-not-load line's Reload link.
	 *
	 * @param InitialStateBuilder  $state   The page's initial state, filled.
	 * @param array<string, mixed> $context `active` (the opened dashboard as resolved), `user` (IUser or null), `reloadUrl`.
	 *
	 * @return array{firstPaint: array<string, mixed>|null, reloadUrl: string}
	 *
	 * @spec openspec/changes/first-paint-skeleton/tasks.md#task-3
	 */
	public static function params(InitialStateBuilder $state, array $context): array {
		$page = [
			'firstLoad'         => $state->value(key: 'dashboardFirstLoad'),
			'activeDashboardId' => $state->value(key: 'activeDashboardId'),
			'dashboardSource'   => $state->value(key: 'dashboardSource'),
			'layout'            => $state->value(key: 'layout'),
			'widgets'           => $state->value(key: 'widgets'),
			'apps'              => $state->value(key: 'integrationApps'),
			'active'            => $context['active'] ?? null,
		];

		return [
			'firstPaint' => self::model(page: $page, reader: ['user' => $context['user'] ?? null, 'timezone' => $state->value(key: 'userTimezone')]),
			'reloadUrl'  => (string) ($context['reloadUrl'] ?? ''),
		];
	}//end params()

	/**
	 * The shell the template prints, or null when the page opens without a
	 * dashboard (REQ-FPS-006).
	 *
	 * @param array<string, mixed> $page   `firstLoad`, `activeDashboardId`, `dashboardSource`, `layout`, `widgets` and `apps`
	 *                                     as the initial state carries them, and `active`, the opened dashboard as resolved.
	 * @param array<string, mixed> $reader `user` (IUser or null) and `timezone`.
	 *
	 * @return array<string, mixed>|null
	 *
	 * @spec openspec/changes/first-paint-skeleton/tasks.md#task-3
	 */
	public static function model(array $page, array $reader): ?array {
		$uuid      = (string) ($page['activeDashboardId'] ?? '');
		$firstLoad = $page['firstLoad'] ?? null;
		if (is_array($firstLoad) === false || $uuid === '') {
			return null;
		}

		[$dashboard, $placements] = self::opened(page: $page, envelope: $firstLoad['active'] ?? null, uuid: $uuid);
		if ($dashboard === []) {
			return null;
		}

		$apps  = (array) ($page['apps'] ?? []);
		$shell = (new FirstPaintShell())->build(
			page: [
				'dashboard'  => $dashboard,
				'placements' => array_values(
					array_filter(
						$placements,
						static fn (mixed $placement): bool => is_array($placement) === true
							&& self::offered(widgetId: (string) ($placement['widgetId'] ?? ''), apps: $apps)
					)
				),
			],
			catalogue: array_values(array_filter((array) ($page['widgets'] ?? []), 'is_array'))
		);

		return $shell + [
			'source'    => (string) ($page['dashboardSource'] ?? ''),
			'isDefault' => (int) ($dashboard['isDefault'] ?? 0) === 1,
			'reader'    => [
				'name'     => self::displayName(user: $reader['user'] ?? null),
				'timezone' => (string) ($reader['timezone'] ?? ''),
			],
		];
	}//end model()

	/**
	 * The dashboard and placements the mounted page draws first: the store
	 * renders the first-load envelope (placements after the visibility rules)
	 * when it is the opened dashboard; a deep link to another dashboard draws
	 * that dashboard's `layout`.
	 *
	 * @param array<string, mixed> $page     The page values (see model()).
	 * @param mixed                $envelope The first load's `active` envelope.
	 * @param string               $uuid     The opened dashboard's uuid.
	 *
	 * @return array{0: array<string, mixed>, 1: array}
	 */
	private static function opened(array $page, mixed $envelope, string $uuid): array {
		if (is_array($envelope) === true && (string) ($envelope['dashboard']['uuid'] ?? '') === $uuid) {
			return [(array) $envelope['dashboard'], (array) ($envelope['placements'] ?? [])];
		}

		$dashboard = [];
		if (($page['active']['dashboard'] ?? null) instanceof JsonSerializable) {
			$dashboard = (array) $page['active']['dashboard']->jsonSerialize();
		}

		return [$dashboard, (array) ($page['layout'] ?? [])];
	}//end opened()

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
