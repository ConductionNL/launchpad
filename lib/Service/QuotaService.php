<?php

/**
 * QuotaService
 *
 * Single enforcement choke point for the admin-configured governance
 * quotas introduced by the `dashboard-quota-limits` capability:
 *  - maximum personal dashboards per user (REQ-QUOTA-002), and
 *  - maximum widget placements per dashboard (REQ-QUOTA-003).
 *
 * Every user-initiated creation path calls one of the `assertCan*`
 * methods; controllers never count. Admin-initiated provisioning (template
 * rollout, compulsory-widget pushes) wraps its work in
 * {@see self::runProvisioning()} so the quota is bypassed for that call
 * path only — never inferred from the acting user's group membership
 * (REQ-QUOTA-004).
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
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Exception\QuotaExceededException;
use Throwable;

/**
 * Server-side enforcement of dashboard / widget governance quotas.
 *
 * @spec openspec/changes/dashboard-quota-limits/specs/dashboard-quota-limits/spec.md
 */
class QuotaService
{

    /**
     * Provisioning-context depth. Greater than zero means the current call
     * stack is inside an admin provisioning path and quota asserts are
     * bypassed (REQ-QUOTA-004). A counter (not a bool) so nested
     * provisioning calls compose correctly.
     *
     * @var integer
     */
    private int $provisioningDepth = 0;

    /**
     * Constructor.
     *
     * @param AdminSettingMapper    $settingMapper   Admin setting mapper —
     *                                               source of the two quota
     *                                               values and the
     *                                               `allow_multiple_dashboards`
     *                                               flag.
     * @param DashboardMapper       $dashboardMapper Dashboard mapper — live
     *                                               personal-dashboard count.
     * @param WidgetPlacementMapper $placementMapper Placement mapper — live
     *                                               per-dashboard placement
     *                                               count.
     */
    public function __construct(
        private readonly AdminSettingMapper $settingMapper,
        private readonly DashboardMapper $dashboardMapper,
        private readonly WidgetPlacementMapper $placementMapper,
    ) {
    }//end __construct()

    /**
     * Run an admin-provisioning callable with user quotas bypassed
     * (REQ-QUOTA-004).
     *
     * The bypass is tied to this call path — template rollout, compulsory
     * widget pushes, admin-on-behalf provisioning — NOT to the acting
     * user's admin group membership. An admin creating their own personal
     * dashboard through the normal user flow does NOT go through here and
     * therefore stays subject to the quota.
     *
     * The depth counter is always decremented (even on throw) so a failing
     * provisioning call can never leave the service permanently bypassed.
     *
     * @param callable():T $work The provisioning work to run unguarded.
     *
     * @template T
     *
     * @return T The callable's return value.
     *
     * @spec openspec/changes/dashboard-quota-limits/specs/dashboard-quota-limits/spec.md#req-quota-004-admin-provisioning-exemption
     */
    public function runProvisioning(callable $work): mixed
    {
        $this->provisioningDepth++;
        try {
            return $work();
        } finally {
            $this->provisioningDepth--;
            if ($this->provisioningDepth < 0) {
                $this->provisioningDepth = 0;
            }
        }
    }//end runProvisioning()

    /**
     * Whether the current call stack is inside a provisioning bypass.
     *
     * @return bool True when quotas are currently bypassed.
     */
    public function isProvisioning(): bool
    {
        return $this->provisioningDepth > 0;
    }//end isProvisioning()

    /**
     * Assert that the user may create one more personal dashboard
     * (REQ-QUOTA-002).
     *
     * Live `COUNT(*)` on the user's personal-scope dashboards (group- and
     * admin-scope dashboards never count). Most-restrictive-wins with the
     * `allow_multiple_dashboards` flag (REQ-QUOTA-002 / design D6): when
     * multiple dashboards are disallowed the effective limit is `1`,
     * regardless of the numeric setting. A numeric quota never loosens the
     * boolean restriction. No enforcement when the effective limit is `0`
     * (unlimited).
     *
     * Bypassed entirely inside {@see self::runProvisioning()}.
     *
     * @param string $userId The acting user ID.
     *
     * @return void
     *
     * @throws QuotaExceededException When the user is at or over the
     *                                effective dashboard limit.
     *
     * @spec openspec/changes/dashboard-quota-limits/specs/dashboard-quota-limits/spec.md#req-quota-002-dashboard-count-enforcement
     */
    public function assertCanCreateDashboard(string $userId): void
    {
        if ($this->isProvisioning() === true) {
            return;
        }

        $limit = $this->effectiveDashboardLimit();
        if ($limit === 0) {
            return;
        }

        $current = $this->dashboardMapper->countPersonalByUserId(
            userId: $userId
        );
        if ($current >= $limit) {
            throw new QuotaExceededException(
                quota: QuotaExceededException::QUOTA_DASHBOARDS,
                limit: $limit,
                current: $current
            );
        }
    }//end assertCanCreateDashboard()

    /**
     * Assert that one more placement may be added to a dashboard
     * (REQ-QUOTA-003).
     *
     * Live `COUNT(*)` on the dashboard's placements. No enforcement when
     * `max_widgets_per_dashboard` is `0` (unlimited). Bypassed entirely
     * inside {@see self::runProvisioning()} so a compulsory-widget push may
     * legitimately push a dashboard over the limit (REQ-QUOTA-004); the
     * resulting over-quota state still blocks the user's own next
     * placement (REQ-QUOTA-005).
     *
     * @param int $dashboardId The target dashboard ID (the placement-
     *                         creation choke point in
     *                         {@see PlacementService} works in numeric IDs).
     *
     * @return void
     *
     * @throws QuotaExceededException When the dashboard is at or over the
     *                                widget limit.
     *
     * @spec openspec/changes/dashboard-quota-limits/specs/dashboard-quota-limits/spec.md#req-quota-003-widget-count-enforcement
     */
    public function assertCanAddPlacement(int $dashboardId): void
    {
        if ($this->provisioningDepth > 0) {
            return;
        }

        $limit = $this->maxWidgetsPerDashboard();
        if ($limit === 0) {
            return;
        }

        $current = $this->placementMapper->countByDashboardId(
            dashboardId: $dashboardId
        );
        if ($current >= $limit) {
            throw new QuotaExceededException(
                quota: QuotaExceededException::QUOTA_WIDGETS,
                limit: $limit,
                current: $current
            );
        }
    }//end assertCanAddPlacement()

    /**
     * Build the additive quota-status envelope for the dashboards list
     * response (REQ-QUOTA-006).
     *
     * `maxDashboards` reflects the EFFECTIVE dashboard limit (so
     * `allow_multiple_dashboards = false` surfaces as `1`), `dashboardsUsed`
     * is the user's live personal-dashboard count, and
     * `maxWidgetsPerDashboard` is the configured per-dashboard widget limit.
     * `0` means unlimited for both `max*` fields.
     *
     * @param string $userId The acting user ID.
     *
     * @return array{maxDashboards: int, dashboardsUsed: int, maxWidgetsPerDashboard: int}
     *   The quota-status envelope.
     *
     * @spec openspec/changes/dashboard-quota-limits/specs/dashboard-quota-limits/spec.md#req-quota-006-quota-status-surfacing-in-ui
     */
    public function getQuotaStatus(string $userId): array
    {
        return [
            'maxDashboards'          => $this->effectiveDashboardLimit(),
            'dashboardsUsed'         => $this->dashboardMapper->countPersonalByUserId(
                userId: $userId
            ),
            'maxWidgetsPerDashboard' => $this->maxWidgetsPerDashboard(),
        ];
    }//end getQuotaStatus()

    /**
     * Resolve the effective per-user dashboard limit, applying
     * most-restrictive-wins against `allow_multiple_dashboards`
     * (REQ-QUOTA-002 / design D6).
     *
     * Rules:
     *  - `allow_multiple_dashboards = false` ⇒ effective limit `1`,
     *    regardless of the numeric setting (the numeric quota MUST NOT
     *    loosen the boolean restriction).
     *  - otherwise the configured numeric value (`0` = unlimited).
     *
     * `allow_user_dashboards` is enforced separately upstream
     * ({@see DashboardService::assertPersonalDashboardsAllowed()}); the
     * quota never grants what that boolean denies.
     *
     * @return int The effective dashboard limit (`0` = unlimited).
     */
    private function effectiveDashboardLimit(): int
    {
        $numeric = $this->readQuota(
            key: AdminSetting::KEY_MAX_DASHBOARDS_PER_USER
        );

        $allowMultiple = (bool) $this->settingMapper->getValue(
            key: AdminSetting::KEY_ALLOW_MULTIPLE_DASHBOARDS,
            default: true
        );

        if ($allowMultiple === false) {
            return 1;
        }

        return $numeric;
    }//end effectiveDashboardLimit()

    /**
     * Read the configured per-dashboard widget limit (`0` = unlimited).
     *
     * @return int The widget limit.
     */
    private function maxWidgetsPerDashboard(): int
    {
        return $this->readQuota(
            key: AdminSetting::KEY_MAX_WIDGETS_PER_DASHBOARD
        );
    }//end maxWidgetsPerDashboard()

    /**
     * Read a stored numeric quota, defensively coercing to a non-negative
     * integer. A missing row, a non-numeric value, or any read failure
     * resolves to `0` (unlimited) so a corrupt setting can never block
     * legitimate creations.
     *
     * @param string $key The admin-setting key.
     *
     * @return int The stored quota, or `0` on absence / corruption.
     */
    private function readQuota(string $key): int
    {
        try {
            $raw = $this->settingMapper->getValue(key: $key, default: 0);
        } catch (Throwable) {
            return 0;
        }

        if (is_int($raw) === false && is_numeric($raw) === false) {
            return 0;
        }

        $value = (int) $raw;
        if ($value < 0) {
            return 0;
        }

        return $value;
    }//end readQuota()
}//end class
