<?php

/**
 * DemoShowcasesService
 *
 * Lists, installs, and uninstalls bundled demo "showcase" dashboards
 * shipped with LaunchPad. Each showcase is delivered as a
 * `launchpad-export-v1.zip` archive under `data/demo-showcases/{id}/{id}.zip`
 * and is installed as a `group_shared` dashboard scoped to the
 * `default` sentinel group so every user sees it (REQ-DASH-012).
 *
 * Installation tracking is per-showcase via `IConfig::getAppValue` keys
 * shaped as `showcase_installed_<id>` whose value is the installed
 * dashboard UUID — the reference source app used a single boolean
 * which only supports one dataset; the per-showcase approach scales
 * naturally (REQ-DEMO-004).
 *
 * Implements the `demo-data-showcases` capability (REQ-DEMO-001..009).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTime;
use DateTimeImmutable;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Exception\ShowcaseNotFoundException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Dashboard\IManager;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Manage bundled demo showcase dashboards.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 *      Mirrors injected dependencies — list / install / uninstall
 *      cohesively belong on one service surface.
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 *      Bundled-asset extraction + ZIP parse + DB writes are kept in
 *      one service so callers see a single entry point.
 */
class DemoShowcasesService {
	/**
	 * Dashboard metadata key used for idempotency tracking.
	 *
	 * Stored as `showcase_installed_<id>` via IConfig; the value is the
	 * installed dashboard UUID. Empty string means "not installed".
	 *
	 * @var string
	 */
	public const CONFIG_PREFIX = 'showcase_installed_';

	/**
	 * Bundled showcase IDs.
	 *
	 * Two kinds, and the difference is what a reader is meant to take
	 * away. The five Dutch fictional ORGANISATIONS mirror the reference
	 * source dataset so existing copy and screenshots stay reusable;
	 * they answer "what does an intranet built on this look like".
	 *
	 * `case-handler` is the first ROLE showcase and answers a different
	 * question: what one person's working day looks like on one page.
	 * It is in English because the widgets it places are the fleet's own
	 * (a dossiq case list, the Tasks app, a calendar, unread mail) and
	 * those carry English labels, so a Dutch shell around English
	 * content would read as a half-translation.
	 *
	 * @var array<int, string>
	 */
	public const BUNDLED_IDS = [
		'case-handler',
		'de-bron',
		'de-linden',
		'gemeente-duin',
		'horizon-labs',
		'van-der-berg',
		'zuiddrecht-intranet',
		'zuiddrecht-management',
	];

	/**
	 * Where the list of LaunchPad's own widget types lives.
	 *
	 * Shared with the frontend: `widgetRegistry.completeness.spec.js`
	 * asserts the registry's keys equal this file, so the types the
	 * installer keeps and the types the workspace can render cannot drift.
	 *
	 * @var string
	 */
	private const WIDGET_TYPES_PATH = __DIR__ . '/../widget-types.json';

	/**
	 * Optional data-directory override (test seam).
	 *
	 * @var string|null
	 */
	private ?string $dataDirOverride = null;

	/**
	 * LaunchPad widget types, keyed by type, loaded once per instance.
	 *
	 * @var array<string, true>|null
	 */
	private ?array $launchpadWidgetTypes = null;

	/**
	 * Builds placements from the archive; see PlacementPayloadHydrator for
	 * which fields travel and why the importer uses the same one. It holds
	 * no state and has no dependencies, so it is constructed here rather
	 * than injected.
	 *
	 * @var PlacementPayloadHydrator
	 */
	private readonly PlacementPayloadHydrator $placementHydrator;

	/**
	 * Constructor.
	 *
	 * @param ShowcaseStores   $stores The dashboard and placement mappers, the database and the lock provider.
	 * @param ShowcasePlatform $platform App config, dashboard manager, URL generator and event dispatcher.
	 * @param LoggerInterface  $logger PSR-3 logger.
	 * @param CatalogueService|null $catalogue Seeds the demo app catalogue entries on an install (launcher-app-catalogue).
	 */
	public function __construct(
		private readonly ShowcaseStores $stores,
		private readonly ShowcasePlatform $platform,
		private readonly LoggerInterface $logger,
		private readonly ?CatalogueService $catalogue = null,
	) {
		$this->placementHydrator = new PlacementPayloadHydrator();
	}//end __construct()

	/**
	 * Override the bundled-showcase data directory (test seam).
	 *
	 * Production code MUST NOT call this — the directory resolves
	 * automatically against the app source tree. Tests pass an
	 * explicit fixture directory so they can stage controlled ZIPs.
	 *
	 * @param string $path Absolute path to the test fixture directory.
	 *
	 * @return void
	 */
	public function setDataDirForTesting(string $path): void {
		$this->dataDirOverride = $path;
	}//end setDataDirForTesting()

	/**
	 * Resolve the on-disk path of the bundled showcase data directory.
	 *
	 * Returns `<app-root>/data/demo-showcases` unless overridden via
	 * {@see DemoShowcasesService::setDataDirForTesting()}.
	 *
	 * @return string Absolute filesystem path.
	 *
	 * @spec openspec/specs/demo-data-showcases/spec.md
	 */
	public function getDataDir(): string {
		if ($this->dataDirOverride !== null) {
			return $this->dataDirOverride;
		}

		return dirname(path: __DIR__, levels: 2) . '/data/demo-showcases';
	}//end getDataDir()

	/**
	 * List every bundled showcase with installation status.
	 *
	 * Iterates the manifest set rather than scanning the directory so
	 * the returned list is stable and predictable for the admin UI.
	 *
	 * @return array<int, array<string, mixed>> Showcase descriptors.
	 *
	 * @spec openspec/specs/demo-data-showcases/spec.md
	 */
	public function getAvailableShowcases(): array {
		$result = [];
		foreach (self::BUNDLED_IDS as $id) {
			$descriptor = $this->describeShowcase(showcaseId: $id);
			if ($descriptor === null) {
				continue;
			}

			$result[] = $descriptor;
		}

		return $result;
	}//end getAvailableShowcases()

	/**
	 * Resolve a single descriptor with installation status.
	 *
	 * Returns `null` when the underlying ZIP is unreadable so the
	 * caller can skip the entry without surfacing partial data
	 * (REQ-DEMO-001 invalid-ZIP scenario).
	 *
	 * @param string $showcaseId The showcase ID.
	 *
	 * @return array<string, mixed>|null The descriptor, or `null` when
	 *                                   the ZIP is missing/malformed.
	 *
	 * @spec openspec/specs/demo-data-showcases/spec.md
	 */
	public function describeShowcase(string $showcaseId): ?array {
		$manifest = $this->readShowcaseManifest(showcaseId: $showcaseId);
		if ($manifest === null) {
			return null;
		}

		return $this->descriptorFor(showcaseId: $showcaseId, manifest: $manifest);
	}//end describeShowcase()

	/**
	 * The manifest of a showcase's archive, null when the archive is
	 * absent or its manifest unreadable.
	 *
	 * @param string $showcaseId The showcase ID.
	 *
	 * @return array<string, mixed>|null The decoded manifest.
	 */
	private function readShowcaseManifest(string $showcaseId): ?array {
		$zipPath = $this->getZipPath(showcaseId: $showcaseId);
		if (file_exists(filename: $zipPath) === false) {
			return null;
		}

		return $this->readManifest(zipPath: $zipPath);
	}//end readShowcaseManifest()

	/**
	 * Build the gallery descriptor of a readable showcase.
	 *
	 * @param string               $showcaseId The showcase ID.
	 * @param array<string, mixed> $manifest   The decoded manifest.
	 *
	 * @return array<string, mixed> The descriptor.
	 */
	private function descriptorFor(string $showcaseId, array $manifest): array {
		$installedUuid = $this->getInstalledUuid(showcaseId: $showcaseId);

		return [
			'id' => $showcaseId,
			'name' => (string)($manifest['showcaseName'] ?? $showcaseId),
			'description' => (string)($manifest['showcaseDescription'] ?? ''),
			'language' => (string)($manifest['showcaseLanguage'] ?? 'nl'),
			'thumbnailUrl' => $this->thumbnailUrl(showcaseId: $showcaseId),
			'isInstalled' => $installedUuid !== '',
			'installedDashboardUuid' => $this->installedDashboardUuid(installedUuid: $installedUuid),
		];
	}//end descriptorFor()

	/**
	 * The showcase's preview image URL, null (logged) when it is absent.
	 *
	 * @param string $showcaseId The showcase ID.
	 *
	 * @return string|null The URL, or null.
	 */
	private function thumbnailUrl(string $showcaseId): ?string {
		// The imagePath() call THROWS when the image is absent, and this runs once per
		// showcase inside the listing, so one showcase without a preview used
		// to 500 the whole gallery. A missing thumbnail is a cosmetic gap in
		// one card, not a reason to hide the other five.
		try {
			return $this->platform->urlGenerator->imagePath(
				Application::APP_ID,
				'showcases/' . $showcaseId . '.png'
			);
		} catch (\RuntimeException $e) {
			$this->logger->warning(
				message: 'Showcase ' . $showcaseId . ' has no preview image',
				context: ['exception' => $e]
			);
			return null;
		}
	}//end thumbnailUrl()

	/**
	 * The installed dashboard UUID, null when the showcase is not installed.
	 *
	 * @param string $installedUuid The recorded UUID, or `''`.
	 *
	 * @return string|null The UUID, or null.
	 */
	private function installedDashboardUuid(string $installedUuid): ?string {
		if ($installedUuid === '') {
			return null;
		}

		return $installedUuid;
	}//end installedDashboardUuid()

	/**
	 * Install a showcase as a `group_shared` dashboard.
	 *
	 * Idempotent: when the showcase is already installed and `$force`
	 * is `false` the existing UUID is returned without further work.
	 * When `$force` is `true` any existing install is uninstalled first
	 * so the caller always receives a fresh dashboard UUID
	 * (REQ-DEMO-004, REQ-DEMO-009 `--force` flag).
	 *
	 * @param string $showcaseId The showcase ID.
	 * @param string $lang Optional locale (accepted for forward
	 *                     compatibility, always resolves to `nl`
	 *                     in v1 — REQ-DEMO-007).
	 * @param bool $force When true, reinstall even if the
	 *                    showcase is already installed.
	 *
	 * @return array{installedDashboardUuid:string, skippedWidgets:array<int, string>, alreadyInstalled:bool}
	 *
	 * @throws ShowcaseNotFoundException When the showcase ID is unknown.
	 * @throws RuntimeException On ZIP / persistence failures.
	 *
	 * @spec openspec/specs/demo-data-showcases/spec.md
	 */
	public function installShowcase(
		string $showcaseId,
		string $lang = 'nl',
		bool $force = false,
	): array {
		$this->assertInstallable(showcaseId: $showcaseId);

		// M8: acquire an exclusive advisory lock keyed on the showcase ID
		// before the existence check to serialise concurrent installs.
		// Two simultaneous admin requests both pass the existence check
		// before either inserts without this guard.
		$lockKey = 'launchpad-showcase-install-' . $showcaseId;
		$this->acquireInstallLock(lockKey: $lockKey, showcaseId: $showcaseId);

		try {
			return $this->installLocked(showcaseId: $showcaseId, options: new ShowcaseInstallOptions(lang: $lang, force: $force));
		} finally {
			$this->releaseInstallLock(lockKey: $lockKey);
		}
	}//end installShowcase()

	/**
	 * Refuse an unknown showcase or one whose archive is missing.
	 *
	 * @param string $showcaseId The showcase ID.
	 *
	 * @return void
	 *
	 * @throws ShowcaseNotFoundException When the showcase cannot be installed.
	 */
	private function assertInstallable(string $showcaseId): void {
		$this->assertKnownShowcase(showcaseId: $showcaseId);

		$zipPath = $this->getZipPath(showcaseId: $showcaseId);
		if (file_exists(filename: $zipPath) === false) {
			throw new ShowcaseNotFoundException(
				message: 'Showcase ZIP missing for ' . $showcaseId
			);
		}
	}//end assertInstallable()

	/**
	 * Take the exclusive install lock of a showcase.
	 *
	 * @param string $lockKey    The lock path.
	 * @param string $showcaseId The showcase ID (for the message).
	 *
	 * @return void
	 *
	 * @throws RuntimeException When another install holds the lock.
	 */
	private function acquireInstallLock(string $lockKey, string $showcaseId): void {
		try {
			$this->stores->lockingProvider->acquireLock(
				path: $lockKey,
				type: ILockingProvider::LOCK_EXCLUSIVE
			);
		} catch (LockedException $e) {
			throw new RuntimeException(
				message: 'Showcase installation already in progress for ' . $showcaseId,
				code: 0,
				previous: $e
			);
		}
	}//end acquireInstallLock()

	/**
	 * Release the exclusive install lock of a showcase.
	 *
	 * @param string $lockKey The lock path.
	 *
	 * @return void
	 */
	private function releaseInstallLock(string $lockKey): void {
		$this->stores->lockingProvider->releaseLock(
			path: $lockKey,
			type: ILockingProvider::LOCK_EXCLUSIVE
		);
	}//end releaseInstallLock()

	/**
	 * Install while the lock is held: reuse an existing install, or
	 * replace it when forced, else install fresh.
	 *
	 * @param string                 $showcaseId The showcase ID.
	 * @param ShowcaseInstallOptions $options    The language and force flag.
	 *
	 * @return array{installedDashboardUuid:string, skippedWidgets:array<int, string>, alreadyInstalled:bool}
	 */
	private function installLocked(string $showcaseId, ShowcaseInstallOptions $options): array {
		$existingUuid = $this->getInstalledUuid(showcaseId: $showcaseId);
		if ($existingUuid !== '' && $options->force === false) {
			return [
				'installedDashboardUuid' => $existingUuid,
				'skippedWidgets' => [],
				'alreadyInstalled' => true,
			];
		}

		if ($existingUuid !== '') {
			$this->uninstallShowcase(showcaseId: $showcaseId);
		}

		return $this->doInstallShowcase(showcaseId: $showcaseId, lang: $options->lang);
	}//end installLocked()

	/**
	 * Internal install logic (called while lock is held).
	 *
	 * @param string $showcaseId The showcase ID.
	 * @param string $lang The source language.
	 *
	 * @return array{installedDashboardUuid: string, skippedWidgets: array<int, string>, alreadyInstalled: bool}
	 */
	private function doInstallShowcase(string $showcaseId, string $lang): array {
		$zipPath = $this->getZipPath(showcaseId: $showcaseId);

		$payload = $this->loadDashboardPayload(zipPath: $zipPath);
		$widgets = (array)($payload['widgets'] ?? []);

		[$valid, $skipped] = $this->partitionWidgets(widgets: $widgets);

		$dashboard = $this->buildDashboardEntity(
			showcaseId: $showcaseId,
			payload: $payload,
			sourceLanguage: $lang
		);

		$this->stores->db->beginTransaction();
		try {
			$persisted = $this->stores->dashboardMapper->insert(entity: $dashboard);
			foreach ($valid as $widgetPayload) {
				$placement = $this->placementHydrator->hydrate(
					dashboardId: (int)$persisted->getId(),
					payload: $widgetPayload
				);
				$this->stores->placementMapper->insert(entity: $placement);
			}

			$this->stores->db->commit();
		} catch (Throwable $e) {
			$this->stores->db->rollBack();
			throw new RuntimeException(
				message: 'Failed to persist showcase: ' . $e->getMessage(),
				code: 0,
				previous: $e
			);
		}

		$installedUuid = (string)$persisted->getUuid();
		$this->markInstalled(
			showcaseId: $showcaseId,
			dashboardUuid: $installedUuid
		);
		$this->seedCatalogue();

		$this->logger->info(
			message: 'Installed demo showcase',
			context: [
				'showcaseId' => $showcaseId,
				'dashboardUuid' => $installedUuid,
				'skippedWidgets' => $skipped,
				'sourceLanguage' => $lang,
			]
		);

		return [
			'installedDashboardUuid' => $installedUuid,
			'skippedWidgets' => $skipped,
			'alreadyInstalled' => false,
		];
	}//end doInstallShowcase()

	/**
	 * Give an empty app catalogue the demo entries a municipality would
	 * recognise (launcher-app-catalogue, design "Seed data"). A failure is
	 * logged and does not undo the showcase.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/launcher-app-catalogue/tasks.md#task-9
	 */
	private function seedCatalogue(): void {
		if ($this->catalogue === null) {
			return;
		}

		try {
			$this->catalogue->seedDemoEntries();
		} catch (Throwable $e) {
			$this->logger->warning(
				message: 'Could not seed the demo app catalogue: ' . $e->getMessage(),
				context: ['exception' => $e]
			);
		}
	}//end seedCatalogue()

	/**
	 * Uninstall a previously-installed showcase.
	 *
	 * Idempotent — silently no-ops when the showcase has no recorded
	 * installation. Cascades to widget placements and clears the
	 * tracking config key (REQ-DEMO-006).
	 *
	 * @param string $showcaseId The showcase ID.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/demo-data-showcases/spec.md
	 */
	public function uninstallShowcase(string $showcaseId): void {
		$existingUuid = $this->getInstalledUuid(showcaseId: $showcaseId);
		if ($existingUuid === '') {
			return;
		}

		try {
			$dashboard = $this->stores->dashboardMapper->findByUuid(uuid: $existingUuid);
			$dashId = (int)$dashboard->getId();
			$this->stores->placementMapper->deleteByDashboardId(dashboardId: $dashId);
			$this->stores->dashboardMapper->delete(entity: $dashboard);

			// SB1 fix: dispatch DashboardDeletedEvent for cascade cleanup
			// (REQ-CSC-001).
			$deletedUuid = (string)$dashboard->getUuid();
			if ($this->platform->eventDispatcher !== null && $deletedUuid !== '') {
				$this->platform->eventDispatcher->dispatchTyped(
					new DashboardDeletedEvent(
						dashboardUuid: $deletedUuid,
						ownerUserId:   (string)($dashboard->getUserId() ?? ''),
						type:          (string)($dashboard->getType() ?? Dashboard::TYPE_GROUP_SHARED),
						deletedAt:     new DateTimeImmutable(),
						dashboardId:   $dashId
					)
				);
			}
		} catch (DoesNotExistException) {
			// The dashboard was deleted out-of-band — clear the marker
			// and proceed.
			$this->logger->warning(
				message: 'Showcase dashboard already missing on uninstall',
				context: ['showcaseId' => $showcaseId, 'uuid' => $existingUuid]
			);
		}//end try

		$this->clearInstalledMarker(showcaseId: $showcaseId);
	}//end uninstallShowcase()

	/**
	 * Look up the recorded installed dashboard UUID for a showcase.
	 *
	 * @param string $showcaseId The showcase ID.
	 *
	 * @return string The UUID, or empty string when not installed.
	 *
	 * @spec openspec/specs/demo-data-showcases/spec.md
	 */
	public function getInstalledUuid(string $showcaseId): string {
		return $this->platform->appConfig->getValueString(
			Application::APP_ID,
			self::CONFIG_PREFIX . $showcaseId,
			''
		);
	}//end getInstalledUuid()

	/**
	 * Split a showcase's widgets into the ones to place and the ones to
	 * skip (REQ-DEMO-005).
	 *
	 * A widget is kept when LaunchPad can render it. That is true in three
	 * cases: it is a tile (`tileType` set), it is one of LaunchPad's own
	 * widget types (`lib/widget-types.json`), or it is a Nextcloud dashboard
	 * widget registered on this instance. Before the second case existed
	 * every LaunchPad widget was skipped, so a showcase built entirely from
	 * LaunchPad types, installed as an empty
	 * dashboard and still reported success.
	 *
	 * An `nc-widget` is kept whatever widget it proxies. The check is on the
	 * widget's TYPE, as REQ-DEMO-005 words it; whether the proxied app is
	 * installed decides what the tile shows, not whether it exists. Checking
	 * the target here would make a showcase's shape depend on which apps an
	 * instance happens to have.
	 *
	 * @param array<int, mixed> $widgets Widget payloads from the
	 *                                   showcase JSON.
	 *
	 * @return array{0:array<int,array<string,mixed>>, 1:array<int,string>}
	 *
	 * @spec openspec/specs/demo-data-showcases/spec.md
	 */
	public function partitionWidgets(array $widgets): array {
		$registered = $this->registeredWidgetIds();
		$known = $this->getLaunchpadWidgetTypes() + $registered;

		$valid = [];
		$skipped = [];
		foreach (array_filter(array: $widgets, callback: 'is_array') as $widget) {
			if ($this->isPlaceable(widget: $widget, known: $known) === true) {
				$valid[] = $widget;
			} elseif ((string)($widget['widgetId'] ?? '') !== '') {
				$skipped[] = (string)$widget['widgetId'];
			}
		}

		return [$valid, $skipped];
	}//end partitionWidgets()

	/**
	 * The ids of the Nextcloud dashboard widgets registered on this
	 * instance, keyed for `isset()` lookups.
	 *
	 * @return array<string, true> The registered ids.
	 */
	private function registeredWidgetIds(): array {
		$registered = [];
		foreach ($this->platform->dashboardManager->getWidgets() as $widget) {
			$registered[$widget->getId()] = true;
		}

		return $registered;
	}//end registeredWidgetIds()

	/**
	 * Whether LaunchPad can render a showcase widget: a tile, or a widget
	 * whose id is a known LaunchPad or registered Nextcloud type.
	 *
	 * @param array<string, mixed> $widget The widget payload.
	 * @param array<string, true>  $known  The renderable widget ids.
	 *
	 * @return bool True when the widget is placed.
	 */
	private function isPlaceable(array $widget, array $known): bool {
		// Tile placements bypass the widget registry — they render
		// through the LaunchPad tile renderer (REQ-DEMO-005).
		if ((string)($widget['tileType'] ?? '') !== '') {
			return true;
		}

		return isset($known[(string)($widget['widgetId'] ?? '')]) === true;
	}//end isPlaceable()

	/**
	 * Load LaunchPad's own widget types from `lib/widget-types.json`.
	 *
	 * Throws rather than returning an empty set when the file is missing or
	 * unreadable. An empty set is exactly the defect this list fixes: every
	 * LaunchPad widget in a showcase would be skipped and the install would
	 * still report success, which is the failure nobody noticed the first
	 * time. A packaging error must be loud.
	 *
	 * @return array<string, true> The types, keyed for `isset()` lookups.
	 *
	 * @throws RuntimeException When the list is missing or malformed.
	 *
	 * @spec openspec/specs/demo-data-showcases/spec.md
	 */
	private function getLaunchpadWidgetTypes(): array {
		if ($this->launchpadWidgetTypes !== null) {
			return $this->launchpadWidgetTypes;
		}

		$decoded = $this->readWidgetTypesFile();
		if (is_array($decoded) === false || is_array($decoded['types'] ?? null) === false) {
			throw new RuntimeException(
				message: 'LaunchPad widget type list missing or malformed: ' . self::WIDGET_TYPES_PATH
			);
		}

		$this->launchpadWidgetTypes = $this->typeSet(types: $decoded['types']);
		return $this->launchpadWidgetTypes;
	}//end getLaunchpadWidgetTypes()

	/**
	 * Decode `lib/widget-types.json`, null when it cannot be read.
	 *
	 * @return mixed The decoded JSON, or null.
	 */
	private function readWidgetTypesFile(): mixed {
		if (is_readable(filename: self::WIDGET_TYPES_PATH) === false) {
			return null;
		}

		$raw = file_get_contents(filename: self::WIDGET_TYPES_PATH);
		if (is_string($raw) === false) {
			return null;
		}

		return json_decode(json: $raw, associative: true);
	}//end readWidgetTypesFile()

	/**
	 * The non-empty string entries of the type list, keyed for `isset()`.
	 *
	 * @param array<int|string, mixed> $types The `types` list.
	 *
	 * @return array<string, true> The types.
	 */
	private function typeSet(array $types): array {
		$set = [];
		foreach ($types as $type) {
			if (is_string($type) === true && $type !== '') {
				$set[$type] = true;
			}
		}

		return $set;
	}//end typeSet()

	/**
	 * Resolve the on-disk ZIP path for a showcase ID.
	 *
	 * @param string $showcaseId The showcase ID.
	 *
	 * @return string The filesystem path.
	 */
	private function getZipPath(string $showcaseId): string {
		return $this->getDataDir() . '/' . $showcaseId . '/' . $showcaseId . '.zip';
	}//end getZipPath()

	/**
	 * Parse and return the manifest from a showcase ZIP archive.
	 *
	 * Returns `null` when the archive is unreadable or the manifest
	 * is malformed; callers treat `null` as "skip this entry"
	 * (REQ-DEMO-001 invalid-ZIP scenario).
	 *
	 * @param string $zipPath The ZIP path.
	 *
	 * @return array<string, mixed>|null The decoded manifest.
	 */
	private function readManifest(string $zipPath): ?array {
		$zip = new ZipArchive();
		if ($zip->open(filename: $zipPath, flags: ZipArchive::RDONLY) !== true) {
			$this->logger->warning(
				message: 'Showcase ZIP could not be opened',
				context: ['path' => $zipPath]
			);
			return null;
		}

		try {
			$raw = $zip->getFromName(name: 'manifest.json');
			if ($raw === false) {
				return null;
			}

			$decoded = json_decode(json: $raw, associative: true);
			if (is_array($decoded) === false) {
				return null;
			}

			return $decoded;
		} finally {
			$zip->close();
		}
	}//end readManifest()

	/**
	 * Load the dashboard payload from a showcase ZIP archive.
	 *
	 * Each showcase ZIP MUST contain exactly one `dashboards/{uuid}.json`
	 * entry (the showcase IS the dashboard).
	 *
	 * @param string $zipPath The ZIP path.
	 *
	 * @return array<string, mixed> The decoded dashboard payload.
	 *
	 * @throws RuntimeException When the dashboard payload is missing or
	 *                          malformed.
	 */
	private function loadDashboardPayload(string $zipPath): array {
		$zip = new ZipArchive();
		if ($zip->open(filename: $zipPath, flags: ZipArchive::RDONLY) !== true) {
			throw new RuntimeException(
				message: 'Could not open showcase archive: ' . $zipPath
			);
		}

		try {
			$payload = $this->firstDashboardEntry(zip: $zip);
		} finally {
			$zip->close();
		}

		if ($payload !== null) {
			return $payload;
		}

		throw new RuntimeException(
			message: 'Showcase archive contained no dashboard JSON entry.'
		);
	}//end loadDashboardPayload()

	/**
	 * The first `dashboards/*.json` entry of an open archive that decodes
	 * to a JSON object or list.
	 *
	 * @param ZipArchive $zip The open archive.
	 *
	 * @return array<string, mixed>|null The payload, or null when none decodes.
	 */
	private function firstDashboardEntry(ZipArchive $zip): ?array {
		for ($i = 0; $i < $zip->numFiles; $i++) {
			if ($this->isDashboardEntryName(name: (string)$zip->getNameIndex(index: $i)) === false) {
				continue;
			}

			$decoded = $this->decodeZipEntry(zip: $zip, index: $i);
			if ($decoded !== null) {
				return $decoded;
			}
		}

		return null;
	}//end firstDashboardEntry()

	/**
	 * Whether an archive entry name is a dashboard payload
	 * (`dashboards/*.json`).
	 *
	 * @param string $name The entry name.
	 *
	 * @return bool True for a dashboard payload entry.
	 */
	private function isDashboardEntryName(string $name): bool {
		return str_starts_with(haystack: $name, needle: 'dashboards/') === true
			&& str_ends_with(haystack: $name, needle: '.json') === true;
	}//end isDashboardEntryName()

	/**
	 * Decode one archive entry, null when it is unreadable or not a JSON
	 * object or list.
	 *
	 * @param ZipArchive $zip   The open archive.
	 * @param integer    $index The entry index.
	 *
	 * @return array<string, mixed>|null The decoded entry, or null.
	 */
	private function decodeZipEntry(ZipArchive $zip, int $index): ?array {
		$raw = $zip->getFromIndex(index: $index);
		if ($raw === false) {
			return null;
		}

		$decoded = json_decode(json: $raw, associative: true);
		if (is_array($decoded) === false) {
			return null;
		}

		return $decoded;
	}//end decodeZipEntry()

	/**
	 * Hydrate a Dashboard entity for a freshly-installed showcase.
	 *
	 * Always creates a `group_shared` dashboard with the `default`
	 * group sentinel so every user sees it (REQ-DASH-012, REQ-DEMO-003).
	 * The dashboard is published immediately — showcases skip the
	 * draft state.
	 *
	 * @param string $showcaseId The showcase ID (for
	 *                           slug + audit trail).
	 * @param array<string, mixed> $payload The decoded payload.
	 * @param string $sourceLanguage The requested locale.
	 *
	 * @return Dashboard The new entity (not yet persisted).
	 */
	private function buildDashboardEntity(
		string $showcaseId,
		array $payload,
		string $sourceLanguage,
	): Dashboard {
		$dashboard = new Dashboard();
		$now = (new DateTime())->format(format: 'Y-m-d H:i:s');

		$name = (string)($payload['name'] ?? $showcaseId);
		$description = null;
		// `isset()` is already false for a null value, so a trailing `!== null`
		// can never be false. PHPStan 2 reports it as notIdentical.alwaysTrue.
		// The same redundancy was removed from the tile block below.
		if (isset($payload['description']) === true) {
			$description = (string)$payload['description'];
		}

		$iconValue = (string)($payload['icon'] ?? '');
		$icon = null;
		if ($iconValue !== '') {
			$icon = $iconValue;
		}

		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$dashboard->setUuid(UuidV4::generate());
		$dashboard->setName($name);
		$dashboard->setDescription($description);
		$dashboard->setIcon($icon);
		$dashboard->setType(Dashboard::TYPE_GROUP_SHARED);
		$dashboard->setUserId(null);
		$dashboard->setGroupId(Dashboard::DEFAULT_GROUP_ID);
		$dashboard->setGridColumns((int)($payload['gridColumns'] ?? 12));
		$dashboard->setPermissionLevel(Dashboard::PERMISSION_VIEW_ONLY);
		$dashboard->setIsDefault(0);
		$dashboard->setIsActive(0);
		$dashboard->setSlug('showcase-' . $showcaseId);
		$dashboard->setSortOrder(0);
		$dashboard->setPublicationStatus(Dashboard::STATUS_PUBLISHED);
		$dashboard->setCreatedAt($now);
		$dashboard->setUpdatedAt($now);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters

		// Surface the showcase + locale in the description tail when the
		// payload omits one — keeps every install discoverable in
		// post-install audits even though the entity has no dedicated
		// metadata column. The descriptive suffix uses an HTML comment
		// so it survives admin UI edits without rendering noise.
		if ($description === null) {
			// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$dashboard->setDescription(
				'LaunchPad demo showcase (' . $showcaseId . ', ' . $sourceLanguage . ')'
			);
		}

		return $dashboard;
	}//end buildDashboardEntity()

	/**
	 * Persist the per-showcase install marker.
	 *
	 * @param string $showcaseId The showcase ID.
	 * @param string $dashboardUuid The installed dashboard UUID.
	 *
	 * @return void
	 */
	private function markInstalled(string $showcaseId, string $dashboardUuid): void {
		$this->platform->appConfig->setValueString(
			Application::APP_ID,
			self::CONFIG_PREFIX . $showcaseId,
			$dashboardUuid
		);
	}//end markInstalled()

	/**
	 * Clear the per-showcase install marker.
	 *
	 * @param string $showcaseId The showcase ID.
	 *
	 * @return void
	 */
	private function clearInstalledMarker(string $showcaseId): void {
		$this->platform->appConfig->deleteKey(
			Application::APP_ID,
			self::CONFIG_PREFIX . $showcaseId
		);
	}//end clearInstalledMarker()

	/**
	 * Reject unknown showcase IDs with a typed exception.
	 *
	 * @param string $showcaseId The candidate ID.
	 *
	 * @return void
	 *
	 * @throws ShowcaseNotFoundException When the ID is not bundled.
	 */
	private function assertKnownShowcase(string $showcaseId): void {
		if (in_array(needle: $showcaseId, haystack: self::BUNDLED_IDS, strict: true) === false) {
			throw new ShowcaseNotFoundException(
				message: 'Unknown showcase ID: ' . $showcaseId
			);
		}
	}//end assertKnownShowcase()
}//end class
