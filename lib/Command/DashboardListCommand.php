<?php

/**
 * DashboardListCommand
 *
 * `launchpad:dashboard:list` — list dashboards with optional `--user`,
 * `--group`, and `--status` filters (REQ-CLI-003). Default output is a
 * compact table; with `--json` the standard envelope is emitted.
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

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Service\CommandService;
use OCP\IUserManager;
use OCP\IUserSession;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `launchpad:dashboard:list` console command.
 */
class DashboardListCommand extends CommandBase {
	/**
	 * Allowed values for `--status` (REQ-CLI-003).
	 *
	 * @var list<string>
	 */
	private const ALLOWED_STATUS = ['draft', 'published', 'scheduled'];

	/**
	 * Constructor.
	 *
	 * @param CommandService $commandService Shared CLI helper.
	 * @param IUserSession $userSession Caller resolution.
	 * @param DashboardMapper $dashboardMapper Dashboard mapper.
	 * @param IUserManager $userManager For `--user` validation.
	 */
	public function __construct(
		CommandService $commandService,
		IUserSession $userSession,
		private readonly DashboardMapper $dashboardMapper,
		private readonly IUserManager $userManager,
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
		$this->setName(name: 'launchpad:dashboard:list')
			->setDescription(description: 'List dashboards with optional filters.')
			->setHelp(
				help: implode(
					separator: "\n",
					array: [
						'List LaunchPad dashboards visible on this instance.',
						'',
						'Options:',
						'  --user=<uid>     Restrict to dashboards owned by user.',
						'  --group=<gid>    Restrict to group-shared dashboards for group.',
						'  --status=<val>   Filter on publication status (draft|published|scheduled).',
						'',
						'Examples:',
						'  php occ launchpad:dashboard:list',
						'  php occ launchpad:dashboard:list --user=alice --status=published --json',
					]
				)
			)
			->addOption(
				name: 'user',
				shortcut: null,
				mode: InputOption::VALUE_REQUIRED,
				description: 'Filter by owning user id.'
			)
			->addOption(
				name: 'group',
				shortcut: null,
				mode: InputOption::VALUE_REQUIRED,
				description: 'Filter by group id (group-shared dashboards).'
			)
			->addOption(
				name: 'status',
				shortcut: null,
				mode: InputOption::VALUE_REQUIRED,
				description: 'Filter by publication status.'
			);
	}//end configureCommand()

	/**
	 * Execute the listing.
	 *
	 * @param InputInterface $input CLI input.
	 * @param OutputInterface $output CLI output.
	 *
	 * @return int
	 *
	 * @spec openspec/specs/cli-commands/spec.md
	 */
	protected function handle(InputInterface $input, OutputInterface $output): int {
		$filters = new DashboardListFilters(input: $input);

		$rejection = $this->filterError(filters: $filters);
		if ($rejection !== null) {
			return $this->emitError(input: $input, output: $output, error: $rejection);
		}

		$rows = $this->toRows(dashboards: $this->collect(filters: $filters));

		if ($this->isJson(input: $input) === true) {
			$this->emitSuccess(
				input: $input,
				output: $output,
				data: ['dashboards' => $rows, 'count' => count(value: $rows)]
			);
			return CommandService::EXIT_SUCCESS;
		}

		if ($this->isQuiet(input: $input) === false) {
			$this->writeTable(output: $output, rows: $rows);
		}

		return CommandService::EXIT_SUCCESS;
	}//end handle()

	/**
	 * The error of a filter that cannot be applied: an unknown status, or
	 * a user that does not exist.
	 *
	 * @param DashboardListFilters $filters The run's filters.
	 *
	 * @return array<string, mixed>|null The error, or null when the filters apply.
	 */
	private function filterError(DashboardListFilters $filters): array|null {
		if ($filters->status !== null
			&& in_array(needle: $filters->status, haystack: self::ALLOWED_STATUS, strict: true) === false
		) {
			return [
				'exitCode' => CommandService::EXIT_INVALID_ARGS,
				'code'     => 'INVALID_ARGUMENT',
				'message'  => 'Invalid --status value: ' . $filters->status,
				'context'  => ['allowed' => self::ALLOWED_STATUS],
			];
		}

		if ($filters->user !== null && $this->userManager->userExists(uid: $filters->user) === false) {
			return [
				'exitCode' => CommandService::EXIT_NOT_FOUND,
				'code'     => 'NOT_FOUND',
				'message'  => 'User not found: ' . $filters->user,
				'context'  => ['userId' => $filters->user],
			];
		}

		return null;
	}//end filterError()

	/**
	 * Flatten dashboards into the row shape shared by both output modes.
	 *
	 * @param array<int, Dashboard> $dashboards The dashboards to flatten.
	 *
	 * @return list<array<string, string>> The rows.
	 */
	private function toRows(array $dashboards): array {
		return array_map(
			callback: static function (Dashboard $dashboard): array {
				return [
					'uuid' => (string)$dashboard->getUuid(),
					'name' => (string)$dashboard->getName(),
					'type' => (string)$dashboard->getType(),
					'owner' => (string)($dashboard->getUserId() ?? ''),
					'group' => (string)($dashboard->getGroupId() ?? ''),
					'publicationStatus' => (string)$dashboard->getPublicationStatus(),
				];
			},
			array: $dashboards
		);
	}//end toRows()

	/**
	 * Render the compact human-readable table.
	 *
	 * The caller skips it in quiet mode. Writes the empty-result notice
	 * when no dashboard matched; otherwise writes a header plus one line
	 * per row.
	 *
	 * @param OutputInterface             $output CLI output.
	 * @param list<array<string, string>> $rows   The rows to render.
	 *
	 * @return void
	 */
	private function writeTable(OutputInterface $output, array $rows): void {
		if (count(value: $rows) === 0) {
			$output->writeln(messages: 'No dashboards match the supplied filters.');
			return;
		}

		$output->writeln(
			messages: sprintf('%-36s  %-20s  %-14s  %-12s  %s', 'UUID', 'NAME', 'TYPE', 'STATUS', 'OWNER')
		);
		foreach ($rows as $row) {
			$output->writeln(messages: self::tableLine(row: $row));
		}
	}//end writeTable()

	/**
	 * One table line: UUID, name (trimmed to 20), type, status and owner.
	 *
	 * @param array<string, string> $row The row.
	 *
	 * @return string The line.
	 */
	private static function tableLine(array $row): string {
		return sprintf(
			'%-36s  %-20s  %-14s  %-12s  %s',
			$row['uuid'],
			mb_strimwidth(string: $row['name'], start: 0, width: 20, trim_marker: '..'),
			$row['type'],
			$row['publicationStatus'],
			$row['owner']
		);
	}//end tableLine()

	/**
	 * Collect dashboards across all relevant scopes for the supplied
	 * filters. The mapper exposes scope-specific finders; we fan out
	 * and apply post-filters in PHP to keep the command non-invasive
	 * (no schema or mapper changes).
	 *
	 * @param DashboardListFilters $filters The user, group and publication status filters.
	 *
	 * @return list<Dashboard>
	 */
	private function collect(DashboardListFilters $filters): array {
		$dashboards = $this->collectScope(user: $filters->user, group: $filters->group);
		$status = $filters->status;

		if ($status === null) {
			return $dashboards;
		}

		return array_values(
			array: array_filter(
				array: $dashboards,
				callback: static function (Dashboard $dashboard) use ($status): bool {
					return $dashboard->getPublicationStatus() === $status;
				}
			)
		);
	}//end collect()

	/**
	 * Pick the mapper scope that matches the supplied filters.
	 *
	 * The three scopes are mutually exclusive and ordered by specificity:
	 * `--user` wins over `--group`, and with neither filter the whole
	 * instance-wide scope is walked.
	 *
	 * @param string|null $user Optional user filter.
	 * @param string|null $group Optional group filter.
	 *
	 * @return list<Dashboard>
	 */
	private function collectScope(string|null $user, string|null $group): array {
		if ($user !== null) {
			return array_values(array: $this->dashboardMapper->findByUserId(userId: $user));
		}

		if ($group !== null) {
			return array_values(array: $this->dashboardMapper->findByGroup(groupId: $group));
		}

		return $this->collectInstanceWide();
	}//end collectScope()

	/**
	 * Walk every dashboard visible instance-wide: the admin templates
	 * plus every root dashboard and its descendants.
	 *
	 * Roots without a UUID cannot be used as a descendant anchor, so
	 * they are emitted on their own and their (unreachable) subtree is
	 * skipped.
	 *
	 * @return list<Dashboard>
	 */
	private function collectInstanceWide(): array {
		$dashboards = [];

		foreach ($this->dashboardMapper->findAdminTemplates() as $dashboard) {
			$dashboards[] = $dashboard;
		}

		foreach ($this->dashboardMapper->findByParent(parentUuid: null) as $root) {
			$dashboards[] = $root;
			$uuid = (string)$root->getUuid();
			if ($uuid === '') {
				continue;
			}

			foreach ($this->dashboardMapper->findDescendants(ancestorUuid: $uuid) as $child) {
				$dashboards[] = $child;
			}
		}

		return $dashboards;
	}//end collectInstanceWide()
}//end class
