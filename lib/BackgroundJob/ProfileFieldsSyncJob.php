<?php

/**
 * ProfileFieldsSyncJob
 *
 * Daily refresh of the profile values (REQ-PEX-001, REQ-PEX-004): reads the
 * LDAP-sourced fields of every account that comes from LDAP and refreshes
 * the standard-field mirror of every user that has signed in.
 *
 * @category  BackgroundJob
 * @package   OCA\LaunchPad\BackgroundJob
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

namespace OCA\LaunchPad\BackgroundJob;

use OCA\LaunchPad\Service\ProfileFieldService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Daily profile field sync.
 *
 * @spec openspec/specs/people-widget/spec.md
 */
class ProfileFieldsSyncJob extends TimedJob {
	/**
	 * Constructor.
	 *
	 * @param ITimeFactory $time Time factory.
	 * @param IUserManager $userManager Users.
	 * @param ProfileFieldService $profileFields Profile field service.
	 * @param LoggerInterface $logger Logger.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function __construct(
		ITimeFactory $time,
		private readonly IUserManager $userManager,
		private readonly ProfileFieldService $profileFields,
		private readonly LoggerInterface $logger,
	) {
		parent::__construct(time: $time);
		$this->setInterval(seconds: 86400);
	}//end __construct()

	/**
	 * Refresh every user that has signed in; one failure does not stop the rest.
	 *
	 * @param mixed $argument Unused.
	 *
	 * @return void
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter) $argument is required by TimedJob.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	protected function run($argument): void {
		$failed = 0;
		$this->userManager->callForSeenUsers(
			function (IUser $user) use (&$failed): bool {
				try {
					$this->profileFields->syncLdapFields(user: $user);
					$this->profileFields->mirrorStandardFields(user: $user);
				} catch (Throwable) {
					$failed++;
				}

				// True keeps the iteration going.
				return true;
			}
		);

		if ($failed > 0) {
			$this->logger->warning(message: 'ProfileFieldsSyncJob: ' . $failed . ' user(s) could not be refreshed.');
		}
	}//end run()
}//end class
