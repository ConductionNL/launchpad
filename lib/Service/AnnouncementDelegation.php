<?php

/**
 * AnnouncementDelegation
 *
 * The groups to which Nextcloud's admin delegation gives Launchpad's
 * announcements setting (announcements-managed-in-settings D1), read from the
 * settings app's authorized-group service. The Roles tab shows them in its
 * read-only "Manage announcements" row; Administration privileges is where
 * they change. Without the settings app the list is empty and says so.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/announcements-managed-in-settings/tasks.md#task-3
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Settings\LaunchPadAnnouncementsAdmin;
use OCP\IGroupManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Reads the groups delegated the announcements setting.
 *
 * @spec openspec/changes/announcements-managed-in-settings/tasks.md#task-3
 */
class AnnouncementDelegation {

	/**
	 * The settings app's service that reads and writes admin delegation.
	 */
	public const DELEGATION_SERVICE = 'OCA\\Settings\\Service\\AuthorizedGroupService';

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container    Resolves the settings app's service when it is there.
	 * @param IGroupManager      $groupManager The group names.
	 * @param LoggerInterface    $logger       Logs a read that failed.
	 */
	public function __construct(
		private readonly ContainerInterface $container,
		private readonly IGroupManager $groupManager,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The delegated groups, by name.
	 *
	 * @return array{available: bool, groups: list<array{id: string, displayName: string}>}
	 *
	 * @spec openspec/changes/announcements-managed-in-settings/tasks.md#task-3
	 */
	public function groups(): array {
		if ($this->container->has(self::DELEGATION_SERVICE) === false) {
			return ['available' => false, 'groups' => []];
		}

		try {
			$delegated = $this->container->get(self::DELEGATION_SERVICE)->findExistingGroupsForClass(LaunchPadAnnouncementsAdmin::class);
		} catch (Throwable $error) {
			$this->logger->warning('Could not read who holds the announcements setting: '.$error->getMessage());
			return ['available' => false, 'groups' => []];
		}

		$groups = [];
		foreach ($delegated as $entry) {
			$groupId  = (string) $entry->getGroupId();
			$groups[] = [
				'id'          => $groupId,
				'displayName' => (string) ($this->groupManager->get($groupId)?->getDisplayName() ?? $groupId),
			];
		}

		usort($groups, static fn (array $left, array $right): int => strcasecmp($left['displayName'], $right['displayName']));

		return ['available' => true, 'groups' => $groups];
	}//end groups()
}//end class
