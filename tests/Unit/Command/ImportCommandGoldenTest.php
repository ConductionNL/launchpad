<?php

/**
 * Import command golden test
 *
 * Pins what `launchpad:import` prints and returns on every branch:
 * missing and unknown file, refused and failed import, UUID collisions
 * and the summary with its error lines.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Command
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Command;

use OCA\LaunchPad\Command\ImportCommand;
use OCA\LaunchPad\Service\ImportService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The printed output of the import command, case by case.
 */
class ImportCommandGoldenTest extends TestCase {

	/**
	 * Run the command against an import service that answers `$answer`.
	 *
	 * @param array<string, mixed>|\Throwable|null $answer The service answer.
	 * @param array<string, mixed>                 $input  The input.
	 *
	 * @return array{0: int, 1: string}
	 */
	private function runImport(array|\Throwable|null $answer, array $input): array {
		$service = $this->createMock(ImportService::class);
		$expect = $service->expects($answer === null ? $this->never() : $this->once())->method('import');
		if ($answer instanceof \Throwable) {
			$expect->willThrowException($answer);
		} elseif ($answer !== null) {
			$expect->with(__FILE__, (bool)($input['--preserve-uuids'] ?? false), (string)($input['--user'] ?? 'cli'))
				->willReturn($answer);
		}

		$tester = new CommandTester(new ImportCommand($service));
		$code = $tester->execute($input);
		return [$code, $tester->getDisplay()];
	}//end runImport()

	public function testImportCommandOutput(): void {
		$this->assertSame([1, "--file parameter is required\n"], $this->runImport(null, []));
		$this->assertSame([1, "File not found: /no/such.zip\n"], $this->runImport(null, ['--file' => '/no/such.zip']));
		$this->assertSame(
			[1, "Not a launchpad archive\n"],
			$this->runImport(new \InvalidArgumentException('Not a launchpad archive'), ['--file' => __FILE__])
		);
		$this->assertSame(
			[1, "Import failed: disk full\n"],
			$this->runImport(new \RuntimeException('disk full'), ['--file' => __FILE__])
		);
		$this->assertSame(
			[1, "UUID collisions detected (--preserve-uuids):\n - uuid a taken\n - collision\n"],
			$this->runImport(
				['status' => ImportService::ERR_UUID_COLLISION, 'errors' => [['message' => 'uuid a taken'], []]],
				['--file' => __FILE__, '--preserve-uuids' => true, '--user' => 'alice']
			)
		);
		$this->assertSame(
			[0, "Imported 3 dashboards, skipped 1, errors: 2\n - bad widget\n"],
			$this->runImport(
				['status' => 'ok', 'importedDashboardCount' => 3, 'skippedDashboardCount' => 1, 'errors' => [['message' => 'bad widget'], ['message' => '']]],
				['--file' => __FILE__]
			)
		);
	}
}
