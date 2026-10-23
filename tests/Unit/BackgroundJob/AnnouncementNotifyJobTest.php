<?php

/**
 * AnnouncementNotifyJobTest
 *
 * REQ-ANN-003 from the caller's side: the background job, constructed as
 * Nextcloud constructs it, notifies the follower of a scheduled announcement
 * once it is live, and a second run sends nothing.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\BackgroundJob
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\BackgroundJob;

use OCA\LaunchPad\BackgroundJob\AnnouncementNotifyJob;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Unit\Support\AnnouncementWorld;

/**
 * Tests for AnnouncementNotifyJob.
 */
class AnnouncementNotifyJobTest extends TestCase {
	use AnnouncementWorld;

	/**
	 * The job sends the due notification once.
	 *
	 * @return void
	 */
	public function testTheJobNotifiesAFollowerOnce(): void {
		$service = $this->buildAnnouncementWorld();
		$service->setFollowing(userId: 'pieter', category: 'Privacy', follow: true);
		$item = $service->create(userId: 'karin', data: ['title' => 'Nieuwe privacyverklaring', 'category' => 'Privacy', 'publishAt' => '2026-10-06T09:00:00Z']);
		$this->clock = strtotime('2026-10-06T08:00:00Z');
		$service->publish(userId: 'karin', uuid: $item->getUuid());

		$job = new AnnouncementNotifyJob(time: $this->createMock(ITimeFactory::class), announcements: $service);
		$run = new ReflectionMethod($job, 'run');

		$this->clock = strtotime('2026-10-06T09:01:00Z');
		$run->invoke($job, null);
		$run->invoke($job, null);

		$this->assertSame([['pieter', 'announcement_published', ['Privacy', 'Nieuwe privacyverklaring']]], $this->sent);
	}//end testTheJobNotifiesAFollowerOnce()
}//end class
