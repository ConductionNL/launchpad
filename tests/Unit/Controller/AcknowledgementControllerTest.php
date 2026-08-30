<?php

/**
 * AcknowledgementControllerTest
 *
 * Unit tests for AcknowledgementController's ADR-005 authorization guards —
 * a user cannot acknowledge on behalf of another user (REQ-ACK-003) and a
 * non-admin, non-owner cannot read the read-receipt report (REQ-ACK-004).
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

use OCA\LaunchPad\Controller\AcknowledgementController;
use OCA\LaunchPad\Service\AcknowledgementService;
use OCA\LaunchPad\Service\RoleService;
use OCP\AppFramework\Http;
use OCP\IGroupManager;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for AcknowledgementController authorization.
 */
class AcknowledgementControllerTest extends TestCase {
	private IRequest&MockObject $request;
	private AcknowledgementService&MockObject $service;
	private RoleService&MockObject $roleService;
	private IGroupManager&MockObject $groupManager;
	private LoggerInterface&MockObject $logger;

	protected function setUp(): void {
		$this->request = $this->createMock(originalClassName: IRequest::class);
		$this->service = $this->createMock(originalClassName: AcknowledgementService::class);
		$this->roleService = $this->createMock(originalClassName: RoleService::class);
		$this->groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
	}

	private function makeController(?string $userId = 'alice'): AcknowledgementController {
		return new AcknowledgementController(
			request: $this->request,
			acknowledgementService: $this->service,
			roleService: $this->roleService,
			groupManager: $this->groupManager,
			logger: $this->logger,
			userId: $userId,
		);
	}

	/**
	 * REQ-ACK-003: a body userId that names another user is rejected with
	 * 403 and no receipt is written.
	 *
	 * @return void
	 */
	public function testAcknowledgeRejectsCrossUser(): void {
		$this->request->method('getParam')->willReturnMap([
			['announcementKey', '', 'ak-1'],
			['userId', null, 'bob'],
			['contentVersion', 1, 1],
		]);

		$this->service->expects($this->never())->method('acknowledge');

		$response = $this->makeController()->acknowledge();

		$this->assertSame(
			expected: Http::STATUS_FORBIDDEN,
			actual: $response->getStatus()
		);
	}

	/**
	 * REQ-ACK-003: acknowledging with one's own userId succeeds.
	 *
	 * @return void
	 */
	public function testAcknowledgeOwnUserSucceeds(): void {
		$this->request->method('getParam')->willReturnMap([
			['announcementKey', '', 'ak-1'],
			['userId', null, 'alice'],
			['contentVersion', 1, 1],
		]);

		$receipt = new \OCA\LaunchPad\Db\Acknowledgement();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$receipt->setAnnouncementKey('ak-1');
		$receipt->setUserId('alice');
		$receipt->setContentVersion(1);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters

		$this->service->expects($this->once())
			->method('acknowledge')
			->with('ak-1', 'alice', 1)
			->willReturn($receipt);

		$response = $this->makeController()->acknowledge();

		$this->assertSame(
			expected: Http::STATUS_OK,
			actual: $response->getStatus()
		);
	}

	/**
	 * REQ-ACK-004: a non-admin who does not own the template is rejected
	 * with 403 and the report is never built.
	 *
	 * @return void
	 */
	public function testReportRejectsNonOwner(): void {
		$this->groupManager->method('isAdmin')->willReturn(false);
		$this->roleService->method('isAdmin')->willReturn(false);
		// Owner resolves to someone else.
		$this->service->method('resolveOwnerUserId')->willReturn('carol');
		$this->service->expects($this->never())->method('report');

		$response = $this->makeController()->report(announcementKey: 'ak-1');

		$this->assertSame(
			expected: Http::STATUS_FORBIDDEN,
			actual: $response->getStatus()
		);
	}

	/**
	 * REQ-ACK-004: the template owner may read the report.
	 *
	 * @return void
	 */
	public function testReportAllowsOwner(): void {
		$this->groupManager->method('isAdmin')->willReturn(false);
		$this->roleService->method('isAdmin')->willReturn(false);
		$this->service->method('resolveOwnerUserId')->willReturn('alice');
		$this->service->expects($this->once())
			->method('report')
			->with('ak-1')
			->willReturn([
				'announcementKey' => 'ak-1',
				'contentVersion' => 1,
				'deadline' => null,
				'overdue' => false,
				'acknowledgedCount' => 0,
				'pendingCount' => 0,
				'pending' => [],
				'acknowledged' => [],
				'rows' => [],
			]);

		$response = $this->makeController()->report(announcementKey: 'ak-1');

		$this->assertSame(
			expected: Http::STATUS_OK,
			actual: $response->getStatus()
		);
	}

	/**
	 * REQ-ACK-002: the pending endpoint returns the current user's
	 * outstanding items and a 200 status.
	 *
	 * @return void
	 */
	public function testPendingReturnsOutstandingItems(): void {
		$this->service->expects($this->once())
			->method('getPending')
			->with('alice')
			->willReturn(['count' => 1, 'items' => [['announcementKey' => 'ak-1']]]);

		$response = $this->makeController()->pending();

		$this->assertSame(
			expected: Http::STATUS_OK,
			actual: $response->getStatus()
		);
		$this->assertSame(expected: 1, actual: $response->getData()['count']);
	}

	/**
	 * REQ-ACK-004/006: a non-manager cannot export the CSV. Also exercises
	 * the `reportCsv` endpoint's authorization contract (the download path
	 * itself is covered by the Playwright e2e / gate-19 spec — the
	 * `DataDownloadResponse` cannot be instantiated under the OCP stub
	 * bootstrap, mirroring `AnalyticsController::exportCsv`).
	 *
	 * @return void
	 */
	public function testReportCsvRejectsNonManager(): void {
		$this->groupManager->method('isAdmin')->willReturn(false);
		$this->roleService->method('isAdmin')->willReturn(false);
		$this->service->method('resolveOwnerUserId')->willReturn('carol');
		$this->service->expects($this->never())->method('report');

		$response = $this->makeController()->reportCsv(announcementKey: 'ak-1');

		$this->assertSame(
			expected: Http::STATUS_FORBIDDEN,
			actual: $response->getStatus()
		);
	}

	/**
	 * REQ-ACK-004: a Nextcloud admin may read the report even when not the
	 * owner.
	 *
	 * @return void
	 */
	public function testReportAllowsNextcloudAdmin(): void {
		$this->groupManager->method('isAdmin')->willReturn(true);
		$this->service->method('report')->willReturn([
			'announcementKey' => 'ak-1',
			'contentVersion' => 1,
			'deadline' => null,
			'overdue' => false,
			'acknowledgedCount' => 0,
			'pendingCount' => 0,
			'pending' => [],
			'acknowledged' => [],
			'rows' => [],
		]);

		$response = $this->makeController()->report(announcementKey: 'ak-1');

		$this->assertSame(
			expected: Http::STATUS_OK,
			actual: $response->getStatus()
		);
	}
}//end class
