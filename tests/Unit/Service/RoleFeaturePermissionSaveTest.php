<?php

/**
 * RoleFeaturePermissionService save and delete tests
 *
 * Pins the admin write paths: required keys, the name rule for new rows,
 * upsert insert-versus-update, payload normalisation of the widget lists,
 * and the grid geometry floor of a role layout default.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use InvalidArgumentException;
use OCA\LaunchPad\Db\RoleFeaturePermission;
use OCA\LaunchPad\Db\RoleFeaturePermissionMapper;
use OCA\LaunchPad\Db\RoleLayoutDefault;
use OCA\LaunchPad\Db\RoleLayoutDefaultMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminSettingsService;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IGroupManager;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RoleFeaturePermissionSaveTest extends TestCase {
	/**
	 * Service.
	 *
	 * @var RoleFeaturePermissionService
	 */
	private RoleFeaturePermissionService $service;

	/**
	 * Perm mapper.
	 *
	 * @var RoleFeaturePermissionMapper
	 */
	private RoleFeaturePermissionMapper&MockObject $permMapper;

	/**
	 * Default mapper.
	 *
	 * @var RoleLayoutDefaultMapper
	 */
	private RoleLayoutDefaultMapper&MockObject $defaultMapper;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		$this->permMapper = $this->createMock(originalClassName: RoleFeaturePermissionMapper::class);
		$this->defaultMapper = $this->createMock(originalClassName: RoleLayoutDefaultMapper::class);

		$this->service = new RoleFeaturePermissionService(
			permissionMapper: $this->permMapper,
			defaultMapper: $this->defaultMapper,
			placementMapper: $this->createMock(originalClassName: WidgetPlacementMapper::class),
			adminSettings: $this->createMock(originalClassName: AdminSettingsService::class),
			adminTemplateService: $this->createMock(originalClassName: AdminTemplateService::class),
			userManager: $this->createMock(originalClassName: IUserManager::class),
			groupManager: $this->createMock(originalClassName: IGroupManager::class),
		);
	}//end setUp()

	/**
	 * List permissions and layout defaults return mapper rows.
	 *
	 * @return void
	 */
	public function testListPermissionsAndLayoutDefaultsReturnMapperRows(): void {
		$perm = new RoleFeaturePermission();
		$default = new RoleLayoutDefault();
		$this->permMapper->method('findAll')->willReturn(value: [$perm]);
		$this->defaultMapper->method('findAll')->willReturn(value: [$default]);

		$this->assertSame(expected: [$perm], actual: $this->service->listPermissions());
		$this->assertSame(expected: [$default], actual: $this->service->listLayoutDefaults());
	}//end testListPermissionsAndLayoutDefaultsReturnMapperRows()

	/**
	 * Save permission rejects missing group id.
	 *
	 * @return void
	 */
	public function testSavePermissionRejectsMissingGroupId(): void {
		$this->permMapper->expects($this->never())->method('insert');
		$this->permMapper->expects($this->never())->method('update');

		$this->expectException(exception: InvalidArgumentException::class);
		$this->expectExceptionMessage(message: 'groupId is required');
		$this->service->savePermission(data: ['name' => 'Staff']);
	}//end testSavePermissionRejectsMissingGroupId()

	/**
	 * Save permission rejects new row without name.
	 *
	 * @return void
	 */
	public function testSavePermissionRejectsNewRowWithoutName(): void {
		$this->permMapper->method('findByGroupId')
			->willThrowException(exception: new DoesNotExistException(msg: 'none'));
		$this->permMapper->expects($this->never())->method('insert');

		$this->expectException(exception: InvalidArgumentException::class);
		$this->expectExceptionMessage(message: 'name is required');
		$this->service->savePermission(data: ['groupId' => 'staff']);
	}//end testSavePermissionRejectsNewRowWithoutName()

	/**
	 * Save permission inserts new row with normalised lists.
	 *
	 * @return void
	 */
	public function testSavePermissionInsertsNewRowWithNormalisedLists(): void {
		$this->permMapper->method('findByGroupId')
			->willThrowException(exception: new DoesNotExistException(msg: 'none'));
		$this->permMapper->expects($this->never())->method('update');
		$this->permMapper->expects($this->once())->method('insert')
			->willReturnArgument(argumentIndex: 0);

		$saved = $this->service->savePermission(data: [
			'groupId'         => 'staff',
			'name'            => 'Staff',
			'description'     => 'All employees',
			'allowedWidgets'  => ['b' => 'news', 'a' => 'files'],
			'deniedWidgets'   => 'not-a-list',
			'priorityWeights' => ['news' => 5],
		]);

		$this->assertSame(expected: 'staff', actual: $saved->getGroupId());
		$this->assertSame(expected: 'Staff', actual: $saved->getName());
		$this->assertSame(expected: 'All employees', actual: $saved->getDescription());
		// A keyed list is re-indexed so it stores as a JSON array, not an object.
		$this->assertSame(expected: '["news","files"]', actual: $saved->getAllowedWidgets());
		// A scalar submitted for a list stores as an empty list.
		$this->assertSame(expected: '[]', actual: $saved->getDeniedWidgets());
		$this->assertSame(expected: '{"news":5}', actual: $saved->getPriorityWeights());
		$this->assertNotNull(actual: $saved->getCreatedAt());
		$this->assertNotNull(actual: $saved->getUpdatedAt());
	}//end testSavePermissionInsertsNewRowWithNormalisedLists()

	/**
	 * Save permission updates existing row and keeps omitted fields.
	 *
	 * @return void
	 */
	public function testSavePermissionUpdatesExistingRowAndKeepsOmittedFields(): void {
		$existing = new RoleFeaturePermission();
		$existing->setId(1);
		$existing->setName('Kept name');
		$existing->setDescription('Old description');
		$existing->setAllowedWidgets('["news"]');
		$this->permMapper->method('findByGroupId')->willReturn(value: $existing);
		$this->permMapper->expects($this->never())->method('insert');
		$this->permMapper->expects($this->once())->method('update')
			->willReturnArgument(argumentIndex: 0);

		// An existing row needs no name, and an explicit null clears the description.
		$saved = $this->service->savePermission(data: [
			'groupId'     => 'staff',
			'description' => null,
		]);

		$this->assertSame(expected: $existing, actual: $saved);
		$this->assertSame(expected: 'Kept name', actual: $saved->getName());
		$this->assertNull(actual: $saved->getDescription());
		$this->assertSame(expected: '["news"]', actual: $saved->getAllowedWidgets());
	}//end testSavePermissionUpdatesExistingRowAndKeepsOmittedFields()

	/**
	 * Delete permission deletes the found row.
	 *
	 * @return void
	 */
	public function testDeletePermissionDeletesTheFoundRow(): void {
		$row = new RoleFeaturePermission();
		$this->permMapper->expects($this->once())->method('find')
			->with(7)->willReturn(value: $row);
		$this->permMapper->expects($this->once())->method('delete')->with($row);

		$this->service->deletePermission(id: 7);
	}//end testDeletePermissionDeletesTheFoundRow()

	/**
	 * Delete permission propagates amissing row.
	 *
	 * @return void
	 */
	public function testDeletePermissionPropagatesAMissingRow(): void {
		$this->permMapper->method('find')
			->willThrowException(exception: new DoesNotExistException(msg: 'gone'));
		$this->permMapper->expects($this->never())->method('delete');

		$this->expectException(exception: DoesNotExistException::class);
		$this->service->deletePermission(id: 7);
	}//end testDeletePermissionPropagatesAMissingRow()

	/**
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function incompleteLayoutKeys(): array {
		return [
			'no group'  => [['widgetId' => 'news', 'name' => 'x']],
			'no widget' => [['groupId' => 'staff', 'name' => 'x']],
			'empty'     => [['groupId' => '', 'widgetId' => '']],
		];
	}//end incompleteLayoutKeys()

	/**
	 * @dataProvider incompleteLayoutKeys
	 * @param array $data Data.
	 * @return void
	 */
	public function testSaveLayoutDefaultRejectsMissingKeys(array $data): void {
		$this->defaultMapper->expects($this->never())->method('insert');

		$this->expectException(exception: InvalidArgumentException::class);
		$this->expectExceptionMessage(message: 'groupId and widgetId are required');
		$this->service->saveLayoutDefault(data: $data);
	}//end testSaveLayoutDefaultRejectsMissingKeys()

	/**
	 * Save layout default rejects new row without name.
	 *
	 * @return void
	 */
	public function testSaveLayoutDefaultRejectsNewRowWithoutName(): void {
		$this->defaultMapper->method('findByGroupAndWidget')
			->willThrowException(exception: new DoesNotExistException(msg: 'none'));
		$this->defaultMapper->expects($this->never())->method('insert');

		$this->expectException(exception: InvalidArgumentException::class);
		$this->expectExceptionMessage(message: 'name is required');
		$this->service->saveLayoutDefault(data: ['groupId' => 'staff', 'widgetId' => 'news']);
	}//end testSaveLayoutDefaultRejectsNewRowWithoutName()

	/**
	 * Save layout default inserts with floored geometry.
	 *
	 * @return void
	 */
	public function testSaveLayoutDefaultInsertsWithFlooredGeometry(): void {
		$this->defaultMapper->method('findByGroupAndWidget')
			->willThrowException(exception: new DoesNotExistException(msg: 'none'));
		$this->defaultMapper->expects($this->once())->method('insert')
			->willReturnArgument(argumentIndex: 0);

		$saved = $this->service->saveLayoutDefault(data: [
			'groupId'      => 'staff',
			'widgetId'     => 'news',
			'name'         => 'News first',
			'description'  => 'Top left',
			'gridX'        => '2',
			'gridY'        => 3,
			'gridWidth'    => 0,
			'gridHeight'   => -4,
			'sortOrder'    => '9',
			'isCompulsory' => 'yes',
		]);

		$this->assertSame(expected: 'staff', actual: $saved->getGroupId());
		$this->assertSame(expected: 'news', actual: $saved->getWidgetId());
		$this->assertSame(expected: 'News first', actual: $saved->getName());
		$this->assertSame(expected: 'Top left', actual: $saved->getDescription());
		$this->assertSame(expected: 2, actual: $saved->getGridX());
		$this->assertSame(expected: 3, actual: $saved->getGridY());
		// A zero or negative span would make the widget unrenderable.
		$this->assertSame(expected: 1, actual: $saved->getGridWidth());
		$this->assertSame(expected: 1, actual: $saved->getGridHeight());
		$this->assertSame(expected: 9, actual: $saved->getSortOrder());
		$this->assertSame(expected: 1, actual: $saved->getIsCompulsory());
		$this->assertNotNull(actual: $saved->getCreatedAt());
	}//end testSaveLayoutDefaultInsertsWithFlooredGeometry()

	/**
	 * Save layout default updates existing row.
	 *
	 * @return void
	 */
	public function testSaveLayoutDefaultUpdatesExistingRow(): void {
		$existing = new RoleLayoutDefault();
		$existing->setId(4);
		$existing->setName('Existing');
		$existing->setIsCompulsory(1);
		$existing->setGridWidth(3);
		$this->defaultMapper->method('findByGroupAndWidget')->willReturn(value: $existing);
		$this->defaultMapper->expects($this->never())->method('insert');
		$this->defaultMapper->expects($this->once())->method('update')
			->willReturnArgument(argumentIndex: 0);

		$saved = $this->service->saveLayoutDefault(data: [
			'groupId'      => 'staff',
			'widgetId'     => 'news',
			'description'  => null,
			'isCompulsory' => 0,
		]);

		$this->assertSame(expected: 'Existing', actual: $saved->getName());
		$this->assertNull(actual: $saved->getDescription());
		$this->assertSame(expected: 0, actual: $saved->getIsCompulsory());
		$this->assertSame(expected: 3, actual: $saved->getGridWidth());
		$this->assertNotNull(actual: $saved->getUpdatedAt());
	}//end testSaveLayoutDefaultUpdatesExistingRow()

	/**
	 * Delete layout default deletes the found row.
	 *
	 * @return void
	 */
	public function testDeleteLayoutDefaultDeletesTheFoundRow(): void {
		$row = new RoleLayoutDefault();
		$this->defaultMapper->expects($this->once())->method('find')
			->with(5)->willReturn(value: $row);
		$this->defaultMapper->expects($this->once())->method('delete')->with($row);

		$this->service->deleteLayoutDefault(id: 5);
	}//end testDeleteLayoutDefaultDeletesTheFoundRow()
}//end class
