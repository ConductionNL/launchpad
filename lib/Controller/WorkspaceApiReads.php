<?php

/**
 * WorkspaceApiReads
 *
 * The API reads the workspace view makes right after it mounts (the notice
 * banner, the active dashboard's reactions and personal layer, the first page
 * of every Files widget), answered while the page renders so the page carries
 * them in its initial state instead of the browser asking for each one.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-27-workspace-api-reads-from-initial-state/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Db\Dashboard;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Answers the workspace's first API reads in-process, through the very
 * controller actions the browser would call, so every access check and every
 * payload stays exactly the one the endpoint gives.
 *
 * @spec openspec/changes/archive/2026-11-27-workspace-api-reads-from-initial-state/tasks.md#task-1
 */
class WorkspaceApiReads {

	/**
	 * The query of the first page a Files widget asks for
	 * (FilesWidget.vue `contentsParams()` at the root, not appending).
	 */
	public const FILES_FIRST_PAGE = ['currentPath' => '/', 'limit' => 50];

	/**
	 * At most this many Files widgets get their first page carried; the rest
	 * ask, so a crowded dashboard cannot make the page itself slow.
	 */
	public const MAX_FILES_WIDGETS = 4;

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container The app container; a controller is
	 *                                      built only when its read is made.
	 * @param LoggerInterface    $logger    Logs a read that failed.
	 *
	 * @spec openspec/changes/archive/2026-11-27-workspace-api-reads-from-initial-state/tasks.md#task-1
	 */
	public function __construct(
		private readonly ContainerInterface $container,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The reads, as `[{path, params, data}]`: `path` below `/apps/launchpad`,
	 * `params` the query the client sends, `data` exactly what the endpoint
	 * answers. A read that is refused, fails or answers anything but 200 is
	 * left out, so the browser asks for it and meets the same answer.
	 *
	 * @param string $userId  The viewer, or '' when nobody is logged in.
	 * @param array  $opening `{dashboard, layout}`: the Dashboard the page
	 *                        opens on (or null) and its serialised placements.
	 *
	 * @return list<array{path: string, params: array<string, mixed>, data: mixed}>|null The reads, or null when none.
	 *
	 * @spec openspec/changes/archive/2026-11-27-workspace-api-reads-from-initial-state/tasks.md#task-1
	 */
	public function firstLoad(string $userId, array $opening): ?array {
		if ($userId === '') {
			return null;
		}

		$reads = [
			$this->read(
				request: ['path' => '/api/announcements'],
				read: fn (): mixed => $this->container->get(AnnouncementController::class)->index()
			),
			$this->read(
				request: ['path' => '/api/preferences/dismissed-notices'],
				read: fn (): mixed => $this->container->get(PreferencesController::class)->getPreference(key: 'dismissed-notices')
			),
		];

		$dashboard = $opening['dashboard'] ?? null;
		if ($dashboard instanceof Dashboard) {
			$reads = [
				...$reads,
				...$this->dashboardReads(userId: $userId, dashboard: $dashboard),
				...$this->filesReads(layout: (array)($opening['layout'] ?? [])),
			];
		}

		$reads = array_values(array_filter($reads, static fn (?array $read): bool => $read !== null));
		if ($reads === []) {
			return null;
		}

		return $reads;
	}//end firstLoad()

	/**
	 * The reads that belong to the dashboard the page opens on: its reactions,
	 * and the viewer's personal layer when somebody else owns it (the
	 * workspace reads it only then).
	 *
	 * @param string    $userId    The viewer.
	 * @param Dashboard $dashboard The dashboard.
	 *
	 * @return list<array|null> The reads, null where one was left out.
	 *
	 * @spec openspec/changes/archive/2026-11-27-workspace-api-reads-from-initial-state/tasks.md#task-1
	 */
	private function dashboardReads(string $userId, Dashboard $dashboard): array {
		$reads = [];
		$uuid = (string)$dashboard->getUuid();
		if ($uuid !== '') {
			$reads[] = $this->read(
				request: ['path' => '/api/dashboards/'.rawurlencode($uuid).'/reactions'],
				read: fn (): mixed => $this->container->get(DashboardReactionApiController::class)->getReactions(uuid: $uuid)
			);
		}

		$dashboardId = (int)$dashboard->getId();
		if ($dashboardId > 0 && $dashboard->getUserId() !== $userId) {
			$reads[] = $this->read(
				request: ['path' => '/api/dashboards/'.$dashboardId.'/personal-layer'],
				read: fn (): mixed => $this->container->get(PersonalLayerApiController::class)->show(dashboardId: $dashboardId)
			);
		}

		return $reads;
	}//end dashboardReads()

	/**
	 * The first page of each visible Files widget on the layout.
	 *
	 * @param array $layout The serialised placements.
	 *
	 * @return list<array|null> The reads, null where one was left out.
	 *
	 * @spec openspec/changes/archive/2026-11-27-workspace-api-reads-from-initial-state/tasks.md#task-1
	 */
	private function filesReads(array $layout): array {
		$reads = [];
		foreach ($this->filesPlacementIds(layout: $layout) as $placementId) {
			$reads[] = $this->read(
				request: ['path' => '/api/widgets/files/'.$placementId.'/contents', 'params' => self::FILES_FIRST_PAGE],
				read: fn (): mixed => $this->container->get(FilesWidgetController::class)->contents(
					placementId: $placementId,
					currentPath: self::FILES_FIRST_PAGE['currentPath'],
					limit: self::FILES_FIRST_PAGE['limit']
				)
			);
		}

		return $reads;
	}//end filesReads()

	/**
	 * The ids of the visible Files widgets on the layout, at most
	 * {@see self::MAX_FILES_WIDGETS}.
	 *
	 * @param array $layout The serialised placements.
	 *
	 * @return list<int> The placement ids.
	 *
	 * @spec openspec/changes/archive/2026-11-27-workspace-api-reads-from-initial-state/tasks.md#task-1
	 */
	private function filesPlacementIds(array $layout): array {
		$ids = [];
		foreach ($layout as $placement) {
			if (is_array($placement) === false
				|| ($placement['widgetId'] ?? '') !== 'files'
				|| (int)($placement['isVisible'] ?? 1) !== 1
				|| (int)($placement['id'] ?? 0) <= 0
			) {
				continue;
			}

			$ids[] = (int)$placement['id'];
			if (count($ids) === self::MAX_FILES_WIDGETS) {
				break;
			}
		}

		return $ids;
	}//end filesPlacementIds()

	/**
	 * Make one read; keep it only when it answered 200.
	 *
	 * @param array{path: string, params?: array<string, mixed>} $request The endpoint below `/apps/launchpad` and the query the client sends.
	 * @param callable(): mixed                                   $read    Calls the controller action.
	 *
	 * @return array{path: string, params: array<string, mixed>, data: mixed}|null The read, or null when left out.
	 *
	 * @spec openspec/changes/archive/2026-11-27-workspace-api-reads-from-initial-state/tasks.md#task-1
	 */
	private function read(array $request, callable $read): ?array {
		try {
			$response = $read();
		} catch (Throwable $e) {
			$this->logger->warning(
				'LaunchPad: '.$request['path'].' left out of the page: '.$e->getMessage(),
				['app' => 'launchpad']
			);
			return null;
		}

		if (($response instanceof JSONResponse) === false || $response->getStatus() !== Http::STATUS_OK) {
			return null;
		}

		return ['path' => $request['path'], 'params' => $request['params'] ?? [], 'data' => $response->getData()];
	}//end read()
}//end class
