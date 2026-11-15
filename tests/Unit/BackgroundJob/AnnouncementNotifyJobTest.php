<?php

/**
 * AnnouncementNotifyJobTest
 *
 * The five-minute follow-notification job (engagement-announcements, D5).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\BackgroundJob
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

namespace Unit\BackgroundJob;

use OCA\LaunchPad\BackgroundJob\AnnouncementNotifyJob;
use OCA\LaunchPad\Service\AnnouncementFollowNotifier;
use OCP\AppFramework\Utility\ITimeFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use RuntimeException;

/**
 * Tests for AnnouncementNotifyJob.
 */
class AnnouncementNotifyJobTest extends TestCase {

	/**
	 * Run the job's protected run().
	 *
	 * @param AnnouncementNotifyJob $job The job.
	 *
	 * @return void
	 */
	private function runJob(AnnouncementNotifyJob $job): void {
		(new ReflectionMethod($job, 'run'))->invoke($job, null);
	}//end runJob()

	/**
	 * The job runs every five minutes and hands each run to the notifier.
	 *
	 * @return void
	 */
	public function testEveryFiveMinutesItNotifiesWhatIsDue(): void {
		$notifier = $this->createMock(AnnouncementFollowNotifier::class);
		$notifier->expects($this->once())->method('notifyDue')->willReturn(2);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->never())->method('warning');

		$job = new AnnouncementNotifyJob(time: $this->createMock(ITimeFactory::class), notifier: $notifier, logger: $logger);
		$this->assertSame(300, $job->getInterval());
		$this->runJob(job: $job);
	}//end testEveryFiveMinutesItNotifiesWhatIsDue()

	/**
	 * A failing run is logged, not thrown, so the next run still comes.
	 *
	 * @return void
	 */
	public function testAFailingRunIsLogged(): void {
		$notifier = $this->createMock(AnnouncementFollowNotifier::class);
		$notifier->method('notifyDue')->willThrowException(new RuntimeException('db down'));
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('warning')->with(
			'launchpad.announcement.notify_job_failed',
			['app' => 'launchpad', 'exception' => 'db down']
		);

		$this->runJob(job: new AnnouncementNotifyJob(time: $this->createMock(ITimeFactory::class), notifier: $notifier, logger: $logger));
	}//end testAFailingRunIsLogged()
}//end class
