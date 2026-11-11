<?php

/**
 * FeedRefreshRun
 *
 * One refresh of one feed URL: the URL, the cache row being refreshed,
 * when the refresh started and the timestamp it stamps on the row
 * (REQ-FRJ-004..006).
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

use DateTime;
use OCA\LaunchPad\Db\FeedCache;
use OCA\LaunchPad\Db\FeedCacheMapper;

/**
 * State shared by the steps of one FeedRefreshService::refreshFeed() call.
 * Only FeedRefreshService builds and reads it.
 *
 * @spec openspec/specs/background-job-feed-refresh/spec.md
 */
final class FeedRefreshRun {

	/**
	 * The cache row being refreshed.
	 *
	 * @var FeedCache
	 */
	public readonly FeedCache $row;

	/**
	 * The `lastFetchedAt` timestamp of this refresh, `Y-m-d H:i:s`.
	 *
	 * @var string
	 */
	public readonly string $now;

	/**
	 * Constructor
	 *
	 * @param string $feedUrl   The feed URL.
	 * @param int    $startedAt Refresh start time in epoch milliseconds.
	 */
	private function __construct(
		public readonly string $feedUrl,
		public readonly int $startedAt,
	) {
	}//end __construct()

	/**
	 * Start a refresh: note the start time, load or create the cache row,
	 * then take the fetch timestamp — in that order.
	 *
	 * @param FeedCacheMapper $mapper  The feed-cache mapper.
	 * @param string          $feedUrl The feed URL.
	 *
	 * @return self
	 *
	 * @spec openspec/specs/background-job-feed-refresh/spec.md
	 */
	public static function start(FeedCacheMapper $mapper, string $feedUrl): self {
		$run = new self(feedUrl: $feedUrl, startedAt: self::nowMs());
		$run->row = $mapper->upsertUrl(feedUrl: $feedUrl);
		$run->now = (new DateTime())->format(format: 'Y-m-d H:i:s');

		return $run;
	}//end start()

	/**
	 * The result envelope of this refresh.
	 *
	 * @param string $status    `ok`, `not-modified`, `skipped` or `failed`.
	 * @param int    $itemCount Number of items the row now holds.
	 *
	 * @return array{status: string, itemCount: int, durationMs: int}
	 *
	 * @spec openspec/specs/background-job-feed-refresh/spec.md
	 */
	public function result(string $status, int $itemCount): array {
		return [
			'status' => $status,
			'itemCount' => $itemCount,
			'durationMs' => (self::nowMs() - $this->startedAt),
		];
	}//end result()

	/**
	 * Number of items still cached on the row from the last successful fetch.
	 *
	 * @return int
	 *
	 * @spec openspec/specs/background-job-feed-refresh/spec.md
	 */
	public function cachedItemCount(): int {
		return count(value: $this->row->decodeItems());
	}//end cachedItemCount()

	/**
	 * Stamp this refresh's fetch timestamp on the row.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/background-job-feed-refresh/spec.md
	 */
	public function stampFetched(): void {
		$this->row->setLastFetchedAt($this->now);
	}//end stampFetched()

	/**
	 * Current time in epoch milliseconds.
	 *
	 * @return int
	 */
	private static function nowMs(): int {
		return (int)(microtime(as_float: true) * 1000);
	}//end nowMs()
}//end class
