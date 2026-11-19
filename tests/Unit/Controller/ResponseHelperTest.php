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
 * @spec openspec/changes/archive/2026-11-19-responsehelper-instance-api/tasks.md#task-2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Controller;

use OCA\LaunchPad\Controller\ResponseHelper;
use OCP\AppFramework\Http;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * The envelopes of the injected ResponseHelper (REQ-DASH-040) and the
 * logging of every exception passed to error() (REQ-DASH-041).
 *
 * @covers \OCA\LaunchPad\Controller\ResponseHelper
 */
class ResponseHelperTest extends TestCase {

	/**
	 * @var RecordingLogger
	 */
	private RecordingLogger $logger;

	/**
	 * @var ResponseHelper
	 */
	private ResponseHelper $helper;

	/**
	 * Build the helper around a logger mock.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->logger = new RecordingLogger();
		$this->helper = new ResponseHelper(logger: $this->logger);
	}//end setUp()

	/**
	 * Unauthorized helper returns the canonical 401.
	 *
	 * @return void
	 */
	public function testUnauthorizedReturnsCanonical401(): void {
		$response = $this->helper->unauthorized();

		$this->assertSame(expected: Http::STATUS_UNAUTHORIZED, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'Not logged in'], actual: $response->getData());
	}//end testUnauthorizedReturnsCanonical401()

	/**
	 * Forbidden helper accepts a custom message and defaults to "Access denied".
	 *
	 * @return void
	 */
	public function testForbiddenAcceptsCustomMessage(): void {
		$custom = $this->helper->forbidden(message: 'Dashboard creation not allowed');
		$this->assertSame(expected: Http::STATUS_FORBIDDEN, actual: $custom->getStatus());
		$this->assertSame(expected: ['error' => 'Dashboard creation not allowed'], actual: $custom->getData());

		$this->assertSame(expected: ['error' => 'Access denied'], actual: $this->helper->forbidden()->getData());
	}//end testForbiddenAcceptsCustomMessage()

	/**
	 * Success helper accepts a custom status.
	 *
	 * @return void
	 */
	public function testSuccessAcceptsCustomStatus(): void {
		$response = $this->helper->success(data: ['id' => 1], statusCode: Http::STATUS_CREATED);

		$this->assertSame(expected: Http::STATUS_CREATED, actual: $response->getStatus());
		$this->assertSame(expected: ['id' => 1], actual: $response->getData());
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

		$this->assertSame(expected: [['id' => 1], ['id' => 2], ['id' => 3]], actual: $this->helper->serializeList(entities: $entities));
	}//end testSerializeListCallsJsonSerializeOnEachEntity()

	/**
	 * A caught exception is logged and a generic error is returned.
	 *
	 * @return void
	 */
	public function testErrorLogsTheExceptionAndReturnsTheGenericMessage(): void {
		$exception = new \RuntimeException('Database constraint X violated');
		$response = $this->helper->error(
			exception: $exception,
			statusCode: Http::STATUS_INTERNAL_SERVER_ERROR,
			message: 'Could not save dashboard'
		);

		$this->assertSame(
			expected: [['error', 'Database constraint X violated', ['exception' => $exception]]],
			actual: $this->logger->records
		);
		$this->assertSame(expected: Http::STATUS_INTERNAL_SERVER_ERROR, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'Could not save dashboard'], actual: $response->getData());
	}//end testErrorLogsTheExceptionAndReturnsTheGenericMessage()

	/**
	 * The default error is logged too and never leaks the raw message.
	 *
	 * @return void
	 */
	public function testDefaultErrorIsLoggedAndStaysGeneric(): void {
		$exception = new \RuntimeException('secret');
		$response  = $this->helper->error(exception: $exception);

		$this->assertSame(expected: [['error', 'secret', ['exception' => $exception]]], actual: $this->logger->records);

		$this->assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $response->getStatus());
		$this->assertSame(expected: ['error' => 'Operation failed'], actual: $response->getData());
	}//end testDefaultErrorIsLoggedAndStaysGeneric()
}//end class

/**
 * A logger that keeps what it is given.
 */
class RecordingLogger extends AbstractLogger {

	/**
	 * @var array<int, array{0: mixed, 1: string, 2: array<mixed>}>
	 */
	public array $records = [];

	/**
	 * Keep one record.
	 *
	 * @param mixed              $level   The level.
	 * @param string|\Stringable $message The message.
	 * @param array<mixed>       $context The context.
	 *
	 * @return void
	 */
	public function log($level, string|\Stringable $message, array $context = []): void {
		$this->records[] = [$level, (string)$message, $context];
	}//end log()
}//end class
