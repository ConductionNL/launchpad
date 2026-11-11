<?php

/**
 * SetupCommand Test
 *
 * Pins `occ launchpad:setup` after the storage step was retired
 * (decision 131): the YAML no longer needs `storage_backend`, an old
 * file that still carries it keeps working, and the steps are numbered
 * one to six.
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

namespace OCA\LaunchPad\Tests\Unit\Command;

use OCA\LaunchPad\Command\SetupCommand;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\SetupWizardService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class SetupCommandTest extends TestCase {
	/** @var SetupWizardService&MockObject */
	private $wizard;

	/** @var AdminSettingsService&MockObject */
	private $settings;

	/** @var list<string> */
	private array $files = [];

	protected function setUp(): void {
		parent::setUp();
		$this->wizard = $this->createMock(SetupWizardService::class);
		$this->settings = $this->createMock(AdminSettingsService::class);
	}

	protected function tearDown(): void {
		foreach ($this->files as $file) {
			@unlink($file);
		}
		parent::tearDown();
	}

	private function yaml(string $body): string {
		$file = tempnam(sys_get_temp_dir(), 'lp-setup-');
		$this->assertIsString($file);
		file_put_contents($file, $body);
		$this->files[] = $file;
		return $file;
	}

	private function runSetup(string $body): CommandTester {
		$tester = new CommandTester(new SetupCommand($this->wizard, $this->settings));
		$tester->execute(['--config' => $this->yaml($body)]);
		return $tester;
	}

	public function testAConfigWithoutStorageBackendSucceeds(): void {
		$this->settings->method('getGroupOrder')->willReturn([]);
		$this->settings->expects($this->once())->method('setGroupOrder')->with(['engineering', 'sales']);
		$this->wizard->expects($this->once())->method('markWizardComplete');

		$tester = $this->runSetup("group_priority_order: [\"engineering\", \"sales\"]\n");

		$this->assertSame(0, $tester->getStatusCode());
		$display = $tester->getDisplay();
		$this->assertStringContainsString('Step 1: Welcome... done', $display);
		$this->assertStringContainsString('Step 2: Group order... done', $display);
		$this->assertStringContainsString('Step 3: Demo data... skipped (not in config)', $display);
		$this->assertStringContainsString('Step 4: Admin roles... skipped (not in config)', $display);
		$this->assertStringContainsString('Step 5: Footer config... skipped (not in config)', $display);
		$this->assertStringContainsString('Step 6: Done... done', $display);
		$this->assertStringNotContainsString('Storage backend', $display);
		$this->assertStringContainsString('Setup wizard completed successfully.', $display);
	}

	public function testAnOldConfigWithStorageBackendStillRunsAndSaysTheFieldIsIgnored(): void {
		$this->settings->method('getGroupOrder')->willReturn(['engineering']);
		$this->settings->expects($this->never())->method('setGroupOrder');
		$this->wizard->expects($this->once())->method('markWizardComplete');

		$tester = $this->runSetup("storage_backend: \"groupfolder\"\ngroup_priority_order: [\"engineering\"]\n");

		$this->assertSame(0, $tester->getStatusCode());
		$display = $tester->getDisplay();
		$this->assertStringContainsString("'storage_backend' is no longer used and was ignored", $display);
		$this->assertStringContainsString('Step 2: Group order... already configured, skipping', $display);
	}

	public function testAnEmptyMapStillCompletesTheWizard(): void {
		$this->wizard->expects($this->once())->method('markWizardComplete');

		$tester = $this->runSetup("{}\n");

		$this->assertSame(0, $tester->getStatusCode());
		$this->assertStringContainsString('Step 2: Group order... skipped (not in config)', $tester->getDisplay());
	}

	public function testTheFullOutputWithPendingStepsInTheConfig(): void {
		$this->settings->method('getGroupOrder')->willReturn([]);
		$this->wizard->expects($this->once())->method('markWizardComplete');

		$tester = $this->runSetup("group_priority_order: [\"a\"]\ndemo_packages: [x]\nfooter_config: {}\n");

		$this->assertSame(0, $tester->getStatusCode());
		$this->assertSame(
			"Step 1: Welcome... done\n"
			. "Step 2: Group order... done\n"
			. "Step 3: Demo data... skipped (capability pending)\n"
			. "Step 4: Admin roles... skipped (not in config)\n"
			. "Step 5: Footer config... skipped (capability pending)\n"
			. "Step 6: Done... done\n"
			. "Setup wizard completed successfully.\n",
			$tester->getDisplay()
		);
	}

	public function testRefusedConfigs(): void {
		$this->wizard->expects($this->never())->method('markWizardComplete');
		$this->settings->method('setGroupOrder')->willThrowException(new \RuntimeException('db down'));

		$tester = new CommandTester(new SetupCommand($this->wizard, $this->settings));
		$this->assertSame(1, $tester->execute([]));
		$this->assertSame("--config parameter is required\n", $tester->getDisplay());

		$this->assertSame(1, $tester->execute(['--config' => '/no/such/setup.yaml']));
		$this->assertSame("File not found: /no/such/setup.yaml\n", $tester->getDisplay());

		$this->assertSame(1, $tester->execute(['--config' => $this->yaml("a: [\n")]));
		$this->assertStringStartsWith('Invalid setup.yaml: ', $tester->getDisplay());

		$this->assertSame(1, $tester->execute(['--config' => $this->yaml("just a string\n")]));
		$this->assertSame("Invalid setup.yaml: top-level structure must be a map.\n", $tester->getDisplay());

		$this->assertSame(1, $tester->execute(['--config' => $this->yaml("group_priority_order: nope\n")]));
		$this->assertSame(
			"Step 1: Welcome... done\nInvalid setup.yaml: 'group_priority_order' must be a list of strings\n",
			$tester->getDisplay()
		);

		$this->assertSame(1, $tester->execute(['--config' => $this->yaml("group_priority_order: [b]\n")]));
		$this->assertSame("Step 1: Welcome... done\nSetup failed: db down\n", $tester->getDisplay());
	}
}
