<?php

/**
 * AnnouncementServiceTest
 *
 * engagement-announcements REQ-ANN-001, 003, 005 through the real
 * AnnouncementService with the real mappers' logic on in-memory storage:
 * targeting, drafts, the publish window, author rights, the notice end time,
 * comment access and follower notifications sent once.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Exception\ForbiddenException;
use OCA\LaunchPad\Service\AnnouncementService;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\TestCase;
use Unit\Support\AnnouncementWorld;

/**
 * Tests for AnnouncementService.
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class AnnouncementServiceTest extends TestCase {
	use AnnouncementWorld;

	/**
	 * Wire the service.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->buildAnnouncementWorld();
	}//end setUp()

	/**
	 * REQ-ANN-001 "News for one group": Pieter sees it, Sanne does not and
	 * gets not found by id.
	 *
	 * @return void
	 */
	public function testNewsForOneGroupReachesOnlyItsMembers(): void {
		$draft = $this->service->create(userId: 'karin', data: ['title' => 'Inloopspreekuur privacy', 'category' => 'Privacy', 'targetGroups' => ['Medewerkers']]);
		$this->service->publish(userId: 'karin', uuid: $draft->getUuid());

		$this->assertSame(['Inloopspreekuur privacy'], array_column($this->service->listVisible(userId: 'pieter'), 'title'));
		$this->assertSame([], $this->service->listVisible(userId: 'sanne'));

		$this->expectException(DoesNotExistException::class);
		$this->service->get(userId: 'sanne', uuid: $draft->getUuid());
	}//end testNewsForOneGroupReachesOnlyItsMembers()

	/**
	 * REQ-ANN-001 "Reader cannot author".
	 *
	 * @return void
	 */
	public function testReaderCannotAuthor(): void {
		$this->assertFalse($this->service->canAuthor(userId: 'pieter'));
		$this->assertTrue($this->service->canAuthor(userId: 'karin'), 'members of the editor groups author');
		$this->assertTrue($this->service->canAuthor(userId: 'admin'));

		$this->expectException(ForbiddenException::class);
		$this->service->create(userId: 'pieter', data: ['title' => 'Mag ik dit?']);
	}//end testReaderCannotAuthor()

	/**
	 * REQ-ANN-001: a draft is shown to authors only.
	 *
	 * @return void
	 */
	public function testDraftsAreHiddenFromReaders(): void {
		$draft = $this->service->create(userId: 'karin', data: ['title' => 'Concept']);

		$this->assertSame([], $this->service->listVisible(userId: 'pieter'));
		$this->assertSame('Concept', $this->service->get(userId: 'karin', uuid: $draft->getUuid())['title']);
		$this->assertSame(['Concept'], array_column($this->service->listForAuthor(userId: 'karin'), 'title'));

		$this->expectException(DoesNotExistException::class);
		$this->service->get(userId: 'pieter', uuid: $draft->getUuid());
	}//end testDraftsAreHiddenFromReaders()

	/**
	 * REQ-ANN-005 "Maintenance banner": visible inside its window only.
	 *
	 * @return void
	 */
	public function testNoticeIsVisibleOnlyInsideItsWindow(): void {
		$notice = $this->service->create(
			userId: 'karin',
			data: [
				'kind'         => 'notice',
				'level'        => 'warning',
				'dismissible'  => false,
				'title'        => 'Onderhoud zaaksysteem zaterdag 08:00 tot 12:00',
				'targetGroups' => ['Burgerzaken'],
				'publishAt'    => '2026-10-09T17:00:00+02:00',
				'expiresAt'    => '2026-10-10T12:00:00+02:00',
			]
		);
		$this->service->publish(userId: 'karin', uuid: $notice->getUuid());

		$this->clock = strtotime('2026-10-09T16:59:00+02:00');
		$this->assertSame([], $this->service->listVisible(userId: 'pieter', kind: 'notice'), 'before the publish time');

		$this->clock = strtotime('2026-10-09T18:00:00+02:00');
		$visible     = $this->service->listVisible(userId: 'pieter', kind: 'notice');
		$this->assertCount(1, $visible);
		$this->assertSame('warning', $visible[0]['level']);
		$this->assertFalse($visible[0]['dismissible']);
		$this->assertSame([], $this->service->listVisible(userId: 'pieter', kind: 'news'), 'the kind filter keeps notices out of the news list');

		$this->clock = strtotime('2026-10-10T12:01:00+02:00');
		$this->assertSame([], $this->service->listVisible(userId: 'pieter', kind: 'notice'), 'after the end time');
	}//end testNoticeIsVisibleOnlyInsideItsWindow()

	/**
	 * REQ-ANN-005 "Notice without an end time".
	 *
	 * @return void
	 */
	public function testNoticeWithoutEndTimeCannotBePublished(): void {
		$notice = $this->service->create(userId: 'karin', data: ['kind' => 'notice', 'title' => 'Storing']);

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('A notice needs an end time');
		$this->service->publish(userId: 'karin', uuid: $notice->getUuid());
	}//end testNoticeWithoutEndTimeCannotBePublished()

	/**
	 * An end time before the publish time is refused.
	 *
	 * @return void
	 */
	public function testEndBeforeStartIsRefused(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service->create(userId: 'karin', data: ['title' => 'Omgekeerd', 'publishAt' => '2026-10-10T10:00:00Z', 'expiresAt' => '2026-10-10T09:00:00Z']);
	}//end testEndBeforeStartIsRefused()

	/**
	 * Unknown target groups are refused rather than silently reaching nobody.
	 *
	 * @return void
	 */
	public function testUnknownTargetGroupIsRefused(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->service->create(userId: 'karin', data: ['title' => 'Typo', 'targetGroups' => ['Medewerkerz']]);
	}//end testUnknownTargetGroupIsRefused()

	/**
	 * REQ-ANN-002: no comments when the author switched them off.
	 *
	 * @return void
	 */
	public function testCommentIsRefusedWhenCommentsAreOff(): void {
		$item = $this->service->create(userId: 'karin', data: ['title' => 'Geen discussie', 'allowComments' => false]);
		$this->service->publish(userId: 'karin', uuid: $item->getUuid());

		$this->expectException(ForbiddenException::class);
		$this->service->addComment(userId: 'pieter', uuid: $item->getUuid(), message: 'Toch een vraag');
	}//end testCommentIsRefusedWhenCommentsAreOff()

	/**
	 * REQ-ANN-002: no comment on an announcement that does not target you.
	 *
	 * @return void
	 */
	public function testCommentIsRefusedForANonTargetedReader(): void {
		$item = $this->service->create(userId: 'karin', data: ['title' => 'Alleen Medewerkers', 'targetGroups' => ['Medewerkers']]);
		$this->service->publish(userId: 'karin', uuid: $item->getUuid());

		$this->expectException(DoesNotExistException::class);
		$this->service->addComment(userId: 'sanne', uuid: $item->getUuid(), message: 'Hallo');
	}//end testCommentIsRefusedForANonTargetedReader()

	/**
	 * REQ-ANN-003 "Follow Privacy": a scheduled announcement notifies the
	 * follower once when it reaches its publish time, and never twice.
	 *
	 * @return void
	 */
	public function testFollowerIsNotifiedOnceWhenAScheduledAnnouncementGoesLive(): void {
		$this->service->setFollowing(userId: 'pieter', category: 'Privacy', follow: true);
		$this->service->setFollowing(userId: 'sanne', category: 'Privacy', follow: true);

		$item = $this->service->create(
			userId: 'karin',
			data: ['title' => 'Nieuwe privacyverklaring', 'category' => 'Privacy', 'targetGroups' => ['Medewerkers'], 'publishAt' => '2026-10-06T09:00:00Z']
		);
		$this->clock = strtotime('2026-10-06T08:00:00Z');
		$this->service->publish(userId: 'karin', uuid: $item->getUuid());
		$this->assertSame([], $this->sent, 'nothing before the publish time');
		$this->assertSame(0, $this->service->notifyDue());

		$this->clock = strtotime('2026-10-06T09:05:00Z');
		$this->assertSame(1, $this->service->notifyDue());
		$this->assertSame([['pieter', 'announcement_published', ['Privacy', 'Nieuwe privacyverklaring']]], $this->sent, 'Sanne follows but is not targeted');

		$this->assertSame(0, $this->service->notifyDue(), 'the job never sends twice');
		$this->assertCount(1, $this->sent);
	}//end testFollowerIsNotifiedOnceWhenAScheduledAnnouncementGoesLive()

	/**
	 * Publishing now notifies at once, and the job then skips it.
	 *
	 * @return void
	 */
	public function testPublishingNowNotifiesAtOnce(): void {
		$this->service->setFollowing(userId: 'pieter', category: 'Facilitair', follow: true);
		$item = $this->service->create(userId: 'karin', data: ['title' => 'Nieuwe werkplekken op de 3e verdieping', 'category' => 'Facilitair']);
		$this->service->publish(userId: 'karin', uuid: $item->getUuid());

		$this->assertCount(1, $this->sent);
		$this->assertSame(0, $this->service->notifyDue());

		$this->assertSame([], $this->service->setFollowing(userId: 'pieter', category: 'Facilitair', follow: false));
	}//end testPublishingNowNotifiesAtOnce()

	/**
	 * "Preview as": the targeting reaches Pieter and not Sanne.
	 *
	 * @return void
	 */
	public function testPreviewAsChecksThePerson(): void {
		$this->assertTrue($this->service->reachesPerson(userId: 'karin', groups: ['Burgerzaken'], readerId: 'pieter'));
		$this->assertFalse($this->service->reachesPerson(userId: 'karin', groups: ['Burgerzaken'], readerId: 'sanne'));
		$this->assertTrue($this->service->reachesPerson(userId: 'karin', groups: [], readerId: 'sanne'), 'no groups means everyone');
	}//end testPreviewAsChecksThePerson()
}//end class
