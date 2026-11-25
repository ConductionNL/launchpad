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
use OCA\LaunchPad\Search\LaunchPadSearchProvider;
use OCA\LaunchPad\Search\SearchLinks;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\MetadataService;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Search\ISearchQuery;
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

	/** @var IL10N The translations, formatting `%n` and parameters. */
	private IL10N $l10n;

	/** @var DashboardSubline The helper under test. */
	private DashboardSubline $subline;

	/**
	 * Mocked managers; l10n formats `%n` and its parameters.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->userManager = $this->createMock(originalClassName: IUserManager::class);
		$this->groupManager = $this->createMock(originalClassName: IGroupManager::class);
		$this->placementMapper = $this->createMock(originalClassName: WidgetPlacementMapper::class);

		$l10n = $this->createMock(originalClassName: IL10N::class);
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
			expected: 'Shared by Marieke Jansen · 14 widgets',
			actual: $this->subline->forDashboard(
				dashboard: $this->dashboard(userId: 'marieke', groupId: null),
				source: Dashboard::SOURCE_SHARED,
				l10n: $this->l10n
			)
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
			expected: 'Shared by Marieke Jansen · 1 widget',
			actual: $this->subline->forDashboard(
				dashboard: $this->dashboard(userId: 'marieke', groupId: null),
				source: Dashboard::SOURCE_SHARED,
				l10n: $this->l10n
			)
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
			expected: 'Shared by gone · 0 widgets',
			actual: $this->subline->forDashboard(
				dashboard: $this->dashboard(userId: 'gone', groupId: null),
				source: Dashboard::SOURCE_SHARED,
				l10n: $this->l10n
			)
		);
	}//end testSharedFallsBackToUidAndZero()

	/**
	 * A shared dashboard without an owner has no detail line.
	 *
	 * @return void
	 */
	public function testSharedWithoutOwnerIsNull(): void {
		self::assertNull(
			actual: $this->subline->forDashboard(
				dashboard: $this->dashboard(userId: null, groupId: null),
				source: Dashboard::SOURCE_SHARED,
				l10n: $this->l10n
			)
		);
	}//end testSharedWithoutOwnerIsNull()

	/**
	 * A group dashboard reads "Group dashboard of <group display name>".
	 *
	 * @return void
	 */
	public function testGroupNamesTheGroup(): void {
		$group = $this->createMock(originalClassName: IGroup::class);
		$group->method('getDisplayName')->willReturn('Communications');
		$this->groupManager->method('get')->with('comms')->willReturn($group);

		self::assertSame(
			expected: 'Group dashboard of Communications',
			actual: $this->subline->forDashboard(
				dashboard: $this->dashboard(userId: null, groupId: 'comms'),
				source: Dashboard::SOURCE_GROUP,
				l10n: $this->l10n
			)
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
			expected: 'Group dashboard of comms',
			actual: $this->subline->forDashboard(
				dashboard: $this->dashboard(userId: null, groupId: 'comms'),
				source: Dashboard::SOURCE_GROUP,
				l10n: $this->l10n
			)
		);
	}//end testUnknownGroupFallsBackToId()

	/**
	 * The default sentinel group and the viewer's own dashboards have no line.
	 *
	 * @return void
	 */
	public function testDefaultGroupAndOwnDashboardsAreNull(): void {
		self::assertNull(
			actual: $this->subline->forDashboard(
				dashboard: $this->dashboard(userId: null, groupId: Dashboard::DEFAULT_GROUP_ID),
				source: Dashboard::SOURCE_GROUP,
				l10n: $this->l10n
			)
		);
		self::assertNull(
			actual: $this->subline->forDashboard(
				dashboard: $this->dashboard(userId: 'pieter', groupId: null),
				source: Dashboard::SOURCE_USER,
				l10n: $this->l10n
			)
		);
	}//end testDefaultGroupAndOwnDashboardsAreNull()

	/**
	 * Through the provider, a shared dashboard's result carries the line, and
	 * the viewer's own dashboard keeps its description.
	 *
	 * @return void
	 */
	public function testProviderUsesTheLine(): void {
		$shared = $this->dashboard(userId: 'marieke', groupId: null);
		$own = $this->dashboard(userId: 'pieter', groupId: null);
		// phpcs:disable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$shared->setName('Permits team');
		$shared->setUuid('uuid-permits');
		$own->setId(4);
		$own->setUuid('uuid-own');
		$own->setName('My team');
		$own->setDescription('Mine');
		// phpcs:enable CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$this->userManager->method('getDisplayName')->willReturn('Marieke Jansen');
		$this->placementMapper->method('countByDashboardId')->willReturn(14);
		$this->placementMapper->method('findByDashboardId')->willReturn([]);

		$dashboards = $this->createMock(originalClassName: DashboardService::class);
		$dashboards->method('getVisibleToUser')->willReturn([
			['dashboard' => $shared, 'source' => Dashboard::SOURCE_SHARED],
			['dashboard' => $own, 'source' => Dashboard::SOURCE_USER],
		]);
		$factory = $this->createMock(originalClassName: IFactory::class);
		$factory->method('get')->willReturn($this->l10n);
		$urls = $this->createMock(originalClassName: IURLGenerator::class);
		$urls->method('linkToRouteAbsolute')->willReturn('https://nc.example/apps/launchpad/');
		$urls->method('imagePath')->willReturn('/apps/launchpad/img/app.svg');
		$urls->method('getAbsoluteURL')->willReturnArgument(0);

		$provider = new LaunchPadSearchProvider(
			dashboardService: $dashboards,
			placementMapper: $this->placementMapper,
			metadataService: $this->createMock(originalClassName: MetadataService::class),
			links: new SearchLinks(l10nFactory: $factory, urlGenerator: $urls, subline: $this->subline)
		);

		$user = $this->createMock(originalClassName: IUser::class);
		$user->method('getUID')->willReturn('pieter');
		$query = $this->createMock(originalClassName: ISearchQuery::class);
		$query->method('getTerm')->willReturn('team');

		$sublines = array_map(
			static fn ($entry): string => $entry->jsonSerialize()['subline'],
			$provider->search(user: $user, query: $query)->jsonSerialize()['entries']
		);

		self::assertSame(expected: ['Shared by Marieke Jansen · 14 widgets', 'Mine'], actual: $sublines);
	}//end testProviderUsesTheLine()
}//end class
