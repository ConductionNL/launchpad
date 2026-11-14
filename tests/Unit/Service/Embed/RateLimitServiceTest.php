<?php

/**
 * RateLimitService unit tests (REQ-EMB-007).
 *
 * @category  Test
 * @package   Unit\Service\Embed
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md
 */

declare(strict_types=1);

namespace Unit\Service\Embed;

use OCA\LaunchPad\Service\Embed\RateLimitService;
use OCP\ICache;
use OCP\ICacheFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The token bucket.
 */
#[CoversClass(RateLimitService::class)]
class RateLimitServiceTest extends TestCase {

	/**
	 * The cache contents.
	 *
	 * @var array<string, mixed>
	 */
	private array $store = [];

	/**
	 * The service over an in-memory cache.
	 *
	 * @return RateLimitService
	 */
	private function service(): RateLimitService {
		$cache = $this->createMock(ICache::class);
		$cache->method('get')->willReturnCallback(fn (string $key): mixed => ($this->store[$key] ?? null));
		$cache->method('set')->willReturnCallback(function (string $key, mixed $value, int $ttl): bool {
			self::assertSame(3600, $ttl);
			$this->store[$key] = $value;
			return true;
		});
		$factory = $this->createMock(ICacheFactory::class);
		$factory->expects(self::once())->method('createDistributed')->with('launchpad-embed-ratelimit')->willReturn($cache);

		return new RateLimitService($factory);
	}//end service()

	/**
	 * A full bucket admits the burst, then refuses with how long to wait.
	 *
	 * @return void
	 */
	public function testTheBurstIsAdmittedAndThenRefused(): void {
		$service = $this->service();
		$policy = ['requestsPerMinute' => 60, 'burstSize' => 10];

		for ($i = 1; $i <= 10; $i++) {
			$verdict = $service->checkRateLimit('token-a', $policy, 1000.0);
			self::assertTrue($verdict['allowed'], 'request ' . $i);
			self::assertSame(10 - $i, $verdict['remaining']);
		}

		$refused = $service->checkRateLimit('token-a', $policy, 1000.0);
		self::assertSame(['allowed' => false, 'limit' => 60, 'remaining' => 0, 'retryAfter' => 1, 'resetAt' => 1010], $refused);
		self::assertTrue($service->checkRateLimit('token-b', $policy, 1000.0)['allowed'], 'buckets are per token');
	}//end testTheBurstIsAdmittedAndThenRefused()

	/**
	 * The bucket refills at the per-minute rate and never above the burst.
	 *
	 * @return void
	 */
	public function testTheBucketRefills(): void {
		$service = $this->service();
		$policy = ['requestsPerMinute' => 60, 'burstSize' => 10];
		for ($i = 1; $i <= 8; $i++) {
			$service->checkRateLimit('token-a', $policy, 0.0);
		}

		self::assertSame(2.0, $service->refillBucket('token-a', 10.0, 1.0, 0.0));
		self::assertSame(7.0, $service->refillBucket('token-a', 10.0, 1.0, 5.0));
		self::assertSame(10.0, $service->refillBucket('token-a', 10.0, 1.0, 30.0), 'capped at the burst size');
		self::assertSame(10.0, $service->refillBucket('new-token', 10.0, 1.0, 30.0), 'a new bucket starts full');
		self::assertSame(9, $service->checkRateLimit('token-a', $policy, 30.0)['remaining']);
	}//end testTheBucketRefills()

	/**
	 * Without a usable policy the org default applies.
	 *
	 * @return void
	 */
	public function testTheOrgDefaultApplies(): void {
		$service = $this->service();

		self::assertSame(600, $service->checkRateLimit('token-a', null, 0.0)['limit']);
		self::assertSame(58, $service->checkRateLimit('token-a', ['requestsPerMinute' => 0, 'burstSize' => 5], 0.0)['remaining']);
	}//end testTheOrgDefaultApplies()

	/**
	 * The 429 body says the limit, the wait and when the bucket is full again.
	 *
	 * @return void
	 */
	public function testTheRefusalBody(): void {
		self::assertSame(
			[
				'error' => 'rate_limit_exceeded',
				'message' => 'Rate limit exceeded. The token allows 60 requests per minute. Try again in 45 seconds.',
				'retryAfter' => 45,
				'resetAt' => '2026-05-22T14:31:00Z',
			],
			$this->service()->refusalBody(['limit' => 60, 'retryAfter' => 45, 'resetAt' => 1779460260])
		);
	}//end testTheRefusalBody()

}//end class
