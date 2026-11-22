<?php

/**
 * DemoLoadCommand
 *
 * `occ launchpad:demo:load`: load the LaunchPad demo environment
 * (users, groups, app catalogue, announcements, team folder and the
 * dashboards of every LaunchPad board). Safe to run again: a second run
 * leaves the same state as the first.
 *
 * @category  Command
 * @package   OCA\LaunchPad\Command
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 * @spec      openspec/specs/demo-data-showcases/spec.md
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Command;

use OCA\LaunchPad\Service\DemoSeedService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Load the LaunchPad demo seed.
 *
 * @spec openspec/specs/demo-data-showcases/spec.md
 */
class DemoLoadCommand extends Command {
	/**
	 * Constructor.
	 *
	 * @param DemoSeedService $seeds The demo seed loader.
	 */
	public function __construct(
		private readonly DemoSeedService $seeds,
	) {
		parent::__construct();
	}//end __construct()

	/**
	 * Configure the command.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	protected function configure(): void {
		$this->setName(name: 'launchpad:demo:load')
			->setDescription(description: 'Load the LaunchPad demo environment: demo users, groups and the dashboards of every board.')
			->addOption(
				name: 'seed',
				shortcut: null,
				mode: InputOption::VALUE_REQUIRED,
				description: 'Seed id: ' . implode(separator: ', ', array: DemoSeedService::SEED_IDS) . '.',
				default: DemoSeedService::SEED_IDS[0]
			)
			->addOption(
				name: 'password',
				shortcut: null,
				mode: InputOption::VALUE_REQUIRED,
				description: 'Password for demo users this run creates. Existing users keep theirs.',
				default: DemoSeedService::DEFAULT_PASSWORD
			)
			->addOption(
				name: 'retire-showcases',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Uninstall the bundled showcases first. They are visible to everyone,'
					. ' so while one is installed no user sees the first-visit empty state.'
			);
	}//end configure()

	/**
	 * Execute the command.
	 *
	 * @param InputInterface  $input  The input.
	 * @param OutputInterface $output The output.
	 *
	 * @return integer The exit code.
	 *
	 * @spec openspec/changes/launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$seedId = (string)$input->getOption(name: 'seed');

		try {
			if ((bool)$input->getOption(name: 'retire-showcases') === true) {
				$retired = $this->seeds->retireShowcases();
				$output->writeln(messages: 'Showcases uninstalled: ' . self::listOrNone(items: $retired) . '.');
			}

			$report = $this->seeds->load(seedId: $seedId, password: (string)$input->getOption(name: 'password'));
		} catch (Throwable $e) {
			$output->writeln(messages: '<error>Demo seed failed: ' . $e->getMessage() . '</error>');
			return self::FAILURE;
		}

		foreach (self::reportLines(seedId: $seedId, report: $report) as $line) {
			$output->writeln(messages: $line);
		}

		return self::SUCCESS;
	}//end execute()

	/**
	 * The lines that summarise a run.
	 *
	 * @param string               $seedId The seed id.
	 * @param array<string, mixed> $report The run report.
	 *
	 * @return array<int, string> The lines.
	 */
	private static function reportLines(string $seedId, array $report): array {
		$lines = [
			'Loaded demo seed ' . $seedId . '.',
			'Users created: ' . self::listOrNone(items: $report['createdUsers']) . '.',
			'Groups created: ' . self::listOrNone(items: $report['createdGroups']) . '.',
			'Catalogue: ' . $report['catalogue']['created'] . ' added, ' . $report['catalogue']['updated'] . ' updated.',
			'Announcements: ' . $report['announcements'] . '.',
		];

		foreach ($report['dashboards'] as $dashboard) {
			array_push($lines, ...self::dashboardLines(dashboard: $dashboard));
		}

		foreach ($report['firstRun'] as $userId => $removed) {
			$lines[] = 'First visit reset for ' . $userId . ' (' . $removed . ' dashboards removed).';
		}

		if (isset($report['files']['error']) === true) {
			$lines[] = '<comment>Team folder not created: ' . $report['files']['error'] . '</comment>';
		}

		return $lines;
	}//end reportLines()

	/**
	 * The lines that describe one seeded dashboard.
	 *
	 * @param array<string, mixed> $dashboard The dashboard's report row.
	 *
	 * @return array<int, string> The lines.
	 */
	private static function dashboardLines(array $dashboard): array {
		$verb = 'updated';
		if ($dashboard['created'] === true) {
			$verb = 'created';
		}

		$lines = [sprintf('%s (%s) %s: %d widgets, uuid %s', $dashboard['name'], $dashboard['board'], $verb, $dashboard['placed'], $dashboard['uuid'])];
		if ($dashboard['fallbacks'] !== []) {
			$lines[] = '  <comment>Placed as fallback: ' . implode(separator: ', ', array: $dashboard['fallbacks']) . '</comment>';
		}

		if ($dashboard['skipped'] !== []) {
			$lines[] = '  <comment>Not registered yet, skipped: ' . implode(separator: ', ', array: $dashboard['skipped']) . '</comment>';
		}

		return $lines;
	}//end dashboardLines()

	/**
	 * A comma-separated list, or "none".
	 *
	 * @param array<int, string> $items The items.
	 *
	 * @return string The list.
	 */
	private static function listOrNone(array $items): string {
		if ($items === []) {
			return 'none';
		}

		return implode(separator: ', ', array: $items);
	}//end listOrNone()
}//end class
