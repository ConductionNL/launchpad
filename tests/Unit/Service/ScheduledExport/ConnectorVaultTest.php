<?php

/**
 * ConnectorVaultTest
 *
 * The credential-vault route (REQ-SCH-010): the call goes through the
 * connector's call service with the source object, a failing status or a
 * missing vault or source fails the delivery, and there is no local
 * fallback.
 *
 * @category  Test
 * @package   Unit\Service\ScheduledExport
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service\ScheduledExport;

use OCA\LaunchPad\Service\ScheduledExport\ConnectorVault;
use OCA\LaunchPad\Service\ScheduledExport\DeliveryException;
use OCP\IGroupManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

/**
 * @covers \OCA\LaunchPad\Service\ScheduledExport\ConnectorVault
 * @uses   \OCA\LaunchPad\Support\FleetAppId
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md#requirement-req-sch-010-header-signing-and-credential-vaulting
 */
class ConnectorVaultTest extends TestCase {

	/**
	 * A vault over a container holding the given services.
	 *
	 * @param array<string, object> $services Class name to service.
	 * @param list<string>          $admins   The administrators.
	 *
	 * @return ConnectorVault
	 */
	private function vault(array $services, array $admins = []): ConnectorVault {
		$container = $this->createMock(ContainerInterface::class);
		$container->method('get')->willReturnCallback(static function (string $id) use ($services): object {
			if (isset($services[$id]) === false) {
				throw new RuntimeException('not registered: ' . $id);
			}

			return $services[$id];
		});

		$groups = $this->createMock(IGroupManager::class);
		$groups->method('isAdmin')->willReturnCallback(static fn (string $userId): bool => in_array($userId, $admins, true));

		return new ConnectorVault($container, $groups);
	}//end vault()

	/**
	 * A call service whose call() returns a log with the given status.
	 *
	 * @param int $status The status.
	 *
	 * @return object
	 */
	private function calls(int $status): object {
		return new class($status) {
			/** @var array<string, mixed> */
			public array $args = [];

			public function __construct(private int $status) {
			}

			public function call(object $source, string $endpoint, string $method, array $config): object {
				$this->args = ['source' => $source, 'endpoint' => $endpoint, 'method' => $method, 'config' => $config];
				return new class($this->status) implements \JsonSerializable {
					public function __construct(private int $status) {
					}

					public function jsonSerialize(): array {
						return ['uuid' => 'log-1', 'response' => ['statusCode' => $this->status]];
					}
				};
			}
		};
	}//end calls()

	/**
	 * An object service that finds the given source.
	 *
	 * @param object|null $source The source.
	 *
	 * @return object
	 */
	private function objects(?object $source): object {
		return new class($source) {
			public function __construct(private ?object $source) {
			}

			public function find(string $id, bool $_rbac = true): ?object {
				return $this->source;
			}
		};
	}//end objects()

	/**
	 * A source owned by the given user.
	 *
	 * @param string|null $owner The owner.
	 *
	 * @return object
	 */
	private function ownedSource(?string $owner): object {
		return new class($owner) {
			public function __construct(private ?string $owner) {
			}

			public function getOwner(): ?string {
				return $this->owner;
			}
		};
	}//end ownedSource()

	/**
	 * The call goes through the source; its log id is returned.
	 *
	 * @return void
	 */
	public function testSendsThroughTheSource(): void {
		$source = $this->ownedSource(owner: 'alice');
		$calls  = $this->calls(status: 201);
		$id     = $this->vault(['OCA\Integriq\Service\CallService' => $calls, 'OCA\OpenRegister\Service\ObjectService' => $this->objects($source)])
			->send(sourceId: 's1', request: ['endpoint' => '/drop', 'body' => 'B', 'headers' => ['Content-Type' => 'x']], owner: 'alice');

		$this->assertSame('log-1', $id);
		$this->assertSame($source, $calls->args['source']);
		$this->assertSame(['body' => 'B', 'headers' => ['Content-Type' => 'x']], $calls->args['config']);
		$this->assertSame('POST', $calls->args['method']);
	}//end testSendsThroughTheSource()

	/**
	 * Without the connector, without the source, or on an error status, the delivery fails.
	 *
	 * @return void
	 */
	public function testFailures(): void {
		$cases = [
			'The credential vault is not available' => [],
			'Unknown credential vault source: s1' => ['OCA\Integriq\Service\CallService' => $this->calls(status: 200), 'OCA\OpenRegister\Service\ObjectService' => $this->objects(null)],
			'HTTP 500 through the credential vault' => ['OCA\Integriq\Service\CallService' => $this->calls(status: 500), 'OCA\OpenRegister\Service\ObjectService' => $this->objects($this->ownedSource(owner: 'alice'))],
		];

		foreach ($cases as $message => $services) {
			try {
				$this->vault($services)->send(sourceId: 's1', request: [], owner: 'alice');
				$this->fail('expected: ' . $message);
			} catch (DeliveryException $e) {
				$this->assertSame($message, $e->getMessage());
			}
		}
	}//end testFailures()

	/**
	 * A source the creator does not own reads as unknown and is never called; an administrator may use any source.
	 *
	 * @return void
	 */
	public function testTheSourceIsHeldToTheCreator(): void {
		$refusals = [
			'someone else owns it' => [$this->ownedSource(owner: 'bob'), 'alice'],
			'it has no owner' => [$this->ownedSource(owner: null), 'alice'],
			'it carries no owner at all' => [new \stdClass(), 'alice'],
			'the export has no creator' => [$this->ownedSource(owner: ''), ''],
		];

		foreach ($refusals as $case => [$source, $owner]) {
			$calls = $this->calls(status: 200);
			try {
				$this->vault(['OCA\Integriq\Service\CallService' => $calls, 'OCA\OpenRegister\Service\ObjectService' => $this->objects($source)])
					->send(sourceId: 's1', request: [], owner: $owner);
				$this->fail('expected a refusal when ' . $case);
			} catch (DeliveryException $e) {
				$this->assertSame('Unknown credential vault source: s1', $e->getMessage(), $case);
				$this->assertSame([], $calls->args, $case);
			}
		}

		$calls = $this->calls(status: 200);
		$id    = $this->vault(['OCA\Integriq\Service\CallService' => $calls, 'OCA\OpenRegister\Service\ObjectService' => $this->objects($this->ownedSource(owner: 'bob'))], admins: ['admin'])
			->send(sourceId: 's1', request: [], owner: 'admin');
		$this->assertSame('log-1', $id);
	}//end testTheSourceIsHeldToTheCreator()
}//end class
