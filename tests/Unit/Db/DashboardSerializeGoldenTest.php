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
				'permissionLevel', 'targetGroups', 'isDefault', 'isActive', 'commentsEnabled', 'reactionsEnabled',
				'createdAt', 'updatedAt', 'parentUuid', 'slug', 'sortOrder', 'publicationStatus', 'publishAt',
				'publishedAt', 'unpublishAt', 'dashboardFooterMode', 'dashboardFooterHtml', 'templateCategory',
				'templateDescription', 'templatePreviewImage',
			],
			array_keys($data)
		);
		$this->assertSame(
			json_encode([
				3, 'u-3', 'Home', null, null, 'user', 'alice', null, null, 12, 'full', ['staff'], 0, 0, null, null,
				null, null, null, 'home', 0, 'published', null, null, null, 'custom', null, 'team', null, null,
			]),
			json_encode(array_values($data))
		);
	}

	public function testTheViewerView(): void {
		$data = self::dashboard()->toViewerArray();
		$this->assertArrayNotHasKey('userId', $data);
		$this->assertArrayNotHasKey('templateCategory', $data);
		$this->assertCount(25, $data);
	}
}
