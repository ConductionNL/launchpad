<?php

/**
 * LaunchPadActivitySetting
 *
 * Shared base for LaunchPad's activity settings (REQ-DIGE-001). Every
 * setting sits in one "LaunchPad" group and can be changed for both the
 * stream and the email digest; subclasses only name the event type and
 * say whether mail defaults to on.
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

use OCP\Activity\ActivitySettings;
use OCP\IL10N;

/**
 * Base class for the four LaunchPad activity settings.
 *
 * @spec openspec/changes/engagement-activity-digest/tasks.md#task-1
 */
abstract class LaunchPadActivitySetting extends ActivitySettings {
	/**
	 * Group identifier shared by every LaunchPad setting.
	 *
	 * @var string
	 */
	public const GROUP_ID = 'launchpad';

	/**
	 * Constructor.
	 *
	 * @param IL10N $l10n LaunchPad translations.
	 */
	public function __construct(
		protected readonly IL10N $l10n,
	) {
	}//end __construct()

	/**
	 * The group all LaunchPad settings share.
	 *
	 * @return string The group identifier.
	 *
	 * @spec openspec/changes/engagement-activity-digest/tasks.md#task-1
	 */
	public function getGroupIdentifier() {
		return self::GROUP_ID;
	}//end getGroupIdentifier()

	/**
	 * The group label people see in their activity settings.
	 *
	 * @return string The translated group name.
	 *
	 * @spec openspec/changes/engagement-activity-digest/tasks.md#task-1
	 */
	public function getGroupName() {
		return $this->l10n->t('LaunchPad');
	}//end getGroupName()

	/**
	 * Every event shows in the stream; like Nextcloud's own activity
	 * settings, a person only opts out of mail and push (REQ-ACT-009).
	 *
	 * @return boolean Always false.
	 *
	 * @spec openspec/specs/activity-feed-integration/spec.md
	 */
	public function canChangeStream() {
		return false;
	}//end canChangeStream()

	/**
	 * Every LaunchPad event shows in the stream.
	 *
	 * @return boolean Always true.
	 *
	 * @spec openspec/specs/activity-feed-integration/spec.md
	 */
	public function isDefaultEnabledStream() {
		return true;
	}//end isDefaultEnabledStream()

	/**
	 * Each person decides whether the event is pushed as a notification.
	 *
	 * @return boolean Always true.
	 *
	 * @spec openspec/specs/activity-feed-integration/spec.md
	 */
	public function canChangeNotification() {
		return true;
	}//end canChangeNotification()

	/**
	 * Push follows the mail default of each event type.
	 *
	 * @return boolean Whether push is on before the person chooses.
	 *
	 * @spec openspec/specs/activity-feed-integration/spec.md
	 */
	public function isDefaultEnabledNotification() {
		return $this->isDefaultEnabledMail();
	}//end isDefaultEnabledNotification()

	/**
	 * Each person decides whether the event goes into their email digest.
	 *
	 * @return boolean Always true.
	 *
	 * @spec openspec/changes/engagement-activity-digest/tasks.md#task-1
	 */
	public function canChangeMail() {
		return true;
	}//end canChangeMail()
}//end class
