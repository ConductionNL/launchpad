<?php

/**
 * DashboardImportRun
 *
 * The tally of one dashboard import batch.
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

use OCA\LaunchPad\Db\Dashboard;

/**
 * One import batch of ImportService: who imports, whether archive UUIDs
 * are kept, and the counts, errors and created dashboards so far. Built
 * by ImportService for one batch and never handed out.
 *
 * @spec openspec/specs/dashboard-export-import/spec.md
 */
final class DashboardImportRun {
	/**
	 * Dashboards imported so far.
	 *
	 * @var integer
	 */
	public int $imported = 0;

	/**
	 * Dashboards skipped so far.
	 *
	 * @var integer
	 */
	public int $skipped = 0;

	/**
	 * Errors of the skipped dashboards, in order.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $errors = [];

	/**
	 * The created dashboards: sourceUuid, uuid and id.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $created = [];

	/**
	 * Constructor
	 *
	 * @param string  $currentUserId The importing user.
	 * @param boolean $preserveUuids Whether archive UUIDs are kept.
	 */
	public function __construct(
		public readonly string $currentUserId,
		public readonly bool $preserveUuids,
	) {
	}//end __construct()

	/**
	 * Count one imported dashboard and list it under its archive UUID.
	 *
	 * @param string    $sourceUuid The UUID in the archive.
	 * @param Dashboard $persisted  The inserted dashboard.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-export-import/spec.md
	 */
	public function recordImported(string $sourceUuid, Dashboard $persisted): void {
		$this->imported++;
		$this->created[] = [
			'sourceUuid' => $sourceUuid,
			'uuid' => (string)$persisted->getUuid(),
			'id' => (int)$persisted->getId(),
		];
	}//end recordImported()

	/**
	 * Count one skipped dashboard with its error.
	 *
	 * @param array<string, mixed> $error The error entry.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-export-import/spec.md
	 */
	public function recordSkipped(array $error): void {
		$this->skipped++;
		$this->errors[] = $error;
	}//end recordSkipped()

	/**
	 * The batch result: counts, errors and created dashboards.
	 *
	 * @return array{importedDashboardCount: int, skippedDashboardCount: int,
	 *               errors: array<int, array<string, mixed>>,
	 *               dashboards: array<int, array<string, mixed>>}
	 *
	 * @spec openspec/specs/dashboard-export-import/spec.md
	 */
	public function summary(): array {
		return [
			'importedDashboardCount' => $this->imported,
			'skippedDashboardCount' => $this->skipped,
			'errors' => $this->errors,
			'dashboards' => $this->created,
		];
	}//end summary()
}//end class
