<?php

/**
 * MoveAnnouncementEditorsToDelegation
 *
 * The one-time move of the former announcement editor groups to Nextcloud's
 * admin delegation of the announcements setting
 * (announcements-managed-in-settings D1, REQ-ADM-034): every editor group
 * becomes a group delegated {@see LaunchPadAnnouncementsAdmin} through the
 * settings app's authorized-group service, then the editor groups setting is
 * emptied, so delegation is the one authority. When the settings app's
 * service is not there, nothing changes and the editor groups keep counting
 * until an administrator delegates the setting.
 *
 * @category  Repair
 * @package   OCA\LaunchPad\Repair
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Repair;

use OCA\LaunchPad\Db\AdminSettingKey;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Settings\LaunchPadAnnouncementsAdmin;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Moves the announcement editor groups to the delegation.
 *
 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */
class MoveAnnouncementEditorsToDelegation implements IRepairStep {
	/**
	 * The settings app's service that writes admin delegation.
	 */
	public const DELEGATION_SERVICE = 'OCA\\Settings\\Service\\AuthorizedGroupService';

	/**
	 * Constructor.
	 *
	 * @param AdminSettingMapper $settings  Reads and clears the editor groups setting.
	 * @param ContainerInterface $container Resolves the settings app's service when it is there.
	 * @param LoggerInterface    $logger    Logs a group that could not be moved.
	 *
	 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function __construct(
		private readonly AdminSettingMapper $settings,
		private readonly ContainerInterface $container,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * The step's name.
	 *
	 * @return string
	 *
	 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function getName(): string {
		return 'Move the announcement editor groups to the delegated announcements setting';
	}//end getName()

	/**
	 * Delegate the announcements setting to every editor group, then empty
	 * the editor groups setting. Nothing changes without the settings app's
	 * service or when a group cannot be delegated.
	 *
	 * @param IOutput $output The repair output.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-27-announcements-managed-in-settings/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
	 */
	public function run(IOutput $output): void {
		$groups = $this->settings->getValue(key: AdminSettingKey::ANNOUNCEMENT_EDITOR_GROUPS->value, default: []);
		if (is_array($groups) === false || $groups === []) {
			return;
		}

		if ($this->container->has(self::DELEGATION_SERVICE) === false) {
			$output->info('The settings app is not available; the announcement editor groups keep counting until the setting is delegated.');
			return;
		}

		try {
			$service = $this->container->get(self::DELEGATION_SERVICE);
			foreach ($groups as $groupId) {
				if (is_string($groupId) === true && $groupId !== '') {
					$service->create($groupId, LaunchPadAnnouncementsAdmin::class);
				}
			}
		} catch (Throwable $error) {
			$this->logger->warning('Could not delegate the announcements setting to the editor groups: '.$error->getMessage());
			return;
		}

		$this->settings->setSetting(AdminSettingKey::ANNOUNCEMENT_EDITOR_GROUPS->value, []);
		$output->info('Delegated the announcements setting to '.count($groups).' editor group(s).');
	}//end run()
}//end class
