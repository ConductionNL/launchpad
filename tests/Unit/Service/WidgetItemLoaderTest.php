<?php

/**
 * Unit tests for WidgetItemLoader: V2, V1 and item-less widgets.
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

use OCA\LaunchPad\Service\WidgetItemLoader;
use OCP\Dashboard\IAPIWidget;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IWidget;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\WidgetItemLoader
 */
class WidgetItemLoaderTest extends TestCase {
	private function item(string $title): WidgetItem {
		return new WidgetItem($title, 'sub', '/link/' . $title, '/icon.svg', '2026');
	}

	public function testV2WidgetReturnsSerializedItemsAndMessages(): void {
		$item = $this->item('a');
		$widget = $this->createMock(IAPIWidgetV2::class);
		$widget->expects($this->once())
			->method('getItemsV2')
			->with('alice', null, 3)
			->willReturn(new WidgetItems([$item], 'Nothing here', 'Almost empty'));

		$result = (new WidgetItemLoader())->loadItems(
			widgets: ['w2' => $widget],
			userId: 'alice',
			widgetIds: ['w2'],
			limit: 3
		);

		$this->assertSame([
			'w2' => [
				'items' => [$item->jsonSerialize()],
				'emptyContentMessage' => 'Nothing here',
				'halfEmptyContentMessage' => 'Almost empty',
			],
		], $result);
	}

	public function testV2IsPreferredWhenTheWidgetImplementsBoth(): void {
		$widget = $this->createMock(WidgetItemLoaderTestDualApiWidget::class);
		$widget->expects($this->never())->method('getItems');
		$widget->expects($this->once())->method('getItemsV2')->willReturn(new WidgetItems());

		$result = (new WidgetItemLoader())->loadItems(widgets: ['w' => $widget], userId: 'alice', widgetIds: ['w']);

		$this->assertSame(['items' => [], 'emptyContentMessage' => '', 'halfEmptyContentMessage' => ''], $result['w']);
	}

	public function testV1WidgetReturnsSerializedItemsWithEmptyMessages(): void {
		$a = $this->item('a');
		$b = $this->item('b');
		$widget = $this->createMock(IAPIWidget::class);
		$widget->expects($this->once())
			->method('getItems')
			->with('alice', null, 7)
			->willReturn([$a, $b]);

		$result = (new WidgetItemLoader())->loadItems(widgets: ['w1' => $widget], userId: 'alice', widgetIds: ['w1']);

		$this->assertSame([
			'items' => [$a->jsonSerialize(), $b->jsonSerialize()],
			'emptyContentMessage' => '',
			'halfEmptyContentMessage' => '',
		], $result['w1']);
	}

	public function testWidgetWithoutItemApiGetsAnEmptyEntry(): void {
		$widget = $this->createMock(IWidget::class);

		$result = (new WidgetItemLoader())->loadItems(widgets: ['plain' => $widget], userId: 'alice', widgetIds: ['plain']);

		$this->assertSame(['plain' => ['items' => [], 'emptyContentMessage' => '', 'halfEmptyContentMessage' => '']], $result);
	}

	public function testUnknownIdsAreSkippedAndOnlyRequestedWidgetsLoad(): void {
		$requested = $this->createMock(IWidget::class);
		$other = $this->createMock(IAPIWidget::class);
		$other->expects($this->never())->method('getItems');

		$result = (new WidgetItemLoader())->loadItems(
			widgets: ['req' => $requested, 'other' => $other],
			userId: 'alice',
			widgetIds: ['missing', 'req']
		);

		$this->assertSame(['req'], array_keys($result));
	}
}

/**
 * A widget that serves both item APIs.
 */
interface WidgetItemLoaderTestDualApiWidget extends IAPIWidget, IAPIWidgetV2 {
}
