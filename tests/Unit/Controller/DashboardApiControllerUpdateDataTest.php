<?php

/**
 * DashboardApiController update payload test
 *
 * Pins the patch that `update()` hands to `DashboardService::updateDashboard()`
 * for every combination of optional fields, against a recorded fixture.
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

use OCA\LaunchPad\Controller\DashboardApiController;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AnalyticsService;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCA\LaunchPad\Service\PermissionService;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The update action's patch payload, case by case.
 *
 * @covers \OCA\LaunchPad\Controller\DashboardApiController
 */
class DashboardApiControllerUpdateDataTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/dashboard-update-data.json';

	/**
	 * The update() argument sets, by case name.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function inputs(): array {
		return [
			'nothing'              => [],
			'name'                 => ['name' => 'Team'],
			'empty name'           => ['name' => ''],
			'description'          => ['description' => 'About'],
			'empty description'    => ['description' => ''],
			'placements'           => ['placements' => [['widgetId' => 'w1']]],
			'no placements'        => ['placements' => []],
			'icon'                 => ['icon' => 'star'],
			'empty icon'           => ['icon' => ''],
			'parent'               => ['parentUuid' => 'p-1'],
			'parent sentinel'      => ['parentUuid' => '__null__'],
			'parent empty'         => ['parentUuid' => ''],
			'parent zero'          => ['parentUuid' => '0'],
			'slug'                 => ['slug' => 'team'],
			'empty slug'           => ['slug' => ''],
			'sort order'           => ['sortOrder' => 3],
			'sort order zero'      => ['sortOrder' => 0],
			'everything'           => [
				'sortOrder'   => 2,
				'slug'        => 's',
				'parentUuid'  => '__null__',
				'icon'        => 'i',
				'placements'  => [],
				'description' => 'd',
				'name'        => 'n',
			],
			'metadata with parent' => [
				'name'       => 'n',
				'parentUuid' => 'p-2',
				'sortOrder'  => 1,
			],
		];
	}//end inputs()

	/**
	 * Run update() with one argument set and return the patch it sends.
	 *
	 * @param array<string, mixed> $args The named update() arguments.
	 *
	 * @return array<string, mixed>|null The patch, or null when none was sent.
	 */
	private function patchFor(array $args): ?array {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$permissions = $this->createMock(PermissionService::class);
		$permissions->method('canEditDashboard')->willReturn(true);
		$permissions->method('canEditDashboardMetadata')->willReturn(true);

		$patch     = null;
		$dashboard = new Dashboard();
		$dashboard->setUuid('u-1');
		$service = $this->createMock(DashboardService::class);
		$service->method('updateDashboard')->willReturnCallback(
			function (int $dashboardId, string $userId, array $data) use (&$patch, $dashboard): Dashboard {
				$patch = ['dashboardId' => $dashboardId, 'userId' => $userId, 'data' => $data];
				return $dashboard;
			}
		);

		$controller = new DashboardApiController(
			request: $this->createMock(IRequest::class),
			dashboardService: $service,
			permissionService: $permissions,
			treeService: $this->createMock(DashboardTreeService::class),
			versionService: $this->createMock(DashboardVersionService::class),
			analyticsService: $this->createMock(AnalyticsService::class),
			logger: $this->createMock(LoggerInterface::class),
			userSession: $session,
			actionAuth: $this->createMock(ActionAuthService::class),
			userId: 'alice',
		);

		$response = $controller->update(7, ...$args);
		$this->assertSame(200, $response->getStatus());

		return $patch;
	}//end patchFor()

	/**
	 * Every case sends the recorded patch, keys in the recorded order.
	 *
	 * @return void
	 */
	public function testPatchMatchesTheRecordedFixture(): void {
		$actual = [];
		foreach (self::inputs() as $case => $args) {
			$actual[$case] = $this->patchFor(args: $args);
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
			$this->markTestIncomplete('Fixture recorded.');
		}

		$expected = json_decode((string) file_get_contents(self::FIXTURE), true);
		foreach ($expected as $case => $patch) {
			$this->assertSame($patch, $actual[$case], $case);
		}

		$this->assertSame(array_keys($expected), array_keys($actual));
	}//end testPatchMatchesTheRecordedFixture()
}//end class
