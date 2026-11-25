<?php

/**
 * LaunchPad Leaver Handover.
 *
 * Where a leaver's dashboards go (REQ-SHARE-016, board LpBeheerOnderhoud):
 * their manager, otherwise a person the administrator names. Without the
 * setting REQ-SHARE-012 applies unchanged.
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
 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\AdminSettingKey;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCP\IUser;
use OCP\IUserManager;

/**
 * The leaver handover setting and the person a leaver's dashboards go to.
 *
 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-choose-where-a-leavers-dashboards-go-req-share-016
 */
class LeaverHandover {

	/**
	 * Constructor.
	 *
	 * @param AdminSettingMapper $settings Reads the setting.
	 * @param IUserManager       $users    Tells whether a manager or the named person still has an account.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly AdminSettingMapper $settings,
		private readonly IUserManager $users,
	) {
	}//end __construct()

	/**
	 * The stored setting: to the manager or not, and the person otherwise ('' for nobody).
	 *
	 * @return array{manager: bool, fallbackUid: string}
	 *
	 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-choose-where-a-leavers-dashboards-go-req-share-016
	 */
	public function setting(): array {
		return $this->normalise(value: $this->settings->getValue(key: AdminSettingKey::LEAVER_HANDOVER->value, default: null));
	}//end setting()

	/**
	 * A setting in its stored shape; anything else reads as not set.
	 *
	 * @param mixed $value The raw value.
	 *
	 * @return array{manager: bool, fallbackUid: string}
	 *
	 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-choose-where-a-leavers-dashboards-go-req-share-016
	 */
	public function normalise(mixed $value): array {
		if (is_array($value) === false) {
			return ['manager' => false, 'fallbackUid' => ''];
		}

		$fallback = $value['fallbackUid'] ?? '';
		if (is_string($fallback) === false) {
			$fallback = '';
		}

		return [
			'manager'     => ($value['manager'] ?? false) === true,
			'fallbackUid' => trim(string: $fallback),
		];
	}//end normalise()

	/**
	 * Who the leaver's dashboards go to: their manager when that is set and
	 * the manager still has an account, else the named person when they
	 * still have one, else nobody (REQ-SHARE-012 applies).
	 *
	 * @param IUser $leaver The person being deleted, read before the account is gone.
	 *
	 * @return string|null The new owner's uid, or null.
	 *
	 * @spec openspec/changes/archive/2026-11-25-sharing-policy-board-sync/specs/dashboard-sharing/spec.md#requirement-administrators-choose-where-a-leavers-dashboards-go-req-share-016
	 */
	public function targetFor(IUser $leaver): ?string {
		$setting = $this->setting();
		$leaverId = $leaver->getUID();

		$candidates = [];
		if ($setting['manager'] === true) {
			$candidates = array_map('strval', $leaver->getManagerUids());
		}

		$candidates[] = $setting['fallbackUid'];
		foreach ($candidates as $uid) {
			if ($uid !== '' && $uid !== $leaverId && $this->users->userExists(uid: $uid) === true) {
				return $uid;
			}
		}

		return null;
	}//end targetFor()

}//end class
