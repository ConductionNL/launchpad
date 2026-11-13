<?php

/**
 * AdminCleanupControllerCoverageTest
 *
 * Unit tests for the AdminCleanupController paths the main suite leaves out:
 * a fresh (uncached) scan, a refused purge, non-string category entries and
 * a purge without a session user.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\AdminCleanupController;
use OCA\LaunchPad\Controller\CleanupSources;
use OCA\LaunchPad\Db\CleanupResult;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\Cleanup\CategoryRegistryService;
use OCA\LaunchPad\Service\OrphanedDataCleanupService;
use OCA\LaunchPad\Service\RequestGuard;
use OCP\AppFramework\Http;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Controller\AdminCleanupController
 * @uses   \OCA\LaunchPad\Controller\CleanupSources
 * @uses   \OCA\LaunchPad\Db\CleanupResult
 * @uses   \OCA\LaunchPad\Service\RequestGuard
 */
class AdminCleanupControllerCoverageTest extends TestCase {
	private OrphanedDataCleanupService&MockObject $cleanupService;

	private CategoryRegistryService&MockObject $registry;

	private IGroupManager&MockObject $groupManager;

	private IUserSession&MockObject $userSession;

	/**
	 * Set up the shared collaborators.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->cleanupService = $this->createMock(OrphanedDataCleanupService::class);
		$this->registry = $this->createMock(CategoryRegistryService::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
	}//end setUp()

	/**
	 * Build the controller under test.
	 *
	 * @return AdminCleanupController
	 */
	private function makeController(): AdminCleanupController {
		return new AdminCleanupController(
			request: $this->createMock(IRequest::class),
			cleanup: new CleanupSources(cleanupService: $this->cleanupService, registry: $this->registry),
			guard: new RequestGuard(
				userSession: $this->userSession,
				groupManager: $this->groupManager,
				actionAuth: $this->createMock(ActionAuthService::class)
			)
		);
	}//end makeController()

	/**
	 * A signed-in user with the given admin flag.
	 *
	 * @param bool $isAdmin Whether the user is an admin.
	 *
	 * @return IUser
	 */
	private function user(bool $isAdmin): IUser {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('root');
		$this->groupManager->method('isAdmin')->with('root')->willReturn($isAdmin);

		return $user;
	}//end user()

	public function testAnUncachedScanRunsAFreshScanAndSaysSo(): void {
		$this->userSession->method('getUser')->willReturn($this->user(isAdmin: true));
		$this->cleanupService->method('getCachedScanResult')->willReturn(null);
		$this->cleanupService->expects($this->once())
			->method('scan')
			->willReturn(new CleanupResult(
				byCategory: ['expired_locks' => 3],
				totalRows: 3,
				durationMs: 12,
				dryRun: false,
				scannedAt: '2026-11-13T10:00:00Z'
			));

		$response = $this->makeController()->scan();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$data = $response->getData();
		$this->assertFalse($data['cached']);
		$this->assertNull($data['cachedAt']);
		$this->assertSame(['expired_locks' => 3], $data['byCategory']);
		$this->assertSame(3, $data['totalRows']);
	}

	public function testANonAdminPurgeIsForbiddenAndPurgesNothing(): void {
		$this->userSession->method('getUser')->willReturn($this->user(isAdmin: false));
		$this->cleanupService->expects($this->never())->method('purge');

		$response = $this->makeController()->purge(categories: ['expired_locks'], dryRun: false);

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame(['error' => 'Admin required'], $response->getData());
	}

	public function testAnAnonymousPurgeIsForbiddenRatherThanUnauthorized(): void {
		$this->userSession->method('getUser')->willReturn(null);
		$this->cleanupService->expects($this->never())->method('purge');

		$this->assertSame(Http::STATUS_FORBIDDEN, $this->makeController()->purge()->getStatus());
	}

	public function testNonStringAndEmptyCategoryEntriesAreDroppedSilently(): void {
		$this->userSession->method('getUser')->willReturn($this->user(isAdmin: true));
		$this->registry->method('getCategoryNames')->willReturn(['expired_locks', 'orphan_placements']);
		$this->cleanupService->expects($this->once())
			->method('purge')
			->with(['orphan_placements'], true, 'root', 'api')
			->willReturn(new CleanupResult(
				byCategory: ['orphan_placements' => 0],
				totalRows: 0,
				durationMs: 1,
				dryRun: true,
				scannedAt: '2026-11-13T10:00:00Z',
				skipped: ['expired_locks']
			));

		$response = $this->makeController()->purge(categories: [7, '', null, 'orphan_placements'], dryRun: true);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(
			[
				'purgedByCategory' => ['orphan_placements' => 0],
				'totalRows' => 0,
				'durationMs' => 1,
				'dryRun' => true,
				'skipped' => ['expired_locks'],
			],
			$response->getData()
		);
	}

	public function testAPurgeWhoseSessionEndsMidRequestIsAttributedToNobody(): void {
		$this->userSession->method('getUser')
			->willReturnOnConsecutiveCalls($this->user(isAdmin: true), null);
		$this->registry->method('getCategoryNames')->willReturn([]);
		$this->cleanupService->expects($this->once())
			->method('purge')
			->with([], false, '', 'api')
			->willReturn(new CleanupResult(
				byCategory: [],
				totalRows: 0,
				durationMs: 0,
				dryRun: false,
				scannedAt: '2026-11-13T10:00:00Z'
			));

		$this->assertSame(Http::STATUS_OK, $this->makeController()->purge()->getStatus());
	}
}//end class
