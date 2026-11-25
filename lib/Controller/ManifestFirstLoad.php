<?php

/**
 * ManifestFirstLoad
 *
 * The kept runtime manifest the workspace page carries in its initial state.
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
 * @spec openspec/changes/manifest-cached-per-user/tasks.md#task-2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

/**
 * The manifest `GET /api/manifest` kept for the caller, when one is kept.
 * The page never builds one itself: building reads OpenRegister, which is
 * what the cache spares the page.
 *
 * @spec openspec/changes/manifest-cached-per-user/tasks.md#task-2
 */
class ManifestFirstLoad {
	/**
	 * Constructor.
	 *
	 * @param ManifestCache     $cache The kept manifests.
	 * @param RequestActor|null $actor The action matrix check; without it nothing is given.
	 */
	public function __construct(
		private readonly ManifestCache $cache,
		private readonly ?RequestActor $actor=null,
	) {
	}//end __construct()

	/**
	 * The kept manifest of the caller, or null when none is kept, when the
	 * caller may not perform `manifest.index`, or without the action check.
	 *
	 * @param string $userId The caller's uid.
	 *
	 * @return array<string, mixed>|null The manifest, or null.
	 *
	 * @spec openspec/changes/manifest-cached-per-user/tasks.md#task-2
	 */
	public function firstLoad(string $userId): ?array {
		if ($userId === '' || $this->actor === null) {
			return null;
		}

		if ($this->actor->refusal(userId: $userId, action: 'manifest.index') !== null) {
			return null;
		}

		return $this->cache->get(userId: $userId);
	}//end firstLoad()
}//end class
