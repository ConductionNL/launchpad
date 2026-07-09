<?php

/**
 * MigrateStorageToGroupFolder
 *
 * `launchpad:storage:migrate-to-groupfolder` — one-time idempotent migration
 * from database content storage to GroupFolder storage (REQ-GFSB-008).
 * Reads all dashboards via DashboardMapper, copies their content JSON from
 * the `content` column to the GroupFolder backend, and reports progress.
 *
 * @category Command
 * @package  OCA\LaunchPad\Command
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-9
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Command;

use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Service\CommandService;
use OCA\LaunchPad\Service\DashboardContentStorage\DashboardContentStorageException;
use OCA\LaunchPad\Service\DashboardContentStorage\GroupFolderContentStorage;
use OCP\IUserSession;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * One-time migration command from DB to GroupFolder storage (REQ-GFSB-008).
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-9
 */
class MigrateStorageToGroupFolder extends CommandBase
{
    /**
     * Constructor.
     *
     * @param CommandService            $commandService     Shared CLI helper.
     * @param IUserSession              $userSession        Caller resolution.
     * @param DashboardMapper           $dashboardMapper    Dashboard mapper.
     * @param GroupFolderContentStorage $groupFolderStorage GroupFolder backend.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-9
     */
    public function __construct(
        CommandService $commandService,
        IUserSession $userSession,
        private readonly DashboardMapper $dashboardMapper,
        private readonly GroupFolderContentStorage $groupFolderStorage,
    ) {
        parent::__construct(
            commandService: $commandService,
            userSession: $userSession
        );
    }//end __construct()

    /**
     * Wire command name, description, and options.
     *
     * @return void
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-9
     */
    protected function configureCommand(): void
    {
        $this->setName(name: 'launchpad:storage:migrate-to-groupfolder')
            ->setDescription(description: 'Migrate dashboard content from DB to GroupFolder (REQ-GFSB-008).')
            ->setHelp(
                help: implode(
                    separator: "\n",
                    array: [
                        'Copies all dashboard content blobs from the `content` column in',
                        '`launchpad_dashboards` to the configured GroupFolder backend.',
                        '',
                        'The command is idempotent: dashboards already present in the',
                        'GroupFolder are skipped. Re-run safely after partial failures.',
                        '',
                        'After a successful migration, switch the active backend via:',
                        '  php occ launchpad:storage:toggle-backend groupfolder',
                        '',
                        'Use --prune-source to remove DB content for successfully migrated',
                        'dashboards (default: DB content is kept for rollback safety).',
                        '',
                        'Run launchpad:storage:migrate-to-groupfolder --help for more details.',
                    ]
                )
            )
            ->addOption(
                name: 'prune-source',
                shortcut: null,
                mode: InputOption::VALUE_NONE,
                description: 'Remove DB content for successfully migrated dashboards (REQ-GFSB-008 design D3).'
            );
    }//end configureCommand()

    /**
     * Execute the migration.
     *
     * @param InputInterface  $input  CLI input.
     * @param OutputInterface $output CLI output.
     *
     * @return int Exit code (0 = success, 1 = partial failure).
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-9
     */
    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $pruneSource = (bool) $input->getOption(name: 'prune-source');

        $dashboards = $this->dashboardMapper->findAll();
        $total      = count($dashboards);

        if ($total === 0) {
            $output->writeln(messages: 'No dashboards found — nothing to migrate.');
            return CommandService::EXIT_SUCCESS;
        }

        $migrated = 0;
        $skipped  = 0;
        $errors   = 0;

        foreach ($dashboards as $i => $dashboard) {
            $uuid    = (string) $dashboard->getUuid();
            $current = (int) $i + 1;

            if ($uuid === '') {
                $output->writeln(
                    messages: "  [{$current}/{$total}] Dashboard id={$dashboard->getId()} has no UUID, skipping."
                );
                $skipped++;
                continue;
            }

            // Idempotent: skip when content already exists in GroupFolder.
            if ($this->groupFolderStorage->exists(uuid: $uuid) === true) {
                $output->writeln(
                    messages: "  [{$current}/{$total}] {$uuid} already migrated, skipping."
                );
                $skipped++;
                continue;
            }

            // phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
            $rawContent = $dashboard->getContent();
            // phpcs:enable

            $contentArray = [];
            if ($rawContent !== null && $rawContent !== '') {
                $decoded = json_decode(json: $rawContent, associative: true);
                if (is_array($decoded) === true) {
                    $contentArray = $decoded;
                }
            }

            try {
                $this->groupFolderStorage->write(uuid: $uuid, content: $contentArray);
                $migrated++;
                $output->writeln(messages: "  [{$current}/{$total}] Migrated {$uuid}.");

                // Optionally clear DB content after successful migration (design D3).
                if ($pruneSource === true) {
                    // phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
                    $dashboard->setContent(null);
                    // phpcs:enable
                    $this->dashboardMapper->update(entity: $dashboard);
                }
            } catch (DashboardContentStorageException $e) {
                $errors++;
                $output->writeln(
                    messages: "  [{$current}/{$total}] ERROR for {$uuid}: ".$e->getMessage()
                );
            }//end try
        }//end foreach

        $output->writeln(
            messages: "\nMigration complete: {$migrated}/{$total} migrated, {$skipped} skipped, {$errors} errors."
        );

        if ($errors > 0) {
            return CommandService::EXIT_ERROR;
        }

        return CommandService::EXIT_SUCCESS;
    }//end handle()
}//end class
