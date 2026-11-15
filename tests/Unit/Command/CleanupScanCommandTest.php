<?php

/**
 * CleanupScanCommandTest
 *
 * Pins what `launchpad:cleanup:scan` prints and returns: the per-category
 * table with its total, the skipped categories, the timing line, and exit
 * code 1 when anything was found.
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

use OCA\LaunchPad\Command\CleanupScanCommand;
use OCA\LaunchPad\Db\CleanupResult;
use OCA\LaunchPad\Service\OrphanedDataCleanupService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CleanupScanCommandTest extends TestCase {

	/**
	 * @param array<string, int> $counts  Rows per category.
	 * @param list<string>       $skipped Skipped categories.
	 *
	 * @return array{0: int, 1: string}
	 */
	private function scan(array $counts, array $skipped): array {
		$result = $this->createMock(CleanupResult::class);
		$result->method('getByCategory')->willReturn($counts);
		$result->method('getTotalRows')->willReturn(array_sum($counts));
		$result->method('getSkipped')->willReturn($skipped);
		$result->method('getDurationMs')->willReturn(12);
		$service = $this->createMock(OrphanedDataCleanupService::class);
		$service->method('scan')->willReturn($result);

		$tester = new CommandTester(new CleanupScanCommand($service));
		$code = $tester->execute([]);
		return [$code, $tester->getDisplay()];
	}

	public function testScanOutput(): void {
		[$code, $out] = $this->scan(['locks' => 2, 'shares' => 0], ['tiles']);
		$this->assertSame(1, $code);
		$this->assertSame(
			"+----------+-------+\n| Category | Count |\n+----------+-------+\n| locks    | 2     |\n| shares   | 0     |\n| TOTAL    | 2     |\n+----------+-------+\n"
			. "Skipped categories (feature unavailable): tiles\nScan completed in 12ms.\n",
			$out
		);

		[$code, $out] = $this->scan(['locks' => 0], []);
		$this->assertSame(0, $code);
		$this->assertStringEndsWith("| TOTAL    | 0     |\n+----------+-------+\nScan completed in 12ms.\n", $out);
	}
}
