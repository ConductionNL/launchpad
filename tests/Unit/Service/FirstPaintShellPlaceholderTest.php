<?php

/**
 * FirstPaintShellPlaceholderTest
 *
 * The placeholder shape of every placement in the shared fixture, resolved by
 * the shell in the order of REQ-FPS-009 (manifest, registry, catalogue,
 * fallback), and the registry copy and fallback table that order relies on.
 * The same fixture runs through nextcloud-vue's resolver in Vitest.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/widget-declared-placeholders/tasks.md#task-3
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\FirstPaintShell;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the placeholder resolution of FirstPaintShell.
 *
 * @covers \OCA\LaunchPad\Service\FirstPaintShell
 */
class FirstPaintShellPlaceholderTest extends TestCase {

	/**
	 * Read a JSON file of the repository.
	 *
	 * @param string $path The path from the repository root.
	 *
	 * @return array<string, mixed>
	 */
	private static function json(string $path): array {
		return json_decode(json: (string) file_get_contents(filename: __DIR__.'/../../../'.$path), associative: true);
	}//end json()

	/**
	 * The fixture's cases.
	 *
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function cases(): array {
		$cases = [];
		foreach (self::json(path: 'tests/fixtures/widget-placeholders.json')['cases'] as $case) {
			$cases[$case['name']] = [$case];
		}

		return $cases;
	}//end cases()

	/**
	 * Every fixture placement gets its expected shape.
	 *
	 * @param array<string, mixed> $case The case.
	 *
	 * @return void
	 *
	 * @dataProvider cases
	 */
	public function testTheShellResolvesTheFixture(array $case): void {
		$kind = (new FirstPaintShell())->kindOf(
			widgetId: $case['widgetId'],
			entry: $case['catalogue'] ?? [],
			placement: $case['placement'] ?? []
		);

		$this->assertSame(expected: $case['expected'], actual: $kind);
	}//end testTheShellResolvesTheFixture()

	/**
	 * A frame takes its placement's manifest declaration.
	 *
	 * @return void
	 */
	public function testAFrameUsesThePlacementsDeclaration(): void {
		$model = (new FirstPaintShell())->build(
			page: [
				'dashboard'  => ['gridColumns' => 12],
				'placements' => [
					['id' => 1, 'widgetId' => 'chart', 'gridX' => 0, 'gridY' => 0, 'gridWidth' => 4, 'gridHeight' => 2, 'placeholder' => 'counter'],
					['id' => 2, 'widgetId' => 'chart', 'gridX' => 4, 'gridY' => 0, 'gridWidth' => 4, 'gridHeight' => 2],
				],
			],
			catalogue: []
		);

		$this->assertSame(expected: ['counter', 'block'], actual: array_column(array: $model['frames'], column_key: 'kind'));
	}//end testAFrameUsesThePlacementsDeclaration()

	/**
	 * The registry copy declares a valid shape for every type, and the
	 * fallback table names no type the registry declares.
	 *
	 * @return void
	 */
	public function testNoFallbackEntryShadowsADeclaration(): void {
		$registry = self::json(path: 'lib/Service/widget-placeholders.json');
		$table    = self::json(path: 'lib/Service/first-paint-kinds.json');

		$this->assertGreaterThan(expected: 80, actual: count(value: $registry));
		foreach ($registry as $type => $declared) {
			$this->assertContains(needle: $declared['shape'] ?? null, haystack: FirstPaintShell::KINDS, message: $type);
		}

		$this->assertSame(expected: [], actual: array_values(array: array_intersect(array_keys(array: $table['kinds']), array_keys(array: $registry))));
		$this->assertSame(expected: 'rows', actual: $table['default']);
	}//end testNoFallbackEntryShadowsADeclaration()
}//end class
