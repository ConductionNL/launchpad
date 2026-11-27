<?php

/**
 * DashboardVersionService
 *
 * Service backing the `dashboard-versioning` capability
 * (REQ-VERS-001..009). Owns:
 *   - automatic snapshot capture on dashboard PUT (debounced 60 s);
 *   - explicit snapshot creation with optional note;
 *   - version listing, fetching, and restore;
 *   - per-dashboard retention enforcement (50 row limit);
 *   - permission gating via {@see PermissionService::canEditDashboardMetadata}
 *     and the owner-or-admin guard.
 *
 * The service is backend-agnostic at the API boundary
 * (REQ-VERS-008) but only the database backend is exercised here. The
 * groupfolder backend that delegates to `\OCP\IVersionManager` lives in
 * a sibling spec that is currently DEFERRED — until that backend lands,
 * every dashboard is treated as database-backed and the service falls
 * back to local snapshots. The contentBackend dispatch hook is wired
 * via {@see self::isGroupfolderBacked()} so wiring the strategy later
 * is a one-line change.
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
use Exception;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardVersion;
use OCA\LaunchPad\Db\DashboardVersionMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Service\Versions\SnapshotChangeDescriber;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Service for dashboard version snapshots, restore, and retention.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Strategy entry point
 *  for both DB and (future) groupfolder backends naturally pulls in the
 *  three mappers + cache + logger + group manager.
 * @spec                                           openspec/specs/dashboard-versioning/spec.md
 */
class DashboardVersionService {

	/**
	 * Default per-dashboard retention limit (REQ-VERS-006).
	 *
	 * @var integer
	 */
	public const RETENTION_LIMIT = DashboardVersionMapper::DEFAULT_RETENTION;

	/**
	 * Snapshot fields whose column holds JSON TEXT rather than a scalar.
	 *
	 * Their array setters do the encoding. Handing the decoded array straight to
	 * the plain setter would store the literal string "Array".
	 *
	 * @var array<string, string> Snapshot field => setter name.
	 */
	private const BLOB_SETTERS = [
		'styleConfig' => 'setStyleConfigArray',
		'content' => 'setContentArray',
		'calculatedFields' => 'setCalculatedFieldsArray',
	];

	/**
	 * Debounce window for automatic snapshots, in seconds (REQ-VERS-001).
	 *
	 * @var integer
	 */
	public const DEBOUNCE_SECONDS = 60;

	/**
	 * How long the state at the start of an edit session is kept, in seconds.
	 * A session whose leave never arrives (the browser closed) stores no version.
	 *
	 * @var int
	 */
	public const EDIT_SESSION_SECONDS = 43200;

	/**
	 * Cache namespace used for the per-dashboard debounce key.
	 *
	 * @var string
	 */
	public const CACHE_NAMESPACE = 'launchpad_versioning';

	/**
	 * Sentinel error message returned by the permission guard.
	 *
	 * @var string
	 */
	public const ERR_FORBIDDEN_NOT_OWNER_OR_ADMIN = 'Forbidden: owner or admin only';

	/**
	 * Sentinel error message returned when GroupFolder versioning is
	 * unavailable for a non-DB-backed dashboard. Callers MUST catch and
	 * convert to the soft-fail response described in REQ-VERS-009.
	 *
	 * @var string
	 */
	public const ERR_VERSIONING_UNAVAILABLE = 'Versioning backend unavailable';

	/**
	 * The ICache used for per-dashboard debounce. Lazily resolved via
	 * the cache factory; the factory may legally return a no-op cache
	 * (NullCache) on dev installs without APCu/Redis — in that case the
	 * debounce is skipped, mirroring the design D2 graceful fallback.
	 *
	 * @var ICache|null
	 */
	private ?ICache $cache = null;

	/**
	 * Constructor
	 *
	 * @param VersionStores   $stores The version, dashboard and placement mappers and the database.
	 * @param IGroupManager   $groupManager Group manager — used exclusively for the owner-or-admin permission
	 *                                      guard (no group-membership lookups).
	 * @param ICacheFactory   $cacheFactory NC cache factory supplying the debounce backing store.
	 * @param LoggerInterface $logger PSR logger.
	 * @param SnapshotChangeDescriber $changeDescriber Writes the "What changed" note of automatic snapshots.
	 */
	public function __construct(
		private readonly VersionStores $stores,
		private readonly IGroupManager $groupManager,
		private readonly ICacheFactory $cacheFactory,
		private readonly LoggerInterface $logger,
		private readonly SnapshotChangeDescriber $changeDescriber=new SnapshotChangeDescriber(),
	) {
	}//end __construct()

	/**
	 * Capture a snapshot of a dashboard's content (REQ-VERS-001 / -002).
	 *
	 * Behaviour:
	 *  - Automatic snapshots (`$explicit = false`) are debounced — at
	 *    most one per `DEBOUNCE_SECONDS` per dashboard. Suppressed
	 *    snapshots return `null` without writing to the table.
	 *  - Explicit snapshots (`$explicit = true`) ALWAYS persist and
	 *    bypass the debounce.
	 *  - The next versionNumber is allocated as `max(existing) + 1`,
	 *    starting at 1 for the first snapshot. Pruning never renumbers
	 *    survivors (REQ-VERS-006).
	 *  - After a successful insert the retention limit is enforced
	 *    inline so the table stays bounded.
	 *
	 * Snapshot payload composition is deliberately the caller's
	 * responsibility — pass the JSON-encoded content directly. When
	 * `$snapshotJson` is empty the service derives a default payload
	 * from the dashboard + its widget placements via
	 * {@see self::buildDefaultSnapshot()} so the API stays usable from
	 * controllers that don't yet pass an explicit body.
	 *
	 * @param Dashboard $dashboard The dashboard being snapshotted.
	 * @param string    $createdBy The acting user ID.
	 * @param array     $options   Optional `snapshotJson` (pre-built
	 *                             snapshot JSON; null or empty to
	 *                             auto-build from the live state), `note`
	 *                             (kept on explicit snapshots only; an
	 *                             automatic snapshot notes what changed
	 *                             since the previous version) and
	 *                             `explicit` (bypass the debounce; false).
	 *
	 * @return DashboardVersion|null The persisted version row, or null
	 *                               when an automatic snapshot was
	 *                               suppressed by the debounce window.
	 *
	 * @spec openspec/specs/dashboard-versioning/spec.md
	 * @spec openspec/changes/archive/2026-11-24-version-change-notes/specs/dashboard-versioning/spec.md#requirement-each-automatic-version-says-what-changed-req-versui-004
	 */
	public function captureSnapshot(Dashboard $dashboard, string $createdBy, array $options=[]): ?DashboardVersion {
		$options += ['snapshotJson' => null, 'note' => null, 'explicit' => false];
		$uuid     = (string)$dashboard->getUuid();
		if ($uuid === '') {
			// Defensive — without a UUID we have nothing to key the
			// snapshot to. Refuse silently rather than throw because
			// legacy rows may not yet have a UUID.
			return null;
		}

		if ($options['explicit'] === false && $this->isDebounced(uuid: $uuid) === true) {
			return null;
		}

		$entity = $this->newVersion(dashboard: $dashboard, options: $options);
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$entity->setCreatedBy($createdBy);

		return $this->insertVersion(entity: $entity);
	}//end captureSnapshot()

	/**
	 * The stored dashboard and its snapshot payload, built before an update so
	 * the automatic snapshot holds the state the update replaces, placements
	 * included (REQ-VERS-001). Pass both to captureSnapshot() once the update
	 * succeeded.
	 *
	 * @param int $dashboardId The dashboard about to be updated.
	 *
	 * @return array{dashboard: Dashboard, snapshotJson: string}|null The state, or null when the dashboard does not exist.
	 *
	 * @spec openspec/specs/dashboard-versioning/spec.md
	 */
	public function stateBeforeUpdate(int $dashboardId): ?array {
		try {
			$dashboard = $this->stores->dashboardMapper->find(id: $dashboardId);
		} catch (DoesNotExistException) {
			return null;
		}

		return ['dashboard' => $dashboard, 'snapshotJson' => $this->buildDefaultSnapshot(dashboard: $dashboard)];
	}//end stateBeforeUpdate()

	/**
	 * Start an edit session: keep the dashboard as it is when this person
	 * enters edit mode, so leaving it can store that state as a version
	 * (REQ-VERS-010). A renewal while the session runs keeps the first state.
	 * Never throws: a missing version must not keep anyone out of edit mode.
	 *
	 * @param string $dashboardUuid The dashboard entering edit mode.
	 * @param string $userId        The editor.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-27-versions-on-widget-edits/tasks.md#task-1
	 */
	public function beginEditSession(string $dashboardUuid, string $userId): void {
		$cache = $this->getCache();
		if ($cache === null || $dashboardUuid === '' || $userId === '') {
			return;
		}

		try {
			$key = $this->editSessionKey(dashboardUuid: $dashboardUuid, userId: $userId);
			if ($cache->hasKey(key: $key) === true) {
				return;
			}

			$dashboard = $this->stores->dashboardMapper->findByUuid(uuid: $dashboardUuid);
			$cache->set(
				key: $key,
				value: $this->buildDefaultSnapshot(dashboard: $dashboard),
				ttl: self::EDIT_SESSION_SECONDS
			);
		} catch (Throwable $t) {
			$this->logger->warning(
				message: 'launchpad: keeping the state at the start of editing failed',
				context: ['exception' => $t, 'dashboardUuid' => $dashboardUuid]
			);
		}//end try
	}//end beginEditSession()

	/**
	 * End an edit session: when the dashboard changed since this person
	 * entered edit mode, store the state from then as an automatic version
	 * (REQ-VERS-010), outside the 60-second debounce, so restoring it undoes
	 * the session. Nothing is stored when nothing changed, or when the newest
	 * version already holds that state (a settings save stored it). Never
	 * throws.
	 *
	 * @param string $dashboardUuid The dashboard leaving edit mode.
	 * @param string $userId        The editor.
	 *
	 * @return DashboardVersion|null The version, or null when none was stored.
	 *
	 * @spec openspec/changes/archive/2026-11-27-versions-on-widget-edits/tasks.md#task-1
	 */
	public function endEditSession(string $dashboardUuid, string $userId): ?DashboardVersion {
		$cache = $this->getCache();
		if ($cache === null || $dashboardUuid === '' || $userId === '') {
			return null;
		}

		try {
			$key    = $this->editSessionKey(dashboardUuid: $dashboardUuid, userId: $userId);
			$before = $cache->get(key: $key);
			$cache->remove(key: $key);
			if (is_string($before) === false || $before === '') {
				return null;
			}

			$dashboard = $this->stores->dashboardMapper->findByUuid(uuid: $dashboardUuid);
			$current   = $this->buildDefaultSnapshot(dashboard: $dashboard);
			if ($this->sameContent(firstJson: $before, secondJson: $current) === true
				|| $this->newestVersionHolds(dashboardUuid: $dashboardUuid, snapshotJson: $before) === true
			) {
				return null;
			}

			$entity = $this->newVersion(
				dashboard: $dashboard,
				options: ['snapshotJson' => $before, 'note' => null, 'explicit' => false]
			);
			// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$entity->setCreatedBy($userId);

			return $this->insertVersion(entity: $entity);
		} catch (DoesNotExistException) {
			return null;
		} catch (Throwable $t) {
			$this->logger->warning(
				message: 'launchpad: storing the version of an edit session failed',
				context: ['exception' => $t, 'dashboardUuid' => $dashboardUuid]
			);
			return null;
		}//end try
	}//end endEditSession()

	/**
	 * Whether the newest stored version of a dashboard holds this content.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $snapshotJson  The content.
	 *
	 * @return bool True when it does.
	 *
	 * @spec openspec/changes/archive/2026-11-27-versions-on-widget-edits/tasks.md#task-1
	 */
	private function newestVersionHolds(string $dashboardUuid, string $snapshotJson): bool {
		$latest = $this->stores->versionMapper->findLatestByDashboard(dashboardUuid: $dashboardUuid, limit: 1);
		if ($latest === []) {
			return false;
		}

		return $this->sameContent(firstJson: (string)$latest[0]->getSnapshotJson(), secondJson: $snapshotJson);
	}//end newestVersionHolds()

	/**
	 * Whether two snapshots hold the same dashboard and widgets, ignoring
	 * row ids and timestamps.
	 *
	 * @param string $firstJson  One snapshot.
	 * @param string $secondJson The other.
	 *
	 * @return bool True when the content is the same.
	 *
	 * @spec openspec/changes/archive/2026-11-27-versions-on-widget-edits/tasks.md#task-1
	 */
	private function sameContent(string $firstJson, string $secondJson): bool {
		$first  = json_decode($firstJson, true);
		$second = json_decode($secondJson, true);

		return $this->comparablePlacements(payload: $first) === $this->comparablePlacements(payload: $second)
			&& $this->comparableDashboard(payload: $first) === $this->comparableDashboard(payload: $second);
	}//end sameContent()

	/**
	 * A snapshot's dashboard fields without the ones every save moves.
	 *
	 * @param mixed $payload The decoded snapshot.
	 *
	 * @return string The fields, encoded with sorted keys.
	 *
	 * @spec openspec/changes/archive/2026-11-27-versions-on-widget-edits/tasks.md#task-1
	 */
	private function comparableDashboard(mixed $payload): string {
		$dashboard = [];
		if (is_array($payload) === true && is_array($payload['dashboard'] ?? null) === true) {
			$dashboard = $payload['dashboard'];
		}

		unset($dashboard['updatedAt'], $dashboard['createdAt']);
		ksort($dashboard);
		return (string)json_encode($dashboard);
	}//end comparableDashboard()

	/**
	 * The cache key of one editor's session on one dashboard.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $userId        The editor.
	 *
	 * @return string The key.
	 */
	private function editSessionKey(string $dashboardUuid, string $userId): string {
		return 'launchpad_ver_edit_'.md5($dashboardUuid.'/'.$userId);
	}//end editSessionKey()

	/**
	 * The next version row of a dashboard, not yet persisted and without
	 * its author.
	 *
	 * @param Dashboard $dashboard The dashboard being snapshotted (it has a UUID).
	 * @param array     $options   `snapshotJson`, `note` and `explicit` as
	 *                             {@see self::captureSnapshot()} takes them.
	 *
	 * @return DashboardVersion The unsaved row.
	 *
	 * @spec openspec/changes/archive/2026-11-24-version-change-notes/specs/dashboard-versioning/spec.md#requirement-each-automatic-version-says-what-changed-req-versui-004
	 */
	private function newVersion(Dashboard $dashboard, array $options): DashboardVersion {
		$uuid         = (string)$dashboard->getUuid();
		$snapshotJson = $options['snapshotJson'];
		$note         = $options['note'];
		// Auto-build the payload when the caller hands us nothing —
		// this keeps the integration with `DashboardService` simple.
		if ($snapshotJson === null || $snapshotJson === '') {
			$snapshotJson = $this->buildDefaultSnapshot(dashboard: $dashboard);
		}

		$coercedNote = null;
		if ($options['explicit'] === true && $note !== null && $note !== '') {
			$coercedNote = $note;
		}

		if ($options['explicit'] === false) {
			$coercedNote = $this->describeChange(dashboardUuid: $uuid, snapshotJson: $snapshotJson);
		}

		$next = ($this->stores->versionMapper->findMaxVersionNumber(
			dashboardUuid: $uuid
		) + 1);

		$entity = new DashboardVersion();
		// Entity setters resolve via __call which uses $args[0]; named
		// args would break the magic forwarding (see project memory).
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$entity->setDashboardUuid($uuid);
		$entity->setVersionNumber($next);
		$entity->setSnapshotJson($snapshotJson);
		$entity->setCreatedAt(
			(new DateTime())->format(format: 'Y-m-d H:i:s')
		);
		$entity->setNote($coercedNote);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters

		return $entity;
	}//end newVersion()

	/**
	 * What changed between a dashboard's newest stored version and a new
	 * snapshot, for the note of an automatic snapshot.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $snapshotJson  The new snapshot.
	 *
	 * @return string|null The description, or null when there is no previous
	 *                     version, nothing differs or the lookup fails.
	 *
	 * @spec openspec/changes/archive/2026-11-24-version-change-notes/specs/dashboard-versioning/spec.md#requirement-each-automatic-version-says-what-changed-req-versui-004
	 */
	private function describeChange(string $dashboardUuid, string $snapshotJson): ?string {
		try {
			$latest = $this->stores->versionMapper->findLatestByDashboard(dashboardUuid: $dashboardUuid, limit: 1);
			if ($latest === []) {
				return null;
			}

			return $this->changeDescriber->describe(
				previousJson: (string)$latest[0]->getSnapshotJson(),
				currentJson: $snapshotJson
			);
		} catch (Throwable $t) {
			$this->logger->debug(
				message: 'launchpad: failed to describe the snapshot change',
				context: ['exception' => $t]
			);
			return null;
		}
	}//end describeChange()

	/**
	 * Persist a version row, prune past the retention limit and refresh
	 * the debounce key.
	 *
	 * @param DashboardVersion $entity The row to insert.
	 *
	 * @return DashboardVersion The persisted row.
	 */
	private function insertVersion(DashboardVersion $entity): DashboardVersion {
		$uuid      = (string)$entity->getDashboardUuid();
		$persisted = $this->stores->versionMapper->insert(entity: $entity);

		// REQ-VERS-006: prune inline so the table never exceeds the
		// retention limit by more than the single in-flight insert.
		$this->stores->versionMapper->pruneOldVersions(
			dashboardUuid: $uuid,
			keepCount: self::RETENTION_LIMIT
		);

		// Refresh the debounce key on EVERY successful insert (explicit
		// or automatic) so a flurry of explicit POSTs followed by an
		// automatic PUT still yields at most one auto snapshot per
		// window.
		$this->markDebounced(uuid: $uuid);

		return $persisted;
	}//end insertVersion()

	/**
	 * List the versions available for a dashboard, newest-first
	 * (REQ-VERS-003).
	 *
	 * Returns metadata only; callers MUST hit
	 * {@see self::fetchSnapshot()} for the body. The response shape
	 * mirrors the soft-fail contract in REQ-VERS-009 — `modeSupported`
	 * is true for database-backed dashboards (always available), false
	 * when a groupfolder-backed dashboard's versioning store is missing.
	 *
	 * @param Dashboard $dashboard The dashboard to list versions for.
	 * @param string $requestingUser The acting user ID.
	 *
	 * @return array{versions: array<int, array<string, mixed>>, modeSupported: bool}
	 *
	 * @throws Exception When the actor is neither owner nor admin.
	 *
	 * @spec openspec/specs/dashboard-versioning/spec.md
	 */
	public function listVersions(
		Dashboard $dashboard,
		string $requestingUser,
	): array {
		$this->assertOwnerOrAdmin(
			dashboard: $dashboard,
			actorUserId: $requestingUser
		);

		if ($this->isGroupfolderBacked(dashboard: $dashboard) === true) {
			// REQ-VERS-009: groupfolder backend not yet wired; return
			// the soft-fail envelope so the UI can hide the version
			// list cleanly.
			return [
				'versions' => [],
				'modeSupported' => false,
			];
		}

		$rows = $this->stores->versionMapper->findLatestByDashboard(
			dashboardUuid: (string)$dashboard->getUuid(),
			limit: self::RETENTION_LIMIT
		);
		$versions = [];
		foreach ($rows as $row) {
			$versions[] = $row->jsonSerialize();
		}

		return [
			'versions' => $versions,
			'modeSupported' => true,
		];
	}//end listVersions()

	/**
	 * Fetch the full snapshot body for a single version (REQ-VERS-004).
	 *
	 * @param Dashboard $dashboard The dashboard.
	 * @param integer $versionNumber The version number to retrieve.
	 * @param string $requestingUser The acting user ID.
	 *
	 * @return DashboardVersion The version row including snapshot body.
	 *
	 * @throws Exception When the actor is neither owner nor admin.
	 * @throws DoesNotExistException When the version does not exist.
	 *
	 * @spec openspec/specs/dashboard-versioning/spec.md
	 */
	public function fetchSnapshot(
		Dashboard $dashboard,
		int $versionNumber,
		string $requestingUser,
	): DashboardVersion {
		$this->assertOwnerOrAdmin(
			dashboard: $dashboard,
			actorUserId: $requestingUser
		);

		if ($this->isGroupfolderBacked(dashboard: $dashboard) === true) {
			throw new DoesNotExistException(
				msg: 'Version not found'
			);
		}

		return $this->stores->versionMapper->findByDashboardAndVersion(
			dashboardUuid: (string)$dashboard->getUuid(),
			versionNumber: $versionNumber
		);
	}//end fetchSnapshot()

	/**
	 * Create an explicit snapshot via the POST /versions endpoint
	 * (REQ-VERS-002). Bypasses the debounce window.
	 *
	 * @param Dashboard $dashboard The dashboard to snapshot.
	 * @param string $requestingUser The acting user ID.
	 * @param string|null $note Optional note; empty becomes NULL.
	 *
	 * @return DashboardVersion The newly persisted version row.
	 *
	 * @throws Exception When the actor is neither owner nor admin or
	 *                   the groupfolder backend is selected (currently
	 *                   unsupported).
	 *
	 * @spec openspec/specs/dashboard-versioning/spec.md
	 */
	public function createExplicitSnapshot(
		Dashboard $dashboard,
		string $requestingUser,
		?string $note = null,
	): DashboardVersion {
		$this->assertOwnerOrAdmin(
			dashboard: $dashboard,
			actorUserId: $requestingUser
		);

		if ($this->isGroupfolderBacked(dashboard: $dashboard) === true) {
			throw new Exception(
				message: self::ERR_VERSIONING_UNAVAILABLE
			);
		}

		$persisted = $this->captureSnapshot(
			dashboard: $dashboard,
			createdBy: $requestingUser,
			options: ['note' => $note, 'explicit' => true]
		);

		if ($persisted === null) {
			// Should not happen — explicit captures are guaranteed to
			// persist. Defensive programming for future strategy
			// implementations that may opt to throw.
			throw new Exception(
				message: self::ERR_VERSIONING_UNAVAILABLE
			);
		}

		return $persisted;
	}//end createExplicitSnapshot()

	/**
	 * Restore a dashboard's content from a historical snapshot
	 * (REQ-VERS-005). The current state is captured as a NEW snapshot
	 * BEFORE the restore overwrites the body so the operation is
	 * itself reversible.
	 *
	 * Restoring the latest version while it holds the dashboard's current
	 * placements is a no-op: no new snapshot, the dashboard untouched
	 * ("restore to current is a no-op" in REQ-VERS-005). A latest version
	 * that differs, such as a pre-restore snapshot, is restored normally
	 * ("restore is reversible").
	 *
	 * @param Dashboard $dashboard The dashboard.
	 * @param integer $versionNumber The target version number.
	 * @param string $restoringUser The acting user ID.
	 *
	 * @return array{snapshot: string, version: DashboardVersion}
	 *                                                            The restored snapshot body + the row that was applied.
	 *
	 * @throws Exception When the actor is neither owner nor admin.
	 * @throws DoesNotExistException When the version does not exist.
	 *
	 * @spec openspec/specs/dashboard-versioning/spec.md
	 */
	public function restoreVersion(
		Dashboard $dashboard,
		int $versionNumber,
		string $restoringUser,
	): array {
		$this->assertOwnerOrAdmin(
			dashboard: $dashboard,
			actorUserId: $restoringUser
		);

		$this->assertVersioningAvailable(dashboard: $dashboard);

		$uuid = (string)$dashboard->getUuid();

		$target = $this->stores->versionMapper->findByDashboardAndVersion(
			dashboardUuid: $uuid,
			versionNumber: $versionNumber
		);

		$latest = $this->stores->versionMapper->findMaxVersionNumber(
			dashboardUuid: $uuid
		);

		if ($latest === $versionNumber && $this->holdsCurrentPlacements(dashboard: $dashboard, target: $target) === true) {
			// REQ-VERS-005: restoring to the current version is a no-op.
			return [
				'snapshot' => (string)$target->getSnapshotJson(),
				'version' => $target,
			];
		}

		$snapshotJson = $this->inRestoreTransaction(
			restore: function () use ($dashboard, $target, $restoringUser): string {
				// Capture pre-restore state as a new snapshot so the restore
				// itself is reversible (REQ-VERS-005 design D3).
				$preRestore = ['snapshotJson' => $this->buildDefaultSnapshot(dashboard: $dashboard), 'note' => 'pre-restore', 'explicit' => true];
				$this->captureSnapshot(dashboard: $dashboard, createdBy: $restoringUser, options: $preRestore);

				return $this->applyVersion(dashboard: $dashboard, target: $target);
			}
		);

		return [
			'snapshot' => $snapshotJson,
			'version' => $target,
		];
	}//end restoreVersion()

	/**
	 * Whether a version holds the same placements as the dashboard now,
	 * ignoring the row ids and timestamps a restore does not keep.
	 *
	 * @param Dashboard        $dashboard The dashboard.
	 * @param DashboardVersion $target    The version to compare.
	 *
	 * @return boolean True when restoring the version would change no placement.
	 */
	private function holdsCurrentPlacements(Dashboard $dashboard, DashboardVersion $target): bool {
		$current = json_decode($this->buildDefaultSnapshot(dashboard: $dashboard), associative: true);
		$stored  = json_decode((string)$target->getSnapshotJson(), associative: true);

		return $this->comparablePlacements(payload: $current) === $this->comparablePlacements(payload: $stored);
	}//end holdsCurrentPlacements()

	/**
	 * The placements of a decoded snapshot as sorted JSON strings without
	 * id, dashboardId, createdAt and updatedAt.
	 *
	 * @param mixed $payload The decoded snapshot.
	 *
	 * @return string[] One string per placement, sorted.
	 */
	private function comparablePlacements(mixed $payload): array {
		$placements = [];
		if (is_array($payload) === true && is_array($payload['placements'] ?? null) === true) {
			$placements = $payload['placements'];
		}

		$comparable = [];
		foreach ($placements as $placement) {
			if (is_array($placement) === false) {
				continue;
			}

			unset($placement['id'], $placement['dashboardId'], $placement['createdAt'], $placement['updatedAt']);
			ksort($placement);
			$comparable[] = (string)json_encode($placement);
		}

		sort($comparable);
		return $comparable;
	}//end comparablePlacements()

	/**
	 * Run a restore inside one DB transaction, rolled back and logged on failure.
	 *
	 * WF1 fix: wrap the wipe-and-reinsert in a DB transaction so a
	 * mid-loop insert failure never leaves the dashboard with zero or
	 * partial placements. When no db connection is wired (test doubles)
	 * the operations run without an explicit transaction envelope,
	 * preserving backwards-compat.
	 *
	 * @param \Closure $restore Runs the restore and returns the applied snapshot JSON.
	 *
	 * @return string The applied snapshot JSON.
	 *
	 * @throws Throwable The restore's failure, after the rollback.
	 */
	private function inRestoreTransaction(\Closure $restore): string {
		$this->stores->db?->beginTransaction();

		try {
			$snapshotJson = $restore();

			$this->stores->db?->commit();
		} catch (Throwable $t) {
			$this->stores->db?->rollBack();

			$this->logger->error(
				message: 'launchpad: restoreVersion failed, transaction rolled back',
				context: ['exception' => $t]
			);
			throw $t;
		}//end try

		return $snapshotJson;
	}//end inRestoreTransaction()

	/**
	 * Refuse versioning for a dashboard backed by a group folder.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 *
	 * @return void
	 *
	 * @throws Exception With {@see self::ERR_VERSIONING_UNAVAILABLE}.
	 */
	private function assertVersioningAvailable(Dashboard $dashboard): void {
		if ($this->isGroupfolderBacked(dashboard: $dashboard) === true) {
			throw new Exception(
				message: self::ERR_VERSIONING_UNAVAILABLE
			);
		}
	}//end assertVersioningAvailable()

	/**
	 * Apply a stored version to the dashboard and stamp its modification time.
	 *
	 * C3 fix: the target snapshot is decoded and applied to the DB so the
	 * restore is a real state change, not a no-op. The dashboard's
	 * updatedAt is stamped so callers that listen on metadata see the
	 * restore (REQ-VERS-005 scenario "restore updates the modified timestamp").
	 *
	 * @param Dashboard        $dashboard The dashboard to restore.
	 * @param DashboardVersion $target    The version to restore to.
	 *
	 * @return string The applied snapshot JSON.
	 */
	private function applyVersion(Dashboard $dashboard, DashboardVersion $target): string {
		$snapshotJson = (string)$target->getSnapshotJson();
		$payload = json_decode($snapshotJson, associative: true);
		if (is_array($payload) === true) {
			$this->applySnapshotPayload(
				dashboard: $dashboard,
				payload: $payload
			);
		}

		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$dashboard->setUpdatedAt(
			(new DateTime())->format(format: 'Y-m-d H:i:s')
		);
		$this->stores->dashboardMapper->update(entity: $dashboard);

		return $snapshotJson;
	}//end applyVersion()

	/*
	 * NO CASCADE-DELETE WRAPPER HERE.
	 *
	 * This service used to carry `deleteVersionsForDashboard()`, a literal
	 * pass-through to `DashboardVersionMapper::deleteByDashboardUuid()`. It
	 * never had a caller: the live cascade path is `VersionsListener`, which
	 * is registered against `DashboardDeletedEvent` in `Application.php`
	 * (REQ-CSC-002) and calls the mapper directly, inside the
	 * log-and-continue envelope REQ-CSC-006 requires. Routing that listener
	 * through a second wrapper would add a hop and nothing else.
	 */

	/**
	 * Whether the supplied dashboard is groupfolder-backed
	 * (REQ-VERS-008). Currently always false because the groupfolder
	 * storage backend is DEFERRED to a sibling spec; the hook exists
	 * so wiring the strategy later is a one-line change.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 *
	 * @return boolean Whether the dashboard's content lives in a groupfolder.
	 *
	 * @spec openspec/specs/dashboard-versioning/spec.md
	 */
	public function isGroupfolderBacked(Dashboard $dashboard): bool {
		// The groupfolder-storage-backend change introduces a
		// `contentBackend` field; until it lands we look up the value
		// defensively via reflection-free magic getters and treat the
		// unknown / 'database' value as DB-backed.
		if (method_exists($dashboard, 'getContentBackend') === false) {
			return false;
		}

		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$value = $dashboard->getContentBackend();
		return $value === 'groupfolder';
	}//end isGroupfolderBacked()

	/**
	 * Whether the per-dashboard debounce window is currently active.
	 *
	 * Soft-fails to "not debounced" when the cache factory hands us a
	 * no-op cache (legal on dev installs without APCu / Redis) so the
	 * snapshot is still captured.
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return boolean Whether automatic snapshot capture should be skipped.
	 */
	private function isDebounced(string $uuid): bool {
		$cache = $this->getCache();
		if ($cache === null) {
			return false;
		}

		try {
			return $cache->hasKey(key: $this->debounceKey(uuid: $uuid));
		} catch (Throwable $t) {
			$this->logger->debug(
				message: 'launchpad: debounce check failed, allowing snapshot',
				context: ['exception' => $t]
			);
			return false;
		}
	}//end isDebounced()

	/**
	 * Mark a dashboard as having just been snapshotted.
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return void
	 */
	private function markDebounced(string $uuid): void {
		$cache = $this->getCache();
		if ($cache === null) {
			return;
		}

		try {
			$cache->set(
				key: $this->debounceKey(uuid: $uuid),
				value: 1,
				ttl: self::DEBOUNCE_SECONDS
			);
		} catch (Throwable $t) {
			$this->logger->debug(
				message: 'launchpad: failed to set debounce key',
				context: ['exception' => $t]
			);
		}
	}//end markDebounced()

	/**
	 * Build the per-dashboard cache key used for the debounce window.
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return string The cache key.
	 */
	private function debounceKey(string $uuid): string {
		return 'launchpad_ver_debounce_' . $uuid;
	}//end debounceKey()

	/**
	 * Lazily resolve and memoise the underlying ICache.
	 *
	 * @return ICache|null The cache, or null when none is available.
	 */
	private function getCache(): ?ICache {
		if ($this->cache !== null) {
			return $this->cache;
		}

		try {
			$this->cache = $this->cacheFactory->createDistributed(
				prefix: self::CACHE_NAMESPACE
			);
		} catch (Throwable $t) {
			$this->logger->debug(
				message: 'launchpad: cache factory unavailable',
				context: ['exception' => $t]
			);
			$this->cache = null;
		}

		return $this->cache;
	}//end getCache()

	/**
	 * Build a default snapshot payload from a dashboard's live state.
	 *
	 * Combines the dashboard's `jsonSerialize()` output with the full
	 * placements list so a future restore can rebuild the layout
	 * deterministically. Always JSON-encodes — failure to encode falls
	 * back to an empty object string so the row remains insertable.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 *
	 * @return string The encoded snapshot body.
	 */
	private function buildDefaultSnapshot(Dashboard $dashboard): string {
		$dashboardId = $dashboard->getId();

		$placements = [];
		if ($dashboardId !== null) {
			try {
				$rows = $this->stores->placementMapper->findByDashboardId(
					dashboardId: $dashboardId
				);
				foreach ($rows as $row) {
					$placements[] = $row->jsonSerialize();
				}
			} catch (Throwable $t) {
				$this->logger->debug(
					message: 'launchpad: failed to load placements for snapshot',
					context: ['exception' => $t]
				);
			}
		}

		$payload = [
			'dashboard' => $dashboard->jsonSerialize(),
			'placements' => $placements,
		];

		$encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
		if ($encoded === false) {
			return '{}';
		}

		return $encoded;
	}//end buildDefaultSnapshot()

	/**
	 * Apply a decoded snapshot payload to the dashboard's child tables.
	 *
	 * Replaces the existing widget placements with those stored in the
	 * snapshot. Runs as an all-or-nothing block: any failure is logged
	 * and re-thrown so the caller can surface a 500 rather than leaving
	 * the dashboard in a partial state.
	 *
	 * REQ-VERS-005 (C3 fix): makes restoreVersion() an actual state
	 * restore rather than a no-op that only returns the historical JSON.
	 *
	 * @param Dashboard $dashboard The target dashboard.
	 * @param array<string, mixed> $payload Decoded snapshot body.
	 *
	 * @return void
	 *
	 * @throws Throwable When a mapper operation fails.
	 *
	 * @spec openspec/specs/dashboard-versioning/spec.md
	 */
	private function applySnapshotPayload(Dashboard $dashboard, array $payload): void {
		$dashboardId = (int)$dashboard->getId();
		if ($dashboardId === 0) {
			return;
		}

		// Wipe current placements and re-insert the historical set.
		$rawPlacements = $payload['placements'] ?? [];
		if (is_array($rawPlacements) === false) {
			$rawPlacements = [];
		}

		try {
			$this->stores->placementMapper->deleteByDashboardId(
				dashboardId: $dashboardId
			);

			foreach ($rawPlacements as $placementData) {
				if (is_array($placementData) === false) {
					continue;
				}

				$entity = $this->hydratePlacement(
					placementData: $placementData,
					dashboardId: $dashboardId
				);
				if ($entity === null) {
					continue;
				}

				$this->stores->placementMapper->insert(entity: $entity);
			}//end foreach
		} catch (Throwable $t) {
			$this->logger->error(
				message: 'launchpad: applySnapshotPayload failed for dashboard ' . $dashboardId,
				context: ['exception' => $t]
			);
			throw $t;
		}//end try
	}//end applySnapshotPayload()

	/**
	 * Build one WidgetPlacement from a snapshot entry.
	 *
	 * Extracted from {@see applySnapshotPayload()} so that method stays within
	 * the complexity budget; the interesting logic is all here.
	 *
	 * @param array<string, mixed> $placementData One entry from the snapshot's
	 *                                            `placements` list.
	 * @param int $dashboardId The dashboard being restored.
	 *
	 * @return WidgetPlacement|null The hydrated entity, or null when the entry
	 *                              cannot be inserted and must be skipped.
	 */
	private function hydratePlacement(array $placementData, int $dashboardId): ?WidgetPlacement {
		// A snapshot placement with no widget id cannot be inserted — the
		// column is NOT NULL and has no default. Skip it loudly instead of
		// letting the DB turn it into a 500.
		$widgetId = ($placementData['widgetId'] ?? null);
		if (is_string($widgetId) === false || $widgetId === '') {
			$this->logger->warning(
				message: 'launchpad: skipping a snapshot placement with no widgetId',
				context: ['dashboardId' => $dashboardId]
			);
			return null;
		}

		$entity = new WidgetPlacement();
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$entity->setDashboardId($dashboardId);

		foreach ($placementData as $field => $value) {
			if ($field === 'id' || $field === 'dashboardId') {
				// `id`: do not re-use the old PK — let the DB assign one.
				// `dashboardId`: already set, and taking it from the snapshot
				// would let a snapshot captured elsewhere move placements onto
				// a different dashboard.
				continue;
			}

			// The two JSON-blob columns hold TEXT, while the snapshot carries
			// them as decoded objects. Their array setters do the encoding;
			// handing an array to setStyleConfig() would store "Array".
			if (isset(self::BLOB_SETTERS[$field]) === true) {
				if (is_array($value) === true) {
					$blobSetter = self::BLOB_SETTERS[$field];
					// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
					$entity->$blobSetter($value);
				}

				continue;
			}

			/*
			 * property_exists(), NOT method_exists().
			 *
			 * 31 of this entity's 33 setters are MAGIC — declared as `@method`
			 * and dispatched through Entity::__call — and method_exists() is
			 * false for those. So the previous `method_exists($entity, $setter)`
			 * guard skipped EVERY field, the placement was inserted with nothing
			 * but its dashboard id, and every other column fell back to its DB
			 * default. The row in the failure log is exactly that: grid 4x4,
			 * is_visible 1, show_title 1 — the defaults — with widget_id NULL
			 * because that column has no default.
			 *
			 * In other words restore never restored anything; it wiped the
			 * placements and then died on the first insert.
			 */

			if (property_exists($entity, (string)$field) === true) {
				$setter = 'set' . ucfirst((string)$field);
				// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
				$entity->$setter($value);
			}
		}//end foreach

		return $entity;
	}//end hydratePlacement()

	/**
	 * Owner-or-admin guard used by every public endpoint.
	 *
	 * @param Dashboard $dashboard The dashboard being acted upon.
	 * @param string $actorUserId The acting user ID.
	 *
	 * @return void
	 *
	 * @throws Exception When the actor is neither owner nor admin.
	 */
	private function assertOwnerOrAdmin(
		Dashboard $dashboard,
		string $actorUserId,
	): void {
		$ownerId = $dashboard->getUserId();
		if ($ownerId !== null && $ownerId === $actorUserId) {
			return;
		}

		try {
			if ($this->groupManager->isAdmin(userId: $actorUserId) === true) {
				return;
			}
		} catch (Throwable) {
			// Defensive — fall through to the denial path so a flaky
			// group manager never silently authorises a caller.
		}

		throw new Exception(
			message: self::ERR_FORBIDDEN_NOT_OWNER_OR_ADMIN
		);
	}//end assertOwnerOrAdmin()
}//end class
