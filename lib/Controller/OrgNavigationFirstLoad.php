<?php

/**
 * OrgNavigationFirstLoad
 *
 * The org navigation the workspace page carries in its initial state.
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
 * @spec openspec/changes/org-navigation-from-initial-state/tasks.md#task-1
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Service\OrgNavigationService;

/**
 * What the shell's first org-navigation load would fetch: the rail position
 * of `GET /api/admin/org-navigation/position` and the tree of
 * `GET /api/admin/org-navigation`, for the default language.
 *
 * @spec openspec/changes/org-navigation-from-initial-state/tasks.md#task-1
 */
class OrgNavigationFirstLoad {
	/**
	 * Constructor.
	 *
	 * @param OrgNavigationSources $sources The org navigation service and the position setting.
	 * @param RequestActor|null    $actor   The action matrix check; without it no first load is given.
	 */
	public function __construct(
		private readonly OrgNavigationSources $sources,
		private readonly ?RequestActor $actor=null,
	) {
	}//end __construct()

	/**
	 * `{position, tree, language}` with the answers of the two endpoints for
	 * the caller. Null when the caller may not read either of them, or
	 * without the action check, so the shell then asks the API as before.
	 *
	 * @param string $userId The caller's uid.
	 *
	 * @return array{position: string, tree: array, language: string}|null The first load, or null.
	 *
	 * @spec openspec/changes/org-navigation-from-initial-state/tasks.md#task-1
	 */
	public function firstLoad(string $userId): ?array {
		if ($userId === '' || $this->actor === null) {
			return null;
		}

		$actions = ['admin-org-navigation.get-org-navigation', 'admin-org-navigation.get-position'];
		foreach ($actions as $action) {
			if ($this->actor->refusal(userId: $userId, action: $action) !== null) {
				return null;
			}
		}

		$language = OrgNavigationService::DEFAULT_LANGUAGE;
		$tree     = $this->sources->service->filterTreeByUserGroups(
			tree: $this->sources->service->getTree(language: $language),
			userId: $userId
		);

		return [
			'position' => $this->sources->position(),
			'tree'     => $tree,
			'language' => $language,
		];
	}//end firstLoad()
}//end class
