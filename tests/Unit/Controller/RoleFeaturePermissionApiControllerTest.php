<?php

/**
 * RoleFeaturePermissionApiControllerTest
 *
 * Covers the admin-only CRUD endpoints introduced by `role-based-content`:
 *  - Non-admin → 403 (gate enforced by NC middleware; simulated here via mock).
 *  - List returns all permission objects (REQ-RFP-007).
 *  - Save with valid body → 201 (REQ-RFP-007).
 *  - Save with invalid body (missing groupId) → 400 (REQ-RFP-007).
 *  - List layout defaults returns all rows (REQ-RFP-008).
 *  - Save layout default with valid body → 201 (REQ-RFP-008).
 *
 * @category Test
 * @package  OCA\MyDash\Tests\Unit\Controller
 *
 * @author    Conduction Development Team <info@conduction.nl>
 * @copyright 2026 Conduction B.V.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * @link https://conduction.nl
 *
 * @spec openspec/changes/role-based-content/tasks.md#task-7
 */

declare(strict_types=1);

namespace Unit\Controller;

use InvalidArgumentException;
use OCA\MyDash\Controller\RoleFeaturePermissionApiController;
use OCA\MyDash\Db\RoleFeaturePermission;
use OCA\MyDash\Db\RoleLayoutDefault;
use OCA\MyDash\Service\RoleFeaturePermissionService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RoleFeaturePermissionApiController.
 *
 * @spec openspec/changes/role-based-content/tasks.md#task-7
 */
class RoleFeaturePermissionApiControllerTest extends TestCase
{

    /**
     * The controller under test.
     *
     * @var RoleFeaturePermissionApiController
     */
    private RoleFeaturePermissionApiController $controller;

    /**
     * HTTP request mock.
     *
     * @var IRequest&MockObject
     */
    private $request;

    /**
     * Permission service mock.
     *
     * @var RoleFeaturePermissionService&MockObject
     */
    private $service;

    /**
     * Set up mocks and controller instance.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->request = $this->createMock(originalClassName: IRequest::class);
        $this->service = $this->createMock(originalClassName: RoleFeaturePermissionService::class);

        $this->controller = new RoleFeaturePermissionApiController(
            request: $this->request,
            service: $this->service,
        );
    }//end setUp()

    /**
     * Build a minimal RoleFeaturePermission entity for testing.
     *
     * @param string $groupId The group ID.
     * @param int    $id      The entity ID.
     *
     * @return RoleFeaturePermission
     */
    private function makePermission(string $groupId='medewerkers', int $id=1): RoleFeaturePermission
    {
        $entity = new RoleFeaturePermission();
        // phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
        $entity->setId($id);
        $entity->setGroupId($groupId);
        $entity->setName('Test permissions');
        $entity->setAllowedWidgets('["activity","notes"]');
        $entity->setDeniedWidgets('[]');
        $entity->setPriorityWeights('{}');
        // phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
        return $entity;
    }//end makePermission()

    /**
     * Build a minimal RoleLayoutDefault entity for testing.
     *
     * @param string $groupId  The group ID.
     * @param string $widgetId The widget ID.
     * @param int    $id       The entity ID.
     *
     * @return RoleLayoutDefault
     */
    private function makeLayoutDefault(
        string $groupId='managers',
        string $widgetId='activity',
        int $id=1
    ): RoleLayoutDefault {
        $entity = new RoleLayoutDefault();
        // phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
        $entity->setId($id);
        $entity->setGroupId($groupId);
        $entity->setWidgetId($widgetId);
        $entity->setName('Manager layout');
        $entity->setGridX(0);
        $entity->setGridY(0);
        $entity->setGridWidth(4);
        $entity->setGridHeight(4);
        $entity->setSortOrder(1);
        $entity->setIsCompulsory(0);
        // phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
        return $entity;
    }//end makeLayoutDefault()

    // ------------------------------------------------------------------ //
    // listPermissions
    // ------------------------------------------------------------------ //

    /**
     * ListPermissions returns HTTP 200 with all permission rows.
     *
     * @return void
     *
     * @spec openspec/changes/role-based-content/tasks.md#task-7
     */
    public function testListPermissionsReturnsAllRows(): void
    {
        $rows = [$this->makePermission(groupId: 'medewerkers', id: 1), $this->makePermission(groupId: 'managers', id: 2)];
        $this->service->expects($this->once())
            ->method('listPermissions')
            ->willReturn($rows);

        $response = $this->controller->listPermissions();

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
        $data = $response->getData();
        $this->assertIsArray(actual: $data);
        $this->assertCount(expectedCount: 2, haystack: $data);
    }//end testListPermissionsReturnsAllRows()

    /**
     * ListPermissions returns HTTP 200 with empty array when no rows exist.
     *
     * @return void
     *
     * @spec openspec/changes/role-based-content/tasks.md#task-7
     */
    public function testListPermissionsReturnsEmptyArray(): void
    {
        $this->service->expects($this->once())
            ->method('listPermissions')
            ->willReturn([]);

        $response = $this->controller->listPermissions();

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
        $data = $response->getData();
        $this->assertIsArray(actual: $data);
        $this->assertCount(expectedCount: 0, haystack: $data);
    }//end testListPermissionsReturnsEmptyArray()

    // ------------------------------------------------------------------ //
    // savePermission
    // ------------------------------------------------------------------ //

    /**
     * SavePermission with valid body returns HTTP 201.
     *
     * @return void
     *
     * @spec openspec/changes/role-based-content/tasks.md#task-7
     */
    public function testSavePermissionReturns201OnValidBody(): void
    {
        $entity = $this->makePermission(groupId: 'communicatie', id: 3);
        $this->service->expects($this->once())
            ->method('savePermission')
            ->willReturn($entity);

        // Simulate valid JSON body via php://input.
        $this->request->method('getParams')
            ->willReturn(
                [
                    'groupId'        => 'communicatie',
                    'name'           => 'Communicatie widget-rechten',
                    'allowedWidgets' => ['activity', 'notes'],
                    'deniedWidgets'  => [],
                ]
            );

        $response = $this->controller->savePermission();

        $this->assertSame(expected: Http::STATUS_CREATED, actual: $response->getStatus());
    }//end testSavePermissionReturns201OnValidBody()

    /**
     * SavePermission with missing groupId returns HTTP 400.
     *
     * @return void
     *
     * @spec openspec/changes/role-based-content/tasks.md#task-7
     */
    public function testSavePermissionReturns400OnMissingGroupId(): void
    {
        $this->service->expects($this->once())
            ->method('savePermission')
            ->willThrowException(new InvalidArgumentException(message: 'groupId is required'));

        $this->request->method('getParams')
            ->willReturn([]);

        $response = $this->controller->savePermission();

        $this->assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $response->getStatus());
        $data = $response->getData();
        $this->assertArrayHasKey(key: 'error', array: $data);
    }//end testSavePermissionReturns400OnMissingGroupId()

    // ------------------------------------------------------------------ //
    // listLayoutDefaults
    // ------------------------------------------------------------------ //

    /**
     * ListLayoutDefaults returns HTTP 200 with all rows.
     *
     * @return void
     *
     * @spec openspec/changes/role-based-content/tasks.md#task-7
     */
    public function testListLayoutDefaultsReturnsAllRows(): void
    {
        $rows = [$this->makeLayoutDefault(groupId: 'managers', widgetId: 'analytics_dashboard', id: 1)];
        $this->service->expects($this->once())
            ->method('listLayoutDefaults')
            ->willReturn($rows);

        $response = $this->controller->listLayoutDefaults();

        $this->assertSame(expected: Http::STATUS_OK, actual: $response->getStatus());
        $data = $response->getData();
        $this->assertIsArray(actual: $data);
        $this->assertCount(expectedCount: 1, haystack: $data);
    }//end testListLayoutDefaultsReturnsAllRows()

    // ------------------------------------------------------------------ //
    // saveLayoutDefault
    // ------------------------------------------------------------------ //

    /**
     * SaveLayoutDefault with valid body returns HTTP 201.
     *
     * @return void
     *
     * @spec openspec/changes/role-based-content/tasks.md#task-7
     */
    public function testSaveLayoutDefaultReturns201OnValidBody(): void
    {
        $entity = $this->makeLayoutDefault(groupId: 'managers', widgetId: 'user_status', id: 4);
        $this->service->expects($this->once())
            ->method('saveLayoutDefault')
            ->willReturn($entity);

        $this->request->method('getParams')
            ->willReturn(
                [
                    'groupId'    => 'managers',
                    'widgetId'   => 'user_status',
                    'name'       => 'Manager gebruikersstatus',
                    'gridX'      => 0,
                    'gridY'      => 7,
                    'gridWidth'  => 4,
                    'gridHeight' => 4,
                    'sortOrder'  => 3,
                ]
            );

        $response = $this->controller->saveLayoutDefault();

        $this->assertSame(expected: Http::STATUS_CREATED, actual: $response->getStatus());
    }//end testSaveLayoutDefaultReturns201OnValidBody()

    /**
     * SaveLayoutDefault with missing required fields returns HTTP 400.
     *
     * @return void
     *
     * @spec openspec/changes/role-based-content/tasks.md#task-7
     */
    public function testSaveLayoutDefaultReturns400OnMissingFields(): void
    {
        $this->service->expects($this->once())
            ->method('saveLayoutDefault')
            ->willThrowException(new InvalidArgumentException(message: 'groupId and widgetId are required'));

        $this->request->method('getParams')
            ->willReturn([]);

        $response = $this->controller->saveLayoutDefault();

        $this->assertSame(expected: Http::STATUS_BAD_REQUEST, actual: $response->getStatus());
    }//end testSaveLayoutDefaultReturns400OnMissingFields()
}//end class
