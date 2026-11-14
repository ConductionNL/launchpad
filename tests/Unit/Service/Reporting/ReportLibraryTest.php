<?php

/**
 * ReportLibrary Test
 *
 * REQ-DWMS-003: a report runs on a fresh instance with nothing composed, a
 * report whose schema is missing is listed as unavailable naming it and does
 * not error when opened, and a result saves as a dashboard owned by the saver.
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

use InvalidArgumentException;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\PlacementService;
use OCA\LaunchPad\Service\Reporting\AreaAggregation;
use OCA\LaunchPad\Service\Reporting\ObjectRows;
use OCA\LaunchPad\Service\Reporting\ReportDefinitions;
use OCA\LaunchPad\Service\Reporting\ReportLibrary;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ReportLibraryTest extends TestCase {

	/** @var ObjectRows&MockObject */
	private $objects;

	/** @var DashboardService&MockObject */
	private $dashboards;

	/** @var PlacementService&MockObject */
	private $placements;

	protected function setUp(): void {
		parent::setUp();
		$this->objects = $this->createMock(ObjectRows::class);
		$this->dashboards = $this->createMock(DashboardService::class);
		$this->placements = $this->createMock(PlacementService::class);
	}//end setUp()

	public function testTheGalleryListsAReportWhoseSchemaIsMissingAsUnavailableNamingIt(): void {
		// Launchpad's own register is there; dossiq is not installed.
		$this->objects->method('rows')->willReturnCallback(
			static fn (string $register, string $schema): ?array => $register === 'launchpad' ? [] : null
		);

		$gallery = $this->library()->gallery();

		$this->assertSame(['open-cases-by-handler', 'cases-per-area', 'dashboards-by-type'], array_column($gallery, 'id'));
		$this->assertSame([false, false, true], array_column($gallery, 'available'));
		$this->assertSame([['dossiq/case'], ['dossiq/case'], []], array_column($gallery, 'missing'));
	}//end testTheGalleryListsAReportWhoseSchemaIsMissingAsUnavailableNamingIt()

	public function testOpeningAnUnavailableReportAnswersWithoutAnError(): void {
		$this->objects->method('rows')->willReturn(null);

		$run = $this->library()->run(id: 'open-cases-by-handler');

		$this->assertFalse($run['available']);
		$this->assertSame(['dossiq/case'], $run['missing']);
		$this->assertNull($run['result']);
		$this->assertNull($this->library()->run(id: 'no-such-report'));
	}//end testOpeningAnUnavailableReportAnswersWithoutAnError()

	public function testAReportRunsOnAFreshInstanceWithItsFiltersAndCountsByHandler(): void {
		$this->objects->expects($this->once())->method('rows')
			->with('dossiq', 'case', ['isFinalStatus' => false])
			->willReturn([['assignee' => 'anna'], ['assignee' => 'ben'], ['assignee' => 'anna'], ['assignee' => ''], []]);

		$run = $this->library()->run(id: 'open-cases-by-handler');

		$this->assertTrue($run['available']);
		$this->assertSame(
			['total' => 5, 'groups' => [['label' => 'Unassigned', 'count' => 2], ['label' => 'anna', 'count' => 2], ['label' => 'ben', 'count' => 1]]],
			$run['result']
		);
	}//end testAReportRunsOnAFreshInstanceWithItsFiltersAndCountsByHandler()

	public function testTheAreaReportUsesItsPeriodAndKeepsTheNoLocationBucket(): void {
		$this->objects->method('rows')->willReturn([
			['district' => 'Noord', 'startDate' => '2026-08-01'],
			['startDate' => '2026-08-02'],
			['district' => 'Noord', 'startDate' => '2026-11-01'],
		]);

		$run = $this->library()->run(id: 'cases-per-area', parameters: ['from' => '2026-07-01', 'until' => '2026-10-01', 'other' => 'ignored']);

		$this->assertSame(['from' => '2026-07-01', 'until' => '2026-10-01'], $run['parameterValues']);
		$this->assertSame(
			['total' => 2, 'groups' => [['label' => 'Noord', 'count' => 1, 'rate' => 0.5], ['label' => AreaAggregation::NO_LOCATION, 'count' => 1, 'rate' => 0.5]]],
			$run['result']
		);
	}//end testTheAreaReportUsesItsPeriodAndKeepsTheNoLocationBucket()

	public function testAParameterThatIsNotADateIsRefused(): void {
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Parameter from must be a date.');

		$this->library()->run(id: 'cases-per-area', parameters: ['from' => 'last tuesday-ish']);
	}//end testAParameterThatIsNotADateIsRefused()

	public function testSaveAsDashboardCreatesADashboardOwnedByTheSaverWithTheResult(): void {
		$this->objects->method('rows')->willReturn([['type' => 'user'], ['type' => 'user'], ['type' => 'admin_template']]);
		$dashboard = new Dashboard();
		$dashboard->setId(42);
		$this->dashboards->expects($this->once())->method('createDashboard')
			->with('handler-anna', 'Dashboards by type', ['description' => 'How many dashboards of each type this instance holds.'])
			->willReturn($dashboard);
		$this->placements->expects($this->once())->method('addWidget')
			->with(
				42,
				'chart',
				$this->callback(function (array $layout): bool {
					$this->assertSame('donut', $layout['content']['chartKind']);
					$this->assertSame(['user', 'admin_template'], $layout['content']['props']['categories']);
					$this->assertSame([2, 1], $layout['content']['props']['series'][0]['data']);
					$this->assertSame(['id' => 'dashboards-by-type', 'parameters' => [], 'savedTotal' => 3], $layout['content']['report']);
					return true;
				})
			)
			->willReturn(new WidgetPlacement());

		$this->assertSame($dashboard, $this->library()->saveAsDashboard(id: 'dashboards-by-type', parameters: [], userId: 'handler-anna'));
	}//end testSaveAsDashboardCreatesADashboardOwnedByTheSaverWithTheResult()

	public function testAnUnavailableOrUnknownReportIsNotSaved(): void {
		$this->objects->method('rows')->willReturn(null);
		$this->dashboards->expects($this->never())->method('createDashboard');

		try {
			$this->library()->saveAsDashboard(id: 'dashboards-by-type', parameters: [], userId: 'anna');
			$this->fail('an unavailable report must not be saved');
		} catch (InvalidArgumentException $e) {
			$this->assertSame('Report dashboards-by-type cannot run here: missing launchpad/dashboard.', $e->getMessage());
		}

		$this->expectExceptionMessage('There is no report nope.');
		$this->library()->saveAsDashboard(id: 'nope', parameters: [], userId: 'anna');
	}//end testAnUnavailableOrUnknownReportIsNotSaved()

	/**
	 * The library over the shipped definitions.
	 *
	 * @return ReportLibrary
	 */
	private function library(): ReportLibrary {
		return new ReportLibrary(new ReportDefinitions(), $this->objects, new AreaAggregation(), $this->dashboards, $this->placements);
	}//end library()
}//end class
