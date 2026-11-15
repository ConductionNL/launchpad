<?php

/**
 * Command audit trail test
 *
 * Runs a real launchpad command and pins the audit line it leaves:
 * the command name without its app prefix, the argv tail, the exit code
 * and the calling user (REQ-CLI-010).
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

use OCA\LaunchPad\Command\I18nExportStringsCommand;
use OCA\LaunchPad\Service\CommandService;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The audit line of one command run.
 */
class CommandAuditTrailTest extends TestCase {

	/**
	 * A run is audited under its own name, with the argv tail, exit code and user.
	 *
	 * @return void
	 */
	public function testARunIsAuditedUnderItsOwnName(): void {
		$logged = [];
		$logger = $this->createMock(LoggerInterface::class);
		$logger->method('info')->willReturnCallback(
			static function (string $message, array $context=[]) use (&$logged): void {
				$logged[] = [$message, $context];
			}
		);
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('admin');
		$session = $this->createMock(IUserSession::class);
		$session->method('getUser')->willReturn($user);

		$argv = $_SERVER['argv'] ?? null;
		$_SERVER['argv'] = ['occ', 'launchpad:i18n:export-strings', '--dry', 'x y'];
		try {
			$root = sys_get_temp_dir().'/launchpad-audit-'.bin2hex(random_bytes(4));
			mkdir($root.'/appinfo', 0777, true);
			file_put_contents($root.'/appinfo/signature.json', '{"hashes":{}}');
			$tester = new CommandTester(new I18nExportStringsCommand(new CommandService($logger), $session, $root));
			$tester->execute([]);
			exec('rm -rf '.escapeshellarg($root));
		} finally {
			$_SERVER['argv'] = $argv;
		}

		$this->assertCount(1, $logged);
		[$line, $context] = $logged[0];
		$this->assertMatchesRegularExpression(
			'/^\[launchpad\] cli i18n:export-strings --dry x y exitCode='.CommandService::EXIT_INVALID_ARGS.' durationMs=\d+ byUser=admin$/',
			$line
		);
		$this->assertSame(['app' => 'launchpad', 'command' => 'i18n:export-strings', 'exitCode' => CommandService::EXIT_INVALID_ARGS, 'byUser' => 'admin'], array_diff_key($context, ['durationMs' => true]));
	}//end testARunIsAuditedUnderItsOwnName()
}//end class
