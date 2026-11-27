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
 * @spec openspec/changes/workspace-api-reads-from-initial-state/tasks.md#task-1
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
 * @spec openspec/changes/workspace-api-reads-from-initial-state/tasks.md#task-1
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
	 * @spec openspec/changes/workspace-api-reads-from-initial-state/tasks.md#task-1
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
	 * @param string         $userId    The viewer, or '' when nobody is logged in.
	 * @param Dashboard|null $dashboard The dashboard the page opens on, or null.
	 * @param array          $layout    That dashboard's serialised placements.
	 *
	 * @return list<array{path: string, params: array<string, mixed>, data: mixed}>|null The reads, or null when none.
	 *
	 * @spec openspec/changes/workspace-api-reads-from-initial-state/tasks.md#task-1
	 */
	public function firstLoad(string $userId, ?Dashboard $dashboard, array $layout): ?array {
		if ($userId === '') {
			return null;
		}

		$reads = [];
		$this->add(
			reads: $reads,
			path: '/api/announcements',
			params: [],
			read: fn (): mixed => $this->container->get(AnnouncementController::class)->index()
		);
		$this->add(
			reads: $reads,
			path: '/api/preferences/dismissed-notices',
			params: [],
			read: fn (): mixed => $this->container->get(PreferencesController::class)->getPreference(key: 'dismissed-notices')
		);

		if ($dashboard !== null) {
			$this->addDashboardReads(reads: $reads, userId: $userId, dashboard: $dashboard, layout: $layout);
		}

		if ($reads === []) {
			return null;
		}

		return $reads;
	}//end firstLoad()

	/**
	 * The reads that belong to the dashboard the page opens on: its reactions,
	 * the viewer's personal layer when somebody else owns it (the workspace
	 * reads it only then), and the first page of each visible Files widget.
	 *
	 * @param array     $reads     The reads so far, appended to.
	 * @param string    $userId    The viewer.
	 * @param Dashboard $dashboard The dashboard.
	 * @param array     $layout    Its serialised placements.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/workspace-api-reads-from-initial-state/tasks.md#task-1
	 */
	private function addDashboardReads(array &$reads, string $userId, Dashboard $dashboard, array $layout): void {
		$uuid = (string)$dashboard->getUuid();
		if ($uuid !== '') {
			$this->add(
				reads: $reads,
				path: '/api/dashboards/'.rawurlencode($uuid).'/reactions',
				params: [],
				read: fn (): mixed => $this->container->get(DashboardReactionApiController::class)->getReactions(uuid: $uuid)
			);
		}

		$dashboardId = (int)$dashboard->getId();
		if ($dashboardId > 0 && $dashboard->getUserId() !== $userId) {
			$this->add(
				reads: $reads,
				path: '/api/dashboards/'.$dashboardId.'/personal-layer',
				params: [],
				read: fn (): mixed => $this->container->get(PersonalLayerApiController::class)->show(dashboardId: $dashboardId)
			);
		}

		foreach ($this->filesPlacementIds(layout: $layout) as $placementId) {
			$this->add(
				reads: $reads,
				path: '/api/widgets/files/'.$placementId.'/contents',
				params: self::FILES_FIRST_PAGE,
				read: fn (): mixed => $this->container->get(FilesWidgetController::class)->contents(
					placementId: $placementId,
					currentPath: self::FILES_FIRST_PAGE['currentPath'],
					limit: self::FILES_FIRST_PAGE['limit']
				)
			);
		}
	}//end addDashboardReads()

	/**
	 * The ids of the visible Files widgets on the layout, at most
	 * {@see self::MAX_FILES_WIDGETS}.
	 *
	 * @param array $layout The serialised placements.
	 *
	 * @return list<int> The placement ids.
	 *
	 * @spec openspec/changes/workspace-api-reads-from-initial-state/tasks.md#task-1
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
	 * Make one read and keep it when it answered 200.
	 *
	 * @param array                    $reads  The reads so far, appended to.
	 * @param string                   $path   The endpoint below `/apps/launchpad`.
	 * @param array<string, mixed>     $params The query the client sends.
	 * @param callable(): mixed        $read   Calls the controller action.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/workspace-api-reads-from-initial-state/tasks.md#task-1
	 */
	private function add(array &$reads, string $path, array $params, callable $read): void {
		try {
			$response = $read();
		} catch (Throwable $e) {
			$this->logger->warning(
				'LaunchPad: '.$path.' left out of the page: '.$e->getMessage(),
				['app' => 'launchpad']
			);
			return;
		}

		if (($response instanceof JSONResponse) === false || $response->getStatus() !== Http::STATUS_OK) {
			return;
		}

		$reads[] = ['path' => $path, 'params' => $params, 'data' => $response->getData()];
	}//end add()
}//end class
