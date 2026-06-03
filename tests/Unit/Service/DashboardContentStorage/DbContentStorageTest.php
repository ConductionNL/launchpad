<?php

/**
 * DbContentStorageTest
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

use OCA\MyDash\Db\Dashboard;
use OCA\MyDash\Db\DashboardMapper;
use OCA\MyDash\Db\WidgetPlacement;
use OCA\MyDash\Db\WidgetPlacementMapper;
use OCA\MyDash\Service\DashboardContentStorage\DashboardContentStorageException;
use OCA\MyDash\Service\DashboardContentStorage\DashboardNotFoundException;
use OCA\MyDash\Service\DashboardContentStorage\DbContentStorage;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for DbContentStorage.
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-12
 */
class DbContentStorageTest extends TestCase
{

    private DashboardMapper&MockObject $dashboardMapper;
    private WidgetPlacementMapper&MockObject $placementMapper;
    private LoggerInterface&MockObject $logger;
    private DbContentStorage $storage;

    protected function setUp(): void
    {
        $this->dashboardMapper = $this->createMock(DashboardMapper::class);
        $this->placementMapper = $this->createMock(WidgetPlacementMapper::class);
        $this->logger          = $this->createMock(LoggerInterface::class);

        $this->storage = new DbContentStorage(
            dashboardMapper: $this->dashboardMapper,
            placementMapper: $this->placementMapper,
            logger: $this->logger,
        );
    }

    public function testReadReturnsMappedPlacements(): void
    {
        $dashboard = new Dashboard();
        $dashboard->setId(42);

        $placement = new WidgetPlacement();
        // phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
        $placement->setWidgetId('files');
        // phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
        $placement->setGridX(0);

        $this->dashboardMapper
            ->method('findByUuid')
            ->with(uuid: 'test-uuid')
            ->willReturn($dashboard);

        $this->placementMapper
            ->method('findByDashboardId')
            ->with(dashboardId: 42)
            ->willReturn([$placement]);

        $result = $this->storage->read(dashboardUuid: 'test-uuid');

        $this->assertCount(1, $result);
        $this->assertSame('files', $result[0]['widgetId']);
    }

    public function testReadThrowsDashboardNotFoundWhenUuidMissing(): void
    {
        $this->dashboardMapper
            ->method('findByUuid')
            ->willThrowException(new DoesNotExistException(''));

        $this->expectException(DashboardNotFoundException::class);
        $this->storage->read(dashboardUuid: 'missing-uuid');
    }

    public function testWriteIsNoOp(): void
    {
        // DB backend write is a no-op; just assert no exception is thrown.
        $this->storage->write(dashboardUuid: 'uuid', content: []);
        $this->assertTrue(true);
    }

    public function testDeleteCallsMapper(): void
    {
        $dashboard = new Dashboard();
        $dashboard->setId(7);

        $this->dashboardMapper
            ->method('findByUuid')
            ->willReturn($dashboard);

        $this->placementMapper
            ->expects($this->once())
            ->method('deleteByDashboardId')
            ->with(dashboardId: 7);

        $this->storage->delete(dashboardUuid: 'some-uuid');
    }

    public function testDeleteIsNoOpWhenDashboardNotFound(): void
    {
        $this->dashboardMapper
            ->method('findByUuid')
            ->willThrowException(new DoesNotExistException(''));

        // Should not throw.
        $this->placementMapper->expects($this->never())->method('deleteByDashboardId');
        $this->storage->delete(dashboardUuid: 'gone-uuid');
    }

    public function testExistsReturnsTrueWhenFound(): void
    {
        $dashboard = new Dashboard();
        $this->dashboardMapper
            ->method('findByUuid')
            ->willReturn($dashboard);

        $this->assertTrue($this->storage->exists(dashboardUuid: 'existing'));
    }

    public function testExistsReturnsFalseWhenNotFound(): void
    {
        $this->dashboardMapper
            ->method('findByUuid')
            ->willThrowException(new DoesNotExistException(''));

        $this->assertFalse($this->storage->exists(dashboardUuid: 'missing'));
    }

}//end class
