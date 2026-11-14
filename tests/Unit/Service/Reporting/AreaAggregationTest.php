<?php

/**
 * AreaAggregation Test
 *
 * REQ-DWMS-009: counts and rates per area over a period, and the explicit
 * "no location" bucket, including the empty case.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service\Reporting
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace Unit\Service\Reporting;

use DateTimeImmutable;
use OCA\LaunchPad\Service\Reporting\AreaAggregation;
use PHPUnit\Framework\TestCase;

class AreaAggregationTest extends TestCase {

	public function testEachDistrictCarriesACountAndARateAndTheRestIsNoLocation(): void {
		$rows = [
			['district' => 'Noord', 'reportedAt' => '2026-07-02'],
			['district' => 'Noord', 'reportedAt' => '2026-08-15'],
			['district' => 'Zuid', 'reportedAt' => '2026-09-30T23:00:00+00:00'],
			['district' => '  ', 'reportedAt' => '2026-08-01'],
			['reportedAt' => '2026-08-02'],
			// Outside the quarter: not counted anywhere.
			['district' => 'Zuid', 'reportedAt' => '2026-10-01'],
			['district' => 'Oost', 'reportedAt' => '2026-06-30'],
		];

		$result = (new AreaAggregation())->aggregate(
			rows: $rows,
			areaProperty: 'district',
			dateProperty: 'reportedAt',
			from: new DateTimeImmutable('2026-07-01T00:00:00+00:00'),
			until: new DateTimeImmutable('2026-10-01T00:00:00+00:00')
		);

		$this->assertSame(5, $result['total']);
		$this->assertSame(
			[
				['area' => 'Noord', 'count' => 2, 'rate' => 0.4],
				['area' => 'Zuid', 'count' => 1, 'rate' => 0.2],
			],
			$result['areas']
		);
		// A blank district and a missing one both land in the bucket, never dropped.
		$this->assertSame(['count' => 2, 'rate' => 0.4], $result['noLocation']);
	}//end testEachDistrictCarriesACountAndARateAndTheRestIsNoLocation()

	public function testNoRowsGiveAnEmptyBucketAndNoDivisionByZero(): void {
		$result = (new AreaAggregation())->aggregate(rows: [], areaProperty: 'district');

		$this->assertSame(['total' => 0, 'areas' => [], 'noLocation' => ['count' => 0, 'rate' => 0.0]], $result);
	}//end testNoRowsGiveAnEmptyBucketAndNoDivisionByZero()

	public function testOnlyObjectsWithoutALocationAllLandInTheBucket(): void {
		$result = (new AreaAggregation())->aggregate(
			rows: [['address' => ['street' => 'Dam']], ['address' => null], ['address' => ['district' => ['nested']]]],
			areaProperty: 'address.district'
		);

		$this->assertSame(3, $result['total']);
		$this->assertSame([], $result['areas']);
		$this->assertSame(['count' => 3, 'rate' => 1.0], $result['noLocation']);
	}//end testOnlyObjectsWithoutALocationAllLandInTheBucket()

	public function testANestedAreaIsReadAndAnUndatedRowFallsOutsideABoundedPeriod(): void {
		$result = (new AreaAggregation())->aggregate(
			rows: [
				['address' => ['district' => 'West'], 'at' => '2026-09-01'],
				['address' => ['district' => 'West'], 'at' => 'not a date'],
				['address' => ['district' => 'West']],
				['address' => ['district' => 12], 'at' => '2026-09-02'],
				'not a row',
			],
			areaProperty: 'address.district',
			dateProperty: 'at',
			from: new DateTimeImmutable('2026-09-01')
		);

		$this->assertSame(2, $result['total']);
		$this->assertSame(
			[['area' => '12', 'count' => 1, 'rate' => 0.5], ['area' => 'West', 'count' => 1, 'rate' => 0.5]],
			$result['areas']
		);
	}//end testANestedAreaIsReadAndAnUndatedRowFallsOutsideABoundedPeriod()
}//end class
