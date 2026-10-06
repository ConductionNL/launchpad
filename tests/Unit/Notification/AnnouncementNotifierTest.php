<?php

/**
 * AnnouncementNotifierTest
 *
 * REQ-ANN-003 "Follow Privacy": the notification a follower receives reads
 * "New announcement in Privacy: Nieuwe privacyverklaring" and opens the
 * workspace. The subject parameters are the ones AnnouncementService sends.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Notification
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Notification;

use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Notification\Notifier;
use OCA\LaunchPad\Service\AnnouncementService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\L10N\IFactory;
use OCP\Notification\INotification;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the announcement_published notification.
 */
class AnnouncementNotifierTest extends TestCase {
	/**
	 * The parsed subject names the category and the title.
	 *
	 * @return void
	 */
	public function testAnnouncementPublishedReadsCategoryAndTitle(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(static fn (string $text, array $params = []): string => vsprintf($text, $params));
		$factory = $this->createMock(IFactory::class);
		$factory->method('get')->willReturn($l10n);
		$urls = $this->createMock(IURLGenerator::class);
		$urls->method('linkToRouteAbsolute')->willReturn('https://cloud.example/apps/launchpad/');

		$parsed       = null;
		$link         = null;
		$notification = $this->createMock(INotification::class);
		$notification->method('getApp')->willReturn('launchpad');
		$notification->method('getSubject')->willReturn(AnnouncementService::SUBJECT_PUBLISHED);
		$notification->method('getSubjectParameters')->willReturn(['Privacy', 'Nieuwe privacyverklaring']);
		$notification->method('getObjectId')->willReturn('0b6f6f0e-1111-4a4a-8b8b-123456789abc');
		$notification->method('setRichSubject')->willReturnSelf();
		$notification->method('setParsedSubject')->willReturnCallback(function (string $subject) use (&$parsed, $notification): INotification {
			$parsed = $subject;

			return $notification;
		});
		$notification->method('setLink')->willReturnCallback(function (string $url) use (&$link, $notification): INotification {
			$link = $url;

			return $notification;
		});

		$notifier = new Notifier(l10nFactory: $factory, urlGenerator: $urls, dashboardMapper: $this->createMock(DashboardMapper::class));
		$notifier->prepare(notification: $notification, languageCode: 'nl');

		$this->assertSame('New announcement in Privacy: Nieuwe privacyverklaring', $parsed);
		$this->assertSame('https://cloud.example/apps/launchpad/', $link);
	}//end testAnnouncementPublishedReadsCategoryAndTitle()
}//end class
