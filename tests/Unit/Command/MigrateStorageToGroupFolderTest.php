<?php

/**
 * MigrateStorageToGroupFolderTest
 *
 * Covers the one-time DB-to-GroupFolder migration command
 * (REQ-GFSB-008). Verifies the migrate/skip/error branches of the
 * handle() loop using mocked DashboardMapper and GroupFolderContentStorage.
 *
 * @category  Test
 * @package   OCA\MyDash\Tests\Unit\Command
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Unit\Command;

use OCA\MyDash\Command\MigrateStorageToGroupFolder;
use OCA\MyDash\Db\Dashboard;
use OCA\MyDash\Db\DashboardMapper;
use OCA\MyDash\Service\CommandService;
use OCA\MyDash\Service\DashboardContentStorage\DashboardContentStorageException;
use OCA\MyDash\Service\DashboardContentStorage\GroupFolderContentStorage;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Expose the protected handle() method for testing.
 */
class TestableMigrateStorageToGroupFolder extends MigrateStorageToGroupFolder
{
    /**
     * Make handle() publicly callable for unit tests.
     *
     * @param InputInterface  $input  CLI input.
     * @param OutputInterface $output CLI output.
     *
     * @return int Exit code.
     */
    public function publicHandle(InputInterface $input, OutputInterface $output): int
    {
        return $this->handle(input: $input, output: $output);
    }//end publicHandle()
}//end class

/**
 * Unit tests for {@see MigrateStorageToGroupFolder}.
 */
class MigrateStorageToGroupFolderTest extends TestCase
{

    /**
     * Command service mock.
     *
     * @var CommandService&MockObject
     */
    private $commandService;

    /**
     * User session mock.
     *
     * @var IUserSession&MockObject
     */
    private $userSession;

    /**
     * Dashboard mapper mock.
     *
     * @var DashboardMapper&MockObject
     */
    private $dashboardMapper;

    /**
     * GroupFolder storage mock.
     *
     * @var GroupFolderContentStorage&MockObject
     */
    private $groupFolderStorage;

    /**
     * Command under test (with public handle() access).
     *
     * @var TestableMigrateStorageToGroupFolder
     */
    private TestableMigrateStorageToGroupFolder $command;

    /**
     * Set up fresh mocks and the command under test for every test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $logger = $this->createMock(originalClassName: LoggerInterface::class);

        $this->commandService     = new CommandService(logger: $logger);
        $this->userSession        = $this->createMock(originalClassName: IUserSession::class);
        $this->dashboardMapper    = $this->createMock(originalClassName: DashboardMapper::class);
        $this->groupFolderStorage = $this->createMock(originalClassName: GroupFolderContentStorage::class);

        $this->command = new TestableMigrateStorageToGroupFolder(
            commandService: $this->commandService,
            userSession: $this->userSession,
            dashboardMapper: $this->dashboardMapper,
            groupFolderStorage: $this->groupFolderStorage
        );
    }//end setUp()

    /**
     * Build a Dashboard entity stub with the given UUID and raw content JSON.
     *
     * @param string      $uuid       The dashboard UUID.
     * @param string|null $rawContent Raw JSON string for the content column.
     *
     * @return Dashboard The configured dashboard entity.
     */
    private function makeDashboard(string $uuid, ?string $rawContent): Dashboard
    {
        $dashboard = new Dashboard();
        // phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
        $dashboard->setUuid($uuid);
        $dashboard->setContent($rawContent);
        // phpcs:enable
        return $dashboard;
    }//end makeDashboard()

    /**
     * Build a mock InputInterface that returns false for the prune-source option.
     *
     * @return InputInterface&MockObject The configured input mock.
     */
    private function makeInput(): InputInterface
    {
        $input = $this->createMock(originalClassName: InputInterface::class);
        $input->method('getOption')->willReturn(false);
        return $input;
    }//end makeInput()

    /**
     * handle() MUST call groupFolderStorage->write() once per dashboard when
     * all dashboards have valid UUIDs and non-empty content, and MUST exit
     * with EXIT_SUCCESS.
     *
     * @return void
     */
    public function testHandleMigratesAllDashboards(): void
    {
        $dashboards = [
            $this->makeDashboard(uuid: 'uuid-1', rawContent: json_encode(['key' => 'a'])),
            $this->makeDashboard(uuid: 'uuid-2', rawContent: json_encode(['key' => 'b'])),
        ];

        $this->dashboardMapper
            ->method('findAll')
            ->willReturn($dashboards);

        $this->groupFolderStorage
            ->method('exists')
            ->willReturn(false);

        $this->groupFolderStorage
            ->expects($this->exactly(2))
            ->method('write');

        $output = $this->createMock(originalClassName: OutputInterface::class);

        $exitCode = $this->command->publicHandle(
            input: $this->makeInput(),
            output: $output
        );

        $this->assertSame(expected: CommandService::EXIT_SUCCESS, actual: $exitCode);
    }//end testHandleMigratesAllDashboards()

    /**
     * handle() MUST skip dashboards that are already present in the GroupFolder
     * backend (idempotent migration) and MUST exit with EXIT_SUCCESS.
     *
     * @return void
     */
    public function testHandleSkipsAlreadyMigratedDashboards(): void
    {
        $dashboards = [
            $this->makeDashboard(uuid: 'uuid-1', rawContent: json_encode(['key' => 'a'])),
        ];

        $this->dashboardMapper
            ->method('findAll')
            ->willReturn($dashboards);

        $this->groupFolderStorage
            ->method('exists')
            ->willReturn(true);

        $this->groupFolderStorage
            ->expects($this->never())
            ->method('write');

        $output = $this->createMock(originalClassName: OutputInterface::class);

        $exitCode = $this->command->publicHandle(
            input: $this->makeInput(),
            output: $output
        );

        $this->assertSame(expected: CommandService::EXIT_SUCCESS, actual: $exitCode);
    }//end testHandleSkipsAlreadyMigratedDashboards()

    /**
     * handle() MUST exit with EXIT_ERROR when at least one dashboard fails
     * to write to the GroupFolder backend.
     *
     * @return void
     */
    public function testHandleExitsWithErrorOnPartialFailure(): void
    {
        $dashboards = [
            $this->makeDashboard(uuid: 'uuid-1', rawContent: json_encode(['key' => 'a'])),
        ];

        $this->dashboardMapper
            ->method('findAll')
            ->willReturn($dashboards);

        $this->groupFolderStorage
            ->method('exists')
            ->willReturn(false);

        $this->groupFolderStorage
            ->method('write')
            ->willThrowException(
                new DashboardContentStorageException(message: 'Write failed')
            );

        $output = $this->createMock(originalClassName: OutputInterface::class);

        $exitCode = $this->command->publicHandle(
            input: $this->makeInput(),
            output: $output
        );

        $this->assertSame(expected: CommandService::EXIT_ERROR, actual: $exitCode);
    }//end testHandleExitsWithErrorOnPartialFailure()
}//end class
