<?php

/**
 * FirstPaintShellTest
 *
 * The first-paint shell's view model (first-paint-skeleton D1, D2, D3, D5):
 * the two header modes, one frame per widget the grid draws (hidden and
 * refused widgets have none), titles as the card shows them, the placeholder
 * kind from the declaration, the fallback table or the default, the phone
 * rule, and the positions per column count.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md#requirement-the-server-renders-every-widget-frame-in-place-req-fps-002
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\FirstPaintShell;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FirstPaintShell.
 *
 * @covers \OCA\LaunchPad\Service\FirstPaintShell
 * @uses   \OCA\LaunchPad\Service\FirstPaintLayout
 */
class FirstPaintShellTest extends TestCase {

	/**
	 * The catalogue the page carries.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function catalogue(): array {
		return [
			['id' => 'files', 'title' => 'Files'],
			['id' => 'nc-talk', 'title' => 'Talk mentions', 'placeholder' => ['shape' => 'lines']],
			['id' => 'map', 'title' => 'Map', 'mobileReady' => false],
		];
	}//end catalogue()

	/**
	 * A placement.
	 *
	 * @param int                  $id       The id.
	 * @param string               $widgetId The type.
	 * @param array<string, mixed> $extra    More fields.
	 *
	 * @return array<string, mixed>
	 */
	private static function placement(int $id, string $widgetId, array $extra=[]): array {
		$base = ['id' => $id, 'widgetId' => $widgetId, 'gridX' => 0, 'gridY' => $id, 'gridWidth' => 6, 'gridHeight' => 2];

		return $extra + $base + ['isVisible' => 1, 'showTitle' => 1];
	}//end placement()

	/**
	 * A dashboard without a greeting row: its name and description head the page.
	 *
	 * @return void
	 */
	public function testThePageHeader(): void {
		$model = (new FirstPaintShell())->build(
			page: [
				'dashboard'  => ['name' => 'Intranet Zuiddrecht', 'description' => 'Groepsdashboard van Communicatie', 'gridColumns' => 12],
				'placements' => [self::placement(id: 1, widgetId: 'files')],
			],
			catalogue: self::catalogue()
		);

		$this->assertSame(
			expected: ['mode' => 'page', 'name' => 'Intranet Zuiddrecht', 'description' => 'Groepsdashboard van Communicatie', 'groundId' => ''],
			actual: $model['header']
		);
		$this->assertSame(expected: [12, 8, 4], actual: $model['columns']);
	}//end testThePageHeader()

	/**
	 * A header placement on the ground is the greeting row (LpVandaag).
	 *
	 * @return void
	 */
	public function testTheGreetingHeader(): void {
		$model = (new FirstPaintShell())->build(
			page: [
				'dashboard'  => ['name' => 'Vandaag', 'gridColumns' => 12],
				'placements' => [self::placement(id: 7, widgetId: 'header', extra: ['content' => ['ground' => true, 'greeting' => true]])],
			],
			catalogue: []
		);

		$this->assertSame(expected: 'greeting', actual: $model['header']['mode']);
		$this->assertSame(expected: '7', actual: $model['header']['groundId']);
		$this->assertSame(expected: 'heading', actual: $model['frames'][0]['kind']);
	}//end testTheGreetingHeader()

	/**
	 * Hidden by the personal layer or refused by the role filter: no frame.
	 * Titles as the card shows them; kinds from the declaration, the table
	 * or the default; the phone rule; positions per column count.
	 *
	 * @return void
	 */
	public function testFrames(): void {
		$model = (new FirstPaintShell())->build(
			page: [
				'dashboard'  => ['name' => 'Werk', 'gridColumns' => 8],
				'hidden'     => [9],
				'placements' => [
				self::placement(id: 1, widgetId: 'files'),
				self::placement(id: 2, widgetId: 'files', extra: ['customTitle' => ' Mijn bestanden ']),
				self::placement(id: 3, widgetId: 'files', extra: ['showTitle' => 0]),
				self::placement(id: 4, widgetId: 'nc-talk'),
				self::placement(id: 5, widgetId: 'unknown-type'),
				self::placement(id: 6, widgetId: 'map'),
				self::placement(id: 8, widgetId: 'chart', extra: ['isVisible' => 0]),
				self::placement(id: 9, widgetId: 'chart'),
				],
			],
			catalogue: self::catalogue()
		);

		$this->assertSame(expected: ['1', '2', '3', '4', '5', '6'], actual: array_column($model['frames'], 'id'));
		$this->assertSame(expected: ['Files', 'Mijn bestanden', '', 'Talk mentions', '', 'Map'], actual: array_column($model['frames'], 'title'));
		$this->assertSame(expected: ['rows', 'rows', 'rows', 'lines', 'rows', 'block'], actual: array_column($model['frames'], 'kind'));
		$this->assertSame(expected: [false, false, false, false, false, true], actual: array_column($model['frames'], 'phoneHidden'));
		$this->assertSame(expected: [8, 4], actual: $model['columns']);
		$this->assertSame(expected: ['x' => 0, 'y' => 2, 'w' => 6, 'h' => 2], actual: $model['frames'][1]['positions'][8]);
		$this->assertSame(expected: ['x' => 0, 'y' => 2, 'w' => 4, 'h' => 2], actual: $model['frames'][1]['positions'][4]);
	}//end testFrames()

	/**
	 * The kind of a type: a valid declaration wins, an invalid one falls back.
	 *
	 * @return void
	 */
	public function testKindOf(): void {
		$shell = new FirstPaintShell();

		$this->assertSame(expected: 'counter', actual: $shell->kindOf(widgetId: 'nc-revenue', entry: ['placeholder' => 'counter']));
		$this->assertSame(expected: 'rows', actual: $shell->kindOf(widgetId: 'nc-revenue', entry: ['placeholder' => ['shape' => 'spiral']]));
		$this->assertSame(expected: 'rows', actual: $shell->kindOf(widgetId: 'files', entry: ['placeholder' => 'counter']));
		$this->assertSame(expected: 'counter', actual: $shell->kindOf(widgetId: 'files', entry: [], placement: ['placeholder' => 'counter']));
		$this->assertSame(expected: 'tiles', actual: $shell->kindOf(widgetId: 'app-tiles'));
		$this->assertSame(expected: 'heading', actual: $shell->kindOf(widgetId: 'section-heading'));
		$this->assertSame(expected: 'rows', actual: $shell->kindOf(widgetId: 'never-heard-of'));
	}//end testKindOf()

	/**
	 * Every kind in the fallback table is a kind the shell can draw.
	 *
	 * @return void
	 */
	public function testTheFallbackTableHoldsOnlyKnownKinds(): void {
		$table = json_decode((string) file_get_contents(__DIR__.'/../../../lib/Service/first-paint-kinds.json'), true);

		$this->assertContains(needle: $table['default'], haystack: FirstPaintShell::KINDS);
		foreach ($table['kinds'] as $type => $kind) {
			$this->assertContains(needle: $kind, haystack: FirstPaintShell::KINDS, message: $type);
		}
	}//end testTheFallbackTableHoldsOnlyKnownKinds()
}//end class
