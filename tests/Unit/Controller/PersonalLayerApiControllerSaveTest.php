<?php

/**
 * PersonalLayerApiControllerSaveTest
 *
 * A save is checked against the dashboard's own placements, not against the
 * caller's view, which has their layer applied: a placement they hid is not
 * in that view, and "Show again" on one of two hidden widgets used to bring
 * both back.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\PersonalLayerApiController;
use OCA\LaunchPad\Db\PersonalLayerMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\PersonalLayerService;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * The placements a personal-layer save is checked against.
 */
class PersonalLayerApiControllerSaveTest extends TestCase {

	/**
	 * A placement with an id.
	 *
	 * @param int $id The id.
	 *
	 * @return WidgetPlacement
	 */
	private static function placement(int $id): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setId($id);
		return $placement;
	}//end placement()

	/**
	 * The save hands the service every placement of the dashboard, also the
	 * ones the caller's view leaves out because they hid them.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function testASaveIsCheckedAgainstTheDashboardsOwnPlacements(): void {
		$all = [self::placement(id: 1), self::placement(id: 2), self::placement(id: 3)];
		$dashboards = $this->createMock(originalClassName: DashboardService::class);
		// The caller hid 2 and 3, so their view holds only 1.
		$dashboards->method('getDashboardForUser')->willReturn(['placements' => [$all[0]]]);
		$placements = $this->createMock(originalClassName: WidgetPlacementMapper::class);
		$placements->method('findByDashboardId')->with(7)->willReturn($all);
		$layers = $this->createMock(originalClassName: PersonalLayerService::class);
		$layers->expects($this->once())->method('save')->with(
			'pieter',
			7,
			[],
			[3],
			$all
		)->willReturn(['saved' => true]);

		$controller = new PersonalLayerApiController(
			request: $this->createMock(originalClassName: IRequest::class),
			layers: $layers,
			mapper: $this->createMock(originalClassName: PersonalLayerMapper::class),
			dashboards: $dashboards,
			userId: 'pieter',
			placements: $placements
		);

		$response = $controller->save(dashboardId: 7, overrides: [], hidden: [3]);

		$this->assertSame(expected: ['saved' => true], actual: $response->getData());
	}//end testASaveIsCheckedAgainstTheDashboardsOwnPlacements()
}//end class
