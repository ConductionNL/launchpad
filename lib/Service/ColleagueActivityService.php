<?php

/**
 * ColleagueActivityService
 *
 * Recent colleague activity, filtered by what the reader may see
 * (REQ-DWMS-007). Built on the existing activity stream, not a second one.
 *
 * The activity app writes a row only for a recipient it judged allowed to
 * see the event, so the stream is scoped to the reader when it is written.
 * Access can change afterwards: a dashboard is unshared, or deleted. So a
 * LaunchPad dashboard event is checked again at read time, and an entry the
 * reader may not see is left out entirely: not redacted, and not counted in
 * the total the widget shows.
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

use OCA\LaunchPad\Activity\Extension;
use OCA\LaunchPad\Db\ActivityEventReader;
use OCA\LaunchPad\Db\DashboardMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserManager;

/**
 * What colleagues did recently that the reader may see.
 */
class ColleagueActivityService {
	/**
	 * The most entries one widget lists.
	 *
	 * @var int
	 */
	public const MAX_LIMIT = 50;

	/**
	 * Constructor.
	 *
	 * @param ActivityEventReader $events The activity stream.
	 * @param DashboardMapper $dashboards Resolves a dashboard event's object.
	 * @param PermissionService $permissions Answers whether the reader may see it.
	 * @param IUserManager $users Gives the actor a display name.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function __construct(
		private readonly ActivityEventReader $events,
		private readonly DashboardMapper $dashboards,
		private readonly PermissionService $permissions,
		private readonly IUserManager $users,
	) {
	}//end __construct()

	/**
	 * The reader's recent colleague activity.
	 *
	 * @param string $readerId The reader.
	 * @param int $limit How many entries at most.
	 *
	 * @return array{items: array<int, array<string, mixed>>, total: int, available: bool}
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function recent(string $readerId, int $limit = 20): array {
		$limit = max(1, min(self::MAX_LIMIT, $limit));
		if ($readerId === '') {
			return ['items' => [], 'total' => 0, 'available' => false];
		}

		// Read a wider window than the limit, so entries dropped by the
		// permission filter do not leave the list short.
		$rows = $this->events->recentFromOthers(readerId: $readerId, limit: ($limit * 3));

		$items = [];
		$visibility = [];
		foreach ($rows as $row) {
			$label = $this->visibleLabel(readerId: $readerId, row: $row, cache: $visibility);
			if ($label === null) {
				continue;
			}

			$actor = (string)$row['user'];
			$items[] = [
				'id' => (int)$row['activity_id'],
				'actor' => $actor,
				'actorName' => ($this->users->getDisplayName($actor) ?? $actor),
				'app' => (string)$row['app'],
				'type' => (string)$row['type'],
				'object' => $label,
				'link' => (string)($row['link'] ?? ''),
				'timestamp' => (int)$row['timestamp'],
			];
			if (count($items) >= $limit) {
				break;
			}
		}//end foreach

		// The total is counted from what survived the filter, never from
		// the rows read.
		return ['items' => $items, 'total' => count($items), 'available' => $this->events->isAvailable()];
	}//end recent()

	/**
	 * The label to show for an entry, or null when the reader may not see it.
	 *
	 * @param string $readerId The reader.
	 * @param array<string, mixed> $row The activity row.
	 * @param array<string, string|null> $cache Labels per dashboard uuid.
	 *
	 * @return string|null
	 */
	private function visibleLabel(string $readerId, array $row, array &$cache): ?string {
		$object = (string)($row['file'] ?? '');
		if ((string)$row['app'] !== Extension::APP_ID || (string)($row['object_type'] ?? '') !== Extension::OBJECT_TYPE) {
			// Other apps' rows were scoped by the activity app when written.
			return basename($object);
		}

		if (array_key_exists($object, $cache) === true) {
			return $cache[$object];
		}

		$cache[$object] = null;
		try {
			$dashboard = $this->dashboards->findByUuid(uuid: $object);
		} catch (DoesNotExistException) {
			return null;
		}

		if ($this->permissions->canViewDashboard(userId: $readerId, dashboardId: (int)$dashboard->getId()) === true) {
			$cache[$object] = (string)$dashboard->getName();
		}

		return $cache[$object];
	}//end visibleLabel()
}//end class
