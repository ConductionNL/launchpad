<?php

/**
 * AcknowledgementMapperTest
 *
 * Unit tests for AcknowledgementMapper: the existence check and single
 * lookup per announcement, user and content version, the listings per
 * announcement and per user, and recording an acknowledgement.
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

use DateTimeInterface;
use OCA\LaunchPad\Db\AcknowledgementMapper;
use PHPUnit\Framework\TestCase;

/**
 * Pins the queries AcknowledgementMapper composes.
 *
 * @covers \OCA\LaunchPad\Db\AcknowledgementMapper
 * @uses \OCA\LaunchPad\Db\ColumnMatchQueries
 * @uses \OCA\LaunchPad\Db\Acknowledgement
 */
class AcknowledgementMapperTest extends TestCase {

	/**
	 * An acknowledgement exists when the count over key, user and integer version is positive.
	 *
	 * @return void
	 */
	public function testExistsForCountsTheExactAcknowledgement(): void {
		$yes = new QueryRecorder(test: $this);
		$yes->rows = [['cnt' => '1']];
		$this->assertTrue((new AcknowledgementMapper($yes->connection()))->existsFor('notice', 'alice', 3));
		$this->assertContains('qb1.from("launchpad_acknowledgements", null)', $yes->calls);
		$this->assertContains('qb1.createNamedParameter(3, 1, null)', $yes->calls);
		$this->assertContains('qb1.andWhere("eq(\"content_version\", \":p3\", null)")', $yes->calls);

		$no = new QueryRecorder(test: $this);
		$no->rows = [['cnt' => '0']];
		$this->assertFalse((new AcknowledgementMapper($no->connection()))->existsFor('notice', 'alice', 3));
	}//end testExistsForCountsTheExactAcknowledgement()

	/**
	 * The single lookup reads at most one row, and answers null when there is none.
	 *
	 * @return void
	 */
	public function testFindOneForAnswersTheFirstRowOrNull(): void {
		$found = new QueryRecorder(test: $this);
		$found->rows = [['id' => 4, 'announcement_key' => 'notice', 'user_id' => 'alice', 'content_version' => 3]];

		$ack = (new AcknowledgementMapper($found->connection()))->findOneFor('notice', 'alice', 3);

		$this->assertSame(4, $ack->getId());
		$this->assertSame(3, $ack->getContentVersion());
		$this->assertContains('qb1.setMaxResults(1)', $found->calls);

		$absent = new QueryRecorder(test: $this);
		$this->assertNull((new AcknowledgementMapper($absent->connection()))->findOneFor('notice', 'alice', 3));
	}//end testFindOneForAnswersTheFirstRowOrNull()

	/**
	 * An announcement version's acknowledgements are oldest first; a user's are newest version first.
	 *
	 * @return void
	 */
	public function testTheListingsAreOrdered(): void {
		$recorder = new QueryRecorder(test: $this);
		$mapper = new AcknowledgementMapper($recorder->connection());

		$mapper->findByAnnouncement('notice', 3);
		$this->assertContains('qb1.where("eq(\"announcement_key\", \":p1\", null)")', $recorder->calls);
		$this->assertContains('qb1.andWhere("eq(\"content_version\", \":p2\", null)")', $recorder->calls);
		$this->assertContains('qb1.orderBy("acknowledged_at", "ASC")', $recorder->calls);
		$this->assertContains('qb1.addOrderBy("id", "ASC")', $recorder->calls);

		$mapper->findByUserForAnnouncement('notice', 'alice');
		$this->assertContains('qb2.andWhere("eq(\"user_id\", \":p4\", null)")', $recorder->calls);
		$this->assertContains('qb2.orderBy("content_version", "DESC")', $recorder->calls);
	}//end testTheListingsAreOrdered()

	/**
	 * Recording inserts an acknowledgement stamped with the acknowledgement time.
	 *
	 * @return void
	 */
	public function testRecordInsertsAStampedAcknowledgement(): void {
		$recorder = new QueryRecorder(test: $this);

		$ack = (new AcknowledgementMapper($recorder->connection()))->record('notice', 'alice', 3);

		$this->assertSame('notice', $ack->getAnnouncementKey());
		$this->assertSame('alice', $ack->getUserId());
		$this->assertSame(3, $ack->getContentVersion());
		$this->assertInstanceOf(DateTimeInterface::class, $ack->getAcknowledgedAt());
		$this->assertSame(1, $ack->getId());
		$this->assertContains('qb1.insert("launchpad_acknowledgements")', $recorder->calls);
	}//end testRecordInsertsAStampedAcknowledgement()
}//end class
