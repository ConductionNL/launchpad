<?php

/**
 * DemoAnnouncementsTest
 *
 * The three demo announcements (engagement-announcements task 11).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
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

namespace Unit\Service;

use DateTime;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Db\AnnouncementMapper;
use OCA\LaunchPad\Service\DemoAnnouncements;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DemoAnnouncements.
 */
class DemoAnnouncementsTest extends TestCase {

	/**
	 * The seeder on Thursday 2026-11-12 10:00 over a mapper holding the given announcements.
	 *
	 * @param list<Announcement> $existing  Live announcements already there.
	 * @param list<Announcement> $inserted  Receives what is inserted.
	 *
	 * @return DemoAnnouncements
	 */
	private function seeder(array $existing, array &$inserted): DemoAnnouncements {
		$mapper = $this->createMock(AnnouncementMapper::class);
		$mapper->method('findLive')->willReturn($existing);
		$mapper->method('findDrafts')->willReturn([]);
		$mapper->method('insert')->willReturnCallback(static function (Announcement $announcement) use (&$inserted): Announcement {
			$inserted[] = $announcement;
			return $announcement;
		});
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturnCallback(static fn (): DateTime => new DateTime('2026-11-12T10:00:00+01:00'));

		return new DemoAnnouncements(announcements: $mapper, time: $time);
	}//end seeder()

	/**
	 * The design's three announcements are seeded published, with their categories, groups and the Saturday notice.
	 *
	 * @return void
	 */
	public function testTheThreeDemoAnnouncementsAreSeeded(): void {
		$inserted = [];
		$this->assertSame(3, $this->seeder(existing: [], inserted: $inserted)->seed());

		$summary = array_map(
			static fn (Announcement $a): array => [$a->getKind(), $a->getTitle(), $a->getCategory(), $a->getTargetGroupList(), $a->getLevel(), $a->getDismissible(), $a->getAllowComments()],
			$inserted
		);
		$this->assertSame(
			[
				['news', 'Nieuwe werkplekken op de 3e verdieping', 'Facilitair', [], null, 0, 1],
				['news', 'Inloopspreekuur privacy', 'Privacy', ['Medewerkers'], null, 0, 1],
				['notice', 'Onderhoud zaaksysteem zaterdag 08:00 tot 12:00', null, [], 'warning', 0, 0],
			],
			$summary
		);
		$this->assertSame('2026-11-14 12:00', $inserted[2]->getExpiresAt()->format('Y-m-d H:i'));
		$this->assertNull($inserted[0]->getExpiresAt());
		foreach ($inserted as $announcement) {
			$this->assertSame('published', $announcement->getStatus());
			$this->assertNotNull($announcement->getNotifiedAt(), 'a demo announcement must not notify followers');
			$this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $announcement->getUuid());
		}
	}//end testTheThreeDemoAnnouncementsAreSeeded()

	/**
	 * Nothing is seeded when announcements already exist.
	 *
	 * @return void
	 */
	public function testNothingIsSeededOverExistingAnnouncements(): void {
		$inserted = [];
		$this->assertSame(0, $this->seeder(existing: [new Announcement()], inserted: $inserted)->seed());
		$this->assertSame([], $inserted);
	}//end testNothingIsSeededOverExistingAnnouncements()
}//end class
