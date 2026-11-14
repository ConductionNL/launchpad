<?php

/**
 * DashboardApiController actions golden test
 *
 * Pins, on the code before its split, the status and body every
 * outcome of the create, update guard, group update, group default,
 * fork and schedule actions answers with (refusals in their order,
 * service errors mapped to responses), plus the breadcrumb hiding and
 * create-parameter resolution those actions use.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use Exception;
use InvalidArgumentException;
use OCA\LaunchPad\Controller\DashboardApiController;
use OCA\LaunchPad\Controller\DashboardApiHistory;
use OCA\LaunchPad\Controller\DashboardApiServices;
use OCA\LaunchPad\Controller\RequestActor;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Exception\PersonalDashboardsDisabledException;
use OCA\LaunchPad\Exception\QuotaExceededException;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AnalyticsService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use RuntimeException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Mirrors the controller constructor.
 */
class DashboardApiControllerActionsGoldenTest extends TestCase {
	private DashboardService $dashboardService;
	private PermissionService $permissionService;
	private DashboardTreeService $treeService;
	private LoggerInterface $logger;
	private bool $sessionUser = true;

	protected function setUp(): void {
		$this->dashboardService = $this->createMock(DashboardService::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->treeService = $this->createMock(DashboardTreeService::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function controller(?string $userId = 'alice'): DashboardApiController {
		$session = $this->createMock(IUserSession::class);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$session->method('getUser')->willReturn($this->sessionUser ? $user : null);

		return new DashboardApiController(
			$this->createMock(IRequest::class),
			new DashboardApiServices($this->dashboardService),
			$this->permissionService,
			new DashboardApiHistory(
				$this->treeService,
				$this->createMock(DashboardVersionService::class),
				$this->createMock(AnalyticsService::class),
				$this->logger,
			),
			new RequestActor($session, $this->createMock(ActionAuthService::class)),
			$userId,
		);
	}

	private function outcome(JSONResponse $response): array {
		return [$response->getStatus(), $response->getData()];
	}

	private function dashboard(string $uuid = 'd-1', ?string $parent = null): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId(5);
		$dashboard->setUuid($uuid);
		$dashboard->setName('N');
		$dashboard->setParentUuid($parent);

		return $dashboard;
	}

	private function allowCreate(): void {
		$this->permissionService->method('canCreateDashboard')->willReturn(true);
		$this->permissionService->method('canHaveMultipleDashboards')->willReturn(true);
		$this->dashboardService->method('getUserDashboards')->willReturn([]);
	}

	public function testCreateWithoutSessionIsUnauthenticated(): void {
		$this->sessionUser = false;
		$this->dashboardService->expects($this->never())->method('createDashboard');

		$this->assertSame([401, ['error' => 'Not authenticated']], $this->outcome($this->controller()->create(name: 'x')));
	}

	public function testCreateWithPersonalDashboardsOffIsRefused(): void {
		$this->dashboardService->method('assertPersonalDashboardsAllowed')->willThrowException(new PersonalDashboardsDisabledException());
		$this->permissionService->expects($this->never())->method('canCreateDashboard');

		[$status, $data] = $this->outcome($this->controller()->create(name: 'x'));

		$this->assertSame([403, 'error', 'personal_dashboards_disabled'], [$status, $data['status'], $data['error']]);
	}

	public function testCreateNotAllowedIsForbidden(): void {
		$this->permissionService->method('canCreateDashboard')->willReturn(false);
		$this->dashboardService->expects($this->never())->method('getUserDashboards');

		$this->assertSame([403, ['error' => 'Dashboard creation not allowed']], $this->outcome($this->controller()->create(name: 'x')));
	}

	public function testSecondDashboardNotAllowedIsForbidden(): void {
		$this->permissionService->method('canCreateDashboard')->willReturn(true);
		$this->permissionService->method('canHaveMultipleDashboards')->willReturn(false);
		$this->dashboardService->method('getUserDashboards')->willReturn([$this->dashboard()]);
		$this->dashboardService->expects($this->never())->method('createDashboard');

		$this->assertSame([403, ['error' => 'Multiple dashboards not allowed']], $this->outcome($this->controller()->create(name: 'x')));
	}

	public function testCreatePassesFlatParametersAndAnswersWithPlacements(): void {
		$this->allowCreate();
		$this->dashboardService->expects($this->once())->method('createDashboard')->with(
			'alice',
			'Board',
			['seedDefaults' => true, 'description' => 'D', 'icon' => 'star', 'parentUuid' => 'p-1', 'slug' => 's', 'sortOrder' => 3]
		)->willReturn($this->dashboard());
		$this->dashboardService->method('findPlacements')->with(5)->willReturn([]);

		[$status, $data] = $this->outcome($this->controller()->create('Board', 'D', 'star', 'p-1', 's', 3));

		$this->assertSame([201, ['dashboard', 'placements'], 'd-1', []], [$status, array_keys($data), $data['dashboard']['uuid'] ?? null, $data['placements'] ?? null]);
	}

	public function testCreateMapsServiceErrors(): void {
		$this->allowCreate();
		$calls = 0;
		$this->dashboardService->method('createDashboard')->willReturnCallback(function () use (&$calls): Dashboard {
			$calls++;
			return match ($calls) {
				1 => $this->throwQuota(),
				2 => $this->throwInvalid(),
				default => $this->throwGeneric(),
			};
		});

		$quota = $this->outcome($this->controller()->create(name: 'x'));
		$invalid = $this->outcome($this->controller()->create(name: 'x'));
		$generic = $this->outcome($this->controller()->create(name: 'x'));

		$this->assertSame(409, $quota[0]);
		$this->assertSame([400, ['status' => 'error', 'error' => 'invalid_argument', 'message' => 'Bad parent']], $invalid);
		$this->assertSame(400, $generic[0]);
	}

	private function throwQuota(): never {
		throw new QuotaExceededException(quota: QuotaExceededException::QUOTA_DASHBOARDS, limit: 1, current: 1);
	}

	private function throwInvalid(): never {
		throw new InvalidArgumentException('Bad parent');
	}

	private function throwGeneric(): never {
		throw new RuntimeException('db down');
	}

	/**
	 * Create bodies and the parameters they resolve to.
	 *
	 * @return array<string,array{0:mixed,1:array<string,mixed>,2:array<string,mixed>}>
	 */
	public static function createParams(): array {
		return [
			'flat defaults' => [null, [], ['name' => 'My Dashboard', 'description' => null, 'icon' => null, 'parentUuid' => null, 'slug' => null, 'sortOrder' => 0]],
			'flat values'   => ['B', ['description' => 'D', 'icon' => 7, 'parentUuid' => 'p', 'slug' => 's', 'sortOrder' => 2], ['name' => 'B', 'description' => 'D', 'icon' => 7, 'parentUuid' => 'p', 'slug' => 's', 'sortOrder' => 2]],
			'body typed'    => [['name' => 'B', 'description' => 'D', 'icon' => 'i', 'parentUuid' => 'p', 'slug' => 's', 'sortOrder' => '4'], [], ['name' => 'B', 'description' => 'D', 'icon' => 'i', 'parentUuid' => 'p', 'slug' => 's', 'sortOrder' => 4]],
			'body untyped'  => [['icon' => 3, 'parentUuid' => 4, 'slug' => 5, 'sortOrder' => 'x'], ['slug' => 'ignored'], ['name' => 'My Dashboard', 'description' => null, 'icon' => null, 'parentUuid' => null, 'slug' => null, 'sortOrder' => 0]],
			'body empty'    => [[], [], ['name' => 'My Dashboard', 'description' => null, 'icon' => null, 'parentUuid' => null, 'slug' => null, 'sortOrder' => 0]],
		];
	}

	/**
	 * @dataProvider createParams
	 */
	public function testCreateParameterResolution(mixed $name, array $flat, array $expected): void {
		$method = new ReflectionMethod(DashboardApiController::class, 'resolveCreateParams');

		$this->assertSame($expected, $method->invoke($this->controller(), $name, $flat));
	}

	/**
	 * Update guard cases: placements given, metadata / full permission, outcome.
	 *
	 * @return array<string,array{0:?array,1:bool,2:bool,3:string}>
	 */
	public static function updateGuards(): array {
		return [
			'metadata allowed'  => [null, true, false, 'applied'],
			'metadata refused'  => [null, false, true, 'forbidden'],
			'layout allowed'    => [[['id' => 1]], false, true, 'applied'],
			'layout refused'    => [[['id' => 1]], true, false, 'forbidden'],
		];
	}

	/**
	 * @dataProvider updateGuards
	 */
	public function testUpdateGuard(?array $placements, bool $metadata, bool $full, string $expected): void {
		$this->permissionService->method('canEditDashboardMetadata')->willReturn($metadata);
		$this->permissionService->method('canEditDashboard')->willReturn($full);
		$this->dashboardService->expects($expected === 'applied' ? $this->once() : $this->never())
			->method('updateDashboard')->willReturn($this->dashboard());

		$outcome = $this->outcome($this->controller()->update(id: 5, name: 'N', placements: $placements));

		if ($expected === 'forbidden') {
			$this->assertSame([403, ['error' => 'Access denied']], $outcome);
		}
	}

	public function testUpdateWithoutLoginIsRefused(): void {
		$this->permissionService->expects($this->never())->method('canEditDashboardMetadata');

		$this->assertSame([401, ['error' => 'Not logged in']], $this->outcome($this->controller(null)->update(id: 5)));
	}

	public function testGroupUpdateOutcomes(): void {
		$this->dashboardService->expects($this->exactly(3))->method('updateGroupShared')->willReturnCallback(
			function (string $actorUserId, string $groupId, string $uuid, array $patch): Dashboard {
				if ($uuid === 'gone') {
					throw new DoesNotExistException('x');
				}

				if ($uuid === 'bad') {
					throw new Exception('nope');
				}

				$this->assertSame(['alice', 'sales', ['name' => 'N', 'gridColumns' => 6]], [$actorUserId, $groupId, $patch]);
				return $this->dashboard();
			}
		);

		$this->assertSame([401, ['error' => 'Not logged in']], $this->outcome($this->controller(null)->updateGroup('sales', 'g')));
		$this->assertSame(200, $this->controller()->updateGroup('sales', 'g', 'N', null, 6)->getStatus());
		$this->assertSame(404, $this->controller()->updateGroup('sales', 'gone')->getStatus());
		$this->assertSame([400, ['error' => 'Operation failed']], $this->outcome($this->controller()->updateGroup('sales', 'bad')));
	}

	public function testGroupDefaultOutcomes(): void {
		$this->dashboardService->expects($this->exactly(3))->method('setGroupDefault')->willReturnCallback(
			static function (string $actorUserId, string $groupId, string $uuid): void {
				if ($uuid === 'gone') {
					throw new DoesNotExistException('x');
				}

				if ($uuid === 'bad') {
					throw new Exception('nope');
				}
			}
		);

		$this->assertSame([401, ['error' => 'Not logged in']], $this->outcome($this->controller(null)->setGroupDefault('sales', 'g')));
		$this->assertSame(400, $this->controller()->setGroupDefault('sales')->getStatus());
		$this->assertSame(400, $this->controller()->setGroupDefault('sales', '')->getStatus());
		$this->assertSame([200, ['status' => 'ok', 'groupId' => 'sales', 'uuid' => 'g']], $this->outcome($this->controller()->setGroupDefault('sales', 'g')));
		$this->assertSame(404, $this->controller()->setGroupDefault('sales', 'gone')->getStatus());
		$this->assertSame([400, ['error' => 'Operation failed']], $this->outcome($this->controller()->setGroupDefault('sales', 'bad')));
	}

	public function testForkOutcomes(): void {
		$this->dashboardService->method('forkAsPersonal')->willReturnCallback(function (string $userId, string $sourceUuid, ?string $name): Dashboard {
			return match ($sourceUuid) {
				'off' => throw new PersonalDashboardsDisabledException(),
				'full' => throw new QuotaExceededException(quota: QuotaExceededException::QUOTA_DASHBOARDS, limit: 1, current: 1),
				'gone' => throw new DoesNotExistException('x'),
				'boom' => throw new RuntimeException('disk'),
				default => $this->dashboard('f-' . $name),
			};
		});
		$this->logger->expects($this->once())->method('error')->with(
			'launchpad: fork failed for user {user}: {message}',
			['user' => 'alice', 'message' => 'disk']
		);

		$this->assertSame([401, ['error' => 'Not logged in']], $this->outcome($this->controller(null)->fork('s')));
		[$status, $data] = $this->outcome($this->controller()->fork('s', 'copy'));
		$this->assertSame([201, 'success', 'f-copy'], [$status, $data['status'], $data['dashboard']['uuid']]);
		$this->assertSame(403, $this->controller()->fork('off')->getStatus());
		$this->assertSame(409, $this->controller()->fork('full')->getStatus());
		$this->assertSame([404, ['status' => 'error', 'error' => 'not_found', 'message' => 'not_found']], $this->normaliseNotFound($this->outcome($this->controller()->fork('gone'))));
		$this->assertSame([500, ['status' => 'error', 'error' => 'internal_error', 'message' => 'An unexpected error occurred']], $this->outcome($this->controller()->fork('boom')));
	}

	private function normaliseNotFound(array $outcome): array {
		$outcome[1]['message'] = $outcome[1]['error'] ?? null;
		return [$outcome[0], ['status' => $outcome[1]['status'] ?? null, 'error' => $outcome[1]['error'] ?? null, 'message' => $outcome[1]['message']]];
	}

	public function testScheduleOutcomes(): void {
		$this->dashboardService->method('schedule')->willReturnCallback(function (string $uuid, ?string $publishAt, string $userId, ?string $unpublishAt): Dashboard {
			return match ($uuid) {
				'gone' => throw new DoesNotExistException('x'),
				'past' => throw new InvalidArgumentException('in the past'),
				'owner' => throw new Exception(DashboardService::ERR_FORBIDDEN_NOT_OWNER_OR_ADMIN),
				'boom' => throw new Exception('other'),
				default => $this->dashboard($uuid . '|' . $publishAt . '|' . $unpublishAt),
			};
		});

		$this->assertSame([401, ['error' => 'Not logged in']], $this->outcome($this->controller(null)->schedule('d', '2030-01-01')));
		$this->assertSame([400, ['status' => 'error', 'error' => 'invalid_argument', 'message' => DashboardService::ERR_SCHEDULE_PAST_DATE]], $this->outcome($this->controller()->schedule('d', '', null)));
		$this->assertSame('d||2030-02-01', $this->controller()->schedule('d', null, '2030-02-01')->getData()['dashboard']['uuid']);
		$this->assertSame('d|2030-01-01|', $this->controller()->schedule('d', '2030-01-01')->getData()['dashboard']['uuid']);
		$this->assertSame(404, $this->controller()->schedule('gone', '2030-01-01')->getStatus());
		$this->assertSame([400, ['status' => 'error', 'error' => 'invalid_argument', 'message' => 'in the past']], $this->outcome($this->controller()->schedule('past', '2030-01-01')));
		$this->assertSame([403, ['error' => DashboardService::ERR_FORBIDDEN_NOT_OWNER_OR_ADMIN]], $this->outcome($this->controller()->schedule('owner', '2030-01-01')));
		$this->assertSame([400, ['error' => 'Operation failed']], $this->outcome($this->controller()->schedule('boom', '2030-01-01')));
	}

	public function testBreadcrumbsHideWhatTheViewerCannotSee(): void {
		$method = new ReflectionMethod(DashboardApiController::class, 'visibleBreadcrumbs');
		$this->dashboardService->method('getVisibleToUser')->willReturn([
			['dashboard' => $this->dashboard('root'), 'source' => 'user'],
			['dashboard' => $this->dashboard('mid'), 'source' => 'user'],
		]);
		$this->treeService->method('computeBreadcrumbs')->willReturn([
			['uuid' => 'root', 'name' => 'Root', 'slug' => 'r'],
			['uuid' => 'secret', 'name' => 'Secret', 'slug' => 's'],
			['name' => 'No uuid'],
			['uuid' => 'mid', 'name' => 'Mid', 'slug' => 'm', 'hidden' => 'kept'],
		]);

		$this->assertSame([], $method->invoke($this->controller(), $this->dashboard('leaf', null), 'alice'));
		$this->assertSame([], $method->invoke($this->controller(), $this->dashboard('leaf', ''), 'alice'));
		$this->assertSame(
			[
				['uuid' => 'root', 'name' => 'Root', 'slug' => 'r', 'hidden' => false],
				['uuid' => null, 'name' => null, 'slug' => null, 'hidden' => true],
				['uuid' => null, 'name' => null, 'slug' => null, 'hidden' => true],
				['uuid' => 'mid', 'name' => 'Mid', 'slug' => 'm', 'hidden' => 'kept'],
			],
			$method->invoke($this->controller(), $this->dashboard('leaf', 'mid'), 'alice')
		);
	}
}//end class
