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
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\PublicWidgetReadService
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

	protected function setUp(): void {
		$this->dashMapper = $this->createMock(DashboardMapper::class);
		$this->dashMapper->method('findByUuid')->willReturnCallback(static function (string $uuid): Dashboard {
			if ($uuid !== 'today') {
				throw new DoesNotExistException('gone');
			}
			$dashboard = new Dashboard();
			$dashboard->setId(self::SHARED_ID);
			return $dashboard;
		});

		$this->placementMapper = $this->createMock(WidgetPlacementMapper::class);
		$this->placementMapper->method('find')->willReturnCallback(function (int $id): WidgetPlacement {
			if (isset($this->placements[$id]) === false) {
				throw new DoesNotExistException('none');
			}
			return $this->placements[$id];
		});

		$this->weather = $this->createMock(WeatherServiceInterface::class);
		$this->liveData = $this->createMock(LiveTileService::class);
		$this->announcements = $this->createMock(AnnouncementServiceInterface::class);
	}

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
			weather: $this->weather,
			liveData: $this->liveData,
			announcements: $this->announcements,
			visibility: $visibility,
		);
	}

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
		$placement->setContentArray($content);
		$this->placements[$id] = $placement;
	}

	/**
	 * A published announcement.
	 *
	 * @param string       $title    The title.
	 * @param string       $kind     news or notice.
	 * @param string|null  $category The category.
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
		$item->setTargetGroupList([]);
		return $item;
	}

	public function testAPlacementNotOnTheSharedDashboardIsRefused(): void {
		$this->place(id: 1, widgetId: 'weather', dashboardId: 99);
		$this->weather->expects($this->never())->method('resolveForPlacement');

		$this->expectException(DoesNotExistException::class);

		$this->service()->read(dashboardUuids: ['today'], placementId: 1);
	}

	public function testADeletedSharedDashboardSharesNothing(): void {
		$this->place(id: 1, widgetId: 'weather');

		$this->expectException(DoesNotExistException::class);

		$this->service()->read(dashboardUuids: ['gone'], placementId: 1);
	}

	public function testAnUnknownPlacementIsRefused(): void {
		$this->expectException(DoesNotExistException::class);

		$this->service()->read(dashboardUuids: ['today'], placementId: 404);
	}

	public function testAWidgetThatIsNotLiveIsRefused(): void {
		$this->place(id: 2, widgetId: 'files');

		$this->expectException(DoesNotExistException::class);

		$this->service()->read(dashboardUuids: ['today'], placementId: 2);
	}

	public function testAWidgetTheRulesHideIsRefused(): void {
		$this->place(id: 3, widgetId: 'weather');
		$visibility = $this->createMock(ConditionalServiceInterface::class);
		$visibility->expects($this->once())->method('visiblePlacements')
			->with($this->anything(), '', false)
			->willReturn([]);
		$this->weather->expects($this->never())->method('resolveForPlacement');

		$this->expectException(DoesNotExistException::class);

		$this->service($visibility)->read(dashboardUuids: ['today'], placementId: 3);
	}

	public function testTheWeatherReadsForTheSharedPlacement(): void {
		$this->place(id: 4, widgetId: 'weather');
		$visibility = $this->createMock(ConditionalServiceInterface::class);
		$visibility->method('visiblePlacements')->willReturnArgument(0);
		$this->weather->expects($this->once())->method('resolveForPlacement')
			->with(4, '')
			->willReturn(['location' => 'Zuiddrecht', 'tempValue' => 12]);

		$result = $this->service($visibility)->read(dashboardUuids: ['menu', 'today'], placementId: 4);

		$this->assertSame(['widget' => 'weather', 'data' => ['location' => 'Zuiddrecht', 'tempValue' => 12]], $result);
	}

	public function testTheLiveDataValueReadsForTheSharedPlacement(): void {
		$this->place(id: 5, widgetId: 'livetile');
		$this->liveData->expects($this->once())->method('resolveForPlacement')
			->with(5)
			->willReturn(['value' => '12 min']);

		$result = $this->service()->read(dashboardUuids: ['today'], placementId: 5);

		$this->assertSame(['widget' => 'livetile', 'data' => ['value' => '12 min']], $result);
	}

	public function testTheNewsListsOnlyTheAnnouncementsForEveryone(): void {
		$this->place(id: 6, widgetId: 'announcements');
		// The service gives only the announcements for everyone; one targeted at a
		// group is never among them, so it never reaches an anonymous visitor.
		$this->announcements->expects($this->once())->method('visibleToEveryone')
			->willReturn([$this->announcement('Stadskantoor open'), $this->announcement('Storing', 'notice')]);
		$this->announcements->expects($this->never())->method('visibleTo');

		$result = $this->service()->read(dashboardUuids: ['today'], placementId: 6);

		$this->assertSame('announcements', $result['widget']);
		$this->assertSame(['Stadskantoor open'], array_column($result['data']['announcements'], 'title'));
		$this->assertArrayNotHasKey('authorId', $result['data']['announcements'][0]);
		$this->assertArrayNotHasKey('targetGroups', $result['data']['announcements'][0]);
	}

	public function testTheNewsKeepsToTheWidgetsCategoryAndLimit(): void {
		$this->place(id: 7, widgetId: 'announcements', content: ['category' => 'Gemeente', 'limit' => 2]);
		$this->announcements->method('visibleToEveryone')->willReturn([
			$this->announcement('Een', category: 'Gemeente'),
			$this->announcement('Anders', category: 'Sport'),
			$this->announcement('Twee', category: 'Gemeente'),
			$this->announcement('Drie', category: 'Gemeente'),
		]);

		$result = $this->service()->read(dashboardUuids: ['today'], placementId: 7);

		$this->assertSame(['Een', 'Twee'], array_column($result['data']['announcements'], 'title'));
	}
}
