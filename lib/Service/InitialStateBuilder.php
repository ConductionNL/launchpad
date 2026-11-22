<?php

/**
 * InitialStateBuilder
 *
 * Centralises the per-page initial-state contract for LaunchPad. This is the
 * only class allowed to call {@see \OCP\AppFramework\Services\IInitialState::provideInitialState()};
 * a CI grep lint enforces that against `lib/Controller/` and `lib/Settings/`.
 *
 * Construct one builder per render with the destination page, call the
 * typed setters, then `apply()`. `apply()` validates that every required
 * key for the chosen page has been set, throwing
 * {@see \OCA\LaunchPad\Exception\MissingInitialStateException} when any are
 * missing — the page never renders with a partial payload.
 *
 * The contract version is stamped onto every payload under the key
 * `_schemaVersion`. Bumping {@see self::INITIAL_STATE_SCHEMA_VERSION}
 * MUST accompany any change to the per-page key sets, and MUST be paired
 * with the matching constant in `src/utils/loadInitialState.js`. See
 * REQ-INIT-002 for the rules.
 *
 * Workspace page (`#launchpad-app`) keys:
 *  - widgets             — array of dashboard widget descriptors
 *  - layout              — array of WidgetPlacement rows for the active dashboard
 *  - primaryGroup        — group id of the user's resolved primary group
 *  - primaryGroupName    — human-readable display name for the primary group
 *  - isAdmin             — boolean, true when the user is in the admin group
 *  - activeDashboardId   — id of the currently active dashboard for the user
 *  - dashboardSource     — 'user' | 'group' | 'default' (drives canEdit)
 *  - groupDashboards     — list of group-scope dashboards visible to the user
 *  - userDashboards      — list of user-scope (personal) dashboards
 *  - allowUserDashboards — admin flag toggling the personal-dashboards UI
 *
 * Admin page (`#launchpad-admin-settings`) keys:
 *  - allGroups                — every Nextcloud group, for the ordering UI
 *  - configuredGroups         — group ids the admin has explicitly ordered
 *  - widgets                  — every available dashboard widget descriptor
 *  - allowUserDashboards      — current value of the personal-dashboards admin flag
 *  - linkCreateFileExtensions — admin-configurable allow-list for the
 *                                link-button-widget createFile flow (REQ-LBN-004)
 *
 * Public start page (`#public-share-vue` at `/start`) keys, version 3:
 *  - publicStartToken — token of the public link the start page shows
 *                       (sharing-public-start-page D3)
 *
 * Both payloads always carry `_schemaVersion`.
 *
 * @category  Service
 * @package   OCA\LaunchPad\Service
 * @author    Conduction b.v. <info@conduction.nl>
 * @copyright 2026 Conduction b.v.
 * @license   EUPL-1.2 https://joinup.ec.europa.eu/collection/eupl/eupl-text-eupl-12
 * @version   GIT:auto
 * @link      https://conduction.nl
 *
 * @link https://conduction.nl/openspec/initial-state-contract REQ-INIT-002
 *
 * SPDX-FileCopyrightText: 2024 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 */

declare(strict_types=1);

namespace OCA\LaunchPad\Service;

use JsonSerializable;
use OCA\LaunchPad\Exception\MissingInitialStateException;
use OCA\LaunchPad\Service\InitialState\Page;
use OCP\AppFramework\Services\IInitialState;

/**
 * Typed builder for the per-page initial-state payload (REQ-INIT-001, REQ-INIT-002).
 */
class InitialStateBuilder {
	/**
	 * Schema version stamped onto every payload under `_schemaVersion`.
	 *
	 * Bump in the same commit that changes any per-page key set. The JS
	 * reader in `src/utils/loadInitialState.js` MUST keep its constant in
	 * lockstep — version drift surfaces as a console warning at runtime
	 * (REQ-INIT-002).
	 *
	 * @var integer
	 */
	public const INITIAL_STATE_SCHEMA_VERSION = 3;

	/**
	 * Reserved payload key carrying the schema version.
	 *
	 * @var string
	 */
	public const KEY_SCHEMA_VERSION = '_schemaVersion';

	/**
	 * Required key set per page. Keys MUST exactly match the spec's Data
	 * Model — adding or removing a key is a spec change (REQ-INIT-002).
	 *
	 * @var array<string, list<string>>
	 */
	private const REQUIRED_KEYS = [
		Page::WORKSPACE->value => [
			'widgets',
			'layout',
			'primaryGroup',
			'primaryGroupName',
			'isAdmin',
			'activeDashboardId',
			'dashboardSource',
			'groupDashboards',
			'userDashboards',
			'allowUserDashboards',
			// REQ-RFP-010: list of widget IDs the caller is permitted to see.
			// null = no restriction configured (backwards-compat).
			'allowedWidgets',
		],
		Page::ADMIN->value => [
			'allGroups',
			'configuredGroups',
			'widgets',
			'allowUserDashboards',
			'linkCreateFileExtensions',
		],
		// Sharing-public-start-page D3: the link the start page shows.
		Page::PUBLIC_START->value => [
			'publicStartToken',
		],
	];

	/**
	 * Buffered key/value pairs awaiting apply().
	 *
	 * @var array<string, mixed>
	 */
	private array $values = [];

	/**
	 * Constructor.
	 *
	 * @param IInitialState $initialState The Nextcloud initial-state service.
	 * @param Page $page Destination page.
	 */
	public function __construct(
		private readonly IInitialState $initialState,
		private readonly Page $page,
	) {
	}//end __construct()

	/**
	 * Set the dashboard widgets list (workspace + admin).
	 *
	 * @param array $widgets Widget descriptors `[{id, title, iconClass, iconUrl, url}, ...]`.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/specs/initial-state-contract/spec.md
	 */
	public function setWidgets(array $widgets): self {
		$this->values['widgets'] = $widgets;
		return $this;
	}//end setWidgets()

	/**
	 * Set the active-dashboard layout (workspace).
	 *
	 * @param array $layout WidgetPlacement rows for the active dashboard.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/specs/initial-state-contract/spec.md
	 */
	public function setLayout(array $layout): self {
		$this->values['layout'] = $layout;
		return $this;
	}//end setLayout()

	/**
	 * Set the primary group id (workspace).
	 *
	 * @param string $primaryGroup Resolved primary group id (e.g. 'default').
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/specs/initial-state-contract/spec.md
	 */
	public function setPrimaryGroup(string $primaryGroup): self {
		$this->values['primaryGroup'] = $primaryGroup;
		return $this;
	}//end setPrimaryGroup()

	/**
	 * Set the primary group display name (workspace).
	 *
	 * @param string $primaryGroupName Human-readable primary group name.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/specs/initial-state-contract/spec.md
	 */
	public function setPrimaryGroupName(string $primaryGroupName): self {
		$this->values['primaryGroupName'] = $primaryGroupName;
		return $this;
	}//end setPrimaryGroupName()

	/**
	 * Set the is-admin flag (workspace).
	 *
	 * @param bool $isAdmin True when the user belongs to the admin group.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/specs/initial-state-contract/spec.md
	 */
	public function setIsAdmin(bool $isAdmin): self {
		$this->values['isAdmin'] = $isAdmin;
		return $this;
	}//end setIsAdmin()

	/**
	 * Set the active dashboard id (workspace).
	 *
	 * @param string $activeDashboardId Id of the currently active dashboard.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/specs/initial-state-contract/spec.md
	 */
	public function setActiveDashboardId(string $activeDashboardId): self {
		$this->values['activeDashboardId'] = $activeDashboardId;
		return $this;
	}//end setActiveDashboardId()

	/**
	 * Set the dashboard source (workspace).
	 *
	 * Valid values: 'user' | 'group' | 'default'. Drives canEdit on the
	 * runtime shell (REQ-RTS-006).
	 *
	 * @param string $dashboardSource One of 'user', 'group', 'default'.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/specs/initial-state-contract/spec.md
	 */
	public function setDashboardSource(string $dashboardSource): self {
		$this->values['dashboardSource'] = $dashboardSource;
		return $this;
	}//end setDashboardSource()

	/**
	 * Set the visible group dashboards (workspace).
	 *
	 * @param array $groupDashboards List of group-scope dashboards visible to the user.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/specs/initial-state-contract/spec.md
	 */
	public function setGroupDashboards(array $groupDashboards): self {
		$this->values['groupDashboards'] = $groupDashboards;
		return $this;
	}//end setGroupDashboards()

	/**
	 * Set the user (personal) dashboards (workspace).
	 *
	 * @param array $userDashboards List of personal dashboards owned by the user.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/specs/initial-state-contract/spec.md
	 */
	public function setUserDashboards(array $userDashboards): self {
		$this->values['userDashboards'] = $userDashboards;
		return $this;
	}//end setUserDashboards()

	/**
	 * Set the allow-user-dashboards flag (workspace + admin).
	 *
	 * @param bool $allowUserDashboards Current value of the admin flag.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/specs/initial-state-contract/spec.md
	 */
	public function setAllowUserDashboards(bool $allowUserDashboards): self {
		$this->values['allowUserDashboards'] = $allowUserDashboards;
		return $this;
	}//end setAllowUserDashboards()

	/**
	 * Set the list of widget IDs the caller is permitted to see (workspace).
	 * `null` means no restriction is configured — legacy behaviour where the
	 * full widget catalogue is shown (REQ-RFP-009 / REQ-RFP-010).
	 *
	 * @param array|null $allowedWidgets List of widget IDs, or null.
	 *
	 * @return self
	 *
	 * @spec openspec/specs/initial-state-contract/spec.md
	 */
	public function setAllowedWidgets(?array $allowedWidgets): self {
		$this->values['allowedWidgets'] = $allowedWidgets;
		return $this;
	}//end setAllowedWidgets()

	/**
	 * Set the canonical slug-chain path for the active dashboard
	 * (workspace).
	 *
	 * Read by the frontend on mount to bring `window.location.pathname`
	 * in line with whichever dashboard was actually rendered — handles
	 * the renamed-parent / stale-bookmark cases by replacing the URL
	 * in-place via `history.replaceState`. Empty string when no
	 * dashboard is active (the page renders the empty state).
	 *
	 * @param string $deepLinkPath Canonical path or '' when none.
	 *
	 * @return self
	 *
	 * @spec openspec/specs/initial-state-contract/spec.md
	 */
	public function setDeepLinkPath(string $deepLinkPath): self {
		$this->values['deepLinkPath'] = $deepLinkPath;
		return $this;
	}//end setDeepLinkPath()

	/**
	 * Set the admin-configured quick-search no-match fallback target
	 * (workspace). `'none'` | `'unified-search'` | a validated `https`
	 * URL template containing `{query}`.
	 *
	 * Optional key — mirrors {@see self::setDeepLinkPath()}'s pattern:
	 * NOT in {@see self::REQUIRED_KEYS}, so an older deploy that hasn't
	 * called this setter yet still passes {@see self::apply()}'s
	 * required-key check, and the JS reader's `'none'` default keeps the
	 * frontend typed either way (tile-quick-search REQ-QSEARCH-004).
	 *
	 * @param string $fallbackTarget The current fallback-target setting value.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/specs/tile-quick-search/spec.md
	 */
	public function setQuicksearchFallbackTarget(string $fallbackTarget): self {
		$this->values['quicksearchFallbackTarget'] = $fallbackTarget;
		return $this;
	}//end setQuicksearchFallbackTarget()

	/**
	 * Set the search shortcuts every search box honours (search-ai-prefix-shortcuts,
	 * REQ-SPX-002). Optional key, like `quicksearchFallbackTarget`: the JS reader
	 * defaults it to an empty list.
	 *
	 * @param array $shortcuts Validated `{prefix, name, urlTemplate}` entries.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/specs/tile-quick-search/spec.md
	 */
	public function setSearchShortcuts(array $shortcuts): self {
		$this->values['searchShortcuts'] = array_values(array: $shortcuts);
		return $this;
	}//end setSearchShortcuts()

	/**
	 * Set the administrator CSS for all of LaunchPad (workspace).
	 *
	 * Optional key, like {@see self::setQuicksearchFallbackTarget()}. The
	 * value is the cleaned and scoped CSS the server stored; the page inserts
	 * it as the text of a style element, never as markup (REQ-THM-003).
	 *
	 * @param string $customCss The stored CSS, or '' for none.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/changes/archive/2026-11-19-platform-dashboard-theme-and-css/specs/dashboards/spec.md#requirement-administrators-add-custom-css-cleaned-and-scoped-req-thm-003
	 */
	public function setCustomCss(string $customCss): self {
		$this->values['customCss'] = $customCss;
		return $this;
	}//end setCustomCss()

	/**
	 * Push whether the organisation opted in to filter telemetry, so the
	 * dashboard's filter bus writes `filter_event` rows only when it did
	 * (drill-down-cross-widget-filter REQ-CWF-010). Optional key: the JS
	 * reader defaults to `false`.
	 *
	 * @param bool $enabled True when the administrator switched telemetry on.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-17
	 */
	public function setFilterTelemetryEnabled(bool $enabled): self {
		$this->values['filterTelemetryEnabled'] = $enabled;
		return $this;
	}//end setFilterTelemetryEnabled()

	/**
	 * Set what the tile editor offers for program and single sign-on tiles
	 * (workspace): the schemes the administrator allowed, and the key and
	 * name of each launch template. The template addresses stay on the
	 * server, which resolves an sso tile's address itself.
	 *
	 * Optional keys, like {@see self::setQuicksearchFallbackTarget()}: the
	 * JS reader defaults both to an empty list.
	 *
	 * @param array<int, string> $schemes   The allowed program schemes.
	 * @param array<int, mixed>  $templates The launch templates (`key`, `name`, `urlTemplate`).
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/changes/archive/2026-11-19-launcher-tile-launch-types/tasks.md#task-4
	 */
	public function setTileLaunchOptions(array $schemes, array $templates): self {
		$this->values['tileAllowedSchemes'] = array_values($schemes);
		$names = [];
		foreach ($templates as $template) {
			if (is_array($template) === true && isset($template['key'], $template['name']) === true) {
				$names[] = ['key' => (string)$template['key'], 'name' => (string)$template['name']];
			}
		}

		$this->values['ssoLaunchTemplates'] = $names;
		return $this;
	}//end setTileLaunchOptions()

	/**
	 * Set whether this request comes from inside the office networks.
	 *
	 * Optional key, like {@see self::setQuicksearchFallbackTarget()}: a
	 * server that never calls it leaves the JS reader's `false` default, so
	 * every tile opens its main address (launcher-tile-internal-address D1).
	 *
	 * @param bool $onOfficeNetwork True when the request address falls in an office network.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/changes/archive/2026-11-19-launcher-tile-internal-address/specs/tiles/spec.md#requirement-the-tile-opens-the-address-that-fits-the-network-req-tia-003
	 */
	public function setOnOfficeNetwork(bool $onOfficeNetwork): self {
		$this->values['onOfficeNetwork'] = $onOfficeNetwork;
		return $this;
	}//end setOnOfficeNetwork()

	/**
	 * Set whether Nextcloud Talk is enabled for the viewer (workspace).
	 *
	 * Optional key, like {@see self::setQuicksearchFallbackTarget()}: the JS
	 * reader's `false` default makes the Communities widget explain that Talk
	 * is needed instead of calling it (engagement-communities REQ-COMM-004).
	 *
	 * @param bool $talkEnabled True when Talk is enabled for the viewer.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/changes/engagement-communities/specs/communities-widget/spec.md#requirement-without-talk-the-widget-explains-itself-req-comm-004
	 */
	public function setTalkEnabled(bool $talkEnabled): self {
		$this->values['talkEnabled'] = $talkEnabled;
		return $this;
	}//end setTalkEnabled()

	/**
	 * Set which apps behind the integration widgets are enabled for the
	 * viewer, by app id. The picker leaves out a widget whose app is not.
	 * Optional key; the JS reader defaults to `{}`, which offers none of them.
	 *
	 * @param array<string, bool> $apps Whether each app is enabled.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md#requirement-the-picker-offers-the-wiki-only-where-it-can-work-req-wiki-004
	 */
	public function setIntegrationApps(array $apps): self {
		$this->values['integrationApps'] = $apps;
		return $this;
	}//end setIntegrationApps()

	/**
	 * Set the viewer's Nextcloud timezone (workspace).
	 *
	 * Optional key; the JS reader defaults to '', which makes a clock without
	 * its own zone follow the device (REQ-CLOCK-002).
	 *
	 * @param string $timezone The IANA timezone name, or ''.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/specs/clock-weather-widgets/spec.md
	 */
	public function setUserTimezone(string $timezone): self {
		$this->values['userTimezone'] = $timezone;
		return $this;
	}//end setUserTimezone()

	/**
	 * Set the Talk conversation tokens the organisation pinned (workspace).
	 *
	 * Optional key; the JS reader defaults to an empty list
	 * (engagement-communities REQ-COMM-003).
	 *
	 * @param array<int, string> $tokens The pinned tokens, in the order pinned.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/changes/engagement-communities/specs/communities-widget/spec.md#requirement-administrators-pin-official-communities-req-comm-003
	 */
	public function setPinnedCommunities(array $tokens): self {
		$this->values['pinnedCommunities'] = array_values($tokens);
		return $this;
	}//end setPinnedCommunities()

	/**
	 * Set every Nextcloud group (admin).
	 *
	 * @param array $allGroups List of `{id, displayName}` pairs.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/specs/initial-state-contract/spec.md
	 */
	public function setAllGroups(array $allGroups): self {
		$this->values['allGroups'] = $allGroups;
		return $this;
	}//end setAllGroups()

	/**
	 * Set the configured (ordered) group ids (admin).
	 *
	 * @param array $configuredGroups Ordered list of group ids.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/specs/initial-state-contract/spec.md
	 */
	public function setConfiguredGroups(array $configuredGroups): self {
		$this->values['configuredGroups'] = $configuredGroups;
		return $this;
	}//end setConfiguredGroups()

	/**
	 * Set the link-button-widget createFile extension allow-list (admin).
	 *
	 * Backed by the `link_create_file_extensions` admin setting
	 * (REQ-LBN-004). When the admin has not customised the list the
	 * caller passes the default values, so the renderer always sees
	 * a non-empty array.
	 *
	 * @param array $extensions Lowercase extensions without dots,
	 *                          e.g. `["txt","md","docx"]`.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/specs/initial-state-contract/spec.md
	 */
	public function setLinkCreateFileExtensions(array $extensions): self {
		$this->values['linkCreateFileExtensions'] = $extensions;
		return $this;
	}//end setLinkCreateFileExtensions()

	/**
	 * Set the token of the public link the start page shows (public-start page).
	 *
	 * @param string $token The public link's token.
	 *
	 * @return self Fluent.
	 *
	 * @spec openspec/changes/sharing-public-start-page/specs/dashboard-public-share/spec.md#requirement-signed-out-visitors-land-on-the-start-page-req-psp-002
	 */
	public function setPublicStartToken(string $token): self {
		$this->values['publicStartToken'] = $token;
		return $this;
	}//end setPublicStartToken()

	/**
	 * Validate required keys then push every buffered pair plus the
	 * schema version to {@see IInitialState::provideInitialState()}.
	 *
	 * @return void
	 *
	 * @throws MissingInitialStateException When any required key for the
	 *                                      page was not set.
	 *
	 * @spec openspec/specs/initial-state-contract/spec.md
	 */
	public function apply(): void {
		$required = self::REQUIRED_KEYS[$this->page->value];

		foreach ($required as $key) {
			if (array_key_exists($key, $this->values) === false) {
				throw new MissingInitialStateException(
					page: $this->page->value,
					key: $key
				);
			}
		}

		foreach ($this->values as $key => $value) {
			$this->initialState->provideInitialState($key, $value ?? self::jsonNull());
		}

		$this->initialState->provideInitialState(
			self::KEY_SCHEMA_VERSION,
			self::INITIAL_STATE_SCHEMA_VERSION
		);
	}//end apply()

	/**
	 * A value that encodes as JSON `null`. Nextcloud's initial-state service
	 * drops a plain PHP `null` with a warning, so a key whose value is
	 * `null` (e.g. `allowedWidgets` when nothing is configured, REQ-RFP-010)
	 * would never reach the page.
	 *
	 * @return JsonSerializable The JSON null.
	 *
	 * @spec openspec/specs/role-feature-permissions/spec.md
	 */
	private static function jsonNull(): JsonSerializable {
		return new class implements JsonSerializable {
			/**
			 * The JSON value.
			 *
			 * @return null Always null.
			 *
			 * @spec openspec/specs/role-feature-permissions/spec.md
			 */
			public function jsonSerialize(): mixed {
				return null;
			}//end jsonSerialize()
		};
	}//end jsonNull()
}//end class
