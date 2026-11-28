<?php

/**
 * FavouriteDashboardService
 *
 * A person's favourite dashboards: the short list the More menu's Dashboards
 * column shows (REQ-SHELL-014, REQ-SHELL-016). Stored as one user preference,
 * `launchpad` / `favourite_dashboards`, a JSON array of dashboard UUIDs in the
 * order the person starred them.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
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

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\AppInfo\Application;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IConfig;

/**
 * Reads and writes a person's favourite dashboards.
 *
 * Only dashboards the person can open may be starred: a UUID outside the
 * dashboards visible to them answers as not found (REQ-DASH-013), so the list
 * never names a dashboard they may not open. A UUID whose dashboard is gone or
 * no longer visible is dropped when the list is read, and the preference is
 * rewritten then.
 */
class FavouriteDashboardService {
	/**
	 * The user preference the list lives under.
	 */
	public const PREFERENCE_KEY = 'favourite_dashboards';

	/**
	 * Constructor
	 *
	 * @param IConfig          $config           The user preference store.
	 * @param DashboardService $dashboardService Reads the dashboards a person can open.
	 *
	 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-1
	 */
	public function __construct(
		private readonly IConfig $config,
		private readonly DashboardService $dashboardService,
	) {
	}//end __construct()

	/**
	 * The person's favourites, in starring order, limited to the dashboards
	 * they can open. Drops the rest and rewrites the preference when it did.
	 *
	 * @param string             $userId       The person.
	 * @param array<string>|null $visibleUuids The UUIDs of the dashboards the person
	 *                                         can open, when the caller already has
	 *                                         them; null reads them here.
	 *
	 * @return array<string> The favourite dashboard UUIDs.
	 *
	 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-1
	 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-2
	 */
	public function listFor(string $userId, ?array $visibleUuids=null): array {
		$stored = $this->read(userId: $userId);
		if ($stored === []) {
			return [];
		}

		$visible = array_flip($visibleUuids ?? $this->visibleUuids(userId: $userId));
		$kept    = array_values(
			array_filter(
				$stored,
				static fn (string $uuid): bool => isset($visible[$uuid])
			)
		);

		if ($kept !== $stored) {
			$this->write(userId: $userId, uuids: $kept);
		}

		return $kept;
	}//end listFor()

	/**
	 * The favourites the page carries: checked against the visible entries the
	 * page already read, so a gone or hidden one drops out without another
	 * query (REQ-SHELL-014). Null when nobody is logged in.
	 *
	 * @param string $userId  The viewer, or ''.
	 * @param array  $visible The entries of DashboardService::getVisibleToUser().
	 *
	 * @return array<string>|null The favourite UUIDs in starring order.
	 *
	 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-2
	 */
	public function forPage(string $userId, array $visible): ?array {
		if ($userId === '') {
			return null;
		}

		$uuids = array_map(
			static fn (array $entry): string => (string) $entry['dashboard']->getUuid(),
			$visible
		);

		return $this->listFor(userId: $userId, visibleUuids: $uuids);
	}//end forPage()

	/**
	 * Star a dashboard. Idempotent: starring a favourite again changes nothing.
	 *
	 * @param string $userId The person.
	 * @param string $uuid   The dashboard UUID.
	 *
	 * @return array<string> The favourites after the change.
	 *
	 * @throws DoesNotExistException When the person cannot open the dashboard.
	 *
	 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-1
	 */
	public function star(string $userId, string $uuid): array {
		$visible    = $this->requireVisible(userId: $userId, uuid: $uuid);
		$favourites = $this->listFor(userId: $userId, visibleUuids: $visible);
		if (in_array(needle: $uuid, haystack: $favourites, strict: true) === false) {
			$favourites[] = $uuid;
			$this->write(userId: $userId, uuids: $favourites);
		}

		return $favourites;
	}//end star()

	/**
	 * Unstar a dashboard. Idempotent: unstarring a non-favourite changes nothing.
	 *
	 * @param string $userId The person.
	 * @param string $uuid   The dashboard UUID.
	 *
	 * @return array<string> The favourites after the change.
	 *
	 * @throws DoesNotExistException When the person cannot open the dashboard.
	 *
	 * @spec openspec/changes/archive/2026-11-28-more-menu-favourites/tasks.md#task-1
	 */
	public function unstar(string $userId, string $uuid): array {
		$visible    = $this->requireVisible(userId: $userId, uuid: $uuid);
		$favourites = $this->listFor(userId: $userId, visibleUuids: $visible);
		$remaining  = array_values(
			array_filter(
				$favourites,
				static fn (string $favourite): bool => $favourite !== $uuid
			)
		);
		if ($remaining !== $favourites) {
			$this->write(userId: $userId, uuids: $remaining);
		}

		return $remaining;
	}//end unstar()

	/**
	 * The UUIDs of the dashboards the person can open, refusing one outside them.
	 *
	 * @param string $userId The person.
	 * @param string $uuid   The dashboard UUID that must be among them.
	 *
	 * @return array<string> Every visible UUID.
	 *
	 * @throws DoesNotExistException When $uuid is not among them.
	 */
	private function requireVisible(string $userId, string $uuid): array {
		$visible = $this->visibleUuids(userId: $userId);
		if (in_array(needle: $uuid, haystack: $visible, strict: true) === false) {
			throw new DoesNotExistException(msg: 'Dashboard not found');
		}

		return $visible;
	}//end requireVisible()

	/**
	 * The UUIDs of every dashboard the person can open.
	 *
	 * @param string $userId The person.
	 *
	 * @return array<string> The UUIDs.
	 */
	private function visibleUuids(string $userId): array {
		$uuids = [];
		foreach ($this->dashboardService->getVisibleToUser(userId: $userId) as $entry) {
			$uuids[] = (string) $entry['dashboard']->getUuid();
		}

		return $uuids;
	}//end visibleUuids()

	/**
	 * The stored list, tolerating an unset or malformed preference.
	 *
	 * @param string $userId The person.
	 *
	 * @return array<string> The stored UUIDs, without duplicates.
	 */
	private function read(string $userId): array {
		$raw = $this->config->getUserValue(
			userId: $userId,
			appName: Application::APP_ID,
			key: self::PREFERENCE_KEY,
			default: ''
		);
		if ($raw === '') {
			return [];
		}

		$decoded = json_decode(json: $raw, associative: true);
		if (is_array($decoded) === false) {
			return [];
		}

		$uuids = array_filter(
			$decoded,
			static fn (mixed $uuid): bool => is_string($uuid) === true && $uuid !== ''
		);

		return array_values(array_unique($uuids));
	}//end read()

	/**
	 * Store the list; an empty list removes the preference.
	 *
	 * @param string        $userId The person.
	 * @param array<string> $uuids  The UUIDs in starring order.
	 *
	 * @return void
	 */
	private function write(string $userId, array $uuids): void {
		if ($uuids === []) {
			$this->config->deleteUserValue(
				userId: $userId,
				appName: Application::APP_ID,
				key: self::PREFERENCE_KEY
			);
			return;
		}

		$this->config->setUserValue(
			userId: $userId,
			appName: Application::APP_ID,
			key: self::PREFERENCE_KEY,
			value: json_encode(value: array_values($uuids))
		);
	}//end write()
}//end class
