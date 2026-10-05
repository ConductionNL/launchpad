<?php

/**
 * TemplateInstallCommand
 *
 * `php occ launchpad:template:install <id> [--group=<gid>]... [--default] [--force]`
 * `php occ launchpad:template:install <id> --update [--dry-run]`
 *
 * Adds a template LaunchPad ships (`data/templates/<id>.json`) as an admin
 * template, for scripted deploys. Optionally hands it to groups or makes it
 * the default for everyone. Safe to run twice: a second run adds nothing and
 * applies the options again.
 *
 * With `--update` it brings the installed template to the version LaunchPad
 * ships now, in place, and the members' copies with it. It prints every
 * widget it adds, removes and changes; `--dry-run` prints the same and
 * writes nothing.
 *
 * @category  Command
 * @package   OCA\LaunchPad\Command
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Command;

use InvalidArgumentException;
use OCA\LaunchPad\Exception\TemplateNotInstalledException;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\ShippedTemplateService;
use OCA\LaunchPad\Service\ShippedTemplateUpdateService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * `launchpad:template:install` console command.
 *
 * @spec openspec/specs/cli-commands/spec.md#req-cli-012
 */
class TemplateInstallCommand extends Command {
	/**
	 * Constructor.
	 *
	 * @param ShippedTemplateService       $templates Shipped template service.
	 * @param AdminSettingsService         $settings  Says whether members may own a copy.
	 * @param ShippedTemplateUpdateService $updates   Updates an installed template in place.
	 */
	public function __construct(
		private readonly ShippedTemplateService $templates,
		private readonly AdminSettingsService $settings,
		private readonly ShippedTemplateUpdateService $updates,
	) {
		parent::__construct();
	}//end __construct()

	/**
	 * Configure CLI options.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/cli-commands/spec.md#req-cli-012
	 */
	protected function configure(): void {
		$this->setName(name: 'launchpad:template:install')
			->setDescription(description: 'Add a template that ships with LaunchPad as an admin template.')
			->addArgument(
				name: 'id',
				mode: InputArgument::REQUIRED,
				description: 'Template id (' . implode(separator: ', ', array: ShippedTemplateService::SHIPPED_IDS) . ').'
			)
			->addOption(
				name: 'group',
				shortcut: 'g',
				mode: (InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY),
				description: 'Group whose members get the template. Repeat for more groups.'
			)
			->addOption(
				name: 'default',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Make it the default template for everyone without a group template.'
			)
			->addOption(
				name: 'force',
				shortcut: 'f',
				mode: InputOption::VALUE_NONE,
				description: 'Add a fresh copy even when the template is already installed.'
			)
			->addOption(
				name: 'update',
				shortcut: 'u',
				mode: InputOption::VALUE_NONE,
				description: 'Bring the installed template to the shipped version, in place, and re-sync'
					. ' the copies members already have. Keeps its groups, default flag and name.'
			)
			->addOption(
				name: 'dry-run',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'With --update: print what would change and write nothing.'
			);
	}//end configure()

	/**
	 * Execute the command.
	 *
	 * @param InputInterface  $input  CLI input.
	 * @param OutputInterface $output CLI output.
	 *
	 * @return int Exit code.
	 *
	 * @spec openspec/specs/cli-commands/spec.md#req-cli-012
	 */
	protected function execute(
		InputInterface $input,
		OutputInterface $output,
	): int {
		$id = (string)$input->getArgument(name: 'id');

		if ((bool)$input->getOption(name: 'update') === true) {
			return $this->executeUpdate(input: $input, output: $output, id: $id);
		}

		if ((bool)$input->getOption(name: 'dry-run') === true) {
			$output->writeln(messages: '<error>--dry-run only works together with --update.</error>');
			return self::FAILURE;
		}

		try {
			$result = $this->templates->install(
				templateId: $id,
				targetGroups: array_map(callback: 'strval', array: (array)$input->getOption(name: 'group')),
				makeDefault: (bool)$input->getOption(name: 'default'),
				force: (bool)$input->getOption(name: 'force')
			);
		} catch (InvalidArgumentException $e) {
			$output->writeln(messages: '<error>' . $e->getMessage() . '</error>');
			return self::FAILURE;
		} catch (Throwable $e) {
			$output->writeln(messages: '<error>Installation failed: ' . $e->getMessage() . '</error>');
			return self::FAILURE;
		}

		$verb = 'Installed';
		if ($result['alreadyInstalled'] === true) {
			$verb = 'Already installed:';
		}

		$output->writeln(
			messages: $verb . ' template ' . $id . ' version ' . (string)$result['version']
				. ' (UUID: ' . $result['uuid'] . ').'
		);

		$groups = '(none)';
		if ($result['targetGroups'] !== []) {
			$groups = implode(separator: ', ', array: $result['targetGroups']);
		}

		$default = 'no';
		if ($result['isDefault'] === true) {
			$default = 'yes';
		}

		$output->writeln(messages: 'Groups: ' . $groups . '. Default for everyone: ' . $default . '.');

		if ($result['missingWidgets'] !== []) {
			$output->writeln(
				messages: '<comment>No app on this instance registers these widgets, so they will show empty: '
					. implode(separator: ', ', array: $result['missingWidgets']) . '</comment>'
			);
		}

		// Off means a member is shown the template itself, view only, and its
		// compulsory flags do nothing (REQ-TMPL-019). An administrator rolling
		// out a template needs to know before the members do.
		if (($this->settings->getSettings()['allowUserDashboards'] ?? false) !== true) {
			$output->writeln(
				messages: '<comment>Personal dashboards are off. Members will see this template read-only and its'
					. ' compulsory widgets have no effect. Turn on "allow personal dashboards" in the LaunchPad'
					. ' admin settings to give each member their own copy.</comment>'
			);
		}

		return self::SUCCESS;
	}//end execute()

	/**
	 * `--update`: bring the installed template to the shipped version and
	 * print every change.
	 *
	 * @param InputInterface  $input  CLI input.
	 * @param OutputInterface $output CLI output.
	 * @param string          $id     The shipped template id.
	 *
	 * @return int Exit code.
	 *
	 * @spec openspec/specs/cli-commands/spec.md#req-cli-012
	 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-020
	 */
	private function executeUpdate(InputInterface $input, OutputInterface $output, string $id): int {
		// An update keeps the groups and the default flag. Taking these
		// options along would change them in the same breath, unseen.
		if ($input->getOption(name: 'group') !== []
			|| (bool)$input->getOption(name: 'default') === true
			|| (bool)$input->getOption(name: 'force') === true
		) {
			$output->writeln(
				messages: '<error>--update keeps the groups and the default flag, so it cannot be combined with'
					. ' --group, --default or --force. Run the command without --update to change those.</error>'
			);
			return self::FAILURE;
		}

		try {
			$result = $this->updates->update(templateId: $id, dryRun: (bool)$input->getOption(name: 'dry-run'));
		} catch (InvalidArgumentException | TemplateNotInstalledException $e) {
			$output->writeln(messages: '<error>' . $e->getMessage() . '</error>');
			return self::FAILURE;
		} catch (Throwable $e) {
			$output->writeln(messages: '<error>Update failed: ' . $e->getMessage() . '</error>');
			return self::FAILURE;
		}

		if ($result['upToDate'] === true) {
			$output->writeln(
				messages: 'Template ' . $id . ' is at version ' . (string)$result['installedVersion']
					. ', the version LaunchPad ships. Nothing changed.'
			);
			return self::SUCCESS;
		}

		$verb = 'Updated';
		if ($result['dryRun'] === true) {
			$verb = 'Dry run, nothing written. Would update';
		}

		$output->writeln(
			messages: $verb . ' template ' . $id . ' from version ' . (string)$result['installedVersion']
				. ' to version ' . (string)$result['version'] . ' (UUID: ' . $result['uuid'] . ').'
		);
		$this->writeChanges(output: $output, result: $result);

		return self::SUCCESS;
	}//end executeUpdate()

	/**
	 * Print the widgets an update adds, removes and changes, and what
	 * happens to the members' copies.
	 *
	 * @param OutputInterface      $output CLI output.
	 * @param array<string, mixed> $result The update result.
	 *
	 * @return void
	 */
	private function writeChanges(OutputInterface $output, array $result): void {
		foreach ($result['added'] as $widget) {
			$output->writeln(messages: '  + added:   ' . $widget);
		}

		foreach ($result['removed'] as $widget) {
			$output->writeln(messages: '  - removed: ' . $widget);
		}

		foreach ($result['changed'] as $change) {
			$output->writeln(
				messages: '  ~ changed: ' . $change['widget'] . ' (' . implode(separator: ', ', array: $change['fields']) . ')'
			);
		}

		$output->writeln(messages: '  = unchanged: ' . (string)$result['unchanged'] . ' widgets');

		$copies = (string)$result['copies'];
		if ($result['dryRun'] === true) {
			$output->writeln(
				messages: 'Members with a copy of this template: ' . $copies
					. '. Their copies would follow. A widget a member added stays.'
			);
			return;
		}

		if (($result['resync']['async'] ?? false) === true) {
			$output->writeln(
				messages: 'Members with a copy of this template: ' . $copies
					. '. Their copies are updated in the background. A widget a member added stays.'
			);
			return;
		}

		$output->writeln(
			messages: 'Members with a copy of this template: ' . $copies . '. Copies changed: '
				. (string)($result['resync']['affectedCount'] ?? 0) . '. A widget a member added stays.'
		);
	}//end writeChanges()
}//end class
