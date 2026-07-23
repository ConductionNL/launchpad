<?php

/**
 * AdminControllerResyncTemplateTest
 *
 * Controller-level tests for `POST /api/admin/templates/{id}/resync`
 * (REQ-RESYNC-001). Covers the admin guard and the 400 mapping for
 * `InvalidArgumentException` (invalid strategy / non-template dashboard);
 * the diff/strategy semantics are covered exhaustively in
 * {@see \Unit\Service\TemplateResyncServiceTest}.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace Unit\Controller;

use InvalidArgumentException;
use OCA\LaunchPad\Controller\AdminController;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\ExportService;
use OCA\LaunchPad\Service\FeedRefreshService;
use OCA\LaunchPad\Service\FooterService;
use OCA\LaunchPad\Service\ImportService;
use OCA\LaunchPad\Service\RoleService;
use OCA\LaunchPad\Service\SetupWizardService;
use OCA\LaunchPad\Service\TemplateResyncService;
use OCP\AppFramework\Http;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdminControllerResyncTemplateTest extends TestCase
{
    /** @var IRequest&MockObject */
    private $request;

    /** @var IGroupManager&MockObject */
    private $groupManager;

    /** @var IUserSession&MockObject */
    private $userSession;

    /** @var TemplateResyncService&MockObject */
    private $resyncService;

    private AdminController $controller;

    protected function setUp(): void
    {
        $this->request       = $this->createMock(IRequest::class);
        $this->groupManager  = $this->createMock(IGroupManager::class);
        $this->userSession   = $this->createMock(IUserSession::class);
        $this->resyncService = $this->createMock(TemplateResyncService::class);

        $this->controller = new AdminController(
            request: $this->request,
            templateService: $this->createMock(AdminTemplateService::class),
            settingsService: $this->createMock(AdminSettingsService::class),
            groupManager: $this->groupManager,
            userSession: $this->userSession,
            exportService: $this->createMock(ExportService::class),
            importService: $this->createMock(ImportService::class),
            roleService: $this->createMock(RoleService::class),
            feedRefresh: $this->createMock(FeedRefreshService::class),
            footerService: $this->createMock(FooterService::class),
            setupWizardService: $this->createMock(SetupWizardService::class),
            actionAuth: $this->createMock(ActionAuthService::class),
            resyncService: $this->resyncService,
        );
    }

    private function loginAsAdmin(): void
    {
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('admin1');
        $this->userSession->method('getUser')->willReturn($user);
        $this->groupManager->method('isAdmin')->with('admin1')->willReturn(true);
    }

    private function loginAsNonAdmin(): void
    {
        $user = $this->createMock(IUser::class);
        $user->method('getUID')->willReturn('alice');
        $this->userSession->method('getUser')->willReturn($user);
        $this->groupManager->method('isAdmin')->with('alice')->willReturn(false);
    }

    /**
     * REQ-RESYNC-001 "Non-admin cannot re-sync" — no user copy is
     * touched, the service is never invoked.
     */
    public function testNonAdminCannotResync(): void
    {
        $this->loginAsNonAdmin();
        $this->resyncService->expects($this->never())->method('resync');

        $response = $this->controller->resyncTemplate(id: 1, strategy: 'overwrite', dryRun: true);

        $this->assertSame(Http::STATUS_FORBIDDEN, $response->getStatus());
    }

    /**
     * REQ-RESYNC-001 "Invalid strategy is rejected" — the service's
     * InvalidArgumentException maps to HTTP 400.
     */
    public function testInvalidStrategyMapsTo400(): void
    {
        $this->loginAsAdmin();
        $this->resyncService->method('resync')
            ->willThrowException(new InvalidArgumentException(
                'Invalid strategy: only "overwrite" and "merge" are accepted'
            ));

        $response = $this->controller->resyncTemplate(id: 1, strategy: 'replace-all', dryRun: true);

        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
    }

    /**
     * REQ-RESYNC-001 "Re-sync rejects a non-template dashboard".
     */
    public function testNonTemplateDashboardMapsTo400(): void
    {
        $this->loginAsAdmin();
        $this->resyncService->method('resync')
            ->willThrowException(new InvalidArgumentException('Not an admin template'));

        $response = $this->controller->resyncTemplate(id: 5, strategy: 'overwrite', dryRun: true);

        $this->assertSame(Http::STATUS_BAD_REQUEST, $response->getStatus());
    }

    /**
     * Happy path — the admin's acting user ID is forwarded to the
     * service and the dry-run report is returned as-is.
     */
    public function testDryRunForwardsActingAdminAndReturnsThePlan(): void
    {
        $this->loginAsAdmin();

        $plan = [
            'templateId'    => 1,
            'strategy'      => 'merge',
            'dryRun'        => true,
            'async'         => false,
            'totalCopies'   => 8,
            'affectedCount' => 3,
            'copies'        => [],
        ];

        $this->resyncService->expects($this->once())
            ->method('resync')
            ->with(
                templateId: 1,
                strategy: 'merge',
                dryRun: true,
                actingAdminId: 'admin1'
            )
            ->willReturn($plan);

        $response = $this->controller->resyncTemplate(id: 1, strategy: 'merge', dryRun: true);

        $this->assertSame(Http::STATUS_OK, $response->getStatus());
        $this->assertSame($plan, $response->getData());
    }
}
