<?php

/**
 * RetryHandler
 *
 * The retry policy of scheduled-export deliveries (REQ-SCH-006): one
 * delivery is one recipient on one channel, and each retries on its own.
 * A failed attempt waits `initialBackoffSeconds`, doubling on every further
 * failure up to `maxBackoffSeconds`, optionally with jitter; after
 * `maxAttempts` the delivery is `failed-permanent`. A run rolls up to
 * `success` only when every delivery succeeded, however many attempts that
 * took.
 *
 * This class decides; it does not send. The dispatcher records each
 * attempt's outcome through `afterAttempt()` and the runner picks up
 * deliveries whose `nextAttemptAt` has passed.
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
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Backoff, attempt bookkeeping and the run roll-up for export deliveries.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-006-retry-with-exponential-backoff-and-dead-letter
 */
class RetryHandler {

	/**
	 * The policy a recipient without its own override gets.
	 *
	 * @var array{maxAttempts: int, initialBackoffSeconds: int, maxBackoffSeconds: int, jitter: bool}
	 */
	public const DEFAULT_POLICY = [
		'maxAttempts' => 5,
		'initialBackoffSeconds' => 60,
		'maxBackoffSeconds' => 3600,
		'jitter' => true,
	];

	public const STATUS_PENDING = 'pending';

	public const STATUS_RETRYING = 'retrying';

	public const STATUS_SUCCESS = 'success';

	public const STATUS_FAILED_PERMANENT = 'failed-permanent';

	/**
	 * A recipient's policy: its override on top of the default. Values of the
	 * wrong type or out of range keep the default.
	 *
	 * @param array<string, mixed>|null $override The recipient's `retryPolicy`.
	 *
	 * @return array{maxAttempts: int, initialBackoffSeconds: int, maxBackoffSeconds: int, jitter: bool}
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-006-retry-with-exponential-backoff-and-dead-letter
	 */
	public function policy(?array $override): array {
		$policy = self::DEFAULT_POLICY;
		foreach (['maxAttempts', 'initialBackoffSeconds', 'maxBackoffSeconds'] as $key) {
			if (is_int($override[$key] ?? null) === true && $override[$key] >= 1) {
				$policy[$key] = $override[$key];
			}
		}

		if (is_bool($override['jitter'] ?? null) === true) {
			$policy['jitter'] = $override['jitter'];
		}

		$policy['maxBackoffSeconds'] = max($policy['maxBackoffSeconds'], $policy['initialBackoffSeconds']);
		return $policy;
	}//end policy()

	/**
	 * How long to wait after a delivery's n-th failed attempt: the initial
	 * backoff doubled per earlier failure, capped at the maximum. With
	 * jitter, a random wait between half of that and all of it.
	 *
	 * @param int                   $failedAttempts The attempts that failed so far (1 after the first).
	 * @param array<string, mixed>  $policy         A policy from `policy()`.
	 * @param (callable(int, int): int)|null $random Picks an integer in [min, max]; `random_int` by default.
	 *
	 * @return int Seconds.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-006-retry-with-exponential-backoff-and-dead-letter
	 */
	public function backoffSeconds(int $failedAttempts, array $policy, ?callable $random = null): int {
		$initial = (int)$policy['initialBackoffSeconds'];
		$cap     = (int)$policy['maxBackoffSeconds'];
		$delay   = $initial;
		for ($i = 1; $i < max(1, $failedAttempts) && $delay < $cap; $i++) {
			$delay *= 2;
		}

		$delay = min($delay, $cap);
		if ($policy['jitter'] !== true) {
			return $delay;
		}

		$random = $random ?? 'random_int';
		return (int)$random(intdiv($delay, 2), $delay);
	}//end backoffSeconds()

	/**
	 * A delivery after one more attempt: counted, and either delivered,
	 * waiting for its next attempt, or failed for good once the policy's
	 * attempts are spent.
	 *
	 * @param array<string, mixed> $delivery The delivery as the run-row holds it.
	 * @param RetryAttempt         $attempt  Whether it delivered, when, under which policy, and why it failed.
	 *
	 * @return array<string, mixed> The updated delivery.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-006-retry-with-exponential-backoff-and-dead-letter
	 */
	public function afterAttempt(array $delivery, RetryAttempt $attempt): array {
		$attempts = (int)($delivery['attemptCount'] ?? 0) + 1;
		$delivery['attemptCount'] = $attempts;
		$at = DateTimeImmutable::createFromInterface($attempt->now);

		if ($attempt->succeeded === true) {
			$delivery['status']        = self::STATUS_SUCCESS;
			$delivery['deliveredAt']   = $at->format(DateTimeInterface::ATOM);
			$delivery['nextAttemptAt'] = null;
			$delivery['error']         = null;
			return $delivery;
		}

		$delivery['error'] = $attempt->error;
		if ($attempts >= (int)$attempt->policy['maxAttempts']) {
			$delivery['status']        = self::STATUS_FAILED_PERMANENT;
			$delivery['nextAttemptAt'] = null;
			return $delivery;
		}

		$wait = $this->backoffSeconds(failedAttempts: $attempts, policy: $attempt->policy, random: $attempt->random);
		$delivery['status']        = self::STATUS_RETRYING;
		$delivery['nextAttemptAt'] = $at->modify('+' . $wait . ' seconds')->format(DateTimeInterface::ATOM);
		return $delivery;
	}//end afterAttempt()

	/**
	 * Whether a delivery is waiting for an attempt that is due now.
	 *
	 * @param array<string, mixed> $delivery The delivery.
	 * @param DateTimeInterface    $now      The moment asked about.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-006-retry-with-exponential-backoff-and-dead-letter
	 */
	public function isDue(array $delivery, DateTimeInterface $now): bool {
		if (($delivery['status'] ?? null) !== self::STATUS_RETRYING) {
			return false;
		}

		$next = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, (string)($delivery['nextAttemptAt'] ?? ''));
		return $next !== false && $next <= $now;
	}//end isDue()

	/**
	 * A manual "Retry" of a delivery that failed for good: a fresh round of
	 * attempts under the same policy, due at once. Any other delivery is
	 * returned unchanged.
	 *
	 * @param array<string, mixed> $delivery The delivery.
	 * @param DateTimeInterface    $now      The moment of the retry.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-006-retry-with-exponential-backoff-and-dead-letter
	 */
	public function rearm(array $delivery, DateTimeInterface $now): array {
		if (($delivery['status'] ?? null) !== self::STATUS_FAILED_PERMANENT) {
			return $delivery;
		}

		$delivery['status']        = self::STATUS_RETRYING;
		$delivery['attemptCount']  = 0;
		$delivery['nextAttemptAt'] = DateTimeImmutable::createFromInterface($now)->format(DateTimeInterface::ATOM);
		return $delivery;
	}//end rearm()

	/**
	 * A run's status from its deliveries: `running` while any is pending or
	 * retrying, `success` when all succeeded, `failed` when none did, and
	 * `partial` otherwise. A run without deliveries failed.
	 *
	 * @param list<array<string, mixed>> $deliveries The run's deliveries.
	 *
	 * @return string `running`, `success`, `partial` or `failed`.
	 *
	 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-006-retry-with-exponential-backoff-and-dead-letter
	 */
	public function rollUp(array $deliveries): string {
		$statuses = array_map(static fn (array $d): string => (string)($d['status'] ?? self::STATUS_PENDING), $deliveries);
		if ($statuses === []) {
			return 'failed';
		}

		if (array_intersect($statuses, [self::STATUS_PENDING, self::STATUS_RETRYING]) !== []) {
			return 'running';
		}

		$succeeded = count(array_keys($statuses, self::STATUS_SUCCESS, true));
		if ($succeeded === count($statuses)) {
			return 'success';
		}

		if ($succeeded === 0) {
			return 'failed';
		}

		return 'partial';
	}//end rollUp()
}//end class
