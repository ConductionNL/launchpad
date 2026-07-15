<?php

/**
 * PublicShareContextTest
 *
 * Unit tests for the request-scoped public-share bearer marker (Task 7 of
 * the dashboard-public-share change). Verifies `markBearer()`,
 * `isBearer()`, `getToken()`, and the `requireMutable()` guard.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @spec openspec/changes/dashboard-public-share/tasks.md#task-7
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Exception\ShareReadOnlyException;
use OCA\LaunchPad\Service\PublicShareContext;
use PHPUnit\Framework\TestCase;

class PublicShareContextTest extends TestCase
{


    public function testDefaultsToNonBearer(): void
    {
        $ctx = new PublicShareContext();
        $this->assertFalse($ctx->isBearer());
        $this->assertNull($ctx->getToken());
    }//end testDefaultsToNonBearer()


    public function testRequireMutablePassesByDefault(): void
    {
        $ctx = new PublicShareContext();
        $ctx->requireMutable();
        // No exception — control reaches here.
        $this->assertTrue(true);
    }//end testRequireMutablePassesByDefault()


    public function testMarkBearerFlipsFlagAndStoresToken(): void
    {
        $ctx = new PublicShareContext();
        $ctx->markBearer(token: 'tok_abc123');
        $this->assertTrue($ctx->isBearer());
        $this->assertSame('tok_abc123', $ctx->getToken());
    }//end testMarkBearerFlipsFlagAndStoresToken()


    public function testRequireMutableThrowsAfterMarkBearer(): void
    {
        $ctx = new PublicShareContext();
        $ctx->markBearer(token: 'tok_xyz');
        $this->expectException(ShareReadOnlyException::class);
        $ctx->requireMutable();
    }//end testRequireMutableThrowsAfterMarkBearer()
}//end class
