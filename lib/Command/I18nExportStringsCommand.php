<?php

/**
 * I18nExportStringsCommand
 *
 * `launchpad:i18n:export-strings` — extract translatable strings from
 * `lib/` (PHP) and `src/` (Vue/JS) into `l10n/launchpad.pot` (REQ-CLI-005).
 *
 * The implementation is intentionally minimal — it scans for the four
 * standard markers (`t(`, `n(`, `$l->t(`, `$this->l->t(`) so the
 * scaffolding is in place; richer extraction (xgettext-equivalent
 * features) is an explicit follow-up.
 *
 * WHERE IT WRITES. On a development checkout the default stays
 * `l10n/launchpad.pot`. A signed release (one that carries
 * `appinfo/signature.json`) is never written to: Nextcloud checks every file
 * of a signed release, and a new file in it shows as a code integrity
 * warning (EXTRA_FILE). There the command asks for `--output`, a path or `-`
 * for standard output.
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

use OCA\LaunchPad\Service\CommandService;
use OCP\IUserSession;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `launchpad:i18n:export-strings` console command.
 *
 * @spec openspec/specs/cli-commands/spec.md#requirement-req-cli-005-new-commands-i18n-management
 */
class I18nExportStringsCommand extends CommandBase {
	/**
	 * Translation marker patterns. Each entry produces a captured
	 * single- or double-quoted string literal.
	 *
	 * @var list<string>
	 */
	private const MARKER_PATTERNS = [
		'/->t\(\s*[\'"]([^\'"]+)[\'"]/',
		'/\bt\(\s*[\'"]([^\'"]+)[\'"]/',
		'/\bn\(\s*[\'"]([^\'"]+)[\'"]/',
	];

	/**
	 * Constructor.
	 *
	 * @param CommandService $commandService Shared CLI helper.
	 * @param IUserSession $userSession Caller resolution.
	 * @param string|null $appRoot The app's own folder; null resolves it from this file.
	 */
	public function __construct(
		CommandService $commandService,
		IUserSession $userSession,
		private readonly ?string $appRoot = null,
	) {
		parent::__construct(commandService: $commandService, userSession: $userSession);
	}//end __construct()

	/**
	 * Wire command name, description, and per-command options.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/cli-commands/spec.md
	 */
	protected function configureCommand(): void {
		$this->setName(name: 'launchpad:i18n:export-strings')
			->setDescription(description: 'Extract translatable strings to a POT file.')
			->addOption(
				name: 'output',
				shortcut: 'o',
				mode: InputOption::VALUE_REQUIRED,
				description: 'Where to write the POT file: a path, or - for standard output. '
					. 'Defaults to l10n/launchpad.pot on a development checkout; a signed release needs this option.'
			)
			->setHelp(
				help: implode(
					separator: "\n",
					array: [
						'Scan lib/ and src/ for translatable strings and write them to a POT file.',
						'On a development checkout the default is l10n/launchpad.pot. A signed release is',
						'never written to, because Nextcloud would report the new file as a code integrity',
						'problem: pass --output=<path>, or --output=- to print the file.',
						'Overwrites the target file.',
						'',
						'Examples:',
						'  php occ launchpad:i18n:export-strings',
						'  php occ launchpad:i18n:export-strings --output=/tmp/launchpad.pot',
						'  php occ launchpad:i18n:export-strings --output=- > launchpad.pot',
					]
				)
			);
	}//end configureCommand()

	/**
	 * Execute the extraction.
	 *
	 * @param InputInterface $input CLI input.
	 * @param OutputInterface $output CLI output.
	 *
	 * @return int
	 *
	 * @spec openspec/specs/cli-commands/spec.md
	 */
	protected function handle(
		InputInterface $input,
		OutputInterface $output,
	): int {
		$appRoot = (string)realpath(path: ($this->appRoot ?? __DIR__ . '/../..'));
		if ($appRoot === '') {
			return $this->emitError(
				input: $input,
				output: $output,
				exitCode: CommandService::EXIT_ERROR,
				code: 'INTERNAL_ERROR',
				message: 'Could not resolve app root directory.'
			);
		}

		$strings = [];
		foreach (['lib', 'src'] as $sub) {
			$path = $appRoot . '/' . $sub;
			if (is_dir(filename: $path) === false) {
				continue;
			}

			$this->collectFromDir(directory: $path, sink: $strings);
		}

		ksort(array: $strings);
		$pot = $this->buildPot(strings: array_keys(array: $strings));
		$target = $input->getOption(name: 'output');
		if ($target === '-') {
			$output->write(messages: $pot, options: OutputInterface::OUTPUT_RAW);
			return CommandService::EXIT_SUCCESS;
		}

		$target = $this->targetPath(target: $target, appRoot: $appRoot);
		if ($target === null) {
			return $this->emitError(
				input: $input,
				output: $output,
				exitCode: CommandService::EXIT_INVALID_ARGS,
				code: 'SIGNED_RELEASE',
				message: 'This is a signed release, so nothing is written into the app folder: Nextcloud would report '
					. 'the new file as a code integrity problem. Pass --output=<path> outside it, or --output=- to print the file.'
			);
		}

		$this->writePot(pot: $pot, outPath: $target);

		$this->emitSuccess(
			input: $input,
			output: $output,
			data: ['count' => count(value: $strings), 'output' => $target],
			human: 'Wrote ' . count(value: $strings) . ' strings to ' . $target
		);

		return CommandService::EXIT_SUCCESS;
	}//end handle()

	/**
	 * Recursively scan `$directory` for translatable markers and
	 * accumulate them into `$sink` (using the string as key for
	 * dedup).
	 *
	 * @param string $directory Directory to scan.
	 * @param array<string,bool> $sink Accumulator (modified by reference).
	 *
	 * @return void
	 */
	private function collectFromDir(string $directory, array &$sink): void {
		$iterator = new RecursiveIteratorIterator(
			iterator: new RecursiveDirectoryIterator(
				$directory,
				RecursiveDirectoryIterator::SKIP_DOTS
			)
		);

		foreach ($iterator as $file) {
			if ($file->isFile() === false) {
				continue;
			}

			$ext = strtolower(string: $file->getExtension());
			if (in_array(needle: $ext, haystack: ['php', 'vue', 'js', 'ts'], strict: true) === false) {
				continue;
			}

			$contents = (string)file_get_contents(filename: $file->getPathname());
			foreach (self::MARKER_PATTERNS as $pattern) {
				$matches = [];
				$found = preg_match_all(pattern: $pattern, subject: $contents, matches: $matches);
				if ($found === false || $found === 0) {
					continue;
				}

				foreach ($matches[1] as $string) {
					$sink[(string)$string] = true;
				}
			}
		}//end foreach
	}//end collectFromDir()

	/**
	 * The POT file content for a list of strings.
	 *
	 * @param list<string> $strings Sorted unique strings.
	 *
	 * @return string The POT file.
	 */
	private function buildPot(array $strings): string {
		$lines = [];
		$lines[] = '# LaunchPad translatable strings, generated by `launchpad:i18n:export-strings`.';
		$lines[] = 'msgid ""';
		$lines[] = 'msgstr ""';
		$lines[] = '"Content-Type: text/plain; charset=UTF-8\n"';
		$lines[] = '';
		foreach ($strings as $string) {
			$escaped = str_replace(search: ['\\', '"'], replace: ['\\\\', '\\"'], subject: $string);
			$lines[] = 'msgid "' . $escaped . '"';
			$lines[] = 'msgstr ""';
			$lines[] = '';
		}

		return implode(separator: "\n", array: $lines);
	}//end buildPot()

	/**
	 * Write the POT file, creating its folder when needed.
	 *
	 * @param string $pot     The POT content.
	 * @param string $outPath Target path.
	 *
	 * @return void
	 */
	private function writePot(string $pot, string $outPath): void {
		$dir = dirname(path: $outPath);
		if (is_dir(filename: $dir) === false) {
			mkdir(directory: $dir, permissions: 0775, recursive: true);
		}

		file_put_contents(filename: $outPath, data: $pot);
	}//end writePot()

	/**
	 * Where to write the POT file, or null when that would be inside a signed release.
	 *
	 * @param mixed  $target  The `--output` value, or null when it was not given.
	 * @param string $appRoot The app folder (resolved).
	 *
	 * @return string|null The path to write, or null to refuse.
	 */
	private function targetPath(mixed $target, string $appRoot): ?string {
		$signed = is_file(filename: $appRoot . '/appinfo/signature.json');
		if (is_string(value: $target) === false || $target === '') {
			$target = $appRoot . '/l10n/launchpad.pot';
		}

		if ($signed === true && $this->isInside(path: $target, folder: $appRoot) === true) {
			return null;
		}

		return $target;
	}//end targetPath()

	/**
	 * Whether a path, existing or not, lies inside a folder.
	 *
	 * @param string $path   The path.
	 * @param string $folder The folder (already resolved).
	 *
	 * @return bool True when inside.
	 */
	private function isInside(string $path, string $folder): bool {
		$dir = dirname(path: $path);
		while (is_dir(filename: $dir) === false && $dir !== dirname(path: $dir)) {
			$dir = dirname(path: $dir);
		}

		$resolved = (string)realpath(path: $dir);

		return $resolved === $folder || str_starts_with(haystack: $resolved . '/', needle: rtrim(string: $folder, characters: '/') . '/');
	}//end isInside()
}//end class
