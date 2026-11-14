<?php

/**
 * PlacementService
 *
 * Service for managing widget placement CRUD operations.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTime;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\IDBConnection;
use Throwable;

/**
 * Service for managing widget placement CRUD operations.
 *
 * @spec openspec/specs/widgets/spec.md
 */
class PlacementService {
	/**
	 * Grid position, size and content of a new widget placement when the
	 * caller leaves them out.
	 */
	private const DEFAULT_LAYOUT = [
		'gridX'      => 0,
		'gridY'      => 0,
		'gridWidth'  => 4,
		'gridHeight' => 4,
		'content'    => null,
	];

	/**
	 * Constructor
	 *
	 * @param WidgetPlacementMapper   $placementMapper Widget placement mapper.
	 * @param PlacementUpdaters       $updaters The tile and placement updaters.
	 * @param PublicShareContext|null $publicShareContext Public-share bearer guard (nullable for legacy test doubles).
	 * @param QuotaService|null       $quotaService Widget-quota enforcer (dashboard-quota-limits
	 *                                              REQ-QUOTA-003). Nullable to keep existing test doubles
	 *                                              working — when absent no quota is enforced.
	 * @param IDBConnection|null      $db           Wraps a bookmark import in one transaction
	 *                                              (launcher-bookmark-import REQ-BMI-003).
	 */
	public function __construct(
		private readonly WidgetPlacementMapper $placementMapper,
		private readonly PlacementUpdaters $updaters,
		private readonly ?PublicShareContext $publicShareContext = null,
		private readonly ?QuotaService $quotaService = null,
		private readonly ?IDBConnection $db = null,
	) {
	}//end __construct()

	/**
	 * Add a widget to a dashboard.
	 *
	 * @param int $dashboardId Dashboard ID.
	 * @param string $widgetId Widget ID.
	 * @param array $layout Grid position and size (`gridX`, `gridY`,
	 *                      `gridWidth`, `gridHeight`; default 0, 0, 4 x 4)
	 *                      and the optional per-type `content` payload for
	 *                      custom widgets (registry-driven types like
	 *                      `label`, `text`, etc. — shape matches the type's
	 *                      `defaultContent`).
	 *
	 * @return WidgetPlacement The created widget placement.
	 *
	 * @throws \OCA\LaunchPad\Exception\QuotaExceededException When the
	 *                                                         dashboard is at the configured widget limit
	 *                                                         (dashboard-quota-limits REQ-QUOTA-003).
	 *
	 * @spec openspec/changes/dashboard-quota-limits/specs/dashboard-quota-limits/spec.md#req-quota-003-widget-count-enforcement
	 */
	public function addWidget(int $dashboardId, string $widgetId, array $layout=[]): WidgetPlacement {
		$layout += self::DEFAULT_LAYOUT;
		// Task-7 of dashboard-public-share — bearer cannot mutate placements.
		$this->publicShareContext?->requireMutable();
		// Dashboard-quota-limits REQ-QUOTA-003: enforce the per-dashboard
		// widget quota at the single placement-creation choke point so the
		// REST add-widget, add-tile, and store paths are all bound.
		// Admin compulsory-widget pushes wrap their call in
		// QuotaService::runProvisioning() to bypass this (REQ-QUOTA-004).
		$this->quotaService?->assertCanAddPlacement(dashboardId: $dashboardId);
		$placement = new WidgetPlacement();
		$now = (new DateTime())->format(format: 'Y-m-d H:i:s');
		$placement->setDashboardId($dashboardId);
		$placement->setWidgetId($widgetId);
		$placement->setGridX($layout['gridX']);
		$placement->setGridY($layout['gridY']);
		$placement->setGridWidth($layout['gridWidth']);
		$placement->setGridHeight($layout['gridHeight']);
		$placement->setIsCompulsory(0);
		$placement->setIsVisible(1);
		$placement->setShowTitle(1);

		if ($layout['content'] !== null) {
			$placement->setContentArray($layout['content']);
		}

		$placement->setCreatedAt($now);
		$placement->setUpdatedAt($now);

		return $this->placementMapper->insert(entity: $placement);
	}//end addWidget()

	/**
	 * Add a tile to a dashboard using an array of tile data.
	 *
	 * @param int $dashboardId Dashboard ID.
	 * @param array $tileData Tile configuration data array.
	 *
	 * @return WidgetPlacement The created tile placement.
	 *
	 * @throws \OCA\LaunchPad\Exception\QuotaExceededException When the
	 *                                                         dashboard is at the configured widget limit
	 *                                                         (dashboard-quota-limits REQ-QUOTA-003).
	 *
	 * @spec openspec/changes/dashboard-quota-limits/specs/dashboard-quota-limits/spec.md#req-quota-003-widget-count-enforcement
	 */
	public function addTileFromArray(
		int $dashboardId,
		array $tileData,
	): WidgetPlacement {
		// Task-7 of dashboard-public-share — bearer cannot mutate placements.
		$this->publicShareContext?->requireMutable();
		// Dashboard-quota-limits REQ-QUOTA-003: tiles are placements too —
		// bind them to the same per-dashboard widget quota choke point.
		$this->quotaService?->assertCanAddPlacement(dashboardId: $dashboardId);
		$placement = new WidgetPlacement();
		$now = (new DateTime())->format(format: 'Y-m-d H:i:s');
		$placement->setDashboardId($dashboardId);
		$placement->setWidgetId('tile-' . uniqid());
		$placement->setGridX($tileData['gridX'] ?? 0);
		$placement->setGridY($tileData['gridY'] ?? 0);
		$placement->setGridWidth($tileData['gridWidth'] ?? 2);
		$placement->setGridHeight(
			$tileData['gridHeight'] ?? 2
		);
		$placement->setIsCompulsory(0);
		$placement->setIsVisible(1);
		$placement->setShowTitle(1);

		$this->updaters->tileUpdater->applyTileConfig(
			placement: $placement,
			tileData: $tileData
		);

		$placement->setCreatedAt($now);
		$placement->setUpdatedAt($now);

		return $this->placementMapper->insert(entity: $placement);
	}//end addTileFromArray()

	/**
	 * Update a widget placement.
	 *
	 * @param int $placementId The placement ID.
	 * @param array $data The data to update.
	 *
	 * @return WidgetPlacement The updated widget placement.
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-35
	 */
	public function updatePlacement(
		int $placementId,
		array $data,
	): WidgetPlacement {
		// Task-7 of dashboard-public-share — bearer cannot mutate placements.
		$this->publicShareContext?->requireMutable();
		$placement = $this->placementMapper->find(id: $placementId);

		$this->updaters->placementUpdater->applyGridUpdates(
			placement: $placement,
			data: $data
		);
		$this->updaters->placementUpdater->applyDisplayUpdates(
			placement: $placement,
			data: $data
		);
		$this->updaters->tileUpdater->applyTileUpdates(
			placement: $placement,
			data: $data
		);
		$this->updaters->placementUpdater->applyAcknowledgementUpdates(
			placement: $placement,
			data: $data
		);

		$placement->setUpdatedAt(
			(new DateTime())->format(format: 'Y-m-d H:i:s')
		);

		return $this->placementMapper->update(entity: $placement);
	}//end updatePlacement()

	/**
	 * Remove a widget placement.
	 *
	 * @param int $placementId The placement ID.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-36
	 */
	public function removePlacement(int $placementId): void {
		// Task-7 of dashboard-public-share — bearer cannot mutate placements.
		$this->publicShareContext?->requireMutable();
		$placement = $this->placementMapper->find(id: $placementId);
		$this->placementMapper->delete(entity: $placement);
	}//end removePlacement()

	/**
	 * Get placement by ID.
	 *
	 * @param int $placementId The placement ID.
	 *
	 * @return WidgetPlacement The widget placement.
	 */
	public function getPlacement(int $placementId): WidgetPlacement {
		return $this->placementMapper->find(id: $placementId);
	}//end getPlacement()

	/**
	 * Get all placements for a dashboard.
	 *
	 * @param int $dashboardId The dashboard ID.
	 *
	 * @return WidgetPlacement[] The list of placements.
	 *
	 * @spec openspec/specs/widgets/spec.md
	 */
	public function getDashboardPlacements(int $dashboardId): array {
		return $this->placementMapper->findByDashboardId(
			dashboardId: $dashboardId
		);
	}//end getDashboardPlacements()

	/**
	 * Create the placements of a bookmark import: the dashboard quota is
	 * checked once for the whole import before anything is written, and every
	 * placement is created in one transaction, so a failure leaves nothing
	 * behind (launcher-bookmark-import REQ-BMI-001, REQ-BMI-003). Everything
	 * goes below the dashboard's lowest edge (bottom-append).
	 *
	 * @param int                $dashboardId The dashboard.
	 * @param BookmarkImportPlan $plan        What to create.
	 *
	 * @return list<WidgetPlacement> The created placements, containers first.
	 *
	 * @throws \OCA\LaunchPad\Exception\QuotaExceededException When the import does not fit the quota.
	 * @throws Throwable When a write fails; the transaction is rolled back first.
	 *
	 * @spec openspec/changes/launcher-bookmark-import/specs/tiles/spec.md#requirement-the-import-respects-the-widget-quota-as-one-operation-req-bmi-003
	 */
	public function importBookmarks(int $dashboardId, BookmarkImportPlan $plan): array {
		$this->publicShareContext?->requireMutable();
		$this->quotaService?->assertCanAddPlacements(dashboardId: $dashboardId, count: $plan->placementCount());

		$bottom = 0;
		foreach ($this->getDashboardPlacements(dashboardId: $dashboardId) as $existing) {
			$bottom = max($bottom, ((int)$existing->getGridY() + (int)$existing->getGridHeight()));
		}

		$this->db?->beginTransaction();
		try {
			$created = [];
			foreach ($plan->layout(bottom: $bottom) as $spec) {
				$created[] = $this->placementMapper->insert(entity: $this->importedPlacement(dashboardId: $dashboardId, spec: $spec));
			}

			$this->db?->commit();
		} catch (Throwable $e) {
			$this->db?->rollBack();
			throw $e;
		}

		return $created;
	}//end importBookmarks()

	/**
	 * One placement of an import: a container with its link children, or a
	 * tile opening the bookmark in a new tab.
	 *
	 * @param int                  $dashboardId The dashboard.
	 * @param array<string, mixed> $spec        One entry of {@see BookmarkImportPlan::layout()}.
	 *
	 * @return WidgetPlacement
	 */
	private function importedPlacement(int $dashboardId, array $spec): WidgetPlacement {
		$now = (new DateTime())->format(format: 'Y-m-d H:i:s');
		$placement = new WidgetPlacement();
		$placement->setDashboardId($dashboardId);
		$placement->setGridX($spec['gridX']);
		$placement->setGridY($spec['gridY']);
		$placement->setGridWidth($spec['gridWidth']);
		$placement->setGridHeight($spec['gridHeight']);
		$placement->setIsCompulsory(0);
		$placement->setIsVisible(1);
		$placement->setShowTitle(1);
		$placement->setCreatedAt($now);
		$placement->setUpdatedAt($now);

		if (isset($spec['tile']) === true) {
			$placement->setWidgetId('tile-' . uniqid());
			$this->updaters->tileUpdater->applyTileConfig(
				placement: $placement,
				tileData: [
					'title' => $spec['tile']['title'],
					'linkType' => 'url',
					'linkVal' => $spec['tile']['url'],
					'icon' => 'icon-link',
					'iconType' => 'class',
				]
			);
			return $placement;
		}

		$placement->setWidgetId((string)$spec['widgetId']);
		$placement->setContentArray($spec['content']);
		return $placement;
	}//end importedPlacement()
}//end class
