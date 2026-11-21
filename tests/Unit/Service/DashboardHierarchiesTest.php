<?php

/**
 * DashboardHierarchiesTest
 *
 * The hierarchy descriptors a dashboard stores are normalised, and every
 * malformed one is refused with a message naming the problem (REQ-CWF-011).
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-011-a-dashboard-stores-its-hierarchies
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Service\DashboardHierarchies;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DashboardHierarchies.
 *
 * @covers \OCA\LaunchPad\Service\DashboardHierarchies
 */
class DashboardHierarchiesTest extends TestCase {

	/**
	 * The nl-administrative preset as the settings send it.
	 *
	 * @return array<string, mixed>
	 */
	private static function nlAdministrative(): array {
		return [
			'id'     => 'nl-administrative',
			'levels' => [
				['dimension' => 'provincieCode', 'label' => 'Province'],
				['dimension' => 'gemeenteCode', 'label' => 'Municipality', 'parentDimension' => 'provincieCode'],
				['dimension' => 'wijkCode', 'label' => ' District ', 'parentDimension' => 'gemeenteCode'],
			],
		];
	}//end nlAdministrative()

	/**
	 * A valid descriptor is kept, its labels trimmed and every parent set to the level above.
	 *
	 * @return void
	 */
	public function testAValidDescriptorIsNormalised(): void {
		$this->assertSame(
			expected: [
				[
					'id'     => 'nl-administrative',
					'levels' => [
						['dimension' => 'provincieCode', 'label' => 'Province', 'parentDimension' => null],
						['dimension' => 'gemeenteCode', 'label' => 'Municipality', 'parentDimension' => 'provincieCode'],
						['dimension' => 'wijkCode', 'label' => 'District', 'parentDimension' => 'gemeenteCode'],
					],
				],
			],
			actual: DashboardHierarchies::validated(value: [self::nlAdministrative()])
		);
	}//end testAValidDescriptorIsNormalised()

	/**
	 * Null and the empty list both mean no hierarchies.
	 *
	 * @return void
	 */
	public function testNullAndTheEmptyListMeanNone(): void {
		$this->assertSame(expected: [], actual: DashboardHierarchies::validated(value: null));
		$this->assertSame(expected: [], actual: DashboardHierarchies::validated(value: []));
	}//end testNullAndTheEmptyListMeanNone()

	/**
	 * Every malformed value, with the message it is refused with.
	 *
	 * @return array<string, array{0: mixed, 1: string}>
	 */
	public static function malformed(): array {
		$level   = ['dimension' => 'gemeenteCode'];
		$one     = static fn (array $levels, string $hierarchyId='x'): array => ['id' => $hierarchyId, 'levels' => $levels];
		$aToB    = [['dimension' => 'a'], ['dimension' => 'b', 'parentDimension' => 'c']];
		$nine    = array_map(static fn (int $i): array => ['dimension' => 'd'.$i], range(1, 9));
		$nineAll = array_map(static fn (int $i): array => $one([['dimension' => 'd'.$i]], 'h'.$i), range(1, 9));
		return [
			'a string'              => ['nl-administrative', 'hierarchies must be a list'],
			'an object'             => [['nl' => self::nlAdministrative()], 'hierarchies must be a list'],
			'an entry no object'    => [['nl-administrative'], 'hierarchies[0] must be an object'],
			'no id'                 => [[['levels' => [$level]]], 'hierarchies[0].id must be lowercase letters, digits and dashes'],
			'a bad id'              => [[$one([$level], 'NL admin')], 'hierarchies[0].id must be lowercase'],
			'no levels'             => [[['id' => 'x']], 'hierarchies[0].levels must be a non-empty list'],
			'empty levels'          => [[$one([])], 'hierarchies[0].levels must be a non-empty list'],
			'a level no object'     => [[$one(['gemeenteCode'])], 'hierarchies[0].levels[0] must be an object'],
			'no dimension'          => [[$one([['label' => 'Gemeente']])], 'hierarchies[0].levels[0].dimension must be a field name'],
			'a bad dimension'       => [[$one([['dimension' => '1; DROP']])], 'hierarchies[0].levels[0].dimension must be a field name'],
			'a label no text'       => [[$one([['dimension' => 'a', 'label' => 5]])], 'hierarchies[0].levels[0].label must be text'],
			'a long label'          => [[$one([['dimension' => 'a', 'label' => str_repeat('a', 129)]])], 'at most 128 characters'],
			'a wrong parent'        => [[$one($aToB)], 'hierarchies[0].levels[1].parentDimension must be the dimension of the level above'],
			'a parent on the first' => [[$one([['dimension' => 'a', 'parentDimension' => 'z']])], 'hierarchies[0].levels[0].parentDimension'],
			'a repeated dimension'  => [[$one([['dimension' => 'a'], ['dimension' => 'a']])], 'hierarchies[0] repeats dimension a'],
			'a shared dimension'    => [[$one([['dimension' => 'a']]), $one([['dimension' => 'a']], 'y')], 'hierarchies[1] repeats dimension a'],
			'a repeated id'         => [[$one([['dimension' => 'a']]), $one([['dimension' => 'b']])], 'hierarchies[1].id x is used twice'],
			'too many levels'       => [[$one($nine)], 'at most 8 levels'],
			'too many hierarchies'  => [$nineAll, 'at most 8 entries'],
		];
	}//end malformed()

	/**
	 * A malformed value is refused with a message naming the problem.
	 *
	 * @param mixed  $value   The value.
	 * @param string $message A part of the refusal message.
	 *
	 * @return void
	 */
	#[DataProvider('malformed')]
	public function testAMalformedValueIsRefused(mixed $value, string $message): void {
		$this->expectException(exception: InvalidArgumentException::class);
		$this->expectExceptionMessage(message: $message);
		DashboardHierarchies::validated(value: $value);
	}//end testAMalformedValueIsRefused()
}//end class
