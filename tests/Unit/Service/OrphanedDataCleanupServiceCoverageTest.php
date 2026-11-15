<?php

/**
 * OrphanedDataCleanupService Coverage Test
 *
 * Covers the orchestrator paths the main test leaves open: requested
 * category-name normalisation, unregistered categories being skipped
 * in both scan and purge, unavailable categories during purge, the
 * rollback-and-rethrow failure path of a dry run, and the best-effort
 * Activity emission swallowing publisher failures.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\CleanupReporting;
use OCA\LaunchPad\Service\Cleanup\CategoryRegistryService;
use OCA\LaunchPad\Service\Cleanup\CleanupCategoryInterface;
use OCA\LaunchPad\Service\OrphanedDataCleanupService;
use OCP\Activity\Exceptions\IncompleteActivityException;
use OCP\Activity\IEvent;
use OCP\Activity\IManager as IActivityManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Additional unit tests for OrphanedDataCleanupService.
 *
 * @covers \OCA\LaunchPad\Service\OrphanedDataCleanupService
 * @uses   \OCA\LaunchPad\Db\CleanupResult
 * @uses   \OCA\LaunchPad\Service\CleanupReporting
 */
class OrphanedDataCleanupServiceCoverageTest extends TestCase {
	/**
	 * Registry mock.
	 *
	 * @var CategoryRegistryService&MockObject
	 */
	private $registry;

	/**
	 * Cache factory mock.
	 *
	 * @var ICacheFactory&MockObject
	 */
	private $cacheFactory;

	/**
	 * Cache mock returned by the factory.
	 *
	 * @var ICache&MockObject
	 */
	private $cache;

	/**
	 * DB connection mock (transaction tracking).
	 *
	 * @var IDBConnection&MockObject
	 */
	private $db;

	/**
	 * Activity manager mock.
	 *
	 * @var IActivityManager&MockObject
	 */
	private $activity;

	/**
	 * Logger mock.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private $logger;

	/**
	 * Service under test.
	 *
	 * @var OrphanedDataCleanupService
	 */
	private OrphanedDataCleanupService $service;

	/**
	 * Build all mocks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->registry = $this->createMock(CategoryRegistryService::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->cache = $this->createMock(ICache::class);
		$this->db = $this->createMock(IDBConnection::class);
		$this->activity = $this->createMock(IActivityManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);

		$this->cacheFactory->method('createDistributed')->willReturn($this->cache);

		$this->service = new OrphanedDataCleanupService(
			registry: $this->registry,
			cacheFactory: $this->cacheFactory,
			db: $this->db,
			reporting: new CleanupReporting(
				activityManager: $this->activity,
				logger: $this->logger
			)
		);
	}

	/**
	 * Build a category mock.
	 *
	 * @param int  $count     Count returned from scan/purge.
	 * @param bool $available Whether the category is available.
	 *
	 * @return CleanupCategoryInterface&MockObject The category.
	 */
	private function makeCategory(int $count, bool $available = true): CleanupCategoryInterface {
		$category = $this->createMock(CleanupCategoryInterface::class);
		$category->method('isAvailable')->willReturn($available);
		$category->method('scan')->willReturn($count);
		$category->method('purge')->willReturn($count);

		return $category;
	}

	/**
	 * A partial scan de-duplicates names, drops empty/non-string entries,
	 * skips unregistered categories and never touches the cache.
	 *
	 * @return void
	 */
	public function testPartialScanNormalisesNamesAndSkipsUnregistered(): void {
		$known = $this->makeCategory(4);
		$lookups = [];
		$this->registry->expects($this->never())->method('getCategoryNames');
		$this->registry->method('getCategoryByName')->willReturnCallback(
			function (string $name) use ($known, &$lookups): ?CleanupCategoryInterface {
				$lookups[] = $name;
				return $name === 'known' ? $known : null;
			}
		);
		$this->cacheFactory->expects($this->never())->method('createDistributed');

		/** @var array<int, string> $requested */
		$requested = ['known', '', 'ghost', 'known', 42];
		$result = $this->service->scan($requested);

		$this->assertSame(['known', 'ghost'], $lookups);
		$this->assertSame(['known' => 4], $result->getByCategory());
		$this->assertSame(['ghost'], $result->getSkipped());
		$this->assertSame(4, $result->getTotalRows());
		$this->assertFalse($result->isDryRun());
	}

	/**
	 * A real purge skips unregistered and unavailable categories, purges
	 * the rest with dryRun=false and logs the audit line.
	 *
	 * @return void
	 */
	public function testPurgeSkipsUnregisteredAndUnavailableCategories(): void {
		$live = $this->makeCategory(3);
		$live->expects($this->once())->method('purge')->with(false)->willReturn(3);
		$down = $this->makeCategory(9, false);
		$down->expects($this->never())->method('purge');

		$this->registry->method('getCategoryByName')->willReturnMap([
			['live', $live],
			['down', $down],
			['ghost', null],
		]);
		$this->db->expects($this->never())->method('beginTransaction');
		$this->cache->expects($this->once())->method('remove')
			->with(OrphanedDataCleanupService::CACHE_KEY_SCAN);

		$event = $this->createMock(IEvent::class);
		$event->method($this->anything())->willReturnSelf();
		$this->activity->method('generateEvent')->willReturn($event);
		$this->activity->expects($this->once())->method('publish')->with($event);

		$this->logger->expects($this->once())->method('info')->with(
			$this->matchesRegularExpression(
				'/^launchpad\.cleanup\.purge source=cli user=alice rows=3 duration_ms=\d+ categories=live$/'
			)
		);

		$result = $this->service->purge(['ghost', 'down', 'live'], false, 'alice', 'cli');

		$this->assertSame(['live' => 3], $result->getByCategory());
		$this->assertSame(['ghost', 'down'], $result->getSkipped());
		$this->assertSame(3, $result->getTotalRows());
	}

	/**
	 * A dry run whose category throws rolls back the open transaction
	 * once and rethrows the original exception.
	 *
	 * @return void
	 */
	public function testDryRunFailureRollsBackOpenTransactionAndRethrows(): void {
		$boom = new RuntimeException('category exploded');
		$category = $this->createMock(CleanupCategoryInterface::class);
		$category->method('isAvailable')->willReturn(true);
		$category->expects($this->once())->method('purge')->with(true)->willThrowException($boom);
		$this->registry->method('getCategoryByName')->with('shares')->willReturn($category);

		$this->db->expects($this->once())->method('beginTransaction');
		$this->db->expects($this->once())->method('inTransaction')->willReturn(true);
		$this->db->expects($this->once())->method('rollBack');
		$this->cacheFactory->expects($this->never())->method('createDistributed');
		$this->logger->expects($this->never())->method('info');

		try {
			$this->service->purge(['shares'], true);
			$this->fail('Expected the category exception to propagate.');
		} catch (RuntimeException $e) {
			$this->assertSame($boom, $e);
			$this->assertSame('category exploded', $e->getMessage());
		}
	}

	/**
	 * A dry-run failure with no transaction still open does not roll back.
	 *
	 * @return void
	 */
	public function testDryRunFailureWithoutOpenTransactionDoesNotRollBack(): void {
		$category = $this->createMock(CleanupCategoryInterface::class);
		$category->method('isAvailable')->willReturn(true);
		$category->method('purge')->willThrowException(new RuntimeException('gone'));
		$this->registry->method('getCategoryByName')->willReturn($category);

		$this->db->expects($this->once())->method('inTransaction')->willReturn(false);
		$this->db->expects($this->never())->method('rollBack');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('gone');

		$this->service->purge(['shares'], true);
	}

	/**
	 * A real-purge failure is rethrown without any transaction handling
	 * and without recording a purge outcome.
	 *
	 * @return void
	 */
	public function testRealPurgeFailureRethrowsWithoutTransactionHandling(): void {
		$category = $this->createMock(CleanupCategoryInterface::class);
		$category->method('isAvailable')->willReturn(true);
		$category->method('purge')->willThrowException(new RuntimeException('real failure'));
		$this->registry->method('getCategoryByName')->willReturn($category);

		$this->db->expects($this->never())->method('beginTransaction');
		$this->db->expects($this->never())->method('inTransaction');
		$this->db->expects($this->never())->method('rollBack');
		$this->cacheFactory->expects($this->never())->method('createDistributed');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('real failure');

		$this->service->purge(['shares'], false);
	}

	/**
	 * An IncompleteActivityException from publish is logged as a warning
	 * and does not fail the purge.
	 *
	 * @return void
	 */
	public function testIncompleteActivityIsLoggedAndSwallowed(): void {
		$this->registry->method('getCategoryNames')->willReturn(['live']);
		$this->registry->method('getCategoryByName')->willReturn($this->makeCategory(2));

		$event = $this->createMock(IEvent::class);
		$event->method($this->anything())->willReturnSelf();
		$this->activity->method('generateEvent')->willReturn($event);
		$this->activity->expects($this->once())->method('publish')
			->willThrowException(new IncompleteActivityException('missing subject'));

		$this->logger->expects($this->once())->method('warning')
			->with('launchpad.cleanup.activity_emit_failed: missing subject');
		$this->logger->expects($this->once())->method('info');

		$result = $this->service->purge([], false, null, 'job');

		$this->assertSame(2, $result->getTotalRows());
		$this->assertSame(['live' => 2], $result->getByCategory());
	}

	/**
	 * Any other Throwable while emitting the event is logged under a
	 * distinct prefix and does not fail the purge.
	 *
	 * @return void
	 */
	public function testOtherActivityFailureIsLoggedAndSwallowed(): void {
		$this->registry->method('getCategoryNames')->willReturn(['live']);
		$this->registry->method('getCategoryByName')->willReturn($this->makeCategory(5));

		$this->activity->expects($this->once())->method('generateEvent')
			->willThrowException(new RuntimeException('activity backend down'));
		$this->activity->expects($this->never())->method('publish');

		$this->logger->expects($this->once())->method('warning')
			->with('launchpad.cleanup.activity_emit_threw: activity backend down');
		$this->logger->expects($this->once())->method('info')->with(
			$this->stringStartsWith('launchpad.cleanup.purge source=api user=system rows=5 ')
		);

		$result = $this->service->purge();

		$this->assertSame(5, $result->getTotalRows());
		$this->assertFalse($result->isDryRun());
	}
}
