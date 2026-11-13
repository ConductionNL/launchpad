<?php

/**
 * Tests for DashboardCleanupListener, the shared base of the cascade listeners
 *
 * The base owns the event filter, the log-and-continue contract and the log
 * line format; the subclasses only supply cleanUp() and the CLEANED phrase.
 * These tests drive the base through minimal subclasses so its contract is
 * proven independently of any mapper.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Listener
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Listener;

use DateTimeImmutable;
use Error;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Listener\DashboardCleanupListener;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Minimal subclass keeping the default CLEANED phrase.
 */
class RecordingCleanupListener extends DashboardCleanupListener {

	/**
	 * Uuids cleanUp() was called with.
	 *
	 * @var list<string>
	 */
	public array $seen = [];

	/**
	 * The count to report, or the throwable to raise.
	 *
	 * @var int|\Throwable
	 */
	public int|\Throwable $outcome = 0;

	/**
	 * Record the call and report the configured outcome.
	 *
	 * @param DashboardDeletedEvent $event The deletion.
	 *
	 * @return int
	 */
	protected function cleanUp(DashboardDeletedEvent $event): int {
		$this->seen[] = $event->getDashboardUuid();
		if ($this->outcome instanceof \Throwable) {
			throw $this->outcome;
		}

		return $this->outcome;
	}//end cleanUp()
}//end class

/**
 * Subclass overriding the CLEANED phrase.
 */
class PhrasedCleanupListener extends RecordingCleanupListener {
	protected const CLEANED = 'purged %d widgets';
}//end class

/**
 * @covers \OCA\LaunchPad\Listener\DashboardCleanupListener
 * @uses   \OCA\LaunchPad\Event\DashboardDeletedEvent
 */
class DashboardCleanupListenerTest extends TestCase {

	/**
	 * The logger.
	 *
	 * @var LoggerInterface|MockObject
	 */
	private LoggerInterface|MockObject $logger;

	/**
	 * Create the logger double.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->logger = $this->createMock(LoggerInterface::class);
	}//end setUp()

	/**
	 * Build a deletion event.
	 *
	 * @return DashboardDeletedEvent
	 */
	private function deleted(): DashboardDeletedEvent {
		return new DashboardDeletedEvent(
			dashboardUuid: 'uuid-5',
			ownerUserId: 'alice',
			type: 'user',
			deletedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00')
		);
	}//end deleted()

	/**
	 * Success logs the default phrase with the short class name and count.
	 *
	 * @return void
	 */
	public function testSuccessLogsTheDefaultPhraseWithTheShortClassName(): void {
		$listener = new RecordingCleanupListener(logger: $this->logger);
		$listener->outcome = 11;

		$this->logger->expects($this->once())
			->method('debug')
			->with(
				'launchpad RecordingCleanupListener: deleted 11 rows for dashboard uuid-5',
				['app' => 'launchpad']
			);
		$this->logger->expects($this->never())->method('warning');

		$listener->handle($this->deleted());

		$this->assertSame(['uuid-5'], $listener->seen);
	}//end testSuccessLogsTheDefaultPhraseWithTheShortClassName()

	/**
	 * A subclass's CLEANED constant replaces the phrase (late static binding).
	 *
	 * @return void
	 */
	public function testASubclassPhraseIsUsed(): void {
		$listener = new PhrasedCleanupListener(logger: $this->logger);
		$listener->outcome = 2;

		$this->logger->expects($this->once())
			->method('debug')
			->with(
				'launchpad PhrasedCleanupListener: purged 2 widgets for dashboard uuid-5',
				['app' => 'launchpad']
			);

		$listener->handle($this->deleted());
	}//end testASubclassPhraseIsUsed()

	/**
	 * An Error (not just an Exception) from cleanUp() is caught and logged.
	 *
	 * @return void
	 */
	public function testAnErrorIsCaughtAndLoggedAsAWarning(): void {
		$listener = new RecordingCleanupListener(logger: $this->logger);
		$listener->outcome = new Error('null deref');

		$this->logger->expects($this->once())
			->method('warning')
			->with(
				'launchpad RecordingCleanupListener: failed for dashboard uuid-5: null deref',
				['app' => 'launchpad']
			);
		$this->logger->expects($this->never())->method('debug');

		$listener->handle($this->deleted());

		$this->assertSame(['uuid-5'], $listener->seen);
	}//end testAnErrorIsCaughtAndLoggedAsAWarning()

	/**
	 * Other events never reach cleanUp() or the logger.
	 *
	 * @return void
	 */
	public function testOtherEventsNeverReachCleanUp(): void {
		$listener = new RecordingCleanupListener(logger: $this->logger);
		$this->logger->expects($this->never())->method($this->anything());

		$listener->handle(new Event());

		$this->assertSame([], $listener->seen);
	}//end testOtherEventsNeverReachCleanUp()
}//end class
