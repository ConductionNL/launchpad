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
 *
 * @spec openspec/specs/setup-wizard/spec.md#requirement-req-wiz-010-cli-command-for-non-interactive-setup-iac-friendly
 */
class SetupCommand extends Command {
	/**
	 * Constructor.
	 *
	 * @param SetupWizardServiceInterface $wizardService Wizard orchestrator.
	 * @param AdminSettingsService        $settings      Group-order persistence
	 *                                                   (Step 2 in the YAML
	 *                                                   schema).
	 * @param SetupSteps                  $steps         Applies Steps 3 to 5: demo
	 *                                                   data, admin role, footer.
	 */
	public function __construct(
		private readonly SetupWizardServiceInterface $wizardService,
		private readonly AdminSettingsService $settings,
		private readonly SetupSteps $steps,
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
		$output->writeln(messages: 'Step 3: Demo data... ' . $this->steps->applyDemoData(config: $config));
		$output->writeln(messages: 'Step 4: Admin roles... ' . $this->steps->applyAdminRole(config: $config));
		$output->writeln(messages: 'Step 5: Footer config... ' . $this->steps->applyFooter(config: $config));

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
}//end class
