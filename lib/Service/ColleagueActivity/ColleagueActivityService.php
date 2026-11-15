<?php

/**
 * ColleagueActivityService
 *
 * Recent colleague activity, filtered to what the reader may see (REQ-DWMS-007).
 *
 * @category Service
 * @package  OCA\LaunchPad\Service\ColleagueActivity
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service\ColleagueActivity;

use OCA\LaunchPad\Activity\Extension;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IUserManager;
use Throwable;

/**
 * What colleagues did recently, as the reader is allowed to see it.
 *
 * The rows come from the activity app, already addressed to the reader. An
 * event about a launchpad dashboard is checked again against the dashboard
 * as it is now, because a share can be withdrawn after the event was
 * written: an event on a dashboard the reader cannot see, or that is gone,
 * is left out. A left-out entry is absent, not redacted, and every count the
 * widget shows is taken from the entries that remain.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-007-recent-colleague-activity-filtered-by-what-you-may-see
 */
class ColleagueActivityService {

	/**
	 * The default number of entries listed.
	 */
	public const DEFAULT_LIMIT = 10;

	/**
	 * The most entries one answer lists.
	 */
	public const MAX_LIMIT = 50;

	/**
	 * The default period, in days.
	 */
	public const DEFAULT_DAYS = 7;

	/**
	 * The longest period, in days.
	 */
	public const MAX_DAYS = 90;

	/**
	 * Constructor.
	 *
	 * @param ColleagueActivityReader $reader      The activity rows addressed to the reader.
	 * @param DashboardMapper         $dashboards  Finds the dashboard an event names.
	 * @param PermissionService       $permissions Whether the reader can see that dashboard.
	 * @param IUserManager            $users       The actors' display names.
	 * @param ITimeFactory            $time        Now.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-5.2
	 */
	public function __construct(
		private readonly ColleagueActivityReader $reader,
		private readonly DashboardMapper $dashboards,
		private readonly PermissionService $permissions,
		private readonly IUserManager $users,
		private readonly ITimeFactory $time,
	) {
	}//end __construct()

	/**
	 * The recent activity of other people the reader may see.
	 *
	 * @param string $readerId The reader.
	 * @param int    $limit    How many entries to list (1 to MAX_LIMIT).
	 * @param int    $days     How many days back (1 to MAX_DAYS).
	 *
	 * @return array{available:bool,days:int,entries:list<array<string,mixed>>,total:int,counts:array<string,int>,actors:int}
	 *         The listed entries, and the totals of every visible entry in the period.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/tasks.md#task-5.2
	 */
	public function recent(string $readerId, int $limit=self::DEFAULT_LIMIT, int $days=self::DEFAULT_DAYS): array {
		$limit = max(1, min($limit, self::MAX_LIMIT));
		$days  = max(1, min($days, self::MAX_DAYS));
		$since = ($this->time->getTime() - ($days * 86400));
		$rows  = $this->reader->rows(readerId: $readerId, since: $since);

		$visible = [];
		$seen    = [];
		foreach (($rows ?? []) as $row) {
			if ($this->maySee(readerId: $readerId, row: $row, seen: $seen) === true) {
				$visible[] = $this->entry(row: $row);
			}
		}

		$counts = [];
		$actors = [];
		foreach ($visible as $entry) {
			$counts[$entry['type']]  = (($counts[$entry['type']] ?? 0) + 1);
			$actors[$entry['actor']] = true;
		}

		ksort($counts);

		return [
			'available' => ($rows !== null),
			'days'      => $days,
			'entries'   => array_slice($visible, 0, $limit),
			'total'     => count($visible),
			'counts'    => $counts,
			'actors'    => count($actors),
		];
	}//end recent()

	/**
	 * Whether the reader may see the object an activity row is about.
	 *
	 * @param string             $readerId The reader.
	 * @param array<string,mixed> $row     The activity row.
	 * @param array<string,bool> $seen     Earlier answers per dashboard, filled as it goes.
	 *
	 * @return bool
	 */
	private function maySee(string $readerId, array $row, array &$seen): bool {
		if ((string) ($row['object_type'] ?? '') !== Extension::OBJECT_TYPE) {
			return true;
		}

		$uuid = (string) ($row['object_name'] ?? '');
		if ($uuid === '') {
			return false;
		}

		if (isset($seen[$uuid]) === false) {
			$seen[$uuid] = $this->canSeeDashboard(readerId: $readerId, uuid: $uuid);
		}

		return $seen[$uuid];
	}//end maySee()

	/**
	 * Whether the reader can see the dashboard with this uuid now; a dashboard that is gone cannot be seen.
	 *
	 * @param string $readerId The reader.
	 * @param string $uuid     The dashboard uuid.
	 *
	 * @return bool
	 */
	private function canSeeDashboard(string $readerId, string $uuid): bool {
		try {
			$dashboard = $this->dashboards->findByUuid($uuid);
			return $this->permissions->canViewDashboard(userId: $readerId, dashboardId: (int) $dashboard->getId());
		} catch (Throwable) {
			return false;
		}
	}//end canSeeDashboard()

	/**
	 * One activity row as a listed entry.
	 *
	 * @param array<string,mixed> $row The activity row.
	 *
	 * @return array<string,mixed> The entry.
	 */
	private function entry(array $row): array {
		$actor  = (string) ($row['user'] ?? '');
		$params = json_decode((string) ($row['subjectparams'] ?? ''), true);
		$title  = '';
		if (is_array($params) === true && is_string($params['dashboard'] ?? null) === true) {
			$title = $params['dashboard'];
		}

		$type = trim((string) ($row['type'] ?? ''));
		if ($type === '') {
			$type = 'other';
		}

		return [
			'id'               => (int) ($row['activity_id'] ?? 0),
			'actor'            => $actor,
			'actorDisplayName' => ($this->users->getDisplayName($actor) ?? $actor),
			'app'              => (string) ($row['app'] ?? ''),
			'type'             => $type,
			'subject'          => (string) ($row['subject'] ?? ''),
			'title'            => $title,
			'link'             => (string) ($row['link'] ?? ''),
			'timestamp'        => (int) ($row['timestamp'] ?? 0),
		];
	}//end entry()
}//end class
