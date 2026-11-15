<?php

/**
 * AnnouncementFollowNotifierTest
 *
 * Followers of a category are told once when an announcement in it becomes
 * visible to them (engagement-announcements, REQ-ANN-003, D5).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
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

namespace Unit\Service;

use DateTime;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Db\AnnouncementFollow;
use OCA\LaunchPad\Db\AnnouncementFollowMapper;
use OCA\LaunchPad\Db\AnnouncementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\AnnouncementFollowNotifier;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Tests for AnnouncementFollowNotifier.
 */
class AnnouncementFollowNotifierTest extends TestCase {

	/**
	 * The announcement mapper.
	 *
	 * @var AnnouncementMapper&MockObject
	 */
	private AnnouncementMapper $announcements;

	/**
	 * The follow mapper.
	 *
	 * @var AnnouncementFollowMapper&MockObject
	 */
	private AnnouncementFollowMapper $follows;

	/**
	 * The notification manager.
	 *
	 * @var INotificationManager&MockObject
	 */
	private INotificationManager $notifications;

	/**
	 * The logger.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private LoggerInterface $logger;

	/**
	 * Who was notified, as "user|subject|category|title|objectId".
	 *
	 * @var list<string>
	 */
	private array $sent = [];

	/**
	 * Wire mappers, groups (pieter and sanne in Medewerkers, kees in Burgerzaken) and a recording notification manager.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->announcements = $this->createMock(AnnouncementMapper::class);
		$this->follows = $this->createMock(AnnouncementFollowMapper::class);
		$this->follows->method('findByCategory')->willReturnCallback(
			fn (string $category): array => ($category === 'Privacy') ? [$this->follow('pieter'), $this->follow('kees'), $this->follow('karin'), $this->follow('pieter')] : []
		);
		$this->notifications = $this->createMock(INotificationManager::class);
		$this->notifications->method('createNotification')->willReturnCallback(fn (): INotification => $this->recording());
		$this->logger = $this->createMock(LoggerInterface::class);
	}//end setUp()

	/**
	 * The notifier under test, at 2026-11-14 12:00.
	 *
	 * @return AnnouncementFollowNotifier
	 */
	private function notifier(): AnnouncementFollowNotifier {
		$groups = $this->createMock(AdminTemplateService::class);
		$groups->method('getUserGroupIdsFor')->willReturnCallback(
			static fn (string $userId): array => ['pieter' => ['Medewerkers'], 'kees' => ['Burgerzaken'], 'karin' => ['Medewerkers']][$userId] ?? []
		);
		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturnCallback(static fn (): DateTime => new DateTime('2026-11-14T12:00:00+00:00'));

		return new AnnouncementFollowNotifier(
			announcements: $this->announcements,
			follows: $this->follows,
			userGroups: $groups,
			notifications: $this->notifications,
			time: $time,
			logger: $this->logger
		);
	}//end notifier()

	/**
	 * A follow row.
	 *
	 * @param string $userId The follower.
	 *
	 * @return AnnouncementFollow
	 */
	private function follow(string $userId): AnnouncementFollow {
		$follow = new AnnouncementFollow();
		$follow->setUserId($userId);
		$follow->setCategory('Privacy');
		return $follow;
	}//end follow()

	/**
	 * A notification that records what it was given when it is sent.
	 *
	 * @return INotification
	 */
	private function recording(): INotification {
		$fields = [];
		$n = $this->createMock(INotification::class);
		foreach (['setApp', 'setUser', 'setDateTime', 'setObject', 'setSubject'] as $setter) {
			$n->method($setter)->willReturnCallback(
				function (...$args) use ($setter, $n, &$fields): INotification {
					$fields[$setter] = $args;
					return $n;
				}
			);
		}

		$this->notifications->method('notify')->willReturnCallback(
			function (INotification $sent) use ($n, &$fields): void {
				if ($sent === $n) {
					$this->sent[] = implode('|', [$fields['setUser'][0], $fields['setSubject'][0], ...$fields['setSubject'][1], $fields['setObject'][1]]);
				}
			}
		);

		return $n;
	}//end recording()

	/**
	 * A published announcement in Privacy for Medewerkers, by karin.
	 *
	 * @param string|null $publishAt When it goes live.
	 *
	 * @return Announcement
	 */
	private function announcement(?string $publishAt = '2026-11-14T11:55:00+00:00'): Announcement {
		$announcement = new Announcement();
		$announcement->setUuid('11111111-2222-4333-8444-555555555555');
		$announcement->setTitle('Nieuwe privacyverklaring');
		$announcement->setCategory('Privacy');
		$announcement->setStatus(Announcement::STATUS_PUBLISHED);
		$announcement->setAuthorId('karin');
		$announcement->setTargetGroupList(['Medewerkers']);
		$announcement->setPublishAt(($publishAt === null) ? null : new DateTime($publishAt));
		return $announcement;
	}//end announcement()

	/**
	 * A follower in the target group gets one notification; a follower outside it and the author get none; it is stamped.
	 *
	 * @return void
	 */
	public function testOnlyTargetedFollowersAreToldOnce(): void {
		$announcement = $this->announcement();
		$this->announcements->expects($this->once())->method('update')->with($announcement);

		$this->assertSame(1, $this->notifier()->notify(announcement: $announcement));
		$this->assertSame(['pieter|announcement_published|Privacy|Nieuwe privacyverklaring|11111111-2222-4333-8444-555555555555'], $this->sent);
		$this->assertEquals(new DateTime('2026-11-14T12:00:00+00:00'), $announcement->getNotifiedAt());

		$this->assertSame(0, $this->notifier()->notify(announcement: $announcement));
		$this->assertCount(1, $this->sent);
	}//end testOnlyTargetedFollowersAreToldOnce()

	/**
	 * An announcement for everyone reaches every follower but its author.
	 *
	 * @return void
	 */
	public function testAnAnnouncementForEveryoneReachesEveryFollower(): void {
		$announcement = $this->announcement();
		$announcement->setTargetGroupList([]);

		$this->assertSame(2, $this->notifier()->notify(announcement: $announcement));
		$this->assertSame(['pieter', 'kees'], array_map(static fn (string $line): string => explode('|', $line)[0], $this->sent));
	}//end testAnAnnouncementForEveryoneReachesEveryFollower()

	/**
	 * A scheduled announcement, a draft and one without a category are not notified, and only the scheduled one stays unstamped.
	 *
	 * @return void
	 */
	public function testNotYetLiveDraftsAndUncategorisedSendNothing(): void {
		$scheduled = $this->announcement(publishAt: '2026-11-15T08:00:00+00:00');
		$draft = $this->announcement();
		$draft->setStatus(Announcement::STATUS_DRAFT);
		$uncategorised = $this->announcement();
		$uncategorised->setCategory(null);
		$this->announcements->expects($this->once())->method('update')->with($uncategorised);

		$this->assertSame(0, $this->notifier()->notify(announcement: $scheduled));
		$this->assertSame(0, $this->notifier()->notify(announcement: $draft));
		$this->assertSame(0, $this->notifier()->notify(announcement: $uncategorised));
		$this->assertNull($scheduled->getNotifiedAt());
		$this->assertNotNull($uncategorised->getNotifiedAt());
		$this->assertSame([], $this->sent);
	}//end testNotYetLiveDraftsAndUncategorisedSendNothing()

	/**
	 * The due run notifies every live announcement not yet notified, and a failed send is logged, not thrown.
	 *
	 * @return void
	 */
	public function testNotifyDueSkipsNotifiedAndLogsAFailedSend(): void {
		$done = $this->announcement();
		$done->setNotifiedAt(new DateTime('2026-11-14T11:56:00+00:00'));
		$this->announcements->method('findLive')->willReturn([$done, $this->announcement()]);
		$this->notifications = $this->createMock(INotificationManager::class);
		$this->notifications->method('createNotification')->willThrowException(new RuntimeException('down'));
		$this->logger->expects($this->once())->method('warning')->with('launchpad.announcement.notify_failed');

		$this->assertSame(0, $this->notifier()->notifyDue());
	}//end testNotifyDueSkipsNotifiedAndLogsAFailedSend()
}//end class
