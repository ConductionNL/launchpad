<?php

/**
 * HiddenWidgetsApiController Test
 *
 * personal-settings REQ-PSET-003: the routes act for the signed-in caller only,
 * and answer 404 for a widget the caller did not hide.
 *
 * @category Test
 * @package  Unit\Controller
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/personal-settings-dialog/specs/personal-settings/spec.md
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\HiddenWidgetsApiController;
use OCA\LaunchPad\Service\HiddenWidgetsOverview;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class HiddenWidgetsApiControllerTest extends TestCase {
	private HiddenWidgetsOverview&MockObject $overview;

	protected function setUp(): void {
		parent::setUp();
		$this->overview = $this->createMock(HiddenWidgetsOverview::class);
	}

	private function controller(?string $userId): HiddenWidgetsApiController {
		return new HiddenWidgetsApiController($this->createMock(IRequest::class), $this->overview, $userId);
	}

	public function testTheListIsReadForTheSignedInCaller(): void {
		$this->overview->expects($this->once())->method('listFor')->with('pieter')
			->willReturn([['placementId' => 101, 'dashboardId' => 10, 'dashboardName' => 'Intranet', 'placement' => []]]);

		$response = $this->controller('pieter')->index();

		$this->assertSame(200, $response->getStatus());
		$this->assertSame(101, $response->getData()['hidden'][0]['placementId']);
	}

	public function testShowAgainActsOnTheCallersLayer(): void {
		$this->overview->expects($this->once())->method('showAgain')->with('pieter', 101)->willReturn(true);

		$response = $this->controller('pieter')->showAgain(101);

		$this->assertSame(200, $response->getStatus());
		$this->assertSame(['shown' => true], $response->getData());
	}

	public function testShowAgainOfAWidgetTheCallerDidNotHideIsNotFound(): void {
		$this->overview->method('showAgain')->willReturn(false);

		$this->assertSame(404, $this->controller('pieter')->showAgain(5)->getStatus());
	}

	public function testShowAllAnswersHowManyCameBack(): void {
		$this->overview->expects($this->once())->method('showAll')->with('pieter')->willReturn(2);

		$this->assertSame(['shown' => 2], $this->controller('pieter')->showAll()->getData());
	}

	public function testNobodySignedInGetsNothing(): void {
		$this->overview->expects($this->never())->method('listFor');
		$this->overview->expects($this->never())->method('showAgain');
		$this->overview->expects($this->never())->method('showAll');

		$this->assertSame(401, $this->controller(null)->index()->getStatus());
		$this->assertSame(401, $this->controller('')->showAgain(1)->getStatus());
		$this->assertSame(401, $this->controller(null)->showAll()->getStatus());
	}
}
