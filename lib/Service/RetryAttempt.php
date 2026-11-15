<?php

/**
 * One delivery attempt of a scheduled export, as the retry handler weighs it.
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
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-006-retry-with-exponential-backoff-and-dead-letter
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTimeInterface;

/**
 * Whether an attempt delivered, when, under which policy, and why it failed.
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-006-retry-with-exponential-backoff-and-dead-letter
 */
final class RetryAttempt {

	/**
	 * Constructor.
	 *
	 * @param bool                           $succeeded Whether this attempt delivered.
	 * @param DateTimeInterface              $now       The moment of the attempt.
	 * @param array<string, mixed>           $policy    A policy from `RetryHandler::policy()`.
	 * @param string|null                    $error     Why the attempt failed.
	 * @param (callable(int, int): int)|null $random    The jitter source.
	 *
	 * @return void
	 */
	public function __construct(
		public readonly bool $succeeded,
		public readonly DateTimeInterface $now,
		public readonly array $policy,
		public readonly ?string $error = null,
		public readonly mixed $random = null,
	) {
	}//end __construct()
}//end class
