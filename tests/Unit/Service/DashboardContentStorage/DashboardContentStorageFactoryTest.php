<?php

/**
 * DashboardContentStorageFactoryTest
 *
 * @category Test
 * @package  OCA\MyDash\Tests\Unit\Service\DashboardContentStorage
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-12
 */

declare(strict_types=1);

namespace Unit\Service\DashboardContentStorage;

use OCA\MyDash\Db\AdminSetting;
use OCA\MyDash\Db\AdminSettingMapper;
use OCA\MyDash\Service\DashboardContentStorage\DbContentStorage;
use OCA\MyDash\Service\DashboardContentStorage\GroupFolderContentStorage;
use OCA\MyDash\Service\DashboardContentStorageFactory;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DashboardContentStorageFactory.
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-12
 */
class DashboardContentStorageFactoryTest extends TestCase
{

    private AdminSettingMapper&MockObject $settingMapper;
    private DbContentStorage&MockObject $dbStorage;
    private GroupFolderContentStorage&MockObject $gfStorage;
    private DashboardContentStorageFactory $factory;

    protected function setUp(): void
    {
        $this->settingMapper = $this->createMock(AdminSettingMapper::class);
        $this->dbStorage     = $this->createMock(DbContentStorage::class);
        $this->gfStorage     = $this->createMock(GroupFolderContentStorage::class);

        $this->factory = new DashboardContentStorageFactory(
            settingMapper: $this->settingMapper,
            dbStorage: $this->dbStorage,
            gfStorage: $this->gfStorage,
        );
    }

    public function testGetStorageReturnsDbStorageByDefault(): void
    {
        $this->settingMapper
            ->method('findByKey')
            ->willThrowException(new DoesNotExistException(''));

        $storage = $this->factory->getStorage();
        $this->assertSame($this->dbStorage, $storage);
    }

    public function testGetStorageReturnsGroupFolderStorageWhenConfigured(): void
    {
        $setting = $this->createMock(AdminSetting::class);
        $setting->method('getValueDecoded')->willReturn('groupfolder');

        $this->settingMapper
            ->method('findByKey')
            ->willReturn($setting);

        $storage = $this->factory->getStorage();
        $this->assertSame($this->gfStorage, $storage);
    }

    public function testGetStorageReturnsDbStorageForDbValue(): void
    {
        $setting = $this->createMock(AdminSetting::class);
        $setting->method('getValueDecoded')->willReturn('db');

        $this->settingMapper
            ->method('findByKey')
            ->willReturn($setting);

        $storage = $this->factory->getStorage();
        $this->assertSame($this->dbStorage, $storage);
    }

    public function testGetStorageReturnsDbForInvalidValue(): void
    {
        $setting = $this->createMock(AdminSetting::class);
        $setting->method('getValueDecoded')->willReturn('invalid');

        $this->settingMapper
            ->method('findByKey')
            ->willReturn($setting);

        $storage = $this->factory->getStorage();
        $this->assertSame($this->dbStorage, $storage);
    }

    public function testGetBackendSettingReturnsDbByDefault(): void
    {
        $this->settingMapper
            ->method('findByKey')
            ->willThrowException(new DoesNotExistException(''));

        $this->assertSame('db', $this->factory->getBackendSetting());
    }

    public function testSetBackendSettingPersistsValue(): void
    {
        $this->settingMapper
            ->expects($this->once())
            ->method('setSetting')
            ->with(
                key: 'content_storage',
                value: 'groupfolder'
            );

        $this->factory->setBackendSetting(backend: 'groupfolder');
    }

    public function testSetBackendSettingThrowsOnInvalidValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->factory->setBackendSetting(backend: 'redis');
    }

    public function testValidBackendsConstant(): void
    {
        $this->assertContains('db', DashboardContentStorageFactory::VALID_BACKENDS);
        $this->assertContains('groupfolder', DashboardContentStorageFactory::VALID_BACKENDS);
    }

}//end class
