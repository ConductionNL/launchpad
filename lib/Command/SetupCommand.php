<?php

/**
 * SetupCommand
 *
 * `php occ launchpad:setup --config=/path/setup.yaml` — non-interactive
 * IaC-friendly entry point for the setup wizard (REQ-WIZ-010). Walks
 * the same step sequence as the Vue modal but reads every choice from
 * a YAML config file: Step 2 stores the group order, Step 3 installs the
 * listed demo showcases, Step 4 gives one group the Dashboard Admin role
 * and Step 5 stores the structured footer config. Idempotent: re-running
 * with the same config detects existing values and skips them.
 *
 * @category  Command
 * @package   OCA\LaunchPad\Command
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Command;

use InvalidArgumentException;
use OCA\LaunchPad\Db\RoleAssignment;
use OCA\LaunchPad\Kernel\Contracts\SetupWizardServiceInterface;
use OCA\LaunchPad\Service\AdminSettingsService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * `launchpad:setup` console command.
 */
class SetupCommand extends Command {
	/**
	 * Who a role row written by this command names as its assigner.
	 *
	 * @var string
	 */
	public const ASSIGNED_BY = 'occ';

	/**
	 * Constructor.
	 *
	 * @param SetupWizardServiceInterface $wizardService Wizard orchestrator.
	 * @param AdminSettingsService        $settings      Group-order persistence
	 *                                                   (Step 2 in the YAML
	 *                                                   schema).
	 * @param SetupStepServices           $steps         The demo, role and footer
	 *                                                   services of Steps 3 to 5.
	 */
	public function __construct(
		private readonly SetupWizardServiceInterface $wizardService,
		private readonly AdminSettingsService $settings,
		private readonly SetupStepServices $steps,
	) {
		parent::__construct();
	}//end __construct()

	/**
	 * Configure CLI options.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/setup-wizard/spec.md
	 */
	protected function configure(): void {
		$this->setName(name: 'launchpad:setup')
			->setDescription(description: 'Run the LaunchPad setup wizard from a YAML config.')
			->setHelp(help: 'Run the LaunchPad setup wizard non-interactively from a YAML config (REQ-WIZ-010).')
			->addOption(
				name: 'config',
				shortcut: 'c',
				mode: InputOption::VALUE_REQUIRED,
				description: 'Path to the YAML config file describing every step.'
			);
	}//end configure()

	/**
	 * Execute the wizard from a YAML file.
	 *
	 * @param InputInterface $input CLI input.
	 * @param OutputInterface $output CLI output.
	 *
	 * @return int Exit code (0 success, 1 error).
	 *
	 * @spec openspec/specs/setup-wizard/spec.md
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$config = $this->readConfig(configPath: (string)($input->getOption(name: 'config') ?? ''), output: $output);
		if ($config === null) {
			return self::FAILURE;
		}

		// Decision 131 retired the storage step. A file written for the
		// seven-step wizard still runs; its storage_backend is not applied.
		if (array_key_exists(key: 'storage_backend', array: $config) === true) {
			$output->writeln(
				messages: "<comment>'storage_backend' is no longer used and was ignored.</comment>"
			);
		}

		if ($this->runSteps(config: $config, output: $output) === false) {
			return self::FAILURE;
		}

		$this->wizardService->markWizardComplete();
		$output->writeln(messages: 'Setup wizard completed successfully.');
		return self::SUCCESS;
	}//end execute()

	/**
	 * Read the setup file; print why and answer null when it is missing,
	 * not valid YAML or not a map.
	 *
	 * @param string          $configPath The `--config` value.
	 * @param OutputInterface $output     CLI output.
	 *
	 * @return array<string, mixed>|null The parsed config, or null.
	 */
	private function readConfig(string $configPath, OutputInterface $output): array|null {
		$pathError = (new FileOptionCheck())->error(option: 'config', path: $configPath);
		if ($pathError !== null) {
			$output->writeln(messages: $pathError);
			return null;
		}

		try {
			$config = Yaml::parseFile(filename: $configPath);
		} catch (ParseException $e) {
			$output->writeln(
				messages: '<error>Invalid setup.yaml: ' . $e->getMessage() . '</error>'
			);
			return null;
		}

		// A YAML list parses to a PHP list: that is not a map either (REQ-SETUP CLI schema).
		if (is_array($config) === false || ($config !== [] && array_is_list(array: $config) === true)) {
			$output->writeln(
				messages: '<error>Invalid setup.yaml: top-level structure must be a map.</error>'
			);
			return null;
		}

		return $config;
	}//end readConfig()

	/**
	 * Apply the steps; print the reason and answer false when one fails.
	 *
	 * @param array<string,mixed> $config Parsed YAML config.
	 * @param OutputInterface     $output CLI output.
	 *
	 * @return bool True when every step applied.
	 */
	private function runSteps(array $config, OutputInterface $output): bool {
		try {
			$this->applySteps(config: $config, output: $output);
		} catch (InvalidArgumentException $e) {
			$output->writeln(messages: '<error>' . $e->getMessage() . '</error>');
			return false;
		} catch (Throwable $e) {
			$output->writeln(
				messages: '<error>Setup failed: ' . $e->getMessage() . '</error>'
			);
			return false;
		}

		return true;
	}//end runSteps()

	/**
	 * Apply each non-Welcome step in order, logging progress + idempotency.
	 *
	 * @param array<string,mixed> $config Parsed YAML config.
	 * @param OutputInterface $output CLI output for progress logging.
	 *
	 * @return void
	 */
	private function applySteps(array $config, OutputInterface $output): void {
		$output->writeln(messages: 'Step 1: Welcome... done');

		$this->applyGroupOrderStep(config: $config, output: $output);
		$this->applyDemoStep(config: $config, output: $output);
		$this->applyAdminRoleStep(config: $config, output: $output);
		$this->applyFooterStep(config: $config, output: $output);

		$output->writeln(messages: 'Step 6: Done... done');
	}//end applySteps()

	/**
	 * Apply Step 2 — group priority order (REQ-WIZ-004).
	 *
	 * @param array<string,mixed> $config Parsed YAML.
	 * @param OutputInterface $output CLI output.
	 *
	 * @return void
	 */
	private function applyGroupOrderStep(
		array $config,
		OutputInterface $output,
	): void {
		if (array_key_exists(key: 'group_priority_order', array: $config) === false) {
			$output->writeln(messages: 'Step 2: Group order... skipped (not in config)');
			return;
		}

		$groups = $config['group_priority_order'];
		if (is_array($groups) === false || array_is_list(array: $groups) === false) {
			throw new InvalidArgumentException(
				message: "Invalid setup.yaml: 'group_priority_order' must be a list of strings"
			);
		}

		$current = $this->settings->getGroupOrder();
		if ($current === array_values(array: $groups)) {
			$output->writeln(
				messages: 'Step 2: Group order... already configured, skipping'
			);
			return;
		}

		$this->settings->setGroupOrder(groupIds: $groups);
		$output->writeln(messages: 'Step 2: Group order... done');
	}//end applyGroupOrderStep()

	/**
	 * Apply Step 3 — install the listed demo showcases (REQ-WIZ-005,
	 * REQ-WIZ-010). A showcase that is already installed is not installed
	 * again.
	 *
	 * @param array<string,mixed> $config Parsed YAML.
	 * @param OutputInterface     $output CLI output.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When `demo_packages` is not a list of strings.
	 *
	 * @spec openspec/changes/e2e-coverage-programme/tasks.md#task-3.5
	 */
	private function applyDemoStep(array $config, OutputInterface $output): void {
		if (array_key_exists(key: 'demo_packages', array: $config) === false) {
			$output->writeln(messages: 'Step 3: Demo data... skipped (not in config)');
			return;
		}

		$showcaseIds = self::stringList(value: $config['demo_packages'], key: 'demo_packages');
		$installed   = 0;
		foreach ($showcaseIds as $showcaseId) {
			$result = $this->steps->showcases->installShowcase(showcaseId: $showcaseId);
			if ($result['alreadyInstalled'] === false) {
				$installed++;
			}
		}

		if ($installed === 0) {
			$output->writeln(messages: 'Step 3: Demo data... already configured, skipping');
			return;
		}

		$output->writeln(messages: 'Step 3: Demo data... done');
	}//end applyDemoStep()

	/**
	 * Apply Step 4 — give one group the Dashboard Admin role (REQ-WIZ-006,
	 * REQ-WIZ-010). A group that already holds it gets no second row.
	 *
	 * @param array<string,mixed> $config Parsed YAML.
	 * @param OutputInterface     $output CLI output.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When `admin_role_group` is not a non-empty string.
	 *
	 * @spec openspec/changes/e2e-coverage-programme/tasks.md#task-3.5
	 */
	private function applyAdminRoleStep(array $config, OutputInterface $output): void {
		if (array_key_exists(key: 'admin_role_group', array: $config) === false) {
			$output->writeln(messages: 'Step 4: Admin roles... skipped (not in config)');
			return;
		}

		$groupId = $config['admin_role_group'];
		if (is_string($groupId) === false || trim(string: $groupId) === '') {
			throw new InvalidArgumentException(
				message: "Invalid setup.yaml: 'admin_role_group' must be a group id"
			);
		}

		if ($this->steps->roles->hasGroupRole(groupId: $groupId, role: RoleAssignment::ROLE_ADMIN) === true) {
			$output->writeln(messages: 'Step 4: Admin roles... already configured, skipping');
			return;
		}

		$this->steps->roles->assignRole(
			userId: null,
			groupId: $groupId,
			role: RoleAssignment::ROLE_ADMIN,
			assignedBy: self::ASSIGNED_BY
		);
		$output->writeln(messages: 'Step 4: Admin roles... done');
	}//end applyAdminRoleStep()

	/**
	 * Apply Step 5 — store the structured footer config (REQ-WIZ-007,
	 * REQ-WIZ-010), validated as the admin page's save validates it.
	 *
	 * @param array<string,mixed> $config Parsed YAML.
	 * @param OutputInterface     $output CLI output.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When `footer_config` is not a map or fails validation.
	 *
	 * @spec openspec/changes/e2e-coverage-programme/tasks.md#task-3.5
	 */
	private function applyFooterStep(array $config, OutputInterface $output): void {
		if (array_key_exists(key: 'footer_config', array: $config) === false) {
			$output->writeln(messages: 'Step 5: Footer config... skipped (not in config)');
			return;
		}

		$footer = $config['footer_config'];
		if (is_array($footer) === false || ($footer !== [] && array_is_list(array: $footer) === true)) {
			throw new InvalidArgumentException(
				message: "Invalid setup.yaml: 'footer_config' must be a map"
			);
		}

		$current = ($this->steps->footer->getGlobalSettings()['footerConfig'] ?? null);
		if ($current === $footer) {
			$output->writeln(messages: 'Step 5: Footer config... already configured, skipping');
			return;
		}

		try {
			$this->steps->footer->updateGlobalSettings(patch: ['footerConfig' => $footer]);
		} catch (InvalidArgumentException $e) {
			throw new InvalidArgumentException(
				message: "Invalid setup.yaml: 'footer_config': " . $e->getMessage(),
				previous: $e
			);
		}

		$output->writeln(messages: 'Step 5: Footer config... done');
	}//end applyFooterStep()

	/**
	 * The value of a YAML key that must be a list of non-empty strings.
	 *
	 * @param mixed  $value The parsed value.
	 * @param string $key   The YAML key, for the error.
	 *
	 * @return list<string> The strings.
	 *
	 * @throws InvalidArgumentException When the value is not such a list.
	 */
	private static function stringList(mixed $value, string $key): array {
		$error = "Invalid setup.yaml: '" . $key . "' must be a list of strings";
		if (is_array($value) === false || array_is_list(array: $value) === false) {
			throw new InvalidArgumentException(message: $error);
		}

		foreach ($value as $item) {
			if (is_string($item) === false || $item === '') {
				throw new InvalidArgumentException(message: $error);
			}
		}

		return $value;
	}//end stringList()
}//end class
