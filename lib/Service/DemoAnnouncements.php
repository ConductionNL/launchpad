<?php

/**
 * DemoAnnouncements
 *
 * The three demo announcements a demo install gets (engagement-announcements,
 * design "Seed data", task 11).
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
 * @spec openspec/changes/engagement-announcements/tasks.md#task-11
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTime;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Db\AnnouncementMapper;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * Seeds two news items and a maintenance notice, only when there are no announcements yet.
 *
 * Seeded announcements are published at once and stamped as notified, so a
 * demo install sends no follow notifications. The notice ends on the coming
 * Saturday at 12:00 and cannot be dismissed.
 *
 * @spec openspec/changes/engagement-announcements/tasks.md#task-11
 */
class DemoAnnouncements {

	/**
	 * The author recorded on the demo announcements.
	 *
	 * @var string
	 */
	public const AUTHOR = 'admin';

	/**
	 * Constructor.
	 *
	 * @param AnnouncementMapper $announcements The announcements.
	 * @param ITimeFactory       $time          The clock.
	 *
	 * @spec openspec/changes/engagement-announcements/tasks.md#task-11
	 */
	public function __construct(
		private readonly AnnouncementMapper $announcements,
		private readonly ITimeFactory $time,
	) {
	}//end __construct()

	/**
	 * Seed the demo announcements unless any announcement exists.
	 *
	 * @return int How many were seeded.
	 *
	 * @spec openspec/changes/engagement-announcements/tasks.md#task-11
	 */
	public function seed(): int {
		$now = $this->time->getDateTime();
		if ($this->announcements->findLive(now: $now) !== [] || $this->announcements->findDrafts(authorId: null) !== []) {
			return 0;
		}

		$saturday = (clone $now)->modify('next saturday')->setTime(12, 0);
		$rows = [
			['news', 'Nieuwe werkplekken op de 3e verdieping', 'Vanaf maandag zijn er twintig flexplekken extra op de derde verdieping. Reserveren gaat via de werkplekkenapp.', 'Facilitair', [], null],
			['news', 'Inloopspreekuur privacy', 'Elke donderdag van 13:00 tot 14:00 beantwoordt de privacy officer je vragen, in kamer 2.14 of online.', 'Privacy', ['Medewerkers'], null],
			['notice', 'Onderhoud zaaksysteem zaterdag 08:00 tot 12:00', 'Het zaaksysteem is zaterdag tussen 08:00 en 12:00 niet bereikbaar wegens onderhoud.', null, [], $saturday],
		];
		foreach ($rows as [$kind, $title, $body, $category, $groups, $expiresAt]) {
			$this->announcements->insert(entity: $this->announcement(kind: $kind, text: [$title, $body], category: $category, groups: $groups, expiresAt: $expiresAt));
		}

		return count($rows);
	}//end seed()

	/**
	 * One published demo announcement.
	 *
	 * @param string        $kind      `news` or `notice`.
	 * @param array         $text      The title and the body.
	 * @param string|null   $category  The category.
	 * @param list<string>  $groups    The target groups.
	 * @param DateTime|null $expiresAt The end time.
	 *
	 * @return Announcement
	 *
	 * @spec openspec/changes/engagement-announcements/tasks.md#task-11
	 */
	private function announcement(string $kind, array $text, ?string $category, array $groups, ?DateTime $expiresAt): Announcement {
		$now = $this->time->getDateTime();
		$announcement = new Announcement();
		$announcement->setUuid($this->uuid());
		$announcement->setKind($kind);
		$announcement->setTitle($text[0]);
		$announcement->setBody($text[1]);
		$announcement->setCategory($category);
		$announcement->setTargetGroupList($groups);
		$announcement->setStatus(Announcement::STATUS_PUBLISHED);
		$announcement->setLevel(($kind === Announcement::KIND_NOTICE) ? 'warning' : null);
		$announcement->setDismissible(0);
		$announcement->setAllowComments((int) ($kind === Announcement::KIND_NEWS));
		$announcement->setPublishAt($now);
		$announcement->setExpiresAt($expiresAt);
		$announcement->setNotifiedAt($now);
		$announcement->setAuthorId(self::AUTHOR);
		$announcement->setCreatedAt($now);
		$announcement->setUpdatedAt($now);

		return $announcement;
	}//end announcement()

	/**
	 * A random version-4 UUID.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/engagement-announcements/tasks.md#task-11
	 */
	private function uuid(): string {
		$data = random_bytes(16);
		$data[6] = chr((ord($data[6]) & 0x0F) | 0x40);
		$data[8] = chr((ord($data[8]) & 0x3F) | 0x80);

		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}//end uuid()
}//end class
