<?php

/**
 * ImportConfluenceCommand
 *
 * `php occ launchpad:import:confluence` — REQ-CFLI-011 CLI surface for
 * the Confluence HTML export importer. Mirrors the HTTP controller but
 * works headless so admins can run imports from cron / shell scripts.
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
use OCA\LaunchPad\Service\ConfluenceImportService;
use OCA\LaunchPad\Service\DashboardTreeService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * `launchpad:import:confluence` console command.
 */
class ImportConfluenceCommand extends Command {
	/**
	 * Constructor.
	 *
	 * @param ConfluenceImportService $importService Importer.
	 * @param DashboardTreeService $treeService Path resolver.
	 */
	public function __construct(
		private readonly ConfluenceImportService $importService,
		private readonly DashboardTreeService $treeService,
	) {
		parent::__construct();
	}//end __construct()

	/**
	 * Configure CLI options.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/confluence-html-import/spec.md
	 */
	protected function configure(): void {
		$this->setName(name: 'launchpad:import:confluence')
			->setDescription(description: 'Import a Confluence HTML export ZIP into LaunchPad dashboards.')
			->addOption(
				name: 'file',
				shortcut: 'f',
				mode: InputOption::VALUE_REQUIRED,
				description: 'Path to the Confluence HTML export ZIP archive.'
			)
			->addOption(
				name: 'parent-path',
				shortcut: 'p',
				mode: InputOption::VALUE_REQUIRED,
				description: 'Slug-chain path under which root pages should be slotted.'
			)
			->addOption(
				name: 'user',
				shortcut: 'u',
				mode: InputOption::VALUE_REQUIRED,
				description: 'User ID to attribute the imported dashboards to.',
				default: 'cli'
			)
			->addOption(
				name: 'dry-run',
				shortcut: null,
				mode: InputOption::VALUE_NONE,
				description: 'Inspect the archive without creating any dashboards.'
			);
	}//end configure()

	/**
	 * Execute the command.
	 *
	 * @param InputInterface $input CLI input.
	 * @param OutputInterface $output CLI output.
	 *
	 * @return int Exit code (0 success, 1 failure).
	 *
	 * @spec openspec/specs/confluence-html-import/spec.md
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$request = new ConfluenceImportRequest(input: $input);

		$fileError = FileOptionCheck::error(option: 'file', path: $request->file);
		if ($fileError !== null) {
			$output->writeln(messages: $fileError);
			return self::FAILURE;
		}

		return $this->importArchive(request: $request, output: $output);
	}//end execute()

	/**
	 * Resolve the parent, then preview or import the archive and print
	 * the outcome.
	 *
	 * @param ConfluenceImportRequest $request The run's options.
	 * @param OutputInterface         $output  CLI output.
	 *
	 * @return int Exit code (0 success, 1 failure).
	 */
	private function importArchive(ConfluenceImportRequest $request, OutputInterface $output): int {
		$parentUuid = $this->parentUuid(parentPath: $request->parentPath, output: $output);
		if ($parentUuid === false) {
			return self::FAILURE;
		}

		try {
			if ($request->isDryRun === true) {
				return $this->reportDryRun(result: $this->importService->dryRun(zipPath: $request->file), output: $output);
			}

			return $this->reportImport(result: $this->importPages(request: $request, parentUuid: $parentUuid), output: $output);
		} catch (Throwable $e) {
			$output->writeln(messages: self::failureLine(exception: $e));
			return self::FAILURE;
		}
	}//end importArchive()

	/**
	 * The UUID of the parent dashboard; null without `--parent-path`,
	 * false (after printing why) when the path resolves to nothing.
	 *
	 * @param string          $parentPath The `--parent-path` value.
	 * @param OutputInterface $output     CLI output.
	 *
	 * @return string|null|false The parent UUID, null, or false.
	 */
	private function parentUuid(string $parentPath, OutputInterface $output): string|null|false {
		if ($parentPath === '') {
			return null;
		}

		$parent = $this->treeService->resolvePath(path: $parentPath);
		if ($parent === null) {
			$output->writeln(
				messages: '<error>Parent path not found: ' . $parentPath . '</error>'
			);
			return false;
		}

		return $parent->getUuid();
	}//end parentUuid()

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
	 * Import the archive's pages under the parent.
	 *
	 * @param ConfluenceImportRequest $request    The run's options.
	 * @param string|null             $parentUuid The parent dashboard UUID, or null for the root.
	 *
	 * @return array<string, mixed> The import result.
	 */
	private function importPages(ConfluenceImportRequest $request, string|null $parentUuid): array {
		return $this->importService->import(
			zipPath: $request->file,
			currentUserId: $request->userId,
			parentUuid: $parentUuid
		);
	}//end importPages()

	/**
	 * Print a dry-run preview.
	 *
	 * @param array<string, mixed> $result The dry-run result.
	 * @param OutputInterface      $output CLI output.
	 *
	 * @return int Exit code.
	 */
	private function reportDryRun(array $result, OutputInterface $output): int {
		$summary = sprintf(
			'Pages: %d, attachments: %d, estimated dashboards: %d, asset folder: %s',
			(int)$result['pageCount'],
			(int)$result['attachmentCount'],
			(int)$result['estimatedDashboards'],
			(string)$result['assetFolder']
		);

		$output->writeln(messages: $summary);
		$this->writeWarnings(warnings: $result['warnings'], output: $output);

		return self::SUCCESS;
	}//end reportDryRun()

	/**
	 * Print the outcome of a full import.
	 *
	 * @param array<string, mixed> $result The import result.
	 * @param OutputInterface      $output CLI output.
	 *
	 * @return int Exit code.
	 */
	private function reportImport(array $result, OutputInterface $output): int {
		$summary = sprintf(
			'Imported %d dashboards, skipped %d, errors: %d, asset folder: %s',
			(int)$result['createdDashboardCount'],
			(int)$result['skippedPageCount'],
			count(value: $result['errors']),
			(string)$result['assetFolder']
		);

		$output->writeln(messages: $summary);

		foreach ($result['errors'] as $err) {
			$output->writeln(
				messages: ' - ' . $err['pageId'] . ': ' . $err['reason']
			);
		}

		$this->writeWarnings(warnings: $result['warnings'], output: $output);

		return self::SUCCESS;
	}//end reportImport()

	/**
	 * Print the import's warnings as comments.
	 *
	 * @param list<string>    $warnings The warnings.
	 * @param OutputInterface $output   CLI output.
	 *
	 * @return void
	 */
	private function writeWarnings(array $warnings, OutputInterface $output): void {
		foreach ($warnings as $warning) {
			$output->writeln(messages: '<comment>warning: ' . $warning . '</comment>');
		}
	}//end writeWarnings()
}//end class
