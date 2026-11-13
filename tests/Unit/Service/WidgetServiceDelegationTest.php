<?php

/**
 * Unit tests for WidgetService: discovery, item loading and placement delegation.
 *
 * @category Test
 * @package  Unit\Service
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Service\MenuService;
use OCA\LaunchPad\Service\PlacementService;
use OCA\LaunchPad\Service\WidgetFormatter;
use OCA\LaunchPad\Service\WidgetItemLoader;
use OCA\LaunchPad\Service\WidgetPlatform;
use OCA\LaunchPad\Service\WidgetRendering;
use OCA\LaunchPad\Service\WidgetService;
use OCP\Dashboard\IManager;
use OCP\Dashboard\IWidget;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\WidgetService
 * @uses   \OCA\LaunchPad\Service\WidgetPlatform
 * @uses   \OCA\LaunchPad\Service\WidgetRendering
 * @uses   \OCA\LaunchPad\Service\MenuService
 * @uses   \OCA\LaunchPad\Db\WidgetPlacement
 */
class WidgetServiceDelegationTest extends TestCase {
	private IManager&MockObject $manager;
	private IUserSession&MockObject $session;
	private PlacementService&MockObject $placements;
	private WidgetFormatter&MockObject $formatter;
	private WidgetItemLoader&MockObject $loader;
	private WidgetService $service;

	protected function setUp(): void {
		$this->manager = $this->createMock(IManager::class);
		$this->session = $this->createMock(IUserSession::class);
		$this->placements = $this->createMock(PlacementService::class);
		$this->formatter = $this->createMock(WidgetFormatter::class);
		$this->loader = $this->createMock(WidgetItemLoader::class);
		$this->service = new WidgetService(
			platform: new WidgetPlatform(dashboardManager: $this->manager, userSession: $this->session),
			placementService: $this->placements,
			rendering: new WidgetRendering(widgetFormatter: $this->formatter, widgetItemLoader: $this->loader),
			menuService: new MenuService()
		);
	}

	public function testAvailableWidgetsAreFormattedForTheUserAndSortedByOrder(): void {
		$a = $this->createMock(IWidget::class);
		$b = $this->createMock(IWidget::class);
		$this->manager->method('getWidgets')->willReturn(['a' => $a, 'b' => $b]);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$this->session->method('getUser')->willReturn($user);
		$this->formatter->expects($this->exactly(2))
			->method('format')
			->willReturnCallback(function (IWidget $widget, string $userId) use ($a): array {
				$this->assertSame('alice', $userId);
				return $widget === $a ? ['id' => 'a', 'order' => 20] : ['id' => 'b', 'order' => 5];
			});

		$result = $this->service->getAvailableWidgets();

		$this->assertSame([['id' => 'b', 'order' => 5], ['id' => 'a', 'order' => 20]], $result);
	}

	public function testAvailableWidgetsUseAnEmptyUserIdWithoutASession(): void {
		$this->manager->method('getWidgets')->willReturn([$this->createMock(IWidget::class)]);
		$this->session->method('getUser')->willReturn(null);
		$this->formatter->expects($this->once())
			->method('format')
			->with($this->anything(), '')
			->willReturn(['id' => 'x', 'order' => 0]);

		$this->assertSame([['id' => 'x', 'order' => 0]], $this->service->getAvailableWidgets());
	}

	public function testWidgetItemsAreLoadedFromTheRegisteredWidgets(): void {
		$widgets = ['a' => $this->createMock(IWidget::class)];
		$this->manager->method('getWidgets')->willReturn($widgets);
		$this->loader->expects($this->once())
			->method('loadItems')
			->with($widgets, 'alice', ['a'], 3)
			->willReturn(['a' => ['items' => []]]);

		$this->assertSame(['a' => ['items' => []]], $this->service->getWidgetItems(userId: 'alice', widgetIds: ['a'], limit: 3));
	}

	public function testAddWidgetDelegatesWhenThereIsNoContent(): void {
		$placement = new WidgetPlacement();
		$this->placements->expects($this->once())
			->method('addWidget')
			->with(3, 'notes', ['gridX' => 1])
			->willReturn($placement);

		$this->assertSame($placement, $this->service->addWidget(dashboardId: 3, widgetId: 'notes', layout: ['gridX' => 1]));
	}

	public function testAddWidgetRejectsInvalidMenuContentBeforeSaving(): void {
		$this->placements->expects($this->never())->method('addWidget');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Menu style must be one of: dropdown, megamenu, tree');
		$this->service->addWidget(dashboardId: 3, widgetId: 'menu', layout: ['content' => ['style' => 'carousel']]);
	}

	public function testValidateWidgetContentIgnoresNonMenuWidgets(): void {
		$this->service->validateWidgetContent(widgetType: 'text', content: ['style' => 'carousel']);

		$this->addToAssertionCount(1);
	}

	public function testValidateWidgetContentAcceptsAValidMenu(): void {
		$this->service->validateWidgetContent(widgetType: 'menu', content: ['style' => 'tree', 'items' => 'not-a-list']);

		$this->addToAssertionCount(1);
	}

	public function testTileAndPlacementCallsDelegate(): void {
		$placement = new WidgetPlacement();
		$this->placements->expects($this->once())->method('addTileFromArray')->with(3, ['title' => 'T'])->willReturn($placement);
		$this->placements->expects($this->once())->method('removePlacement')->with(11);
		$this->placements->expects($this->once())->method('getPlacement')->with(12)->willReturn($placement);
		$this->placements->expects($this->once())->method('getDashboardPlacements')->with(3)->willReturn([$placement]);

		$this->assertSame($placement, $this->service->addTileFromArray(dashboardId: 3, tileData: ['title' => 'T']));
		$this->service->removePlacement(placementId: 11);
		$this->assertSame($placement, $this->service->getPlacement(placementId: 12));
		$this->assertSame([$placement], $this->service->getDashboardPlacements(dashboardId: 3));
	}

	public function testUpdatePlacementWithoutContentDoesNotLoadThePlacement(): void {
		$placement = new WidgetPlacement();
		$this->placements->expects($this->never())->method('getPlacement');
		$this->placements->expects($this->once())->method('updatePlacement')->with(4, ['gridX' => 2])->willReturn($placement);

		$this->assertSame($placement, $this->service->updatePlacement(placementId: 4, data: ['gridX' => 2]));
	}
}
