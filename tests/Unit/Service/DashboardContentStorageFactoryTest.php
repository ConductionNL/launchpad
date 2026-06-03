<?php

/**
 * DashboardContentStorageFactoryTest
 *
 * Verifies that the DashboardContentStorageFactory selects the correct
 * storage implementation based on the persisted admin setting
 * (REQ-GFSB-004).
 *
 * @category  Test
 * @package   OCA\MyDash\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\MyDash\Service\DashboardContentStorage\DbContentStorage;
use OCA\MyDash\Service\DashboardContentStorage\GroupFolderContentStorage;
use OCA\MyDash\Service\DashboardContentStorageFactory;
use OCA\MyDash\Service\SetupWizardService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for {@see DashboardContentStorageFactory}.
 */
class DashboardContentStorageFactoryTest extends TestCase
{

    /**
     * Database-backed storage mock.
     *
     * @var DbContentStorage&MockObject
     */
    private $dbStorage;

    /**
     * GroupFolder-backed storage mock.
     *
     * @var GroupFolderContentStorage&MockObject
     */
    private $groupFolderStorage;

    /**
     * Setup wizard service mock.
     *
     * @var SetupWizardService&MockObject
     */
    private $wizardService;

    /**
     * Factory under test.
     *
     * @var DashboardContentStorageFactory
     */
    private DashboardContentStorageFactory $factory;

    /**
     * Set up fresh mocks and the factory under test for every test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->dbStorage          = $this->createMock(originalClassName: DbContentStorage::class);
        $this->groupFolderStorage = $this->createMock(originalClassName: GroupFolderContentStorage::class);
        $this->wizardService      = $this->createMock(originalClassName: SetupWizardService::class);

        $this->factory = new DashboardContentStorageFactory(
            dbStorage: $this->dbStorage,
            groupFolderStorage: $this->groupFolderStorage,
            wizardService: $this->wizardService
        );
    }//end setUp()

    /**
     * getStorage() MUST return the DbContentStorage instance when the
     * persisted setting value is 'database'.
     *
     * @return void
     */
    public function testGetStorageReturnsDbWhenSettingIsDatabase(): void
    {
        $this->wizardService
            ->method('getContentStorage')
            ->willReturn('database');

        $result = $this->factory->getStorage();

        $this->assertSame(expected: $this->dbStorage, actual: $result);
    }//end testGetStorageReturnsDbWhenSettingIsDatabase()

    /**
     * getStorage() MUST return the GroupFolderContentStorage instance when
     * the persisted setting value is 'groupfolder'.
     *
     * @return void
     */
    public function testGetStorageReturnsGroupFolderWhenSettingIsGroupfolder(): void
    {
        $this->wizardService
            ->method('getContentStorage')
            ->willReturn(SetupWizardService::STORAGE_GROUPFOLDER);

        $result = $this->factory->getStorage();

        $this->assertSame(expected: $this->groupFolderStorage, actual: $result);
    }//end testGetStorageReturnsGroupFolderWhenSettingIsGroupfolder()

    /**
     * getStorage() MUST fall back to the DbContentStorage instance for any
     * unrecognised setting value, ensuring a safe default.
     *
     * @return void
     */
    public function testGetStorageDefaultsToDbForUnknownValue(): void
    {
        $this->wizardService
            ->method('getContentStorage')
            ->willReturn('unknown');

        $result = $this->factory->getStorage();

        $this->assertSame(expected: $this->dbStorage, actual: $result);
    }//end testGetStorageDefaultsToDbForUnknownValue()
}//end class
