<?php

/**
 * DemoShowcasesListCommand
 *
 * `php occ launchpad:demo-showcases:list [--json]`
 *
 * Lists every bundled showcase with its installation status. Supports
 * a `--json` flag for machine-parseable output (REQ-DEMO-009).
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

use OCA\LaunchPad\Service\DemoShowcasesService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `launchpad:demo-showcases:list` console command.
 */
class DemoShowcasesListCommand extends Command {
	/**
	 * Constructor.
	 *
	 * @param DemoShowcasesService $showcases Showcase service.
	 */
	public function __construct(
		private readonly DemoShowcasesService $showcases,
	) {
		parent::__construct();
	}//end __construct()

	/**
	 * Configure CLI options.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/demo-data-showcases/spec.md
	 */
	protected function configure(): void {
		$this->setName(name: 'launchpad:demo-showcases:list')
			->setDescription(description: 'List every bundled LaunchPad demo showcase.')
			->addOption(
				name: 'json',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Emit machine-parseable JSON instead of a table.'
			);
	}//end configure()

	/**
	 * Execute the command.
	 *
	 * @param InputInterface $input CLI input.
	 * @param OutputInterface $output CLI output.
	 *
	 * @return int Exit code.
	 *
	 * @spec openspec/specs/demo-data-showcases/spec.md
	 */
	protected function execute(
		InputInterface $input,
		OutputInterface $output,
	): int {
		$showcases = $this->showcases->getAvailableShowcases();

		if ((bool)$input->getOption(name: 'json') === true) {
			$output->writeln(
				messages: (string)json_encode(
					value: $showcases,
					flags: (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
				)
			);
			return self::SUCCESS;
		}

		$table = new Table(output: $output);
		$table->setHeaders(headers: ['ID', 'Name', 'Language', 'Status', 'Dashboard UUID']);

		foreach ($showcases as $showcase) {
			$status = 'Not installed';
			if ($showcase['isInstalled'] === true) {
				$status = 'Installed';
			}

			$table->addRow(
				row: [
					$showcase['id'],
					$showcase['name'],
					$showcase['language'],
					$status,
					(string)($showcase['installedDashboardUuid'] ?? '-'),
				]
			);
		}

		$table->render();
		return self::SUCCESS;
	}//end execute()
}//end class
