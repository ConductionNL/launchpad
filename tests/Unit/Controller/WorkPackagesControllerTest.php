<?php

/**
 * WorkPackagesController Test.
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

use OCA\LaunchPad\Controller\WorkPackagesController;
use OCA\LaunchPad\Service\WorkPackages\WorkPackagesService;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;

/**
 * The work packages endpoint asks for the signed-in person only.
 */
class WorkPackagesControllerTest extends TestCase {
	/**
	 * Without a session the endpoint answers 401 and asks nothing.
	 *
	 * @return void
	 */
	public function testWithoutASessionTheAnswerIs401(): void {
		$service = $this->createMock(originalClassName: WorkPackagesService::class);
		$service->expects($this->never())->method('openWorkPackages');
		$controller = new WorkPackagesController(request: $this->createMock(originalClassName: IRequest::class), workPackages: $service, userId: null);

		$this->assertSame(expected: 401, actual: $controller->index()->getStatus());
	}//end testWithoutASessionTheAnswerIs401()

	/**
	 * The session's person is asked for, and an unknown scope reads as `mine`.
	 *
	 * @return void
	 */
	public function testTheSessionPersonIsAskedForWithAKnownScope(): void {
		$service = $this->createMock(originalClassName: WorkPackagesService::class);
		$service->expects($this->once())
			->method('openWorkPackages')
			->with('pieter', 'mine', '', 4)
			->willReturn(['state' => 'ok', 'workPackages' => []]);
		$controller = new WorkPackagesController(request: $this->createMock(originalClassName: IRequest::class), workPackages: $service, userId: 'pieter');

		$this->assertSame(expected: ['state' => 'ok', 'workPackages' => []], actual: $controller->index(scope: 'everyone')->getData());
	}//end testTheSessionPersonIsAskedForWithAKnownScope()
}//end class
