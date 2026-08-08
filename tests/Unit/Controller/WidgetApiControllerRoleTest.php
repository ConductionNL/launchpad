<?php

/**
 * WidgetApiControllerRoleTest
 *
 * Covers the role-feature-permission filtering logic added to
 * `WidgetApiController` by the `role-based-content` change (Tasks 3+4):
 *
 *  - allowedWidgets = null (unconfigured) → full widget list returned unchanged.
 *  - allowedWidgets = ["activity"] → only "activity" in list response.
 *  - getItems() for a restricted widget → HTTP 403 + audit log entry written.
 *  - getItems() for mixed allowed/denied widgets → 403 if all denied, else
 *    only allowed items returned.
 *
 * @category Test
 * @package  OCA\LaunchPad\Tests\Unit\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/role-based-content/tasks.md#task-8
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\WidgetApiController;
use OCA\LaunchPad\Service\ActionAuthService;
use OCA\LaunchPad\Service\CalendarWidgetService;
use OCA\LaunchPad\Service\NewsWidgetService;
use OCA\LaunchPad\Service\PermissionService;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCA\LaunchPad\Service\WidgetPlacementService;
use OCA\LaunchPad\Service\WidgetService;
use OCP\AppFramework\Http;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for role-permission filtering in WidgetApiController.
 *
 * @spec openspec/changes/role-based-content/tasks.md#task-8
 */
class WidgetApiControllerRoleTest extends TestCase
{

    /**
     * HTTP request mock.
     *
     * @var IRequest&MockObject
     */
    private $request;

    /**
     * Widget service mock.
     *
     * @var WidgetService&MockObject
     */
    private $widgetService;

    /**
     * Role feature permission service mock.
     *
     * @var RoleFeaturePermissionService&MockObject
     */
    private $roleFeaturePerm;

    /**
     * User session mock.
     *
     * @var IUserSession&MockObject
     */
    private $userSession;

    /**
     * Action auth service mock.
     *
     * @var ActionAuthService&MockObject
     */
    private $actionAuth;

    /**
     * PSR logger mock.
     *
     * @var LoggerInterface&MockObject
     */
    private $logger;

    /**
     * Set up mocks before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->request         = $this->createMock(originalClassName: IRequest::class);
        $this->widgetService   = $this->createMock(originalClassName: WidgetService::class);
        $this->roleFeaturePerm = $this->createMock(originalClassName: RoleFeaturePermissionService::class);
        $this->userSession     = $this->createMock(originalClassName: IUserSession::class);
        $this->actionAuth      = $this->createMock(originalClassName: ActionAuthService::class);
        $this->logger          = $this->createMock(originalClassName: LoggerInterface::class);
    }//end setUp()

    /**
     * Build controller with a logged-in user (uid = 'jan').
     *
     * @return WidgetApiController
     */
    private function makeController(): WidgetApiController
    {
        $user = $this->createMock(originalClassName: IUser::class);
        $user->method('getUID')->willReturn('jan');
        $this->userSession->method('getUser')->willReturn($user);

        return new WidgetApiController(
            request: $this->request,
            widgetService: $this->widgetService,
            permissionService: $this->createMock(originalClassName: PermissionService::class),
            newsWidgetService: $this->createMock(originalClassName: NewsWidgetService::class),
            calendarService: $this->createMock(originalClassName: CalendarWidgetService::class),
            placementService: $this->createMock(originalClassName: WidgetPlacementService::class),
            roleFeaturePerm: $this->roleFeaturePerm,
            userSession: $this->userSession,
            actionAuth: $this->actionAuth,
            logger: $this->logger,
            userId: 'jan',
        );
    }//end makeController()

    // ------------------------------------------------------------------ //
    // listAvailable — filtering
    // ------------------------------------------------------------------ //

    /**
     * Full widget list returned unchanged when allowedWidgets is null (REQ-RFP-009).
     *
     * @return void
     *
     * @spec openspec/changes/role-based-content/tasks.md#task-8
     */
    public function testListAvailableReturnsFullListWhenUnconfigured(): void
    {
        $allWidgets = [
            ['id' => 'activity', 'name' => 'Activity'],
            ['id' => 'analytics_dashboard', 'name' => 'Analytics'],
        ];
        $this->widgetService->method('getAvailableWidgets')->willReturn($allWidgets);
        $this->roleFeaturePerm->method('getAllowedWidgetIds')->with('jan')->willReturn(null);
        $this->actionAuth->method('requireAction')->willReturnSelf();

        $controller = $this->makeController();
        $response   = $controller->listAvailable();

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
        $data = $response->getData();
        $this->assertCount(expectedCount: 2, haystack: $data['data'] ?? $data);
    }//end testListAvailableReturnsFullListWhenUnconfigured()

    /**
     * Only "activity" returned when allowedWidgets = ["activity"].
     *
     * @return void
     *
     * @spec openspec/changes/role-based-content/tasks.md#task-8
     */
    public function testListAvailableFiltersToAllowedWidgets(): void
    {
        $allWidgets = [
            ['id' => 'activity', 'name' => 'Activity'],
            ['id' => 'analytics_dashboard', 'name' => 'Analytics'],
        ];
        $this->widgetService->method('getAvailableWidgets')->willReturn($allWidgets);
        $this->roleFeaturePerm->method('getAllowedWidgetIds')
            ->with('jan')
            ->willReturn(['activity']);
        $this->actionAuth->method('requireAction')->willReturnSelf();

        $controller = $this->makeController();
        $response   = $controller->listAvailable();

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
        $data = $response->getData();
        $list = $data['data'] ?? $data;
        $this->assertCount(expectedCount: 1, haystack: $list);
        $this->assertSame(expected: 'activity', actual: ($list[0]['id'] ?? null));
    }//end testListAvailableFiltersToAllowedWidgets()

    // ------------------------------------------------------------------ //
    // getItems — role denial
    // ------------------------------------------------------------------ //

    /**
     * HTTP 403 returned for denied widget; audit logger called (Task 4).
     *
     * @return void
     *
     * @spec openspec/changes/role-based-content/tasks.md#task-8
     */
    public function testGetItemsReturns403ForDeniedWidget(): void
    {
        $this->roleFeaturePerm->method('isWidgetAllowed')
            ->with('jan', 'analytics_dashboard')
            ->willReturn(false);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                // phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
                $this->stringContains('role_permission_denied'),
                $this->arrayHasKey('userId')
                // phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
            );

        $this->actionAuth->method('requireAction')->willReturnSelf();

        $controller = $this->makeController();
        $response   = $controller->getItems(widgets: ['analytics_dashboard'], limit: 7);

        $this->assertSame(expected: Http::STATUS_FORBIDDEN, actual: $response->getStatus());
        $data = $response->getData();
        $this->assertArrayHasKey(key: 'message', array: $data);
        $this->assertSame(expected: 'Not authorized', actual: $data['message']);
    }//end testGetItemsReturns403ForDeniedWidget()

    /**
     * HTTP 200 returned when all requested widgets are allowed.
     *
     * @return void
     *
     * @spec openspec/changes/role-based-content/tasks.md#task-8
     */
    public function testGetItemsReturns200WhenAllWidgetsAllowed(): void
    {
        $this->roleFeaturePerm->method('isWidgetAllowed')->willReturn(true);
        $this->widgetService->method('getWidgetItems')->willReturn(['activity' => []]);
        $this->actionAuth->method('requireAction')->willReturnSelf();

        $controller = $this->makeController();
        $response   = $controller->getItems(widgets: ['activity'], limit: 7);

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
    }//end testGetItemsReturns200WhenAllWidgetsAllowed()

    /**
     * Denied widgets filtered from a mixed request; allowed items still returned (not 403).
     *
     * @return void
     *
     * @spec openspec/changes/role-based-content/tasks.md#task-8
     */
    public function testGetItemsFiltersDeniedWidgetsFromPartialRequest(): void
    {
        $this->roleFeaturePerm->method('isWidgetAllowed')
            ->willReturnCallback(
                function (string $userId, string $widgetId): bool {
                    return $widgetId === 'activity';
                }
            );

        $this->logger->expects($this->once())->method('warning');
        $this->widgetService->expects($this->once())
            ->method('getWidgetItems')
            ->with(
                userId: 'jan',
                widgetIds: ['activity'],
                limit: 7
            )
            ->willReturn(['activity' => []]);

        $this->actionAuth->method('requireAction')->willReturnSelf();

        $controller = $this->makeController();
        $response   = $controller->getItems(
            widgets: ['activity', 'analytics_dashboard'],
            limit: 7
        );

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
    }//end testGetItemsFiltersDeniedWidgetsFromPartialRequest()

    /**
     * The `role_permission_denied` audit entry must carry the full context.
     *
     * The existing denial tests only assert that `warning()` was called once;
     * the context payload itself - the thing an auditor actually reads - was
     * never asserted, so a regression in any of its five fields would have
     * gone unnoticed. In particular the timestamp is built inline and must be
     * an ISO-8601/ATOM string.
     *
     * @return void
     *
     * @spec openspec/changes/role-based-content/tasks.md#task-8
     */
    public function testDeniedWidgetAuditEntryCarriesFullContext(): void
    {
        $this->roleFeaturePerm->method('isWidgetAllowed')->willReturn(false);
        $this->actionAuth->method('requireAction')->willReturnSelf();

        $captured = null;
        $this->logger->expects($this->once())
            ->method('warning')
            ->willReturnCallback(
                function (string $message, array $context) use (&$captured): void {
                    $this->assertSame(expected: 'role_permission_denied', actual: $message);
                    $captured = $context;
                }
            );

        $controller = $this->makeController();
        $response   = $controller->getItems(widgets: ['analytics_dashboard'], limit: 7);

        $this->assertSame(expected: Http::STATUS_FORBIDDEN, actual: $response->getStatus());
        $this->assertIsArray(actual: $captured);
        $this->assertSame(expected: 'jan', actual: $captured['userId']);
        $this->assertSame(expected: 'analytics_dashboard', actual: $captured['widgetId']);
        $this->assertSame(expected: 'role_permission_denied', actual: $captured['reason']);
        $this->assertSame(expected: 'launchpad', actual: $captured['app']);
        $this->assertMatchesRegularExpression(
            pattern: '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            string: $captured['timestamp']
        );
    }//end testDeniedWidgetAuditEntryCarriesFullContext()
}//end class
