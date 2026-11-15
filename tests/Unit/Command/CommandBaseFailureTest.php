<?php

/**
 * CommandBaseFailureTest
 *
 * Pins what every launchpad command does when its handler throws: exit
 * code 1, a JSON error envelope on stdout or the message on stderr, and an
 * audit line naming the command and the caller.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Command
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Command;

use DomainException;
use OCA\LaunchPad\Command\CommandBase;
use OCA\LaunchPad\Service\CommandService;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Failure handling and audit of the command base.
 *
 * @covers \OCA\LaunchPad\Command\CommandBase
 * @uses   \OCA\LaunchPad\Service\CommandService
 */
class CommandBaseFailureTest extends TestCase {

	/**
	 * Audit records: message and context.
	 *
	 * @var list<array{0: string, 1: array<string, mixed>}>
	 */
	private array $audit = [];

	/**
	 * The argv before the test.
	 *
	 * @var mixed
	 */
	private mixed $argv = null;

	/**
	 * Pin argv so the audit args are deterministic.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->argv = $_SERVER['argv'] ?? null;
		$_SERVER['argv'] = ['occ', 'launchpad:test:boom', 'abc', '--json'];
	}//end setUp()

	/**
	 * Restore argv.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$_SERVER['argv'] = $this->argv;
		parent::tearDown();
	}//end tearDown()

	/**
	 * A command whose handler throws, over a recording audit logger.
	 *
	 * @param string      $name    The command name.
	 * @param IUserSession $session The user session.
	 *
	 * @return CommandTester
	 */
	private function failingCommand(string $name, IUserSession $session): CommandTester {
		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('info')->willReturnCallback(function ($message, array $context = []): void {
			$this->audit[] = [(string)$message, $context];
		});

		$command = new class (new CommandService($logger), $session, $name) extends CommandBase {
			/**
			 * Constructor.
			 *
			 * @param CommandService $service The command service.
			 * @param IUserSession   $session The user session.
			 * @param string         $commandName The name to register.
			 */
			public function __construct(CommandService $service, IUserSession $session, private string $commandName) {
				parent::__construct(commandService: $service, userSession: $session);
				$this->setName($this->commandName);
			}

			/**
			 * Name is set in the constructor.
			 *
			 * @return void
			 */
			protected function configureCommand(): void {
				$this->setName('placeholder');
			}

			/**
			 * Always fail.
			 *
			 * @param InputInterface  $input  Input.
			 * @param OutputInterface $output Output.
			 *
			 * @return int
			 */
			protected function handle(InputInterface $input, OutputInterface $output): int {
				throw new DomainException('store unreachable');
			}
		};

		return new CommandTester($command);
	}//end failingCommand()

	/**
	 * A session whose user has the given uid.
	 *
	 * @param string|null $uid The uid, or null for no user.
	 *
	 * @return IUserSession
	 */
	private function sessionOf(?string $uid): IUserSession {
		$session = $this->createMock(IUserSession::class);
		if ($uid === null) {
			$session->method('getUser')->willReturn(null);
			return $session;
		}

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn($uid);
		$session->method('getUser')->willReturn($user);
		return $session;
	}//end sessionOf()

	/**
	 * With --json a thrown exception becomes one INTERNAL_ERROR envelope on
	 * stdout, the exit code is 1 and the audit names the caller.
	 *
	 * @return void
	 */
	public function testJsonFailureEmitsAnErrorEnvelope(): void {
		$tester = $this->failingCommand(name: 'launchpad:test:boom', session: $this->sessionOf(uid: 'admin'));

		$exit = $tester->execute(['--json' => true]);

		$this->assertSame(CommandService::EXIT_ERROR, $exit);
		$envelope = json_decode(trim($tester->getDisplay()), true);
		$this->assertSame(
			[
				'success' => false,
				'exitCode' => 1,
				'data' => null,
				'errors' => [
					['code' => 'INTERNAL_ERROR', 'message' => 'store unreachable', 'context' => ['exceptionClass' => DomainException::class]],
				],
			],
			$envelope
		);
		$this->assertCount(1, $this->audit);
		$this->assertMatchesRegularExpression(
			'/^\[launchpad\] cli test:boom abc --json exitCode=1 durationMs=\d+ byUser=admin$/',
			$this->audit[0][0]
		);
		$this->assertSame(
			['app' => 'launchpad', 'command' => 'test:boom', 'exitCode' => 1, 'byUser' => 'admin'],
			array_diff_key($this->audit[0][1], ['durationMs' => true])
		);
	}//end testJsonFailureEmitsAnErrorEnvelope()

	/**
	 * Without --json the message goes to stderr only; a command outside the
	 * launchpad namespace keeps its full name and no user audits as cli.
	 *
	 * @return void
	 */
	public function testPlainFailureWritesToStderr(): void {
		$tester = $this->failingCommand(name: 'other:boom', session: $this->sessionOf(uid: null));

		$exit = $tester->execute([], ['capture_stderr_separately' => true]);

		$this->assertSame(CommandService::EXIT_ERROR, $exit);
		$this->assertSame('', $tester->getDisplay());
		$this->assertSame('store unreachable', trim($tester->getErrorOutput()));
		$this->assertSame('other:boom', $this->audit[0][1]['command']);
		$this->assertSame('cli', $this->audit[0][1]['byUser']);
	}//end testPlainFailureWritesToStderr()

	/**
	 * An empty uid or a failing session both audit as cli.
	 *
	 * @return void
	 */
	public function testUnresolvableCallerAuditsAsCli(): void {
		$throwing = $this->createMock(IUserSession::class);
		$throwing->method('getUser')->willThrowException(new RuntimeException('no session'));

		$this->failingCommand(name: 'launchpad:a', session: $this->sessionOf(uid: ''))->execute(['--json' => true]);
		$this->failingCommand(name: 'launchpad:b', session: $throwing)->execute(['--json' => true]);

		$this->assertSame(
			[['a', 'cli'], ['b', 'cli']],
			array_map(static fn (array $row): array => [$row[1]['command'], $row[1]['byUser']], $this->audit)
		);
	}//end testUnresolvableCallerAuditsAsCli()
}//end class
