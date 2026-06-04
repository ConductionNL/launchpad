<?php

/**
 * MigrateStorageToGroupFolderCommand
 *
 * `occ mydash:storage:migrate-to-groupfolder` — REQ-GFSB-008.
 *
 * Copies all existing dashboard widget-placement content from the database
 * backend into the GroupFolder backend. Idempotent: dashboards whose content
 * already exists in the GroupFolder are skipped. Failed writes are logged
 * and counted; the command exits with code 1 when any error occurred.
 *
 * Examples:
 *   php occ mydash:storage:migrate-to-groupfolder
 *   php occ mydash:storage:migrate-to-groupfolder --prune-source
 *
 * @category Command
 * @package  OCA\MyDash\Command
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

namespace OCA\MyDash\Command;

use OCA\MyDash\Db\DashboardMapper;
use OCA\MyDash\Db\WidgetPlacementMapper;
use OCA\MyDash\Service\DashboardContentStorage\DashboardContentStorageException;
use OCA\MyDash\Service\DashboardContentStorage\GroupFolderContentStorage;
use OCA\MyDash\Service\DashboardContentStorageFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * One-time DB-to-GroupFolder dashboard content migration command.
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-9
 */
class MigrateStorageToGroupFolderCommand extends Command
{
    /**
     * Constructor.
     *
     * @param DashboardMapper                $dashboardMapper Dashboard mapper.
     * @param WidgetPlacementMapper          $placementMapper Widget placement mapper.
     * @param GroupFolderContentStorage      $gfStorage       GroupFolder storage backend.
     * @param DashboardContentStorageFactory $factory         Storage factory (for reading DB).
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-9
     */
    public function __construct(
        private readonly DashboardMapper $dashboardMapper,
        private readonly WidgetPlacementMapper $placementMapper,
        private readonly GroupFolderContentStorage $gfStorage,
        private readonly DashboardContentStorageFactory $factory,
    ) {
        parent::__construct();
    }//end __construct()

    /**
     * Configure the command.
     *
     * @return void
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-9
     */
    protected function configure(): void
    {
        $this->setName(name: 'mydash:storage:migrate-to-groupfolder')
            ->setDescription(
                description: 'Migrate all dashboard content from the database backend to the GroupFolder backend.'
                    .' Idempotent: already-migrated dashboards are skipped.'
                    .' By default, source DB placement rows are kept intact (rollback-safe).'
                    .' Use --prune-source to delete them after successful migration.'
            )
            ->addOption(
                name: 'prune-source',
                shortcut: null,
                mode: InputOption::VALUE_NONE,
                description: 'Delete DB placement rows for dashboards successfully migrated to GroupFolder.'
                    .' CANNOT be undone — only use after confirming GroupFolder is stable.'
            );
    }//end configure()

    /**
     * Execute the migration.
     *
     * @param InputInterface  $input  The console input.
     * @param OutputInterface $output The console output.
     *
     * @return int 0 on full success, 1 on partial failure.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-9
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $pruneSource = $input->getOption(name: 'prune-source') === true;
        $output->writeln('<info>MyDash: starting DB → GroupFolder content migration...</info>');

        // Collect all dashboards from the DB.
        $dashboards = $this->dashboardMapper->findAll();
        $total      = count($dashboards);
        $migrated   = 0;
        $skipped    = 0;
        $errors     = 0;

        $output->writeln(
            sprintf('<info>Found %d dashboard(s) to process.</info>', $total)
        );

        foreach ($dashboards as $dashboard) {
            $uuid = (string) ($dashboard->getUuid() ?? '');
            if ($uuid === '') {
                $output->writeln('<comment>Skipping dashboard without UUID (id='.$dashboard->getId().').</comment>');
                $skipped++;
                continue;
            }

            // Skip if already in GroupFolder (idempotent).
            if ($this->gfStorage->exists(dashboardUuid: $uuid) === true) {
                $output->writeln(
                    sprintf('<comment>Dashboard %s already migrated, skipping.</comment>', $uuid)
                );
                $skipped++;
                continue;
            }

            try {
                // Read placements from DB.
                $placements = $this->placementMapper->findByDashboardId(
                    dashboardId: $dashboard->getId()
                );
                $content    = array_map(
                    static fn($placement) => $placement->jsonSerialize(),
                    $placements
                );

                // Write to GroupFolder.
                $this->gfStorage->write(
                    dashboardUuid: $uuid,
                    content: $content
                );

                $migrated++;

                if ($pruneSource === true) {
                    $this->placementMapper->deleteByDashboardId(
                        dashboardId: $dashboard->getId()
                    );
                }

                $output->writeln(
                    sprintf(
                        '<info>Migrated %d/%d: %s (%d placement(s))</info>',
                        $migrated + $skipped + $errors,
                        $total,
                        $uuid,
                        count($content)
                    )
                );
            } catch (DashboardContentStorageException $e) {
                $errors++;
                $output->writeln(
                    sprintf('<error>Error migrating %s: %s</error>', $uuid, $e->getMessage())
                );
            } catch (Throwable $t) {
                $errors++;
                $output->writeln(
                    sprintf('<error>Unexpected error migrating %s: %s</error>', $uuid, $t->getMessage())
                );
            }//end try
        }//end foreach

        $output->writeln(
            sprintf(
                '<info>Migration complete. Migrated: %d, Skipped: %d, Errors: %d (total: %d)</info>',
                $migrated,
                $skipped,
                $errors,
                $total
            )
        );

        if ($errors > 0) {
            $output->writeln(
                '<comment>Re-run the command to retry failed dashboards.</comment>'
            );
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }//end execute()
}//end class
