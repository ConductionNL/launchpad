<?php

/**
 * AcknowledgedWidgets
 *
 * The "Widgets that ask for confirmation" list of the Announcements tab
 * (REQ-ACK-008): one row per announcement key, with the widget's title, the
 * dashboard it was placed on (the admin template when there is one), the
 * deadline and the confirmed and pending counts of the audience report
 * (REQ-ACK-004).
 *
 * @category Service
 * @package  OCA\LaunchPad\Service
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/dashboard-acknowledgements/spec.md#requirement-an-editor-can-see-who-has-confirmed-req-ack-008
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Kernel\Contracts\AcknowledgementServiceInterface;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * Lists the widget placements that ask readers to confirm.
 *
 * @spec openspec/changes/announcements-managed-in-settings/specs/dashboard-acknowledgements/spec.md#requirement-an-editor-can-see-who-has-confirmed-req-ack-008
 */
class AcknowledgedWidgets {

	/**
	 * Constructor.
	 *
	 * @param WidgetPlacementMapper           $placements       The placements.
	 * @param DashboardMapper                 $dashboards       The dashboards they sit on.
	 * @param AcknowledgementServiceInterface $acknowledgements The audience report per key.
	 */
	public function __construct(
		private readonly WidgetPlacementMapper $placements,
		private readonly DashboardMapper $dashboards,
		private readonly AcknowledgementServiceInterface $acknowledgements,
	) {
	}//end __construct()

	/**
	 * One row per announcement key.
	 *
	 * @return list<array{announcementKey: string, title: string, dashboard: string, deadline: string|null, overdue: bool, acknowledgedCount: int, pendingCount: int}>
	 *
	 * @spec openspec/changes/announcements-managed-in-settings/specs/dashboard-acknowledgements/spec.md#requirement-an-editor-can-see-who-has-confirmed-req-ack-008
	 */
	public function list(): array {
		$byKey = [];
		foreach ($this->placements->findAcknowledgedWithKey() as $placement) {
			$byKey[(string) $placement->getAnnouncementKey()][] = $placement;
		}

		$rows = [];
		foreach ($byKey as $key => $group) {
			$row = $this->row(key: (string) $key, group: $group);
			if ($row !== null) {
				$rows[] = $row;
			}
		}

		return $rows;
	}//end list()

	/**
	 * The row of one key, or null when its report cannot be built.
	 *
	 * @param string            $key   The announcement key.
	 * @param WidgetPlacement[] $group The placements that share it, oldest first.
	 *
	 * @return array<string, mixed>|null
	 */
	private function row(string $key, array $group): ?array {
		[$placement, $dashboard] = $this->blueprint(group: $group);
		try {
			$report = $this->acknowledgements->report(announcementKey: $key);
		} catch (DoesNotExistException) {
			return null;
		}

		return [
			'announcementKey'   => $key,
			'title'             => self::titleOf(placement: $placement),
			'dashboard'         => (string) ($dashboard?->getName() ?? ''),
			'deadline'          => $report['deadline'] ?? null,
			'overdue'           => (bool) ($report['overdue'] ?? false),
			'acknowledgedCount' => (int) ($report['acknowledgedCount'] ?? 0),
			'pendingCount'      => (int) ($report['pendingCount'] ?? 0),
		];
	}//end row()

	/**
	 * The placement on an admin template, else the oldest, with its dashboard.
	 *
	 * @param WidgetPlacement[] $group The placements that share a key, oldest first.
	 *
	 * @return array{0: WidgetPlacement, 1: Dashboard|null}
	 */
	private function blueprint(array $group): array {
		$first = null;
		foreach ($group as $placement) {
			$dashboard = $this->dashboardOf(placement: $placement);
			$first ??= [$placement, $dashboard];
			if ($dashboard?->getType() === Dashboard::TYPE_ADMIN_TEMPLATE) {
				return [$placement, $dashboard];
			}
		}

		return $first ?? [$group[0], null];
	}//end blueprint()

	/**
	 * The dashboard of a placement, or null when it is gone.
	 *
	 * @param WidgetPlacement $placement The placement.
	 *
	 * @return Dashboard|null
	 */
	private function dashboardOf(WidgetPlacement $placement): ?Dashboard {
		try {
			return $this->dashboards->find(id: $placement->getDashboardId());
		} catch (DoesNotExistException) {
			return null;
		}
	}//end dashboardOf()

	/**
	 * The widget's title as the editor named it, else its prompt, else its type.
	 *
	 * @param WidgetPlacement $placement The placement.
	 *
	 * @return string
	 */
	private static function titleOf(WidgetPlacement $placement): string {
		foreach ([$placement->getCustomTitle(), $placement->getTileTitle(), $placement->getAcknowledgementPrompt()] as $candidate) {
			if (is_string($candidate) === true && trim($candidate) !== '') {
				return trim($candidate);
			}
		}

		return (string) $placement->getWidgetId();
	}//end titleOf()
}//end class
