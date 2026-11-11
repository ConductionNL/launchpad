<?php

/**
 * ImportCommand
 *
 * `php occ launchpad:import` — restore dashboards from a versioned ZIP
 * archive on disk. Implements REQ-EXIM-010.
 *
 * @category  Command
 * @package   OCA\LaunchPad\Command
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
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
use OCA\LaunchPad\Service\ImportService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * `launchpad:import` console command.
 */
class ImportCommand extends Command {
	/**
	 * Constructor.
	 *
	 * @param ImportService $importService Import service.
	 */
	public function __construct(
		private readonly ImportService $importService,
	) {
		parent::__construct();
	}//end __construct()

	/**
	 * Configure CLI options.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-export-import/spec.md
	 */
	protected function configure(): void {
		$this->setName(name: 'launchpad:import')
			->setDescription(description: 'Import LaunchPad dashboards from a versioned ZIP archive.')
			->addOption(
				name: 'file',
				shortcut: 'f',
				mode: InputOption::VALUE_REQUIRED,
				description: 'Path to the ZIP archive to import.'
			)
			->addOption(
				name: 'preserve-uuids',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Preserve dashboard UUIDs (fail on collision).'
			)
			->addOption(
				name: 'user',
				shortcut: 'u',
				mode: InputOption::VALUE_REQUIRED,
				description: 'User ID to attribute the import to (defaults to "cli").',
				default: 'cli'
			);
	}//end configure()

	/**
	 * Execute the import.
	 *
	 * @param InputInterface $input CLI input.
	 * @param OutputInterface $output CLI output.
	 *
	 * @return int Exit code (0 success, 1 error).
	 *
	 * @spec openspec/specs/dashboard-export-import/spec.md
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$result = $this->importFromOptions(input: $input, output: $output);
		if ($result === null) {
			return self::FAILURE;
		}

		if ($result['status'] === ImportService::ERR_UUID_COLLISION) {
			return $this->reportCollisions(errors: $result['errors'], output: $output);
		}

		return $this->reportSummary(result: $result, output: $output);
	}//end execute()

	/**
	 * Run the import the options ask for; print the reason and answer
	 * null when the file is missing or the import is refused or fails.
	 *
	 * @param InputInterface  $input  CLI input.
	 * @param OutputInterface $output CLI output.
	 *
	 * @return array<string, mixed>|null The import result, or null on failure.
	 */
	private function importFromOptions(InputInterface $input, OutputInterface $output): array|null {
		$file = (string)($input->getOption(name: 'file') ?? '');
		$preserveUuids = (bool)$input->getOption(name: 'preserve-uuids');
		$user = (string)($input->getOption(name: 'user') ?? 'cli');

		if ($this->reportUnreadableFile(file: $file, output: $output) === true) {
			return null;
		}

		try {
			return $this->importService->import(
				zipPath: $file,
				preserveUuids: $preserveUuids,
				currentUserId: $user
			);
		} catch (Throwable $e) {
			$output->writeln(messages: self::failureLine(exception: $e));
			return null;
		}
	}//end importFromOptions()

	/**
	 * Print why `--file` cannot be imported: missing, or not an existing file.
	 *
	 * @param string          $file   The `--file` value.
	 * @param OutputInterface $output CLI output.
	 *
	 * @return bool True when an error was printed.
	 */
	private function reportUnreadableFile(string $file, OutputInterface $output): bool {
		if ($file === '') {
			$output->writeln(messages: '<error>--file parameter is required</error>');
			return true;
		}

		if (file_exists(filename: $file) === false) {
			$output->writeln(messages: '<error>File not found: ' . $file . '</error>');
			return true;
		}

		return false;
	}//end reportUnreadableFile()

	/**
	 * The error line for a refused (invalid archive) or failed import.
	 *
	 * @param Throwable $exception The failure.
	 *
	 * @return string The error line.
	 */
	private static function failureLine(Throwable $exception): string {
		if ($exception instanceof InvalidArgumentException) {
			return '<error>' . $exception->getMessage() . '</error>';
		}

		return '<error>Import failed: ' . $exception->getMessage() . '</error>';
	}//end failureLine()

	/**
	 * Print the UUID collisions of a `--preserve-uuids` import.
	 *
	 * @param array<int, array<string, mixed>> $errors The collision errors.
	 * @param OutputInterface                  $output CLI output.
	 *
	 * @return int The failure exit code.
	 */
	private function reportCollisions(array $errors, OutputInterface $output): int {
		$output->writeln(messages: '<error>UUID collisions detected (--preserve-uuids):</error>');
		foreach ($errors as $err) {
			$msg = (string)($err['message'] ?? 'collision');
			$output->writeln(messages: ' - ' . $msg);
		}

		return self::FAILURE;
	}//end reportCollisions()

	/**
	 * Print the imported and skipped counts and every non-empty error.
	 *
	 * @param array<string, mixed> $result The import result.
	 * @param OutputInterface      $output CLI output.
	 *
	 * @return int The success exit code.
	 */
	private function reportSummary(array $result, OutputInterface $output): int {
		$imported = $result['importedDashboardCount'];
		$skipped = $result['skippedDashboardCount'];
		$errors = $result['errors'];

		$head = 'Imported ' . (string)$imported . ' dashboards, ';
		$tail = 'skipped ' . (string)$skipped . ', errors: ' . (string)count($errors);
		$output->writeln(messages: ($head . $tail));

		foreach ($errors as $err) {
			$msg = (string)($err['message'] ?? '');
			if ($msg !== '') {
				$output->writeln(messages: ' - ' . $msg);
			}
		}

		return self::SUCCESS;
	}//end reportSummary()
}//end class
