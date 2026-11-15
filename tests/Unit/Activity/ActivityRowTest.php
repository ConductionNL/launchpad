<?php

/**
 * ActivityRow test
 *
 * Pins how one activity is addressed to a recipient: the `self` flag
 * follows the recipient, every other field and parameter is carried over,
 * and the original row is left untouched.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Activity
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Activity;

use OCA\LaunchPad\Activity\ActivityRow;
use OCA\LaunchPad\Activity\ActivitySubject;
use OCA\LaunchPad\Activity\Extension;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ActivityRow::forRecipient().
 *
 * @covers \OCA\LaunchPad\Activity\ActivityRow
 * @uses   \OCA\LaunchPad\Activity\ActivitySubject
 */
class ActivityRowTest extends TestCase {

	/**
	 * Build a row with the given extra parameters.
	 *
	 * @param array<string, mixed> $extraParams The extra subject parameters.
	 *
	 * @return ActivityRow
	 */
	private function row(array $extraParams=[]): ActivityRow {
		return new ActivityRow(
			type: Extension::EVENT_SHARED,
			actorUserId: 'alice',
			dashboard: new ActivitySubject(uuid: 'uuid-1', name: 'Team', link: 'https://nc.example/d/1'),
			extraParams: $extraParams
		);
	}//end row()

	/**
	 * Addressed to the actor, the row is a first-person line.
	 *
	 * @return void
	 */
	public function testTheActorGetsTheSelfVariant(): void {
		$row = $this->row(extraParams: ['role' => 'edit']);

		$addressed = $row->forRecipient(recipientUserId: 'alice');

		$this->assertSame(['role' => 'edit', 'self' => true], $addressed->extraParams);
	}//end testTheActorGetsTheSelfVariant()

	/**
	 * Addressed to anyone else, the row is a third-person line.
	 *
	 * @return void
	 */
	public function testAnotherUserGetsTheOtherVariant(): void {
		$row = $this->row(extraParams: ['recipient' => 'bob']);

		$addressed = $row->forRecipient(recipientUserId: 'bob');

		$this->assertSame(['recipient' => 'bob', 'self' => false], $addressed->extraParams);
	}//end testAnotherUserGetsTheOtherVariant()

	/**
	 * A `self` value the caller passed in is replaced by the recipient's,
	 * in both directions, keeping its position among the parameters.
	 *
	 * @return void
	 */
	public function testACallerSuppliedSelfIsOverwritten(): void {
		$claimsSelf  = $this->row(extraParams: ['self' => true, 'message' => 'hi']);
		$claimsOther = $this->row(extraParams: ['self' => 'x', 'message' => 'hi']);

		$this->assertSame(['self' => false, 'message' => 'hi'], $claimsSelf->forRecipient(recipientUserId: 'bob')->extraParams);
		$this->assertSame(['self' => true, 'message' => 'hi'], $claimsOther->forRecipient(recipientUserId: 'alice')->extraParams);
	}//end testACallerSuppliedSelfIsOverwritten()

	/**
	 * The recipient match is exact: a differently-cased user id is someone else.
	 *
	 * @return void
	 */
	public function testTheRecipientMatchIsCaseSensitive(): void {
		$addressed = $this->row()->forRecipient(recipientUserId: 'Alice');

		$this->assertFalse($addressed->extraParams['self']);
	}//end testTheRecipientMatchIsCaseSensitive()

	/**
	 * Addressing returns a new row with the same type, actor and dashboard,
	 * and leaves the original row's parameters unchanged.
	 *
	 * @return void
	 */
	public function testAddressingKeepsTheRowAndLeavesTheOriginalAlone(): void {
		$row = $this->row(extraParams: ['target' => 'carol']);

		$addressed = $row->forRecipient(recipientUserId: 'alice');

		$this->assertNotSame($row, $addressed);
		$this->assertSame(Extension::EVENT_SHARED, $addressed->type);
		$this->assertSame('alice', $addressed->actorUserId);
		$this->assertSame($row->dashboard, $addressed->dashboard);
		$this->assertSame(['target' => 'carol'], $row->extraParams);
	}//end testAddressingKeepsTheRowAndLeavesTheOriginalAlone()

	/**
	 * Addressing an already addressed row to someone else flips the flag back.
	 *
	 * @return void
	 */
	public function testReaddressingFollowsTheLastRecipient(): void {
		$addressed = $this->row()->forRecipient(recipientUserId: 'alice')->forRecipient(recipientUserId: 'bob');

		$this->assertSame(['self' => false], $addressed->extraParams);
	}//end testReaddressingFollowsTheLastRecipient()

	/**
	 * A row built without extra parameters has none until it is addressed.
	 *
	 * @return void
	 */
	public function testARowStartsWithoutParameters(): void {
		$row = new ActivityRow(
			type: Extension::EVENT_CREATED,
			actorUserId: 'alice',
			dashboard: new ActivitySubject(uuid: 'uuid-2', name: 'Solo')
		);

		$this->assertSame([], $row->extraParams);
		$this->assertSame('', $row->dashboard->link);
		$this->assertSame(['self' => true], $row->forRecipient(recipientUserId: 'alice')->extraParams);
	}//end testARowStartsWithoutParameters()
}//end class
