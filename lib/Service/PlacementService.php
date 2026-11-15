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
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Exception\StalePlacementException;

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
	 * @param DashboardMapper|null    $dashboardMapper Reads the dashboard's grid width for a new widget's free spot.
	 * @param FreeSpotFinder          $freeSpots The free-spot search for a new widget.
	 */
	public function __construct(
		private readonly WidgetPlacementMapper $placementMapper,
		private readonly PlacementUpdaters $updaters,
		private readonly ?PublicShareContext $publicShareContext = null,
		private readonly ?QuotaService $quotaService = null,
		private readonly ?DashboardMapper $dashboardMapper = null,
		private readonly FreeSpotFinder $freeSpots = new FreeSpotFinder(),
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
		$spot = $this->freeSpot(dashboardId: $dashboardId, wanted: $layout);
		$placement->setGridX($spot['gridX']);
		$placement->setGridY($spot['gridY']);
		$placement->setGridWidth($spot['gridWidth']);
		$placement->setGridHeight($spot['gridHeight']);
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
		$spot = $this->freeSpot(
			dashboardId: $dashboardId,
			wanted: [
				'gridX'      => (int)($tileData['gridX'] ?? 0),
				'gridY'      => (int)($tileData['gridY'] ?? 0),
				'gridWidth'  => (int)($tileData['gridWidth'] ?? 2),
				'gridHeight' => (int)($tileData['gridHeight'] ?? 2),
			]
		);
		$placement->setGridX($spot['gridX']);
		$placement->setGridY($spot['gridY']);
		$placement->setGridWidth($spot['gridWidth']);
		$placement->setGridHeight($spot['gridHeight']);
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
		$placement->setVersion($this->nextVersion(placement: $placement, data: $data));

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
	 * The version a save writes: one above the version it was made on.
	 *
	 * A save that names the version it was made on claims that version in
	 * one statement; when a colleague saved first the claim fails and the
	 * save is refused with the widget as it is now. A save that names no
	 * version (an older client) just raises the version.
	 *
	 * @param WidgetPlacement      $placement The widget as loaded.
	 * @param array<string, mixed> $data      The update payload.
	 *
	 * @return int
	 *
	 * @throws StalePlacementException When the change was made on an older version.
	 *
	 * @spec openspec/changes/building-edit-a-dashboard-together/specs/dashboard-co-editing/spec.md
	 */
	private function nextVersion(WidgetPlacement $placement, array $data): int {
		if (array_key_exists('version', $data) === false || is_numeric($data['version']) === false) {
			return ($placement->getVersion() + 1);
		}

		$expected = (int)$data['version'];
		if ($this->placementMapper->claimVersion(id: (int)$placement->getId(), expected: $expected) === false) {
			throw new StalePlacementException(current: $this->placementMapper->find(id: (int)$placement->getId()));
		}

		return ($expected + 1);
	}//end nextVersion()

	/**
	 * The spot a new widget takes: the one asked for when free in the saved
	 * layout, else the first free one, never over another widget.
	 *
	 * @param int                                                      $dashboardId The dashboard.
	 * @param array{gridX:int,gridY:int,gridWidth:int,gridHeight:int} $wanted      The spot asked for and the size.
	 *
	 * @return array{gridX:int,gridY:int,gridWidth:int,gridHeight:int}
	 *
	 * @spec openspec/changes/building-edit-a-dashboard-together/specs/dashboard-co-editing/spec.md
	 */
	private function freeSpot(int $dashboardId, array $wanted): array {
		$occupied = array_map(
			static fn (WidgetPlacement $saved): array => [
				'gridX'      => (int)$saved->getGridX(),
				'gridY'      => (int)$saved->getGridY(),
				'gridWidth'  => (int)$saved->getGridWidth(),
				'gridHeight' => (int)$saved->getGridHeight(),
			],
			array_filter(
				(array)$this->placementMapper->findByDashboardId(dashboardId: $dashboardId),
				static fn ($saved): bool => $saved instanceof WidgetPlacement
			)
		);

		return $this->freeSpots->place(
			occupied: array_values($occupied),
			columns: $this->columnsOf(dashboardId: $dashboardId),
			wanted: [
				'gridX'      => (int)$wanted['gridX'],
				'gridY'      => (int)$wanted['gridY'],
				'gridWidth'  => (int)$wanted['gridWidth'],
				'gridHeight' => (int)$wanted['gridHeight'],
			]
		);
	}//end freeSpot()

	/**
	 * The dashboard's grid column count; 12 when it cannot be read.
	 *
	 * @param int $dashboardId The dashboard.
	 *
	 * @return int
	 */
	private function columnsOf(int $dashboardId): int {
		if ($this->dashboardMapper === null) {
			return 12;
		}

		try {
			return max(1, (int)$this->dashboardMapper->find(id: $dashboardId)->getGridColumns());
		} catch (\Throwable) {
			return 12;
		}
	}//end columnsOf()

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
}//end class
