<?php

/**
 * RateLimitService
 *
 * Token-bucket rate limiting per embed token (REQ-EMB-007). A bucket holds at
 * most `burstSize` requests and refills at `requestsPerMinute` / 60 per
 * second. A token without a policy gets the org default (600 per minute,
 * burst 60). The bucket state lives in the distributed cache with a TTL, so a
 * restart forgets it rather than locking anyone out.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service\Embed
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

namespace OCA\LaunchPad\Service\Embed;

use OCP\ICache;
use OCP\ICacheFactory;

/**
 * Admits or refuses one request against its token's bucket.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-007--rate-limiting-and-request-budgeting-per-token
 */
class RateLimitService {

	/**
	 * The org default when a token carries no policy.
	 *
	 * @var array{requestsPerMinute: int, burstSize: int}
	 */
	public const DEFAULT_POLICY = ['requestsPerMinute' => 600, 'burstSize' => 60];

	/**
	 * How long a bucket is kept after its last request, in seconds.
	 *
	 * @var int
	 */
	private const TTL = 3600;

	/**
	 * The bucket store.
	 *
	 * @var ICache
	 */
	private readonly ICache $cache;

	/**
	 * Constructor.
	 *
	 * @param ICacheFactory $cacheFactory Builds the distributed cache.
	 */
	public function __construct(ICacheFactory $cacheFactory) {
		$this->cache = $cacheFactory->createDistributed('launchpad-embed-ratelimit');
	}//end __construct()

	/**
	 * Take one request from the token's bucket.
	 *
	 * @param string                    $tokenId The token.
	 * @param array<string, mixed>|null $policy  The token's `rateLimitPolicy`, null for the default.
	 * @param float                     $now     The time, in seconds since the epoch.
	 *
	 * @return array{allowed: bool, limit: int, remaining: int, retryAfter: int, resetAt: int}
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-007--rate-limiting-and-request-budgeting-per-token
	 */
	public function checkRateLimit(string $tokenId, ?array $policy, float $now): array {
		$policy = $this->policy(policy: $policy);
		$capacity = (float)$policy['burstSize'];
		$rate = ($policy['requestsPerMinute'] / 60.0);

		$tokens = $this->refillBucket(tokenId: $tokenId, capacity: $capacity, rate: $rate, now: $now);
		$allowed = ($tokens >= 1.0);
		if ($allowed === true) {
			$tokens -= 1.0;
		}

		$this->cache->set($this->key(tokenId: $tokenId), ['tokens' => $tokens, 'at' => $now], self::TTL);

		$retryAfter = 0;
		if ($allowed === false) {
			$retryAfter = (int)ceil((1.0 - $tokens) / $rate);
		}

		return [
			'allowed' => $allowed,
			'limit' => $policy['requestsPerMinute'],
			'remaining' => (int)floor($tokens),
			'retryAfter' => $retryAfter,
			'resetAt' => (int)ceil($now + (($capacity - $tokens) / $rate)),
		];
	}//end checkRateLimit()

	/**
	 * The tokens in a bucket now: the stored count plus what refilled since,
	 * never more than the capacity. A new bucket starts full.
	 *
	 * @param string $tokenId  The token.
	 * @param float  $capacity The burst size.
	 * @param float  $rate     Requests per second.
	 * @param float  $now      The time.
	 *
	 * @return float
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-007--rate-limiting-and-request-budgeting-per-token
	 */
	public function refillBucket(string $tokenId, float $capacity, float $rate, float $now): float {
		$state = $this->cache->get($this->key(tokenId: $tokenId));
		if (is_array($state) === false || isset($state['tokens'], $state['at']) === false) {
			return $capacity;
		}

		$elapsed = max(0.0, ($now - (float)$state['at']));

		return min($capacity, ((float)$state['tokens'] + ($elapsed * $rate)));
	}//end refillBucket()

	/**
	 * The 429 body for a refused request.
	 *
	 * @param array{limit: int, retryAfter: int, resetAt: int} $verdict The verdict.
	 *
	 * @return array<string, mixed>
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-007--rate-limiting-and-request-budgeting-per-token
	 */
	public function refusalBody(array $verdict): array {
		return [
			'error' => 'rate_limit_exceeded',
			'message' => sprintf(
				'Rate limit exceeded. The token allows %d requests per minute. Try again in %d seconds.',
				$verdict['limit'],
				$verdict['retryAfter']
			),
			'retryAfter' => $verdict['retryAfter'],
			'resetAt' => gmdate('Y-m-d\TH:i:s\Z', $verdict['resetAt']),
		];
	}//end refusalBody()

	/**
	 * The policy in force: the token's, when both numbers are positive, else the default.
	 *
	 * @param array<string, mixed>|null $policy The token's policy.
	 *
	 * @return array{requestsPerMinute: int, burstSize: int}
	 */
	private function policy(?array $policy): array {
		$perMinute = (int)($policy['requestsPerMinute'] ?? 0);
		$burst = (int)($policy['burstSize'] ?? 0);
		if ($perMinute < 1 || $burst < 1) {
			return self::DEFAULT_POLICY;
		}

		return ['requestsPerMinute' => $perMinute, 'burstSize' => $burst];
	}//end policy()

	/**
	 * The cache key of a token's bucket.
	 *
	 * @param string $tokenId The token.
	 *
	 * @return string
	 */
	private function key(string $tokenId): string {
		return 'bucket-' . hash('sha256', $tokenId);
	}//end key()

}//end class
