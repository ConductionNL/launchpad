<?php

/**
 * ColleagueActivityController Test
 *
 * REQ-DWMS-007: the widget's data, for the signed-in reader only.
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

use OCA\LaunchPad\Controller\ColleagueActivityController;
use OCA\LaunchPad\Service\ColleagueActivity\ColleagueActivityService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ColleagueActivityController::class)]
class ColleagueActivityControllerTest extends TestCase {

	public function testTheActivityIsReadForTheSignedInCaller(): void {
		$answer   = ['available' => true, 'days' => 7, 'entries' => [], 'total' => 0, 'counts' => [], 'actors' => 0];
		$activity = $this->createMock(ColleagueActivityService::class);
		$activity->expects($this->once())->method('recent')->with('anna', 5, 3)->willReturn($answer);

		$response = (new ColleagueActivityController($this->createMock(IRequest::class), $activity, 'anna'))->index(5, 3);

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame($answer, $response->getData());
	}

	public function testWithoutASignedInUserNothingIsRead(): void {
		$activity = $this->createMock(ColleagueActivityService::class);
		$activity->expects($this->never())->method('recent');

		$response = (new ColleagueActivityController($this->createMock(IRequest::class), $activity, null))->index();

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
	}
}
