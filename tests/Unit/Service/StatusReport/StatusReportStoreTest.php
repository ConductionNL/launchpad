<?php

/**
 * StatusReportStore Test
 *
 * REQ-DWMS-004: a kept report goes to the launchpad register's status_report schema through OpenRegister.
 *
 * @category  Test
 * @package   Unit\Service\StatusReport
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

namespace Unit\Service\StatusReport;

use JsonSerializable;
use OCA\LaunchPad\Service\StatusReport\StatusReportStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

require_once dirname(__DIR__, 3) . '/Stubs/OpenRegisterValueStubs.php';

#[CoversClass(StatusReportStore::class)]
class StatusReportStoreTest extends TestCase {

	/**
	 * A container holding this object service, or none.
	 */
	private function container(?object $service): ContainerInterface {
		$container = $this->createMock(ContainerInterface::class);
		$container->method('has')->with(StatusReportStore::OBJECT_SERVICE)->willReturn($service !== null);
		$container->method('get')->with(StatusReportStore::OBJECT_SERVICE)->willReturn($service);
		return $container;
	}

	public function testTheReportIsSavedToTheStatusReportSchemaOfTheLaunchpadRegister(): void {
		$service = new class {
			public array $calls = [];

			public function saveObject(array $object, object $options): JsonSerializable {
				$this->calls[] = [$object, $options->target->register, $options->target->schema];
				return new class($object) implements JsonSerializable {
					public function __construct(private array $object) {
					}

					public function jsonSerialize(): array {
						return $this->object + ['id' => 'uuid-1'];
					}
				};
			}
		};

		$stored = (new StatusReportStore($this->container($service), $this->createMock(LoggerInterface::class)))->save(['health' => 'at-risk']);

		$this->assertSame(['health' => 'at-risk', 'id' => 'uuid-1'], $stored);
		$this->assertSame([[['health' => 'at-risk'], 'launchpad', 'status_report']], $service->calls);
	}

	public function testWithoutOpenRegisterNothingIsStored(): void {
		$this->assertNull((new StatusReportStore($this->container(null), $this->createMock(LoggerInterface::class)))->save(['health' => 'on-track']));
	}

	public function testAFailedSaveIsLoggedAndAnswersNull(): void {
		$service = new class {
			public function saveObject(array $object, object $options): never {
				throw new RuntimeException('validation failed');
			}
		};
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects($this->once())->method('warning')->with('launchpad.status_report.save_failed', ['exception' => 'validation failed']);

		$this->assertNull((new StatusReportStore($this->container($service), $logger))->save([]));
	}

	public function testAnAnswerThatIsNoObjectIsNotTakenForAStoredReport(): void {
		$service = new class {
			public function saveObject(array $object, object $options): string {
				return 'saved';
			}
		};

		$this->assertNull((new StatusReportStore($this->container($service), $this->createMock(LoggerInterface::class)))->save([]));
	}
}
