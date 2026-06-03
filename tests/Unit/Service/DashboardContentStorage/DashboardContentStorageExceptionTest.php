<?php

/**
 * DashboardContentStorageExceptionTest
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

use OCA\MyDash\Service\DashboardContentStorage\DashboardContentStorageException;
use OCA\MyDash\Service\DashboardContentStorage\DashboardNotFoundException;
use OCA\MyDash\Service\DashboardContentStorage\GroupFoldersNotInstalledException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the exception hierarchy.
 *
 * @spec openspec/changes/groupfolder-storage-backend/tasks.md#task-12
 */
class DashboardContentStorageExceptionTest extends TestCase
{

    public function testBaseExceptionConstants(): void
    {
        $this->assertSame(
            'dashboard_content_storage_unavailable',
            DashboardContentStorageException::ERROR_KEY
        );
        $this->assertSame(503, DashboardContentStorageException::HTTP_STATUS);
    }

    public function testDashboardNotFoundExceptionExtendsBase(): void
    {
        $ex = new DashboardNotFoundException(uuid: 'test-uuid', backend: 'db');
        $this->assertInstanceOf(DashboardContentStorageException::class, $ex);
        $this->assertStringContainsString('test-uuid', $ex->getMessage());
        $this->assertStringContainsString('db', $ex->getMessage());
    }

    public function testGroupFoldersNotInstalledExceptionExtendsBase(): void
    {
        $ex = new GroupFoldersNotInstalledException();
        $this->assertInstanceOf(DashboardContentStorageException::class, $ex);
        $this->assertSame(GroupFoldersNotInstalledException::MESSAGE, $ex->getMessage());
        $this->assertStringContainsString('groupfolders', $ex->getMessage());
    }

    public function testGroupFoldersMessageMentionsInstallSteps(): void
    {
        $ex = new GroupFoldersNotInstalledException();
        $this->assertStringContainsString('app store', $ex->getMessage());
    }

}//end class
