<?php

/**
 * StatusReportController Test
 *
 * REQ-DWMS-004: prefill and keep for the signed-in caller.
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

use OCA\LaunchPad\Controller\StatusReportController;
use OCA\LaunchPad\Service\StatusReport\StatusReportRefusedException;
use OCA\LaunchPad\Service\StatusReport\StatusReportService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatusReportController::class)]
#[UsesClass(StatusReportRefusedException::class)]
class StatusReportControllerTest extends TestCase {

	private const SUBJECT = [
		'register'       => 'projects',
		'schema'         => 'task',
		'title'          => 'Programme',
		'deadline'       => '2026-12-15',
		'filters'        => ['team' => 'a'],
		'statusProperty' => 'fase',
		'closedValues'   => ['klaar'],
		'dueProperty'    => 'einde',
	];

	private function controller(?string $userId, StatusReportService $reports): StatusReportController {
		return new StatusReportController($this->createMock(IRequest::class), $reports, $userId);
	}

	public function testTheDraftIsBuiltFromTheBodyOfWorkAsSent(): void {
		$reports = $this->createMock(StatusReportService::class);
		$reports->expects($this->once())->method('draft')->with(self::SUBJECT)->willReturn(['health' => 'on-track']);

		$response = $this->controller('anna', $reports)->draft(...self::SUBJECT);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['health' => 'on-track'], $response->getData());
	}

	public function testAKeptReportIsKeptForTheCallerAndAnswers201(): void {
		$reports = $this->createMock(StatusReportService::class);
		$reports->expects($this->once())->method('keep')->with('anna', self::SUBJECT, 'at-risk', 'Behind.')->willReturn(['id' => 'uuid-1']);

		$response = $this->controller('anna', $reports)->keep(...self::SUBJECT + ['health' => 'at-risk', 'summary' => 'Behind.']);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame(['id' => 'uuid-1'], $response->getData());
	}

	public function testARefusalAnswersItsStatusAndReason(): void {
		$reports = $this->createMock(StatusReportService::class);
		$reports->method('draft')->willThrowException(new StatusReportRefusedException('The deadline must be a day, as YYYY-MM-DD.'));
		$reports->method('keep')->willThrowException(new StatusReportRefusedException('Needs OpenRegister.', 503));
		$controller = $this->controller('anna', $reports);

		$draft = $controller->draft('projects', 'task', '', 'soon');
		$this->assertSame(Http::STATUS_BAD_REQUEST, $draft->getStatus());
		$this->assertSame('The deadline must be a day, as YYYY-MM-DD.', $draft->getData()['error']);
		$this->assertSame(503, $controller->keep('projects', 'task')->getStatus());
	}

	public function testWithoutASignedInUserNothingIsBuiltOrKept(): void {
		$reports = $this->createMock(StatusReportService::class);
		$reports->expects($this->never())->method('draft');
		$reports->expects($this->never())->method('keep');
		$controller = $this->controller(null, $reports);

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $controller->draft('projects', 'task')->getStatus());
		$this->assertSame(Http::STATUS_UNAUTHORIZED, $controller->keep('projects', 'task')->getStatus());
	}
}
