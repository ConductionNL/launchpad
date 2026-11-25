<?php

/**
 * DashboardSerializeGoldenTest
 *
 * Pins the exact serialised shape of a dashboard, every key in order, for
 * the owner view and the viewer view.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Db
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Db;

use OCA\LaunchPad\Db\Dashboard;
use PHPUnit\Framework\TestCase;

class DashboardSerializeGoldenTest extends TestCase {

	private static function dashboard(): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId(3);
		$dashboard->setUuid('u-3');
		$dashboard->setName('Home');
		$dashboard->setUserId('alice');
		$dashboard->setTargetGroups('["staff"]');
		$dashboard->setSlug('home');
		$dashboard->setDashboardFooterMode('custom');
		$dashboard->setTemplateCategory('team');
		return $dashboard;
	}

	public function testTheOwnerView(): void {
		$data = self::dashboard()->jsonSerialize();
		$this->assertSame(
			[
				'id', 'uuid', 'name', 'description', 'icon', 'type', 'userId', 'groupId', 'basedOnTemplate', 'gridColumns',
				'permissionLevel', 'targetGroups', 'isDefault', 'isActive', 'commentsEnabled', 'reactionsEnabled', 'conversationEnabled',
				'createdAt', 'updatedAt', 'parentUuid', 'slug', 'sortOrder', 'publicationStatus', 'publishAt',
				'publishedAt', 'unpublishAt', 'dashboardFooterMode', 'dashboardFooterHtml', 'defaultLinkTarget', 'hierarchies', 'templateCategory',
				'templateDescription', 'templatePreviewImage', 'themeAccent', 'themeBackgroundColor',
				'themeBackgroundResource', 'themeTextColor', 'customCss',
			],
			array_keys($data)
		);
		$this->assertSame(
			json_encode([
				3, 'u-3', 'Home', null, null, 'user', 'alice', null, null, 12, 'full', ['staff'], 0, 0, null, null, null,
				null, null, null, 'home', 0, 'published', null, null, null, 'custom', null, null, [], 'team', null, null,
				null, null, null, null, null,
			]),
			json_encode(array_values($data))
		);
	}

	public function testTheViewerView(): void {
		$data = self::dashboard()->toViewerArray();
		$this->assertArrayNotHasKey('userId', $data);
		$this->assertArrayNotHasKey('templateCategory', $data);
		$this->assertCount(expectedCount: 34, haystack: $data);
		$this->assertArrayHasKey('defaultLinkTarget', $data);
		$this->assertSame(expected: [], actual: $data['hierarchies']);
	}

	public function testALookSerialisesWithItsDerivedTextColour(): void {
		$dashboard = self::dashboard();
		$dashboard->setThemeAccent('#1f5c3a');
		$dashboard->setThemeBackgroundColor('#12301f');
		$dashboard->setThemeBackgroundResource('resource_abc.png');

		$data = $dashboard->toViewerArray();
		$this->assertSame('#1f5c3a', $data['themeAccent']);
		$this->assertSame('#12301f', $data['themeBackgroundColor']);
		$this->assertSame('resource_abc.png', $data['themeBackgroundResource']);
		$this->assertSame('#ffffff', $data['themeTextColor'], 'a dark green background gets white text (REQ-THM-002)');
	}

	/**
	 * A group-shared dashboard keeps its groupId in the viewer view, a
	 * personal one carries groupId null (REQ-DASH-014, "Group-shared
	 * dashboard serialisation includes groupId").
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboards/spec.md#requirement-req-dash-014-group-shared-dashboard-mutation-endpoints
	 */
	public function testTheViewerViewKeepsTheGroupId(): void {
		$shared = self::dashboard();
		// Entity setters take positional arguments (Entity::__call reads $args[0]).
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$shared->setType('group_shared');
		$shared->setGroupId('marketing');
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters

		$this->assertSame(expected: 'marketing', actual: $shared->toViewerArray()['groupId']);
		$this->assertArrayHasKey(key: 'groupId', array: self::dashboard()->toViewerArray());
		$this->assertNull(actual: self::dashboard()->toViewerArray()['groupId']);
	}
}
