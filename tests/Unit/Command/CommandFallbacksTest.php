<?php

/**
 * CommandFallbacksTest
 *
 * Pins the fallback paths of three occ commands: a purge without a
 * question helper proceeds unprompted, an export whose archive cannot be
 * opened fails with the path in the message, and the string export only
 * scans PHP, Vue, JavaScript and TypeScript files.
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
use OCA\LaunchPad\Command\ExportCommand;
use OCA\LaunchPad\Command\I18nExportStringsCommand;
use OCA\LaunchPad\Db\CleanupResult;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Service\Cleanup\CategoryRegistryService;
use OCA\LaunchPad\Service\ExportService;
use OCA\LaunchPad\Service\OrphanedDataCleanupService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use SplFileInfo;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Fallback paths of the purge, export and string-export commands.
 *
 * @covers \OCA\LaunchPad\Command\CleanupPurgeCommand
 * @covers \OCA\LaunchPad\Command\ExportCommand
 * @covers \OCA\LaunchPad\Command\I18nExportStringsCommand
 * @uses   \OCA\LaunchPad\Command\ExportTarget
 * @uses   \OCA\LaunchPad\Db\CleanupResult
 * @uses   \OCA\LaunchPad\Db\Dashboard
 */
class CommandFallbacksTest extends TestCase {

	/**
	 * Scratch directory, removed after each test.
	 *
	 * @var string
	 */
	private string $dir = '';

	/**
	 * Create the scratch directory.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->dir = sys_get_temp_dir().'/launchpad-command-fallbacks-'.getmypid();
		if (is_dir($this->dir) === false) {
			mkdir($this->dir);
		}
	}//end setUp()

	/**
	 * Remove the scratch directory.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ((array) glob($this->dir.'/*') as $path) {
			if (is_string($path) === true && is_file($path) === true) {
				unlink($path);
			} elseif (is_string($path) === true && is_dir($path) === true) {
				rmdir($path);
			}
		}

		if (is_dir($this->dir) === true) {
			rmdir($this->dir);
		}
	}//end tearDown()

	/**
	 * An interactive purge whose `question` helper is not a QuestionHelper
	 * proceeds without asking and purges every category.
	 *
	 * @return void
	 */
	public function testPurgeWithoutQuestionHelperProceedsUnprompted(): void {
		$registry = $this->createMock(CategoryRegistryService::class);
		$registry->method('getCategoryNames')->willReturn(['placements', 'shares']);

		$service = $this->createMock(OrphanedDataCleanupService::class);
		$service->expects($this->once())
			->method('purge')
			->with([], false, null, 'cli')
			->willReturn(
				new CleanupResult(byCategory: ['placements' => 2, 'shares' => 0], totalRows: 2, durationMs: 1, dryRun: false, scannedAt: '2026-01-01T00:00:00Z')
			);

		$notAQuestionHelper = new class extends Helper {
			/**
			 * The helper name.
			 *
			 * @return string
			 */
			public function getName(): string {
				return 'question';
			}//end getName()
		};

		$command = new CleanupPurgeCommand(cleanupService: $service, registry: $registry);
		$command->setHelperSet(new HelperSet(['question' => $notAQuestionHelper]));

		$tester = new CommandTester($command);
		$exit   = $tester->execute([], ['interactive' => true]);

		$this->assertSame(0, $exit);
		$this->assertStringNotContainsString('(y/N)', $tester->getDisplay());
		$this->assertStringNotContainsString('Purge cancelled.', $tester->getDisplay());
	}//end testPurgeWithoutQuestionHelperProceedsUnprompted()

	/**
	 * An archive path that cannot be opened as a ZIP fails the export with
	 * the path in the message, before a manifest is built.
	 *
	 * @return void
	 */
	public function testExportToAnUnopenableArchiveFails(): void {
		$dashboard = new Dashboard();
		$dashboard->setUuid('d-1');

		$mapper = $this->createMock(DashboardMapper::class);
		$mapper->expects($this->once())->method('findByUuid')->with('d-1')->willReturn($dashboard);

		$service = $this->createMock(ExportService::class);
		$service->expects($this->never())->method('buildManifest');
		$service->expects($this->never())->method('serializeDashboard');

		// A directory cannot be opened as a ZIP archive.
		$tester = new CommandTester(new ExportCommand($service, $mapper));
		$exit   = $tester->execute(['--output' => $this->dir, '--scope' => 'dashboard', '--dashboard-uuid' => 'd-1']);

		$this->assertSame(1, $exit);
		$this->assertSame('Export failed: Could not open ZIP archive at '.$this->dir."\n", $tester->getDisplay());
	}//end testExportToAnUnopenableArchiveFails()

	/**
	 * Only regular PHP, Vue, JavaScript and TypeScript files are scanned,
	 * whatever the case of the extension; directories never are.
	 *
	 * @return void
	 */
	public function testOnlyRegularSourceFilesAreScanned(): void {
		$names = ['a.php', 'b.VUE', 'c.js', 'd.ts', 'e.md', 'f.json', 'noext'];
		foreach ($names as $name) {
			touch($this->dir.'/'.$name);
		}

		$method = new ReflectionMethod(I18nExportStringsCommand::class, 'isSourceFile');

		$actual = [];
		foreach ($names as $name) {
			$actual[$name] = $method->invoke(null, new SplFileInfo($this->dir.'/'.$name));
		}

		// A directory with a source extension is still not a file.
		mkdir($this->dir.'/g.php');
		$actual['g.php directory'] = $method->invoke(null, new SplFileInfo($this->dir.'/g.php'));

		$this->assertSame(
			[
				'a.php' => true,
				'b.VUE' => true,
				'c.js' => true,
				'd.ts' => true,
				'e.md' => false,
				'f.json' => false,
				'noext' => false,
				'g.php directory' => false,
			],
			$actual
		);
	}//end testOnlyRegularSourceFilesAreScanned()
}//end class
