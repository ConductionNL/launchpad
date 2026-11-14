<?php

/**
 * ActivityReportController Test
 *
 * REQ-DWMS-005 and REQ-DWMS-006 over HTTP: the report, its CSV, the refusals and the administrator's switch.
 *
 * @category  Test
 * @package   Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
 */

declare(strict_types=1);

namespace Unit\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\Controller\ActivityReportController;
use OCA\LaunchPad\Service\ActivityReport\ActivityReportingPolicy;
use OCA\LaunchPad\Service\ActivityReport\ActivityReportRefusedException;
use OCA\LaunchPad\Service\ActivityReport\ActivityReportService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActivityReportController::class)]
#[UsesClass(ActivityReportService::class)]
#[UsesClass(ActivityReportRefusedException::class)]
class ActivityReportControllerTest extends TestCase {

	private const REPORT = ['subject' => 'anna', 'self' => false, 'from' => '2026-09-01', 'to' => '2026-09-30', 'purpose' => 'workload balancing', 'counts' => ['file_changed' => 2], 'days' => ['2026-09-01' => 2], 'total' => 2];

	private function controller(?string $userId, ActivityReportService $reports, ?ActivityReportingPolicy $policy = null, bool $admin = true): ActivityReportController {
		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $uid): bool => $admin && $uid === 'admin');

		return new ActivityReportController(
			$this->createMock(IRequest::class),
			$reports,
			$policy ?? $this->createMock(ActivityReportingPolicy::class),
			$groups,
			$userId
		);
	}

	public function testTheReportIsBuiltForTheSignedInCallerAsAdministrator(): void {
		$reports = $this->createMock(ActivityReportService::class);
		$reports->expects($this->once())->method('report')->with('admin', true, 'anna', '2026-09-01', '2026-09-30')->willReturn(self::REPORT);

		$response = $this->controller('admin', $reports)->report('anna', '2026-09-01', '2026-09-30');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(self::REPORT, $response->getData());
	}

	public function testANonAdministratorIsPassedOnAsSuch(): void {
		$reports = $this->createMock(ActivityReportService::class);
		$reports->expects($this->once())->method('report')->with('bob', false, '', '2026-09-01', '2026-09-30')->willReturn(['subject' => 'bob'] + self::REPORT);

		$this->controller('bob', $reports)->report('', '2026-09-01', '2026-09-30');
	}

	public function testARefusalAnswersItsStatusSentenceAndTheSwitch(): void {
		$reports = $this->createMock(ActivityReportService::class);
		$reports->method('report')->willThrowException(new ActivityReportRefusedException(ActivityReportService::DISABLED_MESSAGE, 403, true));
		$policy = $this->createMock(ActivityReportingPolicy::class);
		$policy->method('isEnabled')->willReturn(false);

		$response = $this->controller('admin', $reports, $policy)->report('anna', '2026-09-01', '2026-09-30');

		$this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
		$this->assertSame(['error' => ActivityReportService::DISABLED_MESSAGE, 'activityReportingEnabled' => false], $response->getData());
		$this->assertSame(Http::STATUS_FORBIDDEN, $this->controller('admin', $reports, $policy)->export('anna', '2026-09-01', '2026-09-30')->getStatus());
	}

	public function testSignedOutNothingIsRead(): void {
		$reports = $this->createMock(ActivityReportService::class);
		$reports->expects($this->never())->method('report');
		$controller = $this->controller(null, $reports);

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $controller->report('anna')->getStatus());
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $controller->export('anna')->getStatus());
	}

	public function testTheExportIsTheReportAsCsv(): void {
		$reports = $this->createMock(ActivityReportService::class);
		$reports->method('report')->willReturn(self::REPORT);

		$response = $this->controller('admin', $reports)->export('anna', '2026-09-01', '2026-09-30');

		$this->assertInstanceOf(DataDisplayResponse::class, $response);
		$this->assertSame(ActivityReportService::csv(self::REPORT), $response->render());
		// getHeaders() asks the server for its security headers; the set headers are read directly.
		$headers = (new \ReflectionProperty(\OCP\AppFramework\Http\Response::class, 'headers'))->getValue($response);
		$this->assertSame('text/csv; charset=utf-8', $headers['Content-Type']);
		$this->assertSame('attachment; filename="activity-anna-2026-09-01-2026-09-30.csv"', $headers['Content-Disposition']);
	}

	public function testTheSwitchIsReadAndSetAndRefusedWithoutAPurpose(): void {
		$policy = $this->createMock(ActivityReportingPolicy::class);
		$policy->method('isEnabled')->willReturn(true);
		$policy->method('purpose')->willReturn('workload balancing');
		$policy->method('configure')->willReturnCallback(static function (bool $enabled, ?string $purpose): array {
			if ($enabled === true && trim((string) $purpose) === '') {
				throw new InvalidArgumentException('Activity reporting can only be turned on with a purpose.');
			}

			return ['enabled' => $enabled, 'purpose' => (string) $purpose];
		});
		$controller = $this->controller('admin', $this->createMock(ActivityReportService::class), $policy);

		$this->assertSame(['enabled' => true, 'purpose' => 'workload balancing'], $controller->policy()->getData());
		$this->assertSame(['enabled' => true, 'purpose' => 'audit'], $controller->updatePolicy(true, 'audit')->getData());
		$refused = $controller->updatePolicy(true, '');
		$this->assertSame(Http::STATUS_BAD_REQUEST, $refused->getStatus());
		$this->assertSame(['error' => 'Activity reporting can only be turned on with a purpose.'], $refused->getData());
	}
}
