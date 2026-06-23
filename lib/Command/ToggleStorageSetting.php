<?php

/**
 * ToggleStorageSetting
 *
 * `launchpad:storage:toggle-backend {db|groupfolder}` — changes the active
 * content storage backend by writing to the `content_storage` admin setting.
 * Emits a warning when switching from GroupFolder back to DB since DB data
 * is not auto-copied from GroupFolder (REQ-GFSB-010).
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
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-10
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Command;

use OCA\LaunchPad\Service\CommandService;
use OCA\LaunchPad\Service\SetupWizardService;
use OCP\IUserSession;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Toggle the content storage backend via the CLI (REQ-GFSB-010).
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-10
 */
class ToggleStorageSetting extends CommandBase
{
    /**
     * Constructor.
     *
     * @param CommandService     $commandService Shared CLI helper.
     * @param IUserSession       $userSession    Caller resolution.
     * @param SetupWizardService $wizardService  Admin setting writer.
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-10
     */
    public function __construct(
        CommandService $commandService,
        IUserSession $userSession,
        private readonly SetupWizardService $wizardService,
    ) {
        parent::__construct(
            commandService: $commandService,
            userSession: $userSession
        );
    }//end __construct()

    /**
     * Wire command name, description, and arguments.
     *
     * @return void
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-10
     */
    protected function configureCommand(): void
    {
        $this->setName(name: 'launchpad:storage:toggle-backend')
            ->setDescription(description: 'Change the active content storage backend (db|groupfolder).')
            ->setHelp(
                help: implode(
                    separator: "\n",
                    array: [
                        'Changes the `launchpad.content_storage` admin setting.',
                        '',
                        'Valid backends: db, groupfolder',
                        '',
                        '  db          — Store dashboard content in the database (default).',
                        '  groupfolder — Store dashboard content in the "LaunchPad" GroupFolder.',
                        '',
                        'WARNING: Switching back from groupfolder to db does NOT auto-copy',
                        'GroupFolder data back to the database. Run the migration first or',
                        'ensure DB content is intact (migration keeps DB copies by default).',
                        '',
                        'Run php occ launchpad:storage:migrate-to-groupfolder before switching',
                        'to groupfolder to ensure all existing dashboards are available.',
                    ]
                )
            )
            ->addArgument(
                name: 'backend',
                mode: InputArgument::REQUIRED,
                description: 'Target backend: "db" or "groupfolder".'
            );
    }//end configureCommand()

    /**
     * Execute the backend toggle.
     *
     * @param InputInterface  $input  CLI input.
     * @param OutputInterface $output CLI output.
     *
     * @return int Exit code (0 = success, 1 = invalid argument).
     *
     * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-10
     */
    protected function handle(InputInterface $input, OutputInterface $output): int
    {
        $requested = (string) $input->getArgument(name: 'backend');
        $current   = $this->wizardService->getContentStorage();

        // Map CLI aliases to internal constants.
        $targetMap = [
            'db'          => SetupWizardService::STORAGE_DATABASE,
            'database'    => SetupWizardService::STORAGE_DATABASE,
            'groupfolder' => SetupWizardService::STORAGE_GROUPFOLDER,
        ];

        if (array_key_exists(key: $requested, array: $targetMap) === false) {
            $output->writeln(
                messages: '<error>Invalid backend: "'.$requested.'". Use "db" or "groupfolder".</error>'
            );
            return CommandService::EXIT_ERROR;
        }

        $target = $targetMap[$requested];

        if ($target === $current) {
            $output->writeln(messages: 'Backend is already set to "'.$current.'". No change.');
            return CommandService::EXIT_SUCCESS;
        }

        // Warn when switching back from groupfolder to DB.
        if ($current === SetupWizardService::STORAGE_GROUPFOLDER
            && $target === SetupWizardService::STORAGE_DATABASE
        ) {
            $output->writeln(
                messages: implode(
                    separator: "\n",
                    array: [
                        '<comment>WARNING: Switching from groupfolder to db.</comment>',
                        '<comment>GroupFolder content is NOT auto-copied back to the database.</comment>',
                        '<comment>Ensure database content is intact before proceeding.</comment>',
                    ]
                )
            );
        }

        $this->wizardService->setContentStorage(value: $target);

        $output->writeln(messages: 'Storage backend changed from "'.$current.'" to "'.$target.'".');
        return CommandService::EXIT_SUCCESS;
    }//end handle()
}//end class
