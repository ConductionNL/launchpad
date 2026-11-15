<?php

/**
 * DemoShowcasesInstallCommand
 *
 * `php occ launchpad:demo-showcases:install <id> [--lang=nl] [--force]`
 *
 * Mirrors the `POST /api/admin/demo-showcases/{id}/install` endpoint
 * for ops convenience. Idempotent unless `--force` is passed; in that
 * case the existing dashboard is removed and reinstalled (REQ-DEMO-009).
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

use OCA\LaunchPad\Exception\ShowcaseNotFoundException;
use OCA\LaunchPad\Service\DemoShowcasesService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * `launchpad:demo-showcases:install` console command.
 */
class DemoShowcasesInstallCommand extends Command {
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
		$this->setName(name: 'launchpad:demo-showcases:install')
			->setDescription(description: 'Install a bundled LaunchPad demo showcase dashboard.')
			->addArgument(
				name: 'id',
				mode: InputArgument::REQUIRED,
				description: 'Showcase ID (e.g. de-bron, gemeente-duin).'
			)
			->addOption(
				name: 'lang',
				shortcut: null,
				mode: InputOption::VALUE_REQUIRED,
				description: 'Locale (forward-compatible; v1 always resolves to nl).',
				default: 'nl'
			)
			->addOption(
				name: 'force',
				shortcut: 'f',
				mode: InputOption::VALUE_NONE,
				description: 'Reinstall even if the showcase is already installed.'
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
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$id = (string)$input->getArgument(name: 'id');
		$lang = (string)($input->getOption(name: 'lang') ?? 'nl');
		$force = (bool)$input->getOption(name: 'force');

		try {
			$result = $this->showcases->installShowcase(
				showcaseId: $id,
				lang: $lang,
				force: $force
			);
		} catch (Throwable $e) {
			$output->writeln(messages: self::failureLine(exception: $e, showcaseId: $id));
			return self::FAILURE;
		}

		foreach (self::installedLines(showcaseId: $id, result: $result) as $line) {
			$output->writeln(messages: $line);
		}

		return self::SUCCESS;
	}//end execute()

	/**
	 * The error line of a failed install.
	 *
	 * @param Throwable $exception  The failure.
	 * @param string    $showcaseId The showcase id.
	 *
	 * @return string The line.
	 */
	private static function failureLine(Throwable $exception, string $showcaseId): string {
		if ($exception instanceof ShowcaseNotFoundException) {
			return '<error>Showcase not found: ' . $showcaseId . '</error>';
		}

		return '<error>Installation failed: ' . $exception->getMessage() . '</error>';
	}//end failureLine()

	/**
	 * The lines of an install: already installed (with the --force hint),
	 * or the new dashboard and any skipped widgets.
	 *
	 * @param string               $showcaseId The showcase id.
	 * @param array<string, mixed> $result     The install result.
	 *
	 * @return list<string> The lines.
	 */
	private static function installedLines(string $showcaseId, array $result): array {
		if ($result['alreadyInstalled'] === true) {
			return [
				'Showcase ' . $showcaseId . ' is already installed (UUID: ' . $result['installedDashboardUuid'] . ').',
				'Use --force to reinstall.',
			];
		}

		$lines = ['Installed dashboard ' . $result['installedDashboardUuid']];
		$skipped = $result['skippedWidgets'];
		if ($skipped !== []) {
			$lines[] = '<comment>Skipped unknown widgets: ' . implode(separator: ', ', array: $skipped) . '</comment>';
		}

		return $lines;
	}//end installedLines()
}//end class
