<?php

/**
 * LaunchPad Store Installs.
 *
 * Which register item and version an install created which dashboard
 * (REQ-STORE-011), so the marketplace shows Installed with Open, Update when
 * the register offers a newer version, or Available. Kept in the app config
 * as one map by item slug; a deleted dashboard drops its entry.
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
 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\AppInfo\Application;
use OCP\IAppConfig;

/**
 * The install record of the marketplace.
 *
 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md#requirement-req-store-011-an-installed-template-opens-instead-of-installing-again
 */
class StoreInstalls {

	/**
	 * The app config key holding the map.
	 */
	public const CONFIG_KEY = 'store_installs';

	/**
	 * Constructor.
	 *
	 * @param IAppConfig          $appConfig Holds the map.
	 * @param StoreOpenLinks|null $links     Where an installed item opens; without it there is no Open.
	 *
	 * @return void
	 */
	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly ?StoreOpenLinks $links = null,
	) {
	}//end __construct()

	/**
	 * Every install by item slug: the version and the dashboard it created.
	 *
	 * @return array<string, array{version: string, dashboardId: int, dashboardUuid: string}>
	 *
	 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md#requirement-req-store-011-an-installed-template-opens-instead-of-installing-again
	 */
	public function all(): array {
		$decoded = json_decode(json: $this->appConfig->getValueString(Application::APP_ID, self::CONFIG_KEY, '{}'), associative: true);
		if (is_array($decoded) === false) {
			return [];
		}

		$installs = [];
		foreach ($decoded as $slug => $entry) {
			if (is_array($entry) === true && (int)($entry['dashboardId'] ?? 0) > 0) {
				$installs[(string)$slug] = [
					'version'       => (string)($entry['version'] ?? ''),
					'dashboardId'   => (int)$entry['dashboardId'],
					'dashboardUuid' => (string)($entry['dashboardUuid'] ?? ''),
				];
			}
		}

		return $installs;
	}//end all()

	/**
	 * Record that an install of this item and version created this dashboard.
	 *
	 * @param string $slug      The register item slug.
	 * @param string $version   The installed version.
	 * @param array  $dashboard The dashboard the install created: `{id, uuid}`.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md#requirement-req-store-011-an-installed-template-opens-instead-of-installing-again
	 */
	public function record(string $slug, string $version, array $dashboard): void {
		$dashboardId = (int)($dashboard['id'] ?? 0);
		if ($slug === '' || $dashboardId <= 0) {
			return;
		}

		$installs = $this->all();
		$installs[$slug] = ['version' => $version, 'dashboardId' => $dashboardId, 'dashboardUuid' => (string)($dashboard['uuid'] ?? '')];
		$this->store(installs: $installs);
	}//end record()

	/**
	 * Drop the entries of a dashboard that no longer exists.
	 *
	 * @param int $dashboardId The deleted dashboard.
	 *
	 * @return int The number of entries dropped.
	 *
	 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md#requirement-req-store-011-an-installed-template-opens-instead-of-installing-again
	 */
	public function forget(int $dashboardId): int {
		$installs = $this->all();
		$kept = array_filter($installs, static fn (array $entry): bool => $entry['dashboardId'] !== $dashboardId);
		$dropped = count($installs) - count($kept);
		if ($dropped > 0) {
			$this->store(installs: $kept);
		}

		return $dropped;
	}//end forget()

	/**
	 * The cards with their install state: Installed (with the dashboard to
	 * open), Update when the register's version is newer, or Available. The
	 * fields are the ones the store page reads: `installed`,
	 * `installedVersion`, `updateAvailable` and `openUrl` (its Open action),
	 * plus `status` and `installedDashboardId`.
	 *
	 * @param array<int, array<string, mixed>> $cards The register's cards.
	 *
	 * @return array<int, array<string, mixed>> The cards with their state.
	 *
	 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md#requirement-req-store-011-an-installed-template-opens-instead-of-installing-again
	 */
	public function annotate(array $cards): array {
		$installs = $this->all();
		$links = $this->links;

		return array_map(
			static function (mixed $card) use ($installs, $links): mixed {
				if (is_array($card) === false) {
					return $card;
				}

				$install = $installs[(string)($card['slug'] ?? '')] ?? null;
				if ($install === null) {
					return $card + ['installed' => false, 'updateAvailable' => false, 'status' => 'available'];
				}

				$newer = version_compare((string)($card['version'] ?? ''), $install['version'], '>');
				$status = 'installed';
				if ($newer === true) {
					$status = 'update';
				}

				return $card + [
					'installed'            => true,
					'installedVersion'     => $install['version'],
					'installedDashboardId' => $install['dashboardId'],
					'openUrl'              => ($links?->openUrl(uuid: $install['dashboardUuid']) ?? ''),
					'updateAvailable'      => $newer,
					'status'               => $status,
				];
			},
			$cards
		);
	}//end annotate()

	/**
	 * Write the map.
	 *
	 * @param array<string, array{version: string, dashboardId: int, dashboardUuid: string}> $installs The installs.
	 *
	 * @return void
	 */
	private function store(array $installs): void {
		$this->appConfig->setValueString(Application::APP_ID, self::CONFIG_KEY, (string)json_encode(value: $installs));
	}//end store()

}//end class
