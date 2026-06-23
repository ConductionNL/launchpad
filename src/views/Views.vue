<template>
	<div id="launchpad-app">
		<!-- Slide-in sidebar (REQ-SWITCH-001..007). Wired with Vue 2's
		     v-model rebind (model: { prop: 'isOpen', event: 'update:open' })
		     so this template can use plain `v-model` while the sidebar
		     emits the `update:open(boolean)` event mandated by the spec.
		     Once `runtime-shell` ships and replaces this view with
		     `WorkspaceApp.vue`, the same binding shape applies. -->
		<DashboardSwitcherSidebar
			v-model="sidebarOpen"
			:group-name="primaryGroupName"
			:group-dashboards="sidebarGroupDashboards"
			:user-dashboards="sidebarUserDashboards"
			:active-dashboard-id="activeDashboard?.id"
			:allow-user-dashboards="allowUserDashboards"
			:can-edit="canEdit"
			:default-uuid="defaultDashboardUuid"
			:dashboard-quota-reached="dashboardQuotaReached"
			:dashboard-quota-tooltip="dashboardQuotaTooltip"
			:is-edit-mode="isEditMode"
			@switch="onSidebarSwitch"
			@create-dashboard="onSidebarCreateDashboard"
			@delete-dashboard="onSidebarDeleteDashboard"
			@toggle-edit="onRowToggleEdit"
			@open-config="onRowOpenConfig"
			@add-custom-widget="onRowAddCustomWidget"
			@set-default="onRowSetDefault" />
		<SidebarBackdrop
			v-if="sidebarOpen"
			@close="sidebarOpen = false" />

		<!-- Floating controls in top right.
		     Wave3.3 removed the floating `DashboardConfigMenu` (cog) — its
		     entries (Edit / Configure / Add widget / Delete) now live in
		     the left sidebar's header NcActions menu so the per-dashboard
		     destructive + edit actions sit alongside switching. The
		     hamburger keeps its place (top-right entry point when the
		     sidebar is closed). -->
		<div class="launchpad-floating-controls">
			<!-- Active-dashboard cog: the same per-dashboard action menu that
			     sits on every sidebar row (DashboardRowActions), but bound to
			     the currently active dashboard so the user can Edit / Configure
			     / Add widget / Set default / Delete it directly from the
			     top-right cluster without opening the sidebar first. Reuses the
			     existing onRow* handlers; `maybeSwitchTo` is a no-op because the
			     target is already active. -->
			<!-- Active-dashboard cog. Styled `secondary` with a 20px icon so
			     it matches the adjacent dashboards (hamburger) button, and
			     carries the Share action (dashboard-sharing spec) in-menu
			     rather than as a standalone top-bar button. -->
			<DashboardRowActions
				v-if="activeDashboard"
				:dashboard="activeDashboard"
				:source="activeDashboardSource"
				:can-edit="canEdit"
				:can-share="canShareActiveDashboard"
				:default-uuid="defaultDashboardUuid"
				:is-edit-mode="isEditMode"
				:active-dashboard-id="activeDashboard.id"
				button-type="secondary"
				:icon-size="20"
				class="launchpad-active-dashboard-cog"
				@toggle-edit="onRowToggleEdit(activeDashboard, activeDashboardSource)"
				@open-config="onRowOpenConfig(activeDashboard, activeDashboardSource)"
				@add-custom-widget="onRowAddCustomWidget(activeDashboard, activeDashboardSource)"
				@set-default="onRowSetDefault(activeDashboard, activeDashboardSource)"
				@share="openShareDrawer"
				@delete="onSidebarDeleteDashboard(activeDashboard.id)" />
			<NcButton
				type="secondary"
				:aria-label="t('launchpad', 'Dashboards')"
				class="launchpad-sidebar-toggle"
				@click="sidebarOpen = !sidebarOpen">
				<template #icon>
					<MenuIcon :size="20" />
				</template>
			</NcButton>
			<!-- Primary-group label (REQ-TMPL-012) is suppressed for the
			     `default` sentinel — REQ-TMPL-012 documents the literal
			     'Default' as the absence of a configured primary group, so
			     surfacing it adds noise without information. The label
			     remains visible whenever a real Nextcloud group display
			     name is resolved. -->
			<div
				v-if="primaryGroupLabel && primaryGroupLabel !== 'Default'"
				class="launchpad-primary-group-label"
				:title="t('launchpad', 'Your primary group for shared dashboards')">
				{{ primaryGroupLabel }}
			</div>
		</div>

		<!-- Main dashboard grid -->
		<div class="launchpad-container" :class="{ 'launchpad-edit-mode': isEditMode }">
			<CnDashboardGrid
				v-if="activeDashboard"
				:layout="widgetPlacements"
				:editable="isEditMode"
				:columns="activeDashboard.gridColumns || 12"
				:cell-height="60"
				:margin="8"
				:column-opts="dashboardColumnOpts"
				cell-height-css-var="--launchpad-cell-height"
				:item-key="placementItemKey"
				@layout-change="updatePlacements">
				<template #widget="{ item }">
					<div class="launchpad-grid-item" @contextmenu="onWidgetRightClick($event, item)">
						<!-- Tile placements render the launcher tile directly. -->
						<TileWidget
							v-if="isTilePlacement(item)"
							:tile="getTileData(item)"
							:edit-mode="isEditMode"
							@edit="openTileEditorForEdit(item)"
							@remove="removeWidget(item.id)" />
						<!-- All other placements render through the widget wrapper. -->
						<WidgetWrapper
							v-else
							:placement="item"
							:widget="getWidget(item.widgetId)"
							:edit-mode="isEditMode"
							@remove="removeWidget(item.id)"
							@style="openStyleEditor(item)"
							@edit="handleContextMenuEdit(item)" />
					</div>
				</template>
			</CnDashboardGrid>

			<!-- Loading shim. The empty-state below previously rendered
			     during the initial fetch because `activeDashboard` is
			     null until `loadDashboards()` resolves; the spinner
			     keeps the "No dashboard yet" CTA out of view while the
			     user is just waiting on data. -->
			<div v-else-if="loading" class="launchpad-loading">
				<NcLoadingIcon :size="48" />
			</div>

			<!-- Empty-state shell. The "Create dashboard" affordance is gated
			     by the admin `allow_user_dashboards` flag (REQ-ASET-003,
			     extended). When the flag is off the button MUST be hidden
			     and the description swapped for a localised explainer so
			     the workspace never offers an action that would 403. -->
			<div v-else class="launchpad-empty">
				<NcEmptyContent
					:name="t('launchpad', 'No dashboard yet')"
					:description="emptyStateDescription">
					<template #icon>
						<ViewDashboard :size="64" />
					</template>
					<template v-if="allowUserDashboards" #action>
						<NcButton type="primary" @click="handleCreateDashboard">
							{{ t('launchpad', 'Create dashboard') }}
						</NcButton>
					</template>
				</NcEmptyContent>
			</div>
		</div>

		<!-- Widget picker modal -->
		<WidgetPickerModal
			:open="isWidgetModalOpen"
			:widgets="availableWidgets"
			:placed-widget-ids="placedWidgetIds"
			@close="closeWidgetModal"
			@add="addWidget" />

		<!-- Custom widget add/edit modal — registry-driven host for label,
		     text, image, link-button, etc. (REQ-WDG-010..014). The modal does
		     no API calls itself; this view persists the emitted payload. -->
		<CnAddWidgetModal
			:show="isCustomWidgetModalOpen"
			:preselected-type="customWidgetPreselectedType"
			:editing-widget="customWidgetEditing"
			:upload-fn="iconUploadFn"
			:calendars-fetcher="fetchCalendars"
			@close="closeCustomWidgetModal"
			@submit="saveCustomWidget" />

		<!-- Dashboard configuration modal (also used for creating a new dashboard) -->
		<DashboardConfigModal
			:open="isConfigModalOpen"
			:dashboard="configModalMode === 'create' ? null : activeDashboard"
			:mode="configModalMode"
			:can-delete="dashboards.length > 1"
			:default-uuid="defaultDashboardUuid"
			:initial-tab="configModalInitialTab"
			@close="closeConfigModal"
			@save="saveDashboardConfig"
			@delete="deleteCurrentDashboard"
			@set-default="onModalSetDefault" />

		<!-- Style editor modal -->
		<CnWidgetStyleEditorModal
			v-if="editingPlacement"
			:show="isStyleEditorOpen"
			:widget="styleEditorWidget"
			:deletable="!editingPlacement.isCompulsory"
			:extra-icon-options="nlDesignIconOptions"
			@close="closeStyleEditor"
			@save="onStyleSaved"
			@delete="deleteWidget" />

		<!-- Tile editor modal -->
		<TileEditor
			:open="isTileEditorOpen"
			:tile="editingTile"
			@close="closeTileEditor"
			@save="saveTile"
			@delete="deleteTile" />

		<!-- Widget right-click context menu (REQ-WDG-015..017). The
		     popover renders only in edit mode, anchored at the cursor.
		     Clicking Edit reuses AddWidgetModal with the placement set
		     as `editingWidget`; Remove calls the placement-delete path
		     of REQ-WDG-005; Cancel is a no-op close. -->
		<WidgetContextMenu
			v-if="contextMenuVisible"
			:top="contextMenuTop"
			:left="contextMenuLeft"
			@edit="grid.triggerEdit()"
			@remove="grid.triggerRemove()"
			@visibility-rules="grid.triggerVisibilityRules()"
			@close="grid.closeContextMenu()" />

		<!-- Per-widget conditional-visibility editor (conditional-visibility
		     spec). Opened from the context menu's "Visibility rules…" item;
		     gated on `canEdit` so only dashboard owners reach it. -->
		<VisibilityRulesModal
			:open="isVisibilityModalOpen"
			:placement-id="visibilityPlacementId"
			@close="closeVisibilityRules"
			@rule-added="onVisibilityRulesChanged"
			@rule-removed="onVisibilityRulesChanged" />
	</div>
</template>

<script>
import Vue from 'vue'
import { mapState, mapActions } from 'pinia'
import { NcButton, NcEmptyContent, NcLoadingIcon, CnDashboardGrid, CnWidgetStyleEditorModal, CnAddWidgetModal, getDashboardColumnOpts } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { generateUrl, imagePath } from '@nextcloud/router'

// Icons
import ViewDashboard from 'vue-material-design-icons/ViewDashboard.vue'
import MenuIcon from 'vue-material-design-icons/Menu.vue'

// Components
import WidgetWrapper from '../components/WidgetWrapper.vue'
import TileWidget from '../components/TileWidget.vue'
import WidgetPickerModal from '../components/WidgetPickerModal.vue'
import TileEditor from '../components/TileEditor.vue'
import DashboardConfigModal from '../components/DashboardConfigModal.vue'
import WidgetContextMenu from '../components/Widgets/WidgetContextMenu.vue'
import { uploadDataUrl } from '../services/resourceService.js'
import VisibilityRulesModal from '../components/Widgets/VisibilityRulesModal.vue'
import { getWidgetTypeEntry } from '../constants/widgetRegistry.js'
import DashboardSwitcherSidebar from '../components/Workspace/DashboardSwitcherSidebar.vue'
import DashboardRowActions from '../components/Workspace/DashboardRowActions.vue'
import SidebarBackdrop from '../components/Workspace/SidebarBackdrop.vue'

// Stores
import { useDashboardStore } from '../stores/dashboard.js'
import { useWidgetStore } from '../stores/widgets.js'
import { useTileStore } from '../stores/tiles.js'
import { api } from '../services/api.js'

// Composables
import { useGridManager } from '../composables/useGridManager.js'

export default {
	name: 'Views',
	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		ViewDashboard,
		MenuIcon,
		CnDashboardGrid,
		WidgetWrapper,
		TileWidget,
		WidgetPickerModal,
		CnWidgetStyleEditorModal,
		TileEditor,
		DashboardConfigModal,
		CnAddWidgetModal,
		WidgetContextMenu,
		VisibilityRulesModal,
		DashboardSwitcherSidebar,
		DashboardRowActions,
		SidebarBackdrop,
	},
	// REQ-INIT-004 / REQ-ASET-003 / REQ-TMPL-012: pull typed initial-state
	// values down the tree. Defaults keep the UX safe when keys are missing.
	inject: {
		allowUserDashboards: {
			from: 'allowUserDashboards',
			default: false,
		},
		primaryGroup: {
			from: 'primaryGroup',
			default: 'default',
		},
		primaryGroupName: {
			from: 'primaryGroupName',
			default: '',
		},
		// Canonical slug-chain path the server resolved for the active
		// dashboard. Empty string when no dashboard is active OR the
		// active one has no slug. Read once on mount to bring
		// `window.location.pathname` in line with what the server
		// rendered (handles renamed parents / stale bookmarks).
		injectedDeepLinkPath: {
			from: 'deepLinkPath',
			default: '',
		},
	},
	// Inject the typed initial-state snapshot pushed from `src/main.js`
	// (REQ-INIT-003..005). Defaults match the reader contract so the
	// sidebar still mounts when running under tests that don't set a
	// provider (e.g. Vitest harness) — see DashboardSwitcherSidebar specs.
	// (Inject keys are defined once below; this comment block was kept
	// as the historical anchor for the provide/inject contract.)
	/** @spec openspec/specs/dashboards/spec.md */
	setup() {
		// Reactive `canEdit` proxy handed to the grid manager composable.
		// Wrapped in Vue.observable so the composable's
		// `onWidgetRightClick` early-return tracks the live value without
		// re-creating the composable on every edit-mode toggle. When the
		// runtime-shell capability ships, this will be replaced by the
		// typed provide/inject contract and removed from local state.
		const canEditRef = Vue.observable({ value: false })

		// `selectedWidget` from the popover may live in either of two
		// edit paths. The host-side callbacks resolve which one to use
		// (custom-type widgets → AddWidgetModal; nextcloud-widget tiles →
		// CnWidgetStyleEditorModal) and the placement-delete path is the same
		// `removeWidgetFromDashboard` action used by the existing remove
		// flow. The host wires these via methods after instantiation so
		// `this` is bound to the component when the callbacks fire.
		const grid = useGridManager({
			canEdit: canEditRef,
			/** @spec openspec/specs/dashboards/spec.md */
			onEdit(widget) {
				// `this` is the Vue instance once we bind in `created()`.
				grid._host?.handleContextMenuEdit(widget)
			},
			/** @spec openspec/specs/dashboards/spec.md */
			onRemove(widget) {
				grid._host?.handleContextMenuRemove(widget)
			},
			/** @spec openspec/specs/conditional-visibility/spec.md */
			onVisibilityRules(widget) {
				grid._host?.handleContextMenuVisibilityRules(widget)
			},
		})

		return { canEditRef, grid }
	},
	data() {
		return {
			isEditMode: false,
			isWidgetModalOpen: false,
			isConfigModalOpen: false,
			configModalMode: 'edit',
			// Tab the config drawer lands on when opened (dashboard-sharing
			// spec). The top-bar share action sets this to 'sharing'.
			configModalInitialTab: 'general',
			isStyleEditorOpen: false,
			editingPlacement: null,
			// Deep clone of editingPlacement handed to CnWidgetStyleEditorModal,
			// which mutates its `widget` prop in place on Save. Editing the clone
			// keeps the live store placement untouched until the patch persists.
			styleEditorWidget: null,
			isTileEditorOpen: false,
			editingTile: null,
			// Custom widget add/edit modal state. `customWidgetEditing`
			// non-null = edit mode; `customWidgetPreselectedType` non-null =
			// type-specific deep-link from the toolbar.
			isCustomWidgetModalOpen: false,
			customWidgetPreselectedType: null,
			customWidgetEditing: null,
			// Per-widget conditional-visibility editor state. Non-null
			// `visibilityPlacementId` + `isVisibilityModalOpen` opens the
			// modal for that placement (conditional-visibility spec).
			isVisibilityModalOpen: false,
			visibilityPlacementId: null,
			// `dashboard-switcher` capability state — controlled here, the
			// sidebar emits update:open(boolean) via its v-model rebind.
			sidebarOpen: false,
			// REQ-ANLT-011 — per-uuid debounce ledger. Maps dashboard
			// UUID → last-send millisecond timestamp so two near-
			// simultaneous mounts of the same dashboard (multi-tab,
			// fast-tab-switch) collapse into a single view-event POST.
			// Different uuids are tracked independently per spec
			// scenario "Different dashboards are not debounced".
			viewEventLastSent: Object.create(null),

			// Wave3.7 — UUID of the user's pinned default dashboard, or
			// '' when no pin is set. Fetched once on mount via
			// `GET /api/dashboards/default` and refreshed locally
			// whenever the user picks a new default via the cog menu.
			defaultDashboardUuid: '',
		}
	},
	computed: {
		/**
		 * Responsive breakpoint options for CnDashboardGrid (REQ-GRID-007).
		 * Built once from the shared nc-vue helper so the grid reflows its
		 * column count across viewport sizes exactly as the old local grid did.
		 *
		 * @return {object} the GridStack `columnOpts` bag.
		 */
		dashboardColumnOpts() {
			return getDashboardColumnOpts()
		},

		/**
		 * NL Design icon pack offered in the widget style editor, passed to
		 * CnWidgetStyleEditorModal's `extraIconOptions`. App-specific (served
		 * from the nldesign app), so it stays here rather than in the library.
		 *
		 * @return {Array<{id: string, label: string, icon: string}>} icon options.
		 */
		nlDesignIconOptions() {
			const names = ['Airplane', 'Bell', 'Bike', 'Building', 'Bus', 'Cake', 'Calendar', 'Camera', 'Car', 'Certificate', 'Clock', 'Cogwheel', 'Document', 'Earth', 'Euro', 'Flower', 'Folder', 'Heart', 'House', 'Image', 'LightBulb', 'Lightning', 'Mail', 'Map', 'Megaphone', 'Monument', 'Park', 'Parking', 'Person', 'Phone', 'Search', 'Star', 'Tree', 'Wallet']
			return names.map(name => ({
				id: `nl-${name.toLowerCase()}`,
				label: name,
				// imagePath resolves the app's real web root (nldesign lives in
				// custom_apps → /custom_apps/nldesign/img/...), unlike a hardcoded
				// /apps/nldesign/ path which 404s on this install.
				icon: imagePath('nldesign', `icons/${name}.svg`),
			}))
		},

		...mapState(useDashboardStore, [
			'dashboards',
			'activeDashboard',
			'widgetPlacements',
			'permissionLevel',
			'loading',
			'userDashboards',
			'groupSharedDashboards',
			'defaultGroupDashboards',
			// dashboard-quota-limits REQ-QUOTA-006: drive the disabled
			// create affordance + tooltip from the store getters.
			'dashboardQuotaReached',
			'dashboardQuotaTooltip',
		]),
		...mapState(useWidgetStore, ['availableWidgets']),
		...mapState(useTileStore, ['tiles']),

		/** @spec openspec/specs/dashboards/spec.md */
		canEdit() {
			return this.permissionLevel !== 'view_only'
		},
		/**
		 * Whether the top-bar share action should be shown (dashboard-sharing
		 * spec). Requires an active dashboard the user owns (only owners can
		 * manage shares — mirrors `DashboardConfigModal.canManageShares`).
		 *
		 * @return {boolean}
		 */
		/** @spec openspec/specs/dashboard-sharing/spec.md */
		canShareActiveDashboard() {
			const dash = this.activeDashboard
			return !!dash && dash.isOwner !== false && (dash.id ?? null) !== null
		},
		/**
		 * Whether the right-click context menu (REQ-WDG-015) should open
		 * for the current dashboard. Requires both the user permission
		 * gate and the workspace shell's edit-mode toggle. View mode
		 * intentionally falls through to the browser's native menu.
		 *
		 * @return {boolean}
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		canEditForContextMenu() {
			return this.canEdit && this.isEditMode
		},
		/*
		 * Reactive bridges to `grid.state.*` (from the `useGridManager`
		 * composable in `setup()`). Vue 2.7's template compiler does NOT
		 * track property reads on plain objects returned from `setup()` —
		 * binding `v-if="grid.state.contextMenuOpen"` directly captures
		 * the *initial* truthy/falsy value but never re-renders when the
		 * composable mutates the state. Wrapping each access in a computed
		 * forces Vue's dependency tracker to subscribe to the
		 * `Vue.observable()` getter, so subsequent state changes (open via
		 * right-click, close via outside-click / Cancel) correctly mount
		 * and unmount the popover.
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		contextMenuVisible() {
			return this.grid.state.contextMenuOpen
		},
		/** @spec openspec/specs/dashboards/spec.md */
		contextMenuTop() {
			return this.grid.state.contextMenuPosition.y
		},
		/** @spec openspec/specs/dashboards/spec.md */
		contextMenuLeft() {
			return this.grid.state.contextMenuPosition.x
		},
		/** @spec openspec/specs/dashboards/spec.md */
		placedWidgetIds() {
			return this.widgetPlacements.map(p => p.widgetId)
		},
		/**
		 * Combined input for the sidebar's `groupDashboards` prop —
		 * primary-group + default-group rows, each carrying their `source`
		 * discriminator from `/api/dashboards/visible` (REQ-DASH-013).
		 *
		 * @return {Array<object>} Concatenated group + default dashboards.
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		sidebarGroupDashboards() {
			return [...this.groupSharedDashboards, ...this.defaultGroupDashboards]
		},
		/**
		 * Section discriminator for the currently active dashboard, so the
		 * top-right active-dashboard cog (DashboardRowActions) gates its
		 * owner-only entries (Configure / Delete) exactly like the matching
		 * sidebar row. Resolved by locating the active id in the same buckets
		 * the sidebar renders; falls back to the dashboard's own `isOwner`
		 * flag when the record is not in any bucket yet.
		 *
		 * @return {'group'|'default'|'user'} The active dashboard's source.
		 */
		/** @spec openspec/specs/dashboard-switcher/spec.md */
		activeDashboardSource() {
			const id = this.activeDashboard?.id
			if (id != null) {
				if (this.userDashboards?.some(d => d.id === id)) {
					return 'user'
				}
				if (this.defaultGroupDashboards?.some(d => d.id === id)) {
					return 'default'
				}
				if (this.groupSharedDashboards?.some(d => d.id === id)) {
					return 'group'
				}
			}
			return this.activeDashboard?.isOwner === false ? 'group' : 'user'
		},
		/**
		 * Personal dashboards for the sidebar's `userDashboards` prop.
		 * Aliased so the sidebar's prop name reads naturally in the
		 * template even if the store getter is renamed later.
		 *
		 * @return {Array<object>} Dashboards with `source === 'user'`.
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		sidebarUserDashboards() {
			return this.userDashboards
		},
		/**
		 * Empty-state copy. When personal dashboards are disabled by the
		 * admin we swap the friendly "create one" prompt for a localised
		 * explainer (REQ-ASET-003). The translatable English source is
		 * kept short so the layout doesn't wrap awkwardly.
		 *
		 * @return {string}
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		emptyStateDescription() {
			if (this.allowUserDashboards) {
				return this.t('launchpad', 'Create your first dashboard to get started')
			}
			return this.t('launchpad', 'Personal dashboards are not enabled by your administrator')
		},
		/**
		 * Display label for the resolved primary group (REQ-TMPL-012).
		 *
		 * Returns the server-pushed `primaryGroupName` verbatim when it
		 * is non-empty (real Nextcloud groups), the localised
		 * `'Default'` string when the resolver returned the `default`
		 * sentinel and the server didn't pick a name, or an empty
		 * string when there is nothing meaningful to show — the
		 * `v-if` in the template hides the badge in that last case.
		 *
		 * @return {string} Label to render, or '' when none.
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		primaryGroupLabel() {
			if (this.primaryGroupName) {
				return this.primaryGroupName
			}
			if (this.primaryGroup && this.primaryGroup !== 'default') {
				return this.primaryGroup
			}
			return ''
		},
	},
	watch: {
		/**
		 * Mirror the combined edit-mode / permission gate into the
		 * Vue.observable proxy the grid manager composable owns. The
		 * proxy is the only thing the composable reads, so this watcher
		 * is what keeps the right-click guard live.
		 *
		 * @param {boolean} value the new combined edit/permission value
		 */
		canEditForContextMenu: {
			immediate: true,
			/** @spec openspec/specs/dashboards/spec.md */
			handler(value) {
				if (this.canEditRef) {
					this.canEditRef.value = !!value
				}
			},
		},
		/**
		 * REQ-ANLT-011 — fire a view-event whenever the active
		 * dashboard switches (and on initial render once the active
		 * dashboard hydrates). Per-uuid debouncing in
		 * `recordViewEventDebounced` collapses near-simultaneous
		 * mounts of the same dashboard into one network call.
		 *
		 * @param {object|null} dashboard the freshly-active dashboard
		 */
		'activeDashboard.uuid': {
			immediate: true,
			/** @spec openspec/specs/dashboards/spec.md */
			handler(uuid, prevUuid) {
				if (!uuid) {
					return
				}
				this.recordViewEventDebounced(uuid)
				// Outbound URL sync — every uuid change pushes a new
				// history entry so back/forward navigates between
				// dashboards. The first hydration is `replaceState`
				// (handled in mounted) so we don't pollute history with
				// the bootstrap entry.
				if (prevUuid !== undefined) {
					this.pushUrlForActiveDashboard()
				}
			},
		},
	},
	/** @spec openspec/specs/dashboards/spec.md */
	async created() {
		// Bind the host onto the grid composable so its onEdit / onRemove
		// callbacks can delegate to component methods. The composable was
		// instantiated in `setup()` which has no access to `this`.
		this.grid._host = this

		const dashboardStore = useDashboardStore()
		const widgetStore = useWidgetStore()
		const tileStore = useTileStore()

		await Promise.all([
			dashboardStore.loadDashboards(),
			widgetStore.loadAvailableWidgets(),
			tileStore.loadTiles(),
		])

		// Wave3.7 — fetch the user's pinned default-dashboard UUID
		// once on mount so the per-row cog can render the right "Set
		// as default" / "Default dashboard" state. Failure here is
		// non-fatal: the cog falls back to "Set as default" for every
		// row when the pref can't be read.
		try {
			const res = await api.getDefaultDashboardPreference()
			this.defaultDashboardUuid = res?.data?.uuid ?? ''
		} catch (error) {
			console.error('[Views] Failed to load default-dashboard preference:', error)
		}
	},
	/** @spec openspec/specs/dashboards/spec.md */
	mounted() {
		// Attach the document-level click listener (REQ-WDG-016 outside-
		// click closes popover). Detached in beforeDestroy so we never
		// leak a listener across mounts.
		this.grid.attach()

		// Deep-link URL sync — replace the URL in-place so the address
		// bar reflects whichever dashboard the server actually rendered
		// (handles renamed parents and stale bookmarked paths). Uses
		// replaceState rather than pushState so the bootstrap entry
		// doesn't pollute the back-button history.
		this.replaceUrlFromInitialState()

		// Browser back / forward → re-resolve the URL and switch.
		window.addEventListener('popstate', this.handleHistoryPopState)
	},
	/** @spec openspec/specs/dashboards/spec.md */
	beforeDestroy() {
		this.grid.detach()
		// Drop the host pointer to avoid retaining the Vue instance.
		this.grid._host = null

		window.removeEventListener('popstate', this.handleHistoryPopState)
	},
	methods: {
		t,

		/**
		 * Per-item render key for CnDashboardGrid — changes when a placement's
		 * style/update changes so an edit forces a re-render (REQ-GRID).
		 *
		 * @param {object} placement the placement (CnDashboardGrid layout item).
		 * @return {string} the render key.
		 */
		placementItemKey(placement) {
			return `${placement.id}-${placement.updatedAt || ''}-${JSON.stringify(placement.styleConfig || {})}`
		},

		/**
		 * Resolve the available-widget definition for a placement's widgetId.
		 *
		 * @param {string} widgetId the placement's widget id.
		 * @return {object|undefined} the widget definition, if registered.
		 */
		getWidget(widgetId) {
			return this.availableWidgets.find(w => w.id === widgetId)
		},

		/**
		 * Whether a placement renders as a launcher tile (custom tile type).
		 *
		 * @param {object} placement the placement.
		 * @return {boolean} true for tile placements.
		 */
		isTilePlacement(placement) {
			return placement.tileType === 'custom'
		},

		/**
		 * Project a tile placement's flat `tile*` columns into the tile shape
		 * TileWidget expects.
		 *
		 * @param {object} placement the tile placement.
		 * @return {object|null} the tile data, or null when not a tile.
		 */
		getTileData(placement) {
			if (!this.isTilePlacement(placement)) return null
			return {
				id: placement.id,
				title: placement.tileTitle,
				icon: placement.tileIcon,
				iconType: placement.tileIconType,
				backgroundColor: placement.tileBackgroundColor,
				textColor: placement.tileTextColor,
				linkType: placement.tileLinkType,
				linkValue: placement.tileLinkValue,
			}
		},

		...mapActions(useDashboardStore, [
			'switchDashboard',
			'createDashboard',
			'forkDashboard',
			'loadDashboards',
			'updatePlacements',
			'addWidgetToDashboard',
			'addTileToDashboard',
			'removeWidgetFromDashboard',
			'updateWidgetPlacement',
			'recordViewEvent',
		]),

		/**
		 * REQ-ANLT-011 — per-uuid 1-second debounce wrapping the
		 * fire-and-forget store action. The ledger lives on
		 * `data.viewEventLastSent` (Object.create(null)) so different
		 * dashboards are tracked independently per spec scenario
		 * "Different dashboards are not debounced against each other".
		 * The debounce is per-mount (not cross-tab), matching the
		 * spec's reload semantics.
		 *
		 * @param {string} uuid The dashboard UUID to record.
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		recordViewEventDebounced(uuid) {
			if (!uuid) {
				return
			}
			const now = Date.now()
			const last = this.viewEventLastSent[uuid] || 0
			if ((now - last) < 1000) {
				return
			}
			this.viewEventLastSent[uuid] = now
			this.recordViewEvent(uuid)
		},

		...mapActions(useTileStore, ['createTile', 'updateTile', 'deleteTile']),

		/** @spec openspec/specs/dashboards/spec.md */
		toggleEditMode() {
			this.isEditMode = !this.isEditMode
			if (!this.isEditMode) {
				this.closeWidgetModal()
				this.closeStyleEditor()
				// Leaving edit mode also dismisses any open right-click
				// popover so view mode never carries an edit-only surface.
				this.grid.closeContextMenu()
			}
		},

		/**
		 * DashboardGrid forwards every right-click on a placement here
		 * (REQ-WDG-015). The composable owns the early-return + viewport
		 * clamp + state mutation; we just forward the event so view mode
		 * never calls `preventDefault()`.
		 *
		 * @param {MouseEvent} event the contextmenu event
		 * @param {object} placement the placement under the cursor
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		onWidgetRightClick(event, placement) {
			this.grid.onWidgetRightClick(event, placement)
		},

		/**
		 * Edit click from the popover (REQ-WDG-015 edit scenario). Custom-
		 * type placements (label, text, image, link-button, …) reuse the
		 * unified AddWidgetModal with `editingWidget` set (REQ-WDG-010);
		 * all other placements fall through to the legacy style editor so
		 * the popover is useful for stock Nextcloud widgets too.
		 *
		 * @param {object} placement the placement to edit
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		handleContextMenuEdit(placement) {
			// Placements only carry `widgetId` — never a `type` field. For
			// registry-driven custom widgets (label, text, image, link, …)
			// the `widgetId` IS the registry type key, so resolve the type
			// from the registry and open the content editor (AddWidgetModal)
			// with `type` set so `loadEditingWidget` can pre-fill the form.
			// Stock Nextcloud-widget / tile placements have no registry entry
			// and fall through to the legacy style editor.
			const resolvedType = this.resolveWidgetType(placement)
			if (resolvedType) {
				this.openCustomWidgetEdit({ ...placement, type: resolvedType })
				return
			}
			this.openStyleEditor(placement)
		},

		/**
		 * Resolve the registry type key for a placement from its `widgetId`.
		 * Returns the type string for registry-driven custom widgets (the
		 * `widgetId` is the type key) or `null` when the placement is not a
		 * custom widget (stock NC widget, tile, …) so the caller can fall
		 * back to the style editor.
		 *
		 * @param {object} placement the placement under the cursor
		 * @return {string|null} the resolved widget type, or null
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		resolveWidgetType(placement) {
			const widgetId = placement?.widgetId
			if (typeof widgetId !== 'string' || widgetId === '') {
				return null
			}
			const entry = getWidgetTypeEntry(widgetId)
			// Only types with a configurable form can be content-edited; the
			// renderer-only `nc-widget` proxy stays on the style-editor path.
			if (!entry || !entry.form) {
				return null
			}
			return widgetId
		},

		/**
		 * Remove click from the popover (REQ-WDG-015 remove scenario).
		 * Routes through the same store action as the existing remove
		 * flow so the placement-delete path of REQ-WDG-005 (DELETE
		 * `/api/placements/{id}`) remains the single source of truth.
		 *
		 * @param {object} placement the placement to delete
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		async handleContextMenuRemove(placement) {
			if (!placement?.id) {
				return
			}
			try {
				await this.removeWidget(placement.id)
			} catch (error) {
				console.error('[Views] Failed to remove widget via context menu:', error)
			}
		},
		/**
		 * "Visibility rules…" click from the popover
		 * (conditional-visibility spec). Stores the target placement id and
		 * opens the editor modal. The popover only fires this when the user
		 * has `canEdit` (the grid manager early-returns otherwise), so no
		 * extra gate is needed here.
		 *
		 * @param {object} placement the placement whose rules to edit
		 */
		/** @spec openspec/specs/conditional-visibility/spec.md */
		handleContextMenuVisibilityRules(placement) {
			if (!placement?.id) {
				return
			}
			this.visibilityPlacementId = placement.id
			this.isVisibilityModalOpen = true
		},

		/** @spec openspec/specs/conditional-visibility/spec.md */
		closeVisibilityRules() {
			this.isVisibilityModalOpen = false
			this.visibilityPlacementId = null
		},

		/** @spec openspec/specs/conditional-visibility/spec.md */
		onVisibilityRulesChanged() {
			// Rules are evaluated server-side at render; nothing to refetch
			// here. Hook kept so future live-preview can refresh placements.
		},

		/** @spec openspec/specs/dashboards/spec.md */
		openWidgetModal() {
			if (!this.isEditMode) {
				this.isEditMode = true
			}
			this.isWidgetModalOpen = true
		},
		/** @spec openspec/specs/dashboards/spec.md */
		closeWidgetModal() {
			this.isWidgetModalOpen = false
		},
		/**
		 * Open the registry-driven custom widget modal in create mode.
		 * Pass a `type` to deep-link to a specific sub-form (REQ-WDG-010
		 * preselected-type scenario); omit it for the type-picker flow.
		 *
		 * @param {string|null} type registry key, or null for picker flow
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		openCustomWidgetModal(type = null) {
			if (!this.isEditMode) {
				this.isEditMode = true
			}
			this.customWidgetPreselectedType = type
			this.customWidgetEditing = null
			this.isCustomWidgetModalOpen = true
		},
		/**
		 * Open the modal in edit mode for an existing custom-type
		 * placement. The placement's type is immutable in edit mode
		 * (REQ-WDG-010), so the type select is hidden.
		 *
		 * @param {object} placement existing placement record with type+content
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		openCustomWidgetEdit(placement) {
			this.customWidgetEditing = placement
			this.customWidgetPreselectedType = null
			this.isCustomWidgetModalOpen = true
		},
		/** @spec openspec/specs/dashboards/spec.md */
		closeCustomWidgetModal() {
			this.isCustomWidgetModalOpen = false
			this.customWidgetPreselectedType = null
			this.customWidgetEditing = null
		},
		/**
		 * Persist the `{type, content}` payload emitted by AddWidgetModal.
		 * In create mode we route through `addWidgetToDashboard` (which
		 * the per-widget proposals will extend to accept custom-type
		 * payloads); in edit mode we route through `updateWidgetPlacement`.
		 *
		 * The per-widget capability proposals own the actual API contract
		 * — this view simply forwards the payload, mirroring how the tile
		 * editor and style editor work today.
		 *
		 * @param {{type: string, content: object}} payload the widget add/edit payload from AddWidgetModal
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		async saveCustomWidget(payload) {
			try {
				const chrome = payload.chrome || {}
				if (this.customWidgetEditing?.id) {
					await this.updateWidgetPlacement(
						this.customWidgetEditing.id,
						{
							content: payload.content,
							...this.chromePatch(chrome, this.customWidgetEditing.styleConfig),
						},
					)
				} else {
					// Create the placement, then apply the chrome (title /
					// background / icon) from the same modal as a follow-up
					// patch against the new id.
					const created = await this.addWidgetToDashboard({
						type: payload.type,
						content: payload.content,
					})
					if (created?.id) {
						await this.updateWidgetPlacement(created.id, this.chromePatch(chrome, created.styleConfig))
					}
				}
				this.closeCustomWidgetModal()
			} catch (error) {
				console.error('[Views] Failed to save custom widget:', error)
			}
		},

		/**
		 * Upload transport for the shared CnAddWidgetModal's Appearance icon
		 * picker — rounds a custom icon data URL through LaunchPad's resource
		 * service so it isn't embedded inline.
		 *
		 * @param {File} file the file to upload.
		 * @return {Promise<string>} the resulting URL/data URL.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		iconUploadFn(file) {
			return uploadDataUrl(file)
		},

		/**
		 * Calendar list fetcher for the shared CnAddWidgetModal's calendar
		 * widget picker — returns the user's calendars so authors select them
		 * instead of typing principal URIs (REQ-CAL-002).
		 *
		 * @return {Promise<Array<{key: string, name: string, color: string}>>}
		 * @spec openspec/specs/calendar-widget/spec.md
		 */
		async fetchCalendars() {
			const res = await api.getCalendarWidgetCalendars()
			return res?.data?.calendars || []
		},

		/**
		 * Build the chrome patch (title / background / icon) from the unified
		 * add/edit modal payload, preserving any existing styleConfig keys and
		 * folding the background colour into styleConfig.backgroundColor — the
		 * same shape the legacy style editor persisted (see onStyleSaved).
		 *
		 * @param {{showTitle?: boolean, customTitle?: string, customIcon?: string, backgroundColor?: string}} chrome the modal chrome payload
		 * @param {object} [existingStyleConfig] the placement's current styleConfig, if any
		 * @return {object} the placement patch
		 * @spec openspec/specs/dashboards/spec.md
		 */
		chromePatch(chrome, existingStyleConfig) {
			return {
				showTitle: chrome.showTitle,
				customTitle: chrome.customTitle,
				customIcon: chrome.customIcon,
				styleConfig: {
					...(existingStyleConfig || {}),
					backgroundColor: chrome.backgroundColor || '',
				},
			}
		},
		/** @spec openspec/specs/dashboards/spec.md */
		openConfigModal() {
			this.configModalMode = 'edit'
			this.configModalInitialTab = 'general'
			this.isConfigModalOpen = true
		},

		/**
		 * Top-bar share action (dashboard-sharing spec). Opens the config
		 * drawer for the active dashboard pre-selected to the Sharing tab.
		 *
		 * @return {void}
		 */
		/** @spec openspec/specs/dashboard-sharing/spec.md */
		openShareDrawer() {
			this.configModalMode = 'edit'
			this.configModalInitialTab = 'sharing'
			this.isConfigModalOpen = true
		},
		/** @spec openspec/specs/dashboards/spec.md */
		openCreateDashboardModal() {
			this.configModalMode = 'create'
			this.isConfigModalOpen = true
		},
		/** @spec openspec/specs/dashboards/spec.md */
		closeConfigModal() {
			this.isConfigModalOpen = false
		},
		/** @spec openspec/specs/dashboards/spec.md */
		async addWidget(widgetId) {
			await this.addWidgetToDashboard(widgetId)
		},
		/** @spec openspec/specs/dashboards/spec.md */
		async removeWidget(placementId) {
			await this.removeWidgetFromDashboard(placementId)
		},
		/** @spec openspec/specs/dashboards/spec.md */
		openStyleEditor(placement) {
			this.editingPlacement = placement
			// Hand the modal a deep clone — it mutates `widget` in place on Save;
			// the live store placement only changes once the patch persists.
			this.styleEditorWidget = JSON.parse(JSON.stringify(placement))
			this.isStyleEditorOpen = true
		},
		/** @spec openspec/specs/dashboards/spec.md */
		closeStyleEditor() {
			this.isStyleEditorOpen = false
			this.editingPlacement = null
			this.styleEditorWidget = null
		},
		/**
		 * Bridge CnWidgetStyleEditorModal's mutate-in-place `@save(widget)` to
		 * launchpad's immutable store path: derive the chrome+style patch from
		 * the (mutated) clone and persist it against the placement id.
		 *
		 * @param {object} widget The clone the modal mutated on Save.
		 * @return {Promise<void>}
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async onStyleSaved(widget) {
			const id = this.editingPlacement?.id
			if (!id) {
				return
			}
			await this.updateWidgetPlacement(id, {
				showTitle: widget.showTitle,
				customTitle: widget.customTitle,
				customIcon: widget.customIcon,
				styleConfig: widget.styleConfig,
			})
			this.closeStyleEditor()
		},
		/** @spec openspec/specs/dashboards/spec.md */
		async deleteWidget() {
			if (this.editingPlacement?.id) {
				await this.removeWidget(this.editingPlacement.id)
				this.closeStyleEditor()
			}
		},
		/** @spec openspec/specs/dashboards/spec.md */
		openTileEditor(tile = null) {
			if (!this.isEditMode) {
				this.isEditMode = true
			}
			this.editingTile = tile
			this.isTileEditorOpen = true
		},
		/** @spec openspec/specs/dashboards/spec.md */
		openTileEditorForEdit(placement) {
			const tileData = {
				id: placement.id,
				title: placement.tileTitle,
				icon: placement.tileIcon,
				iconType: placement.tileIconType,
				backgroundColor: placement.tileBackgroundColor,
				textColor: placement.tileTextColor,
				linkType: placement.tileLinkType,
				linkValue: placement.tileLinkValue,
			}
			this.openTileEditor(tileData)
		},
		/** @spec openspec/specs/dashboards/spec.md */
		closeTileEditor() {
			this.isTileEditorOpen = false
			this.editingTile = null
		},
		/** @spec openspec/specs/dashboards/spec.md */
		async saveTile(tileData) {
			try {
				if (this.editingTile) {
					await this.updateWidgetPlacement(this.editingTile.id, {
						tileTitle: tileData.title,
						tileIcon: tileData.icon,
						tileIconType: tileData.iconType,
						tileBackgroundColor: tileData.backgroundColor,
						tileTextColor: tileData.textColor,
						tileLinkType: tileData.linkType,
						tileLinkValue: tileData.linkValue,
					})
				} else {
					await this.addTileToDashboard(tileData)
				}
				this.closeTileEditor()
			} catch (error) {
				console.error('[Views] Failed to save tile:', error)
			}
		},
		/** @spec openspec/specs/dashboards/spec.md */
		async deleteTile() {
			if (this.editingTile?.id) {
				await this.removeWidget(this.editingTile.id)
				this.closeTileEditor()
			}
		},
		/** @spec openspec/specs/dashboards/spec.md */
		handleCreateDashboard() {
			this.openCreateDashboardModal()
		},
		/** @spec openspec/specs/dashboards/spec.md */
		async saveDashboardConfig({ id, name, description, icon }) {
			try {
				if (id == null) {
					await this.createDashboard({ name, description, icon })
				} else {
					await api.updateDashboard(id, { name, description, icon })
					await this.loadDashboards()
				}
				this.closeConfigModal()
			} catch (error) {
				console.error('Failed to save dashboard:', error)
			}
		},
		/** @spec openspec/specs/dashboards/spec.md */
		async deleteCurrentDashboard(dashboard) {
			if (!confirm(this.t('launchpad', 'Are you sure you want to delete this dashboard?'))) {
				return
			}

			try {
				await api.deleteDashboard(dashboard.id)
				await this.loadDashboards()
				this.closeConfigModal()
			} catch (error) {
				console.error('Failed to delete dashboard:', error)
			}
		},
		/**
		 * Handle a switch emitted by `DashboardSwitcherSidebar`. The sidebar
		 * passes the row's `source` discriminator alongside the id so we
		 * can pick the correct API endpoint per REQ-DASH-013/REQ-DASH-014:
		 *
		 *   - `'user'`    → personal dashboard endpoint (already the
		 *                   default in `dashboardStore.switchDashboard`)
		 *   - `'group'`   → primary group endpoint
		 *   - `'default'` → default group endpoint
		 *
		 * The store currently fetches via `getDashboardById`, which works
		 * for every visible-to-user record regardless of source — the
		 * group/default branches stay identical for now and exist to make
		 * the source contract visible to readers (and to keep the fan-out
		 * easy when source-specific endpoints land).
		 *
		 * @param {string|number} id Dashboard id from the clicked row.
		 * @param {'group'|'default'|'user'} source Section discriminator.
		 */
		// eslint-disable-next-line no-unused-vars
		/** @spec openspec/specs/dashboards/spec.md */
		async onSidebarSwitch(id, source) {
			// `source` is currently informational — `switchDashboard`
			// resolves any visible dashboard via /api/dashboard/{id}. The
			// signature is kept explicit so per-source behaviour can land
			// without re-touching this view (and so the load-bearing
			// REQ-SWITCH-002 contract is visible at the call site).
			await this.switchDashboard(id)
		},

		/**
		 * Compute the absolute URL for a slug-chain path. The launchpad
		 * routes mount under whatever prefix `generateUrl` produces
		 * (typically `/index.php/apps/launchpad` or `/apps/launchpad` when
		 * URL rewriting is enabled), so we anchor onto the same prefix
		 * the API client uses.
		 *
		 * @param {string} path Leading-slash slug-chain (e.g. `/finance/q1`).
		 * @return {string} Absolute pathname for `history.pushState`.
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		buildDeepLinkUrl(path) {
			if (!path) {
				return ''
			}
			const prefix = generateUrl('/apps/launchpad')
			const cleanPath = path.startsWith('/') ? path : `/${path}`
			return `${prefix}${cleanPath}`
		},

		/**
		 * Bring the browser URL in line with the deep-link path the
		 * server pushed via initial state. Runs once on mount; uses
		 * `replaceState` so the bootstrap entry doesn't pollute the
		 * back-button history.
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		replaceUrlFromInitialState() {
			const target = this.buildDeepLinkUrl(this.injectedDeepLinkPath)
			if (!target) {
				return
			}
			if (window.location.pathname.replace(/\/+$/, '') === target.replace(/\/+$/, '')) {
				return
			}
			try {
				window.history.replaceState(
					{ uuid: this.activeDashboard?.uuid ?? null, source: 'launchpad-deeplink' },
					'',
					target,
				)
			} catch (e) {
				// SecurityError when running outside the page's origin
				// (jsdom test harnesses, sandboxed iframes). Failure is
				// non-fatal — the URL just stays out of sync.
				console.warn('[Views] history.replaceState failed:', e)
			}
		},

		/**
		 * Outbound URL sync — fetch the canonical path for the active
		 * dashboard and `pushState` it. Called from the
		 * `activeDashboard.uuid` watcher AFTER the initial hydration
		 * (the bootstrap render uses `replaceUrlFromInitialState`
		 * instead). Failures are non-fatal; the URL just stays at its
		 * previous value while the active dashboard moves on.
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		async pushUrlForActiveDashboard() {
			const uuid = this.activeDashboard?.uuid
			if (!uuid) {
				return
			}
			try {
				const res = await api.getDashboardPath(uuid)
				const path = res?.data?.path ?? ''
				const target = this.buildDeepLinkUrl(path)
				if (!target) {
					return
				}
				if (window.location.pathname === target) {
					return
				}
				window.history.pushState(
					{ uuid, source: 'launchpad-deeplink' },
					'',
					target,
				)
			} catch (e) {
				console.warn('[Views] failed to push URL for active dashboard:', e)
			}
		},

		/**
		 * Browser back / forward handler. Strips the launchpad route
		 * prefix off `window.location.pathname` and re-resolves the
		 * remaining slug-chain via the existing by-path API. The state
		 * payload's uuid is preferred when present (avoids the
		 * round-trip), but we fall back to path resolution so external
		 * navigations (manually pasted URLs that hit popstate) still
		 * route correctly.
		 *
		 * @param {PopStateEvent} event The popstate event.
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		async handleHistoryPopState(event) {
			const targetUuid = event?.state?.uuid ?? null
			if (targetUuid && targetUuid === this.activeDashboard?.uuid) {
				return
			}

			const prefix = generateUrl('/apps/launchpad')
			const pathname = window.location.pathname
			let suffix = ''
			if (pathname.startsWith(prefix)) {
				suffix = pathname.slice(prefix.length).replace(/^\/+/, '').replace(/\/+$/, '')
			}
			if (!suffix) {
				return
			}

			try {
				const res = await api.getDashboardByPath(suffix)
				const dashboard = res?.data?.dashboard
				if (dashboard?.id !== undefined && dashboard?.id !== null) {
					await this.switchDashboard(dashboard.id)
				}
			} catch (e) {
				console.warn('[Views] popstate path resolution failed:', e)
			}
		},

		/*
		 * Wave3.6 per-row action handlers. Each cog action emits the
		 * row's full dashboard payload (not just the active one), so
		 * we first switch to that dashboard (when not already active)
		 * and then apply the requested action. This lets the user
		 * Edit / Configure / Add-widget on any row without manually
		 * switching first.
		 *
		 * @param {object} dashboard Row payload (`id`, `name`, `isOwner`, …).
		 * @param {'group'|'default'|'user'} source Row section discriminator.
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		async onRowToggleEdit(dashboard, source) {
			await this.maybeSwitchTo(dashboard.id, source)
			this.toggleEditMode()
		},
		/** @spec openspec/specs/dashboards/spec.md */
		async onRowOpenConfig(dashboard, source) {
			await this.maybeSwitchTo(dashboard.id, source)
			this.openConfigModal()
		},
		/** @spec openspec/specs/dashboards/spec.md */
		async onRowAddCustomWidget(dashboard, source) {
			await this.maybeSwitchTo(dashboard.id, source)
			this.openCustomWidgetModal()
		},

		/*
		 * Wave3.7 — pin the row's dashboard as the user's default.
		 * Toggle semantics: clicking on the already-pinned row clears
		 * the pin (so the cog shows "Set as default" again on next
		 * open); clicking on any other row replaces the pin with that
		 * dashboard's UUID. The new pref takes effect on the next
		 * page load — visiting `/apps/launchpad/` will resolve to this
		 * dashboard via the resolver's Step 0.
		 */
		// eslint-disable-next-line no-unused-vars
		/** @spec openspec/specs/dashboards/spec.md */
		async onRowSetDefault(dashboard, source) {
			const uuid = dashboard?.uuid ?? ''
			if (uuid === '') {
				return
			}
			try {
				if (this.defaultDashboardUuid === uuid) {
					await api.clearDefaultDashboardPreference()
					this.defaultDashboardUuid = ''
				} else {
					await api.setDefaultDashboardPreference(uuid)
					this.defaultDashboardUuid = uuid
				}
			} catch (error) {
				console.error('[Views] Failed to update default-dashboard preference:', error)
			}
		},

		/*
		 * Wave3.8 — modal `set-default` handler. Honours the explicit
		 * boolean from the toggle (unlike the row-cog toggle which
		 * inverts based on current pin state). Lets the user clear
		 * the pin from the dashboard's own configuration without
		 * having to hunt for the same dashboard's row.
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		async onModalSetDefault({ uuid, isDefault }) {
			if (!uuid) {
				return
			}
			try {
				if (isDefault) {
					await api.setDefaultDashboardPreference(uuid)
					this.defaultDashboardUuid = uuid
				} else if (this.defaultDashboardUuid === uuid) {
					await api.clearDefaultDashboardPreference()
					this.defaultDashboardUuid = ''
				}
			} catch (error) {
				console.error('[Views] Failed to update default-dashboard preference from modal:', error)
			}
		},

		/*
		 * Switch the active dashboard only when the requested id
		 * differs from the current active. Avoids a redundant API
		 * round-trip when the per-row action targets the row that is
		 * already active.
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		async maybeSwitchTo(id, source) {
			if (this.activeDashboard?.id === id) {
				return
			}
			await this.onSidebarSwitch(id, source)
		},
		/**
		 * Sidebar `+ New Dashboard` handler — forks the active dashboard
		 * as a personal copy (REQ-DASH-020). The store action handles the
		 * 403 toast when personal dashboards are disabled (REQ-ASET-003)
		 * and pushes the new entry to `dashboards` on success.
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		async onSidebarCreateDashboard() {
			const sourceUuid = this.activeDashboard?.uuid
			if (!sourceUuid) {
				return
			}
			try {
				await this.forkDashboard(sourceUuid, null)
			} catch {
				// Error surfaces via the store's showError toast; nothing
				// additional to do here.
			}
		},
		/**
		 * Sidebar personal-row delete handler. Mirrors the topbar
		 * deletion flow (confirm → API → reload) but operates on an
		 * arbitrary id rather than the active dashboard.
		 *
		 * @param {string|number} id Personal dashboard id to delete.
		 */
		/** @spec openspec/specs/dashboards/spec.md */
		async onSidebarDeleteDashboard(id) {
			if (!confirm(this.t('launchpad', 'Are you sure you want to delete this dashboard?'))) {
				return
			}
			try {
				await api.deleteDashboard(id)
				await this.loadDashboards()
			} catch (error) {
				console.error('Failed to delete dashboard:', error)
			}
		},
	},
}
</script>

<style scoped>
#launchpad-app {
	min-height: 100vh;
	width: 100%;
	background: transparent;
}

/* Nextcloud insets the content area horizontally but not at the top, so the
   grid sat flush under the navbar (8px top gap from the grid margin vs 16px
   on the sides). Add a matching top inset so the dashboard breathes evenly. */
.launchpad-container {
	padding-top: 8px;
}

/* CnDashboardGrid renders a flat item background; restore launchpad's
   frosted-glass tile look (was DashboardGrid's local style) by overriding
   the grid-item-content surface. :deep penetrates into CnDashboardGrid. */
.launchpad-container :deep(.grid-stack-item-content) {
	background: var(--color-main-background-blur);
	backdrop-filter: var(--filter-background-blur);
	-webkit-backdrop-filter: var(--filter-background-blur);
	border-radius: var(--border-radius-large);
	overflow: hidden;
}

/* In edit mode the whole grid item is a drag surface. Text-heavy chromeless
   widgets (esp. labels) are pure selectable text filling the cell, so a drag
   would start a text selection instead of a gridstack move. Suppress text
   selection + show the move cursor so dragging works anywhere on any widget.
   The edit cog stays clickable (pointer-events unaffected). */
.launchpad-container.launchpad-edit-mode :deep(.grid-stack-item-content) {
	cursor: move;
	user-select: none;
	-webkit-user-select: none;
}

/* Chromeless widgets (label/divider/header/tile) AND nc-dashboard card
   widgets opt out of the grid-item frosted surface: the former paint their
   own surface, and the latter now carry the shared CnWidgetWrapper
   `nc-dashboard` chrome (its own blur panel), so the grid item must be
   transparent to avoid a double background / mismatched radius. */
.launchpad-container :deep(.grid-stack-item-content:has(.cn-widget-wrapper--borderless)),
.launchpad-container :deep(.grid-stack-item-content:has(.cn-widget-wrapper--nc-dashboard)),
.launchpad-container :deep(.grid-stack-item-content:has(.tile-widget)) {
	background: transparent;
	backdrop-filter: none;
	-webkit-backdrop-filter: none;
}

.launchpad-grid-item {
	width: 100%;
	height: 100%;
}

.launchpad-floating-controls {
	position: fixed;
	top: 80px;
	right: 44px;
	display: flex;
	gap: 8px;
	align-items: center;
	z-index: 1000;
}

.launchpad-sidebar-toggle {
	/* Hint that the sidebar opens from the left even though the toggle
	   itself lives in the top-right cluster. */
	margin-right: auto;
}

/* Primary-group label (REQ-TMPL-012). Subtle pill that names the
   resolved group whose dashboards drive the workspace. */
.launchpad-primary-group-label {
	font-size: 12px;
	font-weight: 500;
	color: var(--color-text-maxcontrast);
	background: var(--color-background-hover);
	border-radius: var(--border-radius-pill, 999px);
	padding: 4px 10px;
	white-space: nowrap;
}

/* Strip the visible text on the menu trigger button — we want icon-only.
   NcActions renders its aria-label as button text in this version. */
.launchpad-floating-controls :deep(.action-item__menutoggle .button-vue__text) {
	display: none;
}
.launchpad-floating-controls :deep(.action-item__menutoggle) {
	width: var(--default-clickable-area, 44px);
	min-width: var(--default-clickable-area, 44px);
	padding: 0;
}

.launchpad-container {
	flex: 1;
	padding: 0;
	overflow: auto;
	min-height: calc(100vh - var(--header-height));
}

.launchpad-empty,
.launchpad-loading {
	display: flex;
	align-items: center;
	justify-content: center;
	height: 100%;
	min-height: calc(100vh - var(--header-height));
}
</style>
