<?php

/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Db\AnnouncementMapper;
use OCA\LaunchPad\Service\CatalogueService;
use OCA\LaunchPad\Service\DemoSeedContent;
use OCA\LaunchPad\Service\ProfileFieldService;
use OCP\IAppConfig;
use PHPUnit\Framework\TestCase;

/**
 * The demo seed gives its new colleagues a start date (people-new-colleagues).
 *
 * @spec openspec/changes/archive/2026-11-25-people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
 */
class DemoSeedStartDatesTest extends TestCase {

	/**
	 * The definitions saved, and the values saved per person.
	 *
	 * @var array<string, mixed>
	 */
	private array $saved = ['definitions' => null, 'values' => []];

	/**
	 * The seed content over profile fields holding these definitions.
	 *
	 * @param array<int, array<string, mixed>> $definitions The current definitions.
	 *
	 * @return DemoSeedContent The content seeder.
	 */
	private function content(array $definitions): DemoSeedContent {
		$fields = $this->createMock(originalClassName: ProfileFieldService::class);
		$fields->method('getDefinitions')->willReturn($definitions);
		$fields->method('saveDefinitions')->willReturnCallback(function (array $raw): array {
			$this->saved['definitions'] = $raw;
			return $raw;
		});
		$fields->method('saveOwnValues')->willReturnCallback(function (string $userId, array $values): array {
			$this->saved['values'][$userId] = $values;
			return [];
		});

		return new DemoSeedContent(
			catalogue: $this->createMock(originalClassName: CatalogueService::class),
			announcements: $this->createMock(originalClassName: AnnouncementMapper::class),
			appConfig: $this->createMock(originalClassName: IAppConfig::class),
			profileFields: $fields
		);
	}//end content()

	/**
	 * Without a start-date field the seed adds one, then dates each person from today.
	 *
	 * @return void
	 */
	public function testTheSeedAddsTheFieldAndDatesFromToday(): void {
		$written = $this->content(definitions: [['key' => 'office', 'type' => 'text']])->seedStartDates(
			entry: ['field' => ['key' => 'start_date', 'label' => 'In dienst sinds'], 'offsets' => ['yasmina' => 5, 'thomas' => -7]],
			today: '2026-10-07'
		);

		$this->assertSame(expected: ['yasmina' => '2026-10-12', 'thomas' => '2026-09-30'], actual: $written);
		$this->assertSame(expected: ['start_date' => '2026-10-12'], actual: $this->saved['values']['yasmina']);
		$added = $this->saved['definitions'][1];
		$this->assertSame(expected: ['start_date', 'date', true], actual: [$added['key'], $added['type'], $added['startDate']]);
		$this->assertSame(expected: 'office', actual: $this->saved['definitions'][0]['key'], message: 'the fields already there stay');
	}//end testTheSeedAddsTheFieldAndDatesFromToday()

	/**
	 * A field already marked as the start date is used, and no field is added.
	 *
	 * @return void
	 */
	public function testTheMarkedFieldIsUsed(): void {
		$this->content(definitions: [['key' => 'joined', 'type' => 'date', 'startDate' => true]])->seedStartDates(
			entry: ['field' => ['key' => 'start_date'], 'offsets' => ['lotte' => -4]],
			today: '2026-10-05'
		);

		$this->assertNull(actual: $this->saved['definitions']);
		$this->assertSame(expected: ['joined' => '2026-10-01'], actual: $this->saved['values']['lotte']);
	}//end testTheMarkedFieldIsUsed()

	/**
	 * A seed without start dates writes nothing.
	 *
	 * @return void
	 */
	public function testNoOffsetsWriteNothing(): void {
		$this->assertSame(expected: [], actual: $this->content(definitions: [])->seedStartDates(entry: [], today: '2026-10-05'));
		$this->assertNull(actual: $this->saved['definitions']);
	}//end testNoOffsetsWriteNothing()
}//end class
