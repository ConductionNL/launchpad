<?php

/**
 * EditSessionVersions
 *
 * Leaving edit mode keeps the dashboard as it was before editing as a version.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\Versions
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-27-versions-on-widget-edits/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\Versions;

use OCA\LaunchPad\Db\DashboardVersion;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCA\LaunchPad\Service\VersionStores;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\ICache;
use OCP\ICacheFactory;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * An edit session runs from entering edit mode (the editor's presence join)
 * to leaving it (the presence leave). It keeps the dashboard as it was when
 * it began; when it ends and the dashboard changed, that state becomes one
 * automatic version, so restoring it undoes the session (REQ-VERS-010).
 *
 * @spec openspec/changes/archive/2026-11-27-versions-on-widget-edits/tasks.md#task-1
 */
class EditSessionVersions {
	/**
	 * How long the state at the start of a session is kept, in seconds. A
	 * session whose end never arrives (the browser closed) stores no version.
	 *
	 * @var int
	 */
	public const SESSION_SECONDS = 43200;

	/**
	 * The distributed cache holding the sessions, or null without one.
	 *
	 * @var ICache|null
	 */
	private readonly ?ICache $cache;

	/**
	 * Constructor.
	 *
	 * @param VersionStores           $stores       The dashboard and version tables.
	 * @param DashboardVersionService $versions     Builds and stores the snapshots.
	 * @param ICacheFactory           $cacheFactory The Nextcloud cache factory.
	 * @param LoggerInterface         $logger       The logger.
	 *
	 * @spec openspec/changes/archive/2026-11-27-versions-on-widget-edits/tasks.md#task-1
	 */
	public function __construct(
		private readonly VersionStores $stores,
		private readonly DashboardVersionService $versions,
		ICacheFactory $cacheFactory,
		private readonly LoggerInterface $logger,
	) {
		$cache = null;
		if ($cacheFactory->isAvailable() === true) {
			$cache = $cacheFactory->createDistributed(prefix: 'launchpad_edit_session');
		}

		$this->cache = $cache;
	}//end __construct()

	/**
	 * Begin a session: keep the dashboard as it is now, once per session (a
	 * presence renewal keeps the first state). Never throws: a missing
	 * version must not keep anyone out of edit mode.
	 *
	 * @param string $dashboardUuid The dashboard entering edit mode.
	 * @param string $userId        The editor.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-27-versions-on-widget-edits/tasks.md#task-1
	 */
	public function begin(string $dashboardUuid, string $userId): void {
		if ($this->cache === null || $dashboardUuid === '' || $userId === '') {
			return;
		}

		try {
			$key = self::key(dashboardUuid: $dashboardUuid, userId: $userId);
			if ($this->cache->hasKey(key: $key) === true) {
				return;
			}

			$dashboard = $this->stores->dashboardMapper->findByUuid(uuid: $dashboardUuid);
			$state     = $this->versions->stateBeforeUpdate(dashboardId: (int)$dashboard->getId());
			if ($state !== null) {
				$this->cache->set(key: $key, value: $state['snapshotJson'], ttl: self::SESSION_SECONDS);
			}
		} catch (Throwable $t) {
			$this->logger->warning(
				message: 'launchpad: keeping the state at the start of editing failed',
				context: ['exception' => $t, 'dashboardUuid' => $dashboardUuid]
			);
		}//end try
	}//end begin()

	/**
	 * End a session: when the dashboard changed since it began, store the
	 * state from then as an automatic version, outside the 60-second
	 * debounce. Nothing is stored when nothing changed, or when the newest
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
	public function end(string $dashboardUuid, string $userId): ?DashboardVersion {
		$before = $this->takeSession(dashboardUuid: $dashboardUuid, userId: $userId);
		if ($before === null) {
			return null;
		}

		try {
			$dashboard = $this->stores->dashboardMapper->findByUuid(uuid: $dashboardUuid);
			$current   = $this->versions->stateBeforeUpdate(dashboardId: (int)$dashboard->getId());
			if ($current === null || $this->isKept(dashboardUuid: $dashboardUuid, before: $before, current: $current['snapshotJson']) === true) {
				return null;
			}

			return $this->versions->captureSnapshot(
				dashboard: $dashboard,
				createdBy: $userId,
				options: ['snapshotJson' => $before, 'force' => true]
			);
		} catch (DoesNotExistException) {
			return null;
		} catch (Throwable $t) {
			$this->logger->warning(
				message: 'launchpad: storing the version of an edit session failed',
				context: ['exception' => $t, 'dashboardUuid' => $dashboardUuid]
			);
			return null;
		}//end try
	}//end end()

	/**
	 * The state kept at the start of a session, removed from the cache.
	 *
	 * @param string $dashboardUuid The dashboard.
	 * @param string $userId        The editor.
	 *
	 * @return string|null The snapshot, or null when no session runs.
	 *
	 * @spec openspec/changes/archive/2026-11-27-versions-on-widget-edits/tasks.md#task-1
	 */
	private function takeSession(string $dashboardUuid, string $userId): ?string {
		if ($this->cache === null || $dashboardUuid === '' || $userId === '') {
			return null;
		}

		try {
			$key    = self::key(dashboardUuid: $dashboardUuid, userId: $userId);
			$before = $this->cache->get(key: $key);
			$this->cache->remove(key: $key);
		} catch (Throwable $t) {
			$this->logger->warning(
				message: 'launchpad: reading the state at the start of editing failed',
				context: ['exception' => $t, 'dashboardUuid' => $dashboardUuid]
			);
			return null;
		}

		if (is_string($before) === false || $before === '') {
			return null;
		}

		return $before;
	}//end takeSession()

	/**
	 * Whether no version is needed: nothing changed during the session, or
	 * the newest version already holds the state from before it.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $before        The state when the session began.
	 * @param string $current       The state now.
	 *
	 * @return bool True when no version is needed.
	 *
	 * @spec openspec/changes/archive/2026-11-27-versions-on-widget-edits/tasks.md#task-1
	 */
	private function isKept(string $dashboardUuid, string $before, string $current): bool {
		if (self::sameContent(firstJson: $before, secondJson: $current) === true) {
			return true;
		}

		$latest = $this->stores->versionMapper->findLatestByDashboard(dashboardUuid: $dashboardUuid, limit: 1);
		if ($latest === []) {
			return false;
		}

		return self::sameContent(firstJson: (string)$latest[0]->getSnapshotJson(), secondJson: $before);
	}//end isKept()

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
	public static function sameContent(string $firstJson, string $secondJson): bool {
		$first  = json_decode($firstJson, true);
		$second = json_decode($secondJson, true);

		return self::comparable(payload: $first) === self::comparable(payload: $second);
	}//end sameContent()

	/**
	 * A snapshot without what every save moves: row ids and timestamps.
	 *
	 * @param mixed $payload The decoded snapshot.
	 *
	 * @return array{dashboard: string, placements: list<string>} The comparable content.
	 */
	private static function comparable(mixed $payload): array {
		$sections = ['dashboard' => [], 'placements' => []];
		if (is_array($payload) === true) {
			$sections = array_merge($sections, array_intersect_key($payload, $sections));
		}

		return [
			'dashboard'  => self::withoutMoving(row: $sections['dashboard'], keys: ['updatedAt', 'createdAt']),
			'placements' => self::comparablePlacements(placements: $sections['placements']),
		];
	}//end comparable()

	/**
	 * The widgets of a snapshot, each without its row id, timestamps and row
	 * version, in a stable order.
	 *
	 * @param mixed $placements The snapshot's widgets.
	 *
	 * @return list<string> The comparable widgets.
	 */
	private static function comparablePlacements(mixed $placements): array {
		$comparable = [];
		foreach ((array)$placements as $placement) {
			$comparable[] = self::withoutMoving(
				row: $placement,
				keys: ['id', 'dashboardId', 'createdAt', 'updatedAt', 'version']
			);
		}

		sort($comparable);
		return $comparable;
	}//end comparablePlacements()

	/**
	 * A row encoded with sorted keys, without the given keys.
	 *
	 * @param mixed        $row  The row.
	 * @param list<string> $keys The keys to leave out.
	 *
	 * @return string The encoded row.
	 */
	private static function withoutMoving(mixed $row, array $keys): string {
		$kept = array_diff_key((array)$row, array_flip($keys));
		ksort($kept);
		return (string)json_encode($kept);
	}//end withoutMoving()

	/**
	 * The cache key of one editor's session on one dashboard.
	 *
	 * @param string $dashboardUuid The dashboard UUID.
	 * @param string $userId        The editor.
	 *
	 * @return string The key.
	 */
	private static function key(string $dashboardUuid, string $userId): string {
		return md5($dashboardUuid.'/'.$userId);
	}//end key()
}//end class
