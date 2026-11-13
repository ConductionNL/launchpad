<?php

/**
 * TileAnalyticsControllerPeriodTest
 *
 * Unit tests for the admin read endpoints of TileAnalyticsController once the
 * caller passes the action guard: the rows they return and the 400 each one
 * answers when the service rejects the period. The CSV success path builds a
 * DataDownloadResponse, which needs Symfony's HeaderUtils from a booted
 * Nextcloud, so only its refusal is exercised here.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\Controller\TileAnalyticsController;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\TileAnalyticsService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Controller\TileAnalyticsController
 * @uses   \OCA\LaunchPad\Controller\SessionActionCheck
 * @uses   \OCA\LaunchPad\Controller\ResponseHelper
 */
class TileAnalyticsControllerPeriodTest extends TestCase {
	private TileAnalyticsService&MockObject $service;

	private ActionAuthService&MockObject $actionAuth;

	private TileAnalyticsController $controller;

	/**
	 * Build the controller with an allowed, signed-in caller.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->service = $this->createMock(TileAnalyticsService::class);
		$this->actionAuth = $this->createMock(ActionAuthService::class);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($this->createMock(IUser::class));

		$this->controller = new TileAnalyticsController(
			request: $this->createMock(IRequest::class),
			tileAnalyticsService: $this->service,
			actionAuth: $this->actionAuth,
			userSession: $session
		);
	}//end setUp()

	public function testTopTilesReturnsTheServiceRows(): void {
		$rows = [['tileId' => 4, 'clicks' => 17]];
		$this->actionAuth->expects($this->once())
			->method('requireAction')
			->with($this->isInstanceOf(IUser::class), 'tile-analytics.top-tiles');
		$this->service->expects($this->once())->method('getTopTiles')->with('7d', 5)->willReturn($rows);

		$response = $this->controller->topTiles(period: '7d', limit: 5);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($rows, $response->getData());
	}

	public function testTopTilesWithABadPeriodIs400(): void {
		$this->service->method('getTopTiles')->willThrowException(new InvalidArgumentException('Unknown period'));

		$response = $this->controller->topTiles(period: '1y');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(
			['status' => 'error', 'error' => 'invalid_period', 'message' => 'Unknown period'],
			$response->getData()
		);
	}

	public function testDashboardBreakdownReturnsTheServiceRows(): void {
		$rows = [['tileId' => 1, 'clicks' => 2]];
		$this->service->expects($this->once())
			->method('getDashboardBreakdown')
			->with('dash-9', '30d')
			->willReturn($rows);

		$this->assertSame($rows, $this->controller->dashboardBreakdown(uuid: 'dash-9')->getData());
	}

	public function testDashboardBreakdownWithABadPeriodIs400(): void {
		$this->service->method('getDashboardBreakdown')->willThrowException(new InvalidArgumentException('Unknown period'));

		$response = $this->controller->dashboardBreakdown(uuid: 'dash-9', period: 'x');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame('invalid_period', $response->getData()['error']);
	}

	public function testExportWithABadPeriodIs400AndNamesNoFile(): void {
		$this->service->method('generateCsvExport')->willThrowException(new InvalidArgumentException('Unknown period'));
		$this->service->expects($this->never())->method('csvExportFilename');

		$response = $this->controller->exportCsv(period: 'x');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame('Unknown period', $response->getData()['message']);
	}
}//end class
