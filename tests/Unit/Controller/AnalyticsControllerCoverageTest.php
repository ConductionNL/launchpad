<?php

/**
 * AnalyticsControllerCoverageTest
 *
 * Unit tests for AnalyticsController's answers once the caller passes the
 * action guard: the success payloads and the 400/404 each
 * endpoint maps a service refusal to. The CSV success path builds a
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
use OCA\LaunchPad\Controller\AnalyticsController;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AnalyticsService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \OCA\LaunchPad\Controller\AnalyticsController
 * @uses   \OCA\LaunchPad\Controller\SessionActionCheck
 * @uses   \OCA\LaunchPad\Controller\ResponseHelper
 */
class AnalyticsControllerCoverageTest extends TestCase {
	private AnalyticsService&MockObject $analytics;

	private ActionAuthService&MockObject $actionAuth;

	private AnalyticsController $controller;

	/**
	 * Build the controller with an allowed, signed-in caller.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->analytics = $this->createMock(AnalyticsService::class);
		$this->actionAuth = $this->createMock(ActionAuthService::class);
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($this->createMock(IUser::class));

		$this->controller = new AnalyticsController(
			request: $this->createMock(IRequest::class),
			analyticsService: $this->analytics,
			actionAuth: $this->actionAuth,
			userSession: $session
		);
	}//end setUp()

	public function testTopDashboardsPassesPeriodAndLimitAndReturnsTheRows(): void {
		$rows = [['uuid' => 'd1', 'views' => 9]];
		$this->actionAuth->expects($this->once())
			->method('requireAction')
			->with($this->isInstanceOf(IUser::class), 'analytics.top-dashboards');
		$this->analytics->expects($this->once())->method('getTopDashboards')->with('7d', 3)->willReturn($rows);

		$response = $this->controller->topDashboards(period: '7d', limit: 3);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($rows, $response->getData());
	}

	public function testTopDashboardsWithABadPeriodIs400(): void {
		$this->analytics->method('getTopDashboards')->willThrowException(new InvalidArgumentException('bad period'));

		$response = $this->controller->topDashboards(period: 'forever');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(
			['status' => 'error', 'error' => 'invalid_period', 'message' => 'bad period'],
			$response->getData()
		);
	}

	public function testDashboardDetailReturnsTheRows(): void {
		$rows = ['dates' => ['2026-11-01' => 2]];
		$this->analytics->expects($this->once())->method('getDashboardDetail')->with('d1', '90d')->willReturn($rows);

		$response = $this->controller->dashboardDetail(uuid: 'd1', period: '90d');

		$this->assertSame($rows, $response->getData());
	}

	public function testDashboardDetailOfAnUnknownDashboardIs404(): void {
		$this->analytics->method('getDashboardDetail')->willThrowException(new DoesNotExistException('gone'));

		$response = $this->controller->dashboardDetail(uuid: 'missing');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['status' => 'error', 'error' => 'not_found'], $response->getData());
	}

	public function testDashboardDetailWithABadPeriodIs400(): void {
		$this->analytics->method('getDashboardDetail')->willThrowException(new InvalidArgumentException('nope'));

		$response = $this->controller->dashboardDetail(uuid: 'd1', period: 'x');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame('invalid_period', $response->getData()['error']);
	}

	public function testInstanceSummaryReturnsTheSummaryOr400(): void {
		$this->analytics->method('getInstanceSummary')
			->willReturnCallback(static function (string $period): array {
				if ($period === 'bad') {
					throw new InvalidArgumentException('bad');
				}

				return ['totalViews' => 12, 'period' => $period];
			});

		$this->assertSame(['totalViews' => 12, 'period' => '30d'], $this->controller->instanceSummary()->getData());
		$this->assertSame(Http::STATUS_BAD_REQUEST, $this->controller->instanceSummary(period: 'bad')->getStatus());
	}

	public function testExportCsvWithABadPeriodIs400AndNoFile(): void {
		$this->analytics->method('generateCsvExport')->willThrowException(new InvalidArgumentException('bad'));
		$this->analytics->expects($this->never())->method('csvExportFilename');

		$response = $this->controller->exportCsv(period: 'bad');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}
}//end class
