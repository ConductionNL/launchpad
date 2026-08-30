<?php

/**
 * CleanupPurgeCommand
 *
 * `occ launchpad:cleanup:purge` — REQ-CLN-002. Deletes orphaned rows in
 * one or more categories. Defaults to interactive confirmation; the
 * `--yes` flag skips the prompt for non-interactive use (cron, CI).
 * `--dry-run` runs the same code path under a transaction rollback
 * so the operator can preview impact without modifying data
 * (REQ-CLN-003).
 *
 * Examples:
 *   occ launchpad:cleanup:purge
 *   occ launchpad:cleanup:purge --dry-run
 *   occ launchpad:cleanup:purge --category=expired_locks --yes
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

use OCA\LaunchPad\Db\CleanupResult;
use OCA\LaunchPad\Service\Cleanup\CategoryRegistryService;
use OCA\LaunchPad\Service\OrphanedDataCleanupService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * `launchpad:cleanup:purge` CLI command.
 */
class CleanupPurgeCommand extends Command {
	/**
	 * Constructor.
	 *
	 * @param OrphanedDataCleanupService $cleanupService The orchestrator.
	 * @param CategoryRegistryService $registry Category registry
	 *                                          (for the
	 *                                          unknown-name
	 *                                          error path).
	 */
	public function __construct(
		private readonly OrphanedDataCleanupService $cleanupService,
		private readonly CategoryRegistryService $registry,
	) {
		parent::__construct();
	}//end __construct()

	/**
	 * Configure the command name, description and options.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/orphaned-data-cleanup/spec.md
	 */
	protected function configure(): void {
		$this->setName(name: 'launchpad:cleanup:purge')
			->setDescription(
				description: 'Delete orphaned LaunchPad data. See options for dry-run and per-category limits.'
			)
			->addOption(
				name: 'category',
				shortcut: null,
				mode: InputOption::VALUE_REQUIRED,
				description: 'Limit to one category by name. Default is all.'
			)
			->addOption(
				name: 'dry-run',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Wrap deletes in a rolled-back transaction.'
			)
			->addOption(
				name: 'yes',
				shortcut: 'y',
				mode: InputOption::VALUE_NONE,
				description: 'Skip the interactive confirmation prompt. Required for cron / CI use.'
			);
	}//end configure()

	/**
	 * Execute the purge.
	 *
	 * @param InputInterface $input The console input.
	 * @param OutputInterface $output The console output.
	 *
	 * @return int 0 on success, 1 on validation failure.
	 *
	 * @spec openspec/specs/orphaned-data-cleanup/spec.md
	 */
	protected function execute(
		InputInterface $input,
		OutputInterface $output,
	): int {
		$categoryOption = $input->getOption(name: 'category');
		$dryRun = (bool)$input->getOption(name: 'dry-run');
		$assumeYes = (bool)$input->getOption(name: 'yes');

		$categoryNames = [];
		if (is_string(value: $categoryOption) === true && $categoryOption !== '') {
			if ($this->registry->getCategoryByName(name: $categoryOption) === null) {
				$output->writeln(
					messages: sprintf(
						'<error>Unknown cleanup category: %s</error>',
						$categoryOption
					)
				);
				$output->writeln(
					messages: sprintf(
						'Valid categories: %s',
						implode(separator: ', ', array: $this->registry->getCategoryNames())
					)
				);

				return 1;
			}

			$categoryNames = [$categoryOption];
		}

		$effectiveCategories = $categoryNames;
		if (count(value: $effectiveCategories) === 0) {
			$effectiveCategories = $this->registry->getCategoryNames();
		}

		if ($this->confirmPurge(
			input: $input,
			output: $output,
			assumeYes: $assumeYes,
			effectiveCategories: $effectiveCategories
		) === false
		) {
			$output->writeln(messages: 'Purge cancelled.');
			return 0;
		}

		$result = $this->cleanupService->purge(
			categoryNames: $categoryNames,
			dryRun: $dryRun,
			userId: null,
			source: 'cli',
		);

		$output->writeln(
			messages: $this->formatSummary(
				result: $result,
				categoryNames: $categoryNames,
				dryRun: $dryRun
			)
		);

		$this->writeSkipped(output: $output, skipped: $result->getSkipped());

		return 0;
	}//end execute()

	/**
	 * Ask the operator to confirm the purge.
	 *
	 * Returns `true` immediately when `--yes` was supplied, or when the
	 * console has no question helper registered (the pre-existing
	 * non-interactive fallback). Otherwise the confirmation question is
	 * asked and its answer returned.
	 *
	 * @param InputInterface $input The console input.
	 * @param OutputInterface $output The console output.
	 * @param bool $assumeYes Whether `--yes` was
	 *                        supplied.
	 * @param array<int, string> $effectiveCategories Categories named in
	 *                                                the prompt.
	 *
	 * @return bool True when the purge may proceed.
	 */
	private function confirmPurge(
		InputInterface $input,
		OutputInterface $output,
		bool $assumeYes,
		array $effectiveCategories,
	): bool {
		if ($assumeYes === true) {
			return true;
		}

		$helper = $this->getHelper(name: 'question');
		if (($helper instanceof QuestionHelper) === false) {
			return true;
		}

		$question = new ConfirmationQuestion(
			question: sprintf(
				'Delete orphaned data in categories: [%s]? (y/N) ',
				implode(separator: ', ', array: $effectiveCategories)
			),
			default: false
		);

		return ($helper->ask(input: $input, output: $output, question: $question) !== false);
	}//end confirmPurge()

	/**
	 * Build the one-line summary written after a purge.
	 *
	 * A single explicitly-named category gets the per-category wording;
	 * every other invocation gets the across-categories wording. Dry runs
	 * are prefixed so the operator can never mistake a preview for a
	 * completed purge.
	 *
	 * @param CleanupResult $result The purge result.
	 * @param array<int, string> $categoryNames The explicitly requested categories.
	 * @param bool $dryRun Whether this was a dry run.
	 *
	 * @return string The summary line.
	 */
	private function formatSummary(
		CleanupResult $result,
		array $categoryNames,
		bool $dryRun,
	): string {
		$prefix = 'Purged';
		if ($dryRun === true) {
			$prefix = 'DRY-RUN: Would purge';
		}

		if (count(value: $categoryNames) === 1) {
			return sprintf(
				'<info>%s %d items from category \'%s\' in %dms.</info>',
				$prefix,
				$result->getTotalRows(),
				$categoryNames[0],
				$result->getDurationMs()
			);
		}

		return sprintf(
			'<info>%s %d items across %d categories in %dms.</info>',
			$prefix,
			$result->getTotalRows(),
			count(value: $result->getByCategory()),
			$result->getDurationMs()
		);
	}//end formatSummary()

	/**
	 * Write the skipped-categories notice when there is one.
	 *
	 * @param OutputInterface $output The console output.
	 * @param array<int, string> $skipped The skipped category names.
	 *
	 * @return void
	 */
	private function writeSkipped(OutputInterface $output, array $skipped): void {
		if (count(value: $skipped) === 0) {
			return;
		}

		$output->writeln(
			messages: sprintf(
				'<comment>Skipped categories: %s</comment>',
				implode(separator: ', ', array: $skipped)
			)
		);
	}//end writeSkipped()
}//end class
