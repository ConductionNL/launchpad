<?php

/**
 * ShellBootReads
 *
 * What the app shell reads on every page, carried in the workspace page's initial state.
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
 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

/**
 * The shell's (nextcloud-vue CnAppRoot) boot reads the workspace page carries:
 * the kept runtime manifest, the setup status and the walkthrough preferences.
 *
 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-2
 */
class ShellBootReads {
	/**
	 * Constructor.
	 *
	 * @param ManifestFirstLoad|null               $manifest    The kept runtime manifest.
	 * @param SetupStatusFirstLoad|null            $setupStatus The setup status.
	 * @param WalkthroughPreferencesFirstLoad|null $walkthrough The walkthrough preferences.
	 */
	public function __construct(
		public readonly ?ManifestFirstLoad $manifest=null,
		public readonly ?SetupStatusFirstLoad $setupStatus=null,
		public readonly ?WalkthroughPreferencesFirstLoad $walkthrough=null,
	) {
	}//end __construct()

	/**
	 * The reads for the caller, under the initial-state keys the shell reads:
	 * `runtimeManifest`, `setup_status` and `walkthrough_preferences`. A read
	 * that is not wired, or that gives nothing, is null.
	 *
	 * @param string $userId The caller's uid.
	 *
	 * @return array<string, array|null> The reads by initial-state key.
	 *
	 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-2
	 */
	public function firstLoad(string $userId): array {
		return [
			'runtimeManifest'         => $this->manifest?->firstLoad(userId: $userId),
			'setup_status'            => $this->setupStatus?->firstLoad(userId: $userId),
			'walkthrough_preferences' => $this->walkthrough?->firstLoad(userId: $userId),
		];
	}//end firstLoad()
}//end class
