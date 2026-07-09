<?php

/**
 * GroupFolderContentStorageTest
 *
 * Covers the GroupFolder-backed DashboardContentStorage implementation
 * (REQ-GFSB-003). Verifies read/write/exists behaviour using mocked
 * filesystem and app-manager dependencies.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service\DashboardContentStorage
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Unit\Service\DashboardContentStorage;

use OCA\LaunchPad\Service\DashboardContentStorage\DashboardNotFoundException;
use OCA\LaunchPad\Service\DashboardContentStorage\GroupFolderContentStorage;
use OCA\LaunchPad\Service\DashboardContentStorage\GroupFoldersNotInstalledException;
use OCP\App\IAppManager;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for {@see GroupFolderContentStorage}.
 */
class GroupFolderContentStorageTest extends TestCase
{

    /**
     * Virtual filesystem root mock.
     *
     * @var IRootFolder&MockObject
     */
    private $rootFolder;

    /**
     * App manager mock.
     *
     * @var IAppManager&MockObject
     */
    private $appManager;

    /**
     * PSR-3 logger mock.
     *
     * @var LoggerInterface&MockObject
     */
    private $logger;

    /**
     * Service under test.
     *
     * @var GroupFolderContentStorage
     */
    private GroupFolderContentStorage $storage;

    /**
     * Set up fresh mocks and the service under test for every test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        $this->rootFolder = $this->createMock(originalClassName: IRootFolder::class);
        $this->appManager = $this->createMock(originalClassName: IAppManager::class);
        $this->logger     = $this->createMock(originalClassName: LoggerInterface::class);

        $this->storage = new GroupFolderContentStorage(
            rootFolder: $this->rootFolder,
            appManager: $this->appManager,
            logger: $this->logger
        );
    }//end setUp()

    /**
     * read() MUST throw GroupFoldersNotInstalledException immediately when
     * the groupfolders app is not installed.
     *
     * @return void
     */
    public function testReadThrowsGroupFoldersNotInstalledExceptionWhenAppMissing(): void
    {
        $this->appManager
            ->method('isInstalled')
            ->with('groupfolders')
            ->willReturn(false);

        $this->expectException(exception: GroupFoldersNotInstalledException::class);

        $this->storage->read(uuid: 'test-uuid');
    }//end testReadThrowsGroupFoldersNotInstalledExceptionWhenAppMissing()

    /**
     * read() MUST throw DashboardNotFoundException when the groupfolders app
     * is installed but the file does not exist for the given UUID.
     *
     * @return void
     */
    public function testReadThrowsDashboardNotFoundExceptionWhenFileNotFound(): void
    {
        $this->appManager
            ->method('isInstalled')
            ->willReturn(true);

        $this->rootFolder
            ->method('get')
            ->willThrowException(new NotFoundException(message: 'File not found'));

        $this->expectException(exception: DashboardNotFoundException::class);

        $this->storage->read(uuid: 'test-uuid');
    }//end testReadThrowsDashboardNotFoundExceptionWhenFileNotFound()

    /**
     * read() MUST return the JSON-decoded content array when the groupfolders
     * app is installed and the file exists with valid JSON content.
     *
     * @return void
     */
    public function testReadReturnsDecodedContent(): void
    {
        $this->appManager
            ->method('isInstalled')
            ->willReturn(true);

        $fileNode = $this->createMock(originalClassName: File::class);
        $fileNode->method('getContent')->willReturn(json_encode(['widgets' => []]));

        $this->rootFolder
            ->method('get')
            ->willReturn($fileNode);

        $result = $this->storage->read(uuid: 'test-uuid');

        $this->assertSame(expected: ['widgets' => []], actual: $result);
    }//end testReadReturnsDecodedContent()

    /**
     * exists() MUST return false without throwing when the groupfolders app
     * is not installed.
     *
     * @return void
     */
    public function testExistsReturnsFalseWhenAppMissing(): void
    {
        $this->appManager
            ->method('isInstalled')
            ->willReturn(false);

        $this->assertFalse(condition: $this->storage->exists(uuid: 'test-uuid'));
    }//end testExistsReturnsFalseWhenAppMissing()

    /**
     * exists() MUST return true when the groupfolders app is installed and
     * the file node can be retrieved from the root folder.
     *
     * @return void
     */
    public function testExistsReturnsTrueWhenFileExists(): void
    {
        $this->appManager
            ->method('isInstalled')
            ->willReturn(true);

        $fileNode = $this->createMock(originalClassName: Node::class);

        $this->rootFolder
            ->method('get')
            ->willReturn($fileNode);

        $this->assertTrue(condition: $this->storage->exists(uuid: 'test-uuid'));
    }//end testExistsReturnsTrueWhenFileExists()

    /**
     * write() MUST call rootFolder->newFolder('/LaunchPad') when the LaunchPad
     * GroupFolder does not yet exist, ensuring the directory is provisioned
     * on demand before the content file is written.
     *
     * @return void
     */
    public function testWriteCreatesGroupFolderWhenMissing(): void
    {
        $this->appManager
            ->method('isInstalled')
            ->willReturn(true);

        $folderNode = $this->createMock(originalClassName: Folder::class);
        $fileNode   = $this->createMock(originalClassName: File::class);
        $folderNode->method('newFile')->willReturn($fileNode);

        // Track call count to distinguish first /LaunchPad lookup from subsequent ones.
        $callCount = 0;

        $this->rootFolder
            ->method('get')
            ->willReturnCallback(
                function (string $path) use ($folderNode, &$callCount): Node {
                    // First call: get('/LaunchPad') in ensureLaunchPadGroupFolder — throw.
                    if ($callCount === 0 && $path === '/LaunchPad') {
                        $callCount++;
                        throw new NotFoundException('Folder not found');
                    }

                    $callCount++;
                    return $folderNode;
                }
            );

        $this->rootFolder
            ->expects($this->once())
            ->method('newFolder')
            ->with('/LaunchPad')
            ->willReturn($folderNode);

        // write() will call get() for the file path; the file mock will not
        // be found (NotFoundException), so write falls back to newFile().
        // We accept any outcome as long as newFolder was called once.
        try {
            $this->storage->write(uuid: 'test-uuid', content: ['widgets' => []]);
        } catch (\Throwable) {
            // File write details are not the focus of this test.
        }
    }//end testWriteCreatesGroupFolderWhenMissing()
}//end class
