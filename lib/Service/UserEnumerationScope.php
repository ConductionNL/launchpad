<?php

/**
 * UserEnumerationScope
 *
 * Which other accounts a user may see listed, following Nextcloud's own
 * user-enumeration settings (Administration > Sharing): everyone, only the
 * members of the viewer's own groups, or nobody.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/people-widget/spec.md#requirement-people-listings-honour-the-user-enumeration-settings-req-ppl-015
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCP\IAppConfig;

/**
 * Reads the enumeration settings once per viewer and answers who they may see.
 *
 * @spec openspec/specs/people-widget/spec.md#requirement-people-listings-honour-the-user-enumeration-settings-req-ppl-015
 */
class UserEnumerationScope {
	/**
	 * Core key: whether users may be listed at all (default `yes`).
	 */
	public const KEY_ALLOW = 'shareapi_allow_share_dialog_user_enumeration';

	/**
	 * Core key: listing limited to the viewer's own groups (default `no`).
	 */
	public const KEY_TO_GROUP = 'shareapi_restrict_user_enumeration_to_group';

	/**
	 * Core key: listing limited to the viewer's phone book (default `no`).
	 */
	public const KEY_TO_PHONE = 'shareapi_restrict_user_enumeration_to_phone';

	/**
	 * Constructor.
	 *
	 * @param IAppConfig           $config    The app configuration (core's sharing keys).
	 * @param AdminTemplateService $templates The viewer's group ids (REQ-TMPL-013 single source).
	 *
	 * @return void
	 */
	public function __construct(
		private readonly IAppConfig $config,
		private readonly AdminTemplateService $templates,
	) {
	}//end __construct()

	/**
	 * The groups whose members the viewer may see listed.
	 *
	 * NULL: everyone (enumeration on, not restricted). An empty list:
	 * nobody (enumeration off, or restricted while the viewer has no
	 * groups). Otherwise the viewer's own group ids. A phone-book-only
	 * restriction lists nobody, since a launchpad listing has no phone
	 * book to match against.
	 *
	 * @param string|null $viewerId The signed-in user; none lists nobody.
	 *
	 * @return array<int, string>|null The visible groups, or NULL for everyone.
	 *
	 * @spec openspec/specs/people-widget/spec.md#requirement-people-listings-honour-the-user-enumeration-settings-req-ppl-015
	 */
	public function visibleGroupsFor(?string $viewerId): ?array {
		if ($viewerId === null || $viewerId === '' || $this->isOn(key: self::KEY_ALLOW, default: 'yes') === false) {
			return [];
		}

		if ($this->isOn(key: self::KEY_TO_GROUP, default: 'no') === true) {
			return array_values(array: $this->templates->getUserGroupIdsFor(userId: $viewerId));
		}

		if ($this->isOn(key: self::KEY_TO_PHONE, default: 'no') === true) {
			return [];
		}

		return null;
	}//end visibleGroupsFor()

	/**
	 * Whether the viewer may see one account listed, per
	 * {@see self::visibleGroupsFor()}: in the restricted mode the account
	 * must share one of the viewer's groups.
	 *
	 * @param string|null $viewerId The signed-in user.
	 * @param string      $ownerId  The account to list.
	 *
	 * @return bool Whether the account may be listed.
	 *
	 * @spec openspec/specs/people-widget/spec.md#requirement-people-listings-honour-the-user-enumeration-settings-req-ppl-015
	 */
	public function maySee(?string $viewerId, string $ownerId): bool {
		$groups = $this->visibleGroupsFor(viewerId: $viewerId);
		if ($groups === null) {
			return true;
		}

		if ($groups === []) {
			return false;
		}

		$shared = array_intersect($groups, $this->templates->getUserGroupIdsFor(userId: $ownerId));
		return $shared !== [];
	}//end maySee()

	/**
	 * Whether a core yes/no setting is on.
	 *
	 * @param string $key     The core app-config key.
	 * @param string $default Its default.
	 *
	 * @return bool Whether it reads `yes`.
	 */
	private function isOn(string $key, string $default): bool {
		return $this->config->getValueString(app: 'core', key: $key, default: $default) === 'yes';
	}//end isOn()
}//end class
