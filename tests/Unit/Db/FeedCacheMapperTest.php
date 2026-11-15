<?php

/**
 * FeedCacheMapperTest
 *
 * Unit tests for FeedCacheMapper: lookup by URL, the find-or-insert upsert,
 * the ordered listing and the orphan cutoff, asserted on the rows returned
 * and on the query each method builds.
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

use DateTimeImmutable;
use OCA\LaunchPad\Db\FeedCache;
use OCA\LaunchPad\Db\FeedCacheMapper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Db\FeedCacheMapper
 * @uses \OCA\LaunchPad\Db\ColumnMatchQueries
 * @uses \OCA\LaunchPad\Db\FeedCache
 */
class FeedCacheMapperTest extends TestCase {

	/**
	 * A row is found by its URL and hydrated.
	 *
	 * @return void
	 */
	public function testFindByUrlMatchesTheUrlColumn(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 3, 'feed_url' => 'https://example.org/feed']];

		$row = (new FeedCacheMapper($recorder->connection()))->findByUrl('https://example.org/feed');

		$this->assertSame(3, $row->getId());
		$this->assertSame('https://example.org/feed', $row->getFeedUrl());
		$this->assertContains('qb1.from("launchpad_feed_cache", null)', $recorder->calls);
		$this->assertContains('qb1.expr.eq("feed_url", ":p1", null)', $recorder->calls);
	}//end testFindByUrlMatchesTheUrlColumn()

	/**
	 * An existing row is returned by the upsert without an insert.
	 *
	 * @return void
	 */
	public function testUpsertReturnsAnExistingRow(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 8, 'feed_url' => 'https://example.org/feed']];

		$row = (new FeedCacheMapper($recorder->connection()))->upsertUrl('https://example.org/feed');

		$this->assertSame(8, $row->getId());
		$this->assertSame([], preg_grep('/\.insert\(/', $recorder->calls));
	}//end testUpsertReturnsAnExistingRow()

	/**
	 * A URL with no row is inserted, and the new row carries the URL.
	 *
	 * @return void
	 */
	public function testUpsertInsertsAMissingUrl(): void {
		$recorder = new QueryRecorder(test: $this);

		$row = (new FeedCacheMapper($recorder->connection()))->upsertUrl('https://example.org/new');

		$this->assertInstanceOf(FeedCache::class, $row);
		$this->assertSame('https://example.org/new', $row->getFeedUrl());
		$this->assertSame(1, $row->getId());
		$this->assertContains('qb2.insert("launchpad_feed_cache")', $recorder->calls);
		$this->assertContains('qb2.setValue("feed_url", ":p2")', $recorder->calls);
	}//end testUpsertInsertsAMissingUrl()

	/**
	 * The listing is ordered by URL.
	 *
	 * @return void
	 */
	public function testFindAllIsOrderedByUrl(): void {
		$recorder = new QueryRecorder(test: $this);
		$recorder->rows = [['id' => 1, 'feed_url' => 'a'], ['id' => 2, 'feed_url' => 'b']];

		$rows = (new FeedCacheMapper($recorder->connection()))->findAll();

		$this->assertSame(['a', 'b'], array_map(static fn (FeedCache $row): ?string => $row->getFeedUrl(), $rows));
		$this->assertContains('qb1.orderBy("feed_url", "ASC")', $recorder->calls);
	}//end testFindAllIsOrderedByUrl()

	/**
	 * The orphan cutoff is compared as a database timestamp, strictly before.
	 *
	 * @return void
	 */
	public function testOrphansAreThoseLastFetchedBeforeTheCutoff(): void {
		$recorder = new QueryRecorder(test: $this);

		$rows = (new FeedCacheMapper($recorder->connection()))->findOrphanedBefore(new DateTimeImmutable('2026-02-03 04:05:06'));

		$this->assertSame([], $rows);
		$this->assertContains('qb1.createNamedParameter("2026-02-03 04:05:06", 2, null)', $recorder->calls);
		$this->assertContains('qb1.expr.lt("last_fetched_at", ":p1", null)', $recorder->calls);
	}//end testOrphansAreThoseLastFetchedBeforeTheCutoff()
}//end class
