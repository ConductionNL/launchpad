<?php

/**
 * Unit tests for AdminWidgetRulesController: list and error mapping.
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

use OCA\LaunchPad\Controller\AdminWidgetRulesController;
use OCA\LaunchPad\Service\ConditionalService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\Controller\AdminWidgetRulesController
 * @uses   \OCA\LaunchPad\Controller\ResponseHelper
 */
class AdminWidgetRulesControllerTest extends TestCase {
	public function testRulesAreReturnedAsIs(): void {
		$service = $this->createMock(ConditionalService::class);
		$service->expects($this->once())->method('listAllRules')->willReturn([['id' => 1, 'ruleType' => 'group']]);

		$response = (new AdminWidgetRulesController($this->createMock(IRequest::class), $service))->index();

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame([['id' => 1, 'ruleType' => 'group']], $response->getData());
	}

	public function testServiceFailureIsAGenericBadRequest(): void {
		$service = $this->createMock(ConditionalService::class);
		$service->method('listAllRules')->willThrowException(new RuntimeException('db down'));

		$response = (new AdminWidgetRulesController($this->createMock(IRequest::class), $service))->index();

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Operation failed'], $response->getData());
	}
}
