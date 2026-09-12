<?php

/**
 * RoleFeaturePermissionDatabaseTest
 *
 * Role feature permissions and role layout defaults, through
 * RoleFeaturePermissionService, against a real database.
 *
 * 🔴 WHY. `name` is NOT NULL with no default in both tables, and the spec
 * lists it as required. The service set it only when the payload carried it.
 * A new row saved without `name` therefore reached the database with the
 * column missing, failed with SQLSTATE 23502, and the API answered 400
 * "Operation failed" with nothing to say which field. A missing required
 * field is now refused as one, before any insert.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Database
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Database;

use InvalidArgumentException;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use OCP\Server;

/**
 * @SuppressWarnings(PHPMD.StaticAccess) Server::get() is the only way in from a test.
 */
class RoleFeaturePermissionDatabaseTest extends RealDatabaseTestCase {
	/**
	 * A new permission row is stored with every required column filled.
	 *
	 * @return void
	 */
	public function testANewPermissionIsStoredWithItsName(): void {
		$groupId = $this->uniqueId(prefix: 'db-group');
		$this->cleanup(fn () => $this->deleteRows('launchpad_role_feat_perms', 'group_id', $groupId));

		Server::get(RoleFeaturePermissionService::class)->savePermission(
			data: ['groupId' => $groupId, 'name' => 'Database probe', 'allowedWidgets' => ['activity']]
		);

		$rows = $this->storedRows('launchpad_role_feat_perms', 'group_id', $groupId);
		$this->assertCount(1, $rows, 'the permission was not stored');
		$this->assertColumnsFilled(row: $rows[0], columns: ['group_id', 'name']);
	}//end testANewPermissionIsStoredWithItsName()

	/**
	 * A new permission without a name is refused as a missing field, not by the database.
	 *
	 * @return void
	 */
	public function testANewPermissionWithoutANameIsRefusedBeforeTheInsert(): void {
		$groupId = $this->uniqueId(prefix: 'db-group');
		$this->cleanup(fn () => $this->deleteRows('launchpad_role_feat_perms', 'group_id', $groupId));

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('name is required');

		Server::get(RoleFeaturePermissionService::class)->savePermission(
			data: ['groupId' => $groupId, 'allowedWidgets' => ['activity']]
		);
	}//end testANewPermissionWithoutANameIsRefusedBeforeTheInsert()

	/**
	 * A new layout default without a name is refused as a missing field.
	 *
	 * @return void
	 */
	public function testANewLayoutDefaultWithoutANameIsRefusedBeforeTheInsert(): void {
		$groupId = $this->uniqueId(prefix: 'db-group');
		$this->cleanup(fn () => $this->deleteRows('launchpad_role_layout_def', 'group_id', $groupId));

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('name is required');

		Server::get(RoleFeaturePermissionService::class)->saveLayoutDefault(
			data: [
				'groupId' => $groupId,
				'widgetId' => 'activity',
				'gridX' => 0,
				'gridY' => 0,
				'gridWidth' => 4,
				'gridHeight' => 4,
			]
		);
	}//end testANewLayoutDefaultWithoutANameIsRefusedBeforeTheInsert()

	/**
	 * A new layout default with a name is stored with every required column filled.
	 *
	 * @return void
	 */
	public function testANewLayoutDefaultIsStoredWithItsName(): void {
		$groupId = $this->uniqueId(prefix: 'db-group');
		$this->cleanup(fn () => $this->deleteRows('launchpad_role_layout_def', 'group_id', $groupId));

		Server::get(RoleFeaturePermissionService::class)->saveLayoutDefault(
			data: [
				'groupId' => $groupId,
				'name' => 'Database probe',
				'widgetId' => 'activity',
				'gridX' => 0,
				'gridY' => 0,
				'gridWidth' => 4,
				'gridHeight' => 4,
			]
		);

		$rows = $this->storedRows('launchpad_role_layout_def', 'group_id', $groupId);
		$this->assertCount(1, $rows, 'the layout default was not stored');
		$this->assertColumnsFilled(row: $rows[0], columns: ['group_id', 'name', 'widget_id']);
	}//end testANewLayoutDefaultIsStoredWithItsName()
}//end class
