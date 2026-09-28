<?php

/**
 * DashboardActivityEmitter
 *
 * Sends the activity events a digest needs from the places where a
 * dashboard changes state: `dashboard_shared` when a share is created,
 * `dashboard_published` when a dashboard becomes published (directly or
 * when a scheduled one is materialised), and `dashboard_updated` when a
 * shared or group dashboard is saved, at most once per dashboard per day
 * (engagement-activity-digest D2, issue #713).
 *
 * Events only go to people who could already see the dashboard: the
 * share's recipient or the members of its group, the dashboard's target
 * groups, and for the default-group sentinel every user (debounced by the
 * publisher's global fan-out guard).
 *
 * @category  Activity
 * @package   OCA\LaunchPad\Activity
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/activity-feed-integration/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Activity;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Db\DashboardShareMapper;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Emits dashboard_shared, dashboard_published and dashboard_updated.
 *
 * @spec openspec/specs/activity-feed-integration/spec.md
 */
class DashboardActivityEmitter {

	/**
	 * Constructor.
	 *
	 * @param ActivityPublisher    $publisher   The activity publisher.
	 * @param DashboardShareMapper $shareMapper The share mapper, for the audience of an update.
	 * @param DebounceHelper       $debounce    The debounce guard.
	 * @param LoggerInterface      $logger      The logger.
	 */
	public function __construct(
		private readonly ActivityPublisher $publisher,
		private readonly DashboardShareMapper $shareMapper,
		private readonly DebounceHelper $debounce,
		private readonly LoggerInterface $logger,
	) {
	}//end __construct()

	/**
	 * A share was created or upgraded: tell its recipients.
	 *
	 * @param Dashboard      $dashboard  The shared dashboard.
	 * @param DashboardShare $share      The share row.
	 * @param string         $actor      The user who shared it.
	 * @param string[]       $recipients The resolved recipient user ids (the sharer excluded).
	 *
	 * @return int The number of activity rows written.
	 *
	 * @spec openspec/specs/activity-feed-integration/spec.md
	 */
	public function shared(Dashboard $dashboard, DashboardShare $share, string $actor, array $recipients): int {
		return $this->guard(
			step: fn (): int => $this->publisher->publishToRecipients(
				type: Extension::EVENT_SHARED,
				actorUserId: $actor,
				dashboardUuid: (string) $dashboard->getUuid(),
				dashboardName: (string) $dashboard->getName(),
				dashboardLink: '',
				recipientUserIds: $recipients,
				extraParams: [
					'recipient' => (string) $share->getShareWith(),
					'role'      => (string) $share->getPermissionLevel(),
				]
			)
		);
	}//end shared()

	/**
	 * A dashboard became published: tell its audience once.
	 *
	 * @param Dashboard $dashboard The published dashboard.
	 * @param string    $actor     The user who published it, or its owner for a scheduled one.
	 *
	 * @return int The number of activity rows written.
	 *
	 * @spec openspec/specs/activity-feed-integration/spec.md
	 */
	public function published(Dashboard $dashboard, string $actor): int {
		return $this->toAudience(type: Extension::EVENT_PUBLISHED, dashboard: $dashboard, actor: $actor);
	}//end published()

	/**
	 * A shared or group dashboard was saved: tell its audience, at most
	 * once per dashboard per day. A personal dashboard that nobody else
	 * can see sends nothing.
	 *
	 * @param Dashboard $dashboard The saved dashboard.
	 * @param string    $actor     The user who saved it.
	 *
	 * @return int The number of activity rows written (0 when debounced or private).
	 *
	 * @spec openspec/specs/activity-feed-integration/spec.md
	 */
	public function updated(Dashboard $dashboard, string $actor): int {
		$shares = $this->shares(dashboard: $dashboard);
		if ($this->groups(dashboard: $dashboard) === [] && $shares === []) {
			return 0;
		}

		if ($this->debounce->allowDailyUpdate(dashboardUuid: (string) $dashboard->getUuid()) === false) {
			return 0;
		}

		return $this->toAudience(type: Extension::EVENT_UPDATED, dashboard: $dashboard, actor: $actor, shares: $shares);
	}//end updated()

	/**
	 * Send one event type to the groups and share recipients of a dashboard.
	 *
	 * @param string                $type      The event type.
	 * @param Dashboard             $dashboard The dashboard.
	 * @param string                $actor     The acting user.
	 * @param DashboardShare[]|null $shares    The shares, when already read.
	 *
	 * @return int The number of activity rows written.
	 */
	private function toAudience(string $type, Dashboard $dashboard, string $actor, ?array $shares=null): int {
		$uuid  = (string) $dashboard->getUuid();
		$name  = (string) $dashboard->getName();
		$count = 0;

		foreach ($this->groups(dashboard: $dashboard) as $groupId) {
			if ($groupId === Dashboard::DEFAULT_GROUP_ID) {
				$count += $this->guard(
					step: fn (): int => $this->publisher->publishGlobal(
						type: $type,
						actorUserId: $actor,
						dashboardUuid: $uuid,
						dashboardName: $name,
						dashboardLink: ''
					)
				);
				continue;
			}

			$count += $this->guard(
				step: fn (): int => $this->publisher->publishToGroup(
					type: $type,
					actorUserId: $actor,
					groupId: $groupId,
					dashboardUuid: $uuid,
					dashboardName: $name,
					dashboardLink: ''
				)
			);
		}//end foreach

		$users = [];
		foreach (($shares ?? $this->shares(dashboard: $dashboard)) as $share) {
			if ($share->getShareType() === DashboardShare::SHARE_TYPE_GROUP) {
				$count += $this->guard(
					step: fn (): int => $this->publisher->publishToGroup(
						type: $type,
						actorUserId: $actor,
						groupId: (string) $share->getShareWith(),
						dashboardUuid: $uuid,
						dashboardName: $name,
						dashboardLink: ''
					)
				);
				continue;
			}

			$users[] = (string) $share->getShareWith();
		}

		if ($users !== []) {
			$count += $this->guard(
				step: fn (): int => $this->publisher->publishToRecipients(
					type: $type,
					actorUserId: $actor,
					dashboardUuid: $uuid,
					dashboardName: $name,
					dashboardLink: '',
					recipientUserIds: $users
				)
			);
		}

		return $count;
	}//end toAudience()

	/**
	 * The groups a dashboard is aimed at: its group for a group dashboard,
	 * plus its target groups.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 *
	 * @return string[] Unique group ids.
	 */
	private function groups(Dashboard $dashboard): array {
		$groups = [];
		if ($dashboard->getType() === Dashboard::TYPE_GROUP_SHARED && (string) $dashboard->getGroupId() !== '') {
			$groups[] = (string) $dashboard->getGroupId();
		}

		foreach ($dashboard->getTargetGroupsArray() as $groupId) {
			if (is_string($groupId) === true && $groupId !== '') {
				$groups[] = $groupId;
			}
		}

		return array_values(array_unique($groups));
	}//end groups()

	/**
	 * The shares of a dashboard, or none when it has no id yet.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 *
	 * @return DashboardShare[] The shares.
	 */
	private function shares(Dashboard $dashboard): array {
		$dashboardId = $dashboard->getId();
		if ($dashboardId === null) {
			return [];
		}

		return $this->guard(
			step: fn (): array => $this->shareMapper->findByDashboardId(dashboardId: (int) $dashboardId),
			fallback: []
		);
	}//end shares()

	/**
	 * Run one emission step; an activity failure never breaks the caller.
	 *
	 * @param callable $step     The step.
	 * @param mixed    $fallback What to return when the step throws.
	 *
	 * @return mixed The step's result, or the fallback.
	 */
	private function guard(callable $step, mixed $fallback=0): mixed {
		try {
			return $step();
		} catch (Throwable $e) {
			$this->logger->warning(
				message: 'LaunchPad dashboard activity could not be emitted',
				context: ['exception' => $e->getMessage()]
			);
			return $fallback;
		}
	}//end guard()
}//end class
