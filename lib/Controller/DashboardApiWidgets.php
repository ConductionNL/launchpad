<?php

/**
 * DashboardApiWidgets
 *
 * The widget services of the dashboard API: which placements a viewer sees,
 * and the edit-mode draft save.
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
 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Controller;

use OCA\LaunchPad\Kernel\Contracts\ConditionalServiceInterface;
use OCA\LaunchPad\Service\DraftSaveService;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;

/**
 * The widget services of the dashboard API.
 *
 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
 */
class DashboardApiWidgets {
	/**
	 * Constructor.
	 *
	 * @param ConditionalServiceInterface|null  $visibility  Applies the widget visibility rules when a dashboard renders.
	 * @param RoleFeaturePermissionService|null $roleWidgets Hides the widgets the viewer's role does not permit.
	 * @param DraftSaveService|null             $draftSave   Stores an edit-mode draft in one save (REQ-EDIT-002).
	 */
	public function __construct(
		public readonly ?ConditionalServiceInterface $visibility=null,
		public readonly ?RoleFeaturePermissionService $roleWidgets=null,
		public readonly ?DraftSaveService $draftSave=null,
	) {
	}//end __construct()
}//end class
