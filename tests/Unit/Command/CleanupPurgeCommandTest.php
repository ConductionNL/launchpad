<?php

/**
 * CleanupPurgeCommandTest
 *
 * Pins what `launchpad:cleanup:purge` asks the cleanup service and prints
 * for an unknown category, one category, all categories, a dry run, an
 * answered and a declined confirmation, and skipped categories.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Command
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Command;

use OCA\LaunchPad\Command\CleanupPurgeCommand;
use OCA\LaunchPad\Db\CleanupResult;
use OCA\LaunchPad\Service\Cleanup\CategoryRegistryService;
use OCA\LaunchPad\Service\OrphanedDataCleanupService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The purge command, case by case.
 */
class CleanupPurgeCommandTest extends TestCase {

	/**
	 * Every case exits, purges and prints as recorded.
	 *
	 * @return void
	 */
	public function testRunsMatchTheRecording(): void {
		$cases = [
			'unknown category' => [['--category' => 'nope', '--yes' => true], []],
			'one category' => [['--category' => 'placements', '--yes' => true], []],
			'all categories dry run' => [['--dry-run' => true, '--yes' => true], []],
			'confirmed' => [[], ['y']],
			'declined' => [[], ['n']],
			'with skipped' => [['--yes' => true], [], ['reactions' => 'table missing']],
		];

		$actual = [];
		foreach ($cases as $name => $case) {
			[$options, $answers] = $case;
			$skipped = ($case[2] ?? []);
			$purges  = [];

			$registry = $this->createMock(CategoryRegistryService::class);
			$registry->method('getCategoryNames')->willReturn(['placements', 'shares', 'reactions']);
			$registry->method('getCategoryByName')->willReturnCallback(fn (string $name) => in_array($name, ['placements', 'shares', 'reactions'], true) ? $this->createMock(\OCA\LaunchPad\Service\Cleanup\CleanupCategoryInterface::class) : null);

			$service = $this->createMock(OrphanedDataCleanupService::class);
			$service->method('purge')->willReturnCallback(
				static function (array $categoryNames, bool $dryRun, ?string $userId, string $source) use (&$purges, $skipped): CleanupResult {
					$purges[] = [$categoryNames, $dryRun, $userId, $source];
					return new CleanupResult(byCategory: ['placements' => 2, 'shares' => 1], totalRows: 3, durationMs: 5, dryRun: $dryRun, scannedAt: '2026-01-01T00:00:00Z', skipped: $skipped);
				}
			);

			$command = new CleanupPurgeCommand(cleanupService: $service, registry: $registry);
			$command->setApplication(new Application());
			$tester = new CommandTester($command);
			$tester->setInputs($answers);
			$exit = $tester->execute($options, ['interactive' => $answers !== []]);

			$actual[$name] = ['exit' => $exit, 'purges' => $purges, 'output' => explode("\n", trim($tester->getDisplay()))];
		}

		$fixture = __DIR__.'/fixtures/cleanup-purge-command.json';
		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents($fixture, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
		}

		$this->assertSame(json_decode((string) file_get_contents($fixture), true), $actual);
	}//end testRunsMatchTheRecording()
}//end class
