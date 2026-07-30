<?php

/**
 * DashboardResolver
 *
 * Service for resolving the effective dashboard for a user.
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

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Service for resolving the effective dashboard for a user.
 */
class DashboardResolver
{
    /**
     * Constructor
     *
     * @param DashboardMapper           $dashboardMapper Dashboard mapper.
     * @param WidgetPlacementMapper     $placementMapper Widget placement mapper.
     * @param TemplateService           $templateService Template service.
     * @param DashboardShareService|null $shareService   Share service used to
     *                                                   resolve dashboards
     *                                                   shared WITH the user
     *                                                   (REQ-SHARE-002).
     *                                                   Nullable so legacy
     *                                                   positional test
     *                                                   construction keeps
     *                                                   working; a null
     *                                                   service simply yields
     *                                                   no shared dashboards.
     */
    public function __construct(
        private readonly DashboardMapper $dashboardMapper,
        private readonly WidgetPlacementMapper $placementMapper,
        private readonly TemplateService $templateService,
        private readonly ?DashboardShareService $shareService=null,
    ) {
    }//end __construct()

    /**
     * Try to get the user's active dashboard.
     *
     * @param string $userId The user ID.
     *
     * @return array|null The dashboard result or null.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-18
     */
    public function tryGetActiveDashboard(string $userId): ?array
    {
        try {
            $dashboard  = $this->dashboardMapper->findActiveByUserId(
                userId: $userId
            );
            $placements = $this->placementMapper->findByDashboardId(
                dashboardId: $dashboard->getId()
            );

            return $this->buildResult(
                dashboard: $dashboard,
                placements: $placements
            );
        } catch (DoesNotExistException) {
            return null;
        }
    }//end tryGetActiveDashboard()

    /**
     * Try to activate an existing user dashboard.
     *
     * @param string $userId The user ID.
     *
     * @return array|null The dashboard result or null.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-18
     */
    public function tryActivateExistingDashboard(string $userId): ?array
    {
        $userDashboards = $this->dashboardMapper->findByUserId(
            userId: $userId
        );
        if (empty($userDashboards) === true) {
            return null;
        }

        $dashboard = $userDashboards[0];
        $this->dashboardMapper->setActive(
            $dashboard->getId(),
            userId: $userId
        );
        $dashboard->setIsActive(1);

        $placements = $this->placementMapper->findByDashboardId(
            dashboardId: $dashboard->getId()
        );

        return $this->buildResult(
            dashboard: $dashboard,
            placements: $placements
        );
    }//end tryActivateExistingDashboard()

    /**
     * Resolve the permission level of every dashboard SHARED with the user.
     *
     * Thin pass-through to {@see DashboardShareService::resolveSharedDashboards()}
     * so the resolver owns one place where "what is shared with me" is asked.
     * Keys are dashboard IDs, values the most permissive level the user was
     * granted (direct user share vs. group share). Returns an empty map when
     * no share service is wired.
     *
     * @param string        $userId       The user ID.
     * @param array<string> $userGroupIds The user's Nextcloud group IDs.
     *
     * @return array<int, string> Map of dashboardId => permission level.
     *
     * @spec openspec/specs/dashboard-sharing/spec.md
     */
    public function findSharedLevels(string $userId, array $userGroupIds): array
    {
        if ($this->shareService === null) {
            return [];
        }

        $levels = $this->shareService->resolveSharedDashboards(
            userId: $userId,
            groupIds: $userGroupIds
        );

        // Deterministic order so "the first shared dashboard" is stable
        // across requests (the map comes back in share-row order).
        ksort($levels);

        return $levels;
    }//end findSharedLevels()

    /**
     * List the dashboards shared WITH a user as source-tagged entries.
     *
     * Mirrors the `{dashboard, source}` shape produced by
     * {@see \OCA\LaunchPad\Db\DashboardMapper::findVisibleToUser()} so callers
     * can concatenate the two sets. Dashboards the user already owns are
     * skipped — ownership is a stronger claim than a share, and the caller
     * dedupes by UUID anyway. Rows whose dashboard was deleted while the
     * share row survived are skipped silently.
     *
     * @param string        $userId       The user ID.
     * @param array<string> $userGroupIds The user's Nextcloud group IDs.
     *
     * @return array<int, array{dashboard: Dashboard, source: string}>
     *
     * @spec openspec/specs/dashboard-sharing/spec.md
     */
    public function findSharedDashboards(string $userId, array $userGroupIds): array
    {
        $levels  = $this->findSharedLevels(
            userId: $userId,
            userGroupIds: $userGroupIds
        );
        $entries = [];

        foreach (array_keys($levels) as $dashboardId) {
            try {
                $dashboard = $this->dashboardMapper->find(id: (int) $dashboardId);
            } catch (DoesNotExistException) {
                // Orphaned share row — the cleanup job reaps these.
                continue;
            }

            if ($dashboard->getUserId() === $userId) {
                continue;
            }

            $entries[] = [
                'dashboard' => $dashboard,
                'source'    => Dashboard::SOURCE_SHARED,
            ];
        }

        return $entries;
    }//end findSharedDashboards()

    /**
     * Try to resolve a dashboard shared with the user as their active one.
     *
     * This is the LAST-RESORT step of the resolution chain: it only ever
     * runs after every dashboard the user owns (or reaches through group
     * membership) has been considered, so a share can never displace a
     * selection the user or their admin already made. Without it a share
     * is inert — a recipient with no dashboard of their own lands on the
     * empty state and the shared dashboard is unreachable.
     *
     * The returned `permissionLevel` comes from the SHARE, not from the
     * dashboard row: the row carries the OWNER's level, which would tell a
     * view-only recipient they may edit.
     *
     * @param string        $userId       The user ID.
     * @param array<string> $userGroupIds The user's Nextcloud group IDs.
     *
     * @return array|null The dashboard result or null when nothing is shared.
     *
     * @spec openspec/specs/dashboard-sharing/spec.md
     */
    public function tryGetSharedDashboard(string $userId, array $userGroupIds): ?array
    {
        $levels = $this->findSharedLevels(
            userId: $userId,
            userGroupIds: $userGroupIds
        );

        foreach ($levels as $dashboardId => $level) {
            try {
                $dashboard = $this->dashboardMapper->find(id: (int) $dashboardId);
            } catch (DoesNotExistException) {
                continue;
            }

            if ($dashboard->getUserId() === $userId) {
                continue;
            }

            $placements = $this->placementMapper->findByDashboardId(
                dashboardId: $dashboard->getId()
            );

            return [
                'dashboard'       => $dashboard,
                'placements'      => $placements,
                'permissionLevel' => $level,
            ];
        }

        return null;
    }//end tryGetSharedDashboard()

    /**
     * Handle a template-based dashboard result.
     *
     * @param Dashboard $template            The template.
     * @param bool      $allowUserDashboards Whether user dashboards allowed.
     * @param string    $userId              The user ID.
     *
     * @return array The dashboard result.
     *
     * @spec openspec/specs/dashboards/spec.md
     */
    public function handleTemplateResult(
        Dashboard $template,
        bool $allowUserDashboards,
        string $userId
    ): array {
        if ($allowUserDashboards === true) {
            $dashboard  = $this->templateService->createDashboardFromTemplate(
                userId: $userId,
                template: $template
            );
            $placements = $this->placementMapper->findByDashboardId(
                dashboardId: $dashboard->getId()
            );

            return $this->buildResult(
                dashboard: $dashboard,
                placements: $placements
            );
        }

        $placements = $this->placementMapper->findByDashboardId(
            dashboardId: $template->getId()
        );
        return [
            'dashboard'       => $template,
            'placements'      => $placements,
            'permissionLevel' => Dashboard::PERMISSION_VIEW_ONLY,
        ];
    }//end handleTemplateResult()

    /**
     * Build a standard dashboard result array.
     *
     * @param Dashboard $dashboard  The dashboard.
     * @param array     $placements The placements.
     *
     * @return array The result array.
     *
     * @spec openspec/specs/dashboards/spec.md
     */
    public function buildResult(
        Dashboard $dashboard,
        array $placements
    ): array {
        $permissionLevel = $this->getEffectivePermissionLevel(
            dashboard: $dashboard
        );

        return [
            'dashboard'       => $dashboard,
            'placements'      => $placements,
            'permissionLevel' => $permissionLevel,
        ];
    }//end buildResult()

    /**
     * Get the effective permission level for a dashboard.
     *
     * Delegating thin wrapper — the authoritative implementation lives in
     * {@see \OCA\LaunchPad\Service\PermissionService::getEffectivePermissionLevel()}.
     * This copy resolves the template chain inline for the resolver's
     * `buildResult()` path; it MUST stay behaviourally equivalent to the
     * canonical implementation.
     *
     * @param Dashboard $dashboard The dashboard.
     *
     * @return string The effective permission level.
     *
     * @spec openspec/changes/retrofit-2026-05-24-annotate-launchpad/tasks.md#task-24
     */
    public function getEffectivePermissionLevel(
        Dashboard $dashboard
    ): string {
        if ($dashboard->getBasedOnTemplate() !== null) {
            try {
                $template = $this->dashboardMapper->find(
                    id: $dashboard->getBasedOnTemplate()
                );
                return $template->getPermissionLevel();
            } catch (DoesNotExistException) {
                // Template was deleted, use full permissions.
            }
        }

        return $dashboard->getPermissionLevel();
    }//end getEffectivePermissionLevel()
}//end class
