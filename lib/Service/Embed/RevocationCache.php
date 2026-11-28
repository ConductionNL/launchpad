<?php

/**
 * RevocationCache
 *
 * Whether an embed token is revoked, cached for at most 60 seconds in the
 * distributed cache every render worker shares (REQ-EMB-009). A revocation
 * is written to the cache at once, so every worker sees it on its next
 * request; a miss falls back to the stored token and is cached for the TTL,
 * which bounds how long any worker can keep accepting a revoked token. A
 * purge drops the entry so the next request reads the store.
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
 * The 60-second revocation cache of embed tokens.
 *
 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-009--token-revocation-takes-effect-within-60-seconds
 */
class RevocationCache {

	/**
	 * The longest a cached answer is trusted, in seconds.
	 *
	 * @var integer
	 */
	public const TTL = 60;

	/**
	 * The cached value of a revoked token.
	 *
	 * @var string
	 */
	private const REVOKED = 'revoked';

	/**
	 * The cached value of a token that is not revoked.
	 *
	 * @var string
	 */
	private const VALID = 'valid';

	/**
	 * The shared cache.
	 *
	 * @var ICache
	 */
	private ICache $cache;

	/**
	 * Constructor.
	 *
	 * @param ICacheFactory $cacheFactory Builds the distributed cache.
	 */
	public function __construct(ICacheFactory $cacheFactory) {
		$this->cache = $cacheFactory->createDistributed('launchpad-embed-revocation');
	}//end __construct()

	/**
	 * Whether a token is revoked: the cached answer, else the store's, cached.
	 *
	 * @param string   $tokenId The token id.
	 * @param callable $lookup  fn(): bool, whether the stored token is revoked.
	 *
	 * @return bool
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-009--token-revocation-takes-effect-within-60-seconds
	 */
	public function isRevoked(string $tokenId, callable $lookup): bool {
		$cached = $this->cache->get(self::key(tokenId: $tokenId));
		if ($cached === self::REVOKED) {
			return true;
		}

		if ($cached === self::VALID) {
			return false;
		}

		$revoked = ($lookup() === true);
		$value = self::VALID;
		if ($revoked === true) {
			$value = self::REVOKED;
		}

		$this->cache->set(self::key(tokenId: $tokenId), $value, self::TTL);

		return $revoked;
	}//end isRevoked()

	/**
	 * Record a revocation for every worker at once.
	 *
	 * @param string $tokenId The token id.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-009--token-revocation-takes-effect-within-60-seconds
	 */
	public function markRevoked(string $tokenId): void {
		$this->cache->set(self::key(tokenId: $tokenId), self::REVOKED, self::TTL);
	}//end markRevoked()

	/**
	 * Drop the cached answer, so the next request reads the store.
	 *
	 * @param string $tokenId The token id.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/embedded-analytics/specs/embedded-analytics/spec.md#requirement-req-emb-009--token-revocation-takes-effect-within-60-seconds
	 */
	public function purge(string $tokenId): void {
		$this->cache->remove(self::key(tokenId: $tokenId));
	}//end purge()

	/**
	 * The cache key of a token.
	 *
	 * @param string $tokenId The token id.
	 *
	 * @return string
	 */
	private static function key(string $tokenId): string {
		return 'token-' . hash('sha256', $tokenId);
	}//end key()
}//end class
