<?php

/**
 * DemoLoadCommandTest
 *
 * Unit tests for `occ launchpad:demo:load`: which language the seed is
 * loaded in, retiring the showcases first, the report it prints and the
 * failure exit when the seed throws.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Command
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Command;

use OCA\LaunchPad\Command\DemoLoadCommand;
use OCA\LaunchPad\Service\DemoSeedService;
use OCP\IConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @covers \OCA\LaunchPad\Command\DemoLoadCommand
 */
class DemoLoadCommandTest extends TestCase {

	/**
	 * Seed service double.
	 *
	 * @var DemoSeedService&MockObject
	 */
	private $seeds;

	/**
	 * Config double.
	 *
	 * @var IConfig&MockObject
	 */
	private $config;

	/**
	 * Build the doubles.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->seeds  = $this->createMock(originalClassName: DemoSeedService::class);
		$this->config = $this->createMock(originalClassName: IConfig::class);
	}//end setUp()

	/**
	 * Run the command with the given options.
	 *
	 * @param array<string, mixed> $options The command options.
	 *
	 * @return CommandTester
	 */
	private function runCommand(array $options = []): CommandTester {
		$tester = new CommandTester(command: new DemoLoadCommand(seeds: $this->seeds, config: $this->config));
		$tester->execute(input: $options, options: ['decorated' => false]);
		return $tester;
	}//end runCommand()

	/**
	 * A report with one created and one updated dashboard.
	 *
	 * @param string $language The language the seed loaded in.
	 *
	 * @return array<string, mixed>
	 */
	private static function report(string $language = 'en'): array {
		return [
			'language'      => $language,
			'createdUsers'  => ['pieter', 'fatima'],
			'createdGroups' => [],
			'catalogue'     => ['created' => 3, 'updated' => 1],
			'announcements' => 4,
			'dashboards'    => [
				['name' => 'Mijn werkdag', 'board' => 'LpWerkplek', 'created' => true, 'placed' => 9, 'uuid' => 'u-1', 'fallbacks' => [], 'skipped' => []],
				[
					'name'      => 'Team',
					'board'     => 'LpTeam',
					'created'   => false,
					'placed'    => 5,
					'uuid'      => 'u-2',
					'fallbacks' => ['calendar'],
					'skipped'   => ['deck', 'talk'],
				],
			],
			'firstRun'      => ['fatima' => 2],
			'files'         => ['error' => 'disk full'],
		];
	}//end report()

	/**
	 * An explicit --lang wins, and the whole report is printed.
	 *
	 * @return void
	 */
	public function testAnExplicitLanguageIsUsedAndTheReportPrinted(): void {
		$this->seeds->expects($this->never())->method('definition');
		$this->seeds->expects($this->once())->method('load')
			->with('zuiddrecht-boards', 'secret', 'en')
			->willReturn(self::report());

		$tester = $this->runCommand(options: ['--password' => 'secret', '--lang' => 'en']);

		$this->assertSame(expected: Command::SUCCESS, actual: $tester->getStatusCode());
		$this->assertSame(
			expected: [
				'Loaded demo seed zuiddrecht-boards (language en).',
				'Users created: pieter, fatima.',
				'Groups created: none.',
				'Catalogue: 3 added, 1 updated.',
				'Announcements: 4.',
				'Mijn werkdag (LpWerkplek) created: 9 widgets, uuid u-1',
				'Team (LpTeam) updated: 5 widgets, uuid u-2',
				'  Placed as fallback: calendar',
				'  Not registered yet, skipped: deck, talk',
				'First visit reset for fatima (2 dashboards removed).',
				'Team folder not created: disk full',
			],
			actual: explode(separator: "\n", string: trim(string: $tester->getDisplay()))
		);
	}//end testAnExplicitLanguageIsUsedAndTheReportPrinted()

	/**
	 * Without --lang the seed's first user's language is used, with the default password.
	 *
	 * @return void
	 */
	public function testWithoutLanguageTheFirstUsersLanguageIsUsed(): void {
		$this->seeds->method('definition')->with('zuiddrecht-boards')->willReturn(['users' => [['id' => 'pieter']]]);
		$this->config->method('getUserValue')->with('pieter', 'core', 'lang', '')->willReturn('de');
		$this->config->expects($this->never())->method('getSystemValueString');
		$this->seeds->expects($this->once())->method('load')
			->with('zuiddrecht-boards', DemoSeedService::DEFAULT_PASSWORD, 'de')
			->willReturn(self::report(language: 'de'));

		$this->assertSame(expected: Command::SUCCESS, actual: $this->runCommand()->getStatusCode());
	}//end testWithoutLanguageTheFirstUsersLanguageIsUsed()

	/**
	 * A first user without a language, or a seed without users, falls back to the server default.
	 *
	 * @return void
	 */
	public function testOtherwiseTheServerDefaultLanguageIsUsed(): void {
		$this->seeds->method('definition')->willReturnOnConsecutiveCalls(['users' => [['id' => 'pieter']]], []);
		$this->config->method('getUserValue')->willReturn('');
		$this->config->method('getSystemValueString')->with('default_language', '')->willReturn('nl');
		$languages = [];
		$this->seeds->method('load')->willReturnCallback(
			callback: static function (string $seedId, string $password, string $language) use (&$languages): array {
				$languages[] = $language;
				return self::report(language: '');
			}
		);

		$first  = $this->runCommand();
		$second = $this->runCommand();

		$this->assertSame(expected: ['nl', 'nl'], actual: $languages);
		$this->assertStringContainsString(needle: 'Loaded demo seed zuiddrecht-boards (as written).', haystack: $first->getDisplay());
		$this->assertSame(expected: Command::SUCCESS, actual: $second->getStatusCode());
	}//end testOtherwiseTheServerDefaultLanguageIsUsed()

	/**
	 * --retire-showcases uninstalls the showcases before loading and names them.
	 *
	 * @return void
	 */
	public function testRetireShowcasesRunsBeforeTheLoad(): void {
		$order = [];
		$this->seeds->method('retireShowcases')->willReturnCallback(
			callback: static function () use (&$order): array {
				$order[] = 'retire';
				return ['intranet', 'kiosk'];
			}
		);
		$this->seeds->method('load')->willReturnCallback(
			callback: static function () use (&$order): array {
				$order[] = 'load';
				return self::report();
			}
		);

		$display = $this->runCommand(options: ['--retire-showcases' => true, '--lang' => 'en'])->getDisplay();

		$this->assertSame(expected: ['retire', 'load'], actual: $order);
		$this->assertStringStartsWith(prefix: 'Showcases uninstalled: intranet, kiosk.', string: $display);
	}//end testRetireShowcasesRunsBeforeTheLoad()

	/**
	 * Without the flag no showcase is touched.
	 *
	 * @return void
	 */
	public function testShowcasesStayWithoutTheFlag(): void {
		$this->seeds->expects($this->never())->method('retireShowcases');
		$this->seeds->method('load')->willReturn(self::report());

		$this->runCommand(options: ['--lang' => 'en']);
	}//end testShowcasesStayWithoutTheFlag()

	/**
	 * A seed that throws ends the command with FAILURE and the message, and prints no report.
	 *
	 * @return void
	 */
	public function testAFailingSeedEndsWithFailure(): void {
		$this->seeds->method('load')->willThrowException(new RuntimeException(message: 'unknown seed nope'));

		$tester = $this->runCommand(options: ['--seed' => 'nope', '--lang' => 'en']);

		$this->assertSame(expected: Command::FAILURE, actual: $tester->getStatusCode());
		$this->assertSame(expected: 'Demo seed failed: unknown seed nope', actual: trim(string: $tester->getDisplay()));
	}//end testAFailingSeedEndsWithFailure()
}//end class
