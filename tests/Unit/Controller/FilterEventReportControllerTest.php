<?php

/**
 * Unit tests for FilterEventReportController: signed-out, not-found and the
 * report itself.
 *
 * @category Test
 * @package  OCA\LaunchPad\Tests\Unit\Controller
 * @author   Conduction b.v. <info@conduction.nl>
 * @license  EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link     https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/tasks.md#task-16
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\FilterEventReportController;
use OCA\LaunchPad\Service\FilterEventReportService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the drill-down path report route.
 */
#[CoversClass(FilterEventReportController::class)]
class FilterEventReportControllerTest extends TestCase {

	/** @var IUserSession&MockObject */
	private IUserSession&MockObject $session;

	/** @var FilterEventReportService&MockObject */
	private FilterEventReportService&MockObject $reports;

	/** The controller under test. */
	private FilterEventReportController $controller;

	/**
	 * Build the controller over mocks.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->session    = $this->createMock(IUserSession::class);
		$this->reports    = $this->createMock(FilterEventReportService::class);
		$this->controller = new FilterEventReportController($this->createMock(IRequest::class), $this->reports, $this->session);
	}//end setUp()

	/**
	 * Sign eva in.
	 *
	 * @return void
	 */
	private function signedIn(): void {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('eva');
		$this->session->method('getUser')->willReturn($user);
	}//end signedIn()

	/**
	 * Signed out is 401 and asks the service nothing.
	 *
	 * @return void
	 */
	public function testSignedOutIsUnauthorized(): void {
		$this->reports->expects(self::never())->method('report');

		self::assertSame(401, $this->controller->report(uuid: 'abc')->getStatus());
	}//end testSignedOutIsUnauthorized()

	/**
	 * The service's report for the caller, with the limit passed on.
	 *
	 * @return void
	 */
	public function testReturnsTheCallersReport(): void {
		$this->signedIn();
		$report = ['dashboardId' => 'abc', 'scope' => 'own', 'events' => 1, 'paths' => [['path' => 'gemeenteCode', 'count' => 1]]];
		$this->reports->expects(self::once())->method('report')
			->with('abc', 'eva', 5)
			->willReturn($report);

		$response = $this->controller->report(uuid: 'abc', limit: 5);

		self::assertSame(200, $response->getStatus());
		self::assertSame($report, $response->getData());
	}//end testReturnsTheCallersReport()

	/**
	 * A dashboard the caller may not see is 404.
	 *
	 * @return void
	 */
	public function testAnInvisibleDashboardIsNotFound(): void {
		$this->signedIn();
		$this->reports->method('report')->willThrowException(new DoesNotExistException('no'));

		self::assertSame(404, $this->controller->report(uuid: 'abc')->getStatus());
	}//end testAnInvisibleDashboardIsNotFound()
}//end class
