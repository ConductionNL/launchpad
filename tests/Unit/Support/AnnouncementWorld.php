<?php

/**
 * AnnouncementWorld
 *
 * Builds the real AnnouncementService on in-memory storage with four people
 * (admin; Karin in the editor group Redactie; Pieter in Medewerkers and
 * Burgerzaken; Sanne in no group), a movable clock and a record of the
 * notifications sent. Used by the service, controller and job tests.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Support
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Support;

use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\AnnouncementService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\ICommentsManager;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use Psr\Log\LoggerInterface;

/**
 * Announcement test world; use in a TestCase.
 */
trait AnnouncementWorld {
	/**
	 * Group memberships of the people in the scenarios.
	 */
	public const ANNOUNCEMENT_MEMBERS = [
		'admin'  => ['admin'],
		'karin'  => ['Redactie'],
		'pieter' => ['Medewerkers', 'Burgerzaken'],
		'sanne'  => [],
	];

	/**
	 * Clock, seconds since the epoch; tests move it.
	 *
	 * @var integer
	 */
	public int $clock = 1791190800;

	/**
	 * Notifications sent: [user, subject, parameters].
	 *
	 * @var array<int, array{0: string, 1: string, 2: array}>
	 */
	public array $sent = [];

	/**
	 * Rows.
	 *
	 * @var InMemoryAnnouncements
	 */
	public InMemoryAnnouncements $rows;

	/**
	 * Follows.
	 *
	 * @var InMemoryAnnouncementFollows
	 */
	public InMemoryAnnouncementFollows $follows;

	/**
	 * Service under test.
	 *
	 * @var AnnouncementService
	 */
	public AnnouncementService $service;

	/**
	 * Wire the real service on in-memory storage and fake people.
	 *
	 * @return AnnouncementService
	 */
	protected function buildAnnouncementWorld(): AnnouncementService {
		$this->rows    = new InMemoryAnnouncements();
		$this->follows = new InMemoryAnnouncementFollows();

		$settings = $this->createMock(AdminSettingMapper::class);
		$settings->method('getValue')->willReturnCallback(
			static fn (string $key, mixed $default = null): mixed => $key === 'announcement_editor_groups' ? ['Redactie'] : $default
		);

		$users = $this->createMock(IUserManager::class);
		$users->method('get')->willReturnCallback(function (string $uid): ?IUser {
			if (array_key_exists($uid, self::ANNOUNCEMENT_MEMBERS) === false) {
				return null;
			}

			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($uid);

			return $user;
		});
		$users->method('userExists')->willReturnCallback(static fn (string $uid): bool => array_key_exists($uid, self::ANNOUNCEMENT_MEMBERS));

		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $uid): bool => $uid === 'admin');
		$groups->method('groupExists')->willReturnCallback(static fn (string $gid): bool => in_array($gid, ['admin', 'Redactie', 'Medewerkers', 'Burgerzaken'], true));

		$comments = $this->createMock(ICommentsManager::class);
		$comments->method('getNumberOfCommentsForObjects')->willReturn([]);
		$comments->method('getForObject')->willReturn([]);

		$notifications = $this->createMock(INotificationManager::class);
		$notifications->method('createNotification')->willReturnCallback(function (): INotification {
			$notification = $this->createMock(INotification::class);
			$state        = new \stdClass();
			$notification->method('setApp')->willReturnSelf();
			$notification->method('setDateTime')->willReturnSelf();
			$notification->method('setObject')->willReturnSelf();
			$notification->method('setUser')->willReturnCallback(function (string $user) use ($notification, $state): INotification {
				$state->user = $user;

				return $notification;
			});
			$notification->method('setSubject')->willReturnCallback(function (string $subject, array $parameters = []) use ($notification, $state): INotification {
				$state->subject    = $subject;
				$state->parameters = $parameters;

				return $notification;
			});
			$notification->method('getUser')->willReturnCallback(static fn (): string => $state->user);
			$notification->method('getSubject')->willReturnCallback(static fn (): string => $state->subject);
			$notification->method('getSubjectParameters')->willReturnCallback(static fn (): array => $state->parameters);

			return $notification;
		});
		$notifications->method('notify')->willReturnCallback(function (INotification $notification): void {
			$this->sent[] = [$notification->getUser(), $notification->getSubject(), $notification->getSubjectParameters()];
		});

		$templates = $this->createMock(AdminTemplateService::class);
		$templates->method('getUserGroupIdsFor')->willReturnCallback(static fn (string $uid): array => self::ANNOUNCEMENT_MEMBERS[$uid] ?? []);

		$time = $this->createMock(ITimeFactory::class);
		$time->method('getTime')->willReturnCallback(fn (): int => $this->clock);

		$this->service = new AnnouncementService(
			announcements: $this->rows,
			follows: $this->follows,
			settings: $settings,
			groupManager: $groups,
			userManager: $users,
			comments: $comments,
			notifications: $notifications,
			time: $time,
			logger: $this->createMock(LoggerInterface::class),
			adminTemplates: $templates,
		);
		return $this->service;
	}//end buildAnnouncementWorld()

}//end trait
