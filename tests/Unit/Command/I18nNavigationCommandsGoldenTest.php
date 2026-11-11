<?php

/**
 * I18n navigation commands golden test
 *
 * Pins what `launchpad:i18n:copy-navigation` and
 * `launchpad:i18n:migrate-language-structure` print and return: missing
 * options, missing tables, the confirmation prompt (declined and
 * accepted), and the success envelope and line.
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

use OCA\LaunchPad\Command\I18nCopyNavigationCommand;
use OCA\LaunchPad\Command\I18nMigrateLanguageStructureCommand;
use OCA\LaunchPad\Service\CommandService;
use OCP\IDBConnection;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The printed output of the two i18n navigation commands, case by case.
 */
class I18nNavigationCommandsGoldenTest extends TestCase {

	private function db(bool $tableExists): IDBConnection {
		$db = $this->createMock(IDBConnection::class);
		$db->method('tableExists')->willReturn($tableExists);
		return $db;
	}

	/**
	 * Run a command; strip the volatile envelope fields.
	 *
	 * @param CommandTester        $tester The tester.
	 * @param array<string, mixed> $input  The input.
	 * @param list<string>         $answers Interactive answers.
	 *
	 * @return array{0: int, 1: string}
	 */
	private static function runCommand(CommandTester $tester, array $input, array $answers = []): array {
		$tester->setInputs($answers);
		$code = $tester->execute($input, ['interactive' => $answers !== []]);
		return [$code, preg_replace('/"(durationMs|timestamp|ts)":[^,}]+,?/', '', $tester->getDisplay(true))];
	}

	private function copy(bool $tableExists): CommandTester {
		return new CommandTester(new I18nCopyNavigationCommand(
			new CommandService(new NullLogger()),
			$this->createMock(IUserSession::class),
			$this->db($tableExists)
		));
	}

	private function migrate(bool $tableExists): CommandTester {
		return new CommandTester(new I18nMigrateLanguageStructureCommand(
			new CommandService(new NullLogger()),
			$this->createMock(IUserSession::class),
			$this->db($tableExists)
		));
	}

	public function testCopyNavigation(): void {
		$outputs = [
			self::runCommand($this->copy(true), ['--from' => 'nl']),
			self::runCommand($this->copy(true), ['--from' => '', '--to' => 'en', '--json' => true]),
			self::runCommand($this->copy(false), ['--from' => 'nl', '--to' => 'en', '--json' => true]),
			self::runCommand($this->copy(false), ['--from' => 'nl', '--to' => 'en']),
			self::runCommand($this->copy(true), ['--from' => 'nl', '--to' => 'en']),
			self::runCommand($this->copy(true), ['--from' => 'nl', '--to' => 'en', '--json' => true]),
		];
		$this->assertSame(
			[CommandService::EXIT_INVALID_ARGS, CommandService::EXIT_INVALID_ARGS, CommandService::EXIT_NOT_FOUND, CommandService::EXIT_NOT_FOUND, 0, 0],
			array_column($outputs, 0)
		);
		$this->assertStringContainsString('Both --from and --to are required', $outputs[0][1]);
		$this->assertStringContainsString('"context":{"from":"","to":"en"}', $outputs[1][1]);
		$this->assertStringContainsString('"code":"NOT_FOUND"', $outputs[2][1]);
		$this->assertStringContainsString('"context":{"language":"nl"}', $outputs[2][1]);
		$this->assertStringContainsString("No navigation tree found for language 'nl'", $outputs[3][1]);
		$this->assertSame("Copied 0 nodes from nl to en\n", $outputs[4][1]);
		$this->assertStringContainsString('{"from":"nl","to":"en","copied":0}', $outputs[5][1]);
	}

	public function testMigrateLanguageStructure(): void {
		$outputs = [
			self::runCommand($this->migrate(false), ['--json' => true]),
			self::runCommand($this->migrate(true), [], ['n']),
			self::runCommand($this->migrate(true), [], ['y']),
			self::runCommand($this->migrate(true), ['--no-interaction' => true]),
			self::runCommand($this->migrate(true), ['--json' => true]),
		];
		$this->assertSame(
			[CommandService::EXIT_ERROR, CommandService::EXIT_INVALID_ARGS, 0, 0, 0],
			array_column($outputs, 0)
		);
		$this->assertStringContainsString('"code":"CAPABILITY_MISSING"', $outputs[0][1]);
		$this->assertStringContainsString('"context":{"expectedTable":"launchpad_language_content"}', $outputs[0][1]);
		$this->assertStringContainsString('Migration aborted by user.', $outputs[1][1]);
		$this->assertStringContainsString('Migration finished — 0 rows migrated.', $outputs[2][1]);
		$this->assertSame("Migration finished — 0 rows migrated.\n", $outputs[3][1]);
		$this->assertStringContainsString('{"migrated":0,"idempotent":true}', $outputs[4][1]);
	}
}
