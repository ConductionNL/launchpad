<?php

/**
 * Bulk publication status golden test
 *
 * Pins what a bulk publication status change does for each caller and
 * request: the response, every stored dashboard row, the logged failures
 * and the audit event, against a recorded fixture.
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

use DateTime;
use OCA\LaunchPad\Activity\ActivityPublisher;
use OCA\LaunchPad\Activity\ActivityRow;
use OCA\LaunchPad\Controller\AdminBulkController;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\BulkDashboardStores;
use OCA\LaunchPad\Service\BulkOperationAccess;
use OCA\LaunchPad\Service\BulkOperationService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IAppConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Bulk status changes, request by request.
 *
 * @covers \OCA\LaunchPad\Controller\AdminBulkController
 * @covers \OCA\LaunchPad\Service\BulkOperationService
 * @uses   \OCA\LaunchPad\Service\BulkDashboardStores
 * @uses   \OCA\LaunchPad\Service\BulkOperationAccess
 * @uses   \OCA\LaunchPad\Service\BulkStatusRequest
 * @uses   \OCA\LaunchPad\Service\BulkStatusChange
 * @uses   \OCA\LaunchPad\Service\PermissionDeniedException
 * @uses   \OCA\LaunchPad\Activity\ActivityRow
 * @uses   \OCA\LaunchPad\Activity\ActivitySubject
 * @uses   \OCA\LaunchPad\Db\Dashboard
 */
class AdminBulkStatusGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/admin-bulk-status.json';

	/**
	 * Dashboards by uuid: status, publishAt, publishedAt.
	 */
	private const ROWS = [
		'd-draft' => ['draft', null, null],
		'd-pub' => ['published', null, '2020-01-01 00:00:00'],
		'd-pubnull' => ['published', null, null],
		'd-sched' => ['scheduled', '2099-01-01 10:00:00', null],
		'd-sched2' => ['scheduled', '2098-05-05 00:00:00', '2019-02-02 00:00:00'],
		'd-fail' => ['draft', null, null],
		'd-locked' => ['draft', null, null],
	];

	/**
	 * Every request matches the recorded fixture.
	 *
	 * @return void
	 */
	public function testStatusChangesMatchTheRecordedFixture(): void {
		$all = ['d-draft', 'd-pub', 'd-pubnull', 'd-sched', 'd-sched2', 'd-missing', 'd-fail'];
		$actual = [];
		$statuses = ['draft', 'published', 'scheduled', 'archived', ' published', '', null];
		$publishAts = [null, '', '  ', '2000-01-01 00:00:00', '2099-01-01 10:00:00', '2099-01-01T10:00:00+02:00', 'garbage', ' 2099-01-01 10:00:00 '];
		foreach ($statuses as $status) {
			foreach ($publishAts as $publishAt) {
				foreach ([false, true] as $dryRun) {
					$name = 'status ' . json_encode($status) . ' at ' . json_encode($publishAt) . ' dry ' . json_encode($dryRun);
					$actual[$name] = $this->request(caller: 'alice', request: [$all, $status, $publishAt, $dryRun]);
				}
			}
		}

		$uuidSets = [
			'all' => $all,
			'locked' => ['d-draft', 'd-locked'],
			'locked missing' => ['d-locked', 'd-missing'],
			'own' => ['d-draft', 'd-pub', 'd-sched'],
			'empty' => [],
			'duplicate' => ['d-draft', 'd-draft'],
			'padded' => [' d-draft '],
			'blank' => ['d-draft', ' '],
			'int' => ['d-draft', 7],
			'not an array' => 'd-draft',
			'null' => null,
		];
		foreach ($uuidSets as $setName => $uuids) {
			foreach (['alice', 'carol', 'bob', null] as $caller) {
				$actual['set ' . $setName . ' caller ' . json_encode($caller)] = $this->request(caller: $caller, request: [$uuids, 'published', null, false]);
			}
		}

		foreach (['true', '1', 'yes', 'on', 'TRUE', 'no', '0', '', null] as $query) {
			$actual['dry query ' . json_encode($query)] = $this->request(caller: 'alice', request: [['d-draft'], 'published', null, null], query: $query);
		}

		$actual['body false wins over query'] = $this->request(caller: 'alice', request: [['d-draft'], 'published', null, false], query: 'true');
		foreach ([1, 2, 0, -5] as $cap) {
			$actual['cap ' . $cap] = $this->request(caller: 'alice', request: [['d-draft', 'd-pub'], 'draft', null, false], cap: $cap);
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
			$this->markTestIncomplete(message: 'Fixture recorded.');
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true);
		$this->assertSame(expected: array_keys($expected), actual: array_keys($actual));
		foreach ($expected as $name => $case) {
			$this->assertSame(expected: $case, actual: $actual[$name], message: $name);
		}
	}

	/**
	 * Run one bulk-status request through the controller and record it.
	 *
	 * @param string|null       $caller  The session user, or none.
	 * @param array<int, mixed> $request Uuids, status, publishAt, dryRun.
	 * @param string|null       $query   The dryRun query parameter.
	 * @param int               $cap     The configured batch cap.
	 *
	 * @return array<string, mixed>
	 */
	private function request(?string $caller, array $request, ?string $query=null, int $cap=500): array {
		$log = [];
		$rows = [];
		foreach (self::ROWS as $uuid => [$status, $publishAt, $publishedAt]) {
			$row = new Dashboard();
			$row->setUuid($uuid);
			$row->setPublicationStatus($status);
			$row->setPublishAt($publishAt);
			$row->setPublishedAt($publishedAt);
			$rows[$uuid] = $row;
		}

		$mapper = $this->getMockBuilder(className: DashboardMapper::class)->disableOriginalConstructor()->getMock();
		$mapper->method('findByUuid')->willReturnCallback(static function (string $uuid) use ($rows): Dashboard {
			if (isset($rows[$uuid]) === false) {
				throw new DoesNotExistException('none');
			}

			return $rows[$uuid];
		});
		$mapper->method('update')->willReturnCallback(static function (Dashboard $row) use (&$log): Dashboard {
			if ($row->getUuid() === 'd-fail') {
				throw new RuntimeException('disk full');
			}

			$log[] = ['update', $row->getUuid(), $row->getPublicationStatus(), $row->getPublishAt(), self::clock(value: $row->getPublishedAt()), self::clock(value: $row->getUpdatedAt())];
			return $row;
		});

		$admins = $this->getMockBuilder(className: IGroupManager::class)->getMock();
		$admins->method('isAdmin')->willReturnCallback(static function (string $userId) use (&$log): bool {
			$log[] = 'isAdmin ' . $userId;
			return $userId === 'alice' || $userId === 'carol';
		});

		$permissions = $this->getMockBuilder(className: PermissionService::class)->disableOriginalConstructor()->getMock();
		$permissions->method('resolveAccessLevel')->willReturnCallback(static function (string $userId, ?int $dashboardId=null, ?Dashboard $dashboard=null) use (&$log): ?string {
			$log[] = 'resolveAccessLevel ' . $userId . ' ' . $dashboard->getUuid();
			if ($dashboard->getUuid() === 'd-locked' || ($userId === 'carol' && $dashboard->getUuid() === 'd-sched2')) {
				return null;
			}

			return Dashboard::PERMISSION_FULL;
		});

		$config = $this->getMockBuilder(className: IAppConfig::class)->getMock();
		$config->method('getValueInt')->willReturn($cap);

		$publisher = $this->getMockBuilder(className: ActivityPublisher::class)->disableOriginalConstructor()->getMock();
		$publisher->method('publish')->willReturnCallback(static function (ActivityRow $row, string $recipientUserId) use (&$log): bool {
			$extra = $row->extraParams;
			$extra['durationMs'] = is_int($extra['durationMs']) && $extra['durationMs'] >= 0 ? '<ms>' : $extra['durationMs'];
			$log[] = ['publish', $row->type, $row->actorUserId, $row->dashboard->uuid, $row->dashboard->name, $extra, $recipientUserId];
			return true;
		});

		$logger = $this->getMockBuilder(className: LoggerInterface::class)->getMock();
		foreach (['error', 'warning'] as $level) {
			$logger->method($level)->willReturnCallback(static function (string $message, array $context=[]) use (&$log, $level): void {
				$log[] = [$level, $message, $context['uuid'] ?? null, isset($context['exception']) ? $context['exception']->getMessage() : null];
			});
		}

		$session = $this->getMockBuilder(className: IUserSession::class)->getMock();
		$user = null;
		if ($caller !== null) {
			$user = $this->getMockBuilder(className: IUser::class)->getMock();
			$user->method('getUID')->willReturn($caller);
		}

		$session->method('getUser')->willReturn($user);

		$httpRequest = $this->getMockBuilder(className: IRequest::class)->getMock();
		$httpRequest->method('getParam')->willReturnCallback(static fn (string $key): ?string => $key === 'dryRun' ? $query : null);

		$service = new BulkOperationService(
			stores: new BulkDashboardStores(
				dashboardMapper: $mapper,
				placementMapper: $this->getMockBuilder(className: WidgetPlacementMapper::class)->disableOriginalConstructor()->getMock(),
				treeService: $this->getMockBuilder(className: DashboardTreeService::class)->disableOriginalConstructor()->getMock()
			),
			access: new BulkOperationAccess(permissionService: $permissions, groupManager: $admins, appConfig: $config),
			activityPublisher: $publisher,
			logger: $logger
		);
		$controller = new AdminBulkController(request: $httpRequest, bulkService: $service, userSession: $session, groupManager: $admins);

		[$uuids, $status, $publishAt, $dryRun] = $request;
		$response = $controller->bulkStatus(dashboardUuids: $uuids, publicationStatus: $status, publishAt: $publishAt, dryRun: $dryRun);

		return ['status' => $response->getStatus(), 'body' => $response->getData(), 'log' => $log];
	}

	/**
	 * Replace a timestamp taken from the clock during the run with a marker.
	 *
	 * @param string|null $value A stored timestamp.
	 *
	 * @return string|null
	 */
	private static function clock(?string $value): ?string {
		if ($value === null || preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $value) !== 1) {
			return $value;
		}

		$delta = abs((new DateTime($value))->getTimestamp() - time());
		return $delta < 600 ? '<now>' : $value;
	}
}
