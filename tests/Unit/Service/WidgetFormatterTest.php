<?php

/**
 * Unit tests for WidgetFormatter: the API shape built from each optional widget interface.
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

use OCA\LaunchPad\Service\WidgetFormatter;
use OCP\Dashboard\IAPIWidget;
use OCP\Dashboard\IAPIWidgetV2;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\IOptionWidget;
use OCP\Dashboard\IReloadableWidget;
use OCP\Dashboard\IWidget;
use OCP\Dashboard\Model\WidgetButton;
use OCP\Dashboard\Model\WidgetOptions;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Service\WidgetFormatter
 */
class WidgetFormatterTest extends TestCase {
	private function base(MockObject $widget): void {
		$widget->method('getId')->willReturn('notes');
		$widget->method('getTitle')->willReturn('Notes');
		$widget->method('getOrder')->willReturn(12);
		$widget->method('getIconClass')->willReturn('icon-notes');
		$widget->method('getUrl')->willReturn('/apps/notes');
	}

	public function testPlainWidgetGetsTheBaseShapeWithDefaults(): void {
		$widget = $this->createMock(IWidget::class);
		$this->base($widget);

		$data = (new WidgetFormatter())->format(widget: $widget, userId: 'alice');

		$this->assertSame([
			'id' => 'notes',
			'title' => 'Notes',
			'order' => 12,
			'iconClass' => 'icon-notes',
			'iconUrl' => null,
			'widgetUrl' => '/apps/notes',
			'itemIconsRound' => false,
			'itemApiVersions' => [],
			'reloadInterval' => 0,
			'buttons' => [],
		], $data);
	}

	public function testIconWidgetSetsTheIconUrl(): void {
		$widget = $this->createMock(IIconWidget::class);
		$this->base($widget);
		$widget->method('getIconUrl')->willReturn('/img/notes.svg');

		$data = (new WidgetFormatter())->format(widget: $widget, userId: 'alice');

		$this->assertSame('/img/notes.svg', $data['iconUrl']);
	}

	public function testApiVersionsListV1ThenV2(): void {
		$widget = $this->createMock(WidgetFormatterTestDualApiWidget::class);
		$this->base($widget);

		$data = (new WidgetFormatter())->format(widget: $widget, userId: 'alice');

		$this->assertSame([1, 2], $data['itemApiVersions']);
	}

	public function testOnlyV2WidgetListsVersionTwo(): void {
		$widget = $this->createMock(IAPIWidgetV2::class);
		$this->base($widget);

		$data = (new WidgetFormatter())->format(widget: $widget, userId: 'alice');

		$this->assertSame([2], $data['itemApiVersions']);
	}

	public function testButtonsAreAskedForTheUserAndFlattened(): void {
		$widget = $this->createMock(IButtonWidget::class);
		$this->base($widget);
		$widget->expects($this->once())
			->method('getWidgetButtons')
			->with('alice')
			->willReturn([
				new WidgetButton(WidgetButton::TYPE_NEW, '/apps/notes/new', 'New note'),
				new WidgetButton(WidgetButton::TYPE_MORE, '/apps/notes', 'More notes'),
			]);

		$data = (new WidgetFormatter())->format(widget: $widget, userId: 'alice');

		$this->assertSame([
			['type' => WidgetButton::TYPE_NEW, 'text' => 'New note', 'link' => '/apps/notes/new'],
			['type' => WidgetButton::TYPE_MORE, 'text' => 'More notes', 'link' => '/apps/notes'],
		], $data['buttons']);
	}

	public function testOptionsSetRoundItemIcons(): void {
		$widget = $this->createMock(IOptionWidget::class);
		$this->base($widget);
		$widget->method('getWidgetOptions')->willReturn(new WidgetOptions(true));

		$data = (new WidgetFormatter())->format(widget: $widget, userId: 'alice');

		$this->assertTrue($data['itemIconsRound']);
	}

	public function testReloadableWidgetSetsTheInterval(): void {
		$widget = $this->createMock(IReloadableWidget::class);
		$this->base($widget);
		$widget->method('getReloadInterval')->willReturn(60);

		$data = (new WidgetFormatter())->format(widget: $widget, userId: 'alice');

		$this->assertSame(60, $data['reloadInterval']);
	}
}

/**
 * A widget that serves both item APIs.
 */
interface WidgetFormatterTestDualApiWidget extends IAPIWidget, IAPIWidgetV2 {
}
