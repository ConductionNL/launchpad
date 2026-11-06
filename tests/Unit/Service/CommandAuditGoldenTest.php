<?php

/**
 * CommandService audit golden test
 *
 * Pins the audit line and log context of the CLI audit trail for a fixed
 * set of runs, against a recorded fixture.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\CommandService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * The CLI audit lines, case by case.
 *
 * @covers \OCA\LaunchPad\Service\CommandService
 */
class CommandAuditGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/command-audit.json';

	/**
	 * Write one audit entry and return the line and context logged.
	 *
	 * @param CommandService       $service The service.
	 * @param array<string, mixed> $run     The run's command, args, exitCode, durationMs and byUser.
	 *
	 * @return void
	 */
	private static function audit(CommandService $service, array $run): void {
		$service->audit(run: $run);
	}//end audit()

	/**
	 * Every run logs the recorded line and context.
	 *
	 * @return void
	 */
	public function testAuditMatchesTheRecordedFixture(): void {
		$long  = str_repeat('a', 600);
		$cases = [
			'plain'      => ['command' => 'dashboard:list', 'args' => '--json', 'exitCode' => 0, 'durationMs' => 12, 'byUser' => 'admin'],
			'cli user'   => ['command' => 'dashboard:list', 'args' => '', 'exitCode' => 0, 'durationMs' => 0, 'byUser' => null],
			'empty user' => ['command' => 'x', 'args' => '  padded  ', 'exitCode' => 2, 'durationMs' => 5, 'byUser' => ''],
			'long args'  => ['command' => 'template:install', 'args' => $long, 'exitCode' => 1, 'durationMs' => 99, 'byUser' => 'root'],
			'multibyte'  => ['command' => 'x', 'args' => str_repeat('é', 520), 'exitCode' => 0, 'durationMs' => 1, 'byUser' => 'ü'],
		];

		$actual = [];
		foreach ($cases as $case => $run) {
			$logged = [];
			$logger = $this->createMock(LoggerInterface::class);
			$logger->method('info')->willReturnCallback(
				static function (string $message, array $context=[]) use (&$logged): void {
					$logged[] = [$message, $context];
				}
			);
			self::audit(service: new CommandService(logger: $logger), run: $run);
			$actual[$case] = $logged;
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
			$this->markTestIncomplete('Fixture recorded.');
		}

		$this->assertSame(json_decode((string) file_get_contents(self::FIXTURE), true), $actual);
	}//end testAuditMatchesTheRecordedFixture()
}//end class
