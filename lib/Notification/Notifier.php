<?php

/**
 * Notifier
 *
 * INotifier implementation for LaunchPad. Renders `dashboard_shared` and
 * `dashboard_ownership_transferred` notifications in the Nextcloud bell,
 * activity stream, and email digest. REQ-SHARE-011.
 *
 * @category  Notification
 * @package   OCA\LaunchPad\Notification
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

namespace OCA\LaunchPad\Notification;

use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\DashboardMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

/**
 * LaunchPad notification renderer.
 *
 * Handles four subjects:
 * - `dashboard_shared` — published when a dashboard is shared with a user or
 *   when a share's permission_level is upgraded (REQ-SHARE-008).
 * - `dashboard_ownership_transferred` — published when a UserDeletedEvent
 *   causes ownership of a dashboard to be transferred to a new owner
 *   (REQ-SHARE-013).
 * - `mentioned_in_comment` — published when a user is `@-mentioned` inside
 *   a dashboard comment (REQ-CMNT-006). For this subject the
 *   `INotification::getObjectId()` is the dashboard UUID directly (not
 *   the DB id), so the URL builder takes a different branch.
 * - `dashboard_template_resynced` — published by
 *   {@see \OCA\LaunchPad\Service\TemplateResyncService} when an admin
 *   re-sync changes a user's provisioned copy (REQ-RESYNC-005).
 */
class Notifier implements INotifier {
	/**
	 * Constructor
	 *
	 * @param IFactory $l10nFactory The L10N factory.
	 * @param IURLGenerator $urlGenerator The URL generator.
	 * @param DashboardMapper $dashboardMapper The dashboard mapper.
	 */
	public function __construct(
		private readonly IFactory $l10nFactory,
		private readonly IURLGenerator $urlGenerator,
		private readonly DashboardMapper $dashboardMapper,
	) {
	}//end __construct()

	/**
	 * Return the notifier app ID.
	 *
	 * @return string The app ID.
	 */
	public function getID(): string {
		return Application::APP_ID;
	}//end getID()

	/**
	 * Return a human-readable notifier name.
	 *
	 * @return string The notifier name.
	 */
	public function getName(): string {
		return $this->l10nFactory->get(app: Application::APP_ID)->t('LaunchPad');
	}//end getName()

	/**
	 * Prepare and render an INotification for display.
	 *
	 * Handles `dashboard_shared` and `dashboard_ownership_transferred`.
	 * Throws `UnknownNotificationException` for any other subject so that
	 * the Nextcloud notification chain can pass it to the next notifier.
	 *
	 * @param INotification $notification The raw notification.
	 * @param string $languageCode The language code for the recipient.
	 *
	 * @return INotification The prepared notification.
	 *
	 * @throws UnknownNotificationException When the subject is not handled.
	 */
	public function prepare(
		INotification $notification,
		string $languageCode,
	): INotification {
		if ($notification->getApp() !== Application::APP_ID) {
			throw new UnknownNotificationException(
				message: 'Unknown app: ' . $notification->getApp()
			);
		}

		$l = $this->l10nFactory->get(
			app: Application::APP_ID,
			lang: $languageCode
		);

		$url  = $this->linkFor(notification: $notification);
		$copy = $this->copyFor(notification: $notification, l: $l);

		$notification->setRichSubject(subject: $copy->richSubject, parameters: []);
		$notification->setParsedSubject(subject: $copy->parsedSubject);
		$notification->setRichMessage(message: $copy->message, parameters: []);
		$notification->setParsedMessage(message: $copy->message);
		$notification->setLink(link: $url);

		return $notification;
	}//end prepare()

	/**
	 * Build the deep link of a notification.
	 *
	 * The mention subject ships the dashboard UUID directly as the
	 * notification's objectId; the share / transfer / resync subjects ship
	 * the dashboard DB id (legacy contract). REQ-CMNT-006.
	 *
	 * @param INotification $notification The notification.
	 *
	 * @return string The URL.
	 */
	private function linkFor(INotification $notification): string {
		if ($notification->getSubject() === 'mentioned_in_comment') {
			return $this->buildDashboardUrlFromUuid(uuid: $notification->getObjectId());
		}

		// The app catalogue (launcher-app-catalogue): a request opens the admin
		// section where it is decided; a decision opens LaunchPad, where Add lists the catalogue.
		if ($notification->getSubject() === 'app_requested') {
			return $this->urlGenerator->linkToRouteAbsolute(
				routeName: 'settings.AdminSettings.index',
				arguments: ['section' => 'launchpad']
			);
		}

		if ($notification->getSubject() === 'app_request_decided') {
			return $this->buildDashboardUrl(objectId: '');
		}

		return $this->buildDashboardUrl(objectId: $notification->getObjectId());
	}//end linkFor()

	/**
	 * Render the subject and message text of a notification.
	 *
	 * @param INotification $notification The notification.
	 * @param IL10N         $l            The L10N instance.
	 *
	 * @return NotificationCopy The rendered text.
	 *
	 * @throws UnknownNotificationException When the subject is not handled.
	 */
	private function copyFor(INotification $notification, IL10N $l): NotificationCopy {
		$params = $notification->getSubjectParameters();

		return match ($notification->getSubject()) {
			'mentioned_in_comment' => $this->mentionCopy(params: $params, l: $l),
			'dashboard_shared' => $this->sharedCopy(params: $params, l: $l),
			'dashboard_ownership_transferred' => $this->transferredCopy(params: $params, l: $l),
			'dashboard_template_resynced' => $this->resyncedCopy(params: $params, l: $l),
			'app_requested' => $this->appRequestedCopy(params: $params, l: $l),
			'app_request_decided' => $this->appDecidedCopy(params: $params, l: $l),
			default => throw new UnknownNotificationException(
				message: 'Unknown subject: ' . $notification->getSubject()
			),
		};
	}//end copyFor()

	/**
	 * Render a `mentioned_in_comment` notification (REQ-CMNT-006).
	 *
	 * Subject parameters: [authorUserId, dashboardUuid].
	 *
	 * @param array $params The subject parameters.
	 * @param IL10N $l      The L10N instance.
	 *
	 * @return NotificationCopy The rendered text.
	 */
	private function mentionCopy(array $params, IL10N $l): NotificationCopy {
		$copy = new NotificationCopy();
		$copy->richSubject   = $l->t(
			'%1$s mentioned you in a dashboard comment',
			[(string)($params[0] ?? '')]
		);
		$copy->parsedSubject = $copy->richSubject;
		$copy->message       = $l->t('Open the dashboard to read the comment');

		return $copy;
	}//end mentionCopy()

	/**
	 * Render a `dashboard_shared` notification.
	 *
	 * Subject parameters: [sharerUserId, dashboardName, permissionLevel].
	 *
	 * @param array $params The subject parameters.
	 * @param IL10N $l      The L10N instance.
	 *
	 * @return NotificationCopy The rendered text.
	 */
	private function sharedCopy(array $params, IL10N $l): NotificationCopy {
		$names = [($params[0] ?? ''), ($params[1] ?? '')];

		$copy = new NotificationCopy();
		$copy->richSubject   = $l->t('%1$s shared **%2$s** with you', $names);
		$copy->parsedSubject = $l->t('%1$s shared %2$s with you', $names);
		$copy->message       = $this->permissionLabel(l: $l, level: ($params[2] ?? ''));

		return $copy;
	}//end sharedCopy()

	/**
	 * Render a `dashboard_ownership_transferred` notification.
	 *
	 * Subject parameters: [dashboardName].
	 *
	 * @param array $params The subject parameters.
	 * @param IL10N $l      The L10N instance.
	 *
	 * @return NotificationCopy The rendered text.
	 */
	private function transferredCopy(array $params, IL10N $l): NotificationCopy {
		$name = [($params[0] ?? '')];

		$copy = new NotificationCopy();
		$copy->richSubject   = $l->t('**%1$s** is now yours', $name);
		$copy->parsedSubject = $l->t('%1$s is now yours', $name);
		$copy->message       = $l->t(
			'Ownership transferred after the previous owner was removed'
		);

		return $copy;
	}//end transferredCopy()

	/**
	 * Render a `dashboard_template_resynced` notification (REQ-RESYNC-005
	 * "Affected users are notified").
	 *
	 * Subject parameters: [dashboardName].
	 *
	 * @param array $params The subject parameters.
	 * @param IL10N $l      The L10N instance.
	 *
	 * @return NotificationCopy The rendered text.
	 */
	private function resyncedCopy(array $params, IL10N $l): NotificationCopy {
		$name = [($params[0] ?? '')];

		$copy = new NotificationCopy();
		$copy->richSubject   = $l->t('An administrator updated your **%1$s** dashboard', $name);
		$copy->parsedSubject = $l->t('An administrator updated your %1$s dashboard', $name);
		$copy->message       = $l->t(
			'The dashboard template you received was updated by an administrator'
		);

		return $copy;
	}//end resyncedCopy()

	/**
	 * Copy for `app_requested`: a user asked for an app the catalogue lacks
	 * (launcher-app-catalogue, REQ-ACAT-003).
	 *
	 * @param array<int, string> $params [requester display name, app name].
	 * @param IL10N              $l      The L10N instance.
	 *
	 * @return NotificationCopy
	 *
	 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-users-can-ask-for-a-missing-app-req-acat-003
	 */
	private function appRequestedCopy(array $params, IL10N $l): NotificationCopy {
		$names = [(string)($params[0] ?? ''), (string)($params[1] ?? '')];

		$copy = new NotificationCopy();
		$copy->richSubject   = $l->t('%1$s asked for the app **%2$s**', $names);
		$copy->parsedSubject = $l->t('%1$s asked for the app %2$s', $names);
		$copy->message       = $l->t('Approve or decline it under App catalogue in the LaunchPad admin settings');

		return $copy;
	}//end appRequestedCopy()

	/**
	 * Copy for `app_request_decided`: the requester hears how their request
	 * was decided (launcher-app-catalogue, REQ-ACAT-004).
	 *
	 * @param array<int, string> $params [app name, `approved` or `declined`, the decline note].
	 * @param IL10N              $l      The L10N instance.
	 *
	 * @return NotificationCopy
	 *
	 * @spec openspec/changes/launcher-app-catalogue/specs/app-catalogue/spec.md#requirement-administrators-decide-on-requests-and-the-requester-hears-back-req-acat-004
	 */
	private function appDecidedCopy(array $params, IL10N $l): NotificationCopy {
		$name = (string)($params[0] ?? '');
		$copy = new NotificationCopy();

		if (($params[1] ?? '') === 'approved') {
			$copy->richSubject   = $l->t('**%1$s** is now in the app catalogue', [$name]);
			$copy->parsedSubject = $l->t('%1$s is now in the app catalogue', [$name]);
			$copy->message       = $l->t('Add it as a tile from Add on your dashboard');

			return $copy;
		}

		$args = [$name, (string)($params[2] ?? '')];
		$copy->richSubject   = $l->t('Your request for **%1$s** was declined: %2$s', $args);
		$copy->parsedSubject = $l->t('Your request for %1$s was declined: %2$s', $args);
		$copy->message       = $l->t('An administrator decided on your request in the app catalogue');

		return $copy;
	}//end appDecidedCopy()

	/**
	 * Build the deep-link URL for a dashboard.
	 *
	 * Falls back to the base index route when the objectId cannot be
	 * resolved to a UUID (e.g. when the dashboard was deleted between
	 * notification creation and rendering).
	 *
	 * @param string $objectId The dashboard DB ID (as string per INotification).
	 *
	 * @return string The URL.
	 */
	private function buildDashboardUrl(string $objectId): string {
		$base = $this->urlGenerator->linkToRouteAbsolute(
			routeName: 'launchpad.page.index'
		);

		if ($objectId === '') {
			return $base;
		}

		try {
			$dashboard = $this->dashboardMapper->find(id: (int)$objectId);
			$uuid = $dashboard->getUuid();
			if ($uuid !== null && $uuid !== '') {
				return $base . '?dashboard=' . urlencode(string: $uuid);
			}
		} catch (DoesNotExistException) {
			// Dashboard deleted — return base URL.
		}

		return $base;
	}//end buildDashboardUrl()

	/**
	 * Build a deep-link URL when the objectId is already the dashboard
	 * UUID — used by the comment-mention notification (REQ-CMNT-006).
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return string The URL.
	 */
	private function buildDashboardUrlFromUuid(string $uuid): string {
		$base = $this->urlGenerator->linkToRouteAbsolute(
			routeName: 'launchpad.page.index'
		);

		if ($uuid === '') {
			return $base;
		}

		return $base . '?dashboard=' . urlencode(string: $uuid);
	}//end buildDashboardUrlFromUuid()

	/**
	 * Return the human-readable label for a permission level.
	 *
	 * @param IL10N $l The L10N instance.
	 * @param string $level The permission level identifier.
	 *
	 * @return string The translated label.
	 */
	private function permissionLabel(
		IL10N $l,
		string $level,
	): string {
		return match ($level) {
			'full' => $l->t('Full access'),
			'add_only' => $l->t('Add-only access'),
			'view_only' => $l->t('View-only access'),
			default => $l->t('Shared access'),
		};
	}//end permissionLabel()
}//end class
