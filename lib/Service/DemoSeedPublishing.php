<?php

/**
 * DemoSeedPublishing
 *
 * What a demo seed publishes beyond its dashboards: administrator settings
 * (such as who may write announcements), the public start page and the kiosk
 * playlists. Each is matched to what is there already, so a re-run keeps the
 * same link and the same playlist token.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 * @spec      openspec/specs/demo-data-showcases/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\AdminSettingKey;
use OCA\LaunchPad\Db\AdminSettingMapper;

/**
 * Seed administrator settings, the public start page and kiosk playlists.
 *
 * @spec openspec/specs/demo-data-showcases/spec.md
 */
class DemoSeedPublishing {
	/**
	 * Constructor.
	 *
	 * @param AdminSettingMapper $settings The administrator settings.
	 * @param PublicShareService $shares   Public links.
	 * @param KioskService       $kiosk    Kiosk playlists.
	 */
	public function __construct(
		private readonly AdminSettingMapper $settings,
		private readonly PublicShareService $shares,
		private readonly KioskService $kiosk,
	) {
	}//end __construct()

	/**
	 * Bring administrator settings to the seeded values. A list is merged
	 * with the one an administrator keeps, any other value is set.
	 *
	 * @param array<string, mixed> $values Setting key to value.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/demo-data-showcases/spec.md
	 */
	public function mergeAdminSettings(array $values): void {
		foreach ($values as $key => $value) {
			if (is_array($value) === true && array_is_list($value) === true) {
				$current = $this->settings->getValue(key: (string)$key, default: []);
				if (is_array($current) === false) {
					$current = [];
				}

				$value = array_values(array: array_unique(array: [...$current, ...$value]));
			}

			$this->settings->setSetting(key: (string)$key, value: $value);
		}
	}//end mergeAdminSettings()

	/**
	 * Make a dashboard the public start page: its first public link without
	 * password or expiry, created when there is none, and the setting that
	 * shows it to signed-out visitors.
	 *
	 * @param string $dashboardUuid The dashboard.
	 * @param string $publisher     The administrator who publishes it.
	 *
	 * @return string The link token.
	 *
	 * @spec openspec/specs/demo-data-showcases/spec.md
	 */
	public function publishStartPage(string $dashboardUuid, string $publisher): string {
		$token = '';
		foreach ($this->shares->listActiveShares(dashboardUuid: $dashboardUuid, callerId: $publisher) as $share) {
			// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			if ((string)$share->getPasswordHash() === '' && $share->getExpiresAt() === null) {
				$token = (string)$share->getToken();
				break;
			}

			// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		}

		if ($token === '') {
			$token = (string)$this->shares->createPublicShare(dashboardUuid: $dashboardUuid, callerId: $publisher)->getToken();
		}

		$this->settings->setSetting(key: AdminSettingKey::PUBLIC_START_SHARE_TOKEN->value, value: $token);
		$this->settings->setSetting(key: AdminSettingKey::PUBLIC_START_ENABLED->value, value: true);

		return $token;
	}//end publishStartPage()

	/**
	 * Bring a kiosk playlist to the seeded entries: the publisher's live
	 * playlist of that name keeps its token, otherwise a new one is made.
	 *
	 * @param array<string, mixed> $playlist  `{name, refresh, entries: [{dashboardUuid, dwellSeconds}]}`.
	 * @param string               $publisher The administrator who owns it.
	 *
	 * @return string The playlist token.
	 *
	 * @spec openspec/specs/demo-data-showcases/spec.md
	 */
	public function ensurePlaylist(array $playlist, string $publisher): string {
		$draft = new KioskPlaylistDraft(
			name: (string)($playlist['name'] ?? ''),
			entries: (array)($playlist['entries'] ?? []),
			refresh: (int)($playlist['refresh'] ?? 300)
		);

		foreach ($this->kiosk->listPlaylists(callerId: $publisher) as $existing) {
			// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			if ((string)$existing->getName() === $draft->name && $existing->getRevokedAt() === null) {
				return (string)$this->kiosk->updatePlaylist(id: (int)$existing->getId(), draft: $draft, callerId: $publisher)->getToken();
			}

			// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		}

		return (string)$this->kiosk->createPlaylist(draft: $draft, callerId: $publisher)->getToken();
	}//end ensurePlaylist()
}//end class
