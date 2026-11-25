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
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\IAppConfig;
use Throwable;

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
	 * @param WidgetPlacementMapper|null $placements Reads which widgets an install created (REQ-STORE-012).
	 *
	 * @return void
	 */
	public function __construct(
		private readonly IAppConfig $appConfig,
		private readonly ?StoreOpenLinks $links = null,
		private readonly ?WidgetPlacementMapper $placements = null,
	) {
	}//end __construct()

	/**
	 * Every install by item slug: the version, the dashboard it created and
	 * the widgets on it that came from the install. `placementIds` is empty for
	 * a record written before the widgets were kept (REQ-STORE-012).
	 *
	 * @return array<string, array{version: string, dashboardId: int, dashboardUuid: string, placementIds: list<int>}>
	 *
	 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md#requirement-req-store-011-an-installed-template-opens-instead-of-installing-again
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-012-update-brings-an-installed-dashboard-to-the-newer-version-in-place
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
					'placementIds'  => self::ids(value: ($entry['placementIds'] ?? [])),
				];
			}
		}

		return $installs;
	}//end all()

	/**
	 * Record that an install of this item and version created this dashboard.
	 *
	 * The widgets that came from the install are kept with it, so an update
	 * later replaces those and leaves the ones a person added (REQ-STORE-012).
	 * Without a list they are read from the dashboard, which right after an
	 * install holds only the install's widgets.
	 *
	 * @param string         $slug         The register item slug.
	 * @param string         $version      The installed version.
	 * @param array          $dashboard    The dashboard the install created: `{id, uuid}`.
	 * @param list<int>|null $placementIds The widgets that came from the install, or null to read them.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/store-page-board-sync/specs/dashboard-store/spec.md#requirement-req-store-011-an-installed-template-opens-instead-of-installing-again
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-012-update-brings-an-installed-dashboard-to-the-newer-version-in-place
	 */
	public function record(string $slug, string $version, array $dashboard, ?array $placementIds = null): void {
		$dashboardId = (int)($dashboard['id'] ?? 0);
		if ($slug === '' || $dashboardId <= 0) {
			return;
		}

		$installs = $this->all();
		$installs[$slug] = [
			'version'       => $version,
			'dashboardId'   => $dashboardId,
			'dashboardUuid' => (string)($dashboard['uuid'] ?? ''),
			'placementIds'  => self::ids(value: ($placementIds ?? $this->placementIdsOf(dashboardId: $dashboardId))),
		];
		$this->store(installs: $installs);
	}//end record()

	/**
	 * The ids of the widgets a dashboard holds now, or none when they cannot be read.
	 *
	 * @param int $dashboardId The dashboard.
	 *
	 * @return list<int> The placement ids.
	 *
	 * @spec openspec/changes/store-update-and-publish/specs/dashboard-store/spec.md#requirement-req-store-012-update-brings-an-installed-dashboard-to-the-newer-version-in-place
	 */
	private function placementIdsOf(int $dashboardId): array {
		if ($this->placements === null) {
			return [];
		}

		try {
			$ids = [];
			foreach ($this->placements->findByDashboardId(dashboardId: $dashboardId) as $placement) {
				$ids[] = (int)$placement->getId();
			}

			return $ids;
		} catch (Throwable) {
			return [];
		}
	}//end placementIdsOf()

	/**
	 * A stored id list as positive integers.
	 *
	 * @param mixed $value The stored value.
	 *
	 * @return list<int> The ids.
	 */
	private static function ids(mixed $value): array {
		if (is_array($value) === false) {
			return [];
		}

		return array_values(array_filter(array_map('intval', $value), static fn (int $id): bool => $id > 0));
	}//end ids()

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
	 * @param array<string, array{version: string, dashboardId: int, dashboardUuid: string, placementIds: list<int>}> $installs The installs.
	 *
	 * @return void
	 */
	private function store(array $installs): void {
		$this->appConfig->setValueString(Application::APP_ID, self::CONFIG_KEY, (string)json_encode(value: $installs));
	}//end store()

}//end class
