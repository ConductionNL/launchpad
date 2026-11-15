<?php

/**
 * PurgeOrphanedCascadeData golden test
 *
 * Pins the delete queries, the output lines and the log lines of the
 * orphan purge repair step, on success and when the database fails,
 * against a recorded fixture.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Repair
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Repair;

use OCA\LaunchPad\Repair\PurgeOrphanedCascadeData;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Unit\Db\QueryRecorder;

/**
 * The purge, case by case.
 *
 * @covers \OCA\LaunchPad\Repair\PurgeOrphanedCascadeData
 */
class PurgeOrphanedCascadeDataGoldenTest extends TestCase {

	private const FIXTURE = __DIR__ . '/fixtures/purge-orphaned-cascade-data.json';

	/**
	 * Both cases match the recorded fixture.
	 *
	 * @return void
	 */
	public function testRunMatchesTheRecordedFixture(): void {
		$recorder = new QueryRecorder(test: $this);
		$failing = $this->getMockBuilder(className: IDBConnection::class)->getMock();
		$failing->method('getQueryBuilder')->willThrowException(new RuntimeException('boom'));

		$actual = [
			'purged' => $this->purge(db: $recorder->connection()) + ['queries' => $recorder->calls],
			'failing' => $this->purge(db: $failing),
		];

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
			$this->markTestIncomplete(message: 'Fixture recorded.');
		}

		$this->assertSame(expected: json_decode((string)file_get_contents(self::FIXTURE), true), actual: $actual);
	}

	/**
	 * Run the step and record what it wrote.
	 *
	 * @param IDBConnection $db The connection.
	 *
	 * @return array<string, array<int, string>>
	 */
	private function purge(IDBConnection $db): array {
		$lines = [];
		$output = $this->getMockBuilder(className: IOutput::class)->getMock();
		foreach (['info', 'warning'] as $level) {
			$output->method($level)->willReturnCallback(static function (string $message) use ($level, &$lines): void {
				$lines[] = $level . ' ' . $message;
			});
		}

		$logs = [];
		$logger = $this->getMockBuilder(className: LoggerInterface::class)->getMock();
		$logger->method('warning')->willReturnCallback(static function (string $message, array $context) use (&$logs): void {
			$logs[] = $message . ' ' . json_encode($context);
		});

		(new PurgeOrphanedCascadeData(db: $db, logger: $logger))->run(output: $output);

		return ['output' => $lines, 'logs' => $logs];
	}
}
