<?php

/**
 * Unit tests for DashboardVersionApiController listVersions and restoreVersion.
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

use OCA\LaunchPad\Controller\ActionCaller;
use OCA\LaunchPad\Controller\DashboardVersionApiController;
use OCA\LaunchPad\Controller\VersionApiSources;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardVersion;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\DashboardVersionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\Controller\DashboardVersionApiController
 * @uses   \OCA\LaunchPad\Controller\ActionCaller
 * @uses   \OCA\LaunchPad\Controller\VersionApiSources
 * @uses   \OCA\LaunchPad\Db\Dashboard
 * @uses   \OCA\LaunchPad\Db\DashboardVersion
 */
class DashboardVersionApiControllerRestoreTest extends TestCase {
	private DashboardMapper&MockObject $mapper;
	private DashboardVersionService&MockObject $versions;
	private ActionAuthService&MockObject $actionAuth;
	private IUserSession&MockObject $session;
	private LoggerInterface&MockObject $logger;

	protected function setUp(): void {
		$this->mapper = $this->createMock(DashboardMapper::class);
		$this->versions = $this->createMock(DashboardVersionService::class);
		$this->actionAuth = $this->createMock(ActionAuthService::class);
		$this->session = $this->createMock(IUserSession::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	private function controller(?string $userId = 'alice'): DashboardVersionApiController {
		$user = null;
		if ($userId !== null) {
			$user = $this->createMock(IUser::class);
			$user->method('getUID')->willReturn($userId);
		}
		$this->session->method('getUser')->willReturn($user);

		return new DashboardVersionApiController(
			request: $this->createMock(IRequest::class),
			sources: new VersionApiSources(dashboardMapper: $this->mapper, versionService: $this->versions, logger: $this->logger),
			caller: new ActionCaller(actionAuth: $this->actionAuth, userSession: $this->session),
			userId: $userId
		);
	}

	private function dashboard(): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId(11);
		$dashboard->setUuid('uuid-ops');
		return $dashboard;
	}

	private function version(int $number): DashboardVersion {
		$version = new DashboardVersion();
		$version->setId(500 + $number);
		$version->setDashboardUuid('uuid-ops');
		$version->setVersionNumber($number);
		$version->setSnapshotJson('{"widgets":[]}');
		return $version;
	}

	public function testListVersionsRejectsAnonymous(): void {
		$this->mapper->expects($this->never())->method('findByUuid');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $this->controller(null)->listVersions(uuid: 'uuid-ops')->getStatus());
	}

	public function testListVersionsChecksTheListAction(): void {
		$this->actionAuth->expects($this->once())
			->method('requireAction')
			->with($this->anything(), 'dashboard-version.list-versions')
			->willThrowException(new OCSForbiddenException());
		$this->mapper->expects($this->never())->method('findByUuid');

		$response = $this->controller()->listVersions(uuid: 'uuid-ops');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame(['error' => 'Forbidden'], $response->getData());
	}

	public function testListVersionsUnknownDashboardIs404(): void {
		$this->mapper->method('findByUuid')->willThrowException(new DoesNotExistException('x'));
		$this->versions->expects($this->never())->method('listVersions');

		$response = $this->controller()->listVersions(uuid: 'nope');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['error' => 'Dashboard not found'], $response->getData());
	}

	public function testListVersionsReturnsTheEnvelope(): void {
		$dashboard = $this->dashboard();
		$this->mapper->expects($this->once())->method('findByUuid')->with('uuid-ops')->willReturn($dashboard);
		$this->versions->expects($this->once())
			->method('listVersions')
			->with($dashboard, 'alice')
			->willReturn(['versions' => [], 'total' => 0]);

		$response = $this->controller()->listVersions(uuid: 'uuid-ops');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['versions' => [], 'total' => 0], $response->getData());
	}

	public function testListVersionsMapsTheOwnerSentinelTo403WithoutLogging(): void {
		$this->mapper->method('findByUuid')->willReturn($this->dashboard());
		$this->versions->method('listVersions')
			->willThrowException(new RuntimeException(DashboardVersionService::ERR_FORBIDDEN_NOT_OWNER_OR_ADMIN));
		$this->logger->expects($this->never())->method('error');

		$response = $this->controller()->listVersions(uuid: 'uuid-ops');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame(['error' => 'forbidden'], $response->getData());
	}

	public function testRestoreVersionReturnsTheVersionAndSnapshot(): void {
		$dashboard = $this->dashboard();
		$version = $this->version(3);
		$this->actionAuth->expects($this->once())
			->method('requireAction')
			->with($this->anything(), 'dashboard-version.restore-version');
		$this->mapper->method('findByUuid')->willReturn($dashboard);
		$this->versions->expects($this->once())
			->method('restoreVersion')
			->with($dashboard, 3, 'alice')
			->willReturn(['version' => $version, 'snapshot' => ['widgets' => []]]);

		$response = $this->controller()->restoreVersion(uuid: 'uuid-ops', versionNumber: 3);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['version' => $version->jsonSerialize(), 'snapshot' => ['widgets' => []]], $response->getData());
	}

	public function testRestoreVersionRejectsAnonymous(): void {
		$this->versions->expects($this->never())->method('restoreVersion');

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $this->controller(null)->restoreVersion(uuid: 'uuid-ops', versionNumber: 1)->getStatus());
	}

	public function testRestoreVersionUnknownDashboardIs404(): void {
		$this->mapper->method('findByUuid')->willThrowException(new DoesNotExistException('x'));

		$response = $this->controller()->restoreVersion(uuid: 'nope', versionNumber: 1);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['error' => 'Dashboard not found'], $response->getData());
	}

	public function testRestoreVersionUnknownVersionIs404(): void {
		$this->mapper->method('findByUuid')->willReturn($this->dashboard());
		$this->versions->method('restoreVersion')->willThrowException(new DoesNotExistException('x'));

		$response = $this->controller()->restoreVersion(uuid: 'uuid-ops', versionNumber: 99);

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['error' => 'Version not found'], $response->getData());
	}

	public function testRestoreVersionHidesAndLogsUnexpectedFailures(): void {
		$failure = new RuntimeException('db exploded');
		$this->mapper->method('findByUuid')->willReturn($this->dashboard());
		$this->versions->method('restoreVersion')->willThrowException($failure);
		$this->logger->expects($this->once())
			->method('error')
			->with('launchpad: version operation failed', ['exception' => $failure]);

		$response = $this->controller()->restoreVersion(uuid: 'uuid-ops', versionNumber: 2);

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame(['error' => 'Operation failed'], $response->getData());
	}
}
