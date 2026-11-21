<?php

/**
 * Contract of FeedRefreshService
 *
 * The public surface of FeedRefreshService that other components call, so they depend
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

/**
 * Contract of FeedRefreshService.
 *
 * @category Kernel
 * @package  OCA\LaunchPad\Kernel\Contracts
 * @author   Conduction Development Team <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * @spec exclude Structural contract, no functional requirement.
 */
interface FeedRefreshServiceInterface {
	/**
	 * Refresh all discovered feed URLs (or a single one when
	 * `$onlyUrl` is provided). Honours the per-tick wall-clock budget
	 * and the cursor-based batch state (REQ-FRJ-008).
	 *
	 * @param string|null $onlyUrl Optional single URL to refresh.
	 *
	 * @return array{processedCount: int, successCount: int, failureCount: int, durationMs: int}
	 *
	 * @spec openspec/specs/background-job-feed-refresh/spec.md
	 */
	public function refreshAll(?string $onlyUrl = null): array;
}//end interface
