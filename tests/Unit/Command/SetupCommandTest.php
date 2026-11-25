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

use InvalidArgumentException;
use OCA\LaunchPad\Command\SetupCommand;
use OCA\LaunchPad\Command\SetupStepServices;
use OCA\LaunchPad\Db\RoleAssignment;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\DemoShowcasesService;
use OCA\LaunchPad\Service\FooterService;
use OCA\LaunchPad\Service\RoleService;
use OCA\LaunchPad\Service\SetupWizardService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class SetupCommandTest extends TestCase {
	/** @var SetupWizardService&MockObject */
	private $wizard;

	/** @var AdminSettingsService&MockObject */
	private $settings;

	/** @var DemoShowcasesService&MockObject */
	private $showcases;

	/** @var RoleService&MockObject */
	private $roles;

	/** @var FooterService&MockObject */
	private $footer;

	/** @var list<string> */
	private array $files = [];

	protected function setUp(): void {
		parent::setUp();
		$this->wizard = $this->createMock(SetupWizardService::class);
		$this->settings = $this->createMock(AdminSettingsService::class);
		$this->showcases = $this->createMock(DemoShowcasesService::class);
		$this->roles = $this->createMock(RoleService::class);
		$this->footer = $this->createMock(FooterService::class);
	}

	private function command(): SetupCommand {
		return new SetupCommand(
			$this->wizard,
			$this->settings,
			new SetupStepServices($this->showcases, $this->roles, $this->footer)
		);
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
		$tester = new CommandTester($this->command());
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

	/**
	 * A full config applies Steps 2 to 5 through the wizard's own services.
	 *
	 * @spec openspec/changes/e2e-coverage-programme/tasks.md#task-3.5
	 */
	public function testAFullConfigAppliesEveryStep(): void {
		$this->settings->method('getGroupOrder')->willReturn([]);
		$this->showcases->expects($this->exactly(2))->method('installShowcase')
			->willReturnCallback(fn (string $showcaseId): array => [
				'installedDashboardUuid' => 'uuid-' . $showcaseId,
				'skippedWidgets' => [],
				'alreadyInstalled' => false,
			]);
		$this->roles->method('hasGroupRole')->with('engineering', RoleAssignment::ROLE_ADMIN)->willReturn(false);
		$this->roles->expects($this->once())->method('assignRole')
			->with(null, 'engineering', RoleAssignment::ROLE_ADMIN, SetupCommand::ASSIGNED_BY);
		$this->footer->method('getGlobalSettings')->willReturn(['footerConfig' => []]);
		$this->footer->expects($this->once())->method('updateGlobalSettings')
			->with(['footerConfig' => ['organisation' => 'Gemeente', 'layoutMode' => 'columns']]);
		$this->wizard->expects($this->once())->method('markWizardComplete');

		$tester = $this->runSetup(
			"group_priority_order: [\"a\"]\n"
			. "demo_packages: [case-handler, de-bron]\n"
			. "admin_role_group: engineering\n"
			. "footer_config:\n  organisation: Gemeente\n  layoutMode: columns\n"
		);

		$this->assertSame(0, $tester->getStatusCode());
		$this->assertSame(
			"Step 1: Welcome... done\n"
			. "Step 2: Group order... done\n"
			. "Step 3: Demo data... done\n"
			. "Step 4: Admin roles... done\n"
			. "Step 5: Footer config... done\n"
			. "Step 6: Done... done\n"
			. "Setup wizard completed successfully.\n",
			$tester->getDisplay()
		);
	}

	/**
	 * A second run with the same config installs, assigns and stores nothing.
	 *
	 * @spec openspec/changes/e2e-coverage-programme/tasks.md#task-3.5
	 */
	public function testARerunSkipsWhatIsAlreadyConfigured(): void {
		$this->settings->method('getGroupOrder')->willReturn(['a']);
		$this->showcases->method('installShowcase')->willReturn([
			'installedDashboardUuid' => 'uuid',
			'skippedWidgets' => [],
			'alreadyInstalled' => true,
		]);
		$this->roles->method('hasGroupRole')->willReturn(true);
		$this->roles->expects($this->never())->method('assignRole');
		$this->footer->method('getGlobalSettings')->willReturn(['footerConfig' => ['organisation' => 'Gemeente']]);
		$this->footer->expects($this->never())->method('updateGlobalSettings');
		$this->wizard->expects($this->once())->method('markWizardComplete');

		$tester = $this->runSetup(
			"group_priority_order: [a]\ndemo_packages: [case-handler]\nadmin_role_group: engineering\n"
			. "footer_config:\n  organisation: Gemeente\n"
		);

		$this->assertSame(0, $tester->getStatusCode());
		$display = $tester->getDisplay();
		$this->assertStringContainsString('Step 3: Demo data... already configured, skipping', $display);
		$this->assertStringContainsString('Step 4: Admin roles... already configured, skipping', $display);
		$this->assertStringContainsString('Step 5: Footer config... already configured, skipping', $display);
	}

	/**
	 * Steps 3 to 5 refuse values of the wrong shape, and a footer the
	 * footer service refuses, with "Invalid setup.yaml:" and exit 1.
	 *
	 * @spec openspec/changes/e2e-coverage-programme/tasks.md#task-3.5
	 */
	public function testStepsThreeToFiveRefuseInvalidValues(): void {
		$this->wizard->expects($this->never())->method('markWizardComplete');
		$this->footer->method('getGlobalSettings')->willReturn(['footerConfig' => []]);
		$this->footer->method('updateGlobalSettings')
			->willThrowException(new InvalidArgumentException('Unknown footer config key: colour'));

		$cases = [
			"demo_packages: case-handler\n" => "Invalid setup.yaml: 'demo_packages' must be a list of strings",
			"demo_packages: [[a]]\n" => "Invalid setup.yaml: 'demo_packages' must be a list of strings",
			"admin_role_group: [a]\n" => "Invalid setup.yaml: 'admin_role_group' must be a group id",
			"admin_role_group: ''\n" => "Invalid setup.yaml: 'admin_role_group' must be a group id",
			"footer_config: [a]\n" => "Invalid setup.yaml: 'footer_config' must be a map",
			"footer_config:\n  colour: red\n" => "Invalid setup.yaml: 'footer_config': Unknown footer config key: colour",
		];
		foreach ($cases as $yaml => $error) {
			$tester = $this->runSetup($yaml);
			$this->assertSame(1, $tester->getStatusCode(), $yaml);
			$this->assertStringContainsString($error, $tester->getDisplay(), $yaml);
		}
	}

	public function testRefusedConfigs(): void {
		$this->wizard->expects($this->never())->method('markWizardComplete');
		$this->settings->method('setGroupOrder')->willThrowException(new \RuntimeException('db down'));

		$tester = new CommandTester($this->command());
		$this->assertSame(1, $tester->execute([]));
		$this->assertSame("--config parameter is required\n", $tester->getDisplay());

		$this->assertSame(1, $tester->execute(['--config' => '/no/such/setup.yaml']));
		$this->assertSame("File not found: /no/such/setup.yaml\n", $tester->getDisplay());

		$this->assertSame(1, $tester->execute(['--config' => $this->yaml("a: [\n")]));
		$this->assertStringStartsWith('Invalid setup.yaml: ', $tester->getDisplay());

		$this->assertSame(1, $tester->execute(['--config' => $this->yaml("just a string\n")]));
		$this->assertSame("Invalid setup.yaml: top-level structure must be a map.\n", $tester->getDisplay());

		// A YAML list at the top level is not a map either.
		$this->assertSame(expected: 1, actual: $tester->execute(input: ['--config' => $this->yaml(body: "- a\n- b\n")]));
		$this->assertSame(expected: "Invalid setup.yaml: top-level structure must be a map.\n", actual: $tester->getDisplay());

		// A map where the group order must be a list.
		$this->assertSame(expected: 1, actual: $tester->execute(input: ['--config' => $this->yaml(body: "group_priority_order:\n  first: a\n")]));
		$this->assertSame(
			expected: "Step 1: Welcome... done\nInvalid setup.yaml: 'group_priority_order' must be a list of strings\n",
			actual: $tester->getDisplay()
		);

		$this->assertSame(1, $tester->execute(['--config' => $this->yaml("group_priority_order: nope\n")]));
		$this->assertSame(
			"Step 1: Welcome... done\nInvalid setup.yaml: 'group_priority_order' must be a list of strings\n",
			$tester->getDisplay()
		);

		$this->assertSame(1, $tester->execute(['--config' => $this->yaml("group_priority_order: [b]\n")]));
		$this->assertSame("Step 1: Welcome... done\nSetup failed: db down\n", $tester->getDisplay());
	}
}
