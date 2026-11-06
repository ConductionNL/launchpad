<?php

/**
 * DashboardAcknowledgedSetting
 *
 * Activity setting for `dashboard_acknowledged` (REQ-DIGE-001): shown in the stream by
 * default, email digest default off.
 *
 * @category  Activity
 * @package   OCA\LaunchPad\Activity\Settings
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

namespace OCA\LaunchPad\Activity\Settings;

use OCA\LaunchPad\Activity\Extension;

/**
 * Setting for the `dashboard_acknowledged` activity.
 *
 * @spec openspec/changes/engagement-activity-digest/tasks.md#task-1
 */
class DashboardAcknowledgedSetting extends LaunchPadActivitySetting {
	/**
	 * The activity type this setting governs.
	 *
	 * @return string The event type.
	 *
	 * @spec openspec/changes/engagement-activity-digest/tasks.md#task-1
	 */
	public function getIdentifier() {
		return Extension::EVENT_ACKNOWLEDGED;
	}//end getIdentifier()

	/**
	 * The label people see in their activity settings.
	 *
	 * @return string The translated label.
	 *
	 * @spec openspec/changes/engagement-activity-digest/tasks.md#task-1
	 */
	public function getName() {
		return $this->l10n->t('You acknowledge an announcement on a dashboard');
	}//end getName()

	/**
	 * Sort order within the LaunchPad group.
	 *
	 * @return integer The priority (0-100).
	 *
	 * @spec openspec/changes/engagement-activity-digest/tasks.md#task-1
	 */
	public function getPriority() {
		return 53;
	}//end getPriority()

	/**
	 * Whether the email digest includes this event by default.
	 *
	 * @return boolean The mail default.
	 *
	 * @spec openspec/changes/engagement-activity-digest/tasks.md#task-1
	 */
	public function isDefaultEnabledMail() {
		return false;
	}//end isDefaultEnabledMail()
}//end class
