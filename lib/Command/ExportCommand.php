<?php

/**
 * ExportCommand
 *
 * `php occ launchpad:export` — write a versioned ZIP archive of one or
 * more dashboards to disk. Implements REQ-EXIM-010.
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

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Service\ExportService;
use OCP\AppFramework\Db\DoesNotExistException;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use ZipArchive;

/**
 * `launchpad:export` console command.
 */
class ExportCommand extends Command {
	/**
	 * Constructor.
	 *
	 * @param ExportService $exportService Export service.
	 * @param DashboardMapper $dashboardMapper Dashboard mapper for
	 *                                         site-scope iteration.
	 */
	public function __construct(
		private readonly ExportService $exportService,
		private readonly DashboardMapper $dashboardMapper,
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
		$this->setName(name: 'launchpad:export')
			->setDescription(description: 'Export LaunchPad dashboards to a versioned ZIP archive.')
			->addOption(
				name: 'scope',
				shortcut: null,
				mode: InputOption::VALUE_REQUIRED,
				description: 'Export scope: "site" (default) or "dashboard".',
				default: 'site'
			)
			->addOption(
				name: 'dashboard-uuid',
				shortcut: null,
				mode: InputOption::VALUE_REQUIRED,
				description: 'Dashboard UUID, required when --scope=dashboard.'
			)
			->addOption(
				name: 'output',
				shortcut: 'o',
				mode: InputOption::VALUE_REQUIRED,
				description: 'Output file path for the ZIP archive.'
			);
	}//end configure()

	/**
	 * Execute the export.
	 *
	 * @param InputInterface $input CLI input.
	 * @param OutputInterface $output CLI output.
	 *
	 * @return int Exit code (0 success, 1 error).
	 *
	 * @spec openspec/specs/dashboard-export-import/spec.md
	 */
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$target = new ExportTarget(
			scope: (string)$input->getOption(name: 'scope'),
			dashboardUuid: (string)($input->getOption(name: 'dashboard-uuid') ?? ''),
			outputPath: (string)($input->getOption(name: 'output') ?? '')
		);

		$optionError = self::optionError(target: $target);
		if ($optionError !== null) {
			$output->writeln(messages: $optionError);
			return self::FAILURE;
		}

		return $this->exportTo(target: $target, output: $output);
	}//end execute()

	/**
	 * The error line for a missing `--output`, an unsupported scope or a
	 * dashboard export without `--dashboard-uuid`.
	 *
	 * @param ExportTarget $target The requested export.
	 *
	 * @return string|null The error line, or null when the options are complete.
	 */
	private static function optionError(ExportTarget $target): string|null {
		if ($target->outputPath === '') {
			return '<error>--output parameter is required</error>';
		}

		if (in_array(needle: $target->scope, haystack: ['site', 'dashboard'], strict: true) === false) {
			return '<error>Unsupported scope: ' . $target->scope . '. Use "site" or "dashboard".</error>';
		}

		if ($target->scope === 'dashboard' && $target->dashboardUuid === '') {
			return '<error>--dashboard-uuid is required when --scope=dashboard</error>';
		}

		return null;
	}//end optionError()

	/**
	 * Write the archive and print the outcome.
	 *
	 * @param ExportTarget    $target The requested export.
	 * @param OutputInterface $output CLI output.
	 *
	 * @return int Exit code (0 success, 1 error).
	 */
	private function exportTo(ExportTarget $target, OutputInterface $output): int {
		try {
			$count = $this->writeArchive(target: $target);
		} catch (DoesNotExistException) {
			$output->writeln(messages: '<error>Dashboard not found: ' . $target->dashboardUuid . '</error>');
			return self::FAILURE;
		} catch (Throwable $e) {
			$output->writeln(messages: '<error>Export failed: ' . $e->getMessage() . '</error>');
			return self::FAILURE;
		}

		$noun = 'dashboards';
		if ($count === 1) {
			$noun = 'dashboard';
		}

		$output->writeln(
			messages: 'Exported ' . (string)$count . ' ' . $noun . ' to ' . $target->outputPath
		);
		return self::SUCCESS;
	}//end exportTo()

	/**
	 * Write the archive to disk by reusing the export service helpers.
	 *
	 * The CLI uses the same serializer the HTTP path uses; we copy the
	 * temporary stream back to the requested on-disk location.
	 *
	 * @param ExportTarget $target The requested export.
	 *
	 * @return int The dashboard count written.
	 *
	 * @throws DoesNotExistException When the dashboard is not found.
	 */
	private function writeArchive(ExportTarget $target): int {
		$dashboards = $this->collectDashboards(target: $target);

		$zip = new ZipArchive();
		if ($zip->open(filename: $target->outputPath, flags: ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			throw new RuntimeException(message: 'Could not open ZIP archive at ' . $target->outputPath);
		}

		$manifest = $this->exportService->buildManifest(
			scope: $target->scope,
			dashboardCount: count($dashboards),
			currentUserId: 'cli'
		);
		$zip->addFromString(name: 'manifest.json', content: self::prettyJson(value: $manifest));

		foreach ($dashboards as $dashboard) {
			$this->addDashboard(zip: $zip, dashboard: $dashboard);
		}

		$zip->addFromString(name: 'metadata-fields.json', content: '[]');
		$zip->addEmptyDir(dirname: 'assets/icons/');

		$zip->close();

		return count($dashboards);
	}//end writeArchive()

	/**
	 * Add one dashboard's JSON and its widget asset directory to the
	 * archive; a dashboard without a UUID is serialised but not written.
	 *
	 * @param ZipArchive $zip       The open archive.
	 * @param Dashboard  $dashboard The dashboard.
	 *
	 * @return void
	 */
	private function addDashboard(ZipArchive $zip, Dashboard $dashboard): void {
		$payload = $this->exportService->serializeDashboard(dashboard: $dashboard);
		$uuid = (string)$dashboard->getUuid();
		if ($uuid === '') {
			return;
		}

		$zip->addFromString(
			name: 'dashboards/' . $uuid . '.json',
			content: self::prettyJson(value: $payload)
		);
		$zip->addEmptyDir(dirname: 'assets/widgets/' . $uuid . '/');
	}//end addDashboard()

	/**
	 * Pretty-printed JSON with unescaped slashes.
	 *
	 * @param array<string, mixed> $value The value.
	 *
	 * @return string The JSON text.
	 */
	private static function prettyJson(array $value): string {
		return (string)json_encode(
			value: $value,
			flags: (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
		);
	}//end prettyJson()

	/**
	 * Resolve the dashboard collection for the requested scope.
	 *
	 * @param ExportTarget $target The requested export.
	 *
	 * @return Dashboard[] The dashboards to export.
	 *
	 * @throws DoesNotExistException When the dashboard is not found.
	 */
	private function collectDashboards(ExportTarget $target): array {
		if ($target->scope === 'dashboard') {
			return [$this->dashboardMapper->findByUuid(uuid: $target->dashboardUuid)];
		}

		$all = [];
		foreach ($this->dashboardMapper->findAdminTemplates() as $tpl) {
			$all[] = $tpl;
		}

		foreach ($this->dashboardMapper->findByParent(parentUuid: null) as $root) {
			$all[] = $root;
			array_push($all, ...$this->descendantsOf(root: $root));
		}

		return $all;
	}//end collectDashboards()

	/**
	 * The descendants of a root dashboard; none when it has no UUID.
	 *
	 * @param Dashboard $root The root dashboard.
	 *
	 * @return Dashboard[] The descendants.
	 */
	private function descendantsOf(Dashboard $root): array {
		$uuid = (string)$root->getUuid();
		if ($uuid === '') {
			return [];
		}

		return $this->dashboardMapper->findDescendants(ancestorUuid: $uuid);
	}//end descendantsOf()
}//end class
