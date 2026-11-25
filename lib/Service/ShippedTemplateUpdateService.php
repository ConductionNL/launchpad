<?php

/**
 * ShippedTemplateUpdateService
 *
 * Brings an installed shipped template to the version LaunchPad ships now,
 * in place: the template keeps its id, UUID, name, groups and default flag,
 * and its widgets become the new version's. Members' copies then follow
 * through the existing re-sync ({@see TemplateResyncService}, `merge`).
 *
 * WHY THE ROWS ARE KEPT. A member's copy remembers, per widget, the id of the
 * template widget it was made from. Re-sync matches on that id. So a widget
 * that is in both versions keeps its row and is changed there; only a widget
 * the new version adds gets a new row, and only one it drops loses its row.
 * Replacing every row would still give members the right page, but each of
 * their widgets would be removed and added again.
 *
 * HOW A WIDGET IS RECOGNISED. A definition carries no key per widget, so the
 * installed widgets are paired with the new ones in three passes, each pass
 * only over what the pass before left: (1) every compared field equal, (2)
 * same widget type, same proxied Nextcloud widget and same title, (3) same
 * widget type, when exactly one is left on each side. What is then left of
 * the new version is added, what is left of the installed one is removed.
 *
 * NEVER SILENTLY. The result names every widget added, removed and changed,
 * and a dry run returns the same lists without writing anything.
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
 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Exception\TemplateNotInstalledException;
use OCA\LaunchPad\Kernel\Contracts\ShippedTemplateUpdateServiceInterface;
use RuntimeException;

/**
 * Update an installed shipped template in place and bring its copies along.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 *      One operation over the definition, the template's rows, the
 *      transaction, the recorded version and the re-sync. Splitting it would
 *      put the write and the version it must stay in step with in two places.
 *
 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
 */
class ShippedTemplateUpdateService implements ShippedTemplateUpdateServiceInterface {
	/**
	 * Pairs the installed widgets with the shipped ones and writes the plan.
	 *
	 * @var PlacementPairing
	 */
	private readonly PlacementPairing $pairing;

	/**
	 * Constructor.
	 *
	 * @param ShippedTemplateService   $shipped Reads the definition and finds the install.
	 * @param ShippedTemplateStores    $stores The dashboard and placement mappers, the database and app config.
	 * @param PlacementPayloadHydrator $hydrator Builds a widget from a definition, as the importer does.
	 * @param TemplateResyncService    $resyncService Brings the members' copies along.
	 * @param PlacementPairing|null    $pairing Pairs and writes the widgets; built over the stores when absent.
	 */
	public function __construct(
		private readonly ShippedTemplateService $shipped,
		private readonly ShippedTemplateStores $stores,
		private readonly PlacementPayloadHydrator $hydrator,
		private readonly TemplateResyncService $resyncService,
		?PlacementPairing $pairing = null,
	) {
		$this->pairing = ($pairing ?? new PlacementPairing(stores: $stores));
	}//end __construct()

	/**
	 * Update the installed template to the shipped version, or say what
	 * that would change.
	 *
	 * Nothing happens when the installed version is the shipped one or
	 * newer: the result says so (`upToDate`) and its lists are empty.
	 *
	 * @param string $templateId The shipped template id.
	 * @param bool   $dryRun     Report only, write nothing.
	 * @param string $userId     Who runs the update (for the re-sync's audit record).
	 *
	 * @return array{templateId:string, uuid:string, id:int, name:string, installedVersion:int, version:int,
	 *               upToDate:bool, dryRun:bool, applied:bool, added:array<int,string>, removed:array<int,string>,
	 *               changed:array<int,array{widget:string, fields:array<int,string>}>, unchanged:int, copies:int,
	 *               resync:array{async:bool, affectedCount:int, totalCopies:int}|null}
	 *
	 * @throws \InvalidArgumentException When the id is not a shipped template.
	 * @throws TemplateNotInstalledException When the template is not installed here.
	 * @throws RuntimeException When the definition is malformed or the write fails.
	 *
	 * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
	 *      The flag is the command's `--dry-run` and the request's `dryRun`, one to one.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
	 */
	public function update(string $templateId, bool $dryRun = false, string $userId = 'cli'): array {
		$run = $this->updateRunFor(templateId: $templateId);

		$result = $this->versionReport(run: $run, dryRun: $dryRun);
		if ($result['upToDate'] === true) {
			return $result;
		}

		$plan = $this->planFor(run: $run);
		$result = array_merge($result, $this->pairing->describe(plan: $plan));
		if ($dryRun === true) {
			return $result;
		}

		$this->applyPlan(run: $run, plan: $plan);

		return $this->appliedResult(result: $result, resync: $this->resyncCopies(run: $run, userId: $userId));
	}//end update()

	/**
	 * Read a shipped template's definition and find its installed template.
	 *
	 * @param string $templateId The shipped template id.
	 *
	 * @return ShippedTemplateUpdateRun The update.
	 *
	 * @throws TemplateNotInstalledException When it was never installed here.
	 */
	private function updateRunFor(string $templateId): ShippedTemplateUpdateRun {
		$definition = $this->shipped->readDefinition(templateId: $templateId);

		return new ShippedTemplateUpdateRun(
			templateId: $templateId,
			installed: new InstalledShippedTemplate(
				definition: $definition,
				template: $this->installedTemplate(templateId: $templateId)
			)
		);
	}//end updateRunFor()

	/**
	 * Mark a report as applied and attach the resync outcome.
	 *
	 * @param array<string, mixed>                                $result The report.
	 * @param array{async: bool, affectedCount: int, totalCopies: int} $resync The resync outcome.
	 *
	 * @return array<string, mixed> The final report.
	 */
	private function appliedResult(array $result, array $resync): array {
		$result['applied'] = true;
		$result['resync'] = $resync;

		return $result;
	}//end appliedResult()

	/**
	 * The installed template dashboard of a shipped template.
	 *
	 * @param string $templateId The shipped template id.
	 *
	 * @return Dashboard The template dashboard.
	 *
	 * @throws TemplateNotInstalledException When it was never installed here.
	 */
	private function installedTemplate(string $templateId): Dashboard {
		$template = $this->shipped->findInstalled(templateId: $templateId);
		if ($template === null) {
			throw new TemplateNotInstalledException(
				message: 'Template ' . $templateId . ' is not installed here, so there is nothing to update.'
			);
		}

		return $template;
	}//end installedTemplate()

	/**
	 * The report before any pairing: who the template is, the installed
	 * and shipped versions, and empty change lists.
	 *
	 * @param ShippedTemplateUpdateRun $run    The update.
	 * @param boolean                  $dryRun Whether only to report.
	 *
	 * @return array<string, mixed> The report.
	 */
	private function versionReport(ShippedTemplateUpdateRun $run, bool $dryRun): array {
		$installedVersion = $this->stores->appConfig->getValueInt(
			Application::APP_ID,
			ShippedTemplateService::CONFIG_VERSION_PREFIX . $run->templateId,
			0
		);

		return $this->templateIdentity(run: $run)
			+ $this->versionState(installed: $run->installed, installedVersion: $installedVersion)
			+ ['dryRun' => $dryRun, 'applied' => false]
			+ ['added' => [], 'removed' => [], 'changed' => [], 'unchanged' => 0]
			+ [
				'copies' => count($this->stores->dashboardMapper->findByBasedOnTemplate(templateId: $run->installed->dashboardId())),
				'resync' => null,
			];
	}//end versionReport()

	/**
	 * Which template the report is about.
	 *
	 * @param ShippedTemplateUpdateRun $run The update.
	 *
	 * @return array{templateId: string, uuid: string, id: int, name: string}
	 */
	private function templateIdentity(ShippedTemplateUpdateRun $run): array {
		return [
			'templateId' => $run->templateId,
			'uuid' => (string)$run->installed->template->getUuid(),
			'id' => $run->installed->dashboardId(),
			'name' => (string)$run->installed->template->getName(),
		];
	}//end templateIdentity()

	/**
	 * The version part of the report.
	 *
	 * @param InstalledShippedTemplate $installed        The installed template.
	 * @param integer                  $installedVersion The recorded installed version.
	 *
	 * @return array{installedVersion: int, version: int, upToDate: bool}
	 */
	private function versionState(InstalledShippedTemplate $installed, int $installedVersion): array {
		return [
			'installedVersion' => $installedVersion,
			'version' => $installed->version(),
			'upToDate' => $installedVersion >= $installed->version(),
		];
	}//end versionState()

	/**
	 * Pair the installed placements with the shipped widgets.
	 *
	 * @param ShippedTemplateUpdateRun $run The update.
	 *
	 * @return array<string, mixed> The plan.
	 */
	private function planFor(ShippedTemplateUpdateRun $run): array {
		$wanted = [];
		foreach ($run->installed->definition['dashboard']['widgets'] as $payload) {
			$wanted[] = $this->hydrator->hydrate(dashboardId: $run->installed->dashboardId(), payload: (array)$payload, asTemplate: true);
		}

		return $this->pairing->pair(
			installed: $this->stores->placementMapper->findByDashboardId(dashboardId: $run->installed->dashboardId()),
			wanted: $wanted
		);
	}//end planFor()

	/**
	 * Apply the plan to the template and record the new version.
	 *
	 * @param ShippedTemplateUpdateRun $run  The update.
	 * @param array<string, mixed>     $plan The pairing plan.
	 *
	 * @return void
	 */
	private function applyPlan(ShippedTemplateUpdateRun $run, array $plan): void {
		$this->pairing->apply(template: $run->installed->template, plan: $plan);
		$this->stores->appConfig->setValueInt(
			Application::APP_ID,
			ShippedTemplateService::CONFIG_VERSION_PREFIX . $run->templateId,
			$run->installed->version()
		);
	}//end applyPlan()

	/**
	 * Merge the updated template into every copy: a member's own widgets
	 * stay, every template widget is brought in line, compulsory ones
	 * included (REQ-RESYNC-003, -004).
	 *
	 * @param ShippedTemplateUpdateRun $run    The update.
	 * @param string                   $userId The acting user.
	 *
	 * @return array{async: bool, affectedCount: int, totalCopies: int}
	 */
	private function resyncCopies(ShippedTemplateUpdateRun $run, string $userId): array {
		$resync = $this->resyncService->resync(
			templateId: $run->installed->dashboardId(),
			strategy: TemplateResyncService::STRATEGY_MERGE,
			dryRun: false,
			actingAdminId: $userId
		);

		return [
			'async' => ($resync['async'] ?? false) === true,
			'affectedCount' => (int)($resync['affectedCount'] ?? 0),
			'totalCopies' => (int)($resync['totalCopies'] ?? 0),
		];
	}//end resyncCopies()

}//end class
