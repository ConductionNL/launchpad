<?php

/**
 * ActivityReportControllerTest
 *
 * Refusals of activity reporting reach the browser as refusals, with the
 * reason, and the CSV arrives as a download (REQ-DWMS-005, REQ-DWMS-006).
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
use OCA\LaunchPad\Controller\ActivityReportController;
use OCA\LaunchPad\Service\ActivityReportService;
use OCA\LaunchPad\Service\ColleagueActivityService;
use OCP\AppFramework\Http;
use OCP\IGroupManager;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ActivityReportController.
 */
class ActivityReportControllerTest extends TestCase {
	public function testASwitchedOffCapabilityIsA403ThatSaysSo(): void {
		$reports = $this->createMock(ActivityReportService::class);
		$reports->method('report')->with('admin', 'anna', '2026-09-01', '2026-09-30')->willReturn(['error' => 'reporting_disabled']);

		$response = $this->controller(userId: 'admin', reports: $reports)->report(userId: 'anna', from: '2026-09-01', until: '2026-09-30');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame(['error' => 'reporting_disabled'], $response->getData());
	}//end testASwitchedOffCapabilityIsA403ThatSaysSo()

	public function testNoUserIdMeansTheCallersOwnActivity(): void {
		$reports = $this->createMock(ActivityReportService::class);
		$reports->expects($this->once())->method('report')->with('anna', 'anna', '2026-09-01', '2026-09-30')->willReturn(['total' => 3]);

		$response = $this->controller(userId: 'anna', reports: $reports)->report(from: '2026-09-01', until: '2026-09-30');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
	}//end testNoUserIdMeansTheCallersOwnActivity()

	public function testAMalformedPeriodIsA400(): void {
		$reports = $this->createMock(ActivityReportService::class);
		$reports->method('report')->willThrowException(new InvalidArgumentException('period_invalid'));

		$response = $this->controller(userId: 'anna', reports: $reports)->report(from: 'yesterday');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
	}//end testAMalformedPeriodIsA400()

	public function testARefusedExportIsA403AndNoDownload(): void {
		// The successful branch builds a DataDownloadResponse, which needs
		// Symfony's HeaderUtils and cannot be built under the OCP stub here
		// (same limit as TileAnalyticsControllerTest); the CSV body itself is
		// covered by ActivityReportServiceTest::testTheExportCarriesTheSameNumbers.
		$reports = $this->createMock(ActivityReportService::class);
		$reports->expects($this->once())->method('export')->with('bram', 'anna', '2026-09-01', '2026-09-30')->willReturn(['error' => 'forbidden']);

		$response = $this->controller(userId: 'bram', reports: $reports)->export(userId: 'anna', from: '2026-09-01', until: '2026-09-30');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame(['error' => 'forbidden'], $response->getData());
	}//end testARefusedExportIsA403AndNoDownload()

	public function testOnlyAnAdministratorReadsOrChangesTheSwitch(): void {
		$reports = $this->createMock(ActivityReportService::class);
		$reports->expects($this->never())->method('setPolicy');

		$controller = $this->controller(userId: 'bram', reports: $reports);

		$this->assertSame(Http::STATUS_FORBIDDEN, $controller->policy()->getStatus());
		$this->assertSame(Http::STATUS_FORBIDDEN, $controller->updatePolicy(enabled: true, purpose: 'x')->getStatus());
	}//end testOnlyAnAdministratorReadsOrChangesTheSwitch()

	public function testTurningItOnWithoutAPurposeIsA400(): void {
		$reports = $this->createMock(ActivityReportService::class);
		$reports->method('setPolicy')->willThrowException(new InvalidArgumentException('purpose_required'));

		$response = $this->controller(userId: 'admin', reports: $reports)->updatePolicy(enabled: true, purpose: '');

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'purpose_required'], $response->getData());
	}//end testTurningItOnWithoutAPurposeIsA400()

	public function testColleagueActivityIsReadForTheCaller(): void {
		$colleagues = $this->createMock(ColleagueActivityService::class);
		$colleagues->expects($this->once())->method('recent')->with('anna', 5)->willReturn(['items' => [], 'total' => 0, 'available' => true]);
		$groups = $this->createMock(IGroupManager::class);
		$controller = new ActivityReportController($this->createMock(IRequest::class), $this->createMock(ActivityReportService::class), $colleagues, $groups, 'anna');

		$response = $controller->colleagues(limit: 5);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(0, $response->getData()['total']);
	}//end testColleagueActivityIsReadForTheCaller()

	/**
	 * The controller with a fake admin check.
	 *
	 * @param string $userId The caller.
	 * @param ActivityReportService $reports The report service double.
	 *
	 * @return ActivityReportController
	 */
	private function controller(string $userId, ActivityReportService $reports): ActivityReportController {
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $uid): bool => ($uid === 'admin'));

		return new ActivityReportController(
			$this->createMock(IRequest::class),
			$reports,
			$this->createMock(ColleagueActivityService::class),
			$groups,
			$userId
		);
	}//end controller()
}//end class
