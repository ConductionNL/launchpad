<?php

/**
 * DashboardServiceSharedResolutionTest
 *
 * Unit tests for how dashboards SHARED with a user enter
 * {@see \OCA\LaunchPad\Service\DashboardService}'s visibility set and its
 * active-dashboard precedence chain (REQ-SHARE-002, REQ-DASH-013,
 * REQ-DASH-018).
 *
 * REGRESSION GUARD: `DashboardMapper::findVisibleToUser()` unions exactly
 * three buckets — owned rows, `group_shared` rows in the user's groups, and
 * the `default` sentinel. Share rows were in none of them, so a share was
 * invisible everywhere: not in the switcher, never a resolution candidate,
 * and never reachable by uuid through `getDashboardForUser()`.
 *
 * PRECEDENCE UNDER TEST: a share is the LAST-RESORT candidate. Anything the
 * user owns, reaches through a group, or explicitly selected wins over it,
 * so a share can never hijack an existing selection.
 *
 * @category  Test
 * @package   OCA\LaunchPad\Tests\Unit\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 *
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace Unit\Service;

use OCA\LaunchPad\Service\DashboardOutlets;
use OCA\LaunchPad\Service\DashboardPlatform;
use OCA\LaunchPad\Service\DashboardStructure;
use OCA\LaunchPad\Service\DashboardBuilders;
use OCA\LaunchPad\Service\DashboardStores;
use OCA\LaunchPad\Db\AdminSettingMapper;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Service\AdminTemplateService;
use OCA\LaunchPad\Service\DashboardFactory;
use OCA\LaunchPad\Service\DashboardResolver;
use OCA\LaunchPad\Service\DashboardService;
use OCA\LaunchPad\Service\PersonalLayerService;
use OCA\LaunchPad\Service\TemplateService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\L10N\IFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Tests for share-aware visibility and resolution precedence.
 *
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DashboardServiceSharedResolutionTest extends TestCase {

	/** @var DashboardMapper&MockObject */
	private $dashboardMapper;

	/** @var WidgetPlacementMapper&MockObject */
	private $placementMapper;

	/** @var DashboardResolver&MockObject */
	private $dashResolver;

	/** @var AdminTemplateService&MockObject */
	private $adminTemplateService;

	/** @var IGroupManager&MockObject */
	private $groupManager;

	/** @var IConfig&MockObject */
	private $config;

	private DashboardService $service;

	/**
	 * The user preferences the config double answers, by key.
	 *
	 * @var array<string, string>
	 */
	private array $prefs = [];

	/**
	 * Whether the group manager double reports the caller as an admin.
	 *
	 * @var boolean
	 */
	private bool $admin = false;

	/**
	 * Set up fresh mocks per test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->dashboardMapper = $this->createMock(DashboardMapper::class);
		$this->placementMapper = $this->createMock(WidgetPlacementMapper::class);
		$this->dashResolver = $this->createMock(DashboardResolver::class);
		$this->adminTemplateService = $this->createMock(AdminTemplateService::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->config = $this->createMock(IConfig::class);

		$this->adminTemplateService->method('getUserGroupIdsFor')->willReturn([]);
		$this->groupManager->method('isAdmin')->willReturnCallback(fn (): bool => $this->admin);
		$this->config->method('getUserValue')->willReturnCallback(
			fn (string $userId, string $appName, string $key, mixed $default = ''): string => ($this->prefs[$key] ?? '')
		);

		$this->service = new DashboardService(
			stores: new DashboardStores(
				dashboardMapper: $this->dashboardMapper,
				placementMapper: $this->placementMapper,
				settingMapper: $this->createMock(AdminSettingMapper::class)
			),
			builders: new DashboardBuilders(
				templateService: $this->createMock(TemplateService::class),
				dashboardFactory: new DashboardFactory(),
				dashResolver: $this->dashResolver,
				adminTemplateService: $this->adminTemplateService
			),
			structure: new DashboardStructure(
				treeService: $this->createMock(\OCA\LaunchPad\Service\DashboardTreeService::class),
				personalLayers: $this->createMock(PersonalLayerService::class)
			),
			platform: new DashboardPlatform(
				groupManager: $this->groupManager,
				db: $this->createMock(IDBConnection::class),
				config: $this->config,
				l10nFactory: $this->createMock(IFactory::class)
			),
			outlets: new DashboardOutlets(
				logger: $this->createMock(LoggerInterface::class)
			)
		);
	}//end setUp()

	/**
	 * Build a published Dashboard entity.
	 *
	 * @param integer $id Entity id.
	 * @param string $uuid Entity uuid.
	 * @param ?string $owner Owning user id.
	 * @param string $type Dashboard type.
	 *
	 * @return Dashboard
	 */
	private function makeDashboard(
		int $id,
		string $uuid,
		?string $owner,
		string $type = Dashboard::TYPE_USER,
	): Dashboard {
		$dashboard = new Dashboard();
		$dashboard->setId($id);
		$dashboard->setUuid($uuid);
		$dashboard->setName('Board ' . $id);
		$dashboard->setUserId($owner);
		$dashboard->setType($type);
		$dashboard->setPermissionLevel(Dashboard::PERMISSION_FULL);
		$dashboard->setPublicationStatus(Dashboard::STATUS_PUBLISHED);

		return $dashboard;
	}//end makeDashboard()

	/**
	 * The recipient's share set holding one dashboard.
	 *
	 * @param Dashboard $shared The shared dashboard.
	 *
	 * @return void
	 */
	private function shareWithRecipient(Dashboard $shared): void {
		$this->dashboardMapper->method('findVisibleToUser')->willReturn([]);
		$this->dashResolver->method('findSharedDashboards')->willReturn(
			[
				[
					'dashboard' => $shared,
					'source' => Dashboard::SOURCE_SHARED,
				],
			]
		);
	}//end shareWithRecipient()

	/**
	 * REQ-DASH-036: a draft is visible only to its owner and admins, so a
	 * share recipient does not see it. Built through {@see DashboardFactory}
	 * so the dashboard is in the state a fresh share really has.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-sharing/spec.md
	 */
	public function testSharedDraftStaysHiddenFromItsRecipient(): void {
		$shared = (new DashboardFactory())->create(userId: 'owner', name: 'Draft board');
		$shared->setId(7);
		$shared->setUuid('uuid-shared-draft');
		$this->assertSame(expected: Dashboard::STATUS_DRAFT, actual: $shared->getPublicationStatus());
		$this->shareWithRecipient(shared: $shared);

		$this->assertSame(expected: [], actual: $this->service->getVisibleToUser(userId: 'recipient'));
		$this->assertNull(actual: $this->service->getDashboardForUser(dashboardId: 7, userId: 'recipient'));
	}//end testSharedDraftStaysHiddenFromItsRecipient()

	/**
	 * REQ-SHARE-002: the same share shows up once the owner publishes.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-sharing/spec.md
	 */
	public function testSharedDashboardShowsOnceItIsPublished(): void {
		$shared = (new DashboardFactory())->create(userId: 'owner', name: 'Draft board');
		$shared->setId(7);
		$shared->setUuid('uuid-shared-draft');
		$shared->setPublicationStatus(Dashboard::STATUS_PUBLISHED);
		$this->shareWithRecipient(shared: $shared);

		$visible = $this->service->getVisibleToUser(userId: 'recipient');

		$this->assertCount(expectedCount: 1, haystack: $visible);
		$this->assertSame(Dashboard::SOURCE_SHARED, $visible[0]['source']);
	}//end testSharedDashboardShowsOnceItIsPublished()

	/**
	 * A shared scheduled dashboard appears once `publishAt` has passed, and
	 * reads as published.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function testSharedScheduledDashboardShowsOnceDue(): void {
		$due = $this->makeDashboard(id: 7, uuid: 'uuid-due', owner: 'owner');
		$due->setPublicationStatus(Dashboard::STATUS_SCHEDULED);
		$due->setPublishAt('2000-01-01T00:00:00+00:00');
		$this->shareWithRecipient(shared: $due);

		$visible = $this->service->getVisibleToUser(userId: 'recipient');

		$this->assertCount(expectedCount: 1, haystack: $visible);
		$this->assertSame(expected: Dashboard::STATUS_PUBLISHED, actual: $visible[0]['dashboard']->getPublicationStatus());
	}//end testSharedScheduledDashboardShowsOnceDue()

	/**
	 * A shared dashboard scheduled for later stays hidden from the recipient.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function testSharedFutureScheduledDashboardStaysHidden(): void {
		$later = $this->makeDashboard(id: 7, uuid: 'uuid-later', owner: 'owner');
		$later->setPublicationStatus(Dashboard::STATUS_SCHEDULED);
		$later->setPublishAt('2999-01-01T00:00:00+00:00');
		$this->shareWithRecipient(shared: $later);

		$this->assertSame(expected: [], actual: $this->service->getVisibleToUser(userId: 'recipient'));
	}//end testSharedFutureScheduledDashboardStaysHidden()

	/**
	 * REQ-DASH-036: an administrator keeps seeing a draft, shared or not.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function testAdminRecipientStillSeesASharedDraft(): void {
		$this->admin = true;
		$draft = $this->makeDashboard(id: 7, uuid: 'uuid-shared-draft', owner: 'owner');
		$draft->setPublicationStatus(Dashboard::STATUS_DRAFT);
		$this->shareWithRecipient(shared: $draft);

		$this->assertCount(expectedCount: 1, haystack: $this->service->getVisibleToUser(userId: 'root'));
	}//end testAdminRecipientStillSeesASharedDraft()

	/**
	 * `GET /api/dashboard` agrees with the visible list: a shared draft is
	 * not landed on, resolution goes on to the template step.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboard-sharing/spec.md
	 */
	public function testEffectiveDashboardSkipsASharedDraft(): void {
		$draft = $this->makeDashboard(id: 7, uuid: 'uuid-shared-draft', owner: 'owner');
		$draft->setPublicationStatus(Dashboard::STATUS_DRAFT);
		$this->dashResolver->method('tryGetActiveDashboard')->willReturn(null);
		$this->dashResolver->method('tryActivateExistingDashboard')->willReturn(null);
		$this->shareWithRecipient(shared: $draft);

		$result = $this->service->getEffectiveDashboard(userId: 'recipient');

		$this->assertTrue(condition: $result === null || $result['dashboard']->getUuid() !== 'uuid-shared-draft');
	}//end testEffectiveDashboardSkipsASharedDraft()

	/**
	 * REQ-SHARE-005: a recipient activating a shared dashboard gets it back
	 * and no active flag is written.
	 *
	 * @return void
	 */
	public function testRecipientActivatingASharedDashboardWritesNothing(): void {
		$shared = $this->makeDashboard(id: 7, uuid: 'uuid-shared', owner: 'owner');
		$this->dashboardMapper->method('find')->willReturn($shared);
		$this->dashboardMapper->method('findVisibleToUser')->willReturn([]);
		$this->dashResolver->method('findSharedDashboards')->willReturn(
			[['dashboard' => $shared, 'source' => Dashboard::SOURCE_SHARED]]
		);
		$this->dashboardMapper->expects($this->never())->method('setActive');

		$this->assertSame(expected: $shared, actual: $this->service->activateDashboard(dashboardId: 7, userId: 'recipient'));
	}//end testRecipientActivatingASharedDashboardWritesNothing()

	/**
	 * Someone the dashboard is not shared with is still refused.
	 *
	 * @return void
	 */
	public function testStrangerActivatingAForeignDashboardIsRefused(): void {
		$foreign = $this->makeDashboard(id: 7, uuid: 'uuid-foreign', owner: 'owner');
		$this->dashboardMapper->method('find')->willReturn($foreign);
		$this->dashboardMapper->method('findVisibleToUser')->willReturn([]);
		$this->dashResolver->method('findSharedDashboards')->willReturn([]);
		$this->dashboardMapper->expects($this->never())->method('setActive');

		$this->expectException(exception: \OCA\LaunchPad\Exception\ForbiddenException::class);
		$this->service->activateDashboard(dashboardId: 7, userId: 'stranger');
	}//end testStrangerActivatingAForeignDashboardIsRefused()

	/**
	 * A draft reached through the default bucket stays hidden too.
	 *
	 * @return void
	 */
	public function testUnsharedDraftStaysHiddenFromNonOwners(): void {
		$draft = $this->makeDashboard(3, 'uuid-foreign-draft', 'someone-else');
		$draft->setPublicationStatus(Dashboard::STATUS_DRAFT);

		$this->dashboardMapper->method('findVisibleToUser')->willReturn(
			[
				[
					'dashboard' => $draft,
					'source' => Dashboard::SOURCE_DEFAULT,
				],
			]
		);
		$this->dashResolver->method('findSharedDashboards')->willReturn([]);

		$this->assertSame(
			[],
			$this->service->getVisibleToUser(userId: 'recipient'),
			'A draft nobody shared with this user must stay hidden.'
		);
	}//end testUnsharedDraftStaysHiddenFromNonOwners()

	/**
	 * REQ-SHARE-002: a shared dashboard is part of "visible to me", tagged
	 * with the `shared` source. Without the fix `getVisibleToUser()` returns
	 * only what the mapper's three-bucket union produced — an empty set.
	 *
	 * @return void
	 */
	public function testVisibleToUserIncludesSharedDashboards(): void {
		$shared = $this->makeDashboard(7, 'uuid-shared', 'owner');

		$this->dashboardMapper->method('findVisibleToUser')->willReturn([]);
		$this->dashResolver->method('findSharedDashboards')->willReturn(
			[
				[
					'dashboard' => $shared,
					'source' => Dashboard::SOURCE_SHARED,
				],
			]
		);

		$visible = $this->service->getVisibleToUser(userId: 'recipient');

		$this->assertCount(expectedCount: 1, haystack: $visible);
		$this->assertSame('uuid-shared', $visible[0]['dashboard']->getUuid());
		$this->assertSame(Dashboard::SOURCE_SHARED, $visible[0]['source']);
	}//end testVisibleToUserIncludesSharedDashboards()

	/**
	 * A dashboard reached BOTH by ownership/group and by a share keeps its
	 * stronger source tag — the shared copy is deduped away by uuid.
	 *
	 * @return void
	 */
	public function testSharedEntryIsDedupedAgainstAStrongerSource(): void {
		$own = $this->makeDashboard(7, 'uuid-dup', 'recipient');

		$this->dashboardMapper->method('findVisibleToUser')->willReturn(
			[
				[
					'dashboard' => $own,
					'source' => Dashboard::SOURCE_USER,
				],
			]
		);
		$this->dashResolver->method('findSharedDashboards')->willReturn(
			[
				[
					'dashboard' => $own,
					'source' => Dashboard::SOURCE_SHARED,
				],
			]
		);

		$visible = $this->service->getVisibleToUser(userId: 'recipient');

		$this->assertCount(expectedCount: 1, haystack: $visible);
		$this->assertSame(Dashboard::SOURCE_USER, $visible[0]['source']);
	}//end testSharedEntryIsDedupedAgainstAStrongerSource()

	/**
	 * REQ-DASH-018 step 6b: a recipient with NOTHING of their own resolves
	 * to the dashboard shared with them instead of the empty state. This is
	 * the whole point of the fix — without it `resolveActiveDashboard()`
	 * returns null and the share is inert.
	 *
	 * @return void
	 */
	public function testResolveActiveDashboardLandsOnASharedDashboard(): void {
		$shared = $this->makeDashboard(7, 'uuid-shared', 'owner');

		$this->dashboardMapper->method('findVisibleToUser')->willReturn([]);
		$this->dashResolver->method('findSharedDashboards')->willReturn(
			[
				[
					'dashboard' => $shared,
					'source' => Dashboard::SOURCE_SHARED,
				],
			]
		);

		$result = $this->service->resolveActiveDashboard(
			userId: 'recipient',
			primaryGroupId: null
		);

		$this->assertNotNull($result, 'A recipient must land on the shared dashboard.');
		$this->assertSame('uuid-shared', $result['dashboard']->getUuid());
		$this->assertSame(Dashboard::SOURCE_SHARED, $result['source']);
	}//end testResolveActiveDashboardLandsOnASharedDashboard()

	/**
	 * PRECEDENCE: the user's OWN dashboard beats a shared one. A share must
	 * never hijack a selection the user already has.
	 *
	 * @return void
	 */
	public function testOwnDashboardWinsOverASharedOne(): void {
		$own = $this->makeDashboard(1, 'uuid-own', 'recipient');
		$shared = $this->makeDashboard(7, 'uuid-shared', 'owner');

		$this->dashboardMapper->method('findVisibleToUser')->willReturn(
			[
				[
					'dashboard' => $own,
					'source' => Dashboard::SOURCE_USER,
				],
			]
		);
		$this->dashResolver->method('findSharedDashboards')->willReturn(
			[
				[
					'dashboard' => $shared,
					'source' => Dashboard::SOURCE_SHARED,
				],
			]
		);

		$result = $this->service->resolveActiveDashboard(
			userId: 'recipient',
			primaryGroupId: null
		);

		$this->assertSame('uuid-own', $result['dashboard']->getUuid());
		$this->assertSame(Dashboard::SOURCE_USER, $result['source']);
	}//end testOwnDashboardWinsOverASharedOne()

	/**
	 * PRECEDENCE: a group-shared dashboard (steps 2-5) also beats a share.
	 *
	 * @return void
	 */
	public function testGroupSharedDashboardWinsOverASharedOne(): void {
		$groupBoard = $this->makeDashboard(
			2,
			'uuid-group',
			null,
			Dashboard::TYPE_GROUP_SHARED
		);
		$groupBoard->setGroupId(Dashboard::DEFAULT_GROUP_ID);
		$shared = $this->makeDashboard(7, 'uuid-shared', 'owner');

		$this->dashboardMapper->method('findVisibleToUser')->willReturn(
			[
				[
					'dashboard' => $groupBoard,
					'source' => Dashboard::SOURCE_DEFAULT,
				],
			]
		);
		$this->dashResolver->method('findSharedDashboards')->willReturn(
			[
				[
					'dashboard' => $shared,
					'source' => Dashboard::SOURCE_SHARED,
				],
			]
		);

		$result = $this->service->resolveActiveDashboard(
			userId: 'recipient',
			primaryGroupId: null
		);

		$this->assertSame('uuid-group', $result['dashboard']->getUuid());
	}//end testGroupSharedDashboardWinsOverASharedOne()

	/**
	 * REQ-SHARE-002: `getEffectiveDashboard()` (the `GET /api/dashboard`
	 * path) consults the share BEFORE auto-provisioning a dashboard from a
	 * template — minting an empty personal dashboard would permanently bury
	 * the share behind step 6.
	 *
	 * @return void
	 */
	public function testEffectiveDashboardPrefersASharedDashboardOverTemplateCreation(): void {
		$shared = $this->makeDashboard(7, 'uuid-shared', 'owner');

		$this->dashResolver->method('tryGetActiveDashboard')->willReturn(null);
		$this->dashResolver->method('tryActivateExistingDashboard')->willReturn(null);
		$this->shareWithRecipient(shared: $shared);
		$this->dashResolver->method('findSharedLevels')->willReturn(
			[7 => Dashboard::PERMISSION_VIEW_ONLY]
		);
		$this->dashResolver->method('buildResult')->willReturnCallback(
			static fn (Dashboard $dashboard, array $placements): array => [
				'dashboard' => $dashboard,
				'placements' => $placements,
				'permissionLevel' => Dashboard::PERMISSION_FULL,
			]
		);

		// If the shared step were missing, resolution would fall through to
		// the template path, which asks the mapper for admin templates.
		$this->dashboardMapper
			->expects($this->never())
			->method('findAdminTemplates');

		$result = $this->service->getEffectiveDashboard(userId: 'recipient');

		$this->assertNotNull($result);
		$this->assertSame('uuid-shared', $result['dashboard']->getUuid());
		$this->assertSame(
			Dashboard::PERMISSION_VIEW_ONLY,
			$result['permissionLevel']
		);
	}//end testEffectiveDashboardPrefersASharedDashboardOverTemplateCreation()

	/**
	 * REQ-SHARE-004: fetching a shared dashboard by id reports the SHARE's
	 * permission level, not the owning row's.
	 *
	 * @return void
	 */
	public function testGetDashboardForUserReportsTheSharePermissionLevel(): void {
		$shared = $this->makeDashboard(7, 'uuid-shared', 'owner');

		$this->dashboardMapper->method('findVisibleToUser')->willReturn([]);
		$this->dashResolver->method('findSharedDashboards')->willReturn(
			[
				[
					'dashboard' => $shared,
					'source' => Dashboard::SOURCE_SHARED,
				],
			]
		);
		$this->dashResolver->method('findSharedLevels')->willReturn(
			[7 => Dashboard::PERMISSION_VIEW_ONLY]
		);
		$this->placementMapper->method('findByDashboardId')->willReturn([]);

		$result = $this->service->getDashboardForUser(
			dashboardId: 7,
			userId: 'recipient'
		);

		$this->assertNotNull($result, 'A shared dashboard must be fetchable by id.');
		$this->assertSame(
			Dashboard::PERMISSION_VIEW_ONLY,
			$result['permissionLevel']
		);
	}//end testGetDashboardForUserReportsTheSharePermissionLevel()
	/**
	 * REQ-SHARE-004: a shared dashboard the user made active reports the
	 * SHARE's level on `GET /api/dashboard`, not the owner's row level, so a
	 * view-only recipient is not offered editing.
	 *
	 * @return void
	 */
	public function testAnActiveSharedDashboardReportsTheSharePermissionLevel(): void {
		$shared = $this->makeDashboard(7, 'uuid-shared', 'owner');
		$this->prefs[DashboardService::DEFAULT_DASHBOARD_UUID_PREF_KEY] = 'uuid-shared';

		$this->dashboardMapper->method('findVisibleToUser')->willReturn([]);
		$this->dashResolver->method('findSharedDashboards')->willReturn(
			[
				[
					'dashboard' => $shared,
					'source' => Dashboard::SOURCE_SHARED,
				],
			]
		);
		$this->dashResolver->method('findSharedLevels')->willReturn(
			[7 => Dashboard::PERMISSION_VIEW_ONLY]
		);
		$this->dashResolver->method('buildResult')->willReturnCallback(
			static fn (Dashboard $dashboard, array $placements): array => [
				'dashboard' => $dashboard,
				'placements' => $placements,
				'permissionLevel' => $dashboard->getPermissionLevel(),
			]
		);
		$this->placementMapper->method('findByDashboardId')->willReturn([]);

		$result = $this->service->getEffectiveDashboard(userId: 'recipient');

		$this->assertNotNull($result);
		$this->assertSame('uuid-shared', $result['dashboard']->getUuid());
		$this->assertSame(Dashboard::PERMISSION_VIEW_ONLY, $result['permissionLevel']);
	}//end testAnActiveSharedDashboardReportsTheSharePermissionLevel()
	/**
	 * An organisation-wide dashboard the user set aside is not visible to
	 * them; what they own stays, even when listed too.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-25-launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	public function testASetAsideOrganisationDashboardIsNotVisible(): void {
		$own = $this->makeDashboard(id: 1, uuid: 'uuid-own', owner: 'nieuw');
		$org = $this->makeDashboard(id: 2, uuid: 'uuid-org', owner: null, type: Dashboard::TYPE_GROUP_SHARED);
		$this->dashboardMapper->method('findVisibleToUser')->willReturn(
			[
				['dashboard' => $own, 'source' => Dashboard::SOURCE_USER],
				['dashboard' => $org, 'source' => Dashboard::SOURCE_DEFAULT],
			]
		);
		$this->prefs[DashboardService::HIDDEN_DASHBOARDS_PREF_KEY] = '["uuid-org","uuid-own"]';

		$visible = $this->service->getVisibleToUser(userId: 'nieuw');

		$this->assertSame(expected: ['uuid-own'], actual: array_map(fn (array $e): string => (string)$e['dashboard']->getUuid(), $visible));
	}//end testASetAsideOrganisationDashboardIsNotVisible()

	/**
	 * A malformed set-aside list hides nothing.
	 *
	 * @return void
	 *
	 * @spec openspec/changes/archive/2026-11-25-launchpad-demo-seed/specs/demo-data-showcases/spec.md
	 */
	public function testAMalformedSetAsideListHidesNothing(): void {
		$org = $this->makeDashboard(id: 2, uuid: 'uuid-org', owner: null, type: Dashboard::TYPE_GROUP_SHARED);
		$this->dashboardMapper->method('findVisibleToUser')->willReturn(
			[['dashboard' => $org, 'source' => Dashboard::SOURCE_DEFAULT]]
		);
		$this->prefs[DashboardService::HIDDEN_DASHBOARDS_PREF_KEY] = 'not json';

		$this->assertCount(expectedCount: 1, haystack: $this->service->getVisibleToUser(userId: 'nieuw'));
	}//end testAMalformedSetAsideListHidesNothing()
}//end class
