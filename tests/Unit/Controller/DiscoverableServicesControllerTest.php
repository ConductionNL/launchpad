<?php

/**
 * DiscoverableServicesControllerTest
 *
 * REQ-TILE-044: the administrator reads and replaces the services to
 * discover; an invalid entry answers 400 with the reason.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/add-tiles-dialog/specs/tiles/spec.md#requirement-tiles-for-services-found-on-the-server-req-tile-044
 */

declare(strict_types=1);

namespace Unit\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\Controller\DiscoverableServicesController;
use OCA\LaunchPad\Service\FoundServicesService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[Small]
class DiscoverableServicesControllerTest extends TestCase {

	/**
	 * The list is answered as stored.
	 *
	 * @return void
	 */
	public function testShowAnswersTheList(): void {
		$services = $this->createMock(FoundServicesService::class);
		$services->method('getServices')->willReturn([['name' => 'Grafana', 'url' => 'http://localhost:3000']]);

		$response = (new DiscoverableServicesController(request: $this->createMock(IRequest::class), foundServices: $services))->show();

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame(['services' => [['name' => 'Grafana', 'url' => 'http://localhost:3000']]], $response->getData());
	}//end testShowAnswersTheList()

	/**
	 * A valid list is stored and answered as stored.
	 *
	 * @return void
	 */
	public function testUpdateStoresTheList(): void {
		$submitted = [['name' => 'Grafana', 'url' => 'HTTP://localhost:3000/']];
		$services = $this->createMock(FoundServicesService::class);
		$services->expects($this->once())->method('setServices')->with($submitted)
			->willReturn([['name' => 'Grafana', 'url' => 'http://localhost:3000']]);

		$response = (new DiscoverableServicesController(request: $this->createMock(IRequest::class), foundServices: $services))
			->update(services: $submitted);

		self::assertSame(Http::STATUS_OK, $response->getStatus());
		self::assertSame(['services' => [['name' => 'Grafana', 'url' => 'http://localhost:3000']]], $response->getData());
	}//end testUpdateStoresTheList()

	/**
	 * An invalid list answers 400 with the service's reason.
	 *
	 * @return void
	 */
	public function testUpdateRefusesAnInvalidList(): void {
		$services = $this->createMock(FoundServicesService::class);
		$services->method('setServices')->willThrowException(new InvalidArgumentException('"ftp://a" is not a web address starting with http:// or https://.'));

		$response = (new DiscoverableServicesController(request: $this->createMock(IRequest::class), foundServices: $services))
			->update(services: [['name' => 'x', 'url' => 'ftp://a']]);

		self::assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		self::assertSame(['message' => '"ftp://a" is not a web address starting with http:// or https://.'], $response->getData());
	}//end testUpdateRefusesAnInvalidList()
}//end class
