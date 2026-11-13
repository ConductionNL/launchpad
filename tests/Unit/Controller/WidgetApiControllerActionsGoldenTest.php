<?php

/**
 * WidgetApiController actions golden test
 *
 * Pins, on the code before its split, what the widget actions answer
 * once their caller guard has passed: the available-widget filter, the
 * add-widget refusals (missing id, dashboard permission, role filter,
 * container depth) and its service-error mapping, the tile and placement
 * update outcomes (style permission, acknowledgement fields, depth),
 * the news limit and the calendar range checks. The caller guards
 * themselves are pinned by WidgetApiControllerGuardGoldenTest.
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

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use OCA\LaunchPad\Controller\RequestActor;
use OCA\LaunchPad\Controller\WidgetApiController;
use OCA\LaunchPad\Controller\WidgetApiPolicy;
use OCA\LaunchPad\Controller\WidgetApiSources;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Exception\QuotaExceededException;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\CalendarWidgetService;
use OCA\LaunchPad\Service\NewsWidgetService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCA\LaunchPad\Service\WidgetPlacementService;
use OCA\LaunchPad\Service\WidgetService;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Mirrors the controller constructor.
 */
class WidgetApiControllerActionsGoldenTest extends TestCase {
	private IRequest $request;
	private WidgetService $widgetService;
	private PermissionService $permissionService;
	private NewsWidgetService $newsService;
	private CalendarWidgetService $calendarService;
	private WidgetPlacementService $placementService;
	private RoleFeaturePermissionService $roleFeaturePerm;

	/** @var array<string,mixed> */
	private array $params = [];

	protected function setUp(): void {
		$this->request = $this->createMock(IRequest::class);
		$this->request->method('getParam')->willReturnCallback(fn (string $key, $default = null) => $this->params[$key] ?? $default);
		$this->widgetService = $this->createMock(WidgetService::class);
		$this->permissionService = $this->createMock(PermissionService::class);
		$this->newsService = $this->createMock(NewsWidgetService::class);
		$this->calendarService = $this->createMock(CalendarWidgetService::class);
		$this->placementService = $this->createMock(WidgetPlacementService::class);
		$this->roleFeaturePerm = $this->createMock(RoleFeaturePermissionService::class);
	}

	private function controller(?string $userId = 'alice'): WidgetApiController {
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($this->createMock(IUser::class));

		return new WidgetApiController(
			$this->request,
			new WidgetApiSources($this->widgetService, $this->newsService, $this->calendarService, $this->placementService),
			$this->permissionService,
			new WidgetApiPolicy($this->roleFeaturePerm, $this->createMock(LoggerInterface::class)),
			new RequestActor($session, $this->createMock(ActionAuthService::class)),
			$userId,
		);
	}

	private function outcome(JSONResponse $response): array {
		return [$response->getStatus(), $response->getData()];
	}

	private function placement(int $id = 9): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setId($id);
		$placement->setWidgetId('w');

		return $placement;
	}

	public function testAvailableWidgetsFilter(): void {
		$widgets = [['id' => 'a'], ['id' => 'b'], ['id' => ''], ['name' => 'no id'], ['id' => 3]];
		$this->widgetService->method('getAvailableWidgets')->willReturn($widgets);
		$this->roleFeaturePerm->method('getAllowedWidgetIds')->willReturnOnConsecutiveCalls(null, ['b', '3', '']);
		$this->roleFeaturePerm->expects($this->exactly(2))->method('getAllowedWidgetIds');

		$this->assertSame([200, $widgets], $this->outcome($this->controller(null)->listAvailable()));
		$this->assertSame([200, $widgets], $this->outcome($this->controller()->listAvailable()));
		$this->assertSame([200, [['id' => 'b'], ['id' => 3]]], $this->outcome($this->controller()->listAvailable()));
	}

	/**
	 * Add-widget refusals after the caller guard.
	 *
	 * @return array<string,array{0:?string,1:bool,2:bool,3:mixed,4:int}>
	 */
	public static function addWidgetRefusals(): array {
		return [
			'no widget id'      => [null, true, true, null, 400],
			'empty widget id'   => ['', true, true, null, 400],
			'dashboard refused' => ['w', false, true, null, 403],
			'role refused'      => ['w', true, false, null, 403],
			'too deep'          => ['w', true, true, ['placements' => 'deep'], 400],
			'other depth error' => ['w', true, true, ['placements' => 'odd'], 400],
		];
	}

	/**
	 * @dataProvider addWidgetRefusals
	 */
	public function testAddWidgetRefusal(?string $widgetId, bool $canAdd, bool $roleAllows, mixed $content, int $status): void {
		$this->permissionService->method('canAddWidget')->willReturn($canAdd);
		$this->roleFeaturePerm->method('isWidgetAllowed')->willReturn($roleAllows);
		$this->params = ['content' => $content];
		$this->placementService->method('validateContainerDepth')->willReturnCallback(static function (array $content): void {
			throw new InvalidArgumentException($content['placements'] === 'deep' ? 'container_depth_exceeded' : 'weird');
		});
		$this->widgetService->expects($this->never())->method('addWidget');

		$this->assertSame($status, $this->controller()->addWidget(dashboardId: 3, widgetId: $widgetId)->getStatus());
	}

	public function testRoleFilterIsNotAskedWhenTheDashboardRefuses(): void {
		$this->permissionService->method('canAddWidget')->willReturn(false);
		$this->roleFeaturePerm->expects($this->never())->method('isWidgetAllowed');

		$this->assertSame([403, ['error' => 'Access denied']], $this->outcome($this->controller()->addWidget(dashboardId: 3, widgetId: 'w')));
	}

	public function testAddWidgetPassesLayoutAndContent(): void {
		$this->permissionService->method('canAddWidget')->willReturn(true);
		$this->roleFeaturePerm->method('isWidgetAllowed')->willReturn(true);
		$calls = [];
		$this->widgetService->method('addWidget')->willReturnCallback(function (int $dashboardId, string $widgetId, array $layout) use (&$calls): WidgetPlacement {
			$calls[] = [$dashboardId, $widgetId, $layout];
			if ($widgetId === 'full') {
				throw new QuotaExceededException(quota: QuotaExceededException::QUOTA_WIDGETS, limit: 1, current: 1);
			}

			if ($widgetId === 'boom') {
				throw new RuntimeException('x');
			}

			return $this->placement();
		});

		$this->params = ['content' => ['label' => 'L']];
		$created = $this->controller()->addWidget(3, 'w', 1, 2, 3, 4);
		$this->params = ['content' => 'text'];
		$this->controller()->addWidget(3, 'w');
		$full = $this->controller()->addWidget(3, 'full');
		$boom = $this->controller()->addWidget(3, 'boom');

		$this->assertSame(201, $created->getStatus());
		$this->assertSame(9, $created->getData()['id']);
		$this->assertSame([
			[3, 'w', ['gridX' => 1, 'gridY' => 2, 'gridWidth' => 3, 'gridHeight' => 4, 'content' => ['label' => 'L']]],
			[3, 'w', ['gridX' => 0, 'gridY' => 0, 'gridWidth' => 4, 'gridHeight' => 4, 'content' => null]],
		], array_slice($calls, 0, 2));
		$this->assertSame(409, $full->getStatus());
		$this->assertSame([400, ['error' => 'Operation failed']], $this->outcome($boom));
	}

	public function testAddTileOutcomes(): void {
		$this->permissionService->method('canAddWidget')->willReturnOnConsecutiveCalls(false, true, true, true);
		$calls = 0;
		$this->widgetService->method('addTileFromArray')->willReturnCallback(function () use (&$calls): WidgetPlacement {
			$calls++;
			return match ($calls) {
				1 => $this->placement(4),
				2 => throw new QuotaExceededException(quota: QuotaExceededException::QUOTA_WIDGETS, limit: 1, current: 1),
				default => throw new RuntimeException('x'),
			};
		});

		$this->assertSame([403, ['error' => 'Access denied']], $this->outcome($this->controller()->addTile(dashboardId: 3)));
		$this->assertSame(201, $this->controller()->addTile(dashboardId: 3)->getStatus());
		$this->assertSame(409, $this->controller()->addTile(dashboardId: 3)->getStatus());
		$this->assertSame([400, ['error' => 'Operation failed']], $this->outcome($this->controller()->addTile(dashboardId: 3)));
	}

	/**
	 * Placement-update cases: style permission, params, ack permission, status.
	 *
	 * @return array<string,array{0:bool,1:array<string,mixed>,2:bool,3:string,4:int}>
	 */
	public static function placementUpdates(): array {
		return [
			'style refused'         => [false, [], true, 'ok', 403],
			'ack refused'           => [true, ['reacknowledgeOnChange' => false], false, 'ok', 403],
			'ack allowed'           => [true, ['acknowledgementPrompt' => 'p'], true, 'ok', 200],
			'no ack fields'         => [true, ['title' => 'x'], false, 'ok', 200],
			'too deep'              => [true, ['content' => ['placements' => 'deep']], false, 'ok', 400],
			'service error'         => [true, [], false, 'boom', 400],
		];
	}

	/**
	 * @dataProvider placementUpdates
	 */
	public function testPlacementUpdate(bool $canStyle, array $params, bool $canAck, string $service, int $status): void {
		$this->params = $params;
		$this->permissionService->method('canStyleWidget')->willReturn($canStyle);
		$this->permissionService->method('canManageAcknowledgement')->willReturn($canAck);
		$this->placementService->method('validateContainerDepth')->willThrowException(new InvalidArgumentException('container_depth_exceeded'));
		$this->widgetService->method('updatePlacement')->willReturnCallback(function () use ($service): WidgetPlacement {
			if ($service === 'boom') {
				throw new RuntimeException('x');
			}

			return $this->placement();
		});

		$response = $this->controller()->updatePlacement(placementId: 9);

		$this->assertSame($status, $response->getStatus());
		if ($params === ['reacknowledgeOnChange' => false]) {
			$this->assertSame(['error' => 'Only an admin or the template owner may set an acknowledgement requirement'], $response->getData());
		}
	}

	/**
	 * News limits and their outcome.
	 *
	 * @return array<string,array{0:?int,1:int|string}>
	 */
	public static function newsLimits(): array {
		return ['null' => [null, 10], 'zero' => [0, 'refused'], 'one' => [1, 1], 'fifty' => [50, 50], 'over' => [51, 'refused'], 'negative' => [-1, 'refused']];
	}

	/**
	 * @dataProvider newsLimits
	 */
	public function testNewsLimit(?int $limit, int|string $expected): void {
		$this->permissionService->method('canViewPlacement')->willReturn(true);
		$this->newsService->method('getItemsForPlacement')->willReturnCallback(static fn (int $placementId, int $limit): array => ['limit' => $limit]);

		$outcome = $this->outcome($this->controller()->newsItems(placementId: 4, limit: $limit));

		if ($expected === 'refused') {
			$this->assertSame(400, $outcome[0]);
			$this->assertSame('limit out of range (1..50)', $outcome[1]['message'] ?? $outcome[1]['error'] ?? null);
			return;
		}

		$this->assertSame([200, ['limit' => $expected]], $outcome);
	}

	/**
	 * Calendar ranges and the window passed on (or the refusal message).
	 *
	 * @return array<string,array{0:string,1:string,2:string}>
	 */
	public static function calendarRanges(): array {
		return [
			'no from'      => ['', '2026-01-02T00:00:00+00:00', 'Both from and to are required ISO 8601 timestamps'],
			'no to'        => ['2026-01-01T00:00:00+00:00', '', 'Both from and to are required ISO 8601 timestamps'],
			'bad date'     => ['yesterday-ish', '2026-01-02T00:00:00+00:00', 'Invalid date format'],
			'inverted'     => ['2026-01-02T00:00:00+00:00', '2026-01-01T00:00:00+00:00', '`to` must be greater than or equal to `from`'],
			'same instant' => ['2026-01-01T00:00:00+00:00', '2026-01-01T00:00:00+00:00', '2026-01-01T00:00:00+00:00|2026-01-01T00:00:00+00:00'],
			'one year'     => ['2026-01-01T00:00:00+00:00', '2027-01-01T00:00:00+00:00', '2026-01-01T00:00:00+00:00|2027-01-01T00:00:00+00:00'],
			'capped'       => ['2026-01-01T00:00:00+00:00', '2028-06-01T00:00:00+00:00', '2026-01-01T00:00:00+00:00|2027-01-01T00:00:00+00:00'],
		];
	}

	/**
	 * @dataProvider calendarRanges
	 */
	public function testCalendarRange(string $from, string $to, string $expected): void {
		$this->widgetService->method('getPlacement')->willReturn($this->placement());
		$this->permissionService->method('canViewPlacement')->willReturn(true);
		$this->calendarService->method('getEvents')->willReturnCallback(static fn (array $config, string $from, string $to): array => ['window' => $from . '|' . $to]);

		[$status, $data] = $this->outcome($this->controller()->calendarEvents(placementId: 4, from: $from, to: $to));

		if (str_contains($expected, '|') === true) {
			$this->assertSame([200, ['window' => $expected]], [$status, $data]);
			return;
		}

		$this->assertSame(400, $status);
		$this->assertSame($expected, $data['message'] ?? $data['error'] ?? null);
	}

	public function testCalendarPlacementLookupAndEventFailures(): void {
		$this->permissionService->method('canViewPlacement')->willReturnOnConsecutiveCalls(false, true);
		$this->widgetService->method('getPlacement')->willReturnCallback(function (int $placementId): WidgetPlacement {
			if ($placementId === 404) {
				throw new Exception('gone');
			}

			return $this->placement($placementId);
		});
		$this->calendarService->method('getEvents')->willThrowException(new RuntimeException('caldav down'));
		$range = ['from' => '2026-01-01T00:00:00+00:00', 'to' => '2026-01-02T00:00:00+00:00'];

		$this->assertSame(404, $this->controller()->calendarEvents(404, ...$range)->getStatus());
		$this->assertSame([403, ['error' => 'Access denied']], $this->outcome($this->controller()->calendarEvents(5, ...$range)));
		$this->assertSame([400, ['error' => 'Operation failed']], $this->outcome($this->controller()->calendarEvents(5, ...$range)));
	}

	/**
	 * A permitted removal hands the placement id to the service and answers ok.
	 *
	 * @return void
	 */
	public function testRemovePlacementRemovesWhatThePermissionAllows(): void {
		$this->permissionService->expects($this->once())->method('canRemoveWidget')
			->with('alice', 9)->willReturn(true);
		$this->widgetService->expects($this->once())->method('removePlacement')->with(9);

		$this->assertSame([200, ['status' => 'ok']], $this->outcome($this->controller()->removePlacement(9)));
	}//end testRemovePlacementRemovesWhatThePermissionAllows()

	/**
	 * A refused removal is 403 and the service is never asked.
	 *
	 * @return void
	 */
	public function testRemovePlacementRefusedByPermissionIsForbidden(): void {
		$this->permissionService->method('canRemoveWidget')->willReturn(false);
		$this->widgetService->expects($this->never())->method('removePlacement');

		$this->assertSame([403, ['error' => 'Access denied']], $this->outcome($this->controller()->removePlacement(9)));
	}//end testRemovePlacementRefusedByPermissionIsForbidden()

	/**
	 * A failing removal answers a generic 400 without the exception's message.
	 *
	 * @return void
	 */
	public function testRemovePlacementFailureHidesTheCause(): void {
		$this->permissionService->method('canRemoveWidget')->willReturn(true);
		$this->widgetService->method('removePlacement')->willThrowException(new Exception('row 9 locked by another session'));

		$this->assertSame([400, ['error' => 'Operation failed']], $this->outcome($this->controller()->removePlacement(9)));
	}//end testRemovePlacementFailureHidesTheCause()
}//end class
