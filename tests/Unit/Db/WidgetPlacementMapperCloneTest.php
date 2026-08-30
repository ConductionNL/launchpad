<?php

/**
 * WidgetPlacementMapperCloneTest
 *
 * Unit tests for {@see WidgetPlacementMapper::cloneToDashboard()}:
 *   - every widget-, tile-, style- and grid-field is byte-for-byte copied
 *   - the `content` blob (nc-widget `{"widgetId": ...}` config) is copied —
 *     regression guard for forked dashboards rendering "No items available"
 *   - the clone targets the destination dashboard id with fresh timestamps
 *
 * REQ-DASH-020.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/specs/dashboards/spec.md
 */

declare(strict_types=1);

namespace Unit\Db;

use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

/**
 * Mapper-layer tests for the placement clone path (REQ-DASH-020).
 */
class WidgetPlacementMapperCloneTest extends TestCase {
	/**
	 * Build a fully populated source placement row.
	 *
	 * @return WidgetPlacement
	 */
	private function makeSourceRow(): WidgetPlacement {
		$row = new WidgetPlacement();
		$row->setDashboardId(4);
		$row->setWidgetId('nc-widget');
		$row->setGridX(0);
		$row->setGridY(4);
		$row->setGridWidth(4);
		$row->setGridHeight(5);
		$row->setIsCompulsory(0);
		$row->setIsVisible(1);
		$row->setStyleConfig('{"accent":"blue"}');
		$row->setCustomTitle('Deals');
		$row->setCustomIcon('chart-line');
		$row->setShowTitle(1);
		$row->setSortOrder(11);
		$row->setTileType('custom');
		$row->setTileTitle('Pipelinq');
		$row->setTileIcon('/custom_apps/pipelinq/img/app.svg');
		$row->setTileIconType('url');
		$row->setTileBackgroundColor('#1c3f6e');
		$row->setTileTextColor('#ffffff');
		$row->setTileLinkType('app');
		$row->setTileLinkValue('pipelinq');
		$row->setContent('{"widgetId":"pipelinq_deals_overview_widget","displayMode":"vertical"}');

		return $row;
	}//end makeSourceRow()

	/**
	 * Build a mapper whose DB-touching methods are stubbed and capture
	 * every inserted entity into `$inserted`.
	 *
	 * @param array<WidgetPlacement> $sourceRows Rows returned by findByDashboardId.
	 * @param array<WidgetPlacement> $inserted Captured inserted clones (by ref).
	 *
	 * @return WidgetPlacementMapper
	 */
	private function makeMapper(array $sourceRows, array &$inserted): WidgetPlacementMapper {
		$mapper = $this->getMockBuilder(WidgetPlacementMapper::class)
			->setConstructorArgs([$this->createMock(IDBConnection::class)])
			->onlyMethods(['findByDashboardId', 'insert'])
			->getMock();

		$mapper->method('findByDashboardId')->willReturn($sourceRows);
		$mapper->method('insert')->willReturnCallback(
			function (WidgetPlacement $entity) use (&$inserted) {
				$inserted[] = $entity;

				return $entity;
			}
		);

		return $mapper;
	}//end makeMapper()

	/**
	 * The clone copies the `content` blob — without it a forked nc-widget
	 * loses its `{"widgetId": ...}` config and renders "No items available".
	 *
	 * @return void
	 */
	public function testCloneCopiesContentBlob(): void {
		$inserted = [];
		$mapper = $this->makeMapper([$this->makeSourceRow()], $inserted);

		$count = $mapper->cloneToDashboard(sourceDashboardId: 4, targetDashboardId: 34);

		$this->assertSame(1, $count);
		$this->assertCount(1, $inserted);
		$this->assertSame(
			'{"widgetId":"pipelinq_deals_overview_widget","displayMode":"vertical"}',
			$inserted[0]->getContent()
		);
	}//end testCloneCopiesContentBlob()

	/**
	 * Every copied field matches the source and the clone points at the
	 * target dashboard (REQ-DASH-020 byte-for-byte contract).
	 *
	 * @return void
	 */
	public function testCloneIsByteForByteWithTargetDashboardId(): void {
		$inserted = [];
		$source = $this->makeSourceRow();
		$mapper = $this->makeMapper([$source], $inserted);

		$mapper->cloneToDashboard(sourceDashboardId: 4, targetDashboardId: 34);

		$clone = $inserted[0];
		$this->assertSame(34, $clone->getDashboardId());
		$this->assertSame($source->getWidgetId(), $clone->getWidgetId());
		$this->assertSame($source->getGridX(), $clone->getGridX());
		$this->assertSame($source->getGridY(), $clone->getGridY());
		$this->assertSame($source->getGridWidth(), $clone->getGridWidth());
		$this->assertSame($source->getGridHeight(), $clone->getGridHeight());
		$this->assertSame($source->getIsCompulsory(), $clone->getIsCompulsory());
		$this->assertSame($source->getIsVisible(), $clone->getIsVisible());
		$this->assertSame($source->getStyleConfig(), $clone->getStyleConfig());
		$this->assertSame($source->getCustomTitle(), $clone->getCustomTitle());
		$this->assertSame($source->getCustomIcon(), $clone->getCustomIcon());
		$this->assertSame($source->getShowTitle(), $clone->getShowTitle());
		$this->assertSame($source->getSortOrder(), $clone->getSortOrder());
		$this->assertSame($source->getTileType(), $clone->getTileType());
		$this->assertSame($source->getTileTitle(), $clone->getTileTitle());
		$this->assertSame($source->getTileIcon(), $clone->getTileIcon());
		$this->assertSame($source->getTileIconType(), $clone->getTileIconType());
		$this->assertSame($source->getTileBackgroundColor(), $clone->getTileBackgroundColor());
		$this->assertSame($source->getTileTextColor(), $clone->getTileTextColor());
		$this->assertSame($source->getTileLinkType(), $clone->getTileLinkType());
		$this->assertSame($source->getTileLinkValue(), $clone->getTileLinkValue());
		$this->assertSame($source->getContent(), $clone->getContent());
		$this->assertNotEmpty($clone->getCreatedAt());
		$this->assertNotEmpty($clone->getUpdatedAt());
	}//end testCloneIsByteForByteWithTargetDashboardId()
}//end class
