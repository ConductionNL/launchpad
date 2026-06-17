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

/**
 * Service for managing widget placement CRUD operations.
 */
class PlacementService
{
    /**
     * Constructor
     *
     * @param WidgetPlacementMapper   $placementMapper    Widget placement mapper.
     * @param TileUpdater             $tileUpdater        Tile updater service.
     * @param PlacementUpdater        $placementUpdater   Placement updater service.
     * @param PublicShareContext|null $publicShareContext Public-share bearer
     *                                                    guard (nullable for
     *                                                    legacy test doubles).
     * @param QuotaService|null       $quotaService       Widget-quota enforcer
     *                                                    (dashboard-quota-limits
     *                                                    REQ-QUOTA-003).
     *                                                    Nullable to keep
     *                                                    existing test doubles
     *                                                    working — when
     *                                                    absent no quota is
     *                                                    enforced.
     */
    public function __construct(
        private readonly WidgetPlacementMapper $placementMapper,
        private readonly TileUpdater $tileUpdater,
        private readonly PlacementUpdater $placementUpdater,
        private readonly ?PublicShareContext $publicShareContext=null,
        private readonly ?QuotaService $quotaService=null,
    ) {
    }//end __construct()

    /**
     * Add a widget to a dashboard.
     *
     * @param int        $dashboardId Dashboard ID.
     * @param string     $widgetId    Widget ID.
     * @param int        $gridX       Grid X position.
     * @param int        $gridY       Grid Y position.
     * @param int        $gridWidth   Grid width.
     * @param int        $gridHeight  Grid height.
     * @param array|null $content     Optional per-type content payload for
     *                                custom widgets (registry-driven types
     *                                like `label`, `text`, etc. — shape
     *                                matches the type's `defaultContent`).
     *
     * @return WidgetPlacement The created widget placement.
     *
     * @throws \OCA\LaunchPad\Exception\QuotaExceededException When the
     *         dashboard is at the configured widget limit
     *         (dashboard-quota-limits REQ-QUOTA-003).
     *
     * @spec openspec/changes/dashboard-quota-limits/specs/dashboard-quota-limits/spec.md#req-quota-003-widget-count-enforcement
     */
    public function addWidget(
        int $dashboardId,
        string $widgetId,
        int $gridX=0,
        int $gridY=0,
        int $gridWidth=4,
        int $gridHeight=4,
        ?array $content=null
    ): WidgetPlacement {
        // Task-7 of dashboard-public-share — bearer cannot mutate placements.
        $this->publicShareContext?->requireMutable();
        // Dashboard-quota-limits REQ-QUOTA-003: enforce the per-dashboard
        // widget quota at the single placement-creation choke point so the
        // REST add-widget, add-tile, and store paths are all bound.
        // Admin compulsory-widget pushes wrap their call in
        // QuotaService::runProvisioning() to bypass this (REQ-QUOTA-004).
        $this->quotaService?->assertCanAddPlacement(dashboardId: $dashboardId);
        $placement = new WidgetPlacement();
        $now       = (new DateTime())->format(format: 'Y-m-d H:i:s');
        $placement->setDashboardId($dashboardId);
        $placement->setWidgetId($widgetId);
        $placement->setGridX($gridX);
        $placement->setGridY($gridY);
        $placement->setGridWidth($gridWidth);
        $placement->setGridHeight($gridHeight);
        $placement->setIsCompulsory(0);
        $placement->setIsVisible(1);
        $placement->setShowTitle(1);

        if ($content !== null) {
            $placement->setContentArray($content);
        }

        $placement->setCreatedAt($now);
        $placement->setUpdatedAt($now);

        return $this->placementMapper->insert(entity: $placement);
    }//end addWidget()

    /**
     * Add a tile to a dashboard using an array of tile data.
     *
     * @param int   $dashboardId Dashboard ID.
     * @param array $tileData    Tile configuration data array.
     *
     * @return WidgetPlacement The created tile placement.
     *
     * @throws \OCA\LaunchPad\Exception\QuotaExceededException When the
     *         dashboard is at the configured widget limit
     *         (dashboard-quota-limits REQ-QUOTA-003).
     *
     * @spec openspec/changes/dashboard-quota-limits/specs/dashboard-quota-limits/spec.md#req-quota-003-widget-count-enforcement
     */
    public function addTileFromArray(
        int $dashboardId,
        array $tileData
    ): WidgetPlacement {
        // Task-7 of dashboard-public-share — bearer cannot mutate placements.
        $this->publicShareContext?->requireMutable();
        // Dashboard-quota-limits REQ-QUOTA-003: tiles are placements too —
        // bind them to the same per-dashboard widget quota choke point.
        $this->quotaService?->assertCanAddPlacement(dashboardId: $dashboardId);
        $placement = new WidgetPlacement();
        $now       = (new DateTime())->format(format: 'Y-m-d H:i:s');
        $placement->setDashboardId($dashboardId);
        $placement->setWidgetId('tile-'.uniqid());
        $placement->setGridX($tileData['gridX'] ?? 0);
        $placement->setGridY($tileData['gridY'] ?? 0);
        $placement->setGridWidth($tileData['gridWidth'] ?? 2);
        $placement->setGridHeight(
            $tileData['gridHeight'] ?? 2
        );
        $placement->setIsCompulsory(0);
        $placement->setIsVisible(1);
        $placement->setShowTitle(1);

        $this->tileUpdater->applyTileConfig(
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
     * @param int   $placementId The placement ID.
     * @param array $data        The data to update.
     *
     * @return WidgetPlacement The updated widget placement.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-35
     */
    public function updatePlacement(
        int $placementId,
        array $data
    ): WidgetPlacement {
        // Task-7 of dashboard-public-share — bearer cannot mutate placements.
        $this->publicShareContext?->requireMutable();
        $placement = $this->placementMapper->find(id: $placementId);

        $this->placementUpdater->applyGridUpdates(
            placement: $placement,
            data: $data
        );
        $this->placementUpdater->applyDisplayUpdates(
            placement: $placement,
            data: $data
        );
        $this->tileUpdater->applyTileUpdates(
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
    public function removePlacement(int $placementId): void
    {
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
    public function getPlacement(int $placementId): WidgetPlacement
    {
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
    public function getDashboardPlacements(int $dashboardId): array
    {
        return $this->placementMapper->findByDashboardId(
            dashboardId: $dashboardId
        );
    }//end getDashboardPlacements()
}//end class
