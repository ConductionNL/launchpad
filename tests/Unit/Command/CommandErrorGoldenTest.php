<?php

/**
 * Command error golden test
 *
 * Pins what a command prints when it fails: the JSON envelope on stdout
 * with --json, the error line otherwise (on stderr when the output has
 * one), with and without context, for a probe command and for the delete
 * command's own refusals.
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

use OCA\LaunchPad\Command\CommandBase;
use OCA\LaunchPad\Command\DashboardDeleteCommand;
use OCA\LaunchPad\Command\DeleteCommandStores;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\CommandService;
use OCA\LaunchPad\Service\DashboardTreeService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The printed failures, case by case.
 *
 * @covers \OCA\LaunchPad\Command\CommandBase
 * @covers \OCA\LaunchPad\Command\DashboardDeleteCommand
 * @uses   \OCA\LaunchPad\Command\DeleteCommandStores
 * @uses   \OCA\LaunchPad\Service\CommandService
 * @uses   \OCA\LaunchPad\Db\Dashboard
 */
class CommandErrorGoldenTest extends TestCase {

	private const FIXTURE = __DIR__.'/fixtures/command-error.json';

	/**
	 * A command that fails with the error it is given.
	 *
	 * @param array<string, mixed> $error The exit code, code, message and context.
	 *
	 * @return CommandBase
	 */
	private function probe(array $error): CommandBase {
		return new class (new CommandService(new NullLogger()), $this->createMock(IUserSession::class), $error) extends CommandBase {
			/**
			 * @param CommandService       $commandService The service.
			 * @param IUserSession         $userSession    The session.
			 * @param array<string, mixed> $error          The error to emit.
			 */
			public function __construct(CommandService $commandService, IUserSession $userSession, private array $error) {
				parent::__construct(commandService: $commandService, userSession: $userSession);
			}

			/**
			 * @return void
			 */
			protected function configureCommand(): void {
				$this->setName(name: 'launchpad:probe');
			}

			/**
			 * @param InputInterface  $input  The input.
			 * @param OutputInterface $output The output.
			 *
			 * @return int
			 */
			protected function handle(InputInterface $input, OutputInterface $output): int {
				return $this->emitError(input: $input, output: $output, error: $this->error);
			}
		};
	}//end probe()

	/**
	 * Run a command and capture stdout, stderr and the exit code.
	 *
	 * @param CommandBase          $command The command.
	 * @param array<string, mixed> $input   The input.
	 *
	 * @return array<string, mixed>
	 */
	private static function runCommand(CommandBase $command, array $input): array {
		$stdout = new StreamOutput(fopen('php://memory', 'w+'));
		$output = new class ($stdout) extends ConsoleOutput {
			private BufferedOutput $err;

			/**
			 * @param StreamOutput $stdout The stdout stream.
			 */
			public function __construct(private StreamOutput $stdout) {
				parent::__construct();
				$this->err = new BufferedOutput();
			}

			/**
			 * @return OutputInterface
			 */
			public function getErrorOutput(): OutputInterface {
				return $this->err;
			}

			/**
			 * @param string $message The message.
			 * @param bool   $newline Newline.
			 *
			 * @return void
			 */
			protected function doWrite(string $message, bool $newline): void {
				$this->stdout->doWrite($message, $newline);
			}
		};
		$command->setApplication(null);
		$exit = $command->run(new ArrayInput($input), $output);
		rewind($stdout->getStream());

		return ['exit' => $exit, 'stdout' => stream_get_contents($stdout->getStream()), 'stderr' => $output->getErrorOutput()->fetch()];
	}//end runCommand()

	/**
	 * Every case prints the recorded output.
	 *
	 * @return void
	 */
	public function testPrintedFailuresMatchTheRecordedFixture(): void {
		$errors = [
			'with context'    => ['exitCode' => CommandService::EXIT_NOT_FOUND, 'code' => 'NOT_FOUND', 'message' => 'Dashboard not found', 'context' => ['uuid' => 'u-1']],
			'without context' => ['exitCode' => CommandService::EXIT_INVALID_ARGS, 'code' => 'ABORTED', 'message' => 'Deletion aborted by user.'],
			'null context'    => ['exitCode' => 3, 'code' => 'X', 'message' => 'm', 'context' => null],
		];
		$actual = [];
		foreach ($errors as $name => $error) {
			foreach (['plain' => [], 'json' => ['--json' => true], 'quiet' => ['--quiet' => true]] as $mode => $flags) {
				$actual['probe '.$name.' '.$mode] = self::runCommand($this->probe($error), $flags);
			}
		}

		$mapper = $this->createMock(DashboardMapper::class);
		$mapper->method('findByUuid')->willReturnCallback(
			static function (string $uuid): Dashboard {
				if ($uuid === 'missing') {
					throw new DoesNotExistException('no');
				}
				$dashboard = new Dashboard();
				$dashboard->setUuid($uuid);
				return $dashboard;
			}
		);
		$mapper->method('countChildrenByParent')->willReturn(2);
		$delete = new DashboardDeleteCommand(
			commandService: new CommandService(new NullLogger()),
			userSession: $this->createMock(IUserSession::class),
			stores: new DeleteCommandStores(
				dashboardMapper: $mapper,
				placementMapper: $this->createMock(WidgetPlacementMapper::class),
				treeService: $this->createMock(DashboardTreeService::class)
			)
		);
		foreach (['missing', 'parent'] as $uuid) {
			foreach (['plain' => [], 'json' => ['--json' => true]] as $mode => $flags) {
				$actual['delete '.$uuid.' '.$mode] = self::runCommand($delete, ['uuid' => $uuid, '--no-interaction' => true] + $flags);
			}
		}

		if (getenv('LAUNCHPAD_RECORD_GOLDEN') === '1') {
			file_put_contents(self::FIXTURE, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
			$this->markTestIncomplete('Fixture recorded.');
		}

		$this->assertSame(json_decode((string) file_get_contents(self::FIXTURE), true), $actual);
	}//end testPrintedFailuresMatchTheRecordedFixture()
}//end class
