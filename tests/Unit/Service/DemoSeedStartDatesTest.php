<?php

/**
 * The demo seed's start dates (REQ-PEX-005, REQ-PPL-014): a start date
 * field is added when none is marked, an existing one is used as it is, and
 * each person's date is counted from the load day.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\DemoSeedStartDates;
use OCA\LaunchPad\Service\ProfileFieldService;
use PHPUnit\Framework\TestCase;

/**
 * Yasmina starts next week, Thomas started last week.
 */
class DemoSeedStartDatesTest extends TestCase {

	/** @var array<int, array<string, mixed>>|null The definitions saved. */
	private ?array $saved = null;

	/** @var array<string, array<string, string>> The values saved, by person. */
	private array $values = [];

	/**
	 * The part over a field service holding the given definitions.
	 *
	 * @param array<int, array<string, mixed>> $definitions The existing definitions.
	 *
	 * @return DemoSeedStartDates The part.
	 */
	private function startDates(array $definitions): DemoSeedStartDates {
		$fields = $this->createMock(originalClassName: ProfileFieldService::class);
		$fields->method('getDefinitions')->willReturn($definitions);
		$fields->method('saveDefinitions')->willReturnCallback(
			function (array $raw): array {
				$this->saved = $raw;
				return $raw;
			}
		);
		$fields->method('saveOwnValues')->willReturnCallback(
			function (string $userId, array $values): array {
				$this->values[$userId] = $values;
				return [];
			}
		);

		return new DemoSeedStartDates(fields: $fields);
	}//end startDates()

	/**
	 * Without a start date field the seed's field is added beside the others.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-a-profile-field-can-hold-a-start-date-req-pex-005
	 */
	public function testTheFieldIsAddedAndTheDatesCountFromToday(): void {
		$report = $this->startDates(definitions: [['key' => 'office', 'type' => 'text']])->seed(
			definition: ['field' => ['key' => 'in_dienst', 'label' => 'In dienst sinds'], 'days' => ['yasmina' => 7, 'thomas' => -7]],
			today: '2026-10-05'
		);

		$this->assertSame(expected: ['field' => 'in_dienst', 'people' => ['yasmina' => '2026-10-12', 'thomas' => '2026-09-28']], actual: $report);
		$this->assertSame(expected: ['office', 'in_dienst'], actual: array_column(array: (array)$this->saved, column_key: 'key'));
		$this->assertSame(expected: ['type' => 'date', 'source' => 'self', 'startDate' => true], actual: array_intersect_key($this->saved[1], ['type' => 1, 'startDate' => 1, 'source' => 1]));
		$this->assertSame(expected: ['in_dienst' => '2026-10-12'], actual: $this->values['yasmina']);
	}//end testTheFieldIsAddedAndTheDatesCountFromToday()

	/**
	 * A field the administrator already marked as the start date is used, untouched.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-a-profile-field-can-hold-a-start-date-req-pex-005
	 */
	public function testAnExistingStartDateFieldIsKept(): void {
		$report = $this->startDates(definitions: [['key' => 'joined', 'type' => 'date', 'startDate' => true]])->seed(
			definition: ['field' => ['key' => 'in_dienst', 'label' => 'In dienst sinds'], 'days' => ['yasmina' => 7]],
			today: '2026-10-05'
		);

		$this->assertSame(expected: 'joined', actual: $report['field']);
		$this->assertNull(actual: $this->saved);
		$this->assertSame(expected: ['joined' => '2026-10-12'], actual: $this->values['yasmina']);
	}//end testAnExistingStartDateFieldIsKept()

	/**
	 * A seed without start dates changes nothing.
	 *
	 * @return void
	 */
	public function testNoStartDatesNoChange(): void {
		$this->assertSame(expected: ['field' => '', 'people' => []], actual: $this->startDates(definitions: [])->seed(definition: [], today: '2026-10-05'));
		$this->assertNull(actual: $this->saved);
		$this->assertSame(expected: [], actual: $this->values);
	}//end testNoStartDatesNoChange()
}//end class
