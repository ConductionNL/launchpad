<?php

/**
 * Unit tests for FilterEventReportService: the drill-down path counts and who
 * sees whose paths.
 *
 * @category Test
 * @package  OCA\LaunchPad\Tests\Unit\Service
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/tasks.md#task-16
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Service\FilterEventReportService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\RoleService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Tests for the drill-down path report.
 */
#[CoversClass(FilterEventReportService::class)]
#[UsesClass(Dashboard::class)]
class FilterEventReportServiceTest extends TestCase {

	private const UUID = 'd1a2b3c4-5678-90ab-cdef-1234567890ab';

	/** @var DashboardMapper&MockObject */
	private DashboardMapper&MockObject $dashboardMapper;

	/** @var PermissionService&MockObject */
	private PermissionService&MockObject $permissions;

	/** @var RoleService&MockObject */
	private RoleService&MockObject $roles;

	/** @var IGroupManager&MockObject */
	private IGroupManager&MockObject $groups;

	/** @var ContainerInterface&MockObject */
	private ContainerInterface&MockObject $container;

	/** @var LoggerInterface&MockObject */
	private LoggerInterface&MockObject $logger;

	/** The OpenRegister ObjectService stand-in: records findAll calls. */
	private object $objectService;

	/** The service under test. */
	private FilterEventReportService $service;

	/**
	 * Build the service over mocks; the dashboard is owned by `owner`.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->dashboardMapper = $this->createMock(DashboardMapper::class);
		$this->permissions     = $this->createMock(PermissionService::class);
		$this->roles           = $this->createMock(RoleService::class);
		$this->groups          = $this->createMock(IGroupManager::class);
		$this->container       = $this->createMock(ContainerInterface::class);
		$this->logger          = $this->createMock(LoggerInterface::class);

		$this->objectService = new class {
			/** @var list<array{config: array, rbac: bool}> */
			public array $calls = [];

			/** @var list<mixed> */
			public array $rows = [];

			/**
			 * Record the call, return the rows.
			 *
			 * @param array $config        The query.
			 * @param bool  $_rbac         Per-object RBAC.
			 * @param bool  $_multitenancy Multitenancy.
			 *
			 * @return list<mixed>
			 */
			public function findAll(array $config=[], bool $_rbac=true, bool $_multitenancy=true): array {
				$this->calls[] = ['config' => $config, 'rbac' => $_rbac];
				return $this->rows;
			}
		};
		$this->container->method('get')->willReturn($this->objectService);

		$dashboard = new Dashboard();
		$dashboard->setId(7);
		$dashboard->setUuid(self::UUID);
		$dashboard->setUserId('owner');
		$this->dashboardMapper->method('findByUuid')->willReturnCallback(
			static function (string $uuid) use ($dashboard): Dashboard {
				if ($uuid !== self::UUID) {
					throw new DoesNotExistException('nope');
				}

				return $dashboard;
			}
		);

		$this->service = new FilterEventReportService(
			$this->dashboardMapper,
			$this->permissions,
			$this->roles,
			$this->groups,
			$this->container,
			$this->logger
		);
	}//end setUp()

	/**
	 * One event row.
	 *
	 * @param string       $user       The user.
	 * @param string       $time       The timestamp.
	 * @param string       $type       The event type.
	 * @param list<string> $dimensions The delta's dimensions.
	 *
	 * @return array<string, mixed>
	 */
	private static function event(string $user, string $time, string $type, array $dimensions): array {
		return [
			'dashboardId' => self::UUID,
			'userId' => $user,
			'eventType' => $type,
			'timestamp' => $time,
			'filterDelta' => array_map(static fn (string $d): array => ['dimension' => $d, 'operator' => 'eq'], $dimensions),
		];
	}//end event()

	/**
	 * Consecutive applies form one path; a reset ends it.
	 *
	 * @return void
	 */
	public function testAppliesFormAPathUntilAReset(): void {
		$paths = FilterEventReportService::aggregatePaths(
			events: [
				self::event('a', '2026-01-01T10:00:02Z', 'apply', ['wijkCode']),
				self::event('a', '2026-01-01T10:00:01Z', 'apply', ['gemeenteCode']),
				self::event('a', '2026-01-01T10:00:03Z', 'reset', ['gemeenteCode', 'wijkCode']),
				self::event('a', '2026-01-01T10:00:04Z', 'apply', ['gemeenteCode']),
			]
		);

		self::assertSame(
			[
				['path' => 'gemeenteCode', 'count' => 1],
				['path' => 'gemeenteCode → wijkCode', 'count' => 1],
			],
			$paths
		);
	}//end testAppliesFormAPathUntilAReset()

	/**
	 * Paths are counted per user and sorted by count; a repeated dimension is
	 * one step; a bookmark starts a new path; share and malformed rows count
	 * nothing.
	 *
	 * @return void
	 */
	public function testPathsAreCountedPerUserAndOrderedByCount(): void {
		$paths = FilterEventReportService::aggregatePaths(
			events: [
				self::event('a', '1', 'apply', ['gemeenteCode']),
				self::event('b', '1', 'apply', ['gemeenteCode']),
				self::event('a', '2', 'apply', ['gemeenteCode', 'wijkCode']),
				self::event('b', '2', 'apply', ['wijkCode']),
				self::event('b', '3', 'share', ['status']),
				self::event('c', '1', 'apply', ['status']),
				self::event('c', '2', 'bookmark', ['period.month']),
				['eventType' => 42],
				'not a row',
			]
		);

		self::assertSame(
			[
				['path' => 'gemeenteCode → wijkCode', 'count' => 2],
				['path' => 'period.month', 'count' => 1],
				['path' => 'status', 'count' => 1],
			],
			$paths
		);
	}//end testPathsAreCountedPerUserAndOrderedByCount()

	/**
	 * The owner sees everyone's events, read without per-object RBAC.
	 *
	 * @return void
	 */
	public function testTheOwnerSeesEveryonesPaths(): void {
		$this->objectService->rows = [
			self::event('x', '1', 'apply', ['gemeenteCode']),
			self::event('y', '1', 'apply', ['wijkCode']),
			['dashboardId' => 'another', 'userId' => 'z', 'eventType' => 'apply', 'filterDelta' => [['dimension' => 'other']]],
		];
		$this->permissions->expects(self::never())->method('canViewDashboard');

		$report = $this->service->report(dashboardUuid: self::UUID, userId: 'owner');

		self::assertSame('all', $report['scope']);
		self::assertSame(2, $report['events']);
		self::assertSame(
			[['path' => 'gemeenteCode', 'count' => 1], ['path' => 'wijkCode', 'count' => 1]],
			$report['paths']
		);
		self::assertSame(
			[
				'config' => [
					'filters' => ['register' => 'launchpad', 'schema' => 'filter_event', 'dashboardId' => self::UUID],
					'limit' => FilterEventReportService::MAX_EVENTS,
				],
				'rbac' => false,
			],
			$this->objectService->calls[0]
		);
	}//end testTheOwnerSeesEveryonesPaths()

	/**
	 * A LaunchPad administrator sees everyone's paths too.
	 *
	 * @return void
	 */
	public function testALaunchPadAdminSeesEveryonesPaths(): void {
		$this->roles->method('isAdmin')->with('boss')->willReturn(true);

		$report = $this->service->report(dashboardUuid: self::UUID, userId: 'boss');

		self::assertSame('all', $report['scope']);
		self::assertArrayNotHasKey('userId', $this->objectService->calls[0]['config']['filters']);
	}//end testALaunchPadAdminSeesEveryonesPaths()

	/**
	 * Another viewer sees only their own events, even if the store returns more.
	 *
	 * @return void
	 */
	public function testAViewerSeesOnlyTheirOwnPaths(): void {
		$this->permissions->method('canViewDashboard')->with('viewer', 7)->willReturn(true);
		$this->objectService->rows = [
			self::event('viewer', '1', 'apply', ['gemeenteCode']),
			self::event('someone', '1', 'apply', ['wijkCode']),
		];

		$report = $this->service->report(dashboardUuid: self::UUID, userId: 'viewer');

		self::assertSame('own', $report['scope']);
		self::assertSame(1, $report['events']);
		self::assertSame([['path' => 'gemeenteCode', 'count' => 1]], $report['paths']);
		self::assertSame('viewer', $this->objectService->calls[0]['config']['filters']['userId']);
	}//end testAViewerSeesOnlyTheirOwnPaths()

	/**
	 * A user who may not view the dashboard is told it does not exist, and
	 * no events are read.
	 *
	 * @return void
	 */
	public function testAStrangerIsRefusedBeforeAnyRead(): void {
		$this->permissions->method('canViewDashboard')->willReturn(false);

		try {
			$this->service->report(dashboardUuid: self::UUID, userId: 'stranger');
			self::fail('A stranger got a report');
		} catch (DoesNotExistException) {
			self::assertSame([], $this->objectService->calls);
		}
	}//end testAStrangerIsRefusedBeforeAnyRead()

	/**
	 * An unknown dashboard is not found.
	 *
	 * @return void
	 */
	public function testAnUnknownDashboardIsNotFound(): void {
		$this->expectException(DoesNotExistException::class);

		$this->service->report(dashboardUuid: 'unknown', userId: 'owner');
	}//end testAnUnknownDashboardIsNotFound()

	/**
	 * The limit caps the paths returned, at least one.
	 *
	 * @return void
	 */
	public function testTheLimitCapsThePaths(): void {
		$this->objectService->rows = [
			self::event('a', '1', 'apply', ['one']),
			self::event('b', '1', 'apply', ['two']),
			self::event('c', '1', 'apply', ['three']),
		];

		self::assertCount(2, $this->service->report(dashboardUuid: self::UUID, userId: 'owner', limit: 2)['paths']);
		self::assertCount(1, $this->service->report(dashboardUuid: self::UUID, userId: 'owner', limit: 0)['paths']);
	}//end testTheLimitCapsThePaths()

	/**
	 * An unreadable register reads as no events, and is logged.
	 *
	 * @return void
	 */
	public function testAnUnreadableRegisterIsAnEmptyReport(): void {
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willThrowException(new RuntimeException('OpenRegister is off'));
		$this->logger->expects(self::once())->method('warning');
		$service = new FilterEventReportService(
			$this->dashboardMapper,
			$this->permissions,
			$this->roles,
			$this->groups,
			$container,
			$this->logger
		);

		$report = $service->report(dashboardUuid: self::UUID, userId: 'owner');

		self::assertSame(0, $report['events']);
		self::assertSame([], $report['paths']);
	}//end testAnUnreadableRegisterIsAnEmptyReport()
}//end class
