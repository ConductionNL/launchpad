<?php

/**
 * ActivityPublisher
 *
 * Single entry point for emitting LaunchPad events into the Nextcloud
 * Activity stream (REQ-ACT-003). Wraps `OCP\Activity\IManager::publish()`
 * so call-sites can stay one-liner short and never construct
 * `IEvent` instances themselves.
 *
 * Audience targeting (REQ-ACT-004..006, REQ-ACT-008) is handled by
 * the dedicated `publishToOwner`, `publishToRecipients`, `publishToGroup`
 * and `publishGlobal` helpers; the generic `publish()` always emits a
 * single row to a single recipient and is the most common entry.
 *
 * @category  Activity
 * @package   OCA\LaunchPad\Activity
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Activity;

use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use Throwable;

/**
 * Thin Activity emission service for LaunchPad.
 */
class ActivityPublisher {
	/**
	 * Constructor.
	 *
	 * @param IManager       $manager The NC Activity manager.
	 * @param IGroupManager  $groupManager The NC group manager.
	 * @param IUserManager   $userManager The NC user manager.
	 * @param PublisherTools $tools The debounce helper and logger.
	 */
	public function __construct(
		private readonly IManager $manager,
		private readonly IGroupManager $groupManager,
		private readonly IUserManager $userManager,
		private readonly PublisherTools $tools,
	) {
	}//end __construct()

	/**
	 * Emit a single activity row to `$recipientUserId`.
	 *
	 * Unknown event types are silently dropped after a warning log entry
	 * (REQ-ACT-002 contract). Reaction events go through the per-actor
	 * debounce (REQ-ACT-007). Every NC `IManager` call is wrapped in a
	 * try/catch so an Activity failure never propagates back into the
	 * owning capability's HTTP handler (REQ-ACT-011 scenario).
	 *
	 * @param ActivityRow $row             The activity to write.
	 * @param string      $recipientUserId The recipient NC user ID.
	 *
	 * @return bool True when the event was successfully published; false when suppressed or dropped.
	 *
	 * @spec openspec/specs/activity-feed-integration/spec.md
	 */
	public function publish(ActivityRow $row, string $recipientUserId): bool {
		if (in_array(needle: $row->type, haystack: Extension::ALL_EVENTS, strict: true) === false) {
			$this->tools->logger->warning(
				message: 'Unknown LaunchPad activity type rejected',
				context: [
					'type' => $row->type,
					'dashboard' => $row->dashboard->uuid,
				]
			);
			return false;
		}

		if ($row->type === Extension::EVENT_REACTED
			&& $this->tools->debounce->allowReaction(
				actorUserId: $row->actorUserId,
				dashboardUuid: $row->dashboard->uuid
			) === false
		) {
			return false;
		}

		try {
			$this->manager->publish(event: $this->buildEvent(row: $row, recipientUserId: $recipientUserId));
		} catch (Throwable $e) {
			$this->tools->logger->error(
				message: 'LaunchPad Activity publish failed',
				context: [
					'type' => $row->type,
					'dashboard' => $row->dashboard->uuid,
					'recipient' => $recipientUserId,
					'exception' => $e,
				]
			);
			return false;
		}//end try

		return true;
	}//end publish()

	/**
	 * Emit one activity row per recipient in `$recipientUserIds`, plus
	 * one row to the actor (REQ-ACT-005). Recipients are de-duplicated
	 * to prevent double-emission when the actor is also a recipient.
	 *
	 * @param ActivityRow $row              The activity to write.
	 * @param string[]    $recipientUserIds Recipient NC user IDs.
	 *
	 * @return int The number of rows successfully written.
	 *
	 * @spec openspec/specs/activity-feed-integration/spec.md
	 */
	public function publishToRecipients(ActivityRow $row, array $recipientUserIds): int {
		$unique = array_values(
			array: array_unique(
				array: array_merge([$row->actorUserId], $recipientUserIds)
			)
		);

		$count = 0;
		foreach ($unique as $userId) {
			if ($this->publish(row: $row->forRecipient(recipientUserId: $userId), recipientUserId: $userId) === true) {
				$count++;
			}
		}

		return $count;
	}//end publishToRecipients()

	/**
	 * Emit activity rows to every member of `$groupId` (REQ-ACT-006).
	 *
	 * Returns 0 (without raising) when the group is unknown or empty.
	 * The actor is included exactly once even when they are also a
	 * member of the group.
	 *
	 * @param ActivityRow $row     The activity to write.
	 * @param string      $groupId The target group ID.
	 *
	 * @return int The number of rows successfully written.
	 *
	 * @spec openspec/specs/activity-feed-integration/spec.md
	 */
	public function publishToGroup(ActivityRow $row, string $groupId): int {
		$group = $this->groupManager->get(gid: $groupId);
		if ($group === null) {
			return 0;
		}

		$userIds = [];
		foreach ($group->getUsers() as $user) {
			$userIds[] = $user->getUID();
		}

		return $this->publishToRecipients(row: $row, recipientUserIds: $userIds);
	}//end publishToGroup()

	/**
	 * Emit activity rows to every authenticated NC user (REQ-ACT-008).
	 *
	 * The full fan-out is gated by
	 * `DebounceHelper::allowGlobalFanout(dashboardUuid, type)` —
	 * suppressed events are logged at DEBUG and produce zero rows.
	 *
	 * @param ActivityRow $row The activity to write.
	 *
	 * @return int The number of rows successfully written (0 when debounced).
	 *
	 * @spec openspec/specs/activity-feed-integration/spec.md
	 */
	public function publishGlobal(ActivityRow $row): int {
		if ($this->tools->debounce->allowGlobalFanout(
			dashboardUuid: $row->dashboard->uuid,
			eventType: $row->type
		) === false
		) {
			$this->tools->logger->debug(
				message: 'LaunchPad global activity fan-out debounced',
				context: [
					'type' => $row->type,
					'dashboard' => $row->dashboard->uuid,
				]
			);
			return 0;
		}

		$count = 0;
		$this->userManager->callForAllUsers(
			callback: function (IUser $user) use ($row, &$count): void {
				$userId = $user->getUID();
				if ($this->publish(row: $row->forRecipient(recipientUserId: $userId), recipientUserId: $userId) === true) {
					$count++;
				}
			}
		);

		return $count;
	}//end publishGlobal()

	/**
	 * Build the canonical `IEvent` object for a single activity row.
	 *
	 * The numeric `objectId` slot in `IEvent::setObject()` requires an
	 * int per the NC interface; the dashboard UUID is stored in the
	 * `objectName` slot so the activity row can be deep-linked back to
	 * the canonical dashboard regardless of database renumbering. The
	 * subject parameters carry every field rendered by `parse()`.
	 *
	 * @param ActivityRow $row             The activity to write.
	 * @param string      $recipientUserId The recipient user ID.
	 *
	 * @return IEvent The fully populated event.
	 */
	private function buildEvent(ActivityRow $row, string $recipientUserId): IEvent {
		$params = array_merge(
			[
				'self' => ($row->actorUserId === $recipientUserId),
				'actor' => $row->actorUserId,
				'dashboard' => $row->dashboard->name,
			],
			$row->extraParams
		);

		$event = $this->manager->generateEvent();
		$event
			->setApp(app: Extension::APP_ID)
			->setType(type: $row->type)
			->setAuthor(author: $row->actorUserId)
			->setAffectedUser(affectedUser: $recipientUserId)
			->setSubject(subject: $row->type, parameters: $params)
			->setObject(
				objectType: Extension::OBJECT_TYPE,
				objectId: 0,
				objectName: $row->dashboard->uuid
			)
			->setLink(link: $row->dashboard->link)
			->setTimestamp(timestamp: time());

		$message = (string)($row->extraParams['message'] ?? '');
		if ($message !== '') {
			$event->setMessage(message: substr(string: $message, offset: 0, length: 200));
		}

		return $event;
	}//end buildEvent()
}//end class
