<?php

/**
 * AnnouncementTargetGroups
 *
 * The groups the announcement form offers as the target group
 * (announcement-form-board-sync, REQ-ANN-006).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\IGroup;
use OCP\IGroupManager;

/**
 * Lists the instance's groups for the target group picker.
 *
 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
 */
class AnnouncementTargetGroups {

	/**
	 * Constructor.
	 *
	 * @param IGroupManager $groupManager The groups.
	 */
	public function __construct(
		private readonly IGroupManager $groupManager,
	) {
	}//end __construct()

	/**
	 * The groups an author can pick as the target group, sorted by display name.
	 *
	 * @return list<array{id: string, displayName: string}>
	 *
	 * @spec openspec/changes/announcement-form-board-sync/specs/announcements/spec.md#requirement-the-announcement-form-as-lpaankondiging-draws-it-req-ann-006
	 */
	public function all(): array {
		$groups = array_map(
			static fn (IGroup $group): array => ['id' => $group->getGID(), 'displayName' => $group->getDisplayName()],
			$this->groupManager->search(search: '')
		);
		usort($groups, static fn (array $one, array $two): int => strcasecmp($one['displayName'], $two['displayName']));

		return $groups;
	}//end all()
}//end class
