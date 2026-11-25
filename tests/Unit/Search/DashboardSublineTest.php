<?php

/**
 * DashboardSublineTest
 *
 * Unit coverage for the detail line of a dashboard search result
 * (REQ-SRCH-013): shared by whom with how many widgets, or the group.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Search
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */

declare(strict_types=1);

namespace Unit\Search;

use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Search\DashboardSubline;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests for {@see DashboardSubline}.
 */
class DashboardSublineTest extends TestCase {
	/** @var IUserManager&MockObject */
	private IUserManager $userManager;

	/** @var IGroupManager&MockObject */
	private IGroupManager $groupManager;

	/** @var WidgetPlacementMapper&MockObject */
	private WidgetPlacementMapper $placementMapper;

	private IL10N $l10n;

	private DashboardSubline $subline;

	/**
	 * Mocked managers; l10n formats `%n` and its parameters.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->placementMapper = $this->createMock(WidgetPlacementMapper::class);

		$l10n = $this->createMock(IL10N::class);
		$l10n->method('t')->willReturnCallback(
			static fn (string $text, array $params = []): string => vsprintf($text, $params)
		);
		$l10n->method('n')->willReturnCallback(
			static function (string $singular, string $plural, int $count, array $params = []): string {
				$text = $plural;
				if ($count === 1) {
					$text = $singular;
				}
				return vsprintf(str_replace('%n', (string)$count, $text), $params);
			}
		);
		$this->l10n = $l10n;

		$this->subline = new DashboardSubline(
			userManager: $this->userManager,
			groupManager: $this->groupManager,
			placementMapper: $this->placementMapper
		);
	}//end setUp()

	/**
	 * A dashboard with the given owner and group.
	 *
	 * @param string|null $userId  The owner.
	 * @param string|null $groupId The group.
	 *
	 * @return Dashboard The entity.
	 */
	private function dashboard(?string $userId, ?string $groupId): Dashboard {
		$dashboard = new Dashboard();
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$dashboard->setId(3);
		$dashboard->setUserId($userId);
		$dashboard->setGroupId($groupId);
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		return $dashboard;
	}//end dashboard()

	/**
	 * A shared dashboard reads "Shared by <owner> · <n> widgets".
	 *
	 * @return void
	 */
	public function testSharedNamesOwnerAndCount(): void {
		$this->userManager->method('getDisplayName')->with('marieke')->willReturn('Marieke Jansen');
		$this->placementMapper->method('countByDashboardId')->with(3)->willReturn(14);

		self::assertSame(
			'Shared by Marieke Jansen · 14 widgets',
			$this->subline->forDashboard($this->dashboard('marieke', null), Dashboard::SOURCE_SHARED, $this->l10n)
		);
	}//end testSharedNamesOwnerAndCount()

	/**
	 * One widget takes the singular form.
	 *
	 * @return void
	 */
	public function testSharedSingularWidget(): void {
		$this->userManager->method('getDisplayName')->willReturn('Marieke Jansen');
		$this->placementMapper->method('countByDashboardId')->willReturn(1);

		self::assertSame(
			'Shared by Marieke Jansen · 1 widget',
			$this->subline->forDashboard($this->dashboard('marieke', null), Dashboard::SOURCE_SHARED, $this->l10n)
		);
	}//end testSharedSingularWidget()

	/**
	 * An owner without a display name falls back to the user id, and a count
	 * that fails reads as 0.
	 *
	 * @return void
	 */
	public function testSharedFallsBackToUidAndZero(): void {
		$this->userManager->method('getDisplayName')->willReturn(null);
		$this->placementMapper->method('countByDashboardId')->willThrowException(new RuntimeException('db'));

		self::assertSame(
			'Shared by gone · 0 widgets',
			$this->subline->forDashboard($this->dashboard('gone', null), Dashboard::SOURCE_SHARED, $this->l10n)
		);
	}//end testSharedFallsBackToUidAndZero()

	/**
	 * A shared dashboard without an owner has no detail line.
	 *
	 * @return void
	 */
	public function testSharedWithoutOwnerIsNull(): void {
		self::assertNull(
			$this->subline->forDashboard($this->dashboard(null, null), Dashboard::SOURCE_SHARED, $this->l10n)
		);
	}//end testSharedWithoutOwnerIsNull()

	/**
	 * A group dashboard reads "Group dashboard of <group display name>".
	 *
	 * @return void
	 */
	public function testGroupNamesTheGroup(): void {
		$group = $this->createMock(IGroup::class);
		$group->method('getDisplayName')->willReturn('Communications');
		$this->groupManager->method('get')->with('comms')->willReturn($group);

		self::assertSame(
			'Group dashboard of Communications',
			$this->subline->forDashboard($this->dashboard(null, 'comms'), Dashboard::SOURCE_GROUP, $this->l10n)
		);
	}//end testGroupNamesTheGroup()

	/**
	 * An unknown group falls back to its id.
	 *
	 * @return void
	 */
	public function testUnknownGroupFallsBackToId(): void {
		$this->groupManager->method('get')->willReturn(null);

		self::assertSame(
			'Group dashboard of comms',
			$this->subline->forDashboard($this->dashboard(null, 'comms'), Dashboard::SOURCE_GROUP, $this->l10n)
		);
	}//end testUnknownGroupFallsBackToId()

	/**
	 * The default sentinel group and the viewer's own dashboards have no line.
	 *
	 * @return void
	 */
	public function testDefaultGroupAndOwnDashboardsAreNull(): void {
		self::assertNull(
			$this->subline->forDashboard(
				$this->dashboard(null, Dashboard::DEFAULT_GROUP_ID),
				Dashboard::SOURCE_GROUP,
				$this->l10n
			)
		);
		self::assertNull(
			$this->subline->forDashboard($this->dashboard('pieter', null), Dashboard::SOURCE_USER, $this->l10n)
		);
	}//end testDefaultGroupAndOwnDashboardsAreNull()
}//end class
