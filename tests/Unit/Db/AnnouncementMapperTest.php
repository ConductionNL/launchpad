<?php

/**
 * AnnouncementMapperTest
 *
 * Unit tests for AnnouncementMapper: the live announcements (published, inside their period, newest first), the drafts of one author or of everyone, and the lookup by uuid.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Db
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Db;

use DateTime;
use OCA\LaunchPad\Db\AnnouncementMapper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Db\AnnouncementMapper
 * @uses   \OCA\LaunchPad\Db\Announcement
 * @uses   \OCA\LaunchPad\Db\ColumnMatchQueries
 */
class AnnouncementMapperTest extends TestCase {

	/**
	 * Live means published, started (or no start) and not yet ended (or no end), newest first.
	 *
	 * @return void
	 */
	public function testFindLiveIsPublishedAndInsideItsPeriod(): void {
		$recorder       = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 3, 'uuid' => 'n-3', 'title' => 'Onderhoud']];

		$live = (new AnnouncementMapper(db: $recorder->connection()))->findLive(now: new DateTime('2026-11-23T10:00:00Z'));

		$this->assertSame(expected: 'Onderhoud', actual: $live[0]->getTitle());
		$this->assertContains(needle: 'qb1.createNamedParameter("published", 2, null)', haystack: $recorder->calls);
		$this->assertContains(needle: 'qb1.andWhere(orX("isNull(\"publish_at\")", "lte(\"publish_at\", \":p2\", null)"))', haystack: $recorder->calls);
		$this->assertContains(needle: 'qb1.andWhere(orX("isNull(\"expires_at\")", "gt(\"expires_at\", \":p3\", null)"))', haystack: $recorder->calls);
		$this->assertSame(
			expected: 2,
			actual: count(value: preg_grep(pattern: '/^qb1\.createNamedParameter\(DateTime, "datetime", null\)$/', array: $recorder->calls))
		);
		$this->assertContains(needle: 'qb1.orderBy("publish_at", "DESC")', haystack: $recorder->calls);
		$this->assertContains(needle: 'qb1.addOrderBy("id", "DESC")', haystack: $recorder->calls);
	}//end testFindLiveIsPublishedAndInsideItsPeriod()

	/**
	 * An author sees only their own drafts, most recently edited first.
	 *
	 * @return void
	 */
	public function testFindDraftsOfOneAuthor(): void {
		$recorder = new QueryRecorder(test: $this);

		$this->assertSame(expected: [], actual: (new AnnouncementMapper(db: $recorder->connection()))->findDrafts(authorId: 'alice'));
		$this->assertContains(needle: 'qb1.createNamedParameter("draft", 2, null)', haystack: $recorder->calls);
		$this->assertContains(needle: 'qb1.andWhere("eq(\"author_id\", \":p2\", null)")', haystack: $recorder->calls);
		$this->assertContains(needle: 'qb1.createNamedParameter("alice", 2, null)', haystack: $recorder->calls);
		$this->assertContains(needle: 'qb1.orderBy("updated_at", "DESC")', haystack: $recorder->calls);
	}//end testFindDraftsOfOneAuthor()

	/**
	 * Without an author every draft is listed, with no author filter.
	 *
	 * @return void
	 */
	public function testFindDraftsOfEveryone(): void {
		$recorder = new QueryRecorder(test: $this);

		(new AnnouncementMapper(db: $recorder->connection()))->findDrafts(authorId: null);

		$this->assertSame(expected: [], actual: preg_grep(pattern: '/author_id/', array: $recorder->calls));
		$this->assertContains(needle: 'qb1.where("eq(\"status\", \":p1\", null)")', haystack: $recorder->calls);
	}//end testFindDraftsOfEveryone()

	/**
	 * The lookup by uuid binds the uuid.
	 *
	 * @return void
	 */
	public function testFindByUuid(): void {
		$recorder       = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 5, 'uuid' => 'n-5']];

		$this->assertSame(expected: 5, actual: (new AnnouncementMapper(db: $recorder->connection()))->findByUuid(uuid: 'n-5')->getId());
		$this->assertContains(needle: 'qb1.createNamedParameter("n-5", 2, null)', haystack: $recorder->calls);
		$this->assertContains(needle: 'qb1.where("eq(\"uuid\", \":p1\", null)")', haystack: $recorder->calls);
	}//end testFindByUuid()
}//end class
