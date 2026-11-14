<?php

/**
 * MapWidgetValidatorTest
 *
 * Save-time validation of `map` widget content (map-support): malformed
 * shapes are refused, and every shape CnMapWidget draws is accepted,
 * including a map of layers only and one fed by a url.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction b.v.
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Service\MapWidgetValidator;
use OCA\LaunchPad\Service\MenuService;
use OCA\LaunchPad\Service\PlacementService;
use OCA\LaunchPad\Service\WidgetFormatter;
use OCA\LaunchPad\Service\WidgetItemLoader;
use OCA\LaunchPad\Service\WidgetPlatform;
use OCA\LaunchPad\Service\WidgetRendering;
use OCA\LaunchPad\Service\WidgetService;
use OCP\Dashboard\IManager;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for {@see MapWidgetValidator} and its wiring into {@see WidgetService}.
 *
 * @spec openspec/changes/map-support/tasks.md
 */
#[CoversClass(MapWidgetValidator::class)]
#[CoversClass(WidgetService::class)]
#[UsesClass(MenuService::class)]
#[UsesClass(WidgetPlatform::class)]
#[UsesClass(WidgetRendering::class)]
class MapWidgetValidatorTest extends TestCase {

	private MapWidgetValidator $validator;

	/**
	 * Build the validator.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->validator = new MapWidgetValidator();
	}//end setUp()

	/**
	 * The validator applies to `map` and its `map-viewer` alias only.
	 *
	 * @return void
	 */
	public function testAppliesToMapTypesOnly(): void {
		$this->assertTrue($this->validator->appliesTo(widgetType: 'map'));
		$this->assertTrue($this->validator->appliesTo(widgetType: 'map-viewer'));
		$this->assertFalse($this->validator->appliesTo(widgetType: 'menu'));
		$this->assertFalse($this->validator->appliesTo(widgetType: 'Map'));
	}//end testAppliesToMapTypesOnly()

	/**
	 * Shapes CnMapWidget draws.
	 *
	 * @return array<string, array{0: array}>
	 */
	public static function validContent(): array {
		return [
			'empty'                   => [[]],
			'the form blob'           => [[
				'register' => 'meldingen',
				'schema'   => 'melding',
				'center'   => [52.13, 5.29],
				'zoom'     => 7,
				'markers'  => ['dataSource' => ['register' => 'meldingen', 'schema' => 'melding'], 'popupField' => 'title'],
			]],
			'a url source'            => [['markers' => ['dataSource' => ['url' => '/api/cases/geo'], 'latField' => 'lat']]],
			'layers only'             => [['layers' => [['type' => 'tile', 'url' => 'https://example.org/{z}/{x}/{y}.png'], ['type' => 'wms']]]],
			'inline features'         => [['markers' => ['features' => []]]],
			'centre as numeric text'  => [['center' => ['52.1', '5.2']]],
		];
	}//end validContent()

	/**
	 * Every drawable shape is accepted.
	 *
	 * @param array $content The content blob.
	 *
	 * @return void
	 */
	#[DataProvider('validContent')]
	public function testDrawableContentIsAccepted(array $content): void {
		$this->validator->validate(content: $content);
		$this->addToAssertionCount(1);
	}//end testDrawableContentIsAccepted()

	/**
	 * Malformed shapes and the refusal each gets.
	 *
	 * @return array<string, array{0: array, 1: string}>
	 */
	public static function malformedContent(): array {
		return [
			'register without schema'      => [['register' => 'meldingen'], 'Map needs both a register and a schema'],
			'markers as a list'            => [['markers' => [1, 2]], 'Map markers must be an object'],
			'markers as text'              => [['markers' => 'all'], 'Map markers must be an object'],
			'source as text'               => [['markers' => ['dataSource' => '/api']], 'Map markers.dataSource must be an object'],
			'source with schema only'      => [['markers' => ['dataSource' => ['schema' => 'melding']]], 'Map markers.dataSource needs both a register and a schema'],
			'source with nothing'          => [['markers' => ['dataSource' => []]], 'Map markers.dataSource must name either a url or a register and schema'],
			'source with both'             => [
				['markers' => ['dataSource' => ['url' => '/api', 'register' => 'r', 'schema' => 's']]],
				'Map markers.dataSource must name either a url or a register and schema',
			],
			'blank url'                    => [['markers' => ['dataSource' => ['url' => '  ']]], 'Map markers.dataSource must name either a url or a register and schema'],
			'layers as an object'          => [['layers' => ['type' => 'tile']], 'Map layers must be a list'],
			'a layer that is text'         => [['layers' => ['tile']], 'Each map layer must be an object'],
			'an unknown layer type'        => [['layers' => [['type' => 'vector']]], 'Map layer type must be one of: tile, wms, wfs, geojson, image'],
			'a layer without a type'       => [['layers' => [['url' => 'x']]], 'Map layer type must be one of: tile, wms, wfs, geojson, image'],
			'a centre of one number'       => [['center' => [52.1]], 'Map center must be [lat, lng]'],
			'a centre of words'            => [['center' => ['north', 'east']], 'Map center must be [lat, lng]'],
			'a centre as an object'        => [['center' => ['lat' => 52.1, 'lng' => 5.2]], 'Map center must be [lat, lng]'],
		];
	}//end malformedContent()

	/**
	 * Every malformed shape is refused with its own message.
	 *
	 * @param array  $content The content blob.
	 * @param string $message The expected refusal.
	 *
	 * @return void
	 */
	#[DataProvider('malformedContent')]
	public function testMalformedContentIsRefused(array $content, string $message): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage($message);
		$this->validator->validate(content: $content);
	}//end testMalformedContentIsRefused()

	/**
	 * Adding a map widget with a malformed source is refused before anything is stored.
	 *
	 * @return void
	 */
	public function testAddingAMalformedMapStoresNothing(): void {
		$placements = $this->createMock(PlacementService::class);
		$placements->expects($this->never())->method('addWidget');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Map markers.dataSource needs both a register and a schema');
		$this->service(placements: $placements)->addWidget(
			dashboardId: 4,
			widgetId: 'map',
			layout: ['content' => ['markers' => ['dataSource' => ['register' => 'meldingen']]]]
		);
	}//end testAddingAMalformedMapStoresNothing()

	/**
	 * A well-formed map is handed to the placement service unchanged.
	 *
	 * @return void
	 */
	public function testAddingAWellFormedMapStoresIt(): void {
		$layout    = ['gridX' => 0, 'content' => ['markers' => ['dataSource' => ['url' => '/api/geo']]]];
		$placement = new WidgetPlacement();
		$placements = $this->createMock(PlacementService::class);
		$placements->expects($this->once())
			->method('addWidget')
			->with(4, 'map', $layout)
			->willReturn($placement);

		$this->assertSame($placement, $this->service(placements: $placements)->addWidget(dashboardId: 4, widgetId: 'map', layout: $layout));
	}//end testAddingAWellFormedMapStoresIt()

	/**
	 * Updating a stored map placement with malformed layers is refused.
	 *
	 * @return void
	 */
	public function testUpdatingAMapWithMalformedLayersIsRefused(): void {
		$stored = new WidgetPlacement();
		$stored->setWidgetId('map-viewer');
		$placements = $this->createMock(PlacementService::class);
		$placements->method('getPlacement')->willReturn($stored);
		$placements->expects($this->never())->method('updatePlacement');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Map layers must be a list');
		$this->service(placements: $placements)->updatePlacement(placementId: 9, data: ['content' => ['layers' => 'osm']]);
	}//end testUpdatingAMapWithMalformedLayersIsRefused()

	/**
	 * A WidgetService over the given placement service, with the real validators.
	 *
	 * @param PlacementService $placements The placement service.
	 *
	 * @return WidgetService
	 */
	private function service(PlacementService $placements): WidgetService {
		return new WidgetService(
			platform: new WidgetPlatform(
				dashboardManager: $this->createMock(IManager::class),
				userSession: $this->createMock(IUserSession::class)
			),
			placementService: $placements,
			rendering: new WidgetRendering(
				widgetFormatter: $this->createMock(WidgetFormatter::class),
				widgetItemLoader: $this->createMock(WidgetItemLoader::class)
			),
			menuService: new MenuService(),
			mapValidator: new MapWidgetValidator()
		);
	}//end service()
}//end class
