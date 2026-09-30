<?php

/**
 * The personal layer names what it hides, so the "Hidden (n)" list can say
 * which widget each entry is (dashboards-personal-hide-ui REQ-PERSUI-002).
 *
 * @category Test
 * @package  Unit\Controller
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\PersonalLayerApiController;
use OCA\LaunchPad\Db\PersonalLayer;
use OCA\LaunchPad\Db\PersonalLayerMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\PersonalLayerService;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

class PersonalLayerApiControllerHiddenListTest extends TestCase {
	private function controller(?PersonalLayer $layer, array $placements): PersonalLayerApiController {
		$layers = $this->getMockBuilder(PersonalLayerService::class)
			->disableOriginalConstructor()
			->onlyMethods(['save', 'reset'])
			->getMock();
		$mapper = $this->getMockBuilder(PersonalLayerMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findForUser'])
			->getMock();
		$mapper->method('findForUser')->willReturn($layer);
		$dashboards = $this->getMockBuilder(DashboardService::class)
			->disableOriginalConstructor()
			->onlyMethods(['getDashboardForUser'])
			->getMock();
		$dashboards->method('getDashboardForUser')->willReturn(['placements' => []]);
		$placementMapper = $this->getMockBuilder(WidgetPlacementMapper::class)
			->disableOriginalConstructor()
			->onlyMethods(['findByDashboardId'])
			->getMock();
		$placementMapper->method('findByDashboardId')->willReturn($placements);

		return new PersonalLayerApiController(
			$this->createMock(IRequest::class),
			$layers,
			$mapper,
			$dashboards,
			'pieter',
			$placementMapper
		);
	}

	private function placement(int $id, string $widgetId, string $title): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setId($id);
		$placement->setDashboardId(1);
		$placement->setWidgetId($widgetId);
		$placement->setCustomTitle($title);
		return $placement;
	}

	public function testTheLayerListsTheWidgetsItHides(): void {
		$layer = new PersonalLayer();
		$layer->setId(3);
		$layer->setUserId('pieter');
		$layer->setDashboardId(1);
		$layer->setOverrides('{}');
		$layer->setHidden('[2]');

		$data = $this->controller(
			$layer,
			[$this->placement(1, 'text', 'Welcome'), $this->placement(2, 'weather', 'Weather')]
		)->show(1)->getData();

		$this->assertCount(1, $data['hiddenPlacements']);
		$this->assertSame(2, $data['hiddenPlacements'][0]['id']);
		$this->assertSame('Weather', $data['hiddenPlacements'][0]['customTitle']);
	}

	public function testNoLayerHidesNothing(): void {
		$data = $this->controller(null, [$this->placement(1, 'text', 'Welcome')])->show(1)->getData();

		$this->assertSame([], $data['hiddenPlacements']);
	}
}
