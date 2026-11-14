<?php

/**
 * RetryHandlerTest
 *
 * The retry policy of scheduled-export deliveries (REQ-SCH-006): defaults
 * and overrides, doubling backoff with a cap and optional jitter, the step
 * to failed-permanent after the last attempt, a manual retry, and the run
 * roll-up.
 *
 * @category  Test
 * @package   Unit\Service
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

use DateTimeImmutable;
use OCA\LaunchPad\Service\RetryHandler;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\RetryHandler
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-006-retry-with-exponential-backoff-and-dead-letter
 */
class RetryHandlerTest extends TestCase {

	private const NO_JITTER = ['maxAttempts' => 5, 'initialBackoffSeconds' => 60, 'maxBackoffSeconds' => 300, 'jitter' => false];

	/**
	 * A recipient without an override gets the default; valid override keys win, invalid ones are ignored.
	 *
	 * @return void
	 */
	public function testPolicyMergesTheOverrideOverTheDefault(): void {
		$handler = new RetryHandler();
		$this->assertSame(['maxAttempts' => 5, 'initialBackoffSeconds' => 60, 'maxBackoffSeconds' => 3600, 'jitter' => true], $handler->policy(null));
		$this->assertSame(
			['maxAttempts' => 3, 'initialBackoffSeconds' => 60, 'maxBackoffSeconds' => 3600, 'jitter' => false],
			$handler->policy(['maxAttempts' => 3, 'initialBackoffSeconds' => 0, 'maxBackoffSeconds' => '10', 'jitter' => false])
		);
		// A cap below the first wait is raised to it.
		$this->assertSame(120, $handler->policy(['initialBackoffSeconds' => 120, 'maxBackoffSeconds' => 30])['maxBackoffSeconds']);
	}//end testPolicyMergesTheOverrideOverTheDefault()

	/**
	 * The first retry waits the initial backoff, each further one doubles, up to the cap.
	 *
	 * @return void
	 */
	public function testBackoffDoublesUpToTheCap(): void {
		$handler = new RetryHandler();
		$waits = array_map(static fn (int $n): int => $handler->backoffSeconds($n, self::NO_JITTER), [1, 2, 3, 4, 5, 30]);
		$this->assertSame([60, 120, 240, 300, 300, 300], $waits);
	}//end testBackoffDoublesUpToTheCap()

	/**
	 * With jitter the wait is drawn between half and all of the backoff.
	 *
	 * @return void
	 */
	public function testJitterDrawsBetweenHalfAndTheFullWait(): void {
		$handler = new RetryHandler();
		$asked   = [];
		$random  = static function (int $min, int $max) use (&$asked): int {
			$asked[] = [$min, $max];
			return $min;
		};
		$policy = ['jitter' => true] + self::NO_JITTER;

		$this->assertSame(60, $handler->backoffSeconds(2, $policy, $random));
		$this->assertSame([[60, 120]], $asked);

		for ($i = 0; $i < 50; $i++) {
			$wait = $handler->backoffSeconds(3, $policy);
			$this->assertGreaterThanOrEqual(120, $wait);
			$this->assertLessThanOrEqual(240, $wait);
		}
	}//end testJitterDrawsBetweenHalfAndTheFullWait()

	/**
	 * A 503 on the first attempt schedules the retry after the initial backoff.
	 *
	 * @return void
	 */
	public function testAFailedAttemptSchedulesTheNextOne(): void {
		$now      = new DateTimeImmutable('2026-11-16T09:00:00+01:00');
		$delivery = (new RetryHandler())->afterAttempt(['recipientId' => 'r1', 'channel' => 'webhook'], false, self::NO_JITTER, $now, 'HTTP 503');

		$this->assertSame(
			['recipientId' => 'r1', 'channel' => 'webhook', 'attemptCount' => 1, 'error' => 'HTTP 503', 'status' => 'retrying', 'nextAttemptAt' => '2026-11-16T09:01:00+01:00'],
			$delivery
		);
	}//end testAFailedAttemptSchedulesTheNextOne()

	/**
	 * After the last allowed attempt the delivery fails for good; a manual retry rearms it.
	 *
	 * @return void
	 */
	public function testTheLastFailedAttemptIsPermanentAndAManualRetryRearmsIt(): void {
		$handler  = new RetryHandler();
		$now      = new DateTimeImmutable('2026-11-16T09:00:00+01:00');
		$policy   = ['maxAttempts' => 3] + self::NO_JITTER;
		$delivery = ['recipientId' => 'r1'];
		for ($i = 0; $i < 3; $i++) {
			$delivery = $handler->afterAttempt($delivery, false, $policy, $now, 'HTTP 503');
		}

		$this->assertSame(3, $delivery['attemptCount']);
		$this->assertSame('failed-permanent', $delivery['status']);
		$this->assertNull($delivery['nextAttemptAt']);
		$this->assertFalse($handler->isDue($delivery, $now));

		$rearmed = $handler->rearm($delivery, $now);
		$this->assertEquals(['status' => 'retrying', 'attemptCount' => 0, 'nextAttemptAt' => '2026-11-16T09:00:00+01:00'], array_intersect_key($rearmed, array_flip(['status', 'attemptCount', 'nextAttemptAt'])));
		$this->assertTrue($handler->isDue($rearmed, $now));
		$this->assertSame(['status' => 'success'], $handler->rearm(['status' => 'success'], $now));
	}//end testTheLastFailedAttemptIsPermanentAndAManualRetryRearmsIt()

	/**
	 * Succeeding on the third attempt counts three attempts and rolls the run up to success.
	 *
	 * @return void
	 */
	public function testRecoveryOnTheThirdAttemptIsASuccess(): void {
		$handler  = new RetryHandler();
		$now      = new DateTimeImmutable('2026-11-16T09:00:00+01:00');
		$delivery = $handler->afterAttempt(['recipientId' => 'r1'], false, self::NO_JITTER, $now, 'HTTP 503');
		$this->assertFalse($handler->isDue($delivery, $now));
		$this->assertTrue($handler->isDue($delivery, $now->modify('+60 seconds')));

		$delivery = $handler->afterAttempt($delivery, false, self::NO_JITTER, $now->modify('+1 minute'), 'HTTP 503');
		$delivery = $handler->afterAttempt($delivery, true, self::NO_JITTER, $now->modify('+3 minutes'));

		$this->assertSame(3, $delivery['attemptCount']);
		$this->assertSame('success', $delivery['status']);
		$this->assertSame('2026-11-16T09:03:00+01:00', $delivery['deliveredAt']);
		$this->assertNull($delivery['error']);
		$this->assertSame('success', $handler->rollUp([$delivery, ['status' => 'success']]));
	}//end testRecoveryOnTheThirdAttemptIsASuccess()

	/**
	 * The run status follows its deliveries.
	 *
	 * @return void
	 */
	public function testRollUp(): void {
		$handler = new RetryHandler();
		$this->assertSame('running', $handler->rollUp([['status' => 'success'], ['status' => 'retrying']]));
		$this->assertSame('running', $handler->rollUp([['status' => 'success'], []]));
		$this->assertSame('partial', $handler->rollUp([['status' => 'success'], ['status' => 'failed-permanent']]));
		$this->assertSame('failed', $handler->rollUp([['status' => 'failed-permanent']]));
		$this->assertSame('failed', $handler->rollUp([]));
	}//end testRollUp()
}//end class
