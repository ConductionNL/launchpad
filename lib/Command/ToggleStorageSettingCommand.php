<?php

/**
 * ToggleStorageSettingCommand
 *
 * `occ mydash:storage:toggle-backend {db|groupfolder}` — REQ-GFSB-010.
 *
 * Convenience command that changes the `launchpad.content_storage` admin
 * setting without needing to open the admin UI. When switching back to `db`
 * from `groupfolder`, the command emits a warning reminding the admin that
 * GroupFolder content is NOT automatically copied back to the database.
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
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-10
 */

declare(strict_types=1);

namespace OCA\MyDash\Command;

use InvalidArgumentException;
use OCA\MyDash\Service\DashboardContentStorageFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Toggle the active content-storage backend setting via CLI.
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-10
 */
class ToggleStorageSettingCommand extends Command
{
    /**
     * Constructor.
     *
     * @param DashboardContentStorageFactory $factory Storage factory (persists
     *                                                the setting).
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-10
     */
    public function __construct(
        private readonly DashboardContentStorageFactory $factory,
    ) {
        parent::__construct();
    }//end __construct()

    /**
     * Configure the command.
     *
     * @return void
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-10
     */
    protected function configure(): void
    {
        $this->setName(name: 'mydash:storage:toggle-backend')
            ->setDescription(
                description: "Change the active dashboard content storage backend. "
                    ."Valid values: 'db' (default) or 'groupfolder'.\n"
                    ."WARNING: switching back to 'db' does NOT copy GroupFolder content to the database. "
                    ."Dashboards whose content is only in GroupFolder will return HTTP 503 until migrated back."
            )
            ->addArgument(
                name: 'backend',
                mode: InputArgument::REQUIRED,
                description: "The target backend: 'db' or 'groupfolder'."
            );
    }//end configure()

    /**
     * Execute the toggle.
     *
     * @param InputInterface  $input  The console input.
     * @param OutputInterface $output The console output.
     *
     * @return int 0 on success, 1 on invalid backend.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-10
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $backend = (string) ($input->getArgument(name: 'backend') ?? '');

        try {
            $current = $this->factory->getBackendSetting();

            $this->factory->setBackendSetting(backend: $backend);

            // Emit a clear warning when switching back to DB from GroupFolder.
            if ($current === DashboardContentStorageFactory::BACKEND_GROUPFOLDER
                && $backend === DashboardContentStorageFactory::BACKEND_DB
            ) {
                $output->writeln(
                    '<comment>WARNING: Switched back to the database backend.</comment>'
                );
                $output->writeln(
                    '<comment>GroupFolder content is NOT automatically copied back to the database.</comment>'
                );
                $output->writeln(
                    '<comment>Dashboards whose content exists only in the GroupFolder will return HTTP 503.</comment>'
                );
                $output->writeln(
                    '<comment>Run mydash:storage:migrate-to-groupfolder in the opposite direction to restore content.</comment>'
                );
            }

            $output->writeln(
                sprintf(
                    '<info>Content storage backend changed from "%s" to "%s".</info>',
                    $current,
                    $backend
                )
            );

            return Command::SUCCESS;
        } catch (InvalidArgumentException $e) {
            $output->writeln('<error>'.$e->getMessage().'</error>');
            return Command::FAILURE;
        }//end try
    }//end execute()
}//end class
