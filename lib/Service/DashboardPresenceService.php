<?php

/**
 * DashboardPresenceService
 *
 * Who is editing a dashboard right now, and since when
 * (building-edit-a-dashboard-together, task 1.1). Every editor in edit mode
 * announces itself on a short heartbeat; an editor that stops announcing
 * drops off after PRESENCE_TTL seconds. The list lives in the distributed
 * cache: it is a few seconds of state, not a record anyone keeps.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/building-edit-a-dashboard-together/specs/dashboard-co-editing/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IUserManager;

/**
 * Keeps and reads the editors present on a dashboard.
 *
 * @spec openspec/changes/building-edit-a-dashboard-together/specs/dashboard-co-editing/spec.md
 */
class DashboardPresenceService {

	/**
	 * Seconds an editor stays listed after its last heartbeat.
	 *
	 * @var int
	 */
	public const PRESENCE_TTL = 90;

	/**
	 * The cache the presence lists are kept in.
	 *
	 * @var ICache
	 */
	private ICache $cache;

	/**
	 * Constructor.
	 *
	 * @param ICacheFactory     $cacheFactory      Gives the distributed cache.
	 * @param DashboardMapper   $dashboardMapper   Resolves the dashboard of a UUID.
	 * @param PermissionService $permissionService Tells who may edit a dashboard.
	 * @param IUserManager      $userManager       Gives an editor's display name.
	 * @param ITimeFactory      $timeFactory       Tells the time.
	 *
	 * @spec openspec/changes/building-edit-a-dashboard-together/tasks.md#task-1.1
	 */
	public function __construct(
		ICacheFactory $cacheFactory,
		private readonly DashboardMapper $dashboardMapper,
		private readonly PermissionService $permissionService,
		private readonly IUserManager $userManager,
		private readonly ITimeFactory $timeFactory,
	) {
		$this->cache = $cacheFactory->createDistributed(prefix: 'launchpad_presence_');
	}//end __construct()

	/**
	 * Announce that a person edits a dashboard, or renew the announcement,
	 * and answer who else is editing it.
	 *
	 * The first announcement sets the moment the person started; renewing
	 * keeps it.
	 *
	 * @param string $dashboardUuid The dashboard.
	 * @param string $userId        The editor.
	 *
	 * @return list<array{displayName: string, since: string}> The colleagues editing it, longest first.
	 *
	 * @throws DoesNotExistException When the dashboard does not exist.
	 * @throws ForbiddenException When the person may not edit the dashboard.
	 *
	 * @spec openspec/changes/building-edit-a-dashboard-together/tasks.md#task-1.1
	 */
	public function join(string $dashboardUuid, string $userId): array {
		$dashboard = $this->dashboardMapper->findByUuid(uuid: $dashboardUuid);
		if ($this->permissionService->canEditDashboard(userId: $userId, dashboardId: (int)$dashboard->getId()) === false) {
			throw new ForbiddenException(message: 'You may not edit this dashboard');
		}

		$now     = $this->timeFactory->getTime();
		$editors = $this->present(dashboardUuid: $dashboardUuid, now: $now);

		$since = ($editors[$userId]['since'] ?? date(format: DATE_ATOM, timestamp: $now));
		$editors[$userId] = [
			'displayName' => $this->displayName(userId: $userId),
			'since'       => $since,
			'seenAt'      => $now,
		];
		$this->store(dashboardUuid: $dashboardUuid, editors: $editors);

		unset($editors[$userId]);
		return $this->colleagues(editors: $editors);
	}//end join()

	/**
	 * Take a person off a dashboard's editors. Idempotent.
	 *
	 * @param string $dashboardUuid The dashboard.
	 * @param string $userId        The editor leaving.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/building-edit-a-dashboard-together/tasks.md#task-1.1
	 */
	public function leave(string $dashboardUuid, string $userId): void {
		$editors = $this->present(dashboardUuid: $dashboardUuid, now: $this->timeFactory->getTime());
		if (array_key_exists($userId, $editors) === false) {
			return;
		}

		unset($editors[$userId]);
		$this->store(dashboardUuid: $dashboardUuid, editors: $editors);
	}//end leave()

	/**
	 * The editors of a dashboard whose heartbeat is still fresh.
	 *
	 * @param string $dashboardUuid The dashboard.
	 * @param int    $now           The current time, in seconds.
	 *
	 * @return array<string, array{displayName: string, since: string, seenAt: int}> By user id.
	 */
	private function present(string $dashboardUuid, int $now): array {
		$stored = $this->cache->get(key: $dashboardUuid);
		if (is_array($stored) === false) {
			return [];
		}

		$editors = [];
		foreach ($stored as $userId => $editor) {
			if (is_array($editor) === false || is_int($editor['seenAt'] ?? null) === false) {
				continue;
			}

			if ($now - $editor['seenAt'] > self::PRESENCE_TTL) {
				continue;
			}

			$editors[(string)$userId] = [
				'displayName' => (string)($editor['displayName'] ?? $userId),
				'since'       => (string)($editor['since'] ?? ''),
				'seenAt'      => $editor['seenAt'],
			];
		}

		return $editors;
	}//end present()

	/**
	 * Keep a dashboard's editors, or drop the entry when nobody is left.
	 *
	 * @param string                                                             $dashboardUuid The dashboard.
	 * @param array<string, array{displayName: string, since: string, seenAt: int}> $editors       By user id.
	 *
	 * @return void
	 */
	private function store(string $dashboardUuid, array $editors): void {
		if ($editors === []) {
			$this->cache->remove(key: $dashboardUuid);
			return;
		}

		$this->cache->set(key: $dashboardUuid, value: $editors, ttl: self::PRESENCE_TTL);
	}//end store()

	/**
	 * The colleagues as the editor sees them: name and start, longest first,
	 * without their user ids.
	 *
	 * @param array<string, array{displayName: string, since: string, seenAt: int}> $editors By user id.
	 *
	 * @return list<array{displayName: string, since: string}>
	 */
	private function colleagues(array $editors): array {
		$list = array_map(
			static fn (array $editor): array => ['displayName' => $editor['displayName'], 'since' => $editor['since']],
			array_values($editors)
		);
		usort($list, static fn (array $a, array $b): int => strcmp($a['since'], $b['since']));

		return $list;
	}//end colleagues()

	/**
	 * A person's display name, the user id when there is none.
	 *
	 * @param string $userId The user id.
	 *
	 * @return string
	 */
	private function displayName(string $userId): string {
		$name = $this->userManager->get(uid: $userId)?->getDisplayName();
		if ($name === null || $name === '') {
			return $userId;
		}

		return $name;
	}//end displayName()
}//end class
