<?php

/**
 * BulkOutcome
 *
 * The per-dashboard outcome entries of one bulk operation and their sum
 * (REQ-BULK-001..004).
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

use Throwable;

/**
 * Builds and sums the `{<count>, skipped, error}` outcome of each dashboard
 * of one bulk operation.
 *
 * @spec openspec/specs/dashboard-bulk-operations/spec.md
 */
final class BulkOutcome {
	/**
	 * Constructor.
	 *
	 * @param string $countKey The outcome's count key (`deleted`, `moved`, `updated`).
	 */
	public function __construct(
		private readonly string $countKey,
	) {
	}//end __construct()

	/**
	 * Sum the per-dashboard outcomes of a batch.
	 *
	 * @param array<int, array<string, mixed>> $outcomes The outcomes, in batch order.
	 *
	 * @return array{0: int, 1: int, 2: array<int, array<string, mixed>>} Count, skipped and errors.
	 *
	 * @spec openspec/specs/dashboard-bulk-operations/spec.md
	 */
	public function tally(array $outcomes): array {
		$count = 0;
		$skipped = 0;
		$errors = [];
		foreach ($outcomes as $outcome) {
			$count += $outcome[$this->countKey];
			$skipped += $outcome['skipped'];

			if ($outcome['error'] !== null) {
				$errors[] = $outcome['error'];
			}
		}

		return [$count, $skipped, $errors];
	}//end tally()

	/**
	 * An outcome that counts dashboards.
	 *
	 * @param int    $count    How many dashboards it counts.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/specs/dashboard-bulk-operations/spec.md
	 */
	public function counted(int $count): array {
		return [
			$this->countKey => $count,
			'skipped' => 0,
			'error' => null,
		];
	}//end counted()

	/**
	 * An outcome that skips the dashboard with an error entry.
	 *
	 * @param array<string, mixed> $error    The per-uuid error.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/specs/dashboard-bulk-operations/spec.md
	 */
	public function skipped(array $error): array {
		return [
			$this->countKey => 0,
			'skipped' => 1,
			'error' => $error,
		];
	}//end skipped()

	/**
	 * An outcome that neither counts nor skips the dashboard, with an error entry.
	 *
	 * @param array<string, mixed> $error    The per-uuid error.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/specs/dashboard-bulk-operations/spec.md
	 */
	public function failed(array $error): array {
		return [
			$this->countKey => 0,
			'skipped' => 0,
			'error' => $error,
		];
	}//end failed()

	/**
	 * A per-uuid error entry.
	 *
	 * @param string $uuid   The dashboard UUID.
	 * @param string $reason The reason code.
	 *
	 * @return array{uuid: string, reason: string}
	 *
	 * @spec openspec/specs/dashboard-bulk-operations/spec.md
	 */
	public function uuidError(string $uuid, string $reason): array {
		return [
			'uuid' => $uuid,
			'reason' => $reason,
		];
	}//end uuidError()

	/**
	 * The per-uuid error of a failed write.
	 *
	 * @param string    $uuid    The dashboard UUID.
	 * @param Throwable $failure The failure.
	 *
	 * @return array{uuid: string, reason: string, detail: string}
	 *
	 * @spec openspec/specs/dashboard-bulk-operations/spec.md
	 */
	public function transactionError(string $uuid, Throwable $failure): array {
		return [
			'uuid' => $uuid,
			'reason' => BulkOperationService::REASON_TRANSACTION_FAILED,
			'detail' => $failure->getMessage(),
		];
	}//end transactionError()
}//end class
