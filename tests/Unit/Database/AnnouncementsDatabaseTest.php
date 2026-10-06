<?php

/**
 * AnnouncementsDatabaseTest
 *
 * engagement-announcements against a real database and Nextcloud's real
 * comments manager: the migration's columns accept what the service writes,
 * the window and targeting SQL find a published item, a like and a comment
 * land in the comments service and come back as counts, a follow is stored
 * once, and a person's follows go when the person is deleted.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Database
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Database;

use OCA\LaunchPad\Db\AnnouncementFollowMapper;
use OCA\LaunchPad\Service\AnnouncementService;
use OCP\Comments\ICommentsManager;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\Server;
use OCP\User\Events\UserDeletedEvent;

/**
 * @SuppressWarnings(PHPMD.StaticAccess) Server::get() is the only way in from a test.
 */
class AnnouncementsDatabaseTest extends RealDatabaseTestCase {
	/**
	 * A published news item is stored, read back through the window SQL by a
	 * member, liked and commented through the real comments manager.
	 *
	 * @return void
	 */
	public function testPublishLikeAndCommentRoundTrip(): void {
		$author = $this->makeUser(prefix: 'db-ann-author');
		$reader = $this->makeUser(prefix: 'db-ann-reader');
		$other  = $this->makeUser(prefix: 'db-ann-other');

		$groups  = Server::get(IGroupManager::class);
		$groupId = $this->uniqueId(prefix: 'db-ann-group');
		$group   = $groups->createGroup(gid: $groupId);
		$users   = Server::get(IUserManager::class);
		$group->addUser(user: $users->get(uid: $reader));
		$groups->get(gid: 'admin')?->addUser(user: $users->get(uid: $author));
		$this->cleanup(static function () use ($groups, $groupId): void {
			$groups->get(gid: $groupId)?->delete();
		});

		$service = Server::get(AnnouncementService::class);
		$created = $service->create(
			userId: $author,
			data: ['title' => 'Nieuwe werkplekken op de 3e verdieping', 'category' => 'Facilitair', 'targetGroups' => [$groupId], 'allowComments' => true]
		);
		$uuid    = $created->getUuid();
		$this->cleanup(function () use ($uuid): void {
			Server::get(ICommentsManager::class)->deleteCommentsAtObject(objectType: AnnouncementService::OBJECT_TYPE, objectId: $uuid);
			$this->deleteRows('launchpad_announcements', 'uuid', $uuid);
		});

		$rows = $this->storedRows('launchpad_announcements', 'uuid', $uuid);
		$this->assertCount(1, $rows);
		$this->assertColumnsFilled($rows[0], ['uuid', 'kind', 'title', 'status', 'author_id', 'created_at', 'updated_at', 'level']);

		$service->publish(userId: $author, uuid: $uuid);
		$this->assertSame([$uuid], array_column($service->listVisible(userId: $reader), 'uuid'), 'a member sees it');
		$this->assertSame([], array_column($service->listVisible(userId: $other), 'uuid'), 'a non-member does not');

		$service->setLiked(userId: $reader, uuid: $uuid, liked: true);
		$service->setLiked(userId: $reader, uuid: $uuid, liked: true);
		$service->addComment(userId: $reader, uuid: $uuid, message: 'Komen er ook sta-bureaus?');

		$seen = $service->get(userId: $reader, uuid: $uuid);
		$this->assertSame(1, $seen['likeCount'], 'liking twice is one like');
		$this->assertSame(1, $seen['commentCount']);
		$this->assertTrue($seen['likedByMe']);
		$this->assertSame(['Komen er ook sta-bureaus?'], array_column($service->listComments(userId: $reader, uuid: $uuid), 'message'));

		$service->setLiked(userId: $reader, uuid: $uuid, liked: false);
		$this->assertSame(0, $service->get(userId: $reader, uuid: $uuid)['likeCount']);
	}//end testPublishLikeAndCommentRoundTrip()

	/**
	 * A follow is stored once and removed with the person.
	 *
	 * @return void
	 */
	public function testFollowIsStoredOnceAndGoesWithThePerson(): void {
		$person  = $this->makeUser(prefix: 'db-ann-follow');
		$follows = Server::get(AnnouncementFollowMapper::class);
		$this->cleanup(function () use ($person): void {
			$this->deleteRows('launchpad_ann_follows', 'user_id', $person);
		});

		$follows->follow(userId: $person, category: 'Privacy');
		$follows->follow(userId: $person, category: 'Privacy');
		$this->assertSame(['Privacy'], $follows->findCategoriesOf(userId: $person));
		$this->assertContains($person, $follows->findFollowersOf(category: 'Privacy'));

		$user = Server::get(IUserManager::class)->get(uid: $person);
		Server::get(IEventDispatcher::class)->dispatchTyped(event: new UserDeletedEvent(user: $user));
		$this->assertSame([], $this->storedRows('launchpad_ann_follows', 'user_id', $person), 'UserDeletedListener removes the follows');
	}//end testFollowIsStoredOnceAndGoesWithThePerson()
}//end class
