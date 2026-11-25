<?php

/**
 * DashboardDebugShareCommand
 *
 * `launchpad:dashboard:debug-share <uuid>` — print sharing, lock,
 * version, and view diagnostics for a dashboard. Intended for support
 * engineers (REQ-CLI-003).
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

use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Db\DashboardView;
use OCA\LaunchPad\Kernel\Contracts\CommandServiceInterface;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IUserSession;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `launchpad:dashboard:debug-share` console command.
 */
class DashboardDebugShareCommand extends CommandBase {
	/**
	 * Constructor.
	 *
	 * @param CommandServiceInterface $commandService Shared CLI helper.
	 * @param IUserSession $userSession Caller resolution.
	 * @param DashboardMapper $dashboardMapper Dashboard mapper.
	 * @param DebugShareStores $stores The share, lock, version and view mappers.
	 */
	public function __construct(
		CommandServiceInterface $commandService,
		IUserSession $userSession,
		private readonly DashboardMapper $dashboardMapper,
		private readonly DebugShareStores $stores,
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
		$this->setName(name: 'launchpad:dashboard:debug-share')
			->setDescription(description: 'Dump sharing & lock state for a dashboard.')
			->setHelp(
				help: implode(
					separator: "\n",
					array: [
						'Print share rows, lock state, version count and view count for support diagnostics.',
						'',
						'Examples:',
						'  php occ launchpad:dashboard:debug-share a1b2c3d4-... --json',
						'  php occ launchpad:dashboard:debug-share a1b2c3d4-... | jq .',
					]
				)
			)
			->addArgument(
				name: 'uuid',
				mode: InputArgument::REQUIRED,
				description: 'Dashboard UUID.'
			);
	}//end configureCommand()

	/**
	 * Execute the diagnostics dump.
	 *
	 * @param InputInterface $input CLI input.
	 * @param OutputInterface $output CLI output.
	 *
	 * @return int
	 *
	 * @spec openspec/specs/cli-commands/spec.md
	 */
	protected function handle(InputInterface $input, OutputInterface $output): int {
		$uuid = (string)$input->getArgument(name: 'uuid');

		try {
			$dashboard = $this->dashboardMapper->findByUuid(uuid: $uuid);
		} catch (DoesNotExistException) {
			return $this->emitDashboardNotFound(input: $input, output: $output, uuid: $uuid);
		}

		$shares = array_map(
			callback: static function (DashboardShare $share): array {
				return $share->jsonSerialize();
			},
			array: $this->stores->shareMapper->findByDashboardId(dashboardId: (int)$dashboard->getId())
		);

		return $this->emitPrettyPayload(
			streams: new CommandStreams(input: $input, output: $output),
			payload: $this->diagnostics(uuid: $uuid, shares: $shares)
		);
	}//end handle()

	/**
	 * The diagnostics payload of a dashboard: its shares, the active edit
	 * lock (an expired lock counts as unlocked), the number of saved
	 * versions and the views recorded over all days.
	 *
	 * @param string                     $uuid   The dashboard UUID.
	 * @param list<array<string, mixed>> $shares The serialised shares.
	 *
	 * @return array<string, mixed> The payload.
	 *
	 * @spec openspec/specs/cli-commands/spec.md
	 */
	private function diagnostics(string $uuid, array $shares): array {
		$lock  = $this->stores->lockMapper->findActive(dashboardUuid: $uuid);
		$views = array_sum(
			array: array_map(
				callback: static fn (DashboardView $view): int => (int)$view->getViewCount(),
				array: $this->stores->viewMapper->findByDashboardInRange(
					dashboardUuid: $uuid,
					startDate: '1970-01-01',
					endDate: '9999-12-31'
				)
			)
		);

		return [
			'uuid' => $uuid,
			'shares' => $shares,
			'locked' => $lock !== null,
			'lockedBy' => $lock?->getUserId(),
			'lockedAt' => $lock?->getCreatedAt(),
			'versionCount' => $this->stores->versionMapper->countByDashboard(dashboardUuid: $uuid),
			'viewCount' => $views,
		];
	}//end diagnostics()
}//end class
