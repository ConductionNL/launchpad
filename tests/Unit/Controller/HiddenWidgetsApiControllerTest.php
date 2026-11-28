<?php

/**
 * HiddenWidgetsApiController Test
 *
 * Personal-settings REQ-PSET-003: the routes act for the signed-in caller only,
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
 * @spec openspec/changes/archive/2026-11-25-personal-settings-dialog/specs/personal-settings/spec.md
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\HiddenWidgetsApiController;
use OCA\LaunchPad\Service\HiddenWidgetsOverview;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for HiddenWidgetsApiController.
 */
class HiddenWidgetsApiControllerTest extends TestCase {

	/**
	 * The overview behind the routes.
	 *
	 * @var HiddenWidgetsOverview&MockObject
	 */
	private HiddenWidgetsOverview&MockObject $overview;

	/**
	 * A fresh overview per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->overview = $this->createMock(originalClassName: HiddenWidgetsOverview::class);
	}//end setUp()

	/**
	 * The list is read for the signed-in caller.
	 *
	 * @return void
	 */
	public function testTheListIsReadForTheSignedInCaller(): void {
		$this->overview->expects($this->once())->method('listFor')->with('pieter')
			->willReturn([['placementId' => 101, 'dashboardId' => 10, 'dashboardName' => 'Intranet', 'placement' => []]]);

		$response = $this->controller(userId: 'pieter')->index();

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: 101, actual: $response->getData()['hidden'][0]['placementId']);
	}//end testTheListIsReadForTheSignedInCaller()

	/**
	 * Show again acts on the caller's layer.
	 *
	 * @return void
	 */
	public function testShowAgainActsOnTheCallersLayer(): void {
		$this->overview->expects($this->once())->method('showAgain')->with('pieter', 101)->willReturn(true);

		$response = $this->controller(userId: 'pieter')->showAgain(placementId: 101);

		$this->assertSame(expected: 200, actual: $response->getStatus());
		$this->assertSame(expected: ['shown' => true], actual: $response->getData());
	}//end testShowAgainActsOnTheCallersLayer()

	/**
	 * A widget the caller did not hide is not found.
	 *
	 * @return void
	 */
	public function testShowAgainOfAWidgetTheCallerDidNotHideIsNotFound(): void {
		$this->overview->method('showAgain')->willReturn(false);

		$this->assertSame(expected: 404, actual: $this->controller(userId: 'pieter')->showAgain(placementId: 5)->getStatus());
	}//end testShowAgainOfAWidgetTheCallerDidNotHideIsNotFound()

	/**
	 * Show all again answers how many came back.
	 *
	 * @return void
	 */
	public function testShowAllAnswersHowManyCameBack(): void {
		$this->overview->expects($this->once())->method('showAll')->with('pieter')->willReturn(2);

		$this->assertSame(expected: ['shown' => 2], actual: $this->controller(userId: 'pieter')->showAll()->getData());
	}//end testShowAllAnswersHowManyCameBack()

	/**
	 * Nobody signed in gets 401 and the overview is never asked.
	 *
	 * @return void
	 */
	public function testNobodySignedInGetsNothing(): void {
		$this->overview->expects($this->never())->method('listFor');
		$this->overview->expects($this->never())->method('showAgain');
		$this->overview->expects($this->never())->method('showAll');

		$this->assertSame(expected: 401, actual: $this->controller(userId: null)->index()->getStatus());
		$this->assertSame(expected: 401, actual: $this->controller(userId: '')->showAgain(placementId: 1)->getStatus());
		$this->assertSame(expected: 401, actual: $this->controller(userId: null)->showAll()->getStatus());
	}//end testNobodySignedInGetsNothing()

	/**
	 * The controller for a caller.
	 *
	 * @param string|null $userId The caller.
	 *
	 * @return HiddenWidgetsApiController
	 */
	private function controller(?string $userId): HiddenWidgetsApiController {
		return new HiddenWidgetsApiController(
			request: $this->createMock(originalClassName: IRequest::class),
			overview: $this->overview,
			userId: $userId
		);
	}//end controller()
}//end class
