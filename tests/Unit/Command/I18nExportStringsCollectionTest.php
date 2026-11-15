<?php

/**
 * I18nExportStringsCollectionTest
 *
 * Pins which files `launchpad:i18n:export-strings` reads (PHP, Vue, JS and
 * TS under lib/ and src/, nothing else) and its error when the app root
 * cannot be resolved.
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

use OCA\LaunchPad\Command\I18nExportStringsCommand;
use OCA\LaunchPad\Service\CommandService;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * File selection and root resolution of the string export command.
 *
 * @covers \OCA\LaunchPad\Command\I18nExportStringsCommand
 * @uses   \OCA\LaunchPad\Command\CommandBase
 * @uses   \OCA\LaunchPad\Command\FileOptionCheck
 * @uses   \OCA\LaunchPad\Service\CommandService
 */
class I18nExportStringsCollectionTest extends TestCase {

	private string $root;

	/**
	 * A fresh app root.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->root = sys_get_temp_dir() . '/launchpad-strings-' . bin2hex(random_bytes(4));
		mkdir($this->root, 0777, true);
	}//end setUp()

	/**
	 * Remove the app root.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		exec('rm -rf ' . escapeshellarg($this->root));
		parent::tearDown();
	}//end tearDown()

	/**
	 * Run the command against an app root.
	 *
	 * @param string               $root  The app root.
	 * @param array<string, mixed> $input The input.
	 *
	 * @return CommandTester
	 */
	private function runCommand(string $root, array $input): CommandTester {
		$tester = new CommandTester(
			new I18nExportStringsCommand(
				commandService: new CommandService($this->createMock(LoggerInterface::class)),
				userSession: $this->createMock(IUserSession::class),
				appRoot: $root
			)
		);
		$tester->execute($input);
		return $tester;
	}//end runCommand()

	/**
	 * Write a file under the app root.
	 *
	 * @param string $relPath  Relative path.
	 * @param string $contents Contents.
	 *
	 * @return void
	 */
	private function put(string $relPath, string $contents): void {
		$path = $this->root . '/' . $relPath;
		if (is_dir(dirname($path)) === false) {
			mkdir(dirname($path), 0777, true);
		}

		file_put_contents($path, $contents);
	}//end put()

	/**
	 * Only source files under lib/ and src/ are read; other files, other
	 * folders and empty folders are ignored; strings are sorted and unique.
	 *
	 * @return void
	 */
	public function testOnlySourceFilesUnderLibAndSrcAreRead(): void {
		$this->put('lib/A.php', "<?php \$l->t('Zebra'); \$l->t('Apple');");
		$this->put('src/b.vue', "<template>{{ t('Banana') }}</template>");
		$this->put('src/deep/c.JS', "n('Cherry', 'Cherries', 2)");
		$this->put('src/d.ts', "t('Apple')");
		$this->put('src/notes.md', "t('Markdown')");
		$this->put('src/data.json', "{\"x\": \"t('Json')\"}");
		$this->put('templates/e.php', "<?php \$l->t('Template');");
		mkdir($this->root . '/src/empty', 0777, true);

		$tester = $this->runCommand(root: $this->root, input: ['--output' => '-']);

		$this->assertSame(0, $tester->getStatusCode());
		preg_match_all('/^msgid "(.+)"$/m', $tester->getDisplay(), $matches);
		$this->assertSame(['Apple', 'Banana', 'Cherry', 'Zebra'], $matches[1]);
	}//end testOnlySourceFilesUnderLibAndSrcAreRead()

	/**
	 * A backslash in a string is escaped in the POT.
	 *
	 * @return void
	 */
	public function testBackslashesAreEscaped(): void {
		$this->put('lib/A.php', "<?php \$l->t('Path a\\\\b');");

		$display = $this->runCommand(root: $this->root, input: ['--output' => '-'])->getDisplay();

		$this->assertStringContainsString("msgid \"Path a\\\\\\\\b\"\n", $display);
	}//end testBackslashesAreEscaped()

	/**
	 * An app root that does not exist is an internal error, reported as an envelope.
	 *
	 * @return void
	 */
	public function testAnUnresolvableRootIsAnError(): void {
		$tester = $this->runCommand(root: $this->root . '/missing', input: ['--json' => true, '--output' => '-']);

		$this->assertSame(CommandService::EXIT_ERROR, $tester->getStatusCode());
		$this->assertSame(
			[
				'success' => false,
				'exitCode' => 1,
				'data' => null,
				'errors' => [['code' => 'INTERNAL_ERROR', 'message' => 'Could not resolve app root directory.']],
			],
			json_decode(trim($tester->getDisplay()), true)
		);
	}//end testAnUnresolvableRootIsAnError()
}//end class
