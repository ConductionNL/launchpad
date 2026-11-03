<?php

/**
 * DashboardShareFailureGoldenTest
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
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

namespace OCA\LaunchPad\Tests\Unit\Controller;

use OCA\LaunchPad\Controller\DashboardShareApiController;
use OCA\LaunchPad\Controller\ShareApiTools;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Db\DashboardShareMapper;
use OCA\LaunchPad\Service\DashboardShareService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\Exception as DbException;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Drives the share endpoints (list, add, remove, replace) through the real
 * DashboardShareService and pins, per endpoint and per caller, the status
 * and body the client gets, the user the owner check ran for, and what was
 * written and logged. Dashboard 1 belongs to alice. The fixture was
 * recorded with LAUNCHPAD_GOLDEN_RECORD=1.
 *
 * @covers \OCA\LaunchPad\Controller\DashboardShareApiController
 * @covers \OCA\LaunchPad\Service\DashboardShareService
 * @covers \OCA\LaunchPad\Controller\ShareApiTools
 * @uses \OCA\LaunchPad\Exception\ForbiddenException
 * @uses \OCA\LaunchPad\Exception\ResourceException
 * @uses \OCA\LaunchPad\Db\Dashboard
 * @uses \OCA\LaunchPad\Db\DashboardShare
 * @uses \OCA\LaunchPad\Service\ShareGrant
 */
class DashboardShareFailureGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/dashboard-share-failures.json';

	/**
	 * Endpoint calls: [method, arguments].
	 */
	private const CALLS = [
		'index' => ['index', [1]],
		'index unknown dashboard' => ['index', [404]],
		'create' => ['create', [1, 'user', 'carol', 'view_only']],
		'create invalid type' => ['create', [1, 'team', 'carol', 'view_only']],
		'create unknown dashboard' => ['create', [404, 'user', 'carol', 'view_only']],
		'destroy' => ['destroy', [10]],
		'destroy unknown share' => ['destroy', [404]],
		'replace' => ['replace', [1, [['shareType' => 'user', 'shareWith' => 'carol', 'permissionLevel' => 'view_only']]]],
		'replace empty' => ['replace', [1, null]],
		'replace invalid level' => ['replace', [1, [['shareType' => 'user', 'shareWith' => 'carol', 'permissionLevel' => 'own']]]],
		'replace int recipient' => ['replace', [1, [['shareType' => 'user', 'shareWith' => 42, 'permissionLevel' => 'view_only']]]],
		'replace array recipient' => ['replace', [1, [['shareType' => 'user', 'shareWith' => ['carol'], 'permissionLevel' => 'view_only']]]],
		'replace int type' => ['replace', [1, [['shareType' => 7, 'shareWith' => 'carol', 'permissionLevel' => 'view_only']]]],
		'replace null level' => ['replace', [1, [['shareType' => 'user', 'shareWith' => 'carol', 'permissionLevel' => null]]]],
		'replace entry not an array' => ['replace', [1, ['user:carol']]],
		'replace unknown dashboard' => ['replace', [404, []]],
	];

	/**
	 * Session users and database states.
	 */
	private const CALLERS = ['alice (owner)' => 'alice', 'bob (not owner)' => 'bob', 'anonymous' => null];

	private const DATABASES = ['healthy', 'write fails', 'read fails'];

	/**
	 * Every call for every caller and database state.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public static function cases(): array {
		$cases = [];
		foreach (array_keys(self::CALLS) as $call) {
			foreach (array_keys(self::CALLERS) as $caller) {
				foreach (self::DATABASES as $database) {
					$cases[$call . ' / ' . $caller . ' / ' . $database] = [$call, $caller, $database];
				}
			}
		}

		return $cases;
	}

	/**
	 * The endpoint answers, checks and writes what it did when recorded.
	 *
	 * @param string $call     The call case.
	 * @param string $caller   The caller case.
	 * @param string $database The database state.
	 *
	 * @return void
	 */
	#[DataProvider('cases')]
	public function testEndpointMatchesTheRecording(string $call, string $caller, string $database): void {
		$log = ['ownerLookups' => [], 'writes' => [], 'logged' => []];
		$controller = $this->controller(userId: self::CALLERS[$caller], database: $database, log: $log);
		[$method, $args] = self::CALLS[$call];
		try {
			$response = $controller->$method(...$args);
			$actual = ['status' => $response->getStatus(), 'data' => $this->plain(value: $response->getData())];
		} catch (Throwable $e) {
			$actual = ['threw' => $e::class];
		}

		$actual += $log;
		$key = $call . ' / ' . $caller . ' / ' . $database;
		if (getenv('LAUNCHPAD_GOLDEN_RECORD') === '1') {
			$recorded = is_file(self::FIXTURE) ? json_decode((string)file_get_contents(self::FIXTURE), true) : [];
			$recorded[$key] = $actual;
			ksort($recorded);
			file_put_contents(self::FIXTURE, json_encode($recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
		}

		$expected = json_decode((string)file_get_contents(self::FIXTURE), true)[$key] ?? null;
		$this->assertNotNull($expected, 'no recording for ' . $key);
		$this->assertSame($expected, $actual);
	}

	/**
	 * The controller on a real share service over recording doubles.
	 *
	 * @param string|null          $userId   The session user.
	 * @param string               $database The database state.
	 * @param array<string, mixed> $log      The log, written by reference.
	 *
	 * @return DashboardShareApiController The controller.
	 */
	private function controller(?string $userId, string $database, array &$log): DashboardShareApiController {
		$dashboard = new Dashboard();
		$dashboard->setId(1);
		$dashboard->setUserId('alice');
		$dashboard->setName('Team');

		$dashboards = $this->createMock(DashboardMapper::class);

		$shares = $this->createMock(DashboardShareMapper::class);
		$readFails = static function () use ($database): void {
			if ($database === 'read fails') {
				throw new DbException('select failed: secret table name');
			}
		};
		$writeFails = static function (string $what) use ($database, &$log): void {
			$log['writes'][] = $what;
			if ($database === 'write fails') {
				throw new DbException('insert failed: secret column name');
			}
		};
		$existing = new DashboardShare();
		$existing->setId(10);
		$existing->setDashboardId(1);
		$existing->setShareType('user');
		$existing->setShareWith('dave');
		$existing->setPermissionLevel('view_only');
		$shares->method('find')->willReturnCallback(static function (int $id) use ($existing, $readFails): DashboardShare {
			$readFails();
			if ($id !== 10) {
				throw new DoesNotExistException('no share ' . $id);
			}

			return $existing;
		});
		$shares->method('findByDashboardId')->willReturnCallback(static function () use ($existing, $readFails): array {
			$readFails();
			return [$existing];
		});
		$shares->method('findShare')->willReturnCallback(static function () use ($readFails): ?DashboardShare {
			$readFails();
			return null;
		});
		$shares->method('insert')->willReturnCallback(static function (DashboardShare $share) use ($writeFails): DashboardShare {
			$writeFails('insert ' . $share->getShareType() . ':' . $share->getShareWith() . ':' . $share->getPermissionLevel());
			$share->setId(11);
			return $share;
		});
		$shares->method('delete')->willReturnCallback(static function (DashboardShare $share) use ($writeFails): DashboardShare {
			$writeFails('delete ' . $share->getId());
			return $share;
		});
		$shares->method('deleteNotIn')->willReturnCallback(static function (int $dashboardId, array $keepKeys) use ($writeFails): int {
			$writeFails('deleteNotIn ' . $dashboardId . ' keep ' . implode(',', $keepKeys));
			return 0;
		});

		$dashboards->method('find')->willReturnCallback(static function (int $id) use ($dashboard, &$log): Dashboard {
			$log['ownerLookups'][] = $id;
			if ($id !== 1) {
				throw new DoesNotExistException('no dashboard ' . $id);
			}

			return $dashboard;
		});

		$notification = $this->createMock(INotification::class);
		foreach (['setApp', 'setUser', 'setDateTime', 'setObject', 'setSubject'] as $setter) {
			$notification->method($setter)->willReturnSelf();
		}

		$notifications = $this->createMock(INotificationManager::class);
		$notifications->method('createNotification')->willReturn($notification);

		$service = new DashboardShareService(
			shareMapper: $shares,
			dashboardMapper: $dashboards,
			groupManager: $this->createMock(IGroupManager::class),
			notificationManager: $notifications,
			db: $this->createMock(IDBConnection::class),
		);

		$logger = $this->createMock(LoggerInterface::class);
		foreach (['error', 'warning', 'info', 'critical'] as $level) {
			$logger->method($level)->willReturnCallback(static function (string|\Stringable $message, array $context = []) use ($level, &$log): void {
				$log['logged'][] = [$level, (string)$message, $context['exceptionClass'] ?? null];
			});
		}

		return $this->build(service: $service, userId: $userId, logger: $logger);
	}

	/**
	 * Construct the controller with the session user.
	 *
	 * @param DashboardShareService $service The share service.
	 * @param string|null           $userId  The session user.
	 * @param LoggerInterface       $logger  The logger.
	 *
	 * @return DashboardShareApiController The controller.
	 */
	private function build(DashboardShareService $service, ?string $userId, LoggerInterface $logger): DashboardShareApiController {
		$userManager = $this->createMock(IUserManager::class);
		$groupManager = $this->createMock(IGroupManager::class);
		return new DashboardShareApiController(
			request: $this->createMock(IRequest::class),
			shareService: $service,
			tools: new ShareApiTools(userManager: $userManager, groupManager: $groupManager, logger: $logger),
			userId: $userId,
		);
	}

	/**
	 * Entities as their JSON shape, recursively; a timestamp shows as its format.
	 *
	 * @param mixed $value The response data.
	 *
	 * @return mixed The plain data.
	 */
	private function plain(mixed $value): mixed {
		if ($value instanceof \JsonSerializable) {
			$value = $value->jsonSerialize();
		}

		if (is_array($value) === true) {
			return array_map(fn (mixed $item): mixed => $this->plain(value: $item), $value);
		}

		if (is_string($value) === true && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value) === 1) {
			return 'stamp Y-m-d H:i:s';
		}

		return $value;
	}
}
