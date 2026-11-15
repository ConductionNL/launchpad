<?php

/**
 * Demo showcases commands golden test
 *
 * Pins what `launchpad:demo:showcases:install` and the showcase list
 * command print and return: unknown showcase, failed install, already
 * installed, installed with skipped widgets, the table and `--json`.
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

use OCA\LaunchPad\Command\DemoShowcasesInstallCommand;
use OCA\LaunchPad\Command\DemoShowcasesListCommand;
use OCA\LaunchPad\Exception\ShowcaseNotFoundException;
use OCA\LaunchPad\Service\DemoShowcasesService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The printed output of the two showcase commands, case by case.
 */
class DemoShowcasesCommandsGoldenTest extends TestCase {

	public function testInstallOutput(): void {
		$service = $this->createMock(DemoShowcasesService::class);
		$service->method('installShowcase')->willReturnCallback(static function (string $showcaseId, string $lang, bool $force): array {
			if ($showcaseId === 'nope') {
				throw new ShowcaseNotFoundException(message: 'gone');
			}

			if ($showcaseId === 'broken') {
				throw new \RuntimeException('disk');
			}

			return [
				'installedDashboardUuid' => 'u-' . $lang,
				'skippedWidgets' => ($force === true) ? ['w1', 'w2'] : [],
				'alreadyInstalled' => ($showcaseId === 'old'),
			];
		});
		$tester = new CommandTester(new DemoShowcasesInstallCommand($service));

		$outputs = [];
		foreach ([['id' => 'nope'], ['id' => 'broken'], ['id' => 'old'], ['id' => 'new', '--lang' => 'en', '--force' => true], ['id' => 'new']] as $input) {
			$code = $tester->execute($input);
			$outputs[] = [$code, $tester->getDisplay()];
		}

		$this->assertSame(
			[
				[1, "Showcase not found: nope\n"],
				[1, "Installation failed: disk\n"],
				[0, "Showcase old is already installed (UUID: u-nl).\nUse --force to reinstall.\n"],
				[0, "Installed dashboard u-en\nSkipped unknown widgets: w1, w2\n"],
				[0, "Installed dashboard u-nl\n"],
			],
			$outputs
		);
	}

	public function testListOutput(): void {
		$service = $this->createMock(DemoShowcasesService::class);
		$service->method('getAvailableShowcases')->willReturn([
			['id' => 'de-bron', 'name' => 'De Bron', 'language' => 'nl', 'isInstalled' => true, 'installedDashboardUuid' => 'u1'],
			['id' => 'harbour', 'name' => 'Harbour', 'language' => 'en', 'isInstalled' => false, 'installedDashboardUuid' => null],
		]);
		$tester = new CommandTester(new DemoShowcasesListCommand($service));

		$this->assertSame(0, $tester->execute([]));
		$table = $tester->getDisplay();
		$this->assertStringContainsString('| de-bron | De Bron | nl       | Installed     | u1             |', $table);
		$this->assertStringContainsString('| harbour | Harbour | en       | Not installed | -              |', $table);

		$this->assertSame(0, $tester->execute(['--json' => true]));
		$this->assertStringStartsWith("[\n    {\n        \"id\": \"de-bron\",", $tester->getDisplay());
	}
}
