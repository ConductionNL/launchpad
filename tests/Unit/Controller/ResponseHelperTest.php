<?php

/**
 * ResponseHelperTest
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/responsehelper-instance-api/tasks.md#task-2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Controller;

use OCA\LaunchPad\Controller\ResponseHelper;
use OCP\AppFramework\Http;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The envelopes of the injected ResponseHelper (REQ-DASH-040) and the
 * logging of every exception passed to error() (REQ-DASH-041).
 *
 * @covers \OCA\LaunchPad\Controller\ResponseHelper
 */
class ResponseHelperTest extends TestCase {

	/**
	 * @var LoggerInterface&MockObject
	 */
	private LoggerInterface $logger;

	private ResponseHelper $helper;

	/**
	 * Build the helper around a logger mock.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->helper = new ResponseHelper(logger: $this->logger);
	}//end setUp()

	/**
	 * Unauthorized helper returns the canonical 401.
	 *
	 * @return void
	 */
	public function testUnauthorizedReturnsCanonical401(): void {
		$response = $this->helper->unauthorized();

		$this->assertSame(Http::STATUS_UNAUTHORIZED, $response->getStatus());
		$this->assertSame(['error' => 'Not logged in'], $response->getData());
	}//end testUnauthorizedReturnsCanonical401()

	/**
	 * Forbidden helper accepts a custom message and defaults to "Access denied".
	 *
	 * @return void
	 */
	public function testForbiddenAcceptsCustomMessage(): void {
		$custom = $this->helper->forbidden(message: 'Dashboard creation not allowed');
		$this->assertSame(Http::STATUS_FORBIDDEN, $custom->getStatus());
		$this->assertSame(['error' => 'Dashboard creation not allowed'], $custom->getData());

		$this->assertSame(['error' => 'Access denied'], $this->helper->forbidden()->getData());
	}//end testForbiddenAcceptsCustomMessage()

	/**
	 * Success helper accepts a custom status.
	 *
	 * @return void
	 */
	public function testSuccessAcceptsCustomStatus(): void {
		$response = $this->helper->success(data: ['id' => 1], statusCode: Http::STATUS_CREATED);

		$this->assertSame(Http::STATUS_CREATED, $response->getStatus());
		$this->assertSame(['id' => 1], $response->getData());
	}//end testSuccessAcceptsCustomStatus()

	/**
	 * Serialize a list calls jsonSerialize() on every element.
	 *
	 * @return void
	 */
	public function testSerializeListCallsJsonSerializeOnEachEntity(): void {
		$entities = array_map(
			static fn (int $id): \JsonSerializable => new class($id) implements \JsonSerializable {
				/**
				 * @param int $id The id.
				 */
				public function __construct(
					private readonly int $id,
				) {
				}

				/**
				 * @return array<string, int>
				 */
				public function jsonSerialize(): array {
					return ['id' => $this->id];
				}
			},
			[1, 2, 3]
		);

		$this->assertSame(
			[['id' => 1], ['id' => 2], ['id' => 3]],
			$this->helper->serializeList(entities: $entities)
		);
	}//end testSerializeListCallsJsonSerializeOnEachEntity()

	/**
	 * A caught exception is logged and a generic error is returned.
	 *
	 * @return void
	 */
	public function testErrorLogsTheExceptionAndReturnsTheGenericMessage(): void {
		$exception = new \RuntimeException('Database constraint X violated');
		$this->logger->expects($this->once())
			->method('error')
			->with('Database constraint X violated', ['exception' => $exception]);

		$response = $this->helper->error(
			exception: $exception,
			statusCode: Http::STATUS_INTERNAL_SERVER_ERROR,
			message: 'Could not save dashboard'
		);

		$this->assertSame(Http::STATUS_INTERNAL_SERVER_ERROR, $response->getStatus());
		$this->assertSame(['error' => 'Could not save dashboard'], $response->getData());
	}//end testErrorLogsTheExceptionAndReturnsTheGenericMessage()

	/**
	 * The default error is logged too and never leaks the raw message.
	 *
	 * @return void
	 */
	public function testDefaultErrorIsLoggedAndStaysGeneric(): void {
		$this->logger->expects($this->once())
			->method('error')
			->with('secret');

		$response = $this->helper->error(exception: new \RuntimeException('secret'));

		$this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
		$this->assertSame(['error' => 'Operation failed'], $response->getData());
	}//end testDefaultErrorIsLoggedAndStaysGeneric()
}//end class
