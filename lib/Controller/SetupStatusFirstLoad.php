<?php

/**
 * SetupStatusFirstLoad
 *
 * The setup status the workspace page carries for the app shell.
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

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Service\SetupStatusDocument;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * What `GET /api/setup/status` answers the caller, for the page's
 * `setup_status` key (REQ-INIT-010), which nextcloud-vue reads before it asks.
 *
 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
 */
class SetupStatusFirstLoad {
	/**
	 * Constructor.
	 *
	 * @param SetupStatusDocument      $document   The status document the endpoint answers.
	 * @param IGroupManager            $groups     Whether the caller is a Nextcloud admin.
	 * @param LaunchPadAdminDelegation $delegation Whether LaunchPad's admin settings are delegated to the caller.
	 * @param LoggerInterface          $logger     Records a read that failed and was left out.
	 */
	public function __construct(
		private readonly SetupStatusDocument $document,
		private readonly IGroupManager $groups,
		private readonly LaunchPadAdminDelegation $delegation,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The document for a Nextcloud admin, `{forbidden: true}` for a caller the
	 * endpoint refuses. Null (the shell asks the endpoint) for a caller
	 * LaunchPad's admin settings are delegated to, without a user, or when a
	 * read fails.
	 *
	 * @param string $userId The caller's uid.
	 *
	 * @return array<string, mixed>|null The carried status, or null.
	 *
	 * @spec openspec/changes/shell-boot-reads-from-initial-state/tasks.md#task-1
	 */
	public function firstLoad(string $userId): ?array {
		if ($userId === '') {
			return null;
		}

		try {
			if ($this->groups->isAdmin(userId: $userId) === true) {
				return $this->document->build();
			}

			if ($this->delegation->isDelegated(userId: $userId) === true) {
				return null;
			}
		} catch (Throwable $e) {
			$this->logger->warning(
				'LaunchPad: setup status left out of the page: '.$e->getMessage(),
				['app' => Application::APP_ID]
			);
			return null;
		}

		return ['forbidden' => true];
	}//end firstLoad()
}//end class
