<?php

/**
 * GroupFolderContentStorageTest
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

use OCA\MyDash\Service\DashboardContentStorage\DashboardNotFoundException;
use OCA\MyDash\Service\DashboardContentStorage\GroupFolderContentStorage;
use OCA\MyDash\Service\DashboardContentStorage\GroupFoldersNotInstalledException;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tests for GroupFolderContentStorage.
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-12
 */
class GroupFolderContentStorageTest extends TestCase
{

    private IRootFolder&MockObject $rootFolder;
    private ContainerInterface&MockObject $container;
    private LoggerInterface&MockObject $logger;
    private GroupFolderContentStorage $storage;

    protected function setUp(): void
    {
        $this->rootFolder = $this->createMock(IRootFolder::class);
        $this->container  = $this->createMock(ContainerInterface::class);
        $this->logger     = $this->createMock(LoggerInterface::class);

        $this->storage = new GroupFolderContentStorage(
            rootFolder: $this->rootFolder,
            container: $this->container,
            logger: $this->logger,
        );
    }

    public function testResolvePathNoLocale(): void
    {
        $path = $this->storage->resolvePath(dashboardUuid: 'abc-123');
        $this->assertSame('LaunchPad/abc-123.json', $path);
    }

    public function testResolvePathWithLocale(): void
    {
        $path = $this->storage->resolvePath(dashboardUuid: 'abc-123', locale: 'nl');
        $this->assertSame('LaunchPad/nl/abc-123.json', $path);
    }

    public function testReadThrowsGroupFoldersNotInstalledWhenAppAbsent(): void
    {
        $this->container
            ->method('has')
            ->with('OCA\\GroupFolders\\Folder\\FolderManager')
            ->willReturn(false);

        $this->expectException(GroupFoldersNotInstalledException::class);
        $this->storage->read(dashboardUuid: 'uuid');
    }

    public function testWriteThrowsGroupFoldersNotInstalledWhenAppAbsent(): void
    {
        $this->container
            ->method('has')
            ->with('OCA\\GroupFolders\\Folder\\FolderManager')
            ->willReturn(false);

        $this->expectException(GroupFoldersNotInstalledException::class);
        $this->storage->write(dashboardUuid: 'uuid', content: []);
    }

    public function testDeleteThrowsGroupFoldersNotInstalledWhenAppAbsent(): void
    {
        $this->container
            ->method('has')
            ->with('OCA\\GroupFolders\\Folder\\FolderManager')
            ->willReturn(false);

        $this->expectException(GroupFoldersNotInstalledException::class);
        $this->storage->delete(dashboardUuid: 'uuid');
    }

    public function testExistsReturnsFalseWhenAppAbsent(): void
    {
        $this->container
            ->method('has')
            ->with('OCA\\GroupFolders\\Folder\\FolderManager')
            ->willReturn(false);

        $this->assertFalse($this->storage->exists(dashboardUuid: 'uuid'));
    }

    public function testFolderNameConstant(): void
    {
        $this->assertSame('LaunchPad', GroupFolderContentStorage::FOLDER_NAME);
    }

    public function testReadSuccessWithInstalledApp(): void
    {
        $content  = [['widgetId' => 'files']];
        $jsonData = json_encode($content);

        $file = $this->createMock(\OCP\Files\File::class);
        $file->method('getContent')->willReturn($jsonData);

        $folder = $this->createMock(Folder::class);
        // First call: uuid.json path succeeds.
        $folder->method('get')->willReturn($file);

        $this->rootFolder
            ->method('get')
            ->willReturn($folder);

        $manager = $this->createMock(\stdClass::class);

        $this->container
            ->method('has')
            ->willReturn(true);
        $this->container
            ->method('get')
            ->willReturn($this->buildFolderManager(folderId: 1));

        // Use a fresh storage with mocked ensureLaunchPadGroupFolder bypass.
        $storage = $this->getMockBuilder(GroupFolderContentStorage::class)
            ->setConstructorArgs([
                'rootFolder'   => $this->rootFolder,
                'groupManager' => $this->groupManager,
                'appConfig'    => $this->appConfig,
                'container'    => $this->container,
                'logger'       => $this->logger,
            ])
            ->onlyMethods(['ensureLaunchPadGroupFolder'])
            ->getMock();

        $storage->method('ensureLaunchPadGroupFolder')->willReturn(1);

        $result = $storage->read(dashboardUuid: 'my-uuid');
        $this->assertSame($content, $result);
    }

    /**
     * Build a FolderManager-like object for injection.
     *
     * @param int $folderId The folder ID to return.
     *
     * @return object
     */
    private function buildFolderManager(int $folderId): object
    {
        return new class ($folderId) {
            public function __construct(private readonly int $folderId)
            {
            }

            public function getFolders(): array
            {
                return [['mount_point' => 'LaunchPad', 'id' => $this->folderId]];
            }

            public function createFolder(string $mountPoint): int
            {
                return $this->folderId;
            }

            public function addApplicableGroup(int $folderId, string $group): void
            {
            }
        };
    }

}//end class
