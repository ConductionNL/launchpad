<?php

/**
 * ImageHostControllerTest
 *
 * REQ-IMG-006: administrators read and replace the image host allow-list;
 * a list holding something other than host names is refused with 400.
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

use InvalidArgumentException;
use OCA\LaunchPad\Controller\ImageHostController;
use OCA\LaunchPad\Service\ImageHostService;
use OCA\LaunchPad\Settings\LaunchPadAdmin;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\IRequest;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[Small]
class ImageHostControllerTest extends TestCase {

	private ImageHostService $imageHosts;

	private ImageHostController $controller;

	protected function setUp(): void {
		$this->imageHosts = $this->createMock(originalClassName: ImageHostService::class);
		$this->controller = new ImageHostController(
			request: $this->createMock(originalClassName: IRequest::class),
			imageHosts: $this->imageHosts,
		);
	}//end setUp()

	public function testShowAnswersTheStoredHosts(): void {
		$this->imageHosts->method('getAllowedHosts')->willReturn(['example.com']);

		$response = $this->controller->show();

		$this->assertSame(200, $response->getStatus());
		$this->assertSame(['hosts' => ['example.com']], $response->getData());
	}//end testShowAnswersTheStoredHosts()

	public function testUpdateAnswersTheStoredList(): void {
		$this->imageHosts->expects($this->once())->method('setAllowedHosts')
			->with(['Example.com'])->willReturn(['example.com']);

		$response = $this->controller->update(hosts: ['Example.com']);

		$this->assertSame(200, $response->getStatus());
		$this->assertSame(['hosts' => ['example.com']], $response->getData());
	}//end testUpdateAnswersTheStoredList()

	public function testUpdateRefusesAnInvalidListWith400(): void {
		$this->imageHosts->method('setAllowedHosts')->willThrowException(new InvalidArgumentException('"https://x" is not a host name.'));

		$response = $this->controller->update(hosts: ['https://x']);

		$this->assertSame(400, $response->getStatus());
		$this->assertSame(['message' => '"https://x" is not a host name.'], $response->getData());
	}//end testUpdateRefusesAnInvalidListWith400()

	public function testBothEndpointsAreAdminOnly(): void {
		foreach (['show', 'update'] as $method) {
			$attributes = (new ReflectionMethod(ImageHostController::class, $method))->getAttributes(AuthorizedAdminSetting::class);
			$this->assertCount(1, $attributes, $method);
			$this->assertSame([LaunchPadAdmin::class], $attributes[0]->getArguments(), $method);
		}
	}//end testBothEndpointsAreAdminOnly()
}//end class
