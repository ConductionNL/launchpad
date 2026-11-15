<?php

/**
 * ReadsJsonCache
 *
 * Reads JSON-encoded entries from a Nextcloud cache.
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
 * @spec openspec/specs/live-data-tile-widget/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\ICache;

/**
 * Decodes the JSON array stored under one cache key, for the services that
 * cache widget readings.
 *
 * @spec openspec/specs/live-data-tile-widget/spec.md
 */
trait ReadsJsonCache {
	/**
	 * Read and JSON-decode a cache entry.
	 *
	 * @param ICache|null $cache    The cache, or null when the cache subsystem is unavailable.
	 * @param string      $cacheKey The cache key.
	 *
	 * @return array<string,mixed>|null The decoded array, or null on a miss or a corrupt entry.
	 *
	 * @spec openspec/specs/live-data-tile-widget/spec.md
	 */
	private function readCache(?ICache $cache, string $cacheKey): ?array {
		if ($cache === null) {
			return null;
		}

		$raw = $cache->get(key: $cacheKey);
		if (is_string(value: $raw) === false) {
			return null;
		}

		$decoded = json_decode(json: $raw, associative: true);
		if (is_array(value: $decoded) === true) {
			return $decoded;
		}

		return null;
	}//end readCache()
}//end trait
