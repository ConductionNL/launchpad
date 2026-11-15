<?php

/**
 * DashboardLimits
 *
 * The optional limits on dashboard writes: role features, public-share bearers, quotas and the fork
 * lock.
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
 *
 * @spec openspec/specs/dashboards/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\Lock\ILockingProvider;

/**
 * The optional limits on dashboard writes: role features, public-share bearers, quotas and the fork
 * lock.
 *
 * @spec openspec/specs/dashboards/spec.md
 */
class DashboardLimits {
	/**
	 * Constructor.
	 *
	 * @param RoleFeaturePermissionService|null $roleFeaturePerm Role-default layout seeding (REQ-RFP-002).
	 *                                                           Nullable to keep legacy PHPUnit doubles working
	 *                                                           — the seedLayoutFromRoleDefaults call site below
	 *                                                           guards on null and degrades to the original no-
	 *                                                           op behaviour.
	 * @param PublicShareContext|null           $publicShareContext Optional public-share bearer context; when
	 *                                                              present a share bearer cannot mutate
	 *                                                              dashboards (dashboard-public-share).
	 * @param QuotaService|null                 $quotaService Optional quota-enforcement service gating per-user
	 *                                                        dashboard creation (dashboard-quota-limits).
	 * @param ILockingProvider|null             $lockingProvider Optional cluster lock provider used to serialise
	 *                                                           the fork naming + insert sequence per user so
	 *                                                           concurrent forks cannot produce duplicate slugs
	 *                                                           (REQ-DASH-020).
	 */
	public function __construct(
		public readonly ?RoleFeaturePermissionService $roleFeaturePerm=null,
		public readonly ?PublicShareContext $publicShareContext=null,
		public readonly ?QuotaService $quotaService=null,
		public readonly ?ILockingProvider $lockingProvider=null,
	) {
	}//end __construct()
}//end class
