<?php

/**
 * LaunchPad Sharing Limits.
 *
 * Who may share a dashboard, and with whom (REQ-SHARE-014). Two admin
 * settings: the groups whose members may share (empty means everyone, which
 * is how sharing worked before the setting existed), and whether recipients
 * are limited to people who share a group with the sharer. The share service
 * enforces both on every share it creates, and the sharee search offers only
 * recipients that would pass.
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/record-parity-requirements/specs/dashboard-sharing/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\AdminSettingKey;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Exception\ForbiddenException;

/**
 * The admin limits on dashboard sharing.
 */
class SharingLimits {

	/**
	 * Recipients may be anyone.
	 */
	public const SCOPE_EVERYONE = 'everyone';

	/**
	 * Recipients must share a group with the sharer.
	 */
	public const SCOPE_SHARED_GROUPS = 'shared-groups';

	/**
	 * The recipient scopes an administrator can pick.
	 *
	 * @var list<string>
	 */
	public const SCOPES = [self::SCOPE_EVERYONE, self::SCOPE_SHARED_GROUPS];

	/**
	 * Constructor.
	 *
	 * @param AdminSettingMapper   $settings  Reads the two settings.
	 * @param AdminTemplateService $templates Answers a user's groups, the one place that asks Nextcloud.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly AdminSettingMapper $settings,
		private readonly AdminTemplateService $templates,
	) {
	}//end __construct()

	/**
	 * The groups whose members may share; empty means everyone may.
	 *
	 * @return list<string> The group ids.
	 *
	 * @spec openspec/changes/record-parity-requirements/specs/dashboard-sharing/spec.md#requirement-administrators-decide-who-may-share-and-with-whom-req-share-014
	 */
	public function allowedGroups(): array {
		$stored = $this->settings->getValue(key: AdminSettingKey::SHARING_ALLOWED_GROUPS->value, default: []);
		if (is_array($stored) === false) {
			return [];
		}

		return array_values(array_filter(array_map('strval', $stored), static fn (string $id): bool => $id !== ''));
	}//end allowedGroups()

	/**
	 * Who may receive a share: everyone, or people who share a group with the sharer.
	 *
	 * An unknown stored value reads as `everyone`, today's behaviour.
	 *
	 * @return string One of SCOPES.
	 *
	 * @spec openspec/changes/record-parity-requirements/specs/dashboard-sharing/spec.md#requirement-administrators-decide-who-may-share-and-with-whom-req-share-014
	 */
	public function recipientScope(): string {
		$stored = $this->settings->getValue(key: AdminSettingKey::SHARING_RECIPIENT_SCOPE->value, default: self::SCOPE_EVERYONE);
		if (in_array($stored, self::SCOPES, true) === false) {
			return self::SCOPE_EVERYONE;
		}

		return $stored;
	}//end recipientScope()

	/**
	 * Whether this user may share dashboards at all.
	 *
	 * @param string $userId The sharer.
	 *
	 * @return bool True when no groups are set, or the user is in one of them.
	 *
	 * @spec openspec/changes/record-parity-requirements/specs/dashboard-sharing/spec.md#requirement-administrators-decide-who-may-share-and-with-whom-req-share-014
	 */
	public function mayShare(string $userId): bool {
		$allowed = $this->allowedGroups();
		if ($allowed === []) {
			return true;
		}

		return array_intersect($allowed, $this->groupsOf(userId: $userId)) !== [];
	}//end mayShare()

	/**
	 * Whether this sharer may share with this recipient.
	 *
	 * Under the shared-groups scope a user recipient must share a group with
	 * the sharer, and a group recipient must be a group the sharer is in.
	 *
	 * @param string $sharerId  The sharer.
	 * @param string $shareType `user` or `group`.
	 * @param string $shareWith The recipient user or group id.
	 *
	 * @return bool True when the recipient is allowed.
	 *
	 * @spec openspec/changes/record-parity-requirements/specs/dashboard-sharing/spec.md#requirement-administrators-decide-who-may-share-and-with-whom-req-share-014
	 */
	public function mayReceive(string $sharerId, string $shareType, string $shareWith): bool {
		if ($this->recipientScope() === self::SCOPE_EVERYONE) {
			return true;
		}

		$sharerGroups = $this->groupsOf(userId: $sharerId);
		if ($shareType === DashboardShare::SHARE_TYPE_GROUP) {
			return in_array($shareWith, $sharerGroups, true);
		}

		return array_intersect($sharerGroups, $this->groupsOf(userId: $shareWith)) !== [];
	}//end mayReceive()

	/**
	 * Refuse a share the limits do not allow.
	 *
	 * @param string $sharerId  The sharer.
	 * @param string $shareType `user` or `group`.
	 * @param string $shareWith The recipient.
	 *
	 * @return void
	 *
	 * @throws ForbiddenException When the sharer may not share, or not with this recipient.
	 *
	 * @spec openspec/changes/record-parity-requirements/specs/dashboard-sharing/spec.md#requirement-administrators-decide-who-may-share-and-with-whom-req-share-014
	 */
	public function assertMayShare(string $sharerId, string $shareType, string $shareWith): void {
		if ($this->mayShare(userId: $sharerId) === false) {
			throw new ForbiddenException(message: 'You are not allowed to share dashboards.');
		}

		if ($this->mayReceive(sharerId: $sharerId, shareType: $shareType, shareWith: $shareWith) === false) {
			throw new ForbiddenException(message: 'You can only share with people who share a group with you.');
		}
	}//end assertMayShare()

	/**
	 * The group ids a user is in; none for an unknown user.
	 *
	 * @param string $userId The user.
	 *
	 * @return list<string> The group ids.
	 */
	private function groupsOf(string $userId): array {
		return array_values(array_map('strval', $this->templates->getUserGroupIdsFor(userId: $userId)));
	}//end groupsOf()

}//end class
