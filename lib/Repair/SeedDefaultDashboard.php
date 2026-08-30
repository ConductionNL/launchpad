<?php

/**
 * SeedDefaultDashboard Repair Step
 *
 * Provisions the shipped default dashboard — the Conduction, Sendent and
 * Nextcloud tiles plus a Files widget — as an instance-wide, group-shared
 * dashboard on the reserved `default` group, on install and after every
 * upgrade.
 *
 * WHY A FRESH INSTALL SHOWED NOTHING AT ALL.
 *
 * `allowUserDashboards` defaults to `false` on purpose (REQ-ASET-003: an
 * admin must opt in before users may create personal dashboards). That
 * default closes the only branch of `DashboardService::tryCreateFromTemplate()`
 * that WRITES, so on a brand-new instance no dashboard was ever created —
 * not by the app, not by the user. Every rung of
 * `DashboardService::resolveActiveDashboard()` returned null and
 * `/apps/launchpad/` rendered "No dashboards available — contact your
 * administrator", with `GET /api/dashboards` answering `{"items": []}` and
 * HTTP 200. The default widget bundle existed and had unit tests; nothing
 * ever called it, because nothing ever created the dashboard it seeds.
 *
 * The step is IDEMPOTENT — `DashboardService::ensureDefaultDashboard()`
 * returns null the moment a `default`-group dashboard exists — so running it
 * on an upgraded instance that already has dashboards changes nothing.
 *
 * Resolves `DashboardService` from the container rather than through the
 * constructor, mirroring {@see ImportLaunchpadRegister}: a repair step is
 * built during upgrade, when a hard constructor dependency on a service
 * graph this wide turns any unrelated wiring error into a failed upgrade
 * instead of a skipped seed.
 *
 * @category Repair
 * @package  OCA\LaunchPad\Repair
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Repair;

use OCA\LaunchPad\Service\DashboardService;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Seed the shipped default dashboard for the whole instance.
 *
 * @spec openspec/specs/default-widget-bundle/spec.md
 */
class SeedDefaultDashboard implements IRepairStep {
	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container The app container.
	 * @param LoggerInterface $logger PSR-3 logger.
	 */
	public function __construct(
		private readonly ContainerInterface $container,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Return the human-readable name of this repair step.
	 *
	 * @return string The step name.
	 *
	 * @spec openspec/specs/default-widget-bundle/spec.md
	 */
	public function getName(): string {
		return 'Seed the default LaunchPad dashboard';
	}//end getName()

	/**
	 * Run the seeding step.
	 *
	 * @param IOutput $output Migration output stream.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/default-widget-bundle/spec.md
	 */
	public function run(IOutput $output): void {
		try {
			// @var DashboardService $dashboardService
			$dashboardService = $this->container->get(DashboardService::class);
			$dashboard = $dashboardService->ensureDefaultDashboard();
		} catch (Throwable $e) {
			$this->logger->warning(
				message: '[SeedDefaultDashboard] seeding failed: ' . $e->getMessage()
			);
			$output->warning(
				message: 'LaunchPad default dashboard not seeded: ' . $e->getMessage()
			);
			return;
		}

		if ($dashboard === null) {
			$output->info(
				message: 'LaunchPad already has a default dashboard — skipping.'
			);
			return;
		}

		$this->logger->info(
			message: '[SeedDefaultDashboard] provisioned the default dashboard.'
		);
		$output->info(
			message: 'LaunchPad default dashboard seeded (Conduction, Sendent, Nextcloud + Files).'
		);
	}//end run()
}//end class
