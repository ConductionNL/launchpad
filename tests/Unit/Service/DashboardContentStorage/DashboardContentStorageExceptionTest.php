<?php

/**
 * DashboardContentStorageExceptionTest
 *
 * Verifies the exception hierarchy for the DashboardContentStorage subsystem.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service\DashboardContentStorage
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Unit\Service\DashboardContentStorage;

use OCA\LaunchPad\Service\DashboardContentStorage\DashboardContentStorageException;
use OCA\LaunchPad\Service\DashboardContentStorage\DashboardNotFoundException;
use OCA\LaunchPad\Service\DashboardContentStorage\GroupFoldersNotInstalledException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the DashboardContentStorage exception hierarchy.
 */
class DashboardContentStorageExceptionTest extends TestCase
{

    /**
     * DashboardNotFoundException MUST extend DashboardContentStorageException
     * so callers can catch either the base or the specific type.
     *
     * @return void
     */
    public function testDashboardNotFoundExceptionExtendsDashboardContentStorageException(): void
    {
        $exception = new DashboardNotFoundException(message: 'not found');
        $this->assertInstanceOf(
            expected: DashboardContentStorageException::class,
            actual: $exception
        );
    }//end testDashboardNotFoundExceptionExtendsDashboardContentStorageException()

    /**
     * GroupFoldersNotInstalledException MUST extend DashboardContentStorageException
     * so callers can catch the base type for all storage failures.
     *
     * @return void
     */
    public function testGroupFoldersNotInstalledExceptionExtendsDashboardContentStorageException(): void
    {
        $exception = new GroupFoldersNotInstalledException();
        $this->assertInstanceOf(
            expected: DashboardContentStorageException::class,
            actual: $exception
        );
    }//end testGroupFoldersNotInstalledExceptionExtendsDashboardContentStorageException()

    /**
     * GroupFoldersNotInstalledException::MESSAGE MUST be a non-empty string
     * that admins can read in logs and UI notifications.
     *
     * @return void
     */
    public function testGroupFoldersNotInstalledExceptionMessageConstantIsNonEmpty(): void
    {
        $this->assertIsString(actual: GroupFoldersNotInstalledException::MESSAGE);
        $this->assertNotEmpty(actual: GroupFoldersNotInstalledException::MESSAGE);
    }//end testGroupFoldersNotInstalledExceptionMessageConstantIsNonEmpty()

    /**
     * Constructing GroupFoldersNotInstalledException with no arguments MUST
     * produce an exception whose message equals the MESSAGE constant.
     *
     * @return void
     */
    public function testGroupFoldersNotInstalledExceptionMessageMatchesConstant(): void
    {
        $exception = new GroupFoldersNotInstalledException();
        $this->assertSame(
            expected: GroupFoldersNotInstalledException::MESSAGE,
            actual: $exception->getMessage()
        );
    }//end testGroupFoldersNotInstalledExceptionMessageMatchesConstant()
}//end class
