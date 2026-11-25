<?php

/**
 * DemoSeedAccounts
 *
 * Creates the demo groups and users of a demo seed and brings their
 * display name, e-mail and group memberships to the seeded values. A user
 * that already exists keeps its password, so a demo account whose password
 * was changed stays reachable after a re-run.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 * @spec      openspec/specs/demo-data-showcases/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use RuntimeException;

/**
 * Seed demo groups and users.
 *
 * @spec openspec/specs/demo-data-showcases/spec.md
 */
class DemoSeedAccounts {
	/**
	 * Constructor.
	 *
	 * @param IUserManager       $userManager  Creates and finds users.
	 * @param IGroupManager      $groupManager Creates groups and memberships.
	 * @param DemoSeedStartDates $startDates   The start date field and the new colleagues' start dates.
	 */
	public function __construct(
		private readonly IUserManager $userManager,
		private readonly IGroupManager $groupManager,
		private readonly DemoSeedStartDates $startDates,
	) {
	}//end __construct()

	/**
	 * The seed's start dates (REQ-PPL-014), counted from the load day.
	 *
	 * @param array<string, mixed> $seed  The seed; reads its `startDates`.
	 * @param string               $today The load day, `YYYY-MM-DD`.
	 *
	 * @return array{field: string, people: array<string, string>} The field key and each person's date.
	 *
	 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-a-profile-field-can-hold-a-start-date-req-pex-005
	 */
	public function ensureStartDates(array $seed, string $today): array {
		return $this->startDates->seed(definition: (array)($seed['startDates'] ?? []), today: $today);
	}//end ensureStartDates()

	/**
	 * Ensure every seeded group exists with its display name.
	 *
	 * @param array<int, array<string, mixed>> $groups The seed groups, `{id, name}`.
	 *
	 * @return array<int, string> The ids of the groups created by this run.
	 *
	 * @spec openspec/changes/launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	public function ensureGroups(array $groups): array {
		$created = [];
		foreach ($groups as $definition) {
			$groupId = (string)$definition['id'];
			$group   = $this->groupManager->get(gid: $groupId);
			if ($group === null) {
				$group = $this->groupManager->createGroup(gid: $groupId);
				$created[] = $groupId;
			}

			$name = (string)($definition['name'] ?? $groupId);
			if ($group !== null && $group->getDisplayName() !== $name) {
				$group->setDisplayName(displayName: $name);
			}
		}

		return $created;
	}//end ensureGroups()

	/**
	 * Ensure every seeded user exists with its display name, e-mail and
	 * exactly the seeded memberships of the seeded groups.
	 *
	 * Memberships of groups outside the seed are left alone, so an
	 * administrator's own grouping survives a re-run.
	 *
	 * The seed manages its own groups and `admin`.
	 *
	 * @param array<string, mixed> $seed     The seed, with its `users` and `groups`.
	 * @param string               $password The password for users this run creates.
	 *
	 * @return array<int, string> The ids of the users created by this run.
	 *
	 * @throws RuntimeException When a user cannot be created.
	 *
	 * @spec openspec/changes/launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	public function ensureUsers(array $seed, string $password): array {
		$managed = [...array_column(array: (array)($seed['groups'] ?? []), column_key: 'id'), 'admin'];
		$created = [];
		foreach ((array)($seed['users'] ?? []) as $definition) {
			$userId = (string)$definition['id'];
			$user   = $this->userManager->get(uid: $userId);
			if ($user === null) {
				$user = $this->createUser(userId: $userId, password: $password);
				$created[] = $userId;
			}

			$this->applyProfile(user: $user, definition: $definition);
			$this->applyMemberships(user: $user, membership: ['wanted' => (array)($definition['groups'] ?? []), 'managed' => $managed]);
		}

		return $created;
	}//end ensureUsers()

	/**
	 * Create one demo user.
	 *
	 * @param string $userId   The user id.
	 * @param string $password The password.
	 *
	 * @return IUser The new user.
	 *
	 * @throws RuntimeException When Nextcloud refuses the user.
	 */
	private function createUser(string $userId, string $password): IUser {
		$user = $this->userManager->createUser(uid: $userId, password: $password);
		if ($user === false) {
			throw new RuntimeException(message: 'Could not create demo user ' . $userId);
		}

		return $user;
	}//end createUser()

	/**
	 * Bring a user's display name and e-mail to the seeded values.
	 *
	 * @param IUser                $user       The user.
	 * @param array<string, mixed> $definition The seed user.
	 *
	 * @return void
	 */
	private function applyProfile(IUser $user, array $definition): void {
		$displayName = (string)($definition['displayName'] ?? '');
		if ($displayName !== '' && $user->getDisplayName() !== $displayName) {
			$user->setDisplayName(displayName: $displayName);
		}

		$email = (string)($definition['email'] ?? '');
		if ($email !== '' && $user->getSystemEMailAddress() !== $email) {
			$user->setSystemEMailAddress(mailAddress: $email);
		}
	}//end applyProfile()

	/**
	 * Add the user to its seeded groups and remove it from the other seed
	 * groups.
	 *
	 * @param IUser                               $user       The user.
	 * @param array<string, array<int, string>>   $membership `wanted`: the groups the seed gives the user;
	 *                                                        `managed`: the groups the seed manages.
	 *
	 * @return void
	 */
	private function applyMemberships(IUser $user, array $membership): void {
		$wanted = array_map(callback: 'strval', array: $membership['wanted']);
		foreach ($membership['managed'] as $groupId) {
			$group = $this->groupManager->get(gid: (string)$groupId);
			if ($group === null) {
				continue;
			}

			$isMember   = $group->inGroup(user: $user);
			$shouldJoin = in_array(needle: $groupId, haystack: $wanted, strict: true);
			if ($shouldJoin === true && $isMember === false) {
				$group->addUser(user: $user);
			} elseif ($shouldJoin === false && $isMember === true) {
				$group->removeUser(user: $user);
			}
		}
	}//end applyMemberships()
}//end class
