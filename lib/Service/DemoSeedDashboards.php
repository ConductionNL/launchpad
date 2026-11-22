<?php

/**
 * DemoSeedDashboards
 *
 * Writes the dashboards of a demo seed. Every seeded dashboard has a fixed
 * UUID: a run updates the row with that UUID and replaces its placements
 * and shares, so running the seed twice leaves the same state as running
 * it once.
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

use DateTime;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCP\AppFramework\Db\DoesNotExistException;
use RuntimeException;
use Throwable;

/**
 * Write seeded dashboards, their placements, shares and user preferences.
 *
 * @spec openspec/specs/demo-data-showcases/spec.md
 */
class DemoSeedDashboards {
	/**
	 * Builds placements from payloads, the same way showcases and imports do.
	 *
	 * @var PlacementPayloadHydrator
	 */
	private readonly PlacementPayloadHydrator $hydrator;

	/**
	 * Constructor.
	 *
	 * @param ShowcaseStores       $stores      The dashboard and placement mappers and the database.
	 * @param DashboardShareService $shares     Replaces a dashboard's shares and notifies new recipients.
	 * @param DemoSeedPreferences  $preferences Which dashboard opens first; tour progress.
	 */
	public function __construct(
		private readonly ShowcaseStores $stores,
		private readonly DashboardShareService $shares,
		private readonly DemoSeedPreferences $preferences,
	) {
		$this->hydrator = new PlacementPayloadHydrator();
	}//end __construct()

	/**
	 * Write one seeded dashboard with its placements and shares.
	 *
	 * @param array<string, mixed>             $definition The seed dashboard.
	 * @param array<int, array<string, mixed>> $payloads   The expanded placement payloads.
	 *
	 * @return array{uuid: string, created: bool} The dashboard UUID and whether this run created it.
	 *
	 * @throws RuntimeException When the dashboard cannot be written.
	 *
	 * @spec openspec/changes/launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	public function write(array $definition, array $payloads): array {
		$this->stores->db->beginTransaction();
		try {
			[$dashboard, $created] = $this->writeRows(definition: $definition, payloads: $payloads);
			$this->stores->db->commit();
		} catch (Throwable $e) {
			$this->stores->db->rollBack();
			throw new RuntimeException(
				message: 'Could not seed dashboard ' . (string)$definition['name'] . ': ' . $e->getMessage(),
				code: 0,
				previous: $e
			);
		}

		$this->applyAudience(dashboard: $dashboard, definition: $definition);

		return ['uuid' => (string)$definition['uuid'], 'created' => $created];
	}//end write()

	/**
	 * Write the dashboard row and replace its placements.
	 *
	 * @param array<string, mixed>             $definition The seed dashboard.
	 * @param array<int, array<string, mixed>> $payloads   The expanded placement payloads.
	 *
	 * @return array{0: Dashboard, 1: bool} The dashboard and whether it was created.
	 */
	private function writeRows(array $definition, array $payloads): array {
		[$dashboard, $created] = $this->upsertDashboard(uuid: (string)$definition['uuid'], definition: $definition);
		$dashboardId = (int)$dashboard->getId();

		$this->stores->placementMapper->deleteByDashboardId(dashboardId: $dashboardId);
		foreach ($payloads as $payload) {
			$this->stores->placementMapper->insert(entity: $this->placementFor(dashboardId: $dashboardId, payload: $payload));
		}

		return [$dashboard, $created];
	}//end writeRows()

	/**
	 * Give the dashboard its shares and make it the first dashboard of the
	 * users the seed names.
	 *
	 * @param Dashboard            $dashboard  The written dashboard.
	 * @param array<string, mixed> $definition The seed dashboard.
	 *
	 * @return void
	 */
	private function applyAudience(Dashboard $dashboard, array $definition): void {
		$this->replaceShares(dashboard: $dashboard, shares: (array)($definition['shares'] ?? []));

		foreach ((array)($definition['defaultFor'] ?? []) as $userId) {
			$this->preferences->setDefaultDashboard(userId: (string)$userId, uuid: (string)$dashboard->getUuid());
		}
	}//end applyAudience()

	/**
	 * The dashboard with this UUID brought to the seeded fields, inserted
	 * when it does not exist yet.
	 *
	 * @param string               $uuid       The fixed UUID.
	 * @param array<string, mixed> $definition The seed dashboard.
	 *
	 * @return array{0: Dashboard, 1: bool} The persisted dashboard and whether it was created.
	 */
	private function upsertDashboard(string $uuid, array $definition): array {
		$now = (new DateTime())->format(format: 'Y-m-d H:i:s');
		try {
			$dashboard = $this->stores->dashboardMapper->findByUuid(uuid: $uuid);
			$created   = false;
		} catch (DoesNotExistException) {
			$dashboard = new Dashboard();
			$created   = true;
			// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$dashboard->setUuid($uuid);
			$dashboard->setCreatedAt($now);
			// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		}

		$this->applyFields(dashboard: $dashboard, definition: $definition);

		if ($created === true) {
			return [$this->stores->dashboardMapper->insert(entity: $dashboard), true];
		}

		return [$this->stores->dashboardMapper->update(entity: $dashboard), false];
	}//end upsertDashboard()

	/**
	 * Write the seeded fields onto a dashboard.
	 *
	 * @param Dashboard            $dashboard  The dashboard.
	 * @param array<string, mixed> $definition The seed dashboard.
	 *
	 * @return void
	 */
	private function applyFields(Dashboard $dashboard, array $definition): void {
		$this->applyOwnership(dashboard: $dashboard, definition: $definition);

		// Entity setters resolve through __call, which reads $args[0].
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$dashboard->setName((string)$definition['name']);
		$dashboard->setDescription($this->stringOrNull(value: $definition['description'] ?? null));
		$dashboard->setIcon($this->stringOrNull(value: $definition['icon'] ?? null));
		$dashboard->setSlug('demo-' . (string)$definition['key']);
		$dashboard->setGridColumns((int)($definition['gridColumns'] ?? 12));
		$dashboard->setSortOrder((int)($definition['sortOrder'] ?? 0));
		$dashboard->setPublicationStatus(Dashboard::STATUS_PUBLISHED);
		$dashboard->setReactionsEnabled((int)($definition['reactionsEnabled'] ?? 0));
		$dashboard->setUpdatedAt((new DateTime())->format(format: 'Y-m-d H:i:s'));
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
	}//end applyFields()

	/**
	 * Write who a dashboard belongs to and what its audience may do: a
	 * personal dashboard has an owner, a group dashboard a group.
	 *
	 * @param Dashboard            $dashboard  The dashboard.
	 * @param array<string, mixed> $definition The seed dashboard.
	 *
	 * @return void
	 */
	private function applyOwnership(Dashboard $dashboard, array $definition): void {
		$type    = (string)($definition['type'] ?? Dashboard::TYPE_USER);
		$userId  = $this->stringOrNull(value: $definition['owner'] ?? null);
		$groupId = null;
		if ($type === Dashboard::TYPE_GROUP_SHARED) {
			$userId  = null;
			$groupId = (string)$definition['group'];
		}

		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$dashboard->setType($type);
		$dashboard->setUserId($userId);
		$dashboard->setGroupId($groupId);
		$dashboard->setPermissionLevel((string)($definition['permissionLevel'] ?? Dashboard::PERMISSION_FULL));
		$dashboard->setIsDefault(0);
		$dashboard->setIsActive(0);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
	}//end applyOwnership()

	/**
	 * A seed value as a string, null when it is absent.
	 *
	 * @param mixed $value The seed value.
	 *
	 * @return string|null The string, or null.
	 */
	private function stringOrNull(mixed $value): ?string {
		if ($value === null) {
			return null;
		}

		return (string)$value;
	}//end stringOrNull()

	/**
	 * A placement for a payload, with its read receipt when the payload asks
	 * for one. A relative deadline (`+4 days`) resolves against the run time.
	 *
	 * @param integer              $dashboardId The dashboard id.
	 * @param array<string, mixed> $payload     The placement payload.
	 *
	 * @return WidgetPlacement The placement, not yet persisted.
	 */
	private function placementFor(int $dashboardId, array $payload): WidgetPlacement {
		$placement = $this->hydrator->hydrate(dashboardId: $dashboardId, payload: $payload);
		if ((int)($payload['requiresAcknowledgement'] ?? 0) !== 1) {
			return $placement;
		}

		$deadline = (string)($payload['acknowledgementDeadline'] ?? '');
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$placement->setRequiresAcknowledgement(1);
		$placement->setAcknowledgementPrompt((string)($payload['acknowledgementPrompt'] ?? ''));
		if ($deadline !== '') {
			$placement->setAcknowledgementDeadline((new DateTime($deadline))->format(format: 'Y-m-d H:i:s'));
		}

		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		return $placement;
	}//end placementFor()

	/**
	 * Give a personal dashboard exactly the seeded shares, through the share
	 * service, so a recipient who is new to the dashboard is notified once.
	 * A group dashboard has no owner and reaches its audience through its group.
	 *
	 * @param Dashboard                        $dashboard The written dashboard.
	 * @param array<int, array<string, mixed>> $shares    The seeded shares, `{type, with, permissionLevel}`.
	 *
	 * @return void
	 */
	private function replaceShares(Dashboard $dashboard, array $shares): void {
		$owner = (string)$dashboard->getUserId();
		if ($owner === '') {
			return;
		}

		$grants = [];
		foreach ($shares as $share) {
			$grants[] = [
				'shareType'       => (string)$share['type'],
				'shareWith'       => (string)$share['with'],
				'permissionLevel' => (string)($share['permissionLevel'] ?? Dashboard::PERMISSION_VIEW_ONLY),
			];
		}

		$this->shares->replaceShares(dashboardId: (int)$dashboard->getId(), shares: $grants, userId: $owner);
	}//end replaceShares()
}//end class
