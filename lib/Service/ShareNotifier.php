<?php

/**
 * ShareNotifier
 *
 * Tells users about share and ownership changes on a dashboard.
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
 *
 * @spec openspec/specs/dashboard-sharing/spec.md
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTime;
use OCA\LaunchPad\Activity\DashboardActivityEmitter;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardShare;
use OCP\IGroupManager;
use OCP\Notification\IManager as INotificationManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Sends the notifications and activity of a share that is already written.
 *
 * @spec openspec/specs/dashboard-sharing/spec.md
 */
class ShareNotifier {
	/**
	 * Constructor
	 *
	 * @param INotificationManager          $notificationManager The notification manager.
	 * @param IGroupManager                 $groupManager        The group manager.
	 * @param LoggerInterface               $logger              Records a failed announcement.
	 * @param DashboardActivityEmitter|null $activity            Sends dashboard_shared to the recipients (#713).
	 */
	public function __construct(
		private readonly INotificationManager $notificationManager,
		private readonly IGroupManager $groupManager,
		private readonly LoggerInterface $logger,
		private readonly ?DashboardActivityEmitter $activity = null,
	) {
	}//end __construct()

	/**
	 * Publish `dashboard_shared` notifications for a share.
	 *
	 * For `user`-type shares: one notification.
	 * For `group`-type shares: fan out one notification per current group
	 * member, excluding the sharer. REQ-SHARE-008.
	 *
	 * The share is already committed and a retry would send nothing, so a
	 * failure is logged and the remaining recipients are still notified.
	 *
	 * @param DashboardShare $share        The share row.
	 * @param string         $sharerUserId The user who created the share.
	 * @param Dashboard      $dashboard    The shared dashboard.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-sharing/spec.md
	 */
	public function shared(
		DashboardShare $share,
		string $sharerUserId,
		Dashboard $dashboard,
	): void {
		$recipients = [];
		try {
			$recipients = $this->resolveRecipients(
				share: $share,
				excludeUserId: $sharerUserId
			);

			// The activity stream and digest get the same audience as the
			// notification (engagement-activity-digest D2, issue #713).
			$this->activity?->shared(
				dashboard: $dashboard,
				share: $share,
				actor: $sharerUserId,
				recipients: $recipients
			);
		} catch (Throwable $e) {
			$this->logFailure(exception: $e);
		}

		$subject = [
			$sharerUserId,
			(string)$dashboard->getName(),
			(string)$share->getPermissionLevel(),
		];
		foreach ($recipients as $recipientId) {
			try {
				$notification = $this->notificationManager->createNotification();
				// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
				$notification->setApp('launchpad')
					// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
					->setUser($recipientId)
					// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
					->setDateTime(new DateTime())
					// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
					->setObject('dashboard', (string)$share->getDashboardId())
					// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
					->setSubject('dashboard_shared', $subject);

				// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
				$this->notificationManager->notify($notification);
			} catch (Throwable $e) {
				$this->logFailure(exception: $e);
			}
		}//end foreach
	}//end shared()

	/**
	 * Publish a `dashboard_ownership_transferred` notification.
	 *
	 * @param string $newOwnerId    The new owner's user ID.
	 * @param int    $dashboardId   The dashboard ID.
	 * @param string $dashboardName The dashboard name.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-sharing/spec.md
	 */
	public function ownershipTransferred(
		string $newOwnerId,
		int $dashboardId,
		string $dashboardName,
	): void {
		$notification = $this->notificationManager->createNotification();
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$notification->setApp('launchpad')
			// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			->setUser($newOwnerId)
			// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			->setDateTime(new DateTime())
			// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			->setObject('dashboard', (string)$dashboardId)
			// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			->setSubject('dashboard_ownership_transferred', [$dashboardName]);

		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$this->notificationManager->notify($notification);
	}//end ownershipTransferred()

	/**
	 * Resolve the recipient user IDs for a share.
	 *
	 * @param DashboardShare $share         The share.
	 * @param string         $excludeUserId A user ID to exclude (the sharer).
	 *
	 * @return string[] The recipient user IDs.
	 */
	private function resolveRecipients(
		DashboardShare $share,
		string $excludeUserId,
	): array {
		if ($share->getShareType() === DashboardShare::SHARE_TYPE_USER) {
			$uid = (string)$share->getShareWith();
			if ($uid === $excludeUserId) {
				return [];
			}

			return [$uid];
		}

		// Group share: expand members.
		$groupId = (string)$share->getShareWith();
		$group = $this->groupManager->get(gid: $groupId);
		if ($group === null) {
			return [];
		}

		$recipients = [];
		foreach ($group->getUsers() as $user) {
			$uid = $user->getUID();
			if ($uid !== $excludeUserId) {
				$recipients[] = $uid;
			}
		}

		return $recipients;
	}//end resolveRecipients()

	/**
	 * Log an announcement that failed after its share was written.
	 *
	 * @param Throwable $exception The failure.
	 *
	 * @return void
	 */
	private function logFailure(Throwable $exception): void {
		$this->logger->error(
			message: 'Dashboard share announcement failed: ' . $exception::class,
			context: ['app' => Application::APP_ID, 'exceptionClass' => $exception::class, 'exception' => $exception]
		);
	}//end logFailure()
}//end class
