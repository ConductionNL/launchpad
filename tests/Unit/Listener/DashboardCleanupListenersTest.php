<?php

/**
 * Tests for the single-mapper dashboard cleanup listeners
 *
 * Each of these listeners removes (or revokes) one kind of row when a
 * dashboard is deleted. Until now they were pinned only by a recorded JSON
 * fixture; these tests state the expected mapper call, the uuid it is scoped
 * to and the log line it writes, per listener, in readable assertions.
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
use OCA\LaunchPad\Db\DashboardLockMapper;
use OCA\LaunchPad\Db\DashboardTranslationMapper;
use OCA\LaunchPad\Db\DashboardViewMapper;
use OCA\LaunchPad\Db\MetadataValueMapper;
use OCA\LaunchPad\Db\PublicShareMapper;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Listener\DashboardCleanupListener;
use OCA\LaunchPad\Listener\LocksListener;
use OCA\LaunchPad\Listener\MetadataValuesListener;
use OCA\LaunchPad\Listener\PublicSharesListener;
use OCA\LaunchPad\Listener\TranslationsListener;
use OCA\LaunchPad\Listener\ViewAnalyticsListener;
use OCP\EventDispatcher\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * The cleanup listeners that delegate to exactly one mapper method.
 *
 * @covers \OCA\LaunchPad\Listener\LocksListener
 * @covers \OCA\LaunchPad\Listener\MetadataValuesListener
 * @covers \OCA\LaunchPad\Listener\PublicSharesListener
 * @covers \OCA\LaunchPad\Listener\TranslationsListener
 * @covers \OCA\LaunchPad\Listener\ViewAnalyticsListener
 * @uses   \OCA\LaunchPad\Listener\DashboardCleanupListener
 * @uses   \OCA\LaunchPad\Event\DashboardDeletedEvent
 */
class DashboardCleanupListenersTest extends TestCase {

	/**
	 * Listener class, its mapper class, the mapper method and the logged
	 * success message (with the count 4 and uuid dash-9 filled in).
	 *
	 * @return array<string, array{0: class-string, 1: class-string, 2: string, 3: string}>
	 */
	public static function listeners(): array {
		return [
			'locks'          => [
				LocksListener::class,
				DashboardLockMapper::class,
				'deleteByDashboardUuid',
				'launchpad LocksListener: deleted 4 lock rows for dashboard dash-9',
			],
			'metadata'       => [
				MetadataValuesListener::class,
				MetadataValueMapper::class,
				'deleteByDashboard',
				'launchpad MetadataValuesListener: deleted 4 metadata rows for dashboard dash-9',
			],
			'public shares'  => [
				PublicSharesListener::class,
				PublicShareMapper::class,
				'revokeByDashboardUuid',
				'launchpad PublicSharesListener: soft-revoked 4 share(s) for dashboard dash-9',
			],
			'translations'   => [
				TranslationsListener::class,
				DashboardTranslationMapper::class,
				'deleteByDashboardUuid',
				'launchpad TranslationsListener: deleted 4 translation rows for dashboard dash-9',
			],
			'view analytics' => [
				ViewAnalyticsListener::class,
				DashboardViewMapper::class,
				'deleteByDashboard',
				'launchpad ViewAnalyticsListener: deleted 4 view rows for dashboard dash-9',
			],
		];
	}//end listeners()

	/**
	 * Build a deletion event.
	 *
	 * @param int|null $dashboardId The numeric id the event carries.
	 *
	 * @return DashboardDeletedEvent
	 */
	private function deleted(?int $dashboardId = null): DashboardDeletedEvent {
		return new DashboardDeletedEvent(
			dashboardUuid: 'dash-9',
			ownerUserId: 'bob',
			type: 'user',
			deletedAt: new DateTimeImmutable('2026-01-02T03:04:05+00:00'),
			dashboardId: $dashboardId
		);
	}//end deleted()

	/**
	 * Construct a listener with the given mapper and logger.
	 *
	 * @param class-string    $listenerClass The listener.
	 * @param MockObject      $mapper        The mapper double.
	 * @param LoggerInterface $logger        The logger double.
	 *
	 * @return DashboardCleanupListener
	 */
	private function build(string $listenerClass, MockObject $mapper, LoggerInterface $logger): DashboardCleanupListener {
		return new $listenerClass($mapper, $logger);
	}//end build()

	/**
	 * The listener calls its mapper once with the deleted dashboard's uuid
	 * and logs the returned count at debug level.
	 *
	 * @param class-string $listenerClass The listener.
	 * @param class-string $mapperClass   The mapper.
	 * @param string       $method        The mapper method.
	 * @param string       $message       The expected debug line.
	 *
	 * @return void
	 */
	#[DataProvider('listeners')]
	public function testCleansUpByUuidAndLogsTheCount(
		string $listenerClass,
		string $mapperClass,
		string $method,
		string $message
	): void {
		$mapper = $this->createMock($mapperClass);
		$mapper->expects($this->once())
			->method($method)
			->with('dash-9')
			->willReturn(4);

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('debug')
			->with($message, ['app' => 'launchpad']);
		$logger->expects($this->never())->method('warning');

		$this->build($listenerClass, $mapper, $logger)->handle($this->deleted());
	}//end testCleansUpByUuidAndLogsTheCount()

	/**
	 * A numeric id on the event does not change the scope: these listeners
	 * always clean up by uuid.
	 *
	 * @param class-string $listenerClass The listener.
	 * @param class-string $mapperClass   The mapper.
	 * @param string       $method        The mapper method.
	 *
	 * @return void
	 */
	#[DataProvider('listeners')]
	public function testAnEventWithANumericIdStillCleansUpByUuid(
		string $listenerClass,
		string $mapperClass,
		string $method
	): void {
		$mapper = $this->createMock($mapperClass);
		$mapper->expects($this->once())
			->method($method)
			->with('dash-9')
			->willReturn(0);

		$logger = $this->createMock(LoggerInterface::class);

		$this->build($listenerClass, $mapper, $logger)->handle($this->deleted(dashboardId: 42));
	}//end testAnEventWithANumericIdStillCleansUpByUuid()

	/**
	 * A mapper failure is logged as a warning naming the listener, the
	 * dashboard and the error, and is not rethrown.
	 *
	 * @param class-string $listenerClass The listener.
	 * @param class-string $mapperClass   The mapper.
	 * @param string       $method        The mapper method.
	 *
	 * @return void
	 */
	#[DataProvider('listeners')]
	public function testAMapperFailureIsLoggedAndSwallowed(
		string $listenerClass,
		string $mapperClass,
		string $method
	): void {
		$mapper = $this->createMock($mapperClass);
		$mapper->method($method)->willThrowException(new RuntimeException('table locked'));

		$short = substr((string)strrchr($listenerClass, '\\'), 1);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())
			->method('warning')
			->with(
				'launchpad ' . $short . ': failed for dashboard dash-9: table locked',
				['app' => 'launchpad']
			);
		$logger->expects($this->never())->method('debug');

		$this->build($listenerClass, $mapper, $logger)->handle($this->deleted());
	}//end testAMapperFailureIsLoggedAndSwallowed()

	/**
	 * Any other event touches neither the mapper nor the logger.
	 *
	 * @param class-string $listenerClass The listener.
	 * @param class-string $mapperClass   The mapper.
	 * @param string       $method        The mapper method.
	 *
	 * @return void
	 */
	#[DataProvider('listeners')]
	public function testOtherEventsAreIgnored(
		string $listenerClass,
		string $mapperClass,
		string $method
	): void {
		$mapper = $this->createMock($mapperClass);
		$mapper->expects($this->never())->method($method);

		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->never())->method($this->anything());

		$this->build($listenerClass, $mapper, $logger)->handle(new Event());
	}//end testOtherEventsAreIgnored()
}//end class
