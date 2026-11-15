<?php

/**
 * WidgetPlacementMapper
 *
 * Database mapper for widget placements.
 *
 * @category  Database
 * @package   OCA\LaunchPad\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Db;

use DateTime;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * WidgetPlacementMapper
 *
 * Mapper for widget placement entities.
 *
 * @extends QBMapper<WidgetPlacement>
 * @spec    openspec/changes/launchpad-legacy-quality-cleanup/tasks.md#task-1
 */
class WidgetPlacementMapper extends QBMapper {
	use ColumnMatchQueries;
	use OrphanRowQueries;

	/**
	 * Parent link for the orphan queries: alias, parent table, parent alias, foreign key.
	 */
	private const ORPHAN_LINK = ['p', 'launchpad_dashboards', 'd', 'dashboard_id'];

	/**
	 * Constructor
	 *
	 * @param IDBConnection $db The database connection.
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct(
			db: $db,
			tableName: 'launchpad_widget_placements',
			entityClass: WidgetPlacement::class
		);
	}//end __construct()

	/**
	 * Find placement by ID.
	 *
	 * @param int $id The placement ID.
	 *
	 * @return WidgetPlacement The found placement.
	 *
	 * @throws DoesNotExistException If not found.
	 * @spec   openspec/changes/launchpad-legacy-quality-cleanup/tasks.md#task-1
	 */
	public function find(int $id): WidgetPlacement {
		$qb = $this->selectWhere(match: ['id' => $id]);

		return $this->findEntity(query: $qb);
	}//end find()

	/**
	 * Find all placements for a dashboard.
	 *
	 * @param int $dashboardId The dashboard ID.
	 *
	 * @return WidgetPlacement[] The list of placements.
	 * @spec   openspec/changes/launchpad-legacy-quality-cleanup/tasks.md#task-1
	 */
	public function findByDashboardId(int $dashboardId): array {
		$qb = $this->selectWhere(match: ['dashboard_id' => $dashboardId]);
		$qb->orderBy(sort: 'sort_order', order: 'ASC')
			->addOrderBy(sort: 'grid_y', order: 'ASC')
			->addOrderBy(sort: 'grid_x', order: 'ASC');

		return $this->findEntities(query: $qb);
	}//end findByDashboardId()

	/**
	 * Find all placements that reference a given widget id across the
	 * entire instance — used by {@see \OCA\LaunchPad\Service\FeedRefreshService::discoverFeedUrls()}
	 * to pull every news-widget placement before each refresh tick
	 * (REQ-FRJ-003).
	 *
	 * @param string $widgetId The widget id (e.g. `'launchpad_news'`).
	 *
	 * @return WidgetPlacement[] The matching placements.
	 * @spec   openspec/changes/launchpad-legacy-quality-cleanup/tasks.md#task-1
	 */
	public function findByWidgetId(string $widgetId): array {
		$qb = $this->selectWhere(match: ['widget_id' => $widgetId]);

		return $this->findEntities(query: $qb);
	}//end findByWidgetId()

	/**
	 * Return every widget placement in the instance, unfiltered.
	 *
	 * Used by {@see \OCA\LaunchPad\Service\HealthPingService::refreshDuePlacements()}
	 * to discover ping-enabled tiles: the `healthPingEnabled` flag lives in
	 * the generic `content` JSON blob rather than under a dedicated widget
	 * id (a tile placement's `widget_id` is a per-instance value like
	 * `tile-<uniqid>`, not a fixed constant), so enumeration cannot be
	 * narrowed at the SQL layer the way {@see self::findByWidgetId()}
	 * narrows the news widget. The caller filters in PHP via
	 * `getContentArray()['healthPingEnabled']`.
	 *
	 * @return WidgetPlacement[] Every placement.
	 * @spec   openspec/specs/service-health-ping/spec.md
	 */
	public function findAll(): array {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from(from: $this->getTableName())
			->orderBy(sort: 'id', order: 'ASC');

		return $this->findEntities(query: $qb);
	}//end findAll()

	/**
	 * Find every placement carrying a given `announcementKey`, across all
	 * dashboards. Backs the read-receipt report's audience resolution: the
	 * blueprint (template) placement and every cloned recipient placement
	 * share one key (REQ-ACK-004, design D2).
	 *
	 * @param string $announcementKey The stable announcement identity.
	 *
	 * @return WidgetPlacement[] The matching placements (may be empty).
	 * @spec   openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
	 */
	public function findByAnnouncementKey(string $announcementKey): array {
		$qb = $this->selectWhere(match: ['announcement_key' => $announcementKey]);

		return $this->findEntities(query: $qb);
	}//end findByAnnouncementKey()

	/**
	 * Delete all placements for a dashboard.
	 *
	 * @param int $dashboardId The dashboard ID.
	 *
	 * @return void
	 * @spec   openspec/changes/launchpad-legacy-quality-cleanup/tasks.md#task-1
	 */
	public function deleteByDashboardId(int $dashboardId): void {
		$qb = $this->deleteWhere(match: ['dashboard_id' => $dashboardId]);

		$qb->executeStatement();
	}//end deleteByDashboardId()

	/**
	 * Delete all placements for a dashboard identified by UUID.
	 *
	 * Used by the cascade-event listener path where only the UUID is
	 * available (the int ID is not carried by DashboardDeletedEvent).
	 * Executes a sub-select to translate UUID → id so no additional
	 * mapper is required in the listener. C4 fix (REQ-CSC-003).
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 *
	 * @return int The number of rows deleted.
	 *
	 * @spec openspec/changes/launchpad-legacy-quality-cleanup/tasks.md#task-1
	 */
	public function deleteByDashboardUuid(string $dashboardUuid): int {
		$qb = $this->db->getQueryBuilder();
		$qb->delete(delete: $this->getTableName())
			->where(
				$qb->expr()->in(
					x: 'dashboard_id',
					y: $qb->createFunction(
						call: '(SELECT id FROM `*PREFIX*launchpad_dashboards` WHERE `uuid` = '
							. $qb->createNamedParameter(value: $dashboardUuid) . ')'
					)
				)
			);

		return $qb->executeStatement();
	}//end deleteByDashboardUuid()

	/**
	 * Update grid positions for multiple placements.
	 *
	 * @param array $updates Array of position updates.
	 *
	 * @return void
	 * @spec   openspec/changes/launchpad-legacy-quality-cleanup/tasks.md#task-1
	 */
	public function updatePositions(array $updates): void {
		foreach ($updates as $update) {
			$this->updatePosition(update: $update);
		}
	}//end updatePositions()

	/**
	 * Write the grid position of one placement and stamp `updated_at`.
	 *
	 * Missing coordinates default to 0, missing sizes to 4.
	 *
	 * @param mixed $update One `{id, gridX?, gridY?, gridWidth?, gridHeight?}` entry
	 *                      (untyped like the incoming list, so a malformed entry
	 *                      fails exactly where it always did).
	 *
	 * @return void
	 */
	private function updatePosition(mixed $update): void {
		$columns = [
			'grid_x' => ($update['gridX'] ?? 0),
			'grid_y' => ($update['gridY'] ?? 0),
			'grid_width' => ($update['gridWidth'] ?? 4),
			'grid_height' => ($update['gridHeight'] ?? 4),
		];

		$qb = $this->db->getQueryBuilder();
		$qb->update(update: $this->getTableName());
		foreach ($columns as $column => $value) {
			$qb->set(
				key: $column,
				value: $qb->createNamedParameter(value: $value, type: IQueryBuilder::PARAM_INT)
			);
		}

		// Every position write raises the widget's version, so a colleague's
		// per-widget save made on the older version is refused
		// (building-edit-a-dashboard-together, task 1.2).
		$qb->set(key: 'version', value: $qb->createFunction(call: $qb->getColumnName(column: 'version') . ' + 1'));
		$qb->set(
			key: 'updated_at',
			value: $qb->createNamedParameter(value: new DateTime(), type: IQueryBuilder::PARAM_DATETIME_MUTABLE)
		)->where(
			$qb->expr()->eq(
				x: 'id',
				y: $qb->createNamedParameter(value: $update['id'], type: IQueryBuilder::PARAM_INT)
			)
		);

		$qb->executeStatement();
	}//end updatePosition()

	/**
	 * Raise a widget's version by one, only when it is still the version the editor saw.
	 *
	 * One statement compares and raises, so of two saves on the same version
	 * exactly one wins.
	 *
	 * @param int $id       The placement id.
	 * @param int $expected The version the editor's change was made on.
	 *
	 * @return bool True when the version was claimed; false when a colleague saved first.
	 *
	 * @spec openspec/changes/building-edit-a-dashboard-together/specs/dashboard-co-editing/spec.md
	 */
	public function claimVersion(int $id, int $expected): bool {
		$qb = $this->db->getQueryBuilder();
		$qb->update(update: $this->getTableName())
			->set(key: 'version', value: $qb->createNamedParameter(value: $expected + 1, type: IQueryBuilder::PARAM_INT))
			->where($qb->expr()->eq(x: 'id', y: $qb->createNamedParameter(value: $id, type: IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq(x: 'version', y: $qb->createNamedParameter(value: $expected, type: IQueryBuilder::PARAM_INT)));

		return $qb->executeStatement() === 1;
	}//end claimVersion()

	/**
	 * Clone all placements from one dashboard to another.
	 *
	 * Reads the source rows via {@see self::findByDashboardId()} and
	 * inserts a new row per source under `$targetDashboardId`. All
	 * widget-, tile-, style- and grid-fields are byte-for-byte copied
	 * (REQ-DASH-020) — including resource URL fields like `tileIcon`
	 * which intentionally point at the same shared resource record
	 * (REQ-DASH-022). The new rows receive fresh `id`, `dashboardId`
	 * pointing at the target, and `createdAt` / `updatedAt` set to now.
	 *
	 * Used by {@see \OCA\LaunchPad\Service\DashboardService::forkAsPersonal()}
	 * inside a single transaction — any DB exception thrown here MUST
	 * be left to bubble so the caller can roll back.
	 *
	 * @param int $sourceDashboardId The source dashboard ID.
	 * @param int $targetDashboardId The destination dashboard ID.
	 *
	 * @return int The number of placements cloned.
	 * @spec   openspec/changes/launchpad-legacy-quality-cleanup/tasks.md#task-1
	 */
	public function cloneToDashboard(
		int $sourceDashboardId,
		int $targetDashboardId,
	): int {
		$rows = $this->findByDashboardId(dashboardId: $sourceDashboardId);
		$now = (new DateTime())->format(format: 'Y-m-d H:i:s');
		$count = 0;

		foreach ($rows as $row) {
			$clone = new WidgetPlacement();
			// Force the new dashboard id — the rest of the fields are
			// byte-for-byte copies of the source row.
			$clone->setDashboardId($targetDashboardId);
			$this->copyLayout(row: $row, clone: $clone);
			$this->copyTile(row: $row, clone: $clone);
			$this->copyAcknowledgement(row: $row, clone: $clone);
			$clone->setCreatedAt($now);
			$clone->setUpdatedAt($now);

			$this->insert(entity: $clone);
			$count++;
		}//end foreach

		return $count;
	}//end cloneToDashboard()

	/**
	 * Copy the widget, grid, style and content fields of a placement.
	 *
	 * REQ-DASH-020: `content` carries the widget configuration — for
	 * `nc-widget` rows the `{"widgetId": ...}` JSON that tells the renderer
	 * what to load. Dropping it forks widgets into a sourceless "No items
	 * available" state.
	 *
	 * @param WidgetPlacement $row   The source placement.
	 * @param WidgetPlacement $clone The clone being built.
	 *
	 * @return void
	 */
	private function copyLayout(WidgetPlacement $row, WidgetPlacement $clone): void {
		$clone->setWidgetId($row->getWidgetId());
		$clone->setGridX($row->getGridX());
		$clone->setGridY($row->getGridY());
		$clone->setGridWidth($row->getGridWidth());
		$clone->setGridHeight($row->getGridHeight());
		$clone->setIsCompulsory($row->getIsCompulsory());
		$clone->setIsVisible($row->getIsVisible());
		$clone->setStyleConfig($row->getStyleConfig());
		$clone->setCustomTitle($row->getCustomTitle());
		$clone->setCustomIcon($row->getCustomIcon());
		$clone->setShowTitle($row->getShowTitle());
		$clone->setSortOrder($row->getSortOrder());
	}//end copyLayout()

	/**
	 * Copy the tile fields and the widget content of a placement.
	 *
	 * REQ-DASH-022: the tile icon keeps the same `/apps/launchpad/resource/...`
	 * URL — no file is duplicated in app data, both dashboards reference the
	 * shared resource record.
	 *
	 * @param WidgetPlacement $row   The source placement.
	 * @param WidgetPlacement $clone The clone being built.
	 *
	 * @return void
	 */
	private function copyTile(WidgetPlacement $row, WidgetPlacement $clone): void {
		$clone->setTileType($row->getTileType());
		$clone->setTileTitle($row->getTileTitle());
		$clone->setTileIcon($row->getTileIcon());
		$clone->setTileIconType($row->getTileIconType());
		$clone->setTileBackgroundColor($row->getTileBackgroundColor());
		$clone->setTileTextColor($row->getTileTextColor());
		$clone->setTileLinkType($row->getTileLinkType());
		$clone->setTileLinkValue($row->getTileLinkValue());
		$clone->setContent($row->getContent());
	}//end copyTile()

	/**
	 * Copy the acknowledgement requirement of a placement.
	 *
	 * REQ-ACK-001: the shared `announcementKey` is preserved on fork so a
	 * forked dashboard keeps the same announcement identity.
	 *
	 * @param WidgetPlacement $row   The source placement.
	 * @param WidgetPlacement $clone The clone being built.
	 *
	 * @return void
	 */
	private function copyAcknowledgement(WidgetPlacement $row, WidgetPlacement $clone): void {
		$clone->setRequiresAcknowledgement($row->getRequiresAcknowledgement());
		$clone->setAcknowledgementPrompt($row->getAcknowledgementPrompt());
		$clone->setAcknowledgementDeadline($row->getAcknowledgementDeadline());
		$clone->setReacknowledgeOnChange($row->getReacknowledgeOnChange());
		$clone->setAcknowledgementContentVersion($row->getAcknowledgementContentVersion());
		$clone->setAnnouncementKey($row->getAnnouncementKey());
	}//end copyAcknowledgement()

	/**
	 * Count placement rows whose `dashboard_id` no longer points at
	 * any row in `launchpad_dashboards`.
	 *
	 * Used by the orphaned-data-cleanup scan path (REQ-CLN-001).
	 * Placements are normally cleared by `DashboardService::delete()`;
	 * a row left behind here usually indicates a crashed delete path
	 * or a manual SQL operation.
	 *
	 * @return int The number of orphaned placement rows.
	 * @spec   openspec/changes/launchpad-legacy-quality-cleanup/tasks.md#task-1
	 */
	public function countOrphaned(): int {
		return $this->countOrphanRows(link: self::ORPHAN_LINK);
	}//end countOrphaned()

	/**
	 * Delete placement rows whose `dashboard_id` no longer points at
	 * any row in `launchpad_dashboards`.
	 *
	 * Companion to {@see self::countOrphaned()} on the purge path
	 * (REQ-CLN-002). Resolves the orphan IDs first via a SELECT and
	 * then deletes by primary key for portability across drivers.
	 *
	 * @return int The number of rows deleted.
	 * @spec   openspec/changes/launchpad-legacy-quality-cleanup/tasks.md#task-1
	 */
	public function deleteOrphaned(): int {
		return $this->deleteOrphanRows(link: self::ORPHAN_LINK);
	}//end deleteOrphaned()

	/**
	 * Count widget placements for a dashboard (REQ-TMPL-014).
	 *
	 * Used by the gallery serialiser so the response includes the
	 * `widgetCount` field without fetching the full placement entities
	 * (the gallery is a list view; widget bodies are not included).
	 *
	 * @param int $dashboardId The dashboard ID.
	 *
	 * @return int The number of placements (0 when none).
	 * @spec   openspec/changes/launchpad-legacy-quality-cleanup/tasks.md#task-1
	 */
	public function countByDashboardId(int $dashboardId): int {
		return $this->countWhere(match: ['dashboard_id' => $dashboardId]);
	}//end countByDashboardId()
}//end class
