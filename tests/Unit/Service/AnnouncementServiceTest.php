<?php

/**
 * AnnouncementServiceTest
 *
 * Who may author, who sees what and when, the notice rules and the input
 * checks (engagement-announcements task 3; REQ-ANN-001, -004, -005).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/engagement-announcements/specs/announcements/spec.md#requirement-authors-publish-targeted-announcements-req-ann-001
 */

declare(strict_types=1);

namespace Unit\Service;

use DateTime;
use InvalidArgumentException;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Db\AnnouncementMapper;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\AnnouncementInput;
use OCA\LaunchPad\Service\AnnouncementService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AnnouncementService.
 *
 * @covers \OCA\LaunchPad\Service\AnnouncementService
 * @uses   \OCA\LaunchPad\Db\Announcement
 * @uses   \OCA\LaunchPad\Service\AnnouncementInput
 * @uses   \OCA\LaunchPad\Exception\ForbiddenException
 */
class AnnouncementServiceTest extends TestCase {

	/**
	 * Now, for every test: Friday 18:00.
	 */
	private const NOW = '2026-11-13T18:00:00+00:00';

	/**
	 * Group memberships by user.
	 *
	 * @var array<string, list<string>>
	 */
	private array $groupsOf = [
		'admin' => ['admin'],
		'karin' => ['redactie', 'Medewerkers'],
		'pieter' => ['Medewerkers', 'Burgerzaken'],
		'sanne' => [],
	];

	/**
	 * The stored announcements, by uuid.
	 *
	 * @var array<string, Announcement>
	 */
	private array $stored = [];

	/**
	 * The service over in-memory mappers.
	 *
	 * @param list<string> $editorGroups The editor groups setting.
	 *
	 * @return AnnouncementService
	 */
	private function service(array $editorGroups = ['redactie']): AnnouncementService {
		$mapper = $this->createMock(AnnouncementMapper::class);
		$mapper->method('insert')->willReturnCallback(function (Announcement $entity): Announcement {
			$this->stored[$entity->getUuid()] = $entity;
			return $entity;
		});
		$mapper->method('update')->willReturnCallback(function (Announcement $entity): Announcement {
			$this->stored[$entity->getUuid()] = $entity;
			return $entity;
		});
		$mapper->method('delete')->willReturnCallback(function (Announcement $entity): Announcement {
			unset($this->stored[$entity->getUuid()]);
			return $entity;
		});
		$mapper->method('findByUuid')->willReturnCallback(function (string $uuid): Announcement {
			if (isset($this->stored[$uuid]) === false) {
				throw new DoesNotExistException('none');
			}

			return $this->stored[$uuid];
		});
		$mapper->method('findLive')->willReturnCallback(fn (DateTime $now): array => array_values(array_filter(
			$this->stored,
			static fn (Announcement $item): bool => $item->getStatus() === 'published'
				&& ($item->getPublishAt() === null || $item->getPublishAt() <= $now)
				&& ($item->getExpiresAt() === null || $item->getExpiresAt() > $now)
		)));
		$mapper->method('findDrafts')->willReturnCallback(fn (?string $authorId): array => array_values(array_filter(
			$this->stored,
			static fn (Announcement $item): bool => $item->getStatus() === 'draft' && ($authorId === null || $item->getAuthorId() === $authorId)
		)));

		$settings = $this->createMock(AdminSettingMapper::class);
		$settings->method('getValue')->willReturnCallback(
			static fn (string $key, mixed $default = null): mixed => ($key === 'announcement_editor_groups') ? $editorGroups : $default
		);

		$userGroups = $this->createMock(AdminTemplateService::class);
		$userGroups->method('getUserGroupIdsFor')->willReturnCallback(fn (string $userId): array => ($this->groupsOf[$userId] ?? []));

		$groupManager = $this->createMock(IGroupManager::class);
		$groupManager->method('isAdmin')->willReturnCallback(static fn (string $userId): bool => $userId === 'admin');
		$groupManager->method('get')->willReturnCallback(function (string $groupId): ?IGroup {
			$members = array_keys(array_filter($this->groupsOf, static fn (array $groups): bool => in_array($groupId, $groups, true)));
			if ($members === []) {
				return null;
			}

			$group = $this->createMock(IGroup::class);
			$group->method('getUsers')->willReturn(array_map(function (string $uid): IUser {
				$user = $this->createMock(IUser::class);
				$user->method('getUID')->willReturn($uid);
				return $user;
			}, $members));
			return $group;
		});

		$time = $this->createMock(ITimeFactory::class);
		$time->method('getDateTime')->willReturnCallback(static fn (): DateTime => new DateTime(self::NOW));

		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('countUsersTotal')->willReturn(4);

		return new AnnouncementService($mapper, new AnnouncementInput(), $settings, $userGroups, $groupManager, $time, $userManager);
	}//end service()

	/**
	 * Karin publishes news for Medewerkers: Pieter sees it, Sanne neither lists nor reads it.
	 *
	 * @return void
	 */
	public function testNewsForOneGroupReachesOnlyThatGroup(): void {
		$service = $this->service();
		$draft = $service->create('karin', ['title' => 'Inloopspreekuur privacy', 'category' => 'Privacy', 'targetGroups' => ['Medewerkers']]);
		$this->assertSame('draft', $draft->getStatus());
		$this->assertSame([], $service->visibleTo('pieter'));

		$published = $service->publish('karin', $draft->getUuid());
		$this->assertSame('published', $published->getStatus());
		$this->assertSame(self::NOW, $published->getPublishAt()->format(DATE_ATOM));

		$this->assertSame(['Inloopspreekuur privacy'], array_map(static fn (Announcement $a): string => $a->getTitle(), $service->visibleTo('pieter')));
		$this->assertSame([], $service->visibleTo('sanne'));
		$this->assertSame('Inloopspreekuur privacy', $service->findFor('pieter', $draft->getUuid())->getTitle());

		$this->expectException(DoesNotExistException::class);
		$service->findFor('sanne', $draft->getUuid());
	}//end testNewsForOneGroupReachesOnlyThatGroup()

	/**
	 * Without target groups an announcement reaches everyone.
	 *
	 * @return void
	 */
	public function testNoTargetGroupsReachesEveryone(): void {
		$service = $this->service();
		$service->publish('karin', $service->create('karin', ['title' => 'Nieuwe werkplekken op de 3e verdieping'])->getUuid());

		$this->assertCount(1, $service->visibleTo('sanne'));
		$this->assertSame(4, $service->reach([]));
	}//end testNoTargetGroupsReachesEveryone()

	/**
	 * A reader cannot author, and an author cannot change someone else's announcement.
	 *
	 * @return void
	 */
	public function testOnlyEditorsAuthorTheirOwn(): void {
		$service = $this->service();
		$this->assertTrue($service->isAuthor('karin'));
		$this->assertTrue($service->isAuthor('admin'));
		$this->assertFalse($service->isAuthor('pieter'));
		$this->assertFalse($this->service(editorGroups: [])->isAuthor('karin'));

		try {
			$service->create('pieter', ['title' => 'Mag ik dit?']);
			$this->fail('A reader wrote an announcement.');
		} catch (ForbiddenException $e) {
			$this->assertSame([], $this->stored);
		}

		$adminNews = $service->create('admin', ['title' => 'Van de beheerder']);
		$service->publish('admin', $adminNews->getUuid());
		$this->expectException(ForbiddenException::class);
		$service->update('karin', $adminNews->getUuid(), ['title' => 'Overschreven']);
	}//end testOnlyEditorsAuthorTheirOwn()

	/**
	 * A draft is visible to its author and to administrators only.
	 *
	 * @return void
	 */
	public function testDraftsAreHiddenFromReaders(): void {
		$service = $this->service();
		$draft = $service->create('karin', ['title' => 'Concept']);

		$this->assertSame([$draft], $service->draftsFor('karin'));
		$this->assertSame([$draft], $service->draftsFor('admin'));
		$this->assertSame([], $service->draftsFor('pieter'));
		$this->assertSame('Concept', $service->findFor('admin', $draft->getUuid())->getTitle());

		$this->expectException(DoesNotExistException::class);
		$service->findFor('pieter', $draft->getUuid());
	}//end testDraftsAreHiddenFromReaders()

	/**
	 * A scheduled announcement is hidden until its publish time and after its end time.
	 *
	 * @return void
	 */
	public function testTheTimeWindowIsRespected(): void {
		$service = $this->service();
		$later = $service->create('karin', ['title' => 'Later', 'publishAt' => '2026-11-14T08:00:00+00:00']);
		$past = $service->create('karin', ['title' => 'Voorbij', 'publishAt' => '2026-11-01T08:00:00+00:00', 'expiresAt' => '2026-11-13T12:00:00+00:00']);
		$service->publish('karin', $later->getUuid());

		$this->assertSame([], $service->visibleTo('pieter'));

		try {
			$service->publish('karin', $past->getUuid());
			$this->fail('An announcement that already ended was published.');
		} catch (InvalidArgumentException $e) {
			$this->assertSame('The end time has already passed', $e->getMessage());
		}

		try {
			$service->update('karin', $later->getUuid(), ['expiresAt' => '2026-11-14T07:00:00+00:00']);
			$this->fail('A published announcement was given an end before its start.');
		} catch (InvalidArgumentException $e) {
			$this->assertSame('The end time must be after the publish time', $e->getMessage());
		}

		$this->expectException(DoesNotExistException::class);
		$service->findFor('pieter', $later->getUuid());
	}//end testTheTimeWindowIsRespected()

	/**
	 * A notice needs an end time, takes a level, and shows only in its period.
	 *
	 * @return void
	 */
	public function testANoticeNeedsAnEndTime(): void {
		$service = $this->service();
		$notice = $service->create('karin', ['kind' => 'notice', 'title' => 'Onderhoud zaaksysteem zaterdag 08:00 tot 12:00', 'targetGroups' => ['Burgerzaken']]);
		$this->assertSame('info', $notice->getLevel());

		try {
			$service->publish('karin', $notice->getUuid());
			$this->fail('A notice without an end time was published.');
		} catch (InvalidArgumentException $e) {
			$this->assertSame('A notice needs an end time', $e->getMessage());
			$this->assertSame('draft', $this->stored[$notice->getUuid()]->getStatus());
		}

		$service->update('karin', $notice->getUuid(), ['level' => 'warning', 'dismissible' => false, 'expiresAt' => '2026-11-14T12:00:00+00:00']);
		$published = $service->publish('karin', $notice->getUuid());

		$this->assertSame('warning', $published->getLevel());
		$this->assertSame(0, $published->getDismissible());
		$this->assertCount(1, $service->visibleTo('pieter'));
		$this->assertSame([], $service->visibleTo('karin'));

		$this->expectException(InvalidArgumentException::class);
		$service->update('karin', $notice->getUuid(), ['expiresAt' => null]);
	}//end testANoticeNeedsAnEndTime()

	/**
	 * Invalid input is refused with a message.
	 *
	 * @return void
	 */
	public function testInvalidInputIsRefused(): void {
		$service = $this->service();
		$cases = [
			[[], 'A title of 1 to 255 characters is required'],
			[['title' => '  '], 'A title of 1 to 255 characters is required'],
			[['title' => 'x', 'kind' => 'poster'], 'kind must be news or notice'],
			[['title' => 'x', 'level' => 'red'], 'level must be info or warning'],
			[['title' => 'x', 'targetGroups' => 'Medewerkers'], 'targetGroups must be a list of group ids'],
			[['title' => 'x', 'publishAt' => 'tomorrow-ish'], 'publishAt must be an ISO 8601 date'],
			[['title' => 'x', 'category' => str_repeat('c', 65)], 'A category of 1 to 64 characters is required'],
		];
		foreach ($cases as [$input, $message]) {
			try {
				$service->create('karin', $input);
				$this->fail('Accepted ' . json_encode($input));
			} catch (InvalidArgumentException $e) {
				$this->assertSame($message, $e->getMessage());
			}
		}

		$this->assertSame([], $this->stored);
	}//end testInvalidInputIsRefused()

	/**
	 * The reach of target groups counts each person once.
	 *
	 * @return void
	 */
	public function testReachCountsEachPersonOnce(): void {
		$service = $this->service();

		$this->assertSame(2, $service->reach(['Medewerkers', 'Burgerzaken']));
		$this->assertSame(0, $service->reach(['Bestaat-niet']));
	}//end testReachCountsEachPersonOnce()

	/**
	 * Deleting needs the author or an administrator; afterwards it is gone.
	 *
	 * @return void
	 */
	public function testDelete(): void {
		$service = $this->service();
		$news = $service->create('karin', ['title' => 'Weg ermee']);
		$service->publish('karin', $news->getUuid());

		try {
			$service->delete('pieter', $news->getUuid());
			$this->fail('A reader deleted an announcement.');
		} catch (ForbiddenException) {
			$this->assertArrayHasKey($news->getUuid(), $this->stored);
		}

		$service->delete('karin', $news->getUuid());
		$this->assertSame([], $this->stored);
	}//end testDelete()
}//end class
