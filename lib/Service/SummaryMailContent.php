<?php

/**
 * SummaryMailContent
 *
 * What one person's summary email says (personal-settings REQ-PSET-002): the
 * announcements published for them since the last summary, and per dashboard
 * they can open, the "What changed" notes of dashboard-versioning since then.
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

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Db\DashboardVersionMapper;
use OCA\LaunchPad\Kernel\Contracts\AnnouncementServiceInterface;

/**
 * Collects the lines of one person's summary.
 *
 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
 */
class SummaryMailContent {

	/**
	 * The newest versions read per dashboard; older ones predate any summary window.
	 */
	private const VERSIONS_PER_DASHBOARD = 25;

	/**
	 * Constructor.
	 *
	 * @param AnnouncementServiceInterface $announcements The announcements a reader sees.
	 * @param DashboardService             $dashboards    The dashboards a reader can open.
	 * @param DashboardVersionMapper       $versions      The dashboards' versions and their notes.
	 */
	public function __construct(
		private readonly AnnouncementServiceInterface $announcements,
		private readonly DashboardService $dashboards,
		private readonly DashboardVersionMapper $versions,
	) {
	}//end __construct()

	/**
	 * The summary of one person since a moment.
	 *
	 * @param string            $userId The reader.
	 * @param DateTimeInterface $since  The last summary, or the start of the window.
	 *
	 * @return array{announcements: list<string>, changes: list<string>}
	 *
	 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
	 */
	public function collect(string $userId, DateTimeInterface $since): array {
		return [
			'announcements' => $this->announcementsSince(userId: $userId, since: $since),
			'changes' => $this->changesSince(userId: $userId, since: $since),
		];
	}//end collect()

	/**
	 * Titles of the announcements published for the reader since the moment.
	 *
	 * @param string            $userId The reader.
	 * @param DateTimeInterface $since  The moment.
	 *
	 * @return list<string>
	 */
	private function announcementsSince(string $userId, DateTimeInterface $since): array {
		$out = [];
		foreach ($this->announcements->visibleTo(userId: $userId) as $announcement) {
			$published = self::publishedAt(announcement: $announcement);
			if ($published !== null && $published > $since->getTimestamp()) {
				$out[] = (string)$announcement->getTitle();
			}
		}

		return $out;
	}//end announcementsSince()

	/**
	 * "Dashboard: note" for every noted version since the moment, on the
	 * dashboards the reader can open, oldest first per dashboard.
	 *
	 * @param string            $userId The reader.
	 * @param DateTimeInterface $since  The moment.
	 *
	 * @return list<string>
	 */
	private function changesSince(string $userId, DateTimeInterface $since): array {
		$out = [];
		foreach ($this->dashboards->getVisibleToUser(userId: $userId) as $entry) {
			$dashboard = $entry['dashboard'];
			$uuid = (string)$dashboard->getUuid();
			if ($uuid === '') {
				continue;
			}

			$notes = [];
			foreach ($this->versions->findLatestByDashboard(dashboardUuid: $uuid, limit: self::VERSIONS_PER_DASHBOARD) as $version) {
				$note = trim((string)$version->getNote());
				$created = self::timestamp(value: $version->getCreatedAt());
				if ($note !== '' && $created !== null && $created > $since->getTimestamp()) {
					$notes[] = (string)$dashboard->getName().': '.$note;
				}
			}

			// The mapper reads newest first; the mail tells it in order.
			array_push($out, ...array_reverse($notes));
		}

		return $out;
	}//end changesSince()

	/**
	 * When an announcement went live: its publish moment, else its creation.
	 *
	 * @param Announcement $announcement The announcement.
	 *
	 * @return int|null Unix time, or null when it carries neither.
	 */
	private static function publishedAt(Announcement $announcement): ?int {
		$moment = $announcement->getPublishAt() ?? $announcement->getCreatedAt();
		if ($moment === null) {
			return null;
		}

		return $moment->getTimestamp();
	}//end publishedAt()

	/**
	 * A stored `Y-m-d H:i:s` moment as Unix time.
	 *
	 * @param string|null $value The stored moment.
	 *
	 * @return int|null Unix time, or null when it does not parse.
	 */
	private static function timestamp(?string $value): ?int {
		if ($value === null || $value === '') {
			return null;
		}

		try {
			return (new DateTimeImmutable($value))->getTimestamp();
		} catch (Exception) {
			return null;
		}
	}//end timestamp()
}//end class
