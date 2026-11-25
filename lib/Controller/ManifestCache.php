<?php

/**
 * ManifestCache
 *
 * The runtime manifest per user and app version, kept for a minute.
 *
 * @category  Controller
 * @package   OCA\LaunchPad\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/manifest-cached-per-user/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\AppInfo\Application;
use OCP\App\IAppManager;
use OCP\ICache;
use OCP\ICacheFactory;

/**
 * The manifest `GET /api/manifest` built for a user, kept per user and app
 * version for {@see self::TTL} seconds, so a reload within that minute does
 * not read OpenRegister again and the page can carry it.
 *
 * @spec openspec/changes/manifest-cached-per-user/tasks.md#task-1
 */
class ManifestCache {
	/**
	 * How long a built manifest is kept, in seconds. A dashboard shared with
	 * the user in OpenRegister reaches their menu at most this much later.
	 *
	 * @var int
	 */
	public const TTL = 60;

	/**
	 * The distributed cache, or null when the server has none.
	 *
	 * @var ICache|null
	 */
	private readonly ?ICache $cache;

	/**
	 * Constructor.
	 *
	 * @param ICacheFactory $cacheFactory The Nextcloud cache factory.
	 * @param IAppManager   $appManager   The installed app version, part of the key.
	 */
	public function __construct(
		ICacheFactory $cacheFactory,
		private readonly IAppManager $appManager,
	) {
		$cache = null;
		if ($cacheFactory->isAvailable() === true) {
			$cache = $cacheFactory->createDistributed('launchpad_manifest');
		}

		$this->cache = $cache;
	}//end __construct()

	/**
	 * The kept manifest of a user, or null when none is kept.
	 *
	 * @param string $userId The user.
	 *
	 * @return array<string, mixed>|null The manifest, or null.
	 *
	 * @spec openspec/changes/manifest-cached-per-user/tasks.md#task-1
	 */
	public function get(string $userId): ?array {
		if ($this->cache === null || $userId === '') {
			return null;
		}

		$kept = $this->cache->get($this->key(userId: $userId));
		if (is_array($kept) === false || isset($kept['$schema']) === false) {
			return null;
		}

		return $kept;
	}//end get()

	/**
	 * Keep the manifest built for a user.
	 *
	 * @param string               $userId   The user.
	 * @param array<string, mixed> $manifest The manifest.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/manifest-cached-per-user/tasks.md#task-1
	 */
	public function set(string $userId, array $manifest): void {
		if ($this->cache === null || $userId === '') {
			return;
		}

		$this->cache->set($this->key(userId: $userId), $manifest, self::TTL);
	}//end set()

	/**
	 * The cache key: user and installed app version.
	 *
	 * @param string $userId The user.
	 *
	 * @return string The key.
	 */
	private function key(string $userId): string {
		return $userId.':'.$this->appManager->getAppVersion(Application::APP_ID);
	}//end key()
}//end class
