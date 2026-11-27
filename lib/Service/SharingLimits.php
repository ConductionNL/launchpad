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
 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\AdminSettingKey;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\DashboardShare;
use InvalidArgumentException;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCP\AppFramework\OCS\OCSForbiddenException;

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
	 * Everyone may share.
	 */
	public const ALLOWED_EVERYONE = 'everyone';

	/**
	 * Only members of the allowed groups may share.
	 */
	public const ALLOWED_GROUPS = 'groups';

	/**
	 * Nobody but an administrator may share (REQ-SHARE-015).
	 */
	public const ALLOWED_NONE = 'none';

	/**
	 * The "Sharing allowed for" choices an administrator can pick.
	 *
	 * @var list<string>
	 */
	public const ALLOWED_MODES = [self::ALLOWED_EVERYONE, self::ALLOWED_GROUPS, self::ALLOWED_NONE];

	/**
	 * The Nextcloud administrators group.
	 */
	private const ADMIN_GROUP = 'admin';

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
	 * Who may share: everyone, the allowed groups, or nobody but an administrator.
	 *
	 * Only Nobody is stored as an override; otherwise the mode follows the
	 * group list (REQ-SHARE-014): groups set means `groups`, none set means
	 * `everyone`. So the group setting keeps working on its own.
	 *
	 * @return string One of ALLOWED_MODES.
	 *
	 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-can-stop-sharing-and-limit-public-links-req-share-015
	 */
	public function allowedMode(): string {
		$stored = $this->settings->getValue(key: AdminSettingKey::SHARING_ALLOWED->value, default: null);
		if ($stored === self::ALLOWED_NONE) {
			return self::ALLOWED_NONE;
		}

		if ($this->allowedGroups() === []) {
			return self::ALLOWED_EVERYONE;
		}

		return self::ALLOWED_GROUPS;
	}//end allowedMode()

	/**
	 * Whether public links may be created; on unless an administrator switched them off.
	 *
	 * @return bool False only for a stored `false`.
	 *
	 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-can-stop-sharing-and-limit-public-links-req-share-015
	 */
	public function publicLinksAllowed(): bool {
		return $this->settings->getValue(key: AdminSettingKey::PUBLIC_LINKS_ALLOWED->value, default: true) !== false;
	}//end publicLinksAllowed()

	/**
	 * Whether a dashboard's Sharing tab may make a screen link (REQ-KIOSK-007);
	 * on when the administrator never stored the switch.
	 *
	 * @return bool True when new screen links may be made.
	 *
	 * @spec openspec/changes/dashboard-show-on-a-screen/tasks.md#task-2
	 */
	public function screenLinksAllowed(): bool {
		return $this->settings->getValue(key: AdminSettingKey::KIOSK_SCREEN_LINKS_ALLOWED->value, default: true) !== false;
	}//end screenLinksAllowed()

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
	 * Under `none` only an administrator may (REQ-SHARE-015); under `groups`
	 * a member of an allowed group; an empty group list lets everyone share.
	 *
	 * @param string $userId The sharer.
	 *
	 * @return bool True when the user may share.
	 *
	 * @spec openspec/changes/record-parity-requirements/specs/dashboard-sharing/spec.md#requirement-administrators-decide-who-may-share-and-with-whom-req-share-014
	 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-can-stop-sharing-and-limit-public-links-req-share-015
	 */
	public function mayShare(string $userId): bool {
		$mode = $this->allowedMode();
		if ($mode === self::ALLOWED_NONE) {
			return in_array(self::ADMIN_GROUP, $this->groupsOf(userId: $userId), true);
		}

		if ($mode === self::ALLOWED_EVERYONE) {
			return true;
		}

		return array_intersect($this->allowedGroups(), $this->groupsOf(userId: $userId)) !== [];
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
	 * Refuse a public link the policy does not allow (REQ-SHARE-015): public
	 * links switched off, a caller who may not share, or no expiry date.
	 *
	 * @param string      $callerId  The person creating the link.
	 * @param string|null $expiresAt The requested expiry date.
	 *
	 * @return void
	 *
	 * @throws OCSForbiddenException    When public links are off or the caller may not share (403).
	 * @throws InvalidArgumentException When the link has no expiry date (400).
	 *
	 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-can-stop-sharing-and-limit-public-links-req-share-015
	 */
	public function assertMayCreatePublicLink(string $callerId, ?string $expiresAt): void {
		if ($this->publicLinksAllowed() === false) {
			throw new OCSForbiddenException(message: 'Public links are switched off.');
		}

		if ($this->mayShare(userId: $callerId) === false) {
			throw new OCSForbiddenException(message: 'You are not allowed to share dashboards.');
		}

		if ($expiresAt === null || trim(string: $expiresAt) === '') {
			throw new InvalidArgumentException(message: 'A public link needs an expiry date (expiresAt).');
		}
	}//end assertMayCreatePublicLink()

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
