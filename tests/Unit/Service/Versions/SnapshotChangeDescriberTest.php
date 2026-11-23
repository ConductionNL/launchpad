<?php

/**
 * SnapshotChangeDescriber Test
 *
 * Unit tests for the "What changed" description of automatic dashboard
 * version snapshots (REQ-VERS-001).
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service\Versions
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service\Versions;

use OCA\LaunchPad\Service\Versions\SnapshotChangeDescriber;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the snapshot change describer.
 */
class SnapshotChangeDescriberTest extends TestCase {

	/**
	 * Describer under test, without translations.
	 *
	 * @var SnapshotChangeDescriber
	 */
	private SnapshotChangeDescriber $describer;

	/**
	 * Set up the describer.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->describer = new SnapshotChangeDescriber();
	}//end setUp()

	/**
	 * Encode a snapshot in the shape DashboardVersionService builds.
	 *
	 * @param array<int, array<string, mixed>> $placements The placements.
	 * @param array<string, mixed>             $dashboard  Dashboard fields over the defaults.
	 *
	 * @return string The snapshot JSON.
	 */
	private function snapshot(array $placements, array $dashboard = []): string {
		return (string)json_encode(
			[
				'dashboard' => $dashboard + ['name' => 'Home', 'gridColumns' => 12],
				'placements' => $placements,
			]
		);
	}//end snapshot()

	/**
	 * A placement fixture.
	 *
	 * @param int                  $id     The placement id.
	 * @param array<string, mixed> $fields Fields over the defaults.
	 *
	 * @return array<string, mixed> The placement.
	 */
	private function placement(int $id, array $fields = []): array {
		return $fields + [
			'id' => $id,
			'widgetId' => 'widget-'.$id,
			'gridX' => 0,
			'gridY' => 0,
			'gridWidth' => 4,
			'gridHeight' => 4,
			'customTitle' => null,
			'content' => [],
		];
	}//end placement()

	/**
	 * Without a previous snapshot there is nothing to compare.
	 *
	 * @return void
	 */
	public function testNoPreviousSnapshotGivesNull(): void {
		$this->assertNull(actual: $this->describer->describe(previousJson: null, currentJson: $this->snapshot(placements: [])));
	}//end testNoPreviousSnapshotGivesNull()

	/**
	 * Identical snapshots give no description.
	 *
	 * @return void
	 */
	public function testIdenticalSnapshotsGiveNull(): void {
		$json = $this->snapshot(placements: [$this->placement(id: 1)]);

		$this->assertNull(actual: $this->describer->describe(previousJson: $json, currentJson: $json));
	}//end testIdenticalSnapshotsGiveNull()

	/**
	 * Undecodable snapshots give no description.
	 *
	 * @return void
	 */
	public function testInvalidJsonGivesNull(): void {
		$this->assertNull(actual: $this->describer->describe(previousJson: 'not json', currentJson: $this->snapshot(placements: [])));
	}//end testInvalidJsonGivesNull()

	/**
	 * Single changes, each named by the placement's display name.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}> Previous, current, expected.
	 */
	public static function singleChangeProvider(): array {
		$test = new self(name: 'provider');
		$base = $test->placement(id: 1, fields: ['content' => ['title' => 'Inbox']]);

		return [
			'added' => [
				$test->snapshot(placements: []),
				$test->snapshot(placements: [$base]),
				'Widget Inbox added',
			],
			'removed' => [
				$test->snapshot(placements: [$base]),
				$test->snapshot(placements: []),
				'Widget Inbox removed',
			],
			'moved' => [
				$test->snapshot(placements: [$base]),
				$test->snapshot(placements: [['gridY' => 3] + $base]),
				'Widget Inbox moved',
			],
			'resized' => [
				$test->snapshot(placements: [$base]),
				$test->snapshot(placements: [['gridWidth' => 8] + $base]),
				'Widget Inbox resized',
			],
			'renamed' => [
				$test->snapshot(placements: [$base]),
				$test->snapshot(placements: [['customTitle' => 'Mail'] + $base]),
				'Widget Inbox renamed to Mail',
			],
			'columns' => [
				$test->snapshot(placements: [$base]),
				$test->snapshot(placements: [$base], dashboard: ['gridColumns' => 6]),
				'Columns from 12 to 6',
			],
			'dashboard renamed' => [
				$test->snapshot(placements: [$base]),
				$test->snapshot(placements: [$base], dashboard: ['name' => 'Work']),
				'Dashboard renamed to Work',
			],
		];
	}//end singleChangeProvider()

	/**
	 * Each kind of change is described on its own.
	 *
	 * @param string $previous The older snapshot.
	 * @param string $current  The newer snapshot.
	 * @param string $expected The expected description.
	 *
	 * @return void
	 *
	 * @dataProvider singleChangeProvider
	 */
	public function testSingleChangeIsDescribed(string $previous, string $current, string $expected): void {
		$this->assertSame(expected: $expected, actual: $this->describer->describe(previousJson: $previous, currentJson: $current));
	}//end testSingleChangeIsDescribed()

	/**
	 * The name falls back from custom title to content title to widget id.
	 *
	 * @return void
	 */
	public function testNameFallsBackToTheWidgetId(): void {
		$previous = $this->snapshot(placements: []);
		$current  = $this->snapshot(placements: [$this->placement(id: 7, fields: ['widgetId' => 'calendar'])]);

		$this->assertSame(expected: 'Widget calendar added', actual: $this->describer->describe(previousJson: $previous, currentJson: $current));
	}//end testNameFallsBackToTheWidgetId()

	/**
	 * Several changes name the first and count the rest.
	 *
	 * @return void
	 */
	public function testSeveralChangesNameTheFirstAndCountTheRest(): void {
		$previous = $this->snapshot(placements: [$this->placement(id: 1), $this->placement(id: 2)]);
		$current  = $this->snapshot(
			placements: [$this->placement(id: 1, fields: ['gridX' => 4]), $this->placement(id: 3)],
			dashboard: ['gridColumns' => 8]
		);

		$this->assertSame(
			'Columns from 12 to 8 and 3 more changes',
			$this->describer->describe(previousJson: $previous, currentJson: $current)
		);
	}//end testSeveralChangesNameTheFirstAndCountTheRest()

	/**
	 * One extra change uses the singular.
	 *
	 * @return void
	 */
	public function testOneMoreChangeIsSingular(): void {
		$previous = $this->snapshot(placements: [$this->placement(id: 1)]);
		$current  = $this->snapshot(placements: [$this->placement(id: 1, fields: ['gridX' => 4, 'gridWidth' => 2])]);

		$this->assertSame(
			'Widget widget-1 moved and 1 more change',
			$this->describer->describe(previousJson: $previous, currentJson: $current)
		);
	}//end testOneMoreChangeIsSingular()

	/**
	 * A long description is cut to the maximum length with an ellipsis.
	 *
	 * @return void
	 */
	public function testLongDescriptionIsShortened(): void {
		$previous = $this->snapshot(placements: []);
		$current  = $this->snapshot(placements: [$this->placement(id: 1, fields: ['customTitle' => str_repeat('x', 200)])]);

		$note = (string)$this->describer->describe(previousJson: $previous, currentJson: $current);

		$this->assertSame(expected: SnapshotChangeDescriber::MAX_LENGTH, actual: mb_strlen($note));
		$this->assertStringEndsWith('…', $note);
	}//end testLongDescriptionIsShortened()

	/**
	 * With translations the source strings go through IL10N.
	 *
	 * @return void
	 */
	public function testDescriptionsAreTranslated(): void {
		$l10n = $this->createMock(IL10N::class);
		$l10n->expects($this->once())
			->method('t')
			->with('Widget %s added', ['Inbox'])
			->willReturn('Widget Inbox toegevoegd');

		$describer = new SnapshotChangeDescriber(l10n: $l10n);
		$current   = $this->snapshot(placements: [$this->placement(id: 1, fields: ['customTitle' => 'Inbox'])]);

		$this->assertSame(
			'Widget Inbox toegevoegd',
			$describer->describe(previousJson: $this->snapshot(placements: []), currentJson: $current)
		);
	}//end testDescriptionsAreTranslated()
}//end class
