<?php

/**
 * Confluence import command golden test
 *
 * Pins what `launchpad:import:confluence` prints and returns on every
 * branch: option refusals, an unknown parent path, the dry run, the import
 * under a parent, and refused and failed imports.
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

use OCA\LaunchPad\Command\ImportConfluenceCommand;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Service\ConfluenceImportService;
use OCA\LaunchPad\Service\DashboardTreeService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The printed output of the Confluence import command, case by case.
 */
class ImportConfluenceCommandGoldenTest extends TestCase {

	/**
	 * Run the command.
	 *
	 * @param array<string, mixed> $input The input.
	 * @param \Throwable|null      $fails What the import service throws, if anything.
	 *
	 * @return array{0: int, 1: string}
	 */
	private function runConfluence(array $input, ?\Throwable $fails = null): array {
		$service = $this->createMock(ConfluenceImportService::class);
		$service->method('dryRun')->with(__FILE__)->willReturn(
			['pageCount' => 4, 'attachmentCount' => 2, 'estimatedDashboards' => 3, 'assetFolder' => 'Imports/x', 'warnings' => ['w1']]
		);
		$import = $service->method('import');
		if ($fails !== null) {
			$import->willThrowException($fails);
		} else {
			$import->willReturnCallback(
				static fn (string $zipPath, string $currentUserId, ?string $parentUuid): array => [
					'createdDashboardCount' => 2,
					'skippedPageCount' => 1,
					'errors' => [['pageId' => 'p9', 'reason' => 'empty ' . $currentUserId . ' ' . ($parentUuid ?? 'root')]],
					'assetFolder' => 'Imports/y',
					'warnings' => ['w2', 'w3'],
				]
			);
		}

		$parent = new Dashboard();
		$parent->setUuid('parent-uuid');
		$tree = $this->createMock(DashboardTreeService::class);
		$tree->method('resolvePath')->willReturnCallback(
			static fn (string $path): ?Dashboard => ($path === 'docs/home' ? $parent : null)
		);

		$tester = new CommandTester(new ImportConfluenceCommand($service, $tree));
		$code = $tester->execute($input);
		return [$code, $tester->getDisplay()];
	}//end runConfluence()

	public function testConfluenceImportOutput(): void {
		$this->assertSame([1, "--file parameter is required\n"], $this->runConfluence([]));
		$this->assertSame([1, "File not found: /no/x.zip\n"], $this->runConfluence(['--file' => '/no/x.zip']));
		$this->assertSame(
			[1, "Parent path not found: nope\n"],
			$this->runConfluence(['--file' => __FILE__, '--parent-path' => 'nope'])
		);
		$this->assertSame(
			[0, "Pages: 4, attachments: 2, estimated dashboards: 3, asset folder: Imports/x\nwarning: w1\n"],
			$this->runConfluence(['--file' => __FILE__, '--dry-run' => true])
		);
		$this->assertSame(
			[0, "Imported 2 dashboards, skipped 1, errors: 1, asset folder: Imports/y\n - p9: empty bob parent-uuid\nwarning: w2\nwarning: w3\n"],
			$this->runConfluence(['--file' => __FILE__, '--parent-path' => 'docs/home', '--user' => 'bob'])
		);
		$this->assertSame(
			[0, "Imported 2 dashboards, skipped 1, errors: 1, asset folder: Imports/y\n - p9: empty cli root\nwarning: w2\nwarning: w3\n"],
			$this->runConfluence(['--file' => __FILE__])
		);
		$this->assertSame(
			[1, "Not a Confluence export\n"],
			$this->runConfluence(['--file' => __FILE__], new \InvalidArgumentException('Not a Confluence export'))
		);
		$this->assertSame(
			[1, "Import failed: zip broken\n"],
			$this->runConfluence(['--file' => __FILE__], new \RuntimeException('zip broken'))
		);
	}
}
