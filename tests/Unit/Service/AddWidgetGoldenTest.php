<?php

/**
 * Add-widget golden test
 *
 * Pins the placement row that WidgetService::addWidget writes through a
 * real PlacementService, for a fixed set of layouts and contents, against
 * a recorded fixture.
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

use OCA\LaunchPad\Controller\WidgetApiPolicy;
use OCA\LaunchPad\Controller\WidgetApiSources;
use OCA\LaunchPad\Controller\RequestActor;
use OCA\LaunchPad\Controller\WidgetApiController;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\CalendarWidgetService;
use OCA\LaunchPad\Service\MenuService;
use OCA\LaunchPad\Service\NewsWidgetService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\PlacementService;
use OCA\LaunchPad\Service\PlacementUpdater;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCA\LaunchPad\Service\TileUpdater;
use OCA\LaunchPad\Service\WidgetFormatter;
use OCA\LaunchPad\Service\WidgetItemLoader;
use OCA\LaunchPad\Service\WidgetPlacementService;
use OCA\LaunchPad\Service\WidgetService;
use OCP\Dashboard\IManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The placement rows of addWidget, case by case.
 *
 * @covers \OCA\LaunchPad\Service\WidgetService
 * @covers \OCA\LaunchPad\Service\PlacementService
 * @covers \OCA\LaunchPad\Controller\WidgetApiController
 * @uses   \OCA\LaunchPad\Controller\ResponseHelper
 * @uses   \OCA\LaunchPad\Service\MenuService
 * @uses   \OCA\LaunchPad\Db\WidgetPlacement
 * @uses   \OCA\LaunchPad\Controller\RequestActor
 * @uses   \OCA\LaunchPad\Controller\WidgetApiSources
 * @uses   \OCA\LaunchPad\Controller\WidgetApiPolicy
 */
class AddWidgetGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/add-widget.json';

	/**
	 * The addWidget() argument sets, by case name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function inputs(): array {
		return [
			'defaults'      => ['dashboardId' => 3, 'widgetId' => 'clock'],
			'position'      => ['dashboardId' => 3, 'widgetId' => 'clock', 'gridX' => 2, 'gridY' => 5],
			'size'          => ['dashboardId' => 4, 'widgetId' => 'notes', 'gridWidth' => 6, 'gridHeight' => 1],
			'all'           => ['dashboardId' => 9, 'widgetId' => 'text', 'gridX' => 1, 'gridY' => 2, 'gridWidth' => 3, 'gridHeight' => 7, 'content' => ['text' => 'hi']],
			'empty content' => ['dashboardId' => 3, 'widgetId' => 'text', 'content' => []],
			'menu'          => ['dashboardId' => 3, 'widgetId' => 'menu', 'content' => ['items' => [['label' => 'Home', 'url' => 'https://example.org']]]],
			'menu too deep' => ['dashboardId' => 3, 'widgetId' => 'menu', 'content' => ['items' => [['children' => [['children' => [['children' => [['label' => 'x']]]]]]]]]],
			'not menu deep' => ['dashboardId' => 3, 'widgetId' => 'text', 'content' => ['items' => [['children' => [['children' => [['children' => [['label' => 'x']]]]]]]]]],
		];
	}//end inputs()

	/**
	 * Call addWidget with one argument set.
	 *
	 * @param WidgetService        $service The service under test.
	 * @param array<string, mixed> $args    The case's named arguments.
	 *
	 * @return WidgetPlacement
	 */
	private static function call(WidgetService $service, array $args): WidgetPlacement {
		return $service->addWidget(
			dashboardId: $args['dashboardId'],
			widgetId: $args['widgetId'],
			layout: array_diff_key($args, ['dashboardId' => true, 'widgetId' => true])
		);
	}//end call()

	/**
	 * Run one case and return the inserted row, or the refusal.
	 *
	 * @param array<string, mixed> $args The case's named arguments.
	 *
	 * @return array<string, mixed>
	 */
	private function record(array $args): array {
		$inserted = null;
		$mapper   = $this->createMock(WidgetPlacementMapper::class);
		$mapper->method('insert')->willReturnCallback(
			static function (WidgetPlacement $placement) use (&$inserted): WidgetPlacement {
				$inserted = $placement;
				return $placement;
			}
		);

		$service = $this->service(mapper: $mapper);

		try {
			$returned = self::call(service: $service, args: $args);
		} catch (Throwable $e) {
			return ['refused' => [get_class($e), $e->getMessage()], 'inserted' => $inserted !== null];
		}

		return $this->row(inserted: $inserted) + ['same' => $returned === $inserted];
	}//end record()

	/**
	 * The inserted row without its timestamps.
	 *
	 * @param WidgetPlacement $inserted The inserted placement.
	 *
	 * @return array<string, mixed>
	 */
	private function row(WidgetPlacement $inserted): array {
		$row = $inserted->jsonSerialize();
		$this->assertNotEmpty($inserted->getCreatedAt());
		$this->assertSame($inserted->getCreatedAt(), $inserted->getUpdatedAt());
		unset($row['createdAt'], $row['updatedAt']);

		return ['row' => $row];
	}//end row()

	/**
	 * A real WidgetService over a real PlacementService and the given mapper.
	 *
	 * @param WidgetPlacementMapper $mapper The placement mapper double.
	 *
	 * @return WidgetService
	 */
	private function service(WidgetPlacementMapper $mapper): WidgetService {
		$placements = new PlacementService(
			placementMapper: $mapper,
			tileUpdater: $this->createMock(TileUpdater::class),
			placementUpdater: $this->createMock(PlacementUpdater::class),
		);
		$service = new WidgetService(
			dashboardManager: $this->createMock(IManager::class),
			placementService: $placements,
			widgetFormatter: $this->createMock(WidgetFormatter::class),
			widgetItemLoader: $this->createMock(WidgetItemLoader::class),
			userSession: $this->createMock(IUserSession::class),
			menuService: new MenuService(),
		);

		return $service;
	}//end service()

	/**
	 * The add-widget endpoint writes the request's grid and content.
	 *
	 * @return void
	 */
	public function testEndpointWritesTheRequestedLayout(): void {
		$cases = [
			'no content' => [[], ['dashboardId' => 5, 'widgetId' => 'clock', 'gridX' => 1, 'gridY' => 2, 'gridWidth' => 3, 'gridHeight' => 6]],
			'content'    => [['content' => ['text' => 'hi']], ['dashboardId' => 5, 'widgetId' => 'text']],
			'scalar'     => [['content' => 'nope'], ['dashboardId' => 5, 'widgetId' => 'text', 'gridY' => 9]],
		];

		$actual = [];
		foreach ($cases as $case => [$params, $args]) {
			$inserted = null;
			$mapper   = $this->createMock(WidgetPlacementMapper::class);
			$mapper->method('insert')->willReturnCallback(
				static function (WidgetPlacement $placement) use (&$inserted): WidgetPlacement {
					$inserted = $placement;
					return $placement;
				}
			);

			$user = $this->createMock(IUser::class);
			$session = $this->createMock(IUserSession::class);
			$session->method('getUser')->willReturn($user);
			$permissions = $this->createMock(PermissionService::class);
			$permissions->method('canAddWidget')->willReturn(true);
			$roles = $this->createMock(RoleFeaturePermissionService::class);
			$roles->method('isWidgetAllowed')->willReturn(true);
			$request = $this->createMock(IRequest::class);
			$request->method('getParam')->willReturnCallback(static fn (string $key) => $params[$key] ?? null);

			$controller = new WidgetApiController(
				request: $request,
				sources: new WidgetApiSources(
					widgetService: $this->service(mapper: $mapper),
					newsWidgetService: $this->createMock(NewsWidgetService::class),
					calendarService: $this->createMock(CalendarWidgetService::class),
					placementService: $this->createMock(WidgetPlacementService::class)
				),
				permissionService: $permissions,
				policy: new WidgetApiPolicy(
					roleFeaturePerm: $roles,
					logger: $this->createMock(LoggerInterface::class)
				),
				actor: new RequestActor(
					userSession: $session,
					actionAuth: $this->createMock(ActionAuthService::class)
				),
				userId: 'alice'
			);

			$response = $controller->addWidget(...$args);
			$this->assertSame(201, $response->getStatus(), $case);
			$actual[$case] = $this->row(inserted: $inserted)['row'];
		}

		$fixture = __DIR__.'/fixtures/add-widget-endpoint.json';
		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents($fixture, self::compact(rows: $actual));
			$this->markTestIncomplete('Fixture recorded.');
		}

		$this->assertSame(json_encode(json_decode((string) file_get_contents($fixture))), json_encode($actual));
	}//end testEndpointWritesTheRequestedLayout()

	/**
	 * Every case writes the recorded row or the recorded refusal.
	 *
	 * @return void
	 */
	public function testRowsMatchTheRecordedFixture(): void {
		$actual = [];
		foreach (self::inputs() as $case => $args) {
			$actual[$case] = $this->record(args: $args);
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, self::compact(rows: $actual));
			$this->markTestIncomplete('Fixture recorded.');
		}

		$expected = json_decode((string) file_get_contents(self::FIXTURE));
		foreach ($expected as $case => $result) {
			$this->assertSame(json_encode($result), json_encode($actual[$case]), $case);
		}

		$this->assertSame(array_keys((array) $expected), array_keys($actual));
	}//end testRowsMatchTheRecordedFixture()

	/**
	 * The fixture text: one line per case.
	 *
	 * @param array<string, mixed> $rows The recorded cases.
	 *
	 * @return string
	 */
	private static function compact(array $rows): string {
		$lines = [];
		foreach ($rows as $case => $row) {
			$lines[] = json_encode((string) $case, JSON_UNESCAPED_UNICODE).': '.json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		}

		return "{\n".implode(",\n", $lines)."\n}\n";
	}//end compact()
}//end class
