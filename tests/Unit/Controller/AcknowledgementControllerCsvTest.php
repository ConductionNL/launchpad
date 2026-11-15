<?php

/**
 * Unit tests for AcknowledgementController's CSV export.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\AcknowledgementController;
use OCA\LaunchPad\Controller\AcknowledgementTools;
use OCA\LaunchPad\Service\AcknowledgementService;
use OCA\LaunchPad\Service\RoleService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\IGroupManager;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionMethod;

/**
 * Who may download an acknowledgement report as CSV, and what the file holds.
 *
 * @covers \OCA\LaunchPad\Controller\AcknowledgementController
 *
 * @uses \OCA\LaunchPad\Controller\AcknowledgementTools
 * @uses \OCA\LaunchPad\Controller\ResponseHelper
 */
class AcknowledgementControllerCsvTest extends TestCase {

	/**
	 * The acknowledgement service double.
	 *
	 * @var AcknowledgementService&MockObject
	 */
	private AcknowledgementService $service;

	/**
	 * The role service double.
	 *
	 * @var RoleService&MockObject
	 */
	private RoleService $roles;

	/**
	 * The group manager double.
	 *
	 * @var IGroupManager&MockObject
	 */
	private IGroupManager $groups;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->service = $this->createMock(AcknowledgementService::class);
		$this->roles = $this->createMock(RoleService::class);
		$this->groups = $this->createMock(IGroupManager::class);
	}//end setUp()

	/**
	 * The controller under test, for the given session user.
	 *
	 * @param string|null $userId The session user.
	 *
	 * @return AcknowledgementController
	 */
	private function controller(?string $userId = 'alice'): AcknowledgementController {
		return new AcknowledgementController(
			request: $this->createMock(IRequest::class),
			tools: new AcknowledgementTools(acknowledgements: $this->service, roleService: $this->roles, logger: $this->createMock(LoggerInterface::class)),
			groupManager: $this->groups,
			userId: $userId,
		);
	}//end controller()

	/**
	 * Without a session user the export is unauthorised and nothing is read.
	 *
	 * @return void
	 */
	public function testAnAnonymousExportIsUnauthorised(): void {
		$this->service->expects(self::never())->method('report');

		self::assertSame(Http::STATUS_UNAUTHORIZED, $this->controller(null)->reportCsv('ak-1')->getStatus());
	}//end testAnAnonymousExportIsUnauthorised()

	/**
	 * Build the CSV for a report, as reportCsv() hands it to the download.
	 *
	 * @param array<string, mixed> $report The report.
	 *
	 * @return string The CSV text.
	 */
	private function csv(array $report): string {
		$build = new ReflectionMethod(AcknowledgementController::class, 'buildCsv');

		return $build->invoke($this->controller(), $report);
	}//end csv()

	/**
	 * The CSV quotes every field, doubles embedded quotes and ends every line with CRLF.
	 *
	 * @return void
	 */
	public function testTheCsvQuotesEveryField(): void {
		self::assertSame(
			"user_id,status,acknowledged_at\r\n"
			. "\"bob\",\"acknowledged\",\"2026-03-01 10:00\"\r\n"
			. "\"carol \"\"cj\"\"\",\"pending\",\"\"\r\n"
			. "\"\",\"\",\"\"\r\n",
			$this->csv(
				[
					'rows' => [
						['userId' => 'bob', 'status' => 'acknowledged', 'acknowledgedAt' => '2026-03-01 10:00'],
						['userId' => 'carol "cj"', 'status' => 'pending', 'acknowledgedAt' => null],
						[],
					],
				]
			)
		);
	}//end testTheCsvQuotesEveryField()

	/**
	 * A report without rows is only the header line.
	 *
	 * @return void
	 */
	public function testAReportWithoutRowsIsOnlyTheHeader(): void {
		self::assertSame("user_id,status,acknowledged_at\r\n", $this->csv([]));
	}//end testAReportWithoutRowsIsOnlyTheHeader()

	/**
	 * A user who is neither admin nor owner may not export, and nothing is read.
	 *
	 * @return void
	 */
	public function testANonManagerMayNotExport(): void {
		$this->groups->method('isAdmin')->willReturn(false);
		$this->roles->expects(self::once())->method('isAdmin')->with('alice')->willReturn(false);
		$this->service->method('resolveOwnerUserId')->willReturn('bob');
		$this->service->expects(self::never())->method('report');

		self::assertSame(Http::STATUS_FORBIDDEN, $this->controller()->reportCsv('ak-1')->getStatus());
	}//end testANonManagerMayNotExport()

	/**
	 * A launchpad admin passes the check without the owner being looked up.
	 *
	 * @return void
	 */
	public function testALaunchpadAdminIsAManager(): void {
		$this->groups->method('isAdmin')->willReturn(false);
		$this->roles->method('isAdmin')->willReturn(true);
		$this->service->expects(self::never())->method('resolveOwnerUserId');
		$this->service->expects(self::once())->method('report')->with('ak-2')->willThrowException(new DoesNotExistException('none'));

		self::assertSame(Http::STATUS_NOT_FOUND, $this->controller()->reportCsv('ak-2')->getStatus());
	}//end testALaunchpadAdminIsAManager()

	/**
	 * The template owner may export; an unknown announcement is a 404.
	 *
	 * @return void
	 */
	public function testAnUnknownAnnouncementIsNotFound(): void {
		$this->groups->method('isAdmin')->willReturn(false);
		$this->roles->method('isAdmin')->willReturn(false);
		$this->service->method('resolveOwnerUserId')->with('ak-9')->willReturn('alice');
		$this->service->method('report')->willThrowException(new DoesNotExistException('none'));

		self::assertSame(Http::STATUS_NOT_FOUND, $this->controller()->reportCsv('ak-9')->getStatus());
	}//end testAnUnknownAnnouncementIsNotFound()
}//end class
