<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * PublicWidgetReadService: a live widget's data on a public link or a kiosk
 * screen, only for a placement on a dashboard the token shows (REQ-PSHR-011).
 *
 * @spec openspec/changes/public-live-widgets/specs/dashboard-public-share/spec.md#requirement-live-widgets-on-a-public-link-and-a-kiosk-screen-req-pshr-011
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Service;

use OCA\LaunchPad\Db\Announcement;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Kernel\Contracts\AnnouncementServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\ConditionalServiceInterface;
use OCA\LaunchPad\Kernel\Contracts\WeatherServiceInterface;
use OCA\LaunchPad\Service\LiveTileService;
use OCA\LaunchPad\Service\PublicWidgetReadService;
use OCA\LaunchPad\Service\PublicWidgetSources;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\PublicWidgetReadService
 * @covers \OCA\LaunchPad\Service\PublicWidgetSources
 */
class PublicWidgetReadServiceTest extends TestCase {

	/**
	 * The id of the shared dashboard "Today in Zuiddrecht".
	 */
	private const SHARED_ID = 7;

	/** @var DashboardMapper&MockObject */
	private $dashMapper;

	/** @var WidgetPlacementMapper&MockObject */
	private $placementMapper;

	/** @var WeatherServiceInterface&MockObject */
	private $weather;

	/** @var LiveTileService&MockObject */
	private $liveData;

	/** @var AnnouncementServiceInterface&MockObject */
	private $announcements;

	/**
	 * The placements by id.
	 *
	 * @var array<int, WidgetPlacement>
	 */
	private array $placements = [];

	/**
	 * Mappers over one shared dashboard, `today`, and the stored placements.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->dashMapper = $this->createMock(originalClassName: DashboardMapper::class);
		$this->dashMapper->method('findByUuid')->willReturnCallback(static function (string $uuid): Dashboard {
			if ($uuid !== 'today') {
				throw new DoesNotExistException(msg: 'gone');
			}
			$dashboard = new Dashboard();
			$dashboard->setId(self::SHARED_ID);
			return $dashboard;
		});

		$this->placementMapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$this->placementMapper->method('find')->willReturnCallback(function (int $id): WidgetPlacement {
			if (isset($this->placements[$id]) === false) {
				throw new DoesNotExistException(msg: 'none');
			}
			return $this->placements[$id];
		});

		$this->weather = $this->createMock(originalClassName: WeatherServiceInterface::class);
		$this->liveData = $this->createMock(originalClassName: LiveTileService::class);
		$this->announcements = $this->createMock(originalClassName: AnnouncementServiceInterface::class);
	}//end setUp()

	/**
	 * The service, with or without visibility rules.
	 *
	 * @param ConditionalServiceInterface|null $visibility The rules.
	 *
	 * @return PublicWidgetReadService
	 */
	private function service(?ConditionalServiceInterface $visibility = null): PublicWidgetReadService {
		return new PublicWidgetReadService(
			dashMapper: $this->dashMapper,
			placementMapper: $this->placementMapper,
			sources: new PublicWidgetSources(weather: $this->weather, liveData: $this->liveData, announcements: $this->announcements),
			visibility: $visibility,
		);
	}//end service()

	/**
	 * Store a placement.
	 *
	 * @param integer              $id          The id.
	 * @param string               $widgetId    The widget type.
	 * @param integer              $dashboardId The dashboard it is on.
	 * @param array<string, mixed> $content     Its content.
	 *
	 * @return void
	 */
	private function place(int $id, string $widgetId, int $dashboardId = self::SHARED_ID, array $content = []): void {
		$placement = new WidgetPlacement();
		$placement->setId($id);
		$placement->setWidgetId($widgetId);
		$placement->setDashboardId($dashboardId);
		$placement->setContentArray(content: $content);
		$this->placements[$id] = $placement;
	}//end place()

	/**
	 * A published announcement for everyone.
	 *
	 * @param string      $title    The title.
	 * @param string      $kind     news or notice.
	 * @param string|null $category The category.
	 *
	 * @return Announcement
	 */
	private function announcement(string $title, string $kind = 'news', ?string $category = null): Announcement {
		$item = new Announcement();
		$item->setUuid('uuid-' . $title);
		$item->setKind($kind);
		$item->setTitle($title);
		$item->setCategory($category);
		$item->setStatus('published');
		$item->setAuthorId('karin');
		$item->setTargetGroupList(groups: []);
		return $item;
	}//end announcement()

	/**
	 * A placement on another dashboard is refused, and nothing is fetched for it.
	 *
	 * @return void
	 */
	public function testAPlacementNotOnTheSharedDashboardIsRefused(): void {
		$this->place(id: 1, widgetId: 'weather', dashboardId: 99);
		$this->weather->expects($this->never())->method('resolveForPlacement');

		$this->expectException(exception: DoesNotExistException::class);

		$this->service()->read(dashboardUuids: ['today'], placementId: 1);
	}//end testAPlacementNotOnTheSharedDashboardIsRefused()

	/**
	 * A share whose dashboard was deleted shares no placement.
	 *
	 * @return void
	 */
	public function testADeletedSharedDashboardSharesNothing(): void {
		$this->place(id: 1, widgetId: 'weather');

		$this->expectException(exception: DoesNotExistException::class);

		$this->service()->read(dashboardUuids: ['gone'], placementId: 1);
	}//end testADeletedSharedDashboardSharesNothing()

	/**
	 * An unknown placement is refused.
	 *
	 * @return void
	 */
	public function testAnUnknownPlacementIsRefused(): void {
		$this->expectException(exception: DoesNotExistException::class);

		$this->service()->read(dashboardUuids: ['today'], placementId: 404);
	}//end testAnUnknownPlacementIsRefused()

	/**
	 * A widget that is not a news, weather or live data widget reads nothing.
	 *
	 * @return void
	 */
	public function testAWidgetThatIsNotLiveIsRefused(): void {
		$this->place(id: 2, widgetId: 'files');

		$this->expectException(exception: DoesNotExistException::class);

		$this->service()->read(dashboardUuids: ['today'], placementId: 2);
	}//end testAWidgetThatIsNotLiveIsRefused()

	/**
	 * A widget the visibility rules hide from a visitor is refused.
	 *
	 * @return void
	 */
	public function testAWidgetTheRulesHideIsRefused(): void {
		$this->place(id: 3, widgetId: 'weather');
		$visibility = $this->createMock(originalClassName: ConditionalServiceInterface::class);
		$visibility->expects($this->once())->method('visiblePlacements')
			->with($this->anything(), '', false)
			->willReturn(value: []);
		$this->weather->expects($this->never())->method('resolveForPlacement');

		$this->expectException(exception: DoesNotExistException::class);

		$this->service(visibility: $visibility)->read(dashboardUuids: ['today'], placementId: 3);
	}//end testAWidgetTheRulesHideIsRefused()

	/**
	 * The weather reads for the shared placement, without a viewing user.
	 *
	 * @return void
	 */
	public function testTheWeatherReadsForTheSharedPlacement(): void {
		$this->place(id: 4, widgetId: 'weather');
		$visibility = $this->createMock(originalClassName: ConditionalServiceInterface::class);
		$visibility->method('visiblePlacements')->willReturnArgument(argumentIndex: 0);
		$this->weather->expects($this->once())->method('resolveForPlacement')
			->with(4, '')
			->willReturn(value: ['location' => 'Zuiddrecht', 'tempValue' => 12]);

		$result = $this->service(visibility: $visibility)->read(dashboardUuids: ['menu', 'today'], placementId: 4);

		$this->assertSame(expected: ['widget' => 'weather', 'data' => ['location' => 'Zuiddrecht', 'tempValue' => 12]], actual: $result);
	}//end testTheWeatherReadsForTheSharedPlacement()

	/**
	 * The live data value reads for the shared placement.
	 *
	 * @return void
	 */
	public function testTheLiveDataValueReadsForTheSharedPlacement(): void {
		$this->place(id: 5, widgetId: 'livetile');
		$this->liveData->expects($this->once())->method('resolveForPlacement')
			->with(5)
			->willReturn(value: ['value' => '12 min']);

		$result = $this->service()->read(dashboardUuids: ['today'], placementId: 5);

		$this->assertSame(expected: ['widget' => 'livetile', 'data' => ['value' => '12 min']], actual: $result);
	}//end testTheLiveDataValueReadsForTheSharedPlacement()

	/**
	 * The news lists the announcements for everyone, never one for a group nor a notice, without author.
	 *
	 * @return void
	 */
	public function testTheNewsListsOnlyTheAnnouncementsForEveryoneAndNoNotices(): void {
		$this->place(id: 6, widgetId: 'announcements');
		$forGroup = $this->announcement(title: 'Inloopspreekuur privacy');
		$forGroup->setTargetGroupList(groups: ['Medewerkers']);
		$this->announcements->expects($this->once())->method('visibleTo')->with('')
			->willReturn(value: [$this->announcement(title: 'Stadskantoor open'), $this->announcement(title: 'Storing', kind: 'notice'), $forGroup]);

		$result = $this->service()->read(dashboardUuids: ['today'], placementId: 6);

		$this->assertSame(expected: 'announcements', actual: $result['widget']);
		$this->assertSame(expected: ['Stadskantoor open'], actual: array_column($result['data']['announcements'], 'title'));
		$this->assertArrayNotHasKey(key: 'authorId', array: $result['data']['announcements'][0]);
		$this->assertArrayNotHasKey(key: 'targetGroups', array: $result['data']['announcements'][0]);
	}//end testTheNewsListsOnlyTheAnnouncementsForEveryoneAndNoNotices()

	/**
	 * The news keeps to the widget's category and limit.
	 *
	 * @return void
	 */
	public function testTheNewsKeepsToTheWidgetsCategoryAndLimit(): void {
		$this->place(id: 7, widgetId: 'announcements', content: ['category' => 'Gemeente', 'limit' => 2]);
		$this->announcements->method('visibleTo')->willReturn(value: [
			$this->announcement(title: 'Een', category: 'Gemeente'),
			$this->announcement(title: 'Anders', category: 'Sport'),
			$this->announcement(title: 'Twee', category: 'Gemeente'),
			$this->announcement(title: 'Drie', category: 'Gemeente'),
		]);

		$result = $this->service()->read(dashboardUuids: ['today'], placementId: 7);

		$this->assertSame(expected: ['Een', 'Twee'], actual: array_column($result['data']['announcements'], 'title'));
	}//end testTheNewsKeepsToTheWidgetsCategoryAndLimit()
}//end class
