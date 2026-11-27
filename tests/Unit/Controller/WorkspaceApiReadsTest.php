<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * What the workspace page carries for the API reads its view makes right
 * after it mounts.
 *
 * @spec openspec/changes/workspace-api-reads-from-initial-state/tasks.md#task-4
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\AnnouncementController;
use OCA\LaunchPad\Controller\DashboardReactionApiController;
use OCA\LaunchPad\Controller\FilesWidgetController;
use OCA\LaunchPad\Controller\PersonalLayerApiController;
use OCA\LaunchPad\Controller\PreferencesController;
use OCA\LaunchPad\Controller\WorkspaceApiReads;
use OCA\LaunchPad\Db\Dashboard;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * Tests for {@see WorkspaceApiReads}.
 */
class WorkspaceApiReadsTest extends TestCase {
	/** @var AnnouncementController&MockObject */
	private AnnouncementController $announcements;

	/** @var PreferencesController&MockObject */
	private PreferencesController $preferences;

	/** @var DashboardReactionApiController&MockObject */
	private DashboardReactionApiController $reactions;

	/** @var PersonalLayerApiController&MockObject */
	private PersonalLayerApiController $layers;

	/** @var FilesWidgetController&MockObject */
	private FilesWidgetController $files;

	/** @var list<string> The controllers the container was asked for. */
	private array $built = [];

	/**
	 * The controller actions, as doubles that answer 200.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->announcements = $this->createMock(originalClassName: AnnouncementController::class);
		$this->announcements->method('index')->willReturn(new JSONResponse(['announcements' => []]));
		$this->preferences = $this->createMock(originalClassName: PreferencesController::class);
		$this->preferences->method('getPreference')->willReturn(new JSONResponse(['value' => '["n1"]']));
		$this->reactions = $this->createMock(originalClassName: DashboardReactionApiController::class);
		$this->reactions->method('getReactions')->willReturn(new JSONResponse(['summary' => []]));
		$this->layers = $this->createMock(originalClassName: PersonalLayerApiController::class);
		$this->layers->method('show')->willReturn(new JSONResponse(['hasLayer' => false]));
		$this->files = $this->createMock(originalClassName: FilesWidgetController::class);
		$this->files->method('contents')->willReturnCallback(
			static fn (int $placementId): JSONResponse => new JSONResponse(['items' => [], 'placement' => $placementId])
		);
	}//end setUp()

	/**
	 * The builder under test, over a container that hands out the doubles.
	 *
	 * @return WorkspaceApiReads The builder.
	 */
	private function reads(): WorkspaceApiReads {
		$controllers = [
			AnnouncementController::class => $this->announcements,
			PreferencesController::class => $this->preferences,
			DashboardReactionApiController::class => $this->reactions,
			PersonalLayerApiController::class => $this->layers,
			FilesWidgetController::class => $this->files,
		];
		$container = $this->createMock(originalClassName: ContainerInterface::class);
		$container->method('get')->willReturnCallback(
			function (string $id) use ($controllers): object {
				$this->built[] = $id;
				return $controllers[$id];
			}
		);

		return new WorkspaceApiReads(container: $container, logger: new NullLogger());
	}//end reads()

	/**
	 * A dashboard.
	 *
	 * @param string|null $owner Its owner, null for a group dashboard.
	 *
	 * @return Dashboard The dashboard.
	 */
	private static function dashboard(?string $owner): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId(7);
		$dashboard->setUuid('d-uuid');
		$dashboard->setUserId($owner);
		return $dashboard;
	}//end dashboard()

	/**
	 * The paths of the carried reads.
	 *
	 * @param array|null $reads The reads.
	 *
	 * @return list<string> Their paths.
	 */
	private static function paths(?array $reads): array {
		return array_map(static fn (array $read): string => $read['path'], $reads ?? []);
	}//end paths()

	/**
	 * Someone else's dashboard: banner, reactions, personal layer and the first
	 * page of each visible Files widget are carried, with the endpoint's answer.
	 *
	 * @return void
	 */
	public function testEveryFirstReadOfSomebodyElsesDashboardIsCarried(): void {
		$layout = [
			['id' => 4, 'widgetId' => 'files', 'isVisible' => 1],
			['id' => 5, 'widgetId' => 'tile', 'isVisible' => 1],
			['id' => 6, 'widgetId' => 'files', 'isVisible' => 0],
		];
		$reads = $this->reads()->firstLoad(userId: 'pieter', opening: ['dashboard' => self::dashboard(owner: null), 'layout' => $layout]);

		$this->assertSame(
			expected: [
				'/api/announcements',
				'/api/preferences/dismissed-notices',
				'/api/dashboards/d-uuid/reactions',
				'/api/dashboards/7/personal-layer',
				'/api/widgets/files/4/contents',
			],
			actual: self::paths(reads: $reads)
		);
		$this->assertSame(expected: ['value' => '["n1"]'], actual: $reads[1]['data']);
		$this->assertSame(expected: WorkspaceApiReads::FILES_FIRST_PAGE, actual: $reads[4]['params']);
		$this->assertSame(expected: ['items' => [], 'placement' => 4], actual: $reads[4]['data']);
	}//end testEveryFirstReadOfSomebodyElsesDashboardIsCarried()

	/**
	 * The viewer's own dashboard: no personal layer (the view never reads it).
	 *
	 * @return void
	 */
	public function testAnOwnDashboardCarriesNoPersonalLayer(): void {
		$reads = $this->reads()->firstLoad(userId: 'pieter', opening: ['dashboard' => self::dashboard(owner: 'pieter'), 'layout' => []]);

		$this->assertNotContains(needle: '/api/dashboards/7/personal-layer', haystack: self::paths(reads: $reads));
		$this->assertNotContains(needle: PersonalLayerApiController::class, haystack: $this->built);
	}//end testAnOwnDashboardCarriesNoPersonalLayer()

	/**
	 * A refused or failing read is left out, so the browser asks and meets the
	 * same answer; the others are still carried.
	 *
	 * @return void
	 */
	public function testARefusedOrFailingReadIsLeftOut(): void {
		$this->announcements = $this->createMock(originalClassName: AnnouncementController::class);
		$this->announcements->method('index')->willReturn(new JSONResponse(['error' => 'Forbidden'], Http::STATUS_FORBIDDEN));
		$this->reactions = $this->createMock(originalClassName: DashboardReactionApiController::class);
		$this->reactions->method('getReactions')->willThrowException(new RuntimeException('database gone'));

		$reads = $this->reads()->firstLoad(userId: 'pieter', opening: ['dashboard' => self::dashboard(owner: 'pieter'), 'layout' => []]);

		$this->assertSame(expected: ['/api/preferences/dismissed-notices'], actual: self::paths(reads: $reads));
	}//end testARefusedOrFailingReadIsLeftOut()

	/**
	 * Nobody logged in: nothing is read at all.
	 *
	 * @return void
	 */
	public function testNothingIsReadForAnAnonymousPage(): void {
		$this->assertNull(actual: $this->reads()->firstLoad(userId: '', opening: ['dashboard' => self::dashboard(owner: null), 'layout' => []]));
		$this->assertSame(expected: [], actual: $this->built);
	}//end testNothingIsReadForAnAnonymousPage()

	/**
	 * At most MAX_FILES_WIDGETS Files widgets get their first page carried.
	 *
	 * @return void
	 */
	public function testOnlyTheFirstFilesWidgetsAreCarried(): void {
		$layout = [];
		for ($id = 1; $id <= WorkspaceApiReads::MAX_FILES_WIDGETS + 2; $id++) {
			$layout[] = ['id' => $id, 'widgetId' => 'files', 'isVisible' => 1];
		}

		$reads = $this->reads()->firstLoad(userId: 'pieter', opening: ['dashboard' => self::dashboard(owner: 'pieter'), 'layout' => $layout]);
		$files = array_filter(self::paths(reads: $reads), static fn (string $path): bool => str_starts_with($path, '/api/widgets/files/'));

		$this->assertCount(expectedCount: WorkspaceApiReads::MAX_FILES_WIDGETS, haystack: $files);
	}//end testOnlyTheFirstFilesWidgetsAreCarried()
}//end class
