<?php

/**
 * StoreUpdateService
 *
 * Brings a dashboard installed from the marketplace to the register's newer
 * version in place (REQ-STORE-012). The dashboard keeps its id, UUID, name,
 * owner, shares and permission level. Its widgets that came from the install
 * become the new version's, paired so a widget present in both keeps its row
 * ({@see PlacementPairing}, as a shipped template update does). A widget a
 * person added themselves stays. A copy made from the dashboard follows with
 * the merge re-sync, the install record takes the new version, and a
 * dashboard version noted "Updated from the marketplace" is written.
 *
 * WHICH WIDGETS CAME FROM THE INSTALL. The install record keeps their ids
 * ({@see StoreInstalls::record()}). A record written before it did holds no
 * ids: then every widget of the dashboard may be paired with the new version,
 * but none is removed, so a person's own widget is never lost.
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
 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-012-update-brings-an-installed-dashboard-to-the-newer-version-in-place
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\WidgetPlacement;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Update an installed marketplace dashboard in place.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 *      One operation over the register item, the install record, the rows,
 *      the version and the re-sync; splitting it would put the write and the
 *      record it must stay in step with in two places.
 *
 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-012-update-brings-an-installed-dashboard-to-the-newer-version-in-place
 */
class StoreUpdateService {
	/**
	 * The note on the dashboard version an update writes.
	 */
	public const VERSION_NOTE = 'Updated from the marketplace';

	/**
	 * Constructor.
	 *
	 * @param StoreService             $store    Resolves the register item.
	 * @param StoreInstalls            $installs The install record.
	 * @param ShippedTemplateStores    $stores   The dashboard and placement mappers.
	 * @param PlacementPayloadHydrator $hydrator Builds a widget from a payload, as the importer does.
	 * @param PlacementPairing         $pairing  Pairs and writes the widgets.
	 * @param TemplateResyncService    $resync   Brings copies along.
	 * @param DashboardVersionService  $versions Writes the version note.
	 * @param LoggerInterface          $logger   Server-side diagnostics only.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly StoreService $store,
		private readonly StoreInstalls $installs,
		private readonly ShippedTemplateStores $stores,
		private readonly PlacementPayloadHydrator $hydrator,
		private readonly PlacementPairing $pairing,
		private readonly TemplateResyncService $resync,
		private readonly DashboardVersionService $versions,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Update the dashboard an install of this item created to the register's version.
	 *
	 * @param string $slug   The register item slug.
	 * @param string $userId The administrator running the update.
	 *
	 * @return array<string, mixed> `{success, message, ...}`; on success also `version`,
	 *     `upToDate`, `added`, `removed`, `changed`, `kept` and `copies`. `notInstalled`
	 *     is true when there is nothing to update.
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-012-update-brings-an-installed-dashboard-to-the-newer-version-in-place
	 */
	public function update(string $slug, string $userId): array {
		$install = ($this->installs->all()[$slug] ?? null);
		if ($install === null) {
			return ['success' => false, 'notInstalled' => true, 'message' => 'That template is not installed here, so there is nothing to update.'];
		}

		$resolved = $this->store->resolveItem(slug: $slug);
		if (isset($resolved['dashboards']) === false) {
			return ['success' => false, 'message' => $resolved['message']];
		}

		if (version_compare($resolved['version'], $install['version'], '>') === false) {
			return ['success' => true, 'components' => [], 'upToDate' => true, 'version' => $install['version'], 'message' => 'This dashboard is already up to date.'];
		}

		try {
			$dashboard = $this->stores->dashboardMapper->find(id: $install['dashboardId']);
		} catch (Throwable) {
			$this->installs->forget(dashboardId: $install['dashboardId']);
			return ['success' => false, 'notInstalled' => true, 'message' => 'The installed dashboard no longer exists.'];
		}

		try {
			return $this->apply(
				slug: $slug,
				dashboard: $dashboard,
				install: $install,
				resolved: $resolved,
				userId: $userId
			);
		} catch (Throwable $e) {
			$this->logger->error(
				message: 'Store update failed for ' . $slug . ': ' . $e->getMessage(),
				context: ['app' => Application::APP_ID]
			);
			return ['success' => false, 'message' => 'That dashboard could not be updated.'];
		}
	}//end update()

	/**
	 * Pair, write, record, re-sync and note the version.
	 *
	 * @param string                                                          $slug      The item slug.
	 * @param Dashboard                                                       $dashboard The installed dashboard.
	 * @param array{version: string, dashboardId: int, dashboardUuid: string, placementIds: list<int>} $install The install record.
	 * @param array{version: string, dashboards: array<int, array<string, mixed>>} $resolved The register item.
	 * @param string                                                          $userId    The acting administrator.
	 *
	 * @return array<string, mixed> The report.
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-012-update-brings-an-installed-dashboard-to-the-newer-version-in-place
	 */
	private function apply(string $slug, Dashboard $dashboard, array $install, array $resolved, string $userId): array {
		$isTemplate = ($dashboard->getType() === Dashboard::TYPE_ADMIN_TEMPLATE);
		$split = $this->split(
			placements: $this->stores->placementMapper->findByDashboardId(dashboardId: (int)$dashboard->getId()),
			tracked: $install['placementIds']
		);

		$plan = $this->pairing->pair(
			installed: $split['fromInstall'],
			wanted: $this->wanted(dashboard: $dashboard, payload: $resolved['dashboards'][0], asTemplate: $isTemplate)
		);
		if ($install['placementIds'] === []) {
			// A record without ids cannot tell an installed widget from a person's own: remove nothing.
			$plan['removed'] = [];
		}

		$report = $this->pairing->describe(plan: $plan);
		$this->pairing->apply(template: $dashboard, plan: $plan);

		$this->installs->record(
			slug: $slug,
			version: $resolved['version'],
			dashboard: ['id' => (int)$dashboard->getId(), 'uuid' => (string)$dashboard->getUuid()],
			placementIds: $this->idsAfter(plan: $plan)
		);

		$this->versions->captureSnapshot(
			dashboard: $dashboard,
			createdBy: $userId,
			options: ['note' => self::VERSION_NOTE, 'explicit' => true]
		);

		return [
			'success'    => true,
			// The store page names every component that is not `installed`; an update has none to name.
			'components' => [],
			'upToDate'   => false,
			'message'    => 'Updated to version ' . $resolved['version'] . '.',
			'version'    => $resolved['version'],
			'kept'       => count($split['own']),
			'copies'     => $this->resyncCopies(dashboard: $dashboard, isTemplate: $isTemplate, userId: $userId),
		] + $report;
	}//end apply()

	/**
	 * Split the dashboard's widgets into those from the install and a person's own.
	 *
	 * @param array<int, WidgetPlacement> $placements The dashboard's widgets now.
	 * @param list<int>                   $tracked    The ids the install record holds.
	 *
	 * @return array{fromInstall: array<int, WidgetPlacement>, own: array<int, WidgetPlacement>}
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-012-update-brings-an-installed-dashboard-to-the-newer-version-in-place
	 */
	private function split(array $placements, array $tracked): array {
		if ($tracked === []) {
			return ['fromInstall' => array_values($placements), 'own' => []];
		}

		$fromInstall = [];
		$own = [];
		foreach ($placements as $placement) {
			if (in_array((int)$placement->getId(), $tracked, true) === true) {
				$fromInstall[] = $placement;
				continue;
			}

			$own[] = $placement;
		}

		return ['fromInstall' => $fromInstall, 'own' => $own];
	}//end split()

	/**
	 * The new version's widgets on this dashboard, unsaved.
	 *
	 * @param Dashboard            $dashboard  The installed dashboard.
	 * @param array<string, mixed> $payload    The new version's dashboard payload.
	 * @param bool                 $asTemplate Whether the dashboard is an admin template.
	 *
	 * @return array<int, WidgetPlacement> The widgets.
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
	 *      Private; the hydrator takes the same one flag.
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-012-update-brings-an-installed-dashboard-to-the-newer-version-in-place
	 */
	private function wanted(Dashboard $dashboard, array $payload, bool $asTemplate): array {
		$wanted = [];
		foreach ((array)($payload['widgets'] ?? []) as $widget) {
			if (is_array($widget) === false) {
				continue;
			}

			$wanted[] = $this->hydrator->hydrate(dashboardId: (int)$dashboard->getId(), payload: $widget, asTemplate: $asTemplate);
		}

		return $wanted;
	}//end wanted()

	/**
	 * The ids of the widgets that come from the install after the update.
	 *
	 * @param array<string, mixed> $plan The written plan.
	 *
	 * @return list<int> The placement ids.
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-012-update-brings-an-installed-dashboard-to-the-newer-version-in-place
	 */
	private function idsAfter(array $plan): array {
		$placements = $plan['unchanged'];
		foreach ($plan['changed'] as $pair) {
			$placements[] = $pair['installed'];
		}

		$placements = array_merge($placements, $plan['added']);

		return array_values(array_map(static fn (WidgetPlacement $p): int => (int)$p->getId(), $placements));
	}//end idsAfter()

	/**
	 * Merge the updated dashboard into its copies when it is a template (REQ-RESYNC-003).
	 *
	 * @param Dashboard $dashboard  The updated dashboard.
	 * @param bool      $isTemplate Whether copies can exist.
	 * @param string    $userId     The acting administrator.
	 *
	 * @return int How many copies were brought along.
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
	 *      Private; only an admin template has copies.
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-012-update-brings-an-installed-dashboard-to-the-newer-version-in-place
	 */
	private function resyncCopies(Dashboard $dashboard, bool $isTemplate, string $userId): int {
		if ($isTemplate === false) {
			return 0;
		}

		$result = $this->resync->resync(
			templateId: (int)$dashboard->getId(),
			strategy: TemplateResyncService::STRATEGY_MERGE,
			dryRun: false,
			actingAdminId: $userId
		);

		return (int)($result['affectedCount'] ?? 0);
	}//end resyncCopies()
}//end class
