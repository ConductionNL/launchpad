<?php

/**
 * Entity Behaviour Coverage Test
 *
 * Pins the serialisation and decoding behaviour of the small Db entity and
 * value classes on the paths the rest of the suite does not reach: the
 * defensive fallbacks for unparseable JSON / timestamps, the target
 * resolution of role assignments and the shape of several jsonSerialize()
 * payloads.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Db;

use OCA\LaunchPad\Db\DashboardLock;
use OCA\LaunchPad\Db\DashboardShare;
use OCA\LaunchPad\Db\DashboardUuidCondition;
use OCA\LaunchPad\Db\FeedCache;
use OCA\LaunchPad\Db\MetadataField;
use OCA\LaunchPad\Db\MetadataValue;
use OCA\LaunchPad\Db\PersonalLayer;
use OCA\LaunchPad\Db\PublicShare;
use OCA\LaunchPad\Db\RoleAssignment;
use OCA\LaunchPad\Db\RoleFeaturePermission;
use OCP\DB\QueryBuilder\IExpressionBuilder;
use OCP\DB\QueryBuilder\IQueryBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Behaviour tests for the small Db entity / value classes.
 *
 * @covers \OCA\LaunchPad\Db\DashboardShare
 * @covers \OCA\LaunchPad\Db\RoleAssignment
 * @covers \OCA\LaunchPad\Db\RoleFeaturePermission
 * @covers \OCA\LaunchPad\Db\MetadataValue
 * @covers \OCA\LaunchPad\Db\DashboardUuidCondition
 * @covers \OCA\LaunchPad\Db\DashboardLock
 * @covers \OCA\LaunchPad\Db\FeedCache
 * @covers \OCA\LaunchPad\Db\MetadataField
 * @covers \OCA\LaunchPad\Db\PersonalLayer
 * @covers \OCA\LaunchPad\Db\PublicShare
 */
class EntityBehaviourCoverageTest extends TestCase {
	/**
	 * DashboardShare serialises every column under its camelCase key.
	 *
	 * @return void
	 */
	public function testDashboardShareJsonSerializeExposesAllColumns(): void {
		$share = new DashboardShare();
		$share->setId(7);
		$share->setDashboardId(12);
		$share->setShareType(DashboardShare::SHARE_TYPE_GROUP);
		$share->setShareWith('editors');
		$share->setPermissionLevel('full');
		$share->setCreatedAt('2026-01-01 10:00:00');
		$share->setUpdatedAt('2026-01-02 11:00:00');

		$this->assertSame(
			[
				'id' => 7,
				'dashboardId' => 12,
				'shareType' => 'group',
				'shareWith' => 'editors',
				'permissionLevel' => 'full',
				'createdAt' => '2026-01-01 10:00:00',
				'updatedAt' => '2026-01-02 11:00:00',
			],
			$share->jsonSerialize()
		);
	}//end testDashboardShareJsonSerializeExposesAllColumns()

	/**
	 * A user assignment resolves its target to the user id, even when a
	 * group id is also present.
	 *
	 * @return void
	 */
	public function testRoleAssignmentTargetPrefersUser(): void {
		$row = new RoleAssignment();
		$row->setUserId('alice');
		$row->setGroupId('staff');

		$this->assertTrue($row->isUserAssignment());
		$this->assertTrue($row->isGroupAssignment());
		$this->assertSame('alice', $row->getTarget());
	}//end testRoleAssignmentTargetPrefersUser()

	/**
	 * An empty user id is not a user assignment; the group id is the target.
	 *
	 * @return void
	 */
	public function testRoleAssignmentTargetFallsBackToGroup(): void {
		$row = new RoleAssignment();
		$row->setUserId('');
		$row->setGroupId('staff');

		$this->assertFalse($row->isUserAssignment());
		$this->assertTrue($row->isGroupAssignment());
		$this->assertSame('staff', $row->getTarget());
	}//end testRoleAssignmentTargetFallsBackToGroup()

	/**
	 * With neither (or only empty) ids, there is no target.
	 *
	 * @return void
	 */
	public function testRoleAssignmentWithoutTargetReturnsNull(): void {
		$row = new RoleAssignment();
		$this->assertFalse($row->isUserAssignment());
		$this->assertFalse($row->isGroupAssignment());
		$this->assertNull($row->getTarget());

		$row->setGroupId('');
		$this->assertFalse($row->isGroupAssignment());
		$this->assertNull($row->getTarget());
	}//end testRoleAssignmentWithoutTargetReturnsNull()

	/**
	 * Empty strings decode to empty collections, and priority weights
	 * serialise as a JSON object even when empty.
	 *
	 * @return void
	 */
	public function testRoleFeaturePermissionEmptyStringsDecodeToEmpty(): void {
		$perm = new RoleFeaturePermission();
		$perm->setAllowedWidgets('');
		$perm->setDeniedWidgets('');
		$perm->setPriorityWeights('');

		$this->assertSame([], $perm->getAllowedWidgetsDecoded());
		$this->assertSame([], $perm->getDeniedWidgetsDecoded());
		$this->assertSame([], $perm->getPriorityWeightsDecoded());
		$this->assertSame('{}', json_encode($perm->jsonSerialize()['priorityWeights']));
	}//end testRoleFeaturePermissionEmptyStringsDecodeToEmpty()

	/**
	 * JSON that decodes to a scalar (or not at all) falls back to empty.
	 *
	 * @return void
	 */
	public function testRoleFeaturePermissionNonArrayJsonDecodesToEmpty(): void {
		$perm = new RoleFeaturePermission();
		$perm->setAllowedWidgets('"weather"');
		$perm->setDeniedWidgets('{not json');
		$perm->setPriorityWeights('42');

		$this->assertSame([], $perm->getAllowedWidgetsDecoded());
		$this->assertSame([], $perm->getDeniedWidgetsDecoded());
		$this->assertSame([], $perm->getPriorityWeightsDecoded());
	}//end testRoleFeaturePermissionNonArrayJsonDecodesToEmpty()

	/**
	 * Widget lists are re-indexed; priority weights keep their keys.
	 *
	 * @return void
	 */
	public function testRoleFeaturePermissionDecodesArrays(): void {
		$perm = new RoleFeaturePermission();
		$perm->setAllowedWidgets('{"a":"weather","b":"news"}');
		$perm->setDeniedWidgets('{"x":"stocks"}');
		$perm->setPriorityWeights('{"weather":3,"news":1}');

		$this->assertSame(['weather', 'news'], $perm->getAllowedWidgetsDecoded());
		$this->assertSame(['stocks'], $perm->getDeniedWidgetsDecoded());
		$this->assertSame(['weather' => 3, 'news' => 1], $perm->getPriorityWeightsDecoded());
	}//end testRoleFeaturePermissionDecodesArrays()

	/**
	 * MetadataValue serialises its id, dashboard uuid, field id and value.
	 *
	 * @return void
	 */
	public function testMetadataValueJsonSerialize(): void {
		$value = new MetadataValue();
		$value->setId(3);
		$value->setDashboardUuid('uuid-1');
		$value->setFieldId(9);
		$value->setValue('Finance');

		$this->assertSame(
			[
				'id' => 3,
				'dashboardUuid' => 'uuid-1',
				'fieldId' => 9,
				'value' => 'Finance',
			],
			$value->jsonSerialize()
		);
	}//end testMetadataValueJsonSerialize()

	/**
	 * The condition adds `dashboard_uuid = :param` as the WHERE clause and
	 * returns what `where()` returns.
	 *
	 * @return void
	 */
	public function testDashboardUuidConditionRestrictsOnUuid(): void {
		$expr = $this->createMock(IExpressionBuilder::class);
		$qb   = $this->createMock(IQueryBuilder::class);

		$qb->expects($this->once())
			->method('createNamedParameter')
			->with('uuid-42')
			->willReturn(':dcValue1');
		$qb->method('expr')->willReturn($expr);
		$expr->expects($this->once())
			->method('eq')
			->with('dashboard_uuid', ':dcValue1')
			->willReturn('dashboard_uuid = :dcValue1');
		$qb->expects($this->once())
			->method('where')
			->with('dashboard_uuid = :dcValue1')
			->willReturnSelf();

		$this->assertSame($qb, (new DashboardUuidCondition())->restrict($qb, 'uuid-42'));
	}//end testDashboardUuidConditionRestrictsOnUuid()

	/**
	 * An unparseable heartbeat is treated as expired, with no time left
	 * and no implied expiry.
	 *
	 * @return void
	 */
	public function testDashboardLockUnparseableHeartbeat(): void {
		$lock = new DashboardLock();
		$lock->setUpdatedAt('not a timestamp');

		$this->assertTrue($lock->isExpired());
		$this->assertSame(0, $lock->expiresIn());
		$this->assertNull($lock->impliedExpiresAt());

		$json = $lock->jsonSerialize();
		$this->assertNull($json['expiresAt']);
		$this->assertSame(0, $json['expiresIn']);
	}//end testDashboardLockUnparseableHeartbeat()

	/**
	 * Items that cannot be JSON-encoded (invalid UTF-8) clear the cache
	 * column instead of storing a broken value.
	 *
	 * @return void
	 */
	public function testFeedCacheEncodeItemsClearsOnEncodingFailure(): void {
		$cache = new FeedCache();
		$cache->setItemsJson('[{"title":"old"}]');

		$cache->encodeItems([['title' => "\xB1\x31"]]);

		$this->assertNull($cache->getItemsJson());
		$this->assertSame([], $cache->decodeItems());
	}//end testFeedCacheEncodeItemsClearsOnEncodingFailure()

	/**
	 * Empty or non-array options decode to an empty list; non-string
	 * entries are dropped.
	 *
	 * @return void
	 */
	public function testMetadataFieldOptionsArrayFallbacks(): void {
		$field = new MetadataField();
		$field->setType(MetadataField::TYPE_SELECT);

		$field->setOptions('');
		$this->assertSame([], $field->getOptionsArray());

		$field->setOptions('"single"');
		$this->assertSame([], $field->getOptionsArray());
		$this->assertSame([], $field->jsonSerialize()['options']);

		$field->setOptions('["a",1,"b",null]');
		$this->assertSame(['a', 'b'], $field->getOptionsArray());
	}//end testMetadataFieldOptionsArrayFallbacks()

	/**
	 * Invalid JSON in overrides / hidden decodes to empty, and overrides
	 * still serialise as a JSON object.
	 *
	 * @return void
	 */
	public function testPersonalLayerInvalidJsonDecodesToEmpty(): void {
		$layer = new PersonalLayer();
		$layer->setOverrides('{broken');
		$layer->setHidden('"5"');

		$this->assertSame([], $layer->overridesArray());
		$this->assertSame([], $layer->hiddenArray());

		$json = $layer->jsonSerialize();
		$this->assertSame('{}', json_encode($json['overrides']));
		$this->assertSame([], $json['hidden']);
	}//end testPersonalLayerInvalidJsonDecodesToEmpty()

	/**
	 * The transient url round-trips through setUrl/getUrl and appears in
	 * the serialised payload.
	 *
	 * @return void
	 */
	public function testPublicShareUrlRoundTrips(): void {
		$share = new PublicShare();
		$this->assertNull($share->getUrl());

		$share->setUrl('https://cloud.example/s/abc');

		$this->assertSame('https://cloud.example/s/abc', $share->getUrl());
		$this->assertSame('https://cloud.example/s/abc', $share->jsonSerialize()['url']);
	}//end testPublicShareUrlRoundTrips()
}//end class
