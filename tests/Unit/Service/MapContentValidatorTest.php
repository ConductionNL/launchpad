<?php

/**
 * MapContentValidator Test
 *
 * The `map` widget content checks on save: a half register source, a
 * spatial filter that does not read or has nothing to narrow, an unsafe
 * detail link and an empty geo property are refused; working maps pass.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/map-support/tasks.md#backend
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Service\MapContentValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see MapContentValidator}.
 */
class MapContentValidatorTest extends TestCase {

	/**
	 * A closed polygon around part of Utrecht.
	 */
	private const POLYGON = ['type' => 'Polygon', 'coordinates' => [[[5, 52], [6, 52], [6, 53], [5, 52]]]];

	/**
	 * A point, which is not an area.
	 */
	private const POINT = ['type' => 'Point', 'coordinates' => [5, 52]];

	/**
	 * A bbox and a radius at once.
	 */
	private const TWO_KINDS = ['bbox' => [5, 52, 6, 53], 'near' => [5, 52], 'radius' => 5];
	/**
	 * Maps that work pass.
	 *
	 * @return array<string, array{0: array}>
	 */
	public static function validMaps(): array {
		$source = ['register' => 'dossiq', 'schema' => 'case'];
		return [
			'layers only'     => [['layers' => [['type' => 'tile', 'url' => 'https://x/{z}/{x}/{y}.png']]]],
			'url markers'     => [['markers' => ['dataSource' => ['url' => '/apps/x/geo.json']]]],
			'register source' => [['markers' => ['geoProperty' => 'location', 'dataSource' => $source]]],
			'bbox'            => [['markers' => ['dataSource' => $source + ['spatialFilter' => ['bbox' => [5.1, 52.05, 5.15, 52.1]]]]]],
			'radius'          => [['markers' => ['dataSource' => $source + ['spatialFilter' => ['near' => [5.12, 52.09], 'radius' => 500]]]]],
			'polygon'         => [['markers' => ['dataSource' => $source + ['spatialFilter' => ['within' => self::POLYGON]]]]],
			'app path link'   => [['markers' => ['detailLink' => '/apps/dossiq/#/cases/{id}']]],
			'https link'      => [['markers' => ['detailLink' => 'https://example.nl/case/{id}']]],
		];
	}//end validMaps()

	/**
	 * Maps that would draw wrong or empty are refused.
	 *
	 * @return array<string, array{0: array}>
	 */
	public static function invalidMaps(): array {
		$source = ['register' => 'dossiq', 'schema' => 'case'];
		return [
			'register without schema'   => [['markers' => ['dataSource' => ['register' => 'dossiq']]]],
			'filter without a register' => [['markers' => ['dataSource' => ['url' => '/x', 'spatialFilter' => ['bbox' => [5, 52, 6, 53]]]]]],
			'three-number bbox'         => [['markers' => ['dataSource' => $source + ['spatialFilter' => ['bbox' => [5, 52, 6]]]]]],
			'bbox west of east swapped' => [['markers' => ['dataSource' => $source + ['spatialFilter' => ['bbox' => [6, 52, 5, 53]]]]]],
			'radius of zero'            => [['markers' => ['dataSource' => $source + ['spatialFilter' => ['near' => [5, 52], 'radius' => 0]]]]],
			'point as polygon'          => [['markers' => ['dataSource' => $source + ['spatialFilter' => ['within' => self::POINT]]]]],
			'two kinds at once'         => [['markers' => ['dataSource' => $source + ['spatialFilter' => self::TWO_KINDS]]]],
			'javascript link'           => [['markers' => ['detailLink' => 'javascript:alert(1)']]],
			'protocol-relative link'    => [['markers' => ['detailLink' => '//evil.example/x']]],
			'empty geo property'        => [['markers' => ['geoProperty' => '']]],
		];
	}//end invalidMaps()

	/**
	 * A working map passes.
	 *
	 * @param array $content The content.
	 *
	 * @return void
	 */
	#[DataProvider('validMaps')]
	public function testAWorkingMapPasses(array $content): void {
		(new MapContentValidator())->validate(content: $content);
		$this->addToAssertionCount(count: 1);
	}//end testAWorkingMapPasses()

	/**
	 * A map that would draw wrong is refused.
	 *
	 * @param array $content The content.
	 *
	 * @return void
	 */
	#[DataProvider('invalidMaps')]
	public function testAWrongMapIsRefused(array $content): void {
		$this->expectException(exception: InvalidArgumentException::class);
		(new MapContentValidator())->validate(content: $content);
	}//end testAWrongMapIsRefused()
}//end class
