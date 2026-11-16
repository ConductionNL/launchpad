<?php

/**
 * Contract of OrphanedDataCleanupService
 *
 * The public surface of OrphanedDataCleanupService that other components call, so they depend
 * on this contract instead of the implementation.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Kernel\Contracts;

use OCA\LaunchPad\Db\CleanupResult;

/**
 * Contract of OrphanedDataCleanupService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface OrphanedDataCleanupServiceInterface {
	/**
	 * Scan one or more categories for orphaned rows.
	 *
	 * When `$categoryNames` is empty the full registered set is
	 * scanned. Categories whose `isAvailable()` returns `false` are
	 * recorded under `skipped` instead of contributing a count.
	 *
	 * The result is cached under {@see self::CACHE_KEY_SCAN} for
	 * {@see self::CACHE_TTL_SECONDS} seconds whenever the caller
	 * asks for the full set; partial scans bypass the cache to avoid
	 * polluting the full-set entry with category-filtered numbers.
	 *
	 * @param array<int, string> $categoryNames Names to scan, or [].
	 *
	 * @return CleanupResult The result.
	 *
	 * @spec openspec/specs/orphaned-data-cleanup/spec.md
	 */
	public function scan(array $categoryNames = []): CleanupResult;

	/**
	 * Purge orphaned rows in one or more categories.
	 *
	 * When `$categoryNames` is empty the full registered set is
	 * purged. Categories whose `isAvailable()` returns `false` are
	 * recorded under `skipped`.
	 *
	 * Dry-run mode wraps the entire walk in a single transaction and
	 * rolls it back at the end so individual category implementations
	 * can use one delete path for both modes (REQ-CLN-003).
	 *
	 * On a successful real purge the scan cache is invalidated
	 * (REQ-CLN-010) and exactly one Activity event is emitted
	 * (REQ-CLN-009).
	 *
	 * @param array<int, string> $categoryNames Names to purge, or [].
	 * @param bool $dryRun True for simulation.
	 * @param string|null $userId The actor for audit
	 *                            (null = system).
	 * @param string $source Origin label for the
	 *                       activity event ('cli',
	 *                       'api', 'job').
	 *
	 * @return CleanupResult The result.
	 *
	 * @spec openspec/specs/orphaned-data-cleanup/spec.md
	 */
	public function purge(
		array $categoryNames = [],
		bool $dryRun = false,
		?string $userId = null,
		string $source = 'api',
	): CleanupResult;

	/**
	 * Read the most recent cached scan result.
	 *
	 * Returns `null` when the cache backend has no value (expired,
	 * never written, or unavailable). The cache is constructed lazily
	 * via {@see ICacheFactory::createDistributed()} so installs
	 * without a memory cache silently fall through to fresh scans.
	 *
	 * @return CleanupResult|null The cached result or null.
	 *
	 * @spec openspec/specs/orphaned-data-cleanup/spec.md
	 */
	public function getCachedScanResult(): ?CleanupResult;
}//end interface
