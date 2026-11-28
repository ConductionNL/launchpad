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
use Psr\Log\LoggerInterface;

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
	/**
	 * A declaring widget carries its shape.
	 *
	 * @return void
	 */
	public function testADeclaringWidgetCarriesItsPlaceholder(): void {
		$data = (new WidgetFormatter())->format(widget: new WidgetFormatterTestPlaceholderWidget(placeholder: 'counter'), userId: 'alice');

		$this->assertSame(expected: 'counter', actual: $data['placeholder']);
	}

	/**
	 * A widget without getPlaceholder() carries no placeholder.
	 *
	 * @return void
	 */
	public function testASilentWidgetCarriesNoPlaceholder(): void {
		$widget = $this->createMock(originalClassName: IWidget::class);
		$this->base(widget: $widget);

		$data = (new WidgetFormatter())->format(widget: $widget, userId: 'alice');

		$this->assertArrayNotHasKey(key: 'placeholder', array: $data);
	}

	/**
	 * An invalid declaration is dropped with a debug log naming the widget.
	 *
	 * @return void
	 */
	public function testInvalidPlaceholderIsDropped(): void {
		$logger = $this->createMock(originalClassName: LoggerInterface::class);
		$logger->expects($this->once())
			->method('debug')
			->with(
				$this->stringContains(string: 'placeholder'),
				$this->callback(callback: fn (array $context): bool => $context['widget'] === 'declaring')
			);

		$data = (new WidgetFormatter(logger: $logger))->format(widget: new WidgetFormatterTestPlaceholderWidget(placeholder: 'spinner'), userId: 'alice');

		$this->assertArrayNotHasKey(key: 'placeholder', array: $data);
	}

	/**
	 * A shape with a count is carried as an array.
	 *
	 * @return void
	 */
	public function testAShapeWithACountIsCarried(): void {
		$widget = new WidgetFormatterTestPlaceholderWidget(placeholder: ['shape' => 'rows', 'count' => 5]);

		$data = (new WidgetFormatter())->format(widget: $widget, userId: 'alice');

		$this->assertSame(expected: ['shape' => 'rows', 'count' => 5], actual: $data['placeholder']);
	}

	/**
	 * Invalid shapes and counts are dropped.
	 *
	 * @param mixed $declared The declared value.
	 *
	 * @return void
	 *
	 * @dataProvider invalidDeclarations
	 */
	public function testInvalidShapesAndCountsAreDropped(mixed $declared): void {
		$data = (new WidgetFormatter())->format(widget: new WidgetFormatterTestPlaceholderWidget(placeholder: $declared), userId: 'alice');

		$this->assertArrayNotHasKey(key: 'placeholder', array: $data);
	}

	/**
	 * Declarations the formatter must drop.
	 *
	 * @return array<string, array{0: mixed}>
	 */
	public static function invalidDeclarations(): array {
		return [
			'unknown shape' => ['spinner'],
			'empty string' => [''],
			'count zero' => [['shape' => 'rows', 'count' => 0]],
			'count above 24' => [['shape' => 'rows', 'count' => 25]],
			'count as text' => [['shape' => 'rows', 'count' => '5']],
			'no shape' => [['count' => 3]],
			'unknown shape in array' => [['shape' => 'grid']],
			'a number' => [3],
			'null' => [null],
		];
	}

	/**
	 * A declaration that throws is dropped.
	 *
	 * @return void
	 */
	public function testAThrowingDeclarationIsDropped(): void {
		$widget = new WidgetFormatterTestPlaceholderWidget(placeholder: new \RuntimeException(message: 'boom'));

		$data = (new WidgetFormatter())->format(widget: $widget, userId: 'alice');

		$this->assertArrayNotHasKey(key: 'placeholder', array: $data);
	}

	/**
	 * Every one of the six shapes is accepted.
	 *
	 * @return void
	 */
	public function testEveryShapeIsAccepted(): void {
		foreach (WidgetFormatter::PLACEHOLDER_SHAPES as $shape) {
			$data = (new WidgetFormatter())->format(widget: new WidgetFormatterTestPlaceholderWidget(placeholder: $shape), userId: 'alice');
			$this->assertSame(expected: $shape, actual: $data['placeholder']);
		}
	}
}

/**
 * A widget that serves both item APIs.
 */
interface WidgetFormatterTestDualApiWidget extends IAPIWidget, IAPIWidgetV2 {
}

/**
 * A widget that declares a placeholder (or throws when asked).
 */
class WidgetFormatterTestPlaceholderWidget implements IWidget {
	/**
	 * Constructor.
	 *
	 * @param mixed $placeholder The value getPlaceholder() returns, or a Throwable it throws.
	 */
	public function __construct(
		private readonly mixed $placeholder,
	) {
	}

	/**
	 * The widget id.
	 *
	 * @return string
	 */
	public function getId(): string {
		return 'declaring';
	}

	/**
	 * The title.
	 *
	 * @return string
	 */
	public function getTitle(): string {
		return 'Declaring';
	}

	/**
	 * The order.
	 *
	 * @return integer
	 */
	public function getOrder(): int {
		return 1;
	}

	/**
	 * No icon class.
	 *
	 * @return string
	 */
	public function getIconClass(): string {
		return '';
	}

	/**
	 * No URL.
	 *
	 * @return string|null
	 */
	public function getUrl(): ?string {
		return null;
	}

	/**
	 * Nothing to load.
	 *
	 * @return void
	 */
	public function load(): void {
	}

	/**
	 * The declared placeholder.
	 *
	 * @return mixed
	 */
	public function getPlaceholder(): mixed {
		if ($this->placeholder instanceof \Throwable) {
			throw $this->placeholder;
		}

		return $this->placeholder;
	}
}
