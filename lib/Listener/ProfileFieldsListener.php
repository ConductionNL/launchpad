<?php

/**
 * ProfileFieldsListener
 *
 * Keeps `launchpad_profile_values` current (REQ-PEX-001, REQ-PEX-004): on
 * sign-in it reads the LDAP-sourced fields and refreshes the standard-field
 * mirror, on a profile edit it refreshes the mirror, and on user deletion it
 * removes the person's values.
 *
 * @category  Listener
 * @package   OCA\LaunchPad\Listener
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

namespace OCA\LaunchPad\Listener;

use OCA\LaunchPad\Service\ProfileFieldService;
use OCP\Accounts\UserUpdatedEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\User\Events\UserDeletedEvent;
use OCP\User\Events\UserLoggedInEvent;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Profile field sync on sign-in, profile edit and user deletion.
 *
 * @template-implements IEventListener<Event>
 *
 * @spec openspec/specs/people-widget/spec.md
 */
class ProfileFieldsListener implements IEventListener {
	/**
	 * Constructor.
	 *
	 * @param ProfileFieldService $profileFields Profile field service.
	 * @param LoggerInterface $logger Logger.
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function __construct(
		private readonly ProfileFieldService $profileFields,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * Handle the event. A failure is logged and never breaks the sign-in or
	 * the profile edit that raised it.
	 *
	 * @param Event $event The event.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/people-widget/spec.md
	 */
	public function handle(Event $event): void {
		try {
			if ($event instanceof UserLoggedInEvent) {
				$this->profileFields->syncLdapFields(user: $event->getUser());
				$this->profileFields->mirrorStandardFields(user: $event->getUser());
				return;
			}

			if ($event instanceof UserUpdatedEvent) {
				$this->profileFields->mirrorStandardFields(user: $event->getUser());
				return;
			}

			if ($event instanceof UserDeletedEvent) {
				$this->profileFields->deleteUser(userId: $event->getUser()->getUID());
			}
		} catch (Throwable $e) {
			$this->logger->warning(message: 'Profile field sync failed: ' . $e->getMessage());
		}
	}//end handle()
}//end class
