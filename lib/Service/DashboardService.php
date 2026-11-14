<?php

/**
 * DashboardService
 *
 * Service for managing dashboards (personal, group-shared, and the
 * visible-to-user resolution endpoint).
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2024 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use DateTime;
use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use OCA\LaunchPad\AppInfo\Application;
use OCA\LaunchPad\Db\AdminSetting;
use OCA\LaunchPad\Db\Dashboard;
use OCA\LaunchPad\Db\DashboardMapper;
use OCA\LaunchPad\Db\WidgetPlacement;
use OCA\LaunchPad\Db\WidgetPlacementMapper;
use OCA\LaunchPad\Event\DashboardDeletedEvent;
use OCA\LaunchPad\Exception\DashboardHasChildrenException;
use OCA\LaunchPad\Exception\PersonalDashboardsDisabledException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use Throwable;

/**
 * Service for managing dashboards.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)   Constructor wiring for the
 *                                                  three dashboard scopes at
 *                                                  once: mappers, permission
 *                                                  and share services, the
 *                                                  factory, plus the NC
 *                                                  collaborators the CRUD path
 *                                                  needs (user manager, L10N
 *                                                  factory, locking provider,
 *                                                  logger).
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)     Personal + group-shared + visible-to-user CRUD lives here intentionally.
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) Same; splitting risks losing the single-source-of-truth behaviour.
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)   The constructor wires every dependency the three scopes need.
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)     `resolveActiveDashboard` fans out the 7-step REQ-DASH-018 chain.
 * @SuppressWarnings(PHPMD.ExcessiveClassLength)     Single source of truth for CRUD + tree + publication + footer.
 * @SuppressWarnings(PHPMD.TooManyMethods)           Mode methods live next to one another for grep-ability.
 * @spec                                             openspec/specs/dashboards/spec.md
 */
class DashboardService {
	/**
	 * The optional createDashboard() fields and their defaults.
	 */
	private const CREATE_DEFAULTS = [
		'description'  => null,
		'icon'         => null,
		'parentUuid'   => null,
		'slug'         => null,
		'sortOrder'    => 0,
		'seedDefaults' => false,
	];

	/**
	 * HTTP-like error message for non-admin attempting an admin-only mutation.
	 *
	 * @var string
	 */
	public const ERR_FORBIDDEN_NOT_ADMIN = 'Forbidden: admin only';

	/**
	 * Error message returned by the last-in-group delete guard.
	 *
	 * @var string
	 */
	public const ERR_LAST_IN_GROUP = 'Cannot delete the only dashboard in the group';

	/**
	 * Error message returned when the path-group does not match the record.
	 *
	 * @var string
	 */
	public const ERR_GROUP_MISMATCH = 'Dashboard does not belong to this group';

	/**
	 * Error message returned when the default-flip target is not found.
	 *
	 * @var string
	 */
	public const ERR_DEFAULT_TARGET_NOT_IN_GROUP = 'Dashboard not found in group';

	/**
	 * Preference key for the user's last-active dashboard UUID.
	 *
	 * Stored via IConfig::setUserValue / getUserValue.
	 * REQ-DASH-019.
	 *
	 * @var string
	 */
	public const ACTIVE_DASHBOARD_UUID_PREF_KEY = 'active_dashboard_uuid';

	/**
	 * User-pref key for the EXPLICIT default dashboard (wave3.7).
	 * Distinct from `active_dashboard_uuid` — this one is only ever
	 * written when the user explicitly pins a dashboard via the
	 * per-row "Set as default" action; it is NOT auto-overwritten on
	 * every switch. The resolver checks it BEFORE the active pref so
	 * an explicit pin survives across switches.
	 *
	 * @var string
	 */
	public const DEFAULT_DASHBOARD_UUID_PREF_KEY = 'default_dashboard_uuid';

	/**
	 * HTTP-like error message for non-owner / non-admin attempting a
	 * publication state mutation. REQ-DASH-032..034.
	 *
	 * @var string
	 */
	public const ERR_FORBIDDEN_NOT_OWNER_OR_ADMIN = 'Forbidden: owner or admin only';

	/**
	 * Validation error returned when `publishAt` is missing or in the
	 * past for the schedule action. REQ-DASH-033.
	 *
	 * @var string
	 */
	public const ERR_SCHEDULE_PAST_DATE = 'publishAt must be a future timestamp';

	/**
	 * Validation error when the take-down time is not after the go-live
	 * time (sharing-dashboard-schedule-screen REQ-SCHEDUI-002).
	 *
	 * @var string
	 */
	public const ERR_UNPUBLISH_BEFORE_PUBLISH = 'unpublishAt must be after publishAt';

	/**
	 * The default widget set of a brand-new dashboard.
	 *
	 * A tile with `iconType` `url` names a bundled image under
	 * `launchpad/img/`; {@see self::defaultWidgetSeeds()} resolves it.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private const DEFAULT_WIDGET_SEEDS = [
		[
			'widgetId' => 'tile',
			'gridX' => 0,
			'gridY' => 0,
			'gridWidth' => 4,
			'gridHeight' => 3,
			'sortOrder' => 0,
			'tile' => [
				'title' => 'Conduction',
				'icon' => 'conduction-logo.png',
				'iconType' => 'url',
				'backgroundColor' => '#ffffff',
				'textColor' => '#000000',
				'linkType' => 'url',
				'linkValue' => 'https://conduction.nl',
			],
		],
		[
			'widgetId' => 'tile',
			'gridX' => 4,
			'gridY' => 0,
			'gridWidth' => 4,
			'gridHeight' => 3,
			'sortOrder' => 1,
			'tile' => [
				'title' => 'Sendent',
				'icon' => 'sendent-logo.png',
				'iconType' => 'url',
				'backgroundColor' => '#ffffff',
				'textColor' => '#000000',
				'linkType' => 'url',
				'linkValue' => 'https://sendent.com',
			],
		],
		[
			'widgetId' => 'tile',
			'gridX' => 8,
			'gridY' => 0,
			'gridWidth' => 4,
			'gridHeight' => 3,
			'sortOrder' => 2,
			'tile' => [
				'title' => 'Nextcloud',
				'icon' => 'icon-nextcloud',
				'iconType' => 'class',
				'backgroundColor' => '#0082c9',
				'textColor' => '#ffffff',
				'linkType' => 'url',
				'linkValue' => 'https://nextcloud.com',
			],
		],
		[
			'widgetId' => 'files',
			'gridX' => 0,
			'gridY' => 3,
			'gridWidth' => 12,
			'gridHeight' => 5,
			'sortOrder' => 3,
			'tile' => null,
		],
	];

	/**
	 * Constructor
	 *
	 * @param DashboardStores    $stores The dashboard, placement, admin-setting and lock mappers.
	 * @param DashboardBuilders  $builders The template services, the dashboard factory and the resolver.
	 * @param DashboardStructure $structure The tree, personal-layer, translation and footer services.
	 * @param DashboardPlatform  $platform The group manager, database connection, config and l10n factory.
	 * @param DashboardOutlets   $outlets The logger, event dispatcher, URL generator and activity emitter.
	 * @param DashboardLimits    $limits The optional role-feature, public-share, quota and locking collaborators.
	 */
	public function __construct(
		private readonly DashboardStores $stores,
		private readonly DashboardBuilders $builders,
		private readonly DashboardStructure $structure,
		private readonly DashboardPlatform $platform,
		private readonly DashboardOutlets $outlets,
		private readonly DashboardLimits $limits = new DashboardLimits(),
	) {
	}//end __construct()

	/**
	 * Resolve an app image to a web path that honours the app's actual web
	 * root. Hardcoding `/apps/launchpad/...` breaks when the app is served
	 * from a non-default apps directory (e.g. `/apps-shared/`); IURLGenerator
	 * resolves the correct prefix. Falls back to the legacy literal when the
	 * generator is unavailable (e.g. positional construction in unit tests).
	 *
	 * @param string $image The image filename under `img/`.
	 *
	 * @return string The web path to the image.
	 */
	private function appImagePath(string $image): string {
		if ($this->outlets->urlGenerator !== null) {
			return $this->outlets->urlGenerator->imagePath('launchpad', $image);
		}

		return '/apps/launchpad/img/' . $image;
	}//end appImagePath()

	/**
	 * Get all dashboards for a user.
	 *
	 * Returns only personal `user`-type dashboards owned by the caller —
	 * group-shared dashboards never appear here (REQ-DASH-014, see
	 * `getVisibleToUser` for the unioned endpoint).
	 *
	 * @param string $userId The user ID.
	 *
	 * @return Dashboard[] The list of personal dashboards.
	 * @spec   openspec/specs/dashboards/spec.md
	 */
	public function getUserDashboards(string $userId): array {
		return $this->stores->dashboardMapper->findByUserId(userId: $userId);
	}//end getUserDashboards()

	/**
	 * Get a single dashboard with its placements + permission level when
	 * the user is allowed to read it.
	 *
	 * Visibility is delegated to {@see self::getVisibleToUser()} so
	 * ownership, group-share, and publication-state filters all share one
	 * implementation. The returned shape mirrors {@see DashboardResolver::buildResult()}
	 * so the front-end's switch flow consumes the same envelope it gets
	 * from `GET /api/dashboard` (active dashboard).
	 *
	 * @param int $dashboardId The dashboard ID to load.
	 * @param string $userId The caller's user ID.
	 *
	 * @return array|null `{dashboard, placements, permissionLevel}` when
	 *                    visible; null when the user has no read access.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function getDashboardForUser(
		int $dashboardId,
		string $userId,
	): ?array {
		// `getVisibleToUser` returns entries shaped as
		// `{dashboard: Dashboard, source: string}` (per
		// {@see self::filterByPublicationState()} contract). Match on
		// `dashboard.id` rather than treating the entry itself as an
		// entity — calling `getId()` on the wrapper triggers the
		// "member function on array" fatal that broke the switch flow
		// when this method first shipped.
		$entry = $this->findVisibleEntryById(
			visible: $this->getVisibleToUser(userId: $userId),
			dashboardId: $dashboardId
		);
		if ($entry === null) {
			return null;
		}

		$dashboard = $entry['dashboard'];
		$placements = $this->stores->placementMapper->findByDashboardId(
			dashboardId: $dashboard->getId()
		);

		// REQ-SHARE-004: for a dashboard reached through a share the
		// authoritative permission level is the SHARE's, not the row's —
		// the row carries the OWNER's level, which would advertise edit
		// rights to a view-only recipient. Server-side enforcement already
		// goes through PermissionService::resolveAccessLevel(); this keeps
		// the envelope the frontend renders from agreeing with it.
		$sharedLevel = null;
		if ((string)$entry['source'] === Dashboard::SOURCE_SHARED) {
			$sharedLevel = $this->sharedPermissionLevel(dashboard: $dashboard, userId: $userId);
		}

		$layered = $this->withPersonalLayer(
			placements: $placements,
			dashboard: $dashboard,
			userId: $userId
		);

		if ($sharedLevel !== null) {
			return [
				'dashboard' => $dashboard,
				'placements' => $layered,
				'permissionLevel' => $sharedLevel,
			];
		}

		return $this->builders->dashResolver->buildResult(
			dashboard: $dashboard,
			placements: $layered
		);
	}//end getDashboardForUser()

	/**
	 * The first visible entry for the dashboard ID.
	 *
	 * @param array<int, array{dashboard: Dashboard, source: string}> $visible     The user's visible set.
	 * @param int                                                     $dashboardId The dashboard ID.
	 *
	 * @return array{dashboard: Dashboard, source: string}|null
	 */
	private function findVisibleEntryById(array $visible, int $dashboardId): ?array {
		foreach ($visible as $entry) {
			if ($entry['dashboard']->getId() === $dashboardId) {
				return $entry;
			}
		}

		return null;
	}//end findVisibleEntryById()

	/**
	 * The permission level a share grants the user on the dashboard, or NULL when none does.
	 *
	 * @param Dashboard $dashboard The shared dashboard.
	 * @param string    $userId    The recipient.
	 *
	 * @return string|null
	 */
	private function sharedPermissionLevel(Dashboard $dashboard, string $userId): ?string {
		$levels = $this->builders->dashResolver->findSharedLevels(
			userId: $userId,
			userGroupIds: $this->builders->adminTemplateService->getUserGroupIdsFor(
				userId: $userId
			)
		);

		if (isset($levels[$dashboard->getId()]) === true) {
			return $levels[$dashboard->getId()];
		}

		return null;
	}//end sharedPermissionLevel()

	/**
	 * Lay the caller's own arrangement over a dashboard somebody else owns.
	 *
	 * Their own dashboard is left alone: the arrangement there IS the
	 * dashboard, and a layer on top of it would be a second place the same
	 * thing is stored. A caller with no layer gets the placements back
	 * unchanged, so this costs one miss until somebody uses the feature.
	 *
	 * The layer service is required rather than nullable. It used to be
	 * nullable, and a null read exactly like a caller with no layer, so a
	 * wiring failure would have stored layers and applied none of them
	 * without a single test noticing.
	 *
	 * @param array $placements What the owner composed.
	 * @param Dashboard $dashboard The dashboard being read.
	 * @param string $userId The caller.
	 *
	 * @return array The placements the caller sees.
	 *
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	private function withPersonalLayer(
		array $placements,
		Dashboard $dashboard,
		string $userId,
	): array {
		if ((string)$dashboard->getUserId() === $userId) {
			return $placements;
		}

		return $this->structure->personalLayers->applyTo(
			placements: $placements,
			userId: $userId,
			dashboardId: (int)$dashboard->getId()
		);
	}//end withPersonalLayer()

	/**
	 * Get the effective dashboard for a user.
	 * Returns user's active dashboard or applicable admin template.
	 *
	 * This is `GET /api/dashboard`, the call the grid loads from, so it is
	 * the read the personal layer has to reach. The resolution chain below
	 * has eight exits, several of them inside DashboardResolver, so the
	 * layer goes on here, once, over whatever the chain settled on, rather
	 * than at eight places where the ninth would be forgotten.
	 *
	 * @param string $userId The user ID.
	 *
	 * @return array|null The effective dashboard data or null.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md
	 */
	public function getEffectiveDashboard(string $userId): ?array {
		$result = $this->resolveEffectiveDashboard(userId: $userId);
		if ($result === null || isset($result['placements']) === false) {
			return $result;
		}

		$result['placements'] = $this->withPersonalLayer(
			placements: $result['placements'],
			dashboard: $result['dashboard'],
			userId: $userId
		);

		return $result;
	}//end getEffectiveDashboard()

	/**
	 * The resolution chain behind {@see self::getEffectiveDashboard()}.
	 *
	 * @param string $userId The user ID.
	 *
	 * @return array|null The effective dashboard data or null.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	private function resolveEffectiveDashboard(string $userId): ?array {
		$result = $this->resolvePreferredEffectiveDashboard(userId: $userId);
		if ($result !== null) {
			return $result;
		}

		$result = $this->resolveOwnedEffectiveDashboard(userId: $userId);
		if ($result !== null) {
			return $result;
		}

		$result = $this->resolveRoleLayoutOrDefaultGroupDashboard(userId: $userId);
		if ($result !== null) {
			return $result;
		}

		return $this->resolveSharedOrTemplateDashboard(userId: $userId);
	}//end resolveEffectiveDashboard()

	/**
	 * Steps 0-1 of {@see self::resolveEffectiveDashboard()}: the pinned
	 * default, then the last-used preference.
	 *
	 * Both resolve against the user's full visible set. This honours a
	 * group/default (showcase) dashboard whose `user_id` is NULL — not
	 * just personal `is_active` rows — so the API-driven view matches the
	 * server-rendered landing URL, which resolveActiveDashboard() resolves
	 * the same way. A stale pref (UUID no longer visible) falls through to
	 * the legacy chain.
	 *
	 * @param string $userId The user ID.
	 *
	 * @return array|null The built result, or null when neither preference is visible.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	private function resolvePreferredEffectiveDashboard(string $userId): array|null {
		$defaultUuid = $this->getDefaultPreference(userId: $userId);
		$activeUuid = $this->getLastUsedPreference(userId: $userId);
		if ($defaultUuid === '' && $activeUuid === '') {
			return null;
		}

		$visible = $this->getVisibleToUser(userId: $userId);
		// Default pin wins over last-used — same precedence as
		// resolveActiveDashboard() steps 0 then 1.
		foreach ([$defaultUuid, $activeUuid] as $prefUuid) {
			$candidate = $this->findVisibleByUuid(visible: $visible, uuid: $prefUuid);
			if ($candidate !== null) {
				return $this->buildResultWithPlacements(dashboard: $candidate);
			}
		}

		return null;
	}//end resolvePreferredEffectiveDashboard()

	/**
	 * The auto-overwriting last-used dashboard preference (REQ-DASH-019).
	 *
	 * @param string $userId The user ID.
	 *
	 * @return string The stored UUID, or '' when unset.
	 */
	private function getLastUsedPreference(string $userId): string {
		return (string)$this->platform->config->getUserValue(
			userId: $userId,
			appName: Application::APP_ID,
			key: self::ACTIVE_DASHBOARD_UUID_PREF_KEY,
			default: ''
		);
	}//end getLastUsedPreference()

	/**
	 * The visible dashboard with this UUID; null for an empty UUID or no match.
	 *
	 * @param array<int, array{dashboard: Dashboard, source: string}> $visible The user's visible set.
	 * @param string                                                  $uuid    The preferred UUID.
	 *
	 * @return Dashboard|null The first match.
	 */
	private function findVisibleByUuid(array $visible, string $uuid): ?Dashboard {
		if ($uuid === '') {
			return null;
		}

		foreach ($visible as $entry) {
			$candidate = $entry['dashboard'];
			if ((string)$candidate->getUuid() === $uuid) {
				return $candidate;
			}
		}

		return null;
	}//end findVisibleByUuid()

	/**
	 * The resolver result for a dashboard with its placements loaded.
	 *
	 * @param Dashboard $dashboard The resolved dashboard.
	 *
	 * @return array The built result.
	 */
	private function buildResultWithPlacements(Dashboard $dashboard): array {
		$placements = $this->stores->placementMapper->findByDashboardId(
			dashboardId: $dashboard->getId()
		);
		return $this->builders->dashResolver->buildResult(
			dashboard: $dashboard,
			placements: $placements
		);
	}//end buildResultWithPlacements()

	/**
	 * The user's legacy `is_active` personal dashboard, else the first
	 * existing one, activated.
	 *
	 * @param string $userId The user ID.
	 *
	 * @return array|null The result, or null when the user owns none.
	 */
	private function resolveOwnedEffectiveDashboard(string $userId): ?array {
		$result = $this->builders->dashResolver->tryGetActiveDashboard(
			userId: $userId
		);
		if ($result !== null) {
			return $result;
		}

		return $this->builders->dashResolver->tryActivateExistingDashboard(
			userId: $userId
		);
	}//end resolveOwnedEffectiveDashboard()

	/**
	 * A dashboard shared with the user, else a new one from a template.
	 *
	 * REQ-SHARE-002: a dashboard someone deliberately shared with this
	 * user beats auto-provisioning a brand-new one from a template.
	 * Placed AFTER both owned-dashboard steps (same precedence as
	 * resolveActiveDashboard step 6b) and BEFORE the template step
	 * because tryCreateFromTemplate WRITES — it would otherwise mint an
	 * empty personal dashboard and permanently bury the share.
	 *
	 * @param string $userId The user ID.
	 *
	 * @return array|null The result.
	 */
	private function resolveSharedOrTemplateDashboard(string $userId): ?array {
		$result = $this->builders->dashResolver->tryGetSharedDashboard(
			userId: $userId,
			userGroupIds: $this->builders->adminTemplateService->getUserGroupIdsFor(
				userId: $userId
			)
		);
		if ($result !== null) {
			return $result;
		}

		return $this->tryCreateFromTemplate(userId: $userId);
	}//end resolveSharedOrTemplateDashboard()

	/**
	 * The user's role layout when their groups carry one, else the instance default.
	 *
	 * Kept out of getEffectiveDashboard() so that method takes this as one
	 * step: inline, the extra branches put it over phpmd's NPath threshold.
	 *
	 * @param string $userId The user to resolve for.
	 *
	 * @return array|null The built dashboard result, or null when neither exists.
	 *
	 * @spec openspec/specs/role-feature-permissions/spec.md#req-rfp-002-role-based-default-dashboard-layout
	 */
	private function resolveRoleLayoutOrDefaultGroupDashboard(string $userId): ?array {
		// REQ-TMPL-019: a member who owns nothing yet gets the template meant
		// for them, ahead of the dashboard everyone shares. Whoever owns a
		// personal dashboard left the chain two steps earlier. The page shell
		// takes the same rung at the same place (resolveTemplateOrGroupShared).
		$fromTemplate = $this->resolveTemplateRung(userId: $userId);
		if ($fromTemplate !== null) {
			return $this->describeTemplateRung(rung: $fromTemplate);
		}

		// 🔴 ROLE DEFAULTS MUST BEAT THE INSTANCE-WIDE DEFAULT DASHBOARD.
		// REQ-RFP-002 says a new user is seeded from their group's
		// RoleLayoutDefault rows. Since #361 seeds one `default`
		// group-shared dashboard on install, the default group step matched
		// for EVERY new user, so `tryCreateFromTemplate()` at the end of
		// getEffectiveDashboard() never ran and no role layout was ever
		// seeded. Measured on a fresh instance: a user in a group with layout
		// defaults resolved to the shared `default` dashboard and owned
		// nothing.
		//
		// Narrow on purpose: only an instance that configured role layout
		// defaults for one of this user's groups takes the new path, and
		// `tryCreateFromTemplate()` still answers null when personal
		// dashboards are switched off, which falls through to the old
		// behaviour.
		if ($this->limits->roleFeaturePerm?->hasRoleLayoutDefaultsFor(userId: $userId) === true) {
			$result = $this->tryCreateFromTemplate(userId: $userId);
			if ($result !== null) {
				return $result;
			}
		}

		return $this->resolveDefaultGroupDashboard(userId: $userId);
	}//end resolveRoleLayoutOrDefaultGroupDashboard()

	/**
	 * Resolve an instance-wide dashboard on the reserved `default` group.
	 *
	 * This rung exists because the two resolution chains disagreed, and the
	 * disagreement was silent. The page shell resolves through
	 * resolveActiveDashboard(), whose steps 2-5 land on a `group_shared` row —
	 * including the `default` sentinel that means "everyone" (REQ-DASH-012).
	 * getEffectiveDashboard() had no equivalent rung at all: with no preference
	 * set it went straight from the owned dashboards to shares and templates.
	 * So an instance whose only dashboard is the shipped `default`-group one
	 * rendered fine in the shell while `GET /api/dashboard` answered
	 * `{"error": "No dashboard available"}` for the same user, and the grid drew
	 * its empty state on top of a dashboard the sidebar was already listing.
	 *
	 * Only the DEFAULT_GROUP_ID sentinel is resolved here — rungs 3 and 5 of
	 * resolveGroupSharedDashboard(). A per-group default needs the caller's
	 * primary group, which this signature does not carry, and guessing one would
	 * make the two chains disagree in a new way.
	 *
	 * Extracted from getEffectiveDashboard() rather than written inline: with
	 * this block in place that method measured 104 lines against phpmd's
	 * ExcessiveMethodLength threshold of 100.
	 *
	 * @param string $userId The user to resolve for.
	 *
	 * @return array|null The built dashboard result, or null when none exists.
	 */
	private function resolveDefaultGroupDashboard(string $userId): ?array {
		$groupShared = $this->resolveGroupSharedDashboard(
			visible: $this->getVisibleToUser(userId: $userId),
			groupId: Dashboard::DEFAULT_GROUP_ID
		);
		if ($groupShared === null) {
			return null;
		}

		return $this->builders->dashResolver->buildResult(
			dashboard: $groupShared['dashboard'],
			placements: $this->stores->placementMapper->findByDashboardId(
				dashboardId: $groupShared['dashboard']->getId()
			)
		);

	}//end resolveDefaultGroupDashboard()

	/**
	 * Create a new dashboard for a user.
	 *
	 * Accepts the optional hierarchy fields introduced by REQ-DASH-023..029:
	 * `parentUuid`, `slug`, `sortOrder`. The factory generates a slug
	 * from the name when none is supplied; the tree service validates
	 * the parent (existence, cycle, depth) and the slug uniqueness
	 * (per-parent) before the row is persisted.
	 *
	 * @param string $userId The user ID.
	 * @param string $name The dashboard name.
	 * @param array $options Optional fields: `description` (null);
	 *                       `icon`, an opaque icon identifier (registry key
	 *                       or URL, see the `dashboard-icons` capability;
	 *                       null or '' ⇒ default glyph); `parentUuid` (null
	 *                       ⇒ root, REQ-DASH-023); `slug` (null ⇒ derived
	 *                       from the name, REQ-DASH-024); `sortOrder` (0,
	 *                       REQ-DASH-029); `seedDefaults` (false), whether
	 *                       to seed the default widget bundle (Conduction +
	 *                       Sendent + Nextcloud tiles + a Files widget).
	 *                       The controller path (`POST /api/dashboard`)
	 *                       opts in; the bootstrap path keeps it off to
	 *                       avoid double-seeding when role defaults apply.
	 *
	 * @return Dashboard The created dashboard.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function createDashboard(string $userId, string $name, array $options=[]): Dashboard {
		$options += self::CREATE_DEFAULTS;
		$this->assertMayCreate(userId: $userId, parentUuid: $options['parentUuid']);

		$dashboard = $this->builders->dashboardFactory->create(
			userId: $userId,
			name: $name,
			options: self::factoryOptions(options: $options)
		);
		$this->applyNewIcon(dashboard: $dashboard, icon: $options['icon']);
		$this->assertNewSlugUnique(dashboard: $dashboard, parentUuid: $options['parentUuid']);

		$this->stores->dashboardMapper->deactivateAllForUser(userId: $userId);

		$persisted = $this->stores->dashboardMapper->insert(entity: $dashboard);
		$this->completeNewDashboard(persisted: $persisted, options: $options);

		return $persisted;
	}//end createDashboard()

	/**
	 * The checks every dashboard creation passes first, before any DB
	 * write or entity is built.
	 *
	 * Task-7 of dashboard-public-share — public-share bearer cannot mutate.
	 * Dashboard-quota-limits REQ-QUOTA-002: enforce the per-user
	 * dashboard quota at the single creation choke point so every
	 * user-initiated surface (REST create, CLI, template
	 * instantiation) is bound. Admin provisioning paths wrap their
	 * calls in QuotaService::runProvisioning() to bypass this
	 * (REQ-QUOTA-004). REQ-DASH-023, REQ-DASH-028: parent existence +
	 * cycle + depth checks BEFORE the entity is built so the request is
	 * rejected without a partial row in memory.
	 *
	 * @param string      $userId     The user ID.
	 * @param string|null $parentUuid The requested parent, or null for a root.
	 *
	 * @return void
	 */
	private function assertMayCreate(string $userId, ?string $parentUuid): void {
		$this->limits->publicShareContext?->requireMutable();
		$this->limits->quotaService?->assertCanCreateDashboard(userId: $userId);
		$this->structure->treeService->validateParent(
			movingUuid: null,
			newParentUuid: $parentUuid
		);
	}//end assertMayCreate()

	/**
	 * The options the dashboard factory takes from the create options.
	 *
	 * @param array<string, mixed> $options The create options with defaults.
	 *
	 * @return array<string, mixed> The factory options.
	 */
	private static function factoryOptions(array $options): array {
		return [
			'description' => $options['description'],
			'parentUuid'  => $options['parentUuid'],
			'slug'        => $options['slug'],
			'sortOrder'   => $options['sortOrder'],
		];
	}//end factoryOptions()

	/**
	 * Set a new dashboard's icon when one was given.
	 *
	 * Icon is an opaque string (registry key or URL) — see the
	 * `dashboard-icons` capability. NULL/empty means "use the
	 * frontend default glyph".
	 *
	 * @param Dashboard   $dashboard The new dashboard.
	 * @param string|null $icon      The requested icon.
	 *
	 * @return void
	 */
	private function applyNewIcon(Dashboard $dashboard, ?string $icon): void {
		if ($icon !== null && $icon !== '') {
			// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$dashboard->setIcon($icon);
		}
	}//end applyNewIcon()

	/**
	 * REQ-DASH-024: slug uniqueness within parent scope. The
	 * factory may have emitted NULL when the name yields no legal
	 * characters and the caller did not supply an explicit slug —
	 * skip the unique check in that case (NULL slugs are simply
	 * unaddressable by path).
	 *
	 * @param Dashboard   $dashboard  The new dashboard.
	 * @param string|null $parentUuid The parent, or null for a root.
	 *
	 * @return void
	 */
	private function assertNewSlugUnique(Dashboard $dashboard, ?string $parentUuid): void {
		$resolvedSlug = $dashboard->getSlug();
		if ($resolvedSlug !== null && $resolvedSlug !== '') {
			$this->structure->treeService->validateSlugUnique(
				parentUuid: $parentUuid,
				slug: $resolvedSlug
			);
		}
	}//end assertNewSlugUnique()

	/**
	 * Seed a stored dashboard's primary translation and, when asked, its
	 * default widgets.
	 *
	 * User-initiated dashboards (the "+" affordance in the sidebar)
	 * arrive here with $seedDefaults=true and are bootstrapped with
	 * the standard tile + files bundle. The bootstrap path
	 * (tryCreateFromTemplate) keeps $seedDefaults=false because it
	 * runs its own role-default-driven seed afterwards and would
	 * otherwise double-seed.
	 *
	 * @param Dashboard            $persisted The stored dashboard.
	 * @param array<string, mixed> $options   The create options with defaults.
	 *
	 * @return void
	 */
	private function completeNewDashboard(Dashboard $persisted, array $options): void {
		$this->seedPrimaryTranslation(dashboard: $persisted);

		if ($options['seedDefaults'] === true) {
			$this->seedDefaultWidgets(dashboardId: $persisted->getId());
		}
	}//end completeNewDashboard()

	/**
	 * REQ-DASH-038, REQ-DASH-044: every newly created dashboard gets
	 * a primary translation row in the owner's Nextcloud locale (or
	 * the default fallback when no preference is set). The seed is
	 * best-effort — failure here does not abort dashboard creation,
	 * because the legacy materialiser keeps reads working until the
	 * seed retries via a subsequent translation API call.
	 *
	 * @param Dashboard $dashboard The stored dashboard.
	 *
	 * @return void
	 */
	private function seedPrimaryTranslation(Dashboard $dashboard): void {
		if ($this->structure->translationService === null) {
			return;
		}

		try {
			$this->structure->translationService->seedPrimaryFor(
				dashboard: $dashboard
			);
		} catch (Throwable $t) {
			$this->outlets->logger->warning(
				message: 'launchpad: failed to seed primary translation: {message}',
				context: ['message' => $t->getMessage()]
			);
		}
	}//end seedPrimaryTranslation()

	/**
	 * Update a dashboard.
	 *
	 * @param int $dashboardId The dashboard ID.
	 * @param string $userId The user ID.
	 * @param array $data The data to update.
	 *
	 * @return Dashboard The updated dashboard.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function updateDashboard(
		int $dashboardId,
		string $userId,
		array $data,
	): Dashboard {
		// Task-7 of dashboard-public-share — public-share bearer cannot mutate.
		$this->limits->publicShareContext?->requireMutable();
		$dashboard = $this->stores->dashboardMapper->find(id: $dashboardId);

		if ($dashboard->getUserId() !== $userId) {
			throw new Exception(message: 'Access denied');
		}

		$this->applyDashboardUpdates(
			dashboard: $dashboard,
			data: $data
		);

		$saved = $this->stores->dashboardMapper->update(entity: $dashboard);
		// Shared dashboards announce a save, at most once a day (#713).
		$this->outlets->activity?->updated(dashboard: $saved, actor: $userId);

		return $saved;
	}//end updateDashboard()

	/**
	 * Delete a dashboard, with cascade-delete guard for the tree.
	 *
	 * REQ-DASH-030: when the dashboard has children the caller MUST pass
	 * `$cascade = true`; otherwise `Exception` is raised with the
	 * sentinel message `Dashboard has children` so the controller can
	 * map it to HTTP 409 with the child count. With `$cascade = true`
	 * the entire subtree (including placements) is removed via
	 * {@see DashboardTreeService::deleteSubtree()}.
	 *
	 * @param int $dashboardId The dashboard ID.
	 * @param string $userId The user ID.
	 * @param bool $cascade When true, remove the entire subtree.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function deleteDashboard(
		int $dashboardId,
		string $userId,
		bool $cascade = false,
	): void {
		// Task-7 of dashboard-public-share — public-share bearer cannot mutate.
		$this->limits->publicShareContext?->requireMutable();
		$dashboard = $this->stores->dashboardMapper->find(id: $dashboardId);

		if ($dashboard->getUserId() !== $userId) {
			throw new Exception(message: 'Access denied');
		}

		$uuid = (string)$dashboard->getUuid();
		$this->assertNoChildrenUnlessCascading(uuid: $uuid, cascade: $cascade);

		if ($cascade === true && $uuid !== '') {
			$this->deleteSubtreeWithChildRows(dashboard: $dashboard, uuid: $uuid);
			return;
		}

		$this->deleteLeafWithChildRows(
			dashboard: $dashboard,
			dashboardId: $dashboardId,
			uuid: $uuid,
			fallbackOwnerId: $userId
		);
	}//end deleteDashboard()

	/**
	 * Refuse a non-cascading delete of a dashboard that still has
	 * children.
	 *
	 * A cascading delete is allowed to proceed regardless, and a
	 * dashboard with no UUID cannot have children to begin with.
	 *
	 * @param string $uuid The dashboard's UUID.
	 * @param bool $cascade Whether the caller asked to cascade.
	 *
	 * @return void
	 *
	 * @throws DashboardHasChildrenException When children exist and the
	 *                                       caller did not cascade.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	private function assertNoChildrenUnlessCascading(string $uuid, bool $cascade): void {
		if ($uuid === '' || $cascade === true) {
			return;
		}

		$childCount = $this->stores->dashboardMapper->countChildrenByParent(
			parentUuid: $uuid
		);
		if ($childCount > 0) {
			throw new DashboardHasChildrenException(
				childCount: $childCount
			);
		}
	}//end assertNoChildrenUnlessCascading()

	/**
	 * Delete a single (childless, or force-deleted) dashboard together
	 * with the child rows this service owns.
	 *
	 * Translation variants are cleared first so they never outlive the
	 * parent (REQ-DASH-044); placements and the editing lock follow
	 * (REQ-LOCK-008); the row itself goes last, then the deletion event
	 * is dispatched for the cascade listeners.
	 *
	 * @param Dashboard $dashboard The dashboard to delete.
	 * @param int $dashboardId Its numeric ID.
	 * @param string $uuid Its UUID (may be empty).
	 * @param string $fallbackOwnerId Owner to attribute when the row
	 *                                carried none.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	private function deleteLeafWithChildRows(
		Dashboard $dashboard,
		int $dashboardId,
		string $uuid,
		string $fallbackOwnerId,
	): void {
		// REQ-DASH-044: cascade-delete translation variants for the
		// dashboard about to disappear so they don't outlive the parent.
		if ($uuid !== '' && $this->structure->translationService !== null) {
			$this->structure->translationService->deleteAllForDashboard(
				dashboardUuid: $uuid
			);
		}

		$this->stores->placementMapper->deleteByDashboardId(
			dashboardId: $dashboardId
		);

		// Cascade-clear the editing lock so a deleted dashboard never
		// leaves an orphaned lock row behind (REQ-LOCK-008).
		if ($this->stores->lockMapper !== null && $uuid !== '') {
			$this->stores->lockMapper->deleteByDashboardUuid(
				dashboardUuid: $uuid
			);
		}

		$this->stores->dashboardMapper->delete(entity: $dashboard);

		$this->dispatchDashboardDeleted(
			dashboard: $dashboard,
			uuid: $uuid,
			fallbackOwnerId: $fallbackOwnerId
		);
	}//end deleteLeafWithChildRows()

	/**
	 * Delete a dashboard's whole subtree, clearing the child rows the
	 * tree walker does not own.
	 *
	 * Translation rows are scoped by uuid and are cleared BEFORE the
	 * walker drops the parent rows, so a variant is never orphaned on a
	 * vanished dashboard — for the root AND for every descendant
	 * (REQ-DASH-044). The editing lock is cleared for the root only
	 * (REQ-LOCK-008): the spec scopes locks to the dashboard the user is
	 * editing, so a descendant lock simply leaks a row that the
	 * next-acquire inline cleanup reaps.
	 *
	 * @param Dashboard $dashboard The subtree root to delete.
	 * @param string $uuid The root's UUID (already non-empty).
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	private function deleteSubtreeWithChildRows(Dashboard $dashboard, string $uuid): void {
		if ($this->structure->translationService !== null) {
			$descendants = $this->stores->dashboardMapper->findDescendants(
				ancestorUuid: $uuid
			);
			foreach ($descendants as $descendant) {
				$descendantUuid = (string)$descendant->getUuid();
				if ($descendantUuid !== '') {
					$this->structure->translationService->deleteAllForDashboard(
						dashboardUuid: $descendantUuid
					);
				}
			}

			$this->structure->translationService->deleteAllForDashboard(
				dashboardUuid: $uuid
			);
		}

		if ($this->stores->lockMapper !== null) {
			$this->stores->lockMapper->deleteByDashboardUuid(
				dashboardUuid: $uuid
			);
		}

		$this->structure->treeService->deleteSubtree(dashboard: $dashboard);
	}//end deleteSubtreeWithChildRows()

	/**
	 * Dispatch {@see DashboardDeletedEvent} for a dashboard that has just
	 * been deleted.
	 *
	 * SB1 fix: the cascade listeners (comments, reactions, versions,
	 * metadata_values, public_shares, view_analytics) clean up their child
	 * rows off this event (REQ-CSC-001). No-op when no dispatcher is wired
	 * or the dashboard carried no UUID.
	 *
	 * @param Dashboard $dashboard The dashboard that was deleted.
	 * @param string $uuid Its UUID.
	 * @param string $fallbackOwnerId Owner to attribute when the row
	 *                                carried none.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	private function dispatchDashboardDeleted(
		Dashboard $dashboard,
		string $uuid,
		string $fallbackOwnerId,
	): void {
		if ($this->outlets->eventDispatcher === null || $uuid === '') {
			return;
		}

		$ownerId = $dashboard->getUserId();
		$resolvedOwnerId = $fallbackOwnerId;
		if ($ownerId !== null && $ownerId !== '') {
			$resolvedOwnerId = $ownerId;
		}

		$this->outlets->eventDispatcher->dispatchTyped(
			new DashboardDeletedEvent(
				dashboardUuid: $uuid,
				ownerUserId:   $resolvedOwnerId,
				type:          (string)($dashboard->getType() ?? Dashboard::TYPE_USER),
				deletedAt:     new DateTimeImmutable(),
				dashboardId:   $dashboard->getId()
			)
		);
	}//end dispatchDashboardDeleted()

	/**
	 * Activate a dashboard for a user.
	 *
	 * @param int $dashboardId The dashboard ID.
	 * @param string $userId The user ID.
	 *
	 * @return Dashboard The activated dashboard.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function activateDashboard(
		int $dashboardId,
		string $userId,
	): Dashboard {
		$dashboard = $this->stores->dashboardMapper->find(id: $dashboardId);

		if ($dashboard->getUserId() !== $userId) {
			throw new Exception(message: 'Access denied');
		}

		$this->stores->dashboardMapper->setActive(
			$dashboardId,
			userId: $userId
		);
		// Cast to int — the entity column is SMALLINT.
		$dashboard->setIsActive(1);

		return $dashboard;
	}//end activateDashboard()

	/**
	 * Look up a dashboard by its UUID.
	 *
	 * Thin wrapper so controllers can resolve a UUID to a Dashboard entity
	 * for permission checks without importing DashboardMapper directly.
	 *
	 * @param string $uuid The dashboard UUID.
	 *
	 * @return Dashboard The dashboard entity.
	 *
	 * @throws DoesNotExistException When no dashboard with that UUID exists.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function findByUuid(string $uuid): Dashboard {
		return $this->stores->dashboardMapper->findByUuid(uuid: $uuid);
	}//end findByUuid()

	/**
	 * List the group-shared dashboards in a single group.
	 *
	 * Any logged-in user may list — REQ-DASH-014.
	 *
	 * @param string $groupId The group ID.
	 *
	 * @return Dashboard[] The group-shared dashboards in the group.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function listGroupDashboards(string $groupId): array {
		return $this->stores->dashboardMapper->findByGroup(groupId: $groupId);
	}//end listGroupDashboards()

	/**
	 * Find a single group-shared dashboard, validating the path-group.
	 *
	 * Returns the dashboard only when its `groupId` matches the path
	 * parameter — otherwise the caller treats it as a 404.
	 * REQ-DASH-014 (group-id mismatch returns 404).
	 *
	 * @param string $groupId The group ID from the URL.
	 * @param string $uuid The dashboard UUID from the URL.
	 *
	 * @return Dashboard The dashboard.
	 *
	 * @throws DoesNotExistException When no dashboard with that UUID
	 *                               exists, or when its `groupId` does
	 *                               not match the path parameter, or
	 *                               when its type is not group_shared.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function findGroupDashboard(
		string $groupId,
		string $uuid,
	): Dashboard {
		$dashboard = $this->stores->dashboardMapper->findByUuid(uuid: $uuid);

		if ($dashboard->getType() !== Dashboard::TYPE_GROUP_SHARED) {
			throw new DoesNotExistException(
				msg: self::ERR_GROUP_MISMATCH
			);
		}

		if ($dashboard->getGroupId() !== $groupId) {
			throw new DoesNotExistException(
				msg: self::ERR_GROUP_MISMATCH
			);
		}

		return $dashboard;
	}//end findGroupDashboard()

	/**
	 * Create a new group-shared dashboard.
	 *
	 * Admin-only — caller MUST have validated the actor with
	 * {@see DashboardService::isAdmin()}. The route attribute alone is
	 * not enough (per Hydra semantic-auth gate).
	 *
	 * @param string $actorUserId The acting user ID (for the admin
	 *                            check).
	 * @param string $groupId The group ID.
	 * @param array $fields The dashboard `name`, and optional `description`
	 *                      (null) and `gridColumns` (12).
	 *
	 * @return Dashboard The created group-shared dashboard.
	 *
	 * @throws Exception When the actor is not an administrator.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function createGroupShared(string $actorUserId, string $groupId, array $fields): Dashboard {
		$fields += ['description' => null, 'gridColumns' => 12];
		if ($this->isAdmin(userId: $actorUserId) === false) {
			throw new Exception(message: self::ERR_FORBIDDEN_NOT_ADMIN);
		}

		$dashboard = $this->builders->dashboardFactory->create(
			userId: null,
			name: $fields['name'],
			options: [
				'description' => $fields['description'],
				'type'        => Dashboard::TYPE_GROUP_SHARED,
				'groupId'     => $groupId,
				'gridColumns' => $fields['gridColumns'],
			]
		);
		$dashboard->setPermissionLevel(Dashboard::PERMISSION_VIEW_ONLY);
		// REQ-DASH-016: new group-shared rows always start non-default.
		// Promotion is only possible via the dedicated
		// POST /api/dashboards/group/{groupId}/default endpoint.
		$dashboard->setIsDefault(0);

		return $this->stores->dashboardMapper->insert(entity: $dashboard);
	}//end createGroupShared()

	/**
	 * Update a group-shared dashboard.
	 *
	 * Admin-only. The path's `groupId` must match the record's `groupId`
	 * — otherwise `DoesNotExistException` (treated as 404 by caller).
	 * The `userId` field is intentionally never patched (REQ-DASH-014).
	 *
	 * @param string $actorUserId The acting user ID (for the admin check).
	 * @param string $groupId The group ID from the URL.
	 * @param string $uuid The dashboard UUID from the URL.
	 * @param array $patch The patch data (name, description,
	 *                     gridColumns, placements supported).
	 *
	 * @return Dashboard The updated dashboard.
	 *
	 * @throws Exception When the actor is not an administrator.
	 * @throws DoesNotExistException On 404.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function updateGroupShared(
		string $actorUserId,
		string $groupId,
		string $uuid,
		array $patch,
	): Dashboard {
		if ($this->isAdmin(userId: $actorUserId) === false) {
			throw new Exception(message: self::ERR_FORBIDDEN_NOT_ADMIN);
		}

		$dashboard = $this->findGroupDashboard(
			groupId: $groupId,
			uuid: $uuid
		);

		// REQ-DASH-017: PUT MUST NOT mutate `isDefault` regardless of
		// payload contents. Drop the field defensively before applying
		// updates — even though `applyDashboardUpdates` already ignores
		// unknown keys, we strip it explicitly so the contract is
		// visible at the service boundary.
		unset($patch['isDefault']);

		$this->applyDashboardUpdates(
			dashboard: $dashboard,
			data: $patch
		);

		$saved = $this->stores->dashboardMapper->update(entity: $dashboard);
		// The group hears about a save, at most once a day (#713).
		$this->outlets->activity?->updated(dashboard: $saved, actor: $actorUserId);

		return $saved;
	}//end updateGroupShared()

	/**
	 * Delete a group-shared dashboard.
	 *
	 * Admin-only. The last-in-group guard returns an `Exception` (the
	 * controller maps to HTTP 400) when removing the row would leave
	 * the group with zero group-shared dashboards. The `default` group
	 * is exempt from the guard. REQ-DASH-014.
	 *
	 * @param string $actorUserId The acting user ID.
	 * @param string $groupId The group ID from the URL.
	 * @param string $uuid The dashboard UUID from the URL.
	 *
	 * @return void
	 *
	 * @throws Exception When the actor is not admin, or the
	 *                   last-in-group guard rejects the delete.
	 * @throws DoesNotExistException On 404.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function deleteGroupShared(
		string $actorUserId,
		string $groupId,
		string $uuid,
	): void {
		$this->assertAdmin(userId: $actorUserId);

		$dashboard = $this->findGroupDashboard(
			groupId: $groupId,
			uuid: $uuid
		);

		$this->assertNotLastInGroup(groupId: $groupId);
		$this->deleteWithPlacements(dashboard: $dashboard);
		$this->dispatchGroupSharedDeleted(dashboard: $dashboard, actorUserId: $actorUserId);
	}//end deleteGroupShared()

	/**
	 * Refuse an admin-only mutation for a non-admin actor.
	 *
	 * @param string $userId The acting user ID.
	 *
	 * @return void
	 *
	 * @throws Exception When the actor is not an admin.
	 */
	private function assertAdmin(string $userId): void {
		if ($this->isAdmin(userId: $userId) === false) {
			throw new Exception(message: self::ERR_FORBIDDEN_NOT_ADMIN);
		}
	}//end assertAdmin()

	/**
	 * Refuse removing the only group-shared dashboard of a group; the
	 * `default` group is exempt (REQ-DASH-014).
	 *
	 * @param string $groupId The group ID.
	 *
	 * @return void
	 *
	 * @throws Exception When the group has one dashboard or fewer.
	 */
	private function assertNotLastInGroup(string $groupId): void {
		if ($groupId === Dashboard::DEFAULT_GROUP_ID) {
			return;
		}

		$count = $this->stores->dashboardMapper->countByGroup(
			groupId: $groupId
		);
		if ($count <= 1) {
			throw new Exception(message: self::ERR_LAST_IN_GROUP);
		}
	}//end assertNotLastInGroup()

	/**
	 * Delete a dashboard's placements, then the dashboard itself.
	 *
	 * @param Dashboard $dashboard The dashboard to delete.
	 *
	 * @return void
	 */
	private function deleteWithPlacements(Dashboard $dashboard): void {
		$this->stores->placementMapper->deleteByDashboardId(
			dashboardId: $dashboard->getId()
		);
		$this->stores->dashboardMapper->delete(entity: $dashboard);
	}//end deleteWithPlacements()

	/**
	 * SB1 fix: dispatch DashboardDeletedEvent for cascade cleanup
	 * (REQ-CSC-001). Actor is the admin who performed the delete.
	 *
	 * @param Dashboard $dashboard   The deleted group-shared dashboard.
	 * @param string    $actorUserId The admin who deleted it.
	 *
	 * @return void
	 */
	private function dispatchGroupSharedDeleted(Dashboard $dashboard, string $actorUserId): void {
		$deletedUuid = (string)$dashboard->getUuid();
		if ($this->outlets->eventDispatcher !== null && $deletedUuid !== '') {
			$this->outlets->eventDispatcher->dispatchTyped(
				new DashboardDeletedEvent(
					dashboardUuid: $deletedUuid,
					ownerUserId:   $actorUserId,
					type:          (string)($dashboard->getType() ?? Dashboard::TYPE_GROUP_SHARED),
					deletedAt:     new DateTimeImmutable(),
					dashboardId:   $dashboard->getId()
				)
			);
		}
	}//end dispatchGroupSharedDeleted()

	/**
	 * Promote a single group-shared dashboard to the group's default.
	 *
	 * Admin-only. Wraps both mapper writes — clear the existing default
	 * on every other dashboard in the group, then set the target to
	 * `is_default = 1` — in a single DB transaction so concurrent
	 * promotions cannot leave two rows with `is_default = 1` in the
	 * same group. REQ-DASH-015.
	 *
	 * Order of operations matters: we issue the SET first; if the
	 * target uuid does not belong to the group the row count is `0`
	 * and we throw {@see DoesNotExistException} (mapped to HTTP 404 by
	 * the controller). The transaction is then rolled back, leaving
	 * the previous default untouched.
	 *
	 * @param string $actorUserId The acting user ID (for the admin
	 *                            check).
	 * @param string $groupId The group ID from the URL.
	 * @param string $uuid The dashboard UUID from the URL.
	 *
	 * @return void
	 *
	 * @throws Exception When the actor is not an admin.
	 * @throws DoesNotExistException When the uuid does not belong to
	 *                               the given group.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function setGroupDefault(
		string $actorUserId,
		string $groupId,
		string $uuid,
	): void {
		if ($this->isAdmin(userId: $actorUserId) === false) {
			throw new Exception(message: self::ERR_FORBIDDEN_NOT_ADMIN);
		}

		$this->platform->db->beginTransaction();
		try {
			$affected = $this->stores->dashboardMapper->setGroupDefaultUuid(
				groupId: $groupId,
				uuid: $uuid
			);

			if ($affected === 0) {
				// The target uuid does not belong to this group — roll
				// back so the existing default in the group is
				// preserved. REQ-DASH-015 scenario "Default cannot be
				// set across groups".
				$this->platform->db->rollBack();
				throw new DoesNotExistException(
					msg: self::ERR_DEFAULT_TARGET_NOT_IN_GROUP
				);
			}

			$this->stores->dashboardMapper->clearGroupDefaults(
				groupId: $groupId,
				exceptUuid: $uuid
			);

			$this->platform->db->commit();
		} catch (DoesNotExistException $e) {
			// Already rolled back above — re-throw for the controller.
			throw $e;
		} catch (Throwable $t) {
			$this->platform->db->rollBack();
			throw $t;
		}//end try
	}//end setGroupDefault()

	/**
	 * Get all dashboards visible to a user, source-tagged.
	 *
	 * Resolves the user's full group memberships through
	 * {@see AdminTemplateService::getUserGroupIdsFor()} (REQ-TMPL-013 —
	 * single source of truth for `IGroupManager::getUserGroupIds`) and
	 * wires the result into the mapper's union query. Returns the
	 * deduplicated list with `source` set per row. REQ-DASH-013.
	 *
	 * @param string $userId The user ID.
	 *
	 * @return array<int, array{dashboard: Dashboard, source: string}>
	 *                                                                 List of {dashboard, source} pairs.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function getVisibleToUser(string $userId): array {
		$userGroupIds = $this->builders->adminTemplateService->getUserGroupIdsFor(
			userId: $userId
		);

		$entries = $this->stores->dashboardMapper->findVisibleToUser(
			userId: $userId,
			userGroupIds: $userGroupIds
		);

		// REQ-DASH-031..035: apply publication-state filter and lazy
		// materialisation in one pass — drafts hidden from non-owners,
		// scheduled rows past `publishAt` surfaced as published without
		// a DB write, future scheduled rows hidden from non-owners.
		// Defensive cast: legacy test doubles may stub isAdmin to null.
		$isAdmin = (bool)$this->safeIsAdmin(userId: $userId);
		$filtered = $this->filterByPublicationState(
			entries: $entries,
			actorUserId: $userId,
			actorIsAdmin: $isAdmin
		);

		// REQ-SHARE-002: dashboards reached through an explicit share row
		// are part of "visible to me" too. The mapper's union only covers
		// ownership, group_shared membership and the `default` sentinel, so
		// without this a share is invisible: the recipient never sees the
		// dashboard in the switcher and the resolver can never land on it.
		//
		// Appended AFTER the publication filter, deliberately. Every
		// dashboard DashboardFactory creates starts as `draft`
		// (REQ-DASH-031), and the filter hides drafts from non-owners — so
		// running shares through it would delete every share again, for the
		// ordinary case, and leave the feature exactly as dead as before.
		// A share row is the owner's explicit, named grant to this specific
		// user; it is the same class of entitlement that already exempts
		// the owner and admins from the hide, and it is what
		// PermissionService::resolveAccessLevel() already honours. Users
		// WITHOUT a share still cannot see a draft.
		//
		// Deduped by UUID against the filtered set so an owned or
		// group-reached row always keeps its stronger source tag.
		$seenUuids = [];
		foreach ($filtered as $entry) {
			$seenUuids[(string)$entry['dashboard']->getUuid()] = true;
		}

		$sharedEntries = $this->builders->dashResolver->findSharedDashboards(
			userId: $userId,
			userGroupIds: $userGroupIds
		);
		foreach ($sharedEntries as $sharedEntry) {
			$uuid = (string)$sharedEntry['dashboard']->getUuid();
			if (isset($seenUuids[$uuid]) === true) {
				continue;
			}

			// A due scheduled row still materialises in-memory so the
			// recipient sees the same `published` state everyone else does.
			$this->materialiseDueSchedule(dashboard: $sharedEntry['dashboard']);

			$seenUuids[$uuid] = true;
			$filtered[] = $sharedEntry;
		}

		return $filtered;
	}//end getVisibleToUser()

	/**
	 * Surface a due `scheduled` dashboard as `published`, in memory only.
	 *
	 * Extracted from {@see self::filterByPublicationState()} so the
	 * share-reached path (which deliberately bypasses the visibility hide,
	 * see `getVisibleToUser`) still reports the same publication state as
	 * every other viewer. No DB write — REQ-DASH-034.
	 *
	 * @param Dashboard $dashboard The dashboard to materialise.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	private function materialiseDueSchedule(Dashboard $dashboard): void {
		if ($dashboard->getPublicationStatus() !== Dashboard::STATUS_SCHEDULED) {
			return;
		}

		if ($this->isDue(dashboard: $dashboard, now: new DateTime()) === true) {
			$dashboard->setPublicationStatus(Dashboard::STATUS_PUBLISHED);
		}
	}//end materialiseDueSchedule()

	/**
	 * Whether a dashboard's `publishAt` has passed. A missing or
	 * malformed timestamp is never due (the row stays scheduled).
	 *
	 * @param Dashboard $dashboard The dashboard.
	 * @param DateTime  $now       The moment to compare against.
	 *
	 * @return bool True when the scheduled publication is due.
	 */
	private function isDue(Dashboard $dashboard, DateTime $now): bool {
		$publishAt = $dashboard->getPublishAt();
		if ($publishAt === null || $publishAt === '') {
			return false;
		}

		try {
			return new DateTime($publishAt) <= $now;
		} catch (Exception) {
			// Malformed timestamp — leave the row as scheduled.
			return false;
		}
	}//end isDue()

	/**
	 * Defensive admin check for the visibility filter.
	 *
	 * Wraps {@see self::isAdmin()} in a try/catch so legacy test doubles
	 * that stub the underlying `IGroupManager::isAdmin` to return `null`
	 * or throw on unknown users degrade gracefully to "not admin"
	 * instead of taking down the whole `getVisibleToUser` call.
	 *
	 * @param string $userId The user ID.
	 *
	 * @return bool Whether the user is a Nextcloud admin (false on any
	 *              defect in the underlying group manager).
	 */
	private function safeIsAdmin(string $userId): bool {
		try {
			return (bool)$this->platform->groupManager->isAdmin(userId: $userId);
		} catch (Throwable) {
			return false;
		}
	}//end safeIsAdmin()

	/**
	 * Apply publication-state filtering and lazy materialisation to a
	 * visible-to-user result set. REQ-DASH-031..035.
	 *
	 * Rules:
	 *  - `published` rows pass through unchanged.
	 *  - `scheduled` rows whose `publishAt <= now()` are surfaced as
	 *    `published` (entity mutated in-memory only — no DB write).
	 *  - `scheduled` rows whose `publishAt > now()` and `draft` rows are
	 *    hidden from non-owner non-admin viewers; owners and admins
	 *    keep seeing them in their unmaterialised form.
	 *
	 * @param array<int, array{dashboard: Dashboard, source: string}> $entries The mapper result set.
	 * @param string $actorUserId The acting user ID.
	 * @param bool $actorIsAdmin Whether the actor is a Nextcloud admin.
	 *
	 * @return array<int, array{dashboard: Dashboard, source: string}>
	 *                                                                 The filtered, lazily-materialised result set.
	 */
	private function filterByPublicationState(
		array $entries,
		string $actorUserId,
		bool $actorIsAdmin,
	): array {
		$now = new DateTime();
		$filtered = [];
		foreach ($entries as $entry) {
			$published = $this->effectivePublicationStatus(dashboard: $entry['dashboard'], now: $now) === Dashboard::STATUS_PUBLISHED;
			// A draft or (still) scheduled dashboard is hidden from
			// non-owner non-admin viewers.
			if ($published === true || $this->isOwnedBy(dashboard: $entry['dashboard'], userId: $actorUserId) === true || $actorIsAdmin === true) {
				$filtered[] = $entry;
			}
		}

		return $filtered;
	}//end filterByPublicationState()

	/**
	 * Whether a dashboard has an owner and it is the given user.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 * @param string    $userId    The viewing user.
	 *
	 * @return bool True for the owner.
	 */
	private function isOwnedBy(Dashboard $dashboard, string $userId): bool {
		$ownerId = $dashboard->getUserId();

		return $ownerId !== null && $ownerId === $userId;
	}//end isOwnedBy()

	/**
	 * The publication state a dashboard has at read time, applied to the
	 * entity in memory only: an empty legacy status reads as published
	 * (REQ-DASH-035), a scheduled row past `publishAt` as published
	 * (REQ-DASH-034), and a published row past `unpublishAt` as a draft
	 * (REQ-SCHEDUI-002).
	 *
	 * @param Dashboard $dashboard The dashboard being read.
	 * @param DateTime $now The moment of the read.
	 *
	 * @return string The effective status.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	private function effectivePublicationStatus(Dashboard $dashboard, DateTime $now): string {
		$status = $this->materialisedStatus(dashboard: $dashboard, now: $now);

		// REQ-SCHEDUI-002 (sharing-dashboard-schedule-screen): a published
		// dashboard whose take-down time has passed reads as a draft, so
		// only its owner and administrators keep seeing it.
		if ($status === Dashboard::STATUS_PUBLISHED && $this->isTakenDown(dashboard: $dashboard, now: $now) === true) {
			$dashboard->setPublicationStatus(Dashboard::STATUS_DRAFT);
			return Dashboard::STATUS_DRAFT;
		}

		return $status;
	}//end effectivePublicationStatus()

	/**
	 * The stored publication status, with legacy empty rows read as
	 * published and due scheduled rows published in place.
	 *
	 * @param Dashboard $dashboard The dashboard (mutated when due).
	 * @param DateTime  $now       The moment to compare against.
	 *
	 * @return string The status before the take-down check.
	 */
	private function materialisedStatus(Dashboard $dashboard, DateTime $now): string {
		$status = $dashboard->getPublicationStatus();
		// Pre-migration / legacy rows that never set the column
		// semantically remain visible (REQ-DASH-035): treat an
		// empty string as `'published'` so backwards compatibility
		// holds even if an entity is hydrated without the column.
		if ($status === '') {
			return Dashboard::STATUS_PUBLISHED;
		}

		// REQ-DASH-034: lazy materialisation of due scheduled rows.
		if ($status === Dashboard::STATUS_SCHEDULED && $this->isDue(dashboard: $dashboard, now: $now) === true) {
			$dashboard->setPublicationStatus(
				Dashboard::STATUS_PUBLISHED
			);
			return Dashboard::STATUS_PUBLISHED;
		}

		return $status;
	}//end materialisedStatus()

	/**
	 * Resolve the active dashboard for a user using the 7-step precedence
	 * chain defined in REQ-DASH-018.
	 *
	 * Steps:
	 *  1. Saved `active_dashboard_uuid` preference — if the UUID resolves to
	 *     a dashboard currently visible to the user (REQ-DASH-013).
	 *  2. `group_shared` with `isDefault = 1` in the user's primary group.
	 *  3. `group_shared` with `isDefault = 1` in the `'default'` group.
	 *  4. First `group_shared` (by sortOrder ASC, then createdAt) in the
	 *     user's primary group.
	 *  5. First `group_shared` in the `'default'` group.
	 *  6. User's first personal (`user`-type) dashboard.
	 *  6b. First dashboard SHARED with the user (REQ-SHARE-002) — last
	 *     resort, so a share never displaces anything the user already
	 *     owns, reaches through a group, or explicitly selected.
	 *  7. `null` — triggers the empty-state UI.
	 *
	 * The only side-effect on read is the stale-pref auto-clear in step 1:
	 * when the saved UUID is not visible the pref is deleted and a WARNING
	 * is logged before falling through to step 2.
	 *
	 * @param string $userId The user ID.
	 * @param string|null $primaryGroupId The user's primary group ID, or null /
	 *                                    {@see Dashboard::DEFAULT_GROUP_ID}.
	 *
	 * @return array{dashboard: Dashboard, source: string}|null
	 *                                                          `{dashboard, source}` where source is `'user'`, `'group'`, or
	 *                                                          `'default'`; or `null` when no dashboard exists at all.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function resolveActiveDashboard(
		string $userId,
		?string $primaryGroupId,
	): ?array {
		// Normalise the sentinel so steps 2-5 can rely on it.
		$groupId = $this->normalisedGroupId(primaryGroupId: $primaryGroupId);

		// Pre-fetch all visible dashboards once — used for the pref lookup
		// and to avoid redundant DB round-trips.
		$visible = $this->getVisibleToUser(userId: $userId);

		// Steps 0-1: the pinned default, then the saved preference.
		$preferred = $this->resolvePreferredActiveDashboard(
			userId: $userId,
			byUuid: $this->indexVisibleByUuid(visible: $visible)
		);
		if ($preferred !== null) {
			return $preferred;
		}

		// Steps 2-3: group-shared with isDefault = 1.
		// Steps 4-5: first group-shared (sortOrder ASC, createdAt ASC).
		// Step 1b (REQ-TMPL-019) sits in front of them: the admin template
		// meant for a user who owns no dashboard yet.
		$groupShared = $this->resolveTemplateOrGroupShared(
			userId: $userId,
			visible: $visible,
			groupId: $groupId
		);
		if ($groupShared !== null) {
			return $groupShared;
		}

		return $this->findFirstPersonalOrShared(visible: $visible);
	}//end resolveActiveDashboard()

	/**
	 * The primary group, or {@see Dashboard::DEFAULT_GROUP_ID} for none.
	 *
	 * @param string|null $primaryGroupId The user's primary group ID.
	 *
	 * @return string The group ID steps 2-5 resolve against.
	 */
	private function normalisedGroupId(string|null $primaryGroupId): string {
		if ($primaryGroupId === null || $primaryGroupId === '') {
			return Dashboard::DEFAULT_GROUP_ID;
		}

		return $primaryGroupId;
	}//end normalisedGroupId()

	/**
	 * A UUID-keyed index of the visible set for O(1) pref lookup.
	 *
	 * @param array<int, array{dashboard: Dashboard, source: string}> $visible The user's visible set.
	 *
	 * @return array<string, array{dashboard: Dashboard, source: string}> The index; empty UUIDs left out.
	 */
	private function indexVisibleByUuid(array $visible): array {
		$byUuid = [];
		foreach ($visible as $entry) {
			$uuid = (string)$entry['dashboard']->getUuid();
			if ($uuid !== '') {
				$byUuid[$uuid] = $entry;
			}
		}

		return $byUuid;
	}//end indexVisibleByUuid()

	/**
	 * Steps 0-1 of {@see self::resolveActiveDashboard()}.
	 *
	 * Step 0 (wave3.7): explicit default — if the user has pinned a
	 * default dashboard via the per-row "Set as default" action, it always
	 * wins over the auto-overwriting `active_dashboard_uuid` pref so
	 * visiting `/apps/launchpad/` consistently opens the same dashboard
	 * regardless of where the user navigated last. Step 1: saved preference.
	 *
	 * @param string                                                     $userId The user ID.
	 * @param array<string, array{dashboard: Dashboard, source: string}> $byUuid UUID-keyed visible index.
	 *
	 * @return array{dashboard: Dashboard, source: string}|null The hit, or null to fall through.
	 */
	private function resolvePreferredActiveDashboard(string $userId, array $byUuid): ?array {
		$pinned = $this->resolvePreferredDashboard(
			userId: $userId,
			byUuid: $byUuid,
			prefKey: self::DEFAULT_DASHBOARD_UUID_PREF_KEY
		);
		if ($pinned !== null) {
			return $pinned;
		}

		return $this->resolvePreferredDashboard(
			userId: $userId,
			byUuid: $byUuid,
			prefKey: self::ACTIVE_DASHBOARD_UUID_PREF_KEY
		);
	}//end resolvePreferredActiveDashboard()

	/**
	 * Steps 6-7 of {@see self::resolveActiveDashboard()}.
	 *
	 * Step 6: first personal dashboard. Step 6b (REQ-SHARE-002): first
	 * dashboard shared WITH the user. Deliberately the LAST resort — after
	 * the saved preference, every group-shared candidate and every personal
	 * dashboard — so a share can never hijack a selection the user or their
	 * admin already made. It only ever fires for a user who would otherwise
	 * land on the empty state, which is exactly the case that made sharing
	 * inert. Step 7: nothing found ⇒ NULL.
	 *
	 * @param array<int, array{dashboard: Dashboard, source: string}> $visible The user's visible set.
	 *
	 * @return array{dashboard: Dashboard, source: string}|null The hit, or null.
	 */
	private function findFirstPersonalOrShared(array $visible): ?array {
		$personal = $this->findFirstBySource(
			visible: $visible,
			source: Dashboard::SOURCE_USER
		);
		if ($personal !== null) {
			return $personal;
		}

		return $this->findFirstBySource(
			visible: $visible,
			source: Dashboard::SOURCE_SHARED
		);
	}//end findFirstPersonalOrShared()

	/**
	 * Resolve a user-preference dashboard pointer, self-healing a stale
	 * one.
	 *
	 * Both preference keys behave identically: when the stored UUID is
	 * still visible to the user it wins outright; when it is not, the
	 * pref is a leftover pointing at a dashboard that has since been
	 * deleted or unshared, so it is cleared and logged and the caller
	 * falls through to the next resolution step.
	 *
	 * @param string $userId The user ID.
	 * @param array<string, array{dashboard: Dashboard, source: string}> $byUuid UUID-keyed visible index.
	 * @param string $prefKey The preference key to read.
	 *
	 * @return array{dashboard: Dashboard, source: string}|NULL The hit, or NULL to fall through.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	private function resolvePreferredDashboard(
		string $userId,
		array $byUuid,
		string $prefKey,
	): ?array {
		$uuid = $this->platform->config->getUserValue(
			userId: $userId,
			appName: Application::APP_ID,
			key: $prefKey,
			default: ''
		);

		if ($uuid === '') {
			return null;
		}

		if (isset($byUuid[$uuid]) === true) {
			return $byUuid[$uuid];
		}

		// Stale pref: UUID is no longer visible — clear and fall through.
		$this->platform->config->deleteUserValue(
			userId: $userId,
			appName: Application::APP_ID,
			key: $prefKey
		);
		$this->outlets->logger->warning(
			message: 'launchpad: stale ' . $prefKey . ' "{uuid}" cleared for user "{user}"',
			context: ['uuid' => $uuid, 'user' => $userId]
		);

		return null;
	}//end resolvePreferredDashboard()

	/**
	 * Step 1b, then steps 2-5, of the page shell's chain.
	 *
	 * Without step 1b the page never looked at templates at all: a group
	 * dashboard for everyone (one is seeded on install) always won, and with
	 * none the page said "No dashboards available" while `GET /api/dashboard`
	 * held the template.
	 *
	 * @param string $userId The user.
	 * @param array<int, array{dashboard: Dashboard, source: string}> $visible The visible set.
	 * @param string $groupId The normalised primary group.
	 *
	 * @return array{dashboard: Dashboard, source: string}|NULL The hit, or NULL to fall through.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-019
	 */
	private function resolveTemplateOrGroupShared(string $userId, array $visible, string $groupId): ?array {
		$fromTemplate = $this->resolveTemplateRung(userId: $userId);
		if ($fromTemplate === null) {
			return $this->resolveGroupSharedDashboard(visible: $visible, groupId: $groupId);
		}

		$source = Dashboard::SOURCE_USER;
		if ($fromTemplate['viewOnly'] === true) {
			$source = Dashboard::SOURCE_GROUP;
		}

		return ['dashboard' => $fromTemplate['dashboard'], 'source' => $source];
	}//end resolveTemplateOrGroupShared()

	/**
	 * The template rung of both resolution chains (REQ-TMPL-019).
	 *
	 * Answers for a user who owns no personal dashboard and has no saved
	 * choice the caller could honour. Such a user gets the admin template
	 * that applies to them: one that targets a group of theirs, else the
	 * default template.
	 *
	 * A template that targets the user's group outranks every group
	 * dashboard. The default template outranks the dashboards of the
	 * `default` group (everyone), and steps aside for a default dashboard of
	 * the user's own primary group, which is the more specific of the two.
	 *
	 * With personal dashboards allowed the user receives a copy, once, and it
	 * becomes their saved choice, so every later visit resolves it through
	 * the preference step and this rung stays silent (it only fires for a
	 * user who owns nothing). With personal dashboards off no copy may be
	 * made (REQ-ASET-003), and the template itself is shown, view only.
	 *
	 * @param string $userId The user.
	 *
	 * @return array{dashboard: Dashboard, viewOnly: bool}|null The dashboard to show, or null to fall through.
	 *
	 * @spec openspec/specs/admin-templates/spec.md#req-tmpl-019
	 */
	private function resolveTemplateRung(string $userId): ?array {
		$template = $this->builders->templateService->getApplicableTemplate(userId: $userId);
		if ($template === null || $this->templateStepsAside(userId: $userId, template: $template) === true) {
			return null;
		}

		if ($this->getAllowUserDashboards() === false) {
			return ['dashboard' => $template, 'viewOnly' => true];
		}

		return ['dashboard' => $this->copyTemplateFor(userId: $userId, template: $template), 'viewOnly' => false];
	}//end resolveTemplateRung()

	/**
	 * Whether the applicable template is not this user's start: they own
	 * a dashboard already, or the template targets no group and their
	 * primary group has a default of its own.
	 *
	 * "First time" means: owns nothing yet. Whoever has a personal
	 * dashboard already made, or was given, their start.
	 *
	 * @param string    $userId   The user.
	 * @param Dashboard $template The applicable template.
	 *
	 * @return bool True when the template is not shown.
	 */
	private function templateStepsAside(string $userId, Dashboard $template): bool {
		return $this->stores->dashboardMapper->findByUserId(userId: $userId) !== []
			|| ($template->getTargetGroupsArray() === []
			&& $this->hasPrimaryGroupDefault(userId: $userId) === true);
	}//end templateStepsAside()

	/**
	 * Copy the template for the user and make the copy their active
	 * dashboard.
	 *
	 * @param string    $userId   The user.
	 * @param Dashboard $template The applicable template.
	 *
	 * @return Dashboard The copy.
	 */
	private function copyTemplateFor(string $userId, Dashboard $template): Dashboard {
		$copy = $this->builders->templateService->createDashboardFromTemplate(
			userId: $userId,
			template: $template
		);
		$this->platform->config->setUserValue(
			userId: $userId,
			appName: Application::APP_ID,
			key: self::ACTIVE_DASHBOARD_UUID_PREF_KEY,
			value: (string)$copy->getUuid()
		);

		return $copy;
	}//end copyTemplateFor()

	/**
	 * Whether the user's primary group has a default group dashboard.
	 *
	 * @param string $userId The user.
	 *
	 * @return bool True when one exists.
	 */
	private function hasPrimaryGroupDefault(string $userId): bool {
		$groupId = $this->builders->adminTemplateService->resolvePrimaryGroup(userId: $userId);
		if ($groupId === Dashboard::DEFAULT_GROUP_ID) {
			return false;
		}

		return $this->findFirstGroupSharedWhere(
			visible: $this->getVisibleToUser(userId: $userId),
			groupId: $groupId,
			source: Dashboard::SOURCE_GROUP,
			requireDefault: true
		) !== null;
	}//end hasPrimaryGroupDefault()

	/**
	 * Shape the template rung's answer for `GET /api/dashboard`.
	 *
	 * @param array{dashboard: Dashboard, viewOnly: bool} $rung The rung's answer.
	 *
	 * @return array The dashboard result.
	 */
	private function describeTemplateRung(array $rung): array {
		$placements = $this->stores->placementMapper->findByDashboardId(
			dashboardId: $rung['dashboard']->getId()
		);

		if ($rung['viewOnly'] === true) {
			return [
				'dashboard' => $rung['dashboard'],
				'placements' => $placements,
				'permissionLevel' => Dashboard::PERMISSION_VIEW_ONLY,
			];
		}

		return $this->builders->dashResolver->buildResult(
			dashboard: $rung['dashboard'],
			placements: $placements
		);
	}//end describeTemplateRung()

	/**
	 * Resolve steps 2-5 of the active-dashboard ladder: the group-shared
	 * candidates.
	 *
	 * Order is normative — primary-group default, default-group default,
	 * primary-group first, default-group first. The primary-group rungs
	 * are skipped when the user has no primary group (the sentinel).
	 *
	 * @param array<int, array{dashboard: Dashboard, source: string}> $visible The visible set.
	 * @param string $groupId The normalised primary group.
	 *
	 * @return array{dashboard: Dashboard, source: string}|NULL The hit, or NULL to fall through.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	private function resolveGroupSharedDashboard(array $visible, string $groupId): ?array {
		foreach (self::groupSharedRungs(groupId: $groupId) as $rung) {
			$result = $this->findFirstGroupSharedWhere(
				visible: $visible,
				groupId: $rung[0],
				source: $rung[1],
				requireDefault: $rung[2]
			);
			if ($result !== null) {
				return $result;
			}
		}

		return null;
	}//end resolveGroupSharedDashboard()

	/**
	 * The group-shared fallback rungs in order, each `[groupId, source,
	 * requireDefault]`. Steps 2 and 4 (the primary group's default, then
	 * its first dashboard) only exist for a viewer whose primary group is
	 * not the default group; steps 3 and 5 are the default group's.
	 *
	 * @param string $groupId The viewer's primary group.
	 *
	 * @return list<array{0:string,1:string,2:bool}> The rungs.
	 */
	private static function groupSharedRungs(string $groupId): array {
		if ($groupId === Dashboard::DEFAULT_GROUP_ID) {
			return [
				[Dashboard::DEFAULT_GROUP_ID, Dashboard::SOURCE_DEFAULT, true],
				[Dashboard::DEFAULT_GROUP_ID, Dashboard::SOURCE_DEFAULT, false],
			];
		}

		return [
			[$groupId, Dashboard::SOURCE_GROUP, true],
			[Dashboard::DEFAULT_GROUP_ID, Dashboard::SOURCE_DEFAULT, true],
			[$groupId, Dashboard::SOURCE_GROUP, false],
			[Dashboard::DEFAULT_GROUP_ID, Dashboard::SOURCE_DEFAULT, false],
		];
	}//end groupSharedRungs()

	/**
	 * Return the first visible entry carrying the given source, in
	 * iteration order.
	 *
	 * @param array<int, array{dashboard: Dashboard, source: string}> $visible The visible set.
	 * @param string $source The source discriminator.
	 *
	 * @return array{dashboard: Dashboard, source: string}|NULL The hit, or NULL when none match.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	private function findFirstBySource(array $visible, string $source): ?array {
		foreach ($visible as $entry) {
			if ($entry['source'] === $source) {
				return $entry;
			}
		}

		return null;
	}//end findFirstBySource()

	/**
	 * Persist (or clear) the user's active-dashboard preference.
	 *
	 * Accepts any non-empty UUID string without performing an existence
	 * check — the resolver's stale-pref path handles invalid UUIDs on next
	 * read (REQ-DASH-019 "no existence check on write").
	 *
	 * @param string $userId The user ID.
	 * @param string $uuid The dashboard UUID, or empty string to clear.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function setActivePreference(string $userId, string $uuid): void {
		if ($uuid === '') {
			$this->platform->config->deleteUserValue(
				userId: $userId,
				appName: Application::APP_ID,
				key: self::ACTIVE_DASHBOARD_UUID_PREF_KEY
			);
			return;
		}

		$this->platform->config->setUserValue(
			userId: $userId,
			appName: Application::APP_ID,
			key: self::ACTIVE_DASHBOARD_UUID_PREF_KEY,
			value: $uuid
		);
	}//end setActivePreference()

	/**
	 * Persist (or clear) the user's EXPLICIT default-dashboard pin
	 * (wave3.7).
	 *
	 * Distinct from {@see self::setActivePreference()} — this pref is
	 * only ever written when the user clicks "Set as default" on a
	 * row's cog menu (it is NOT auto-overwritten on every switch).
	 * The resolver checks it before the active pref so an explicit
	 * pin survives across switches.
	 *
	 * Same write semantics as `setActivePreference`: no existence
	 * check on write, the resolver's stale-pref path handles missing
	 * UUIDs on next read.
	 *
	 * @param string $userId The user ID.
	 * @param string $uuid The dashboard UUID, or empty string to clear.
	 *
	 * @return void
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function setDefaultPreference(string $userId, string $uuid): void {
		if ($uuid === '') {
			$this->platform->config->deleteUserValue(
				userId: $userId,
				appName: Application::APP_ID,
				key: self::DEFAULT_DASHBOARD_UUID_PREF_KEY
			);
			return;
		}

		$this->platform->config->setUserValue(
			userId: $userId,
			appName: Application::APP_ID,
			key: self::DEFAULT_DASHBOARD_UUID_PREF_KEY,
			value: $uuid
		);
	}//end setDefaultPreference()

	/**
	 * Read the user's explicit default-dashboard pin (wave3.7).
	 *
	 * Returns the empty string when no pin is set. No existence
	 * check is performed here — callers should treat the return
	 * value as advisory; the resolver's stale-pref path is the
	 * authoritative source-of-truth.
	 *
	 * @param string $userId The user ID.
	 *
	 * @return string The pinned dashboard UUID, or '' when unset.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function getDefaultPreference(string $userId): string {
		return (string)$this->platform->config->getUserValue(
			userId: $userId,
			appName: Application::APP_ID,
			key: self::DEFAULT_DASHBOARD_UUID_PREF_KEY,
			default: ''
		);
	}//end getDefaultPreference()

	/**
	 * Transition a dashboard to `published` and stamp `publishedAt`
	 * the first time it happens. REQ-DASH-032.
	 *
	 * Idempotent: republishing an already-published dashboard returns
	 * the existing entity without altering `publishedAt`. Owner-or-admin
	 * gated — non-owner non-admin callers raise `Exception` with the
	 * sentinel message {@see self::ERR_FORBIDDEN_NOT_OWNER_OR_ADMIN}
	 * so the controller can map to HTTP 403.
	 *
	 * @param string $uuid The dashboard UUID to publish.
	 * @param string $userId The acting user ID.
	 *
	 * @return Dashboard The updated dashboard entity.
	 *
	 * @throws DoesNotExistException When the UUID does not exist.
	 * @throws Exception When the actor is neither the owner
	 *                   nor a Nextcloud administrator.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function publishDashboard(string $uuid, string $userId): Dashboard {
		$dashboard = $this->stores->dashboardMapper->findByUuid(uuid: $uuid);
		$this->assertOwnerOrAdmin(
			dashboard: $dashboard,
			actorUserId: $userId
		);

		$now = (new DateTime())->format(format: 'Y-m-d H:i:s');

		// REQ-SCHEDUI-002: publishing now overrides a take-down time that
		// already passed. The stored status can still read `published`
		// (the take-down is computed at read time), so this runs before the
		// idempotent return; otherwise the dashboard stays down.
		if ($this->isTakenDown(dashboard: $dashboard, now: new DateTime()) === true) {
			$dashboard->setUnpublishAt(null);
			if ($dashboard->getPublicationStatus() === Dashboard::STATUS_PUBLISHED) {
				$dashboard->setUpdatedAt($now);
				return $this->stores->dashboardMapper->update(entity: $dashboard);
			}
		}

		// Idempotent: already published — no-op other than touching
		// updatedAt is intentionally skipped so audit timestamps stay
		// accurate. Caller still receives the current state.
		if ($dashboard->getPublicationStatus() === Dashboard::STATUS_PUBLISHED) {
			return $dashboard;
		}

		$dashboard->setPublicationStatus(Dashboard::STATUS_PUBLISHED);

		// First-publication timestamp survives unpublish (REQ-DASH-032
		// scenario "Unpublish preserves publishedAt"); only set it when
		// the dashboard has never been published before.
		if ($dashboard->getPublishedAt() === null) {
			$dashboard->setPublishedAt($now);
		}

		// Clear any pending scheduled-publish hint; the dashboard is
		// published immediately and `publishAt` is meaningless now.
		$dashboard->setPublishAt(null);
		$dashboard->setUpdatedAt($now);

		$saved = $this->stores->dashboardMapper->update(entity: $dashboard);
		// Only the transition into published is announced; the idempotent
		// early return above sends nothing (#713).
		$this->outlets->activity?->published(dashboard: $saved, actor: $userId);

		return $saved;
	}//end publishDashboard()

	/**
	 * Transition a dashboard back to `draft` while preserving
	 * `publishedAt` for the audit trail. REQ-DASH-033.
	 *
	 * Idempotent: unpublishing an already-draft dashboard returns the
	 * existing entity unchanged. Owner-or-admin gated.
	 *
	 * @param string $uuid The dashboard UUID to unpublish.
	 * @param string $userId The acting user ID.
	 *
	 * @return Dashboard The updated dashboard entity.
	 *
	 * @throws DoesNotExistException When the UUID does not exist.
	 * @throws Exception When the actor is neither owner nor
	 *                   admin.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function unpublish(string $uuid, string $userId): Dashboard {
		$dashboard = $this->stores->dashboardMapper->findByUuid(uuid: $uuid);
		$this->assertOwnerOrAdmin(
			dashboard: $dashboard,
			actorUserId: $userId
		);

		if ($dashboard->getPublicationStatus() === Dashboard::STATUS_DRAFT) {
			return $dashboard;
		}

		$dashboard->setPublicationStatus(Dashboard::STATUS_DRAFT);
		// REQ-DASH-033: publishedAt is preserved verbatim; publishAt is
		// cleared because the scheduled hint no longer applies once we
		// are explicitly back in draft state.
		$dashboard->setPublishAt(null);
		// REQ-SCHEDUI-002: a take-down time means nothing for a draft.
		$dashboard->setUnpublishAt(null);
		$dashboard->setUpdatedAt(
			(new DateTime())->format(format: 'Y-m-d H:i:s')
		);

		return $this->stores->dashboardMapper->update(entity: $dashboard);
	}//end unpublish()

	/**
	 * Schedule a dashboard for automatic publication at a future
	 * timestamp. REQ-DASH-034.
	 *
	 * Validates that `$publishAt` parses as a valid timestamp strictly
	 * greater than `now()`. Past or unparseable values raise
	 * `InvalidArgumentException` with the canonical error message
	 * {@see self::ERR_SCHEDULE_PAST_DATE} so the controller can map to
	 * HTTP 400 with an i18n-translatable copy. Owner-or-admin gated.
	 *
	 * @param string $uuid The dashboard UUID to schedule.
	 * @param string|null $publishAt The ISO-8601 timestamp at which the
	 *                          dashboard should automatically publish.
	 * @param string $userId The acting user ID.
	 * @param string|null $unpublishAt Optional take-down time (REQ-SCHEDUI-002); null or empty leaves none.
	 *
	 * @return Dashboard The updated dashboard entity.
	 *
	 * @throws DoesNotExistException When the UUID does not exist.
	 * @throws InvalidArgumentException When `publishAt` is missing,
	 *                                  unparseable, or in the past.
	 * @throws Exception When the actor is neither owner
	 *                   nor admin.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function schedule(
		string $uuid,
		?string $publishAt,
		string $userId,
		?string $unpublishAt = null,
	): Dashboard {
		$dashboard = $this->stores->dashboardMapper->findByUuid(uuid: $uuid);
		$this->assertOwnerOrAdmin(
			dashboard: $dashboard,
			actorUserId: $userId
		);

		$window = $this->parsePublicationWindow(publishAt: $publishAt, unpublishAt: $unpublishAt);

		if ($window->publishAt !== null) {
			$dashboard->setPublicationStatus(Dashboard::STATUS_SCHEDULED);
			$dashboard->setPublishAt($window->publishAt);
		}

		$dashboard->setUnpublishAt($window->unpublishAt);
		$dashboard->setUpdatedAt(
			(new DateTime())->format(format: 'Y-m-d H:i:s')
		);

		return $this->stores->dashboardMapper->update(entity: $dashboard);
	}//end schedule()

	/**
	 * Validate and parse the go-live and take-down times of a schedule request.
	 *
	 * @param string|null $publishAt   The requested go-live time.
	 * @param string|null $unpublishAt The requested take-down time.
	 *
	 * @return PublicationWindow The parsed times; null where none was given.
	 *
	 * @throws InvalidArgumentException When neither is given, either is unparseable
	 *                                  or past, or the take-down is not after the go-live.
	 */
	private function parsePublicationWindow(string|null $publishAt, string|null $unpublishAt): PublicationWindow {
		$hasPublishAt = $this->isFilledTime(value: $publishAt);
		$hasUnpublishAt = $this->isFilledTime(value: $unpublishAt);
		if ($hasPublishAt === false && $hasUnpublishAt === false) {
			throw new InvalidArgumentException(message: self::ERR_SCHEDULE_PAST_DATE);
		}

		$parsedPublish = null;
		if ($hasPublishAt === true) {
			$parsedPublish = $this->parseFuturePublishAt(publishAt: (string)$publishAt);
		}

		$parsedUnpublish = null;
		if ($hasUnpublishAt === true) {
			$parsedUnpublish = $this->parseTakeDownAfter(unpublishAt: (string)$unpublishAt, publishAt: $parsedPublish);
		}

		return new PublicationWindow(publishAt: $parsedPublish, unpublishAt: $parsedUnpublish);
	}//end parsePublicationWindow()

	/**
	 * Whether a requested time is given (not null, not blank).
	 *
	 * @param string|null $value The requested time.
	 *
	 * @return bool
	 */
	private function isFilledTime(string|null $value): bool {
		return $value !== null && trim($value) !== '';
	}//end isFilledTime()

	/**
	 * Parse a take-down time that is in the future and after the go-live time.
	 *
	 * REQ-SCHEDUI-002 (sharing-dashboard-schedule-screen): the take-down
	 * time is also in the future, and after the go-live time.
	 *
	 * @param string      $unpublishAt The requested take-down time.
	 * @param string|null $publishAt   The parsed go-live time, or null.
	 *
	 * @return string The parsed take-down time.
	 *
	 * @throws InvalidArgumentException When it is unparseable, past, or not after the go-live.
	 */
	private function parseTakeDownAfter(string $unpublishAt, string|null $publishAt): string {
		$parsedUnpublish = $this->parseFuturePublishAt(publishAt: $unpublishAt);
		if ($publishAt !== null && $parsedUnpublish <= $publishAt) {
			throw new InvalidArgumentException(message: self::ERR_UNPUBLISH_BEFORE_PUBLISH);
		}

		return $parsedUnpublish;
	}//end parseTakeDownAfter()

	/**
	 * Eagerly materialise scheduled dashboards whose `publishAt` is
	 * past-due. REQ-DASH-034 (optional eager path).
	 *
	 * Iterates every row with `publication_status = 'scheduled'` and
	 * `publish_at <= now()`, flips it to `'published'`, and stamps
	 * `publishedAt = now()`. Lazy materialisation in the visibility
	 * filter still runs at read time (correctness guarantee); this
	 * method exists only to keep the database row consistent with the
	 * effective state for cleaner audit queries.
	 *
	 * @return int The number of dashboards materialised.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function materialiseScheduledDashboards(): int {
		$dueRows = $this->stores->dashboardMapper->findDueScheduled();
		if (count($dueRows) === 0) {
			return 0;
		}

		$now = (new DateTime())->format(format: 'Y-m-d H:i:s');
		foreach ($dueRows as $dashboard) {
			$dashboard->setPublicationStatus(Dashboard::STATUS_PUBLISHED);
			if ($dashboard->getPublishedAt() === null) {
				$dashboard->setPublishedAt($now);
			}

			$dashboard->setPublishAt(null);
			$dashboard->setUpdatedAt($now);
			$saved = $this->stores->dashboardMapper->update(entity: $dashboard);
			// A scheduled dashboard is announced once, when its row flips
			// to published here; the owner is the actor (#713).
			$this->outlets->activity?->published(dashboard: $saved, actor: (string) $saved->getUserId());
		}

		return count($dueRows);
	}//end materialiseScheduledDashboards()

	/**
	 * Fork any dashboard the user can read into a brand-new personal copy.
	 *
	 * Implements REQ-DASH-020 / REQ-DASH-021 / REQ-DASH-022:
	 *  - source can be ANY dashboard the user can see — personal, group,
	 *    or default-group sentinel — resolved via the same visible-to-user
	 *    chain as the rest of the multi-scope dashboards code
	 *    (REQ-DASH-013). A source the caller cannot see is treated as a
	 *    404 ({@see DoesNotExistException}).
	 *  - REQ-ASET-003 (extended) gating runs FIRST — when the admin flag
	 *    `allow_user_dashboards` is off the call MUST throw
	 *    {@see PersonalDashboardsDisabledException} before any DB write.
	 *  - the fork is always a `user`-type row owned by `$userId`, with
	 *    `groupId = null`, `isDefault = 0`, and `isActive = 1` (every
	 *    other personal dashboard owned by the user is deactivated as a
	 *    side-effect, mirroring {@see self::createDashboard()}).
	 *  - the fork's `gridColumns` is copied from the source.
	 *  - all `widget_placements` rows on the source are byte-for-byte
	 *    cloned via {@see WidgetPlacementMapper::cloneToDashboard()} —
	 *    tile fields, styleConfig, grid coords, sortOrder, customTitle
	 *    are all preserved (REQ-DASH-020 scenario "byte-for-byte
	 *    clones"). Resource URL fields (e.g. `tileIcon`) reference the
	 *    SAME shared resource record (REQ-DASH-022).
	 *  - the entire operation runs inside a single
	 *    {@see IDBConnection::beginTransaction()} — any failure rolls
	 *    back the new dashboard row AND the partial placement clones
	 *    (REQ-DASH-021).
	 *  - the fork becomes the user's active dashboard (the legacy
	 *    `is_active` SMALLINT column on `user`-type rows is the source
	 *    of truth for personal-only stacks; the
	 *    `active_dashboard_uuid` user-preference is also written so the
	 *    REQ-DASH-018 resolution chain returns the fork on next render).
	 *
	 * @param string $userId The acting user.
	 * @param string $sourceUuid The source dashboard UUID.
	 * @param string|null $name Optional explicit name; when null
	 *                          or empty the default
	 *                          "My copy of {source name}" is
	 *                          used (translated via the user's
	 *                          active language).
	 *
	 * @return Dashboard The newly created (and activated) personal
	 *                   dashboard entity.
	 *
	 * @throws PersonalDashboardsDisabledException When the admin flag
	 *                                             `allow_user_dashboards`
	 *                                             is off — caller maps
	 *                                             to HTTP 403 with the
	 *                                             stable error code
	 *                                             `personal_dashboards_disabled`.
	 * @throws DoesNotExistException When the source UUID
	 *                               does not exist OR the
	 *                               user cannot read it
	 *                               (HTTP 404 — do not
	 *                               leak existence).
	 * @throws Throwable On any other DB error
	 *                   — the transaction is
	 *                   rolled back before
	 *                   rethrowing
	 *                   (REQ-DASH-021).
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function forkAsPersonal(
		string $userId,
		string $sourceUuid,
		?string $name = null,
	): Dashboard {
		// REQ-ASET-003 (extended): gate FIRST so we never persist when
		// personal dashboards are disabled — and so the caller surfaces
		// the stable `personal_dashboards_disabled` envelope no matter
		// what happens with the body.
		$this->assertPersonalDashboardsAllowed();

		// Dashboard-quota-limits REQ-QUOTA-002: a fork creates a new
		// personal-scope dashboard, so it is bound by the per-user
		// dashboard quota exactly like a plain create. Runs before any DB
		// write and before the transaction opens.
		$this->limits->quotaService?->assertCanCreateDashboard(userId: $userId);

		// REQ-DASH-020: source must be visible to the user — reuse the
		// visible-to-user resolver so personal / group / default-group
		// sources all resolve through the same indexed-and-deduped path.
		$source = $this->findVisibleDashboardForFork(
			userId: $userId,
			sourceUuid: $sourceUuid
		);

		$resolvedName = $this->resolveForkName(
			userId: $userId,
			requestedName: $name,
			sourceName: (string)$source->getName()
		);

		// REQ-DASH-020: the slug is derived from the name, so a fork name
		// that collides with one of the user's existing root dashboards
		// would produce a duplicate "/my-copy-of-x" slug (forks bypass the
		// tree service's per-sibling slug check, and the unique index does
		// not cover NULL parent_uuid). Disambiguate the name up-front so
		// repeated forks of the same source get distinct names and slugs.
		//
		// The disambiguate (read) → insert (write) sequence is a TOCTOU
		// window: two simultaneous forks of the same source by the same
		// user would both read the same "taken" slug set, both compute the
		// same " (2)" candidate, and both insert — and the non-unique
		// (parent_uuid, slug) index would not stop them. Serialise the
		// whole read-compute-insert sequence behind a per-user exclusive
		// lock so the second fork only runs its naming pass after the
		// first has committed (and so picks " (3)"). The lock is cluster-
		// wide (DB-backed locking provider), so it holds across app
		// servers, not just within one PHP process.
		$lockKey = 'launchpad/fork/' . $userId;
		$lockAcquired = $this->acquireForkLock(lockKey: $lockKey);

		try {
			$resolvedName = $this->makeUniquePersonalName(
				userId: $userId,
				baseName: $resolvedName
			);

			return $this->persistFork(
				userId: $userId,
				source: $source,
				resolvedName: $resolvedName
			);
		} finally {
			if ($lockAcquired === true) {
				$this->limits->lockingProvider?->releaseLock(
					path: $lockKey,
					type: ILockingProvider::LOCK_EXCLUSIVE
				);
			}
		}//end try
	}//end forkAsPersonal()

	/**
	 * Persist a fork inside a transaction, cloning the source's
	 * placements and pinning the result as the user's active dashboard.
	 *
	 * The whole body runs in one transaction: REQ-DASH-021 requires that
	 * the rollback cover the inserted dashboard row AND any partially
	 * cloned placements, so the catch is deliberately wide and never
	 * leaves a half-persisted fork behind.
	 *
	 * @param string $userId The forking user.
	 * @param Dashboard $source The dashboard being forked.
	 * @param string $resolvedName The already-disambiguated fork name.
	 *
	 * @return Dashboard The persisted fork.
	 *
	 * @throws Throwable Whatever the transaction body threw, after rollback.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	private function persistFork(
		string $userId,
		Dashboard $source,
		string $resolvedName,
	): Dashboard {
		$this->platform->db->beginTransaction();
		try {
			// REQ-DASH-020: force `isDefault = 0` and `groupId = null`
			// on the fork — the factory is the single source of truth
			// for the (type, groupId) invariant (REQ-DASH-011).
			$fork = $this->builders->dashboardFactory->create(
				userId: $userId,
				name: $resolvedName,
				options: [
					'description'     => $source->getDescription(),
					'type'            => Dashboard::TYPE_USER,
					'groupId'         => null,
					'gridColumns'     => $source->getGridColumns(),
					'permissionLevel' => Dashboard::PERMISSION_FULL,
				]
			);
			// Defensive — the factory already sets this for TYPE_USER but
			// we make the contract visible at the call site.
			$fork->setIsDefault(0);

			// REQ-DASH-020: deactivate every other personal dashboard
			// for this user before persisting the fork — mirrors
			// {@see self::createDashboard()} so the single-active
			// invariant holds across the transaction.
			$this->stores->dashboardMapper->deactivateAllForUser(userId: $userId);
			$fork->setIsActive(1);

			$persisted = $this->stores->dashboardMapper->insert(entity: $fork);

			// REQ-DASH-020: byte-for-byte placement clone. Any DB error
			// bubbles out of the mapper and the catch below rolls back.
			$this->stores->placementMapper->cloneToDashboard(
				sourceDashboardId: (int)$source->getId(),
				targetDashboardId: (int)$persisted->getId()
			);

			// REQ-DASH-018 / REQ-DASH-019: also pin the active-dashboard
			// user-pref so the resolver returns the fork on the next
			// render even when the personal `is_active` column is not
			// the source of truth (multi-scope deployments).
			$forkUuid = (string)$persisted->getUuid();
			if ($forkUuid !== '') {
				$this->setActivePreference(
					userId: $userId,
					uuid: $forkUuid
				);
			}

			$this->platform->db->commit();

			return $persisted;
		} catch (Throwable $t) {
			$this->platform->db->rollBack();
			throw $t;
		}//end try
	}//end persistFork()

	/**
	 * Acquire the per-user exclusive fork-naming lock, with a bounded
	 * retry so a contending fork waits for the in-flight one to commit
	 * rather than failing.
	 *
	 * `ILockingProvider::acquireLock()` is non-blocking — it throws
	 * {@see LockedException} immediately when the key is held — so we
	 * poll a few times with a short backoff. If the lock still cannot be
	 * acquired within the window we proceed WITHOUT it: that degrades to
	 * the pre-lock behaviour (the narrow TOCTOU window) rather than
	 * failing a user-initiated fork outright. When no locking provider is
	 * wired (e.g. unit tests / minimal containers) we also proceed
	 * unlocked.
	 *
	 * @param string $lockKey The cluster-wide lock key (per user).
	 *
	 * @return bool True when the lock was acquired (caller MUST release it).
	 */
	private function acquireForkLock(string $lockKey): bool {
		if ($this->limits->lockingProvider === null) {
			return false;
		}

		$maxAttempts = 10;
		for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
			try {
				$this->limits->lockingProvider->acquireLock(
					path: $lockKey,
					type: ILockingProvider::LOCK_EXCLUSIVE
				);
				return true;
			} catch (LockedException) {
				// Held by a concurrent fork — wait briefly and retry.
				usleep(microseconds: 100000);
			}
		}

		$this->outlets->logger->warning(
			message: 'forkAsPersonal could not acquire the fork-naming lock; proceeding unlocked',
			context: [
				'app' => Application::APP_ID,
				'lockKey' => $lockKey,
			]
		);

		return false;
	}//end acquireForkLock()

	/**
	 * Resolve the source dashboard for a fork via the visible-to-user
	 * chain.
	 *
	 * Personal, group-shared (matching), and default-group sentinel
	 * dashboards are all eligible source candidates. A source UUID the
	 * caller cannot see MUST be reported as a 404 to avoid leaking
	 * existence (REQ-DASH-020 scenario "Cannot fork a dashboard you
	 * cannot read").
	 *
	 * @param string $userId The acting user.
	 * @param string $sourceUuid The source UUID.
	 *
	 * @return Dashboard The resolved source dashboard entity.
	 *
	 * @throws DoesNotExistException When the source is not visible.
	 */
	private function findVisibleDashboardForFork(
		string $userId,
		string $sourceUuid,
	): Dashboard {
		$visible = $this->getVisibleToUser(userId: $userId);
		foreach ($visible as $entry) {
			$candidate = $entry['dashboard'];
			if ((string)$candidate->getUuid() === $sourceUuid) {
				return $candidate;
			}
		}

		throw new DoesNotExistException(msg: 'Dashboard not found');
	}//end findVisibleDashboardForFork()

	/**
	 * Resolve the effective name for a forked dashboard.
	 *
	 * When the caller supplies a non-empty `$requestedName` we use it
	 * verbatim. Otherwise the system applies the localised default
	 * `t('My copy of {name}', ['name' => $sourceName])` using the
	 * acting user's active language (REQ-DASH-020).
	 *
	 * @param string $userId The acting user (drives the
	 *                       l10n locale).
	 * @param string|null $requestedName Caller-supplied name.
	 * @param string $sourceName The source dashboard's name —
	 *                           substituted into the default
	 *                           pattern via the IL10N
	 *                           placeholder mechanism (NOT
	 *                           string concatenation).
	 *
	 * @return string The resolved name (always non-empty).
	 */
	private function resolveForkName(
		string $userId,
		?string $requestedName,
		string $sourceName,
	): string {
		$trimmed = trim((string)$requestedName);
		if ($trimmed !== '') {
			return $trimmed;
		}

		$l10n = $this->platform->l10nFactory->get(
			app: Application::APP_ID,
			lang: $this->platform->config->getUserValue(
				userId: $userId,
				appName: 'core',
				key: 'lang',
				default: ''
			)
		);

		// IL10N::t uses positional `%s` placeholders (vsprintf under
		// the hood) — the cross-cutting JS / Python pipelines use
		// `{name}` curly placeholders, but the PHP boundary stays on
		// the standard sprintf substitution mechanism.
		return $l10n->t('My copy of %s', [$sourceName]);
	}//end resolveForkName()

	/**
	 * Disambiguate a fork name so its derived slug is free among the
	 * user's existing root personal dashboards. Appends " (2)", " (3)",
	 * … until {@see SlugGenerator::slugify()} yields an unused slug, so
	 * repeated forks of one source no longer share a name and slug
	 * (REQ-DASH-020). Names whose slug is empty (no legal characters)
	 * are returned unchanged — such rows stay unaddressable by path.
	 *
	 * @param string $userId The owning user.
	 * @param string $baseName The candidate fork name.
	 *
	 * @return string The disambiguated name (possibly suffixed).
	 */
	private function makeUniquePersonalName(
		string $userId,
		string $baseName,
	): string {
		$taken = [];
		foreach ($this->stores->dashboardMapper->findByUserId(userId: $userId) as $dash) {
			// Forks land at the root, so only root siblings can collide.
			if ($dash->getParentUuid() !== null) {
				continue;
			}

			$slug = (string)$dash->getSlug();
			if ($slug !== '') {
				$taken[$slug] = true;
			}
		}

		$candidate = $baseName;
		$counter = 2;
		while (isset($taken[SlugGenerator::slugify(name: $candidate)]) === true) {
			$candidate = $baseName . ' (' . $counter . ')';
			$counter++;
		}

		return $candidate;
	}//end makeUniquePersonalName()

	/**
	 * Check whether the given user is a Nextcloud administrator.
	 *
	 * Wraps `IGroupManager::isAdmin()` so callers don't have to import
	 * the interface and so tests can stub one method.
	 *
	 * @param string $userId The user ID.
	 *
	 * @return bool Whether the user is an admin.
	 * @spec   openspec/specs/dashboards/spec.md
	 */
	public function isAdmin(string $userId): bool {
		return $this->platform->groupManager->isAdmin(userId: $userId);
	}//end isAdmin()

	/**
	 * Check whether the user is a member of a group or is an admin.
	 *
	 * Used by listGroup / getGroup to gate group-shared dashboard
	 * endpoints against non-member callers (REQ-DASH-014, H1).
	 *
	 * @param string $userId The user ID.
	 * @param string $groupId The group ID from the URL.
	 *
	 * @return bool True when the user is in the group or is a NC admin.
	 *
	 * @spec openspec/changes/launchpad-legacy-quality-cleanup/tasks.md#task-1
	 */
	public function userCanAccessGroup(string $userId, string $groupId): bool {
		if ($this->platform->groupManager->isAdmin(userId: $userId) === true) {
			return true;
		}

		$userGroupIds = $this->builders->adminTemplateService->getUserGroupIdsFor(
			userId: $userId
		);

		return in_array(needle: $groupId, haystack: $userGroupIds, strict: true);
	}//end userCanAccessGroup()

	/**
	 * Read the admin `allow_user_dashboards` flag without throwing.
	 *
	 * Use this when callers need a plain boolean (e.g. to render the UI
	 * affordance or to push the flag into the initial-state contract);
	 * use {@see self::assertPersonalDashboardsAllowed()} when the call
	 * site needs the request to be rejected with a 403 envelope.
	 *
	 * The default is `false` (admins must opt in) — this is the secure
	 * default mandated by REQ-ASET-003: when the row is missing, personal
	 * dashboard creation MUST be blocked.
	 *
	 * @return bool Whether personal dashboard creation is currently
	 *              permitted by admin settings.
	 *
	 * @SuppressWarnings(PHPMD.BooleanGetMethodName) Intentional: the proposal
	 *  pins the public name to `getAllowUserDashboards()` so it mirrors the
	 *  initial-state key (`allowUserDashboards`) and the
	 *  setting key constant (`AdminSetting::KEY_ALLOW_USER_DASHBOARDS`).
	 *  Renaming to `isAllowUserDashboards()` would break the symmetry the
	 *  spec relies on.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function getAllowUserDashboards(): bool {
		return (bool)$this->stores->settingMapper->getValue(
			key: AdminSetting::KEY_ALLOW_USER_DASHBOARDS,
			default: false
		);
	}//end getAllowUserDashboards()

	/**
	 * Assert that personal-dashboard creation is permitted by admin settings.
	 *
	 * Implements REQ-ASET-003 runtime gating: when the admin flag
	 * `allow_user_dashboards` is `false` (or absent — default is `false`),
	 * creation of `type='user'` dashboards MUST be blocked at the service
	 * boundary. Read / update / delete operations on existing personal
	 * dashboards MUST NOT call this method.
	 *
	 * @return void
	 *
	 * @throws PersonalDashboardsDisabledException When the flag is off.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function assertPersonalDashboardsAllowed(): void {
		if ($this->getAllowUserDashboards() === false) {
			throw new PersonalDashboardsDisabledException();
		}
	}//end assertPersonalDashboardsAllowed()

	/**
	 * Scan the pre-fetched visible list for the first group-shared dashboard
	 * matching a given `groupId`, optionally filtered to `isDefault = 1`.
	 *
	 * The visible list preserves mapper order (sortOrder ASC, createdAt ASC
	 * for group-shared rows via {@see DashboardMapper::findByGroup}), so the
	 * "first" result is already correctly ordered without a secondary sort
	 * here.
	 *
	 * @param array<int, array{dashboard: Dashboard, source: string}> $visible The full visible-to-user list.
	 * @param string $groupId The group ID to filter on.
	 * @param string $source Expected source tag
	 *                       (`'group'` or
	 *                       `'default'`).
	 * @param bool $requireDefault When true, only rows with
	 *                             `isDefault = 1` are
	 *                             considered.
	 *
	 * @return array{dashboard: Dashboard, source: string}|null
	 */
	private function findFirstGroupSharedWhere(
		array $visible,
		string $groupId,
		string $source,
		bool $requireDefault,
	): ?array {
		foreach (self::entriesFromSource(visible: $visible, source: $source) as $entry) {
			if (self::isGroupSharedIn(dashboard: $entry['dashboard'], groupId: $groupId) === true
				&& self::meetsDefaultRequirement(dashboard: $entry['dashboard'], requireDefault: $requireDefault) === true
			) {
				return $entry;
			}
		}

		return null;
	}//end findFirstGroupSharedWhere()

	/**
	 * The visible entries resolved through the given source, in order.
	 *
	 * @param array<int, array{dashboard: Dashboard, source: string}> $visible The visible entries.
	 * @param string                                                  $source  The source.
	 *
	 * @return array<int, array{dashboard: Dashboard, source: string}> The matching entries.
	 */
	private static function entriesFromSource(array $visible, string $source): array {
		return array_filter(
			array: $visible,
			callback: static fn (array $entry): bool => $entry['source'] === $source
		);
	}//end entriesFromSource()

	/**
	 * Whether a dashboard is a group-shared dashboard of the given group.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 * @param string    $groupId   The group ID.
	 *
	 * @return bool True when it is.
	 */
	private static function isGroupSharedIn(Dashboard $dashboard, string $groupId): bool {
		return $dashboard->getType() === Dashboard::TYPE_GROUP_SHARED
			&& $dashboard->getGroupId() === $groupId;
	}//end isGroupSharedIn()

	/**
	 * Whether a dashboard satisfies the rung's default requirement.
	 *
	 * @param Dashboard $dashboard      The dashboard.
	 * @param bool      $requireDefault Whether only a default dashboard qualifies.
	 *
	 * @return bool True when it qualifies.
	 */
	private static function meetsDefaultRequirement(Dashboard $dashboard, bool $requireDefault): bool {
		return $requireDefault === false || (int)$dashboard->getIsDefault() === 1;
	}//end meetsDefaultRequirement()

	/**
	 * A root slug for this user's auto-provisioned dashboard that no sibling holds.
	 *
	 * Root slugs share one namespace across every owner, so 'my-dashboard'
	 * belongs to whoever provisioned first. The owner's id is appended for
	 * everyone else, and a random segment settles the rest.
	 *
	 * @param string $userId The owner.
	 *
	 * @return string A slug free at root, or the plain one when nothing holds it.
	 *
	 * @spec openspec/specs/role-feature-permissions/spec.md#req-rfp-002-role-based-default-dashboard-layout
	 */
	private function uniqueRootSlugFor(string $userId): string {
		$base = SlugGenerator::slugify(name: 'My Dashboard');
		$candidates = [$base, ($base . '-' . SlugGenerator::slugify(name: $userId))];
		$candidates[] = ($base . '-' . bin2hex(random_bytes(4)));

		foreach ($candidates as $candidate) {
			if ($candidate === '' || $candidate === $base . '-') {
				continue;
			}

			try {
				$this->structure->treeService->validateSlugUnique(parentUuid: null, slug: $candidate);
				return $candidate;
			} catch (InvalidArgumentException) {
				continue;
			}
		}

		return ($base . '-' . bin2hex(random_bytes(6)));
	}//end uniqueRootSlugFor()

	/**
	 * Try to create a dashboard from a template or empty.
	 *
	 * @param string $userId The user ID.
	 *
	 * @return array|null The dashboard result or null.
	 */
	private function tryCreateFromTemplate(string $userId): ?array {
		$allowUserDashboards = $this->getAllowUserDashboards();
		$template = $this->builders->templateService->getApplicableTemplate(userId: $userId);

		if ($template !== null) {
			return $this->builders->dashResolver->handleTemplateResult(
				template: $template,
				allowUserDashboards: $allowUserDashboards,
				userId: $userId
			);
		}

		if ($allowUserDashboards === true) {
			return $this->provisionPersonalDashboard(userId: $userId);
		}

		return null;
	}//end tryCreateFromTemplate()

	/**
	 * Provision a user's first personal dashboard, laid out from their
	 * role defaults or, without any, the default bundle.
	 *
	 * 🔴 THE SLUG IS PER USER, BECAUSE THE ROOT SLUG NAMESPACE IS NOT.
	 * `validateSlugUnique()` looks for any root dashboard with this
	 * slug, whoever owns it, and every auto-provisioned dashboard is
	 * named 'My Dashboard'. So the SECOND user ever to reach this
	 * branch got `Slug must be unique among siblings` and the request
	 * answered HTTP 500 with no dashboard at all. Measured on a fresh
	 * instance: the first user provisioned, the next one 500'd.
	 *
	 * @param string $userId The user ID.
	 *
	 * @return array{dashboard: Dashboard, placements: array, permissionLevel: string}
	 */
	private function provisionPersonalDashboard(string $userId): array {
		$dashboard = $this->createDashboard(
			userId: $userId,
			name: 'My Dashboard',
			options: ['slug' => $this->uniqueRootSlugFor(userId: $userId)]
		);

		$seeded = $this->seedFromRoleDefaults(userId: $userId, dashboard: $dashboard);

		return [
			'dashboard' => $dashboard,
			'placements' => $this->provisionedPlacements(dashboard: $dashboard, seeded: $seeded),
			'permissionLevel' => Dashboard::PERMISSION_FULL,
		];
	}//end provisionPersonalDashboard()

	/**
	 * REQ-RFP-002: when no admin template applies, prefer seeding
	 * from the user's RoleLayoutDefault rows. The dependency is nullable
	 * to keep legacy PHPUnit doubles (built before role-based-content
	 * shipped) working — when null we treat it as "no defaults seeded" so
	 * the legacy hardcoded fallback runs.
	 *
	 * @param string    $userId    The user ID.
	 * @param Dashboard $dashboard The provisioned dashboard.
	 *
	 * @return int The number of placements seeded from role defaults.
	 */
	private function seedFromRoleDefaults(string $userId, Dashboard $dashboard): int {
		if ($this->limits->roleFeaturePerm === null) {
			return 0;
		}

		return $this->limits->roleFeaturePerm->seedLayoutFromRoleDefaults(
			userId: $userId,
			dashboard: $dashboard
		);
	}//end seedFromRoleDefaults()

	/**
	 * The provisioned dashboard's placements: the role-seeded ones, or —
	 * only when nothing was seeded — the hardcoded default bundle.
	 *
	 * The hardcoded pair is a FALLBACK: it used to be created on every
	 * path, so a role-seeded layout came out carrying the role defaults
	 * AND tile/tile/tile/files.
	 *
	 * @param Dashboard $dashboard The provisioned dashboard.
	 * @param int       $seeded    The number of role-seeded placements.
	 *
	 * @return array The placements.
	 */
	private function provisionedPlacements(Dashboard $dashboard, int $seeded): array {
		if ($seeded > 0) {
			return $this->stores->placementMapper->findByDashboardId(
				dashboardId: $dashboard->getId()
			);
		}

		if ($seeded === 0) {
			return $this->createDefaultPlacements(
				dashboardId: $dashboard->getId()
			);
		}

		return [];
	}//end provisionedPlacements()

	/**
	 * Create default widget placements for a new dashboard.
	 *
	 * Delegates to {@see self::seedDefaultWidgets()}; retained as a
	 * private alias because the bootstrap fallback in
	 * {@see self::tryCreateFromTemplate()} historically called this name.
	 *
	 * @param int $dashboardId The dashboard ID.
	 *
	 * @return WidgetPlacement[] The created placements.
	 */
	private function createDefaultPlacements(int $dashboardId): array {
		return $this->seedDefaultWidgets(dashboardId: $dashboardId);
	}//end createDefaultPlacements()

	/**
	 * Return all widget placements for a dashboard.
	 *
	 * Thin pass-through over the placement mapper used by callers that
	 * only have a dashboard id and need the full placement list (e.g.
	 * the `POST /api/dashboard` controller path returning the freshly
	 * seeded default widget bundle in its response envelope).
	 *
	 * @param int $dashboardId The dashboard ID.
	 *
	 * @return WidgetPlacement[] The placements ordered by sortOrder.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function findPlacements(int $dashboardId): array {
		return $this->stores->placementMapper->findByDashboardId(
			dashboardId: $dashboardId
		);
	}//end findPlacements()

	/**
	 * Provision the shipped default dashboard when the instance has none.
	 *
	 * WHY A FRESH INSTALL WAS EMPTY. `allowUserDashboards` deliberately
	 * defaults to `false` (REQ-ASET-003: an admin must opt in before users
	 * may create personal dashboards). That default closes the only
	 * branch of {@see self::tryCreateFromTemplate()} that WRITES, so on a
	 * brand-new instance nothing ever created a dashboard — not the app,
	 * not the user. `oc_launchpad_dashboards` stayed empty, every rung of
	 * {@see self::resolveActiveDashboard()} returned null, and
	 * `/apps/launchpad/` rendered "No dashboards available — contact your
	 * administrator". The default widget bundle
	 * ({@see self::defaultWidgetSeeds()}) had shipped and had unit tests
	 * the whole time; nothing ever called it, because nothing ever created
	 * the dashboard it seeds.
	 *
	 * THE SHAPE MATTERS, AND AN ADMIN TEMPLATE IS THE WRONG ONE. This app
	 * has two resolution chains and they do not agree.
	 * {@see self::getEffectiveDashboard()} consults admin templates via
	 * `tryCreateFromTemplate()`; the page shell
	 * ({@see \OCA\LaunchPad\Controller\PageController::index()}) calls
	 * `resolveActiveDashboard()`, which reads only
	 * {@see self::getVisibleToUser()} and never looks at a template at all.
	 * Seeding a default admin template therefore writes four placements the
	 * workspace still will not render. The shape the SHELL resolves is a
	 * `group_shared` dashboard on the reserved `default` group sentinel
	 * (REQ-DASH-012) with `isDefault = 1` — rung 3 of
	 * `resolveGroupSharedDashboard()`, which fires for every user whether or
	 * not they are in any Nextcloud group.
	 *
	 * It is created `published`, not `draft`: `filterByPublicationState()`
	 * hides drafts from everyone but the owner, and this row has no owner.
	 *
	 * Idempotent — it does nothing once a `default`-group dashboard exists,
	 * so it is safe on both `install` and `post-migration`. An admin who
	 * deletes it gets it back on the next upgrade; that is the same
	 * trade-off the app's other seeding repair steps make, and the reason
	 * this ships as shared instance content rather than as user content.
	 *
	 * @return Dashboard|null The provisioned dashboard, or null when one
	 *                        already existed.
	 *
	 * @spec openspec/specs/default-widget-bundle/spec.md
	 */
	public function ensureDefaultDashboard(): ?Dashboard {
		$existing = $this->stores->dashboardMapper->findByGroup(
			groupId: Dashboard::DEFAULT_GROUP_ID
		);
		if (count(value: $existing) > 0) {
			return null;
		}

		$dashboard = $this->builders->dashboardFactory->create(
			userId: null,
			name: 'Dashboard',
			options: [
				'description'     => 'The dashboard everyone starts on. Edit it to change what your whole organisation sees.',
				'type'            => Dashboard::TYPE_GROUP_SHARED,
				'groupId'         => Dashboard::DEFAULT_GROUP_ID,
				'permissionLevel' => Dashboard::PERMISSION_VIEW_ONLY,
			]
		);
		// Rung 3 of resolveGroupSharedDashboard() requires isDefault; without
		// it the row is only reachable through the weaker rung-5 fallback.
		$dashboard->setIsDefault(1);
		// Drafts are hidden from every non-owner, and this row has no owner.
		$dashboard->setPublicationStatus(Dashboard::STATUS_PUBLISHED);

		$persisted = $this->stores->dashboardMapper->insert(entity: $dashboard);
		$this->seedDefaultWidgets(dashboardId: $persisted->getId());

		return $persisted;
	}//end ensureDefaultDashboard()

	/**
	 * Seed the default widget bundle on a freshly-created dashboard.
	 *
	 * Every brand-new personal dashboard is bootstrapped with three
	 * preconfigured `tile` widgets (Conduction, Sendent, Nextcloud) on
	 * the top row plus a `files` widget below, on a 12-column grid:
	 *
	 *   row 0..2 : Conduction (0..3) | Sendent (4..7) | Nextcloud (8..11)
	 *   row 3..7 : Files (0..11)
	 *
	 * Admin-created template dashboards skip this seed — templates run
	 * through the resolver path
	 * ({@see self::dashResolver::handleTemplateResult()}) and ship the
	 * widget set their author intended.
	 *
	 * The tile content is persisted via the legacy flat
	 * `tile{Title,Icon,IconType,BackgroundColor,TextColor,LinkType,LinkValue}`
	 * columns so the renderer's inline-or-flat coalescing path
	 * (REQ-TILE-PLACEMENT) picks them up without a JSON `content`
	 * column. The Nextcloud tile uses an `icon-` CSS class (no logo
	 * asset is shipped); Conduction and Sendent point at the PNGs in
	 * `launchpad/img/` via {@see self::appImagePath()}, which resolves the
	 * app's real web root (so the URLs work regardless of which apps
	 * directory the app is served from).
	 *
	 * @param int $dashboardId The dashboard ID to seed.
	 *
	 * @return WidgetPlacement[] The persisted placements (4 entries).
	 */
	private function seedDefaultWidgets(int $dashboardId): array {
		$now = (new DateTime())->format(format: 'Y-m-d H:i:s');

		$placements = [];
		foreach ($this->defaultWidgetSeeds() as $config) {
			$placements[] = $this->stores->placementMapper->insert(
				entity: $this->buildSeedPlacement(
					dashboardId: $dashboardId,
					config: $config,
					now: $now
				)
			);
		}

		return $placements;
	}//end seedDefaultWidgets()

	/**
	 * The declarative seed table for a brand-new dashboard's default
	 * widget set.
	 *
	 * Pure data — extracted from {@see self::seedDefaultWidgets()} so the
	 * seeding logic and the seed content can be read (and changed)
	 * independently.
	 *
	 * @return array<int, array<string, mixed>> The seed configs.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	private function defaultWidgetSeeds(): array {
		$seeds = self::DEFAULT_WIDGET_SEEDS;
		foreach ($seeds as $index => $seed) {
			// A `url` icon names a bundled image; resolve it to the app's real web root.
			if (($seed['tile']['iconType'] ?? null) === 'url') {
				$seeds[$index]['tile']['icon'] = $this->appImagePath(image: $seed['tile']['icon']);
			}
		}

		return $seeds;
	}//end defaultWidgetSeeds()

	/**
	 * Materialise one seed config into an unsaved
	 * {@see WidgetPlacement}.
	 *
	 * @param int $dashboardId The owning dashboard.
	 * @param array<string, mixed> $config One entry of the seed table.
	 * @param string $now Current `Y-m-d H:i:s`.
	 *
	 * @return WidgetPlacement The populated, not-yet-inserted placement.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	private function buildSeedPlacement(int $dashboardId, array $config, string $now): WidgetPlacement {
		$placement = new WidgetPlacement();
		$placement->setDashboardId($dashboardId);
		$placement->setWidgetId($config['widgetId']);
		$placement->setGridX($config['gridX']);
		$placement->setGridY($config['gridY']);
		$placement->setGridWidth($config['gridWidth']);
		$placement->setGridHeight($config['gridHeight']);
		$placement->setSortOrder($config['sortOrder']);
		$placement->setShowTitle(1);
		$placement->setIsVisible(1);
		$placement->setCreatedAt($now);
		$placement->setUpdatedAt($now);

		if ($config['tile'] === null) {
			return $placement;
		}

		// `tileType` MUST be non-null for `WidgetPlacement::jsonSerialize()`
		// to emit the flat tile* fields the renderer reads. The
		// sentinel `'preset'` distinguishes seed-tiles from the
		// legacy `'custom'` value that routes through the
		// pre-registry tile path in DashboardGrid.vue, so these
		// placements still flow through the registry-backed
		// TileWidget renderer (REQ-WDG-022).
		$placement->setTileType('preset');
		$placement->setTileTitle($config['tile']['title']);
		$placement->setTileIcon($config['tile']['icon']);
		$placement->setTileIconType($config['tile']['iconType']);
		$placement->setTileBackgroundColor($config['tile']['backgroundColor']);
		$placement->setTileTextColor($config['tile']['textColor']);
		$placement->setTileLinkType($config['tile']['linkType']);
		$placement->setTileLinkValue($config['tile']['linkValue']);

		return $placement;
	}//end buildSeedPlacement()

	/**
	 * Apply updates to a dashboard entity.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 * @param array $data The update data.
	 *
	 * @return void
	 */
	private function applyDashboardUpdates(
		Dashboard $dashboard,
		array $data,
	): void {
		$this->applyFieldUpdates(dashboard: $dashboard, data: $data);
		$this->applyTreeUpdates(dashboard: $dashboard, data: $data);
		$this->applyFooterUpdates(dashboard: $dashboard, data: $data);
		$this->applyLookUpdates(dashboard: $dashboard, data: $data);

		$dashboard->setUpdatedAt(
			(new DateTime())->format(format: 'Y-m-d H:i:s')
		);

		$this->applyPlacementUpdates(data: $data);
	}//end applyDashboardUpdates()

	/**
	 * Apply a patched look: accent, background colour and background image (REQ-THM-001/002).
	 *
	 * An empty string clears a field, so the dashboard follows the theme
	 * again. Colours are stored as lower-case `#rrggbb`, the image as a
	 * LaunchPad resource file name. Whenever the accent or the background
	 * changes, the accent that results must reach 3:1 against the background
	 * that results (the instance's when the dashboard has none).
	 *
	 * @param Dashboard            $dashboard The dashboard being updated.
	 * @param array<string, mixed> $data      The update payload.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When a colour or image is malformed, or the accent is too pale.
	 *
	 * @spec openspec/changes/platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-a-look-must-stay-readable-req-thm-002
	 */
	private function applyLookUpdates(Dashboard $dashboard, array $data): void {
		$accent     = $dashboard->getThemeAccent();
		$background = $dashboard->getThemeBackgroundColor();
		$colours    = ['themeAccent' => 'The accent', 'themeBackgroundColor' => 'The background colour'];
		foreach ($colours as $key => $label) {
			if (array_key_exists($key, $data) === false) {
				continue;
			}

			$value = null;
			if (trim((string)$data[$key]) !== '') {
				$value = DashboardLook::normaliseHex(colour: (string)$data[$key], field: $label);
			}

			if ($key === 'themeAccent') {
				$accent = $value;
				continue;
			}

			$background = $value;
		}

		if (array_key_exists('themeBackgroundResource', $data) === true) {
			$resource = trim((string)$data['themeBackgroundResource']);
			if ($resource !== '' && DashboardLook::isResourceName(name: $resource) === false) {
				throw new InvalidArgumentException('The background image must be an image uploaded to LaunchPad.');
			}

			$stored = null;
			if ($resource !== '') {
				$stored = $resource;
			}

			$dashboard->setThemeBackgroundResource($stored);
		}

		$colourChanged = array_key_exists('themeAccent', $data) === true || array_key_exists('themeBackgroundColor', $data) === true;
		if ($colourChanged === true && $accent !== null) {
			DashboardLook::assertAccentReadable(accent: $accent, background: $background);
		}

		$dashboard->setThemeAccent($accent);
		$dashboard->setThemeBackgroundColor($background);
	}//end applyLookUpdates()

	/**
	 * Apply a patched placement position list, when the payload has one.
	 *
	 * @param array<string, mixed> $data The update payload.
	 *
	 * @return void
	 */
	private function applyPlacementUpdates(array $data): void {
		if (isset($data['placements']) === true
			&& is_array($data['placements']) === true
		) {
			$this->stores->placementMapper->updatePositions(
				updates: $data['placements']
			);
		}
	}//end applyPlacementUpdates()

	/**
	 * Apply the patched name, description, icon and grid columns.
	 *
	 * @param Dashboard            $dashboard The dashboard being updated.
	 * @param array<string, mixed> $data      The update payload.
	 *
	 * @return void
	 */
	private function applyFieldUpdates(Dashboard $dashboard, array $data): void {
		if (isset($data['name']) === true) {
			$dashboard->setName($data['name']);
		}

		if (isset($data['description']) === true) {
			$dashboard->setDescription($data['description']);
		}

		$this->applyIconUpdate(dashboard: $dashboard, data: $data);

		if (isset($data['gridColumns']) === true) {
			$dashboard->setGridColumns($data['gridColumns']);
		}
	}//end applyFieldUpdates()

	/**
	 * Apply a patched icon: a string is stored as given, anything else
	 * clears it.
	 *
	 * Icon may be NULL/empty (use default), a registry key, or a URL.
	 * The discriminator + lookup live frontend-side in the
	 * `dashboard-icons` capability — we just store the opaque string.
	 *
	 * @param Dashboard            $dashboard The dashboard being updated.
	 * @param array<string, mixed> $data      The update payload.
	 *
	 * @return void
	 */
	private function applyIconUpdate(Dashboard $dashboard, array $data): void {
		if (array_key_exists(key: 'icon', array: $data) === false) {
			return;
		}

		$iconToStore = null;
		if (is_string($data['icon']) === true) {
			$iconToStore = $data['icon'];
		}

		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$dashboard->setIcon($iconToStore);
	}//end applyIconUpdate()

	/**
	 * Apply per-dashboard footer override updates (REQ-FTR-006).
	 *
	 * Mode + HTML are decoupled — callers can patch either independently.
	 * Validates the mode against {@see Dashboard::FOOTER_MODES}, sanitises
	 * the HTML through {@see FooterService::sanitiseHtml()} when mode is
	 * `custom`, and clears the HTML to NULL when mode flips away from
	 * `custom` (REQ-FTR-006 mode-change scenario). When mode is `custom`
	 * but no HTML is supplied (and the dashboard has no stored HTML
	 * either), throws so the controller can return HTTP 400.
	 *
	 * @param Dashboard $dashboard The dashboard being updated.
	 * @param array $data The patch payload.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When mode is invalid or `custom`
	 *                                  is requested without HTML.
	 */
	private function applyFooterUpdates(
		Dashboard $dashboard,
		array $data,
	): void {
		$modeProvided = array_key_exists(key: 'dashboardFooterMode', array: $data);
		$htmlProvided = array_key_exists(key: 'dashboardFooterHtml', array: $data);

		if ($modeProvided === false && $htmlProvided === false) {
			return;
		}

		$newMode = $this->resolveFooterMode(
			dashboard: $dashboard,
			data: $data,
			modeProvided: $modeProvided
		);

		if ($newMode === Dashboard::FOOTER_MODE_CUSTOM) {
			$sanitised = $this->structure->footerService->sanitiseHtml(
				html: $this->resolveCustomFooterHtml(
					dashboard: $dashboard,
					data: $data,
					htmlProvided: $htmlProvided
				)
			);
			$dashboard->setDashboardFooterMode(Dashboard::FOOTER_MODE_CUSTOM);
			// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
			$dashboard->setDashboardFooterHtml($sanitised);
			return;
		}

		// Inherit / hidden — clear stale HTML to keep the invariant.
		$dashboard->setDashboardFooterMode($newMode);
		// phpcs:ignore CustomSniffs.Functions.NamedParameters.RequireNamedParameters
		$dashboard->setDashboardFooterHtml(null);
	}//end applyFooterUpdates()

	/**
	 * Resolve and validate the footer mode an update is asking for.
	 *
	 * Falls back to the dashboard's current mode when the payload does
	 * not carry one, and normalises NULL/empty to
	 * {@see Dashboard::FOOTER_MODE_INHERIT}.
	 *
	 * @param Dashboard $dashboard The dashboard being updated.
	 * @param array<string, mixed> $data The update payload.
	 * @param bool $modeProvided Whether the payload carried a mode.
	 *
	 * @return string The validated footer mode.
	 *
	 * @throws InvalidArgumentException When the mode is not in the enum.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	private function resolveFooterMode(
		Dashboard $dashboard,
		array $data,
		bool $modeProvided,
	): string {
		$newMode = $dashboard->getDashboardFooterMode();
		if ($modeProvided === true) {
			$newMode = $data['dashboardFooterMode'];
		}

		return self::validFooterMode(mode: $newMode);
	}//end resolveFooterMode()

	/**
	 * A footer mode with null / empty read as `inherit`, refused unless it
	 * is one of the known modes.
	 *
	 * @param mixed $mode The candidate mode.
	 *
	 * @return string The mode.
	 *
	 * @throws InvalidArgumentException When the mode is unknown.
	 */
	private static function validFooterMode(mixed $mode): string {
		if ($mode === null || $mode === '') {
			$mode = Dashboard::FOOTER_MODE_INHERIT;
		}

		if (is_string($mode) === false
			|| in_array(needle: $mode, haystack: Dashboard::FOOTER_MODES, strict: true) === false
		) {
			throw new InvalidArgumentException(
				message: 'dashboardFooterMode must be one of: ' . implode(separator: ', ', array: Dashboard::FOOTER_MODES)
			);
		}

		return $mode;
	}//end validFooterMode()

	/**
	 * Resolve the raw custom-footer HTML an update is asking to store.
	 *
	 * Falls back to the dashboard's current HTML when the payload does
	 * not carry any, and rejects an absent/blank value — a `custom`
	 * footer with no body is not a meaningful state.
	 *
	 * @param Dashboard $dashboard The dashboard being updated.
	 * @param array<string, mixed> $data The update payload.
	 * @param bool $htmlProvided Whether the payload carried HTML.
	 *
	 * @return string The raw (unsanitised) footer HTML.
	 *
	 * @throws InvalidArgumentException When no usable HTML is available.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	private function resolveCustomFooterHtml(
		Dashboard $dashboard,
		array $data,
		bool $htmlProvided,
	): string {
		$rawHtml = $dashboard->getDashboardFooterHtml();
		if ($htmlProvided === true) {
			$rawHtml = $data['dashboardFooterHtml'];
		}

		if ($rawHtml === null || is_string($rawHtml) === false || trim(string: $rawHtml) === '') {
			throw new InvalidArgumentException(
				message: 'dashboardFooterHtml is required when dashboardFooterMode=custom'
			);
		}

		return $rawHtml;
	}//end resolveCustomFooterHtml()

	/**
	 * Resolve the effective footer payload for a dashboard
	 * (REQ-FTR-006 — see {@see FooterService::resolveFooterForDashboard()}).
	 *
	 * Thin pass-through so callers (controllers, listeners, tests) can
	 * stay on the `DashboardService` surface.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 *
	 * @return array|null The effective footer payload, or NULL.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	public function resolveFooterForDashboard(Dashboard $dashboard): ?array {
		return $this->structure->footerService->resolveFooterForDashboard(dashboard: $dashboard);
	}//end resolveFooterForDashboard()

	/**
	 * Apply hierarchy updates (parentUuid, slug, sortOrder) with the
	 * REQ-DASH-023..029 guards.
	 *
	 * The three keys are decoupled — callers can patch any subset:
	 *  - `parentUuid` (NULL or string) — re-parents the dashboard, runs
	 *    cycle and depth checks via {@see DashboardTreeService}.
	 *  - `slug` (string) — updates the slug, runs the per-parent
	 *    uniqueness guard against the resolved parent (post-update).
	 *  - `sortOrder` (int) — updates the sibling sort order verbatim.
	 *
	 * Slug uniqueness is rechecked against the dashboard's parent AFTER
	 * any pending `parentUuid` change so a single PUT can re-parent and
	 * rename in one atomic patch.
	 *
	 * @param Dashboard $dashboard The dashboard being updated.
	 * @param array $data The update payload.
	 *
	 * @return void
	 */
	private function applyTreeUpdates(
		Dashboard $dashboard,
		array $data,
	): void {
		if (array_key_exists(key: 'parentUuid', array: $data) === true) {
			$this->applyParentUpdate(dashboard: $dashboard, newParent: $data['parentUuid']);
		}

		// The slug uniqueness check fires against the parent as it stands
		// after any re-parenting above.
		if (array_key_exists(key: 'slug', array: $data) === true) {
			$this->applySlugUpdate(dashboard: $dashboard, newSlug: $data['slug']);
		}

		if (array_key_exists(key: 'sortOrder', array: $data) === true) {
			$sortOrder = $data['sortOrder'];
			$dashboard->setSortOrder((int)$sortOrder);
		}
	}//end applyTreeUpdates()

	/**
	 * Re-parent the dashboard after the REQ-DASH-028 cycle + depth check.
	 *
	 * @param Dashboard $dashboard The dashboard being updated.
	 * @param mixed     $newParent The requested parent UUID (NULL for root).
	 *
	 * @return void
	 */
	private function applyParentUpdate(Dashboard $dashboard, mixed $newParent): void {
		$newParent = $this->stringOrNull(value: $newParent);

		$this->structure->treeService->validateParent(
			movingUuid: $dashboard->getUuid(),
			newParentUuid: $newParent
		);

		$dashboard->setParentUuid($newParent);
	}//end applyParentUpdate()

	/**
	 * Set the slug; a non-empty one must be well-formed and unique under the dashboard's parent.
	 *
	 * @param Dashboard $dashboard The dashboard being updated.
	 * @param mixed     $newSlug   The requested slug.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the slug is malformed or taken.
	 */
	private function applySlugUpdate(Dashboard $dashboard, mixed $newSlug): void {
		$newSlug = $this->stringOrNull(value: $newSlug);
		if ($newSlug !== null && $newSlug !== '') {
			$this->assertSlugAllowed(dashboard: $dashboard, slug: $newSlug);
		}

		$dashboard->setSlug($newSlug);
	}//end applySlugUpdate()

	/**
	 * Refuse a malformed slug, then one a sibling already owns.
	 *
	 * @param Dashboard $dashboard The dashboard being updated.
	 * @param string    $slug      The non-empty requested slug.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException When the slug is malformed or taken.
	 */
	private function assertSlugAllowed(Dashboard $dashboard, string $slug): void {
		if (SlugGenerator::isValid(slug: $slug) === false) {
			throw new InvalidArgumentException(
				message: 'Slug must match [a-z0-9_-]+ and be at most 128 characters'
			);
		}

		// REQ-DASH-024: per-parent uniqueness — exclude the row
		// being updated so no-op writes succeed.
		$this->structure->treeService->validateSlugUnique(
			parentUuid: $dashboard->getParentUuid(),
			slug: $slug,
			excludeUuid: $dashboard->getUuid()
		);
	}//end assertSlugAllowed()

	/**
	 * The value as a string, NULL kept as NULL.
	 *
	 * @param mixed $value A payload value.
	 *
	 * @return string|null The cast value.
	 */
	private function stringOrNull(mixed $value): string|null {
		if ($value !== null && is_string($value) === false) {
			return (string)$value;
		}

		return $value;
	}//end stringOrNull()

	/**
	 * Assert the actor is allowed to mutate the dashboard's publication
	 * state. REQ-DASH-031..034.
	 *
	 * Owner-or-admin is the gate: the dashboard's `userId` must match
	 * the actor, OR the actor must be a Nextcloud admin. Group-shared
	 * and admin-template dashboards (which have `userId = null`) fall
	 * back to the admin-only check.
	 *
	 * @param Dashboard $dashboard The dashboard being mutated.
	 * @param string $actorUserId The acting user ID.
	 *
	 * @return void
	 *
	 * @throws Exception When the actor is neither owner nor admin.
	 */
	private function assertOwnerOrAdmin(
		Dashboard $dashboard,
		string $actorUserId,
	): void {
		$ownerId = $dashboard->getUserId();
		if ($ownerId !== null && $ownerId === $actorUserId) {
			return;
		}

		if ($this->isAdmin(userId: $actorUserId) === true) {
			return;
		}

		throw new Exception(
			message: self::ERR_FORBIDDEN_NOT_OWNER_OR_ADMIN
		);
	}//end assertOwnerOrAdmin()

	/**
	 * Whether a dashboard's take-down time has passed.
	 *
	 * @param Dashboard $dashboard The dashboard.
	 * @param DateTime $now The moment of the read.
	 *
	 * @return bool True when `unpublishAt` is set and not in the future.
	 *
	 * @spec openspec/specs/dashboards/spec.md
	 */
	private function isTakenDown(Dashboard $dashboard, DateTime $now): bool {
		$unpublishAt = $dashboard->getUnpublishAt();
		if ($unpublishAt === null || $unpublishAt === '') {
			return false;
		}

		try {
			return new DateTime($unpublishAt) <= $now;
		} catch (Exception) {
			return false;
		}
	}//end isTakenDown()

	/**
	 * Parse and validate a `publishAt` argument for the schedule action.
	 * REQ-DASH-034.
	 *
	 * Accepts any string `DateTime::__construct` understands (ISO-8601
	 * recommended). The parsed value is normalised to the canonical
	 * `Y-m-d H:i:s` storage format used elsewhere on the entity.
	 *
	 * @param string $publishAt The caller-supplied publish-at string.
	 *
	 * @return string The normalised storage timestamp.
	 *
	 * @throws InvalidArgumentException When the string is empty,
	 *                                  unparseable, or not strictly in
	 *                                  the future.
	 */
	private function parseFuturePublishAt(string $publishAt): string {
		$trimmed = trim($publishAt);
		if ($trimmed === '') {
			throw new InvalidArgumentException(
				message: self::ERR_SCHEDULE_PAST_DATE
			);
		}

		try {
			$parsed = new DateTime($trimmed);
		} catch (Exception) {
			throw new InvalidArgumentException(
				message: self::ERR_SCHEDULE_PAST_DATE
			);
		}

		$now = new DateTime();
		if ($parsed <= $now) {
			throw new InvalidArgumentException(
				message: self::ERR_SCHEDULE_PAST_DATE
			);
		}

		return $parsed->format(format: 'Y-m-d H:i:s');
	}//end parseFuturePublishAt()
}//end class
