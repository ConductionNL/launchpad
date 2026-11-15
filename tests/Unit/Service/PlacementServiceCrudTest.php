<?php

/**
 * Unit tests for PlacementService CRUD: defaults, tile creation, updates and the read-only guard.
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

use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Exception\ShareReadOnlyException;
use OCA\LaunchPad\Service\PlacementService;
use OCA\LaunchPad\Service\PlacementUpdater;
use OCA\LaunchPad\Service\PlacementUpdaters;
use OCA\LaunchPad\Service\PublicShareContext;
use OCA\LaunchPad\Service\QuotaService;
use OCA\LaunchPad\Service\TileUpdater;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\PlacementService
 * @uses   \OCA\LaunchPad\Db\WidgetPlacement
 * @uses   \OCA\LaunchPad\Service\PlacementUpdater
 * @uses   \OCA\LaunchPad\Service\PlacementUpdaters
 * @uses   \OCA\LaunchPad\Service\TileUpdater
 * @uses   \OCA\LaunchPad\Service\PublicShareContext
 * @uses   \OCA\LaunchPad\Exception\ShareReadOnlyException
 * @uses   \OCA\LaunchPad\Exception\ResourceException
 */
class PlacementServiceCrudTest extends TestCase {
	private WidgetPlacementMapper&MockObject $mapper;
	private PublicShareContext $shareContext;
	private QuotaService&MockObject $quota;
	private PlacementService $service;

	protected function setUp(): void {
		$this->mapper = $this->createMock(WidgetPlacementMapper::class);
		$this->shareContext = new PublicShareContext();
		$this->quota = $this->createMock(QuotaService::class);
		$this->service = new PlacementService(
			placementMapper: $this->mapper,
			updaters: new PlacementUpdaters(tileUpdater: new TileUpdater(), placementUpdater: new PlacementUpdater()),
			publicShareContext: $this->shareContext,
			quotaService: $this->quota
		);
	}

	private function insertReturnsArgument(): void {
		$this->mapper->expects($this->once())
			->method('insert')
			->willReturnArgument(0);
	}

	public function testAddWidgetUsesTheDefaultLayout(): void {
		$this->quota->expects($this->once())->method('assertCanAddPlacement')->with(7);
		$this->insertReturnsArgument();

		$placement = $this->service->addWidget(dashboardId: 7, widgetId: 'notes');

		$this->assertSame(7, $placement->getDashboardId());
		$this->assertSame('notes', $placement->getWidgetId());
		$this->assertSame([0, 0, 4, 4], [$placement->getGridX(), $placement->getGridY(), $placement->getGridWidth(), $placement->getGridHeight()]);
		$this->assertSame(0, $placement->getIsCompulsory());
		$this->assertSame(1, $placement->getIsVisible());
		$this->assertSame(1, $placement->getShowTitle());
		$this->assertNull($placement->getContent());
		$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $placement->getCreatedAt());
		$this->assertSame($placement->getCreatedAt(), $placement->getUpdatedAt());
	}

	public function testAddWidgetKeepsGivenLayoutAndContent(): void {
		$this->insertReturnsArgument();

		$placement = $this->service->addWidget(
			dashboardId: 7,
			widgetId: 'text',
			layout: ['gridX' => 2, 'gridY' => 3, 'gridWidth' => 6, 'gridHeight' => 1, 'content' => ['text' => 'Hi']]
		);

		$this->assertSame([2, 3, 6, 1], [$placement->getGridX(), $placement->getGridY(), $placement->getGridWidth(), $placement->getGridHeight()]);
		$this->assertSame(['text' => 'Hi'], $placement->getContentArray());
	}

	public function testAddWidgetIsRefusedForAPublicShareBearer(): void {
		$this->shareContext->markBearer(token: 'tok');
		$this->quota->expects($this->never())->method('assertCanAddPlacement');
		$this->mapper->expects($this->never())->method('insert');

		$this->expectException(ShareReadOnlyException::class);
		$this->service->addWidget(dashboardId: 7, widgetId: 'notes');
	}

	public function testAddTileCreatesATilePlacementWithTileDefaults(): void {
		$this->quota->expects($this->once())->method('assertCanAddPlacement')->with(9);
		$this->insertReturnsArgument();

		$placement = $this->service->addTileFromArray(dashboardId: 9, tileData: ['title' => 'Wiki', 'gridX' => 1]);

		$this->assertStringStartsWith('tile-', $placement->getWidgetId());
		$this->assertSame([1, 0, 2, 2], [$placement->getGridX(), $placement->getGridY(), $placement->getGridWidth(), $placement->getGridHeight()]);
		$this->assertSame('custom', $placement->getTileType());
		$this->assertSame('Wiki', $placement->getTileTitle());
		$this->assertSame(1, $placement->getIsVisible());
	}

	public function testAddTileIsRefusedForAPublicShareBearer(): void {
		$this->shareContext->markBearer(token: 'tok');
		$this->mapper->expects($this->never())->method('insert');

		$this->expectException(ShareReadOnlyException::class);
		$this->service->addTileFromArray(dashboardId: 9, tileData: []);
	}

	public function testUpdatePlacementAppliesAllUpdatersAndStampsUpdatedAt(): void {
		$stored = new WidgetPlacement();
		$stored->setUpdatedAt('2000-01-01 00:00:00');
		$this->mapper->expects($this->once())->method('find')->with(5)->willReturn($stored);
		$this->mapper->expects($this->once())->method('update')->with($stored)->willReturnArgument(0);

		$result = $this->service->updatePlacement(
			placementId: 5,
			data: ['gridX' => 3, 'customTitle' => 'T', 'tileTitle' => 'Tile', 'acknowledgementPrompt' => 'Read']
		);

		$this->assertSame($stored, $result);
		$this->assertSame(3, $result->getGridX());
		$this->assertSame('T', $result->getCustomTitle());
		$this->assertSame('Tile', $result->getTileTitle());
		$this->assertSame('Read', $result->getAcknowledgementPrompt());
		$this->assertNotSame('2000-01-01 00:00:00', $result->getUpdatedAt());
	}

	public function testUpdatePlacementIsRefusedForAPublicShareBearer(): void {
		$this->shareContext->markBearer(token: 'tok');
		$this->mapper->expects($this->never())->method('find');
		$this->mapper->expects($this->never())->method('update');

		$this->expectException(ShareReadOnlyException::class);
		$this->service->updatePlacement(placementId: 5, data: []);
	}

	public function testRemovePlacementDeletesTheFoundEntity(): void {
		$stored = new WidgetPlacement();
		$this->mapper->expects($this->once())->method('find')->with(5)->willReturn($stored);
		$this->mapper->expects($this->once())->method('delete')->with($stored);

		$this->service->removePlacement(placementId: 5);
	}

	public function testRemovePlacementIsRefusedForAPublicShareBearer(): void {
		$this->shareContext->markBearer(token: 'tok');
		$this->mapper->expects($this->never())->method('delete');

		$this->expectException(ShareReadOnlyException::class);
		$this->service->removePlacement(placementId: 5);
	}

	public function testReadsPassThroughToTheMapper(): void {
		$one = new WidgetPlacement();
		$this->mapper->expects($this->once())->method('find')->with(4)->willReturn($one);
		$this->mapper->expects($this->once())->method('findByDashboardId')->with(8)->willReturn([$one]);

		$this->assertSame($one, $this->service->getPlacement(placementId: 4));
		$this->assertSame([$one], $this->service->getDashboardPlacements(dashboardId: 8));
	}

	public function testWithoutGuardsTheServiceStillCreates(): void {
		$service = new PlacementService(
			placementMapper: $this->mapper,
			updaters: new PlacementUpdaters(tileUpdater: new TileUpdater(), placementUpdater: new PlacementUpdater())
		);
		$this->insertReturnsArgument();

		$this->assertSame('notes', $service->addWidget(dashboardId: 1, widgetId: 'notes')->getWidgetId());
	}
}
