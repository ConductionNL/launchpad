<?php

/**
 * I18nExportStringsCommand tests: a signed release is never written to.
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/cli-commands/spec.md#requirement-req-cli-005-new-commands-i18n-management
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Tests\Unit\Command;

use OCA\LaunchPad\Command\I18nExportStringsCommand;
use OCA\LaunchPad\Service\CommandService;
use OCP\IUserSession;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Nextcloud checks every file of a signed release, so a POT file written into
 * one shows as a code integrity warning (EXTRA_FILE).
 */
class I18nExportStringsCommandTest extends TestCase {

	private string $root;

	protected function setUp(): void {
		parent::setUp();
		$this->root = sys_get_temp_dir() . '/launchpad-pot-' . bin2hex(random_bytes(4));
		mkdir($this->root . '/lib', 0777, true);
		mkdir($this->root . '/appinfo');
		file_put_contents($this->root . '/lib/Thing.php', "<?php \$l->t('Add widget'); \$l->t('Remove widget');\n");
	}

	protected function tearDown(): void {
		exec('rm -rf ' . escapeshellarg($this->root));
		parent::tearDown();
	}

	private function runCommand(array $input): CommandTester {
		$command = new I18nExportStringsCommand(
			new CommandService($this->createMock(LoggerInterface::class)),
			$this->createMock(IUserSession::class),
			$this->root
		);
		$tester = new CommandTester($command);
		$tester->execute($input);

		return $tester;
	}

	/** Every file under the app root, relative. */
	private function files(): array {
		$out = [];
		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS));
		foreach ($iterator as $file) {
			$out[] = substr($file->getPathname(), strlen($this->root) + 1);
		}

		sort($out);

		return $out;
	}

	public function testACheckoutStillWritesTheDefaultPot(): void {
		$tester = $this->runCommand([]);

		$this->assertSame(0, $tester->getStatusCode());
		$this->assertStringContainsString('msgid "Add widget"', (string)file_get_contents($this->root . '/l10n/launchpad.pot'));
	}

	public function testASignedReleaseIsNotWrittenTo(): void {
		file_put_contents($this->root . '/appinfo/signature.json', '{"hashes":{}}');
		$before = $this->files();

		$tester = $this->runCommand([]);

		$this->assertSame(CommandService::EXIT_INVALID_ARGS, $tester->getStatusCode());
		$this->assertStringContainsString('--output', $tester->getDisplay());
		$this->assertSame($before, $this->files(), 'no file added to the signed release');
	}

	public function testASignedReleaseRefusesAnOutputInsideItself(): void {
		file_put_contents($this->root . '/appinfo/signature.json', '{"hashes":{}}');
		$before = $this->files();

		$tester = $this->runCommand(['--output' => $this->root . '/l10n/elsewhere.pot']);

		$this->assertSame(CommandService::EXIT_INVALID_ARGS, $tester->getStatusCode());
		$this->assertSame($before, $this->files());
	}

	public function testASignedReleaseWritesToAPathOutsideItself(): void {
		file_put_contents($this->root . '/appinfo/signature.json', '{"hashes":{}}');
		$target = sys_get_temp_dir() . '/launchpad-pot-out-' . bin2hex(random_bytes(4)) . '.pot';

		$tester = $this->runCommand(['--output' => $target]);
		$pot = (string)@file_get_contents($target);
		@unlink($target);

		$this->assertSame(0, $tester->getStatusCode());
		$this->assertStringContainsString('msgid "Remove widget"', $pot);
	}

	public function testADashPrintsThePot(): void {
		file_put_contents($this->root . '/appinfo/signature.json', '{"hashes":{}}');
		$before = $this->files();

		$tester = $this->runCommand(['--output' => '-']);

		$this->assertSame(0, $tester->getStatusCode());
		$this->assertStringContainsString('msgid "Add widget"', $tester->getDisplay());
		$this->assertSame($before, $this->files());
	}
}
