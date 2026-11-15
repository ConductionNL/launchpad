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

use OCA\LaunchPad\Controller\AcknowledgementTools;
use OCA\LaunchPad\Controller\AcknowledgementController;
use OCA\LaunchPad\Db\Acknowledgement;
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
	/**
	 * Request double.
	 *
	 * @var IRequest&MockObject
	 */
	private IRequest&MockObject $request;

	/**
	 * Acknowledgement service double.
	 *
	 * @var AcknowledgementService&MockObject
	 */
	private AcknowledgementService&MockObject $service;

	/**
	 * Role service double.
	 *
	 * @var RoleService&MockObject
	 */
	private RoleService&MockObject $roleService;

	/**
	 * Group manager double.
	 *
	 * @var IGroupManager&MockObject
	 */
	private IGroupManager&MockObject $groupManager;

	/**
	 * Logger double.
	 *
	 * @var LoggerInterface&MockObject
	 */
	private LoggerInterface&MockObject $logger;

	/**
	 * Create fresh doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->request = $this->createMock(originalClassName: IRequest::class);
		$this->service = $this->createMock(originalClassName: AcknowledgementService::class);
		$this->roleService = $this->createMock(originalClassName: RoleService::class);
		$this->groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$this->logger = $this->createMock(originalClassName: LoggerInterface::class);
	}

	/**
	 * Build the controller for a session user.
	 *
	 * @param string|null $userId The session user.
	 *
	 * @return AcknowledgementController The controller.
	 */
	private function makeController(?string $userId = 'alice'): AcknowledgementController {
		return new AcknowledgementController(
			request: $this->request,
			tools: new AcknowledgementTools(
				acknowledgements: $this->service,
				roleService: $this->roleService,
				logger: $this->logger
			),
			groupManager: $this->groupManager,
			userId: $userId
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
		// Alice may acknowledge ak-1, so only the body userId check can refuse.
		$this->service->method('isAllowedToAcknowledge')->willReturnCallback(
			static fn (string $announcementKey, string $userId): bool => $announcementKey === 'ak-1' && $userId === 'alice'
		);
		$this->service->method('currentContentVersion')->willReturnMap([['ak-1', 'alice', 1]]);

		$this->service->expects($this->never())->method('acknowledge');

		$response = $this->makeController()->acknowledge();

		$this->assertSame(
			expected: Http::STATUS_FORBIDDEN,
			actual: $response->getStatus()
		);
	}

	/**
	 * A user outside the announcement's audience cannot acknowledge it, and
	 * the audience is checked for the session user, not a request value.
	 *
	 * @return void
	 */
	public function testAcknowledgeRefusesAUserOutsideTheAudience(): void {
		$this->request->method('getParam')->willReturnMap([
			['announcementKey', '', 'ak-1'],
			['userId', null, null],
			['contentVersion', 1, 1],
		]);
		$this->service->method('isAllowedToAcknowledge')->willReturnCallback(
			static fn (string $announcementKey, string $userId): bool => $announcementKey === 'ak-1' && $userId === 'alice'
		);
		$this->service->expects($this->never())->method('acknowledge');

		$response = $this->makeController(userId: 'mallory')->acknowledge();

		$this->assertSame(expected: Http::STATUS_FORBIDDEN, actual: $response->getStatus());
	}

	/**
	 * An announcement key nobody is addressed by cannot be acknowledged.
	 *
	 * @return void
	 */
	public function testAcknowledgeRefusesAnUnknownAnnouncement(): void {
		$this->request->method('getParam')->willReturnMap([
			['announcementKey', '', 'made-up'],
			['userId', null, 'alice'],
			['contentVersion', 1, 1],
		]);
		$this->service->method('isAllowedToAcknowledge')->willReturnCallback(
			static fn (string $announcementKey, string $userId): bool => $announcementKey === 'ak-1' && $userId === 'alice'
		);
		$this->service->expects($this->never())->method('acknowledge');

		$response = $this->makeController()->acknowledge();

		$this->assertSame(expected: Http::STATUS_FORBIDDEN, actual: $response->getStatus());
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

		$receipt = $this->makeReceipt(version: 1);

		$this->service->method('isAllowedToAcknowledge')->willReturnCallback(
			static fn (string $announcementKey, string $userId): bool => $announcementKey === 'ak-1' && $userId === 'alice'
		);
		$this->service->method('currentContentVersion')->willReturnMap([['ak-1', 'alice', 1]]);
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
	 * Point the request at ak-1 for alice with the given body version, with
	 * ak-1 addressed to alice at the given current version.
	 *
	 * @param int $bodyVersion    The contentVersion in the request.
	 * @param int $currentVersion The announcement's current version.
	 *
	 * @return void
	 */
	private function acknowledgeVersion(int $bodyVersion, int $currentVersion): void {
		$this->request->method('getParam')->willReturnMap([
			['announcementKey', '', 'ak-1'],
			['userId', null, null],
			['contentVersion', 1, $bodyVersion],
		]);
		$this->service->method('isAllowedToAcknowledge')->willReturnCallback(
			static fn (string $announcementKey, string $userId): bool => $announcementKey === 'ak-1' && $userId === 'alice'
		);
		$this->service->method('currentContentVersion')->willReturnMap([['ak-1', 'alice', $currentVersion]]);
	}

	/**
	 * REQ-ACK-005: a receipt cannot be taken in advance for a content
	 * version that has not been published yet.
	 *
	 * @return void
	 */
	public function testAcknowledgeRefusesAFutureContentVersion(): void {
		$this->acknowledgeVersion(bodyVersion: 3, currentVersion: 2);
		$this->service->expects($this->never())->method('acknowledge');

		$response = $this->makeController()->acknowledge();

		$this->assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $response->getStatus());
	}

	/**
	 * An announcement without a current version accepts no receipt.
	 *
	 * @return void
	 */
	public function testAcknowledgeRefusesWhenTheAnnouncementHasNoVersion(): void {
		$this->request->method('getParam')->willReturnMap([
			['announcementKey', '', 'ak-1'],
			['userId', null, null],
			['contentVersion', 1, 1],
		]);
		$this->service->method('isAllowedToAcknowledge')->willReturn(true);
		$this->service->method('currentContentVersion')->willReturn(null);
		$this->service->expects($this->never())->method('acknowledge');

		$response = $this->makeController()->acknowledge();

		$this->assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $response->getStatus());
	}

	/**
	 * REQ-ACK-005: the current version is recorded as sent.
	 *
	 * @return void
	 */
	public function testAcknowledgeAcceptsTheCurrentContentVersion(): void {
		$this->acknowledgeVersion(bodyVersion: 2, currentVersion: 2);
		$this->service->expects($this->once())->method('acknowledge')->with('ak-1', 'alice', 2)->willReturn($this->makeReceipt(version: 2));

		$response = $this->makeController()->acknowledge();

		$this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
	}

	/**
	 * REQ-ACK-005: an older version is accepted, since a copy that was not
	 * re-synced yet still shows it.
	 *
	 * @return void
	 */
	public function testAcknowledgeAcceptsAnOlderContentVersion(): void {
		$this->acknowledgeVersion(bodyVersion: 1, currentVersion: 2);
		$this->service->expects($this->once())->method('acknowledge')->with('ak-1', 'alice', 1)->willReturn($this->makeReceipt(version: 1));

		$response = $this->makeController()->acknowledge();

		$this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
	}

	/**
	 * REQ-ACK-005: the version check uses the announcement being
	 * acknowledged and the session user, not ak-1's version.
	 *
	 * @return void
	 */
	public function testAcknowledgeChecksTheVersionOfTheAnnouncementItNames(): void {
		$bodyVersion = 3;
		$this->request->method('getParam')->willReturnCallback(
			static function (string $key, mixed $default = null) use (&$bodyVersion): mixed {
				return ['announcementKey' => 'ak-2', 'userId' => null, 'contentVersion' => $bodyVersion][$key] ?? $default;
			}
		);
		$this->service->method('isAllowedToAcknowledge')->willReturn(true);
		$this->service->method('currentContentVersion')->willReturnMap([['ak-1', 'alice', 1], ['ak-2', 'alice', 3], ['ak-2', 'bob', 9]]);
		$this->service->expects($this->once())->method('acknowledge')->with('ak-2', 'alice', 3)->willReturn($this->makeReceipt(version: 3));

		$this->assertSame(expected: Http::STATUS_OK, actual: $this->makeController()->acknowledge()->getStatus());
		$bodyVersion = 4;
		$this->assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $this->makeController()->acknowledge()->getStatus());
	}

	/**
	 * Build alice's receipt for ak-1.
	 *
	 * @param int $version The content version.
	 *
	 * @return Acknowledgement The receipt.
	 */
	private function makeReceipt(int $version): Acknowledgement {
		$receipt = new Acknowledgement();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$receipt->setAnnouncementKey('ak-1');
		$receipt->setUserId('alice');
		$receipt->setContentVersion($version);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters

		return $receipt;
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
		$this->assertSame(expected: ['count' => 1, 'items' => [['announcementKey' => 'ak-1']]], actual: $response->getData());
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

	/**
	 * The 401s, the missing key, the version clamp, and the 404 and
	 * logged 500 answers of every endpoint.
	 *
	 * @return void
	 */
	public function testEveryRemainingAnswer(): void {
		$anonymous = $this->makeController(userId: null);
		foreach ([$anonymous->acknowledge(), $anonymous->pending(), $anonymous->report(announcementKey: 'k'), $anonymous->reportCsv(announcementKey: 'k')] as $response) {
			$this->assertSame(401, $response->getStatus());
		}

		$params = ['announcementKey' => '', 'contentVersion' => -4];
		$this->request->method('getParam')->willReturnCallback(
			function (string $key, mixed $default = null) use (&$params): mixed {
				return $params[$key] ?? $default;
			}
		);
		$this->assertSame(
			[400, ['error' => 'announcementKey is required']],
			[$this->makeController()->acknowledge()->getStatus(), $this->makeController()->acknowledge()->getData()]
		);

		$params['announcementKey'] = 'k';
		$logged = [];
		$this->logger->method('error')->willReturnCallback(function (string $message) use (&$logged): void {
			$logged[] = $message;
		});
		$this->service->method('isAllowedToAcknowledge')->willReturn(true);
		$this->service->method('currentContentVersion')->willReturn(1);
		$this->service->expects($this->once())->method('acknowledge')->with('k', 'alice', 1)
			->willThrowException(new \RuntimeException('db down'));
		$this->service->method('getPending')->willThrowException(new \RuntimeException('db down'));
		$this->groupManager->method('isAdmin')->willReturn(true);
		$reports = [
			new \OCP\AppFramework\Db\DoesNotExistException('none'),
			new \RuntimeException('db down'),
			new \OCP\AppFramework\Db\DoesNotExistException('none'),
			new \RuntimeException('db down'),
		];
		$this->service->method('report')->willReturnCallback(function () use (&$reports) {
			$next = array_shift($reports);
			if ($next instanceof \Throwable) {
				throw $next;
			}

			return $next;
		});

		$controller = $this->makeController();
		$answers = [];
		foreach ([
			$controller->acknowledge(),
			$controller->pending(),
			$controller->report(announcementKey: 'k'),
			$controller->report(announcementKey: 'k'),
			$controller->reportCsv(announcementKey: 'k'),
			$controller->reportCsv(announcementKey: 'k'),
		] as $response) {
			$answers[] = [$response->getStatus(), $response->getData()];
		}

		$this->assertSame(
			[
				[500, ['error' => 'Operation failed']],
				[500, ['error' => 'Operation failed']],
				[404, ['error' => 'Unknown announcement']],
				[500, ['error' => 'Operation failed']],
				[404, ['error' => 'Unknown announcement']],
				[500, ['error' => 'Operation failed']],
			],
			$answers
		);
		$this->assertSame(['acknowledge failed: db down', 'pending failed: db down', 'report failed: db down', 'reportCsv failed: db down'], $logged);

	}
}//end class
