<?php

/**
 * AllowedWidgetFilterTest
 *
 * Unit tests for filtering the widget catalogue to the widgets a user's roles allow.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Controller
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/specs/role-feature-permissions/spec.md
 */

declare(strict_types=1);

namespace Unit\Controller;

use OCA\LaunchPad\Controller\AllowedWidgetFilter;
use OCA\LaunchPad\Service\RoleFeaturePermissionService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AllowedWidgetFilter.
 *
 * @covers \OCA\LaunchPad\Controller\AllowedWidgetFilter
 */
class AllowedWidgetFilterTest extends TestCase {
	private const WIDGETS = [
		['id' => 'calendar', 'title' => 'Calendar'],
		['id' => 'notes'],
		['title' => 'No id'],
		['id' => ''],
		['id' => 7],
	];

	public function testWithoutAUserEverythingIsKeptAndNothingIsAsked(): void {
		$permissions = $this->createMock(RoleFeaturePermissionService::class);
		$permissions->expects($this->never())->method('getAllowedWidgetIds');

		$this->assertSame(self::WIDGETS, (new AllowedWidgetFilter($permissions, null))->filter(self::WIDGETS));
	}

	public function testWithoutRestrictionsEverythingIsKept(): void {
		$permissions = $this->createMock(RoleFeaturePermissionService::class);
		$permissions->expects($this->once())->method('getAllowedWidgetIds')->with('alice')->willReturn(null);

		$this->assertSame(self::WIDGETS, (new AllowedWidgetFilter($permissions, 'alice'))->filter(self::WIDGETS));
	}

	public function testOnlyAllowedIdsAreKeptInOrder(): void {
		$permissions = $this->createMock(RoleFeaturePermissionService::class);
		$permissions->method('getAllowedWidgetIds')->willReturn(['notes', 'calendar', '7', '']);

		$this->assertSame(
			[['id' => 'calendar', 'title' => 'Calendar'], ['id' => 'notes'], ['id' => 7]],
			(new AllowedWidgetFilter($permissions, 'alice'))->filter(self::WIDGETS)
		);
	}

	public function testAnEmptyAllowListKeepsNothing(): void {
		$permissions = $this->createMock(RoleFeaturePermissionService::class);
		$permissions->method('getAllowedWidgetIds')->willReturn([]);

		$this->assertSame([], (new AllowedWidgetFilter($permissions, 'alice'))->filter(self::WIDGETS));
	}

	public function testMatchingIsStrictOnStrings(): void {
		$permissions = $this->createMock(RoleFeaturePermissionService::class);
		$permissions->method('getAllowedWidgetIds')->willReturn([7]);

		$this->assertSame([], (new AllowedWidgetFilter($permissions, 'alice'))->filter([['id' => 7]]));
	}
}
