<template>
	<div id="launchpad-app">
		<!-- Slide-in sidebar (REQ-SWITCH-001..007). The sidebar keeps the
		     `isOpen` prop / `update:open` event pair mandated by the spec.
		     Vue 3 removed the component-level `model: { prop, event }`
		     option, so a bare `v-model` here would bind `modelValue` /
		     `update:modelValue` — neither of which the sidebar declares —
		     and the panel would never open. Bind both halves explicitly.
		     Once `runtime-shell` ships and replaces this view with
		     `WorkspaceApp.vue`, the same binding shape applies. -->
		<DashboardSwitcherSidebar
			:isOpen="sidebarOpen"
			:groupName="primaryGroupName"
			:groupDashboards="sidebarGroupDashboards"
			:userDashboards="sidebarUserDashboards"
			:activeDashboardId="activeDashboard?.id"
			:allowUserDashboards="allowUserDashboards"
			:canEdit="canEdit"
			:defaultUuid="defaultDashboardUuid"
			:dashboardQuotaReached="dashboardQuotaReached"
			:dashboardQuotaTooltip="dashboardQuotaTooltip"
			:isEditMode="isEditMode"
			@update:open="sidebarOpen = $event"
			@switch="onSidebarSwitch"
			@createDashboard="onSidebarCreateDashboard"
			@deleteDashboard="onSidebarDeleteDashboard"
			@toggleEdit="onRowToggleEdit"
			@openConfig="onRowOpenConfig"
			@addCustomWidget="onRowAddCustomWidget"
			@setDefault="onRowSetDefault" />
		<SidebarBackdrop v-if="sidebarOpen" @close="sidebarOpen = false" />

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
				:canEdit="canEdit"
				:canShare="canShareActiveDashboard"
				:defaultUuid="defaultDashboardUuid"
				:isEditMode="isEditMode"
				:activeDashboardId="activeDashboard.id"
				buttonType="secondary"
				:iconSize="20"
				class="launchpad-active-dashboard-cog"
				@toggleEdit="onRowToggleEdit(activeDashboard, activeDashboardSource)"
				@openConfig="onRowOpenConfig(activeDashboard, activeDashboardSource)"
				@addCustomWidget="
					onRowAddCustomWidget(activeDashboard, activeDashboardSource)
				"
				@setDefault="onRowSetDefault(activeDashboard, activeDashboardSource)"
				@share="openShareDrawer"
				@delete="onSidebarDeleteDashboard(activeDashboard.id)" />
			<NcButton
				variant="secondary"
				:aria-label="t('launchpad', 'Dashboards')"
				class="launchpad-sidebar-toggle"
				@click="sidebarOpen = !sidebarOpen">
				<template #icon>
					<MenuIcon :size="20" />
				</template>
			</NcButton>
			<!-- dashboard-acknowledgements REQ-ACK-002: dashboard-level count
			     of the user's outstanding mandatory-read items. Hidden entirely
			     when zero so dashboards without acknowledgement requirements are
			     visually unchanged. -->
			<span
				v-if="outstandingAcknowledgementCount > 0"
				class="launchpad-ack-indicator"
				data-testid="acknowledgement-outstanding-count"
				:title="t('launchpad', 'You have items awaiting acknowledgement')">
				{{
					n(
						'launchpad',
						'%n item to acknowledge',
						'%n items to acknowledge',
						outstandingAcknowledgementCount,
					)
				}}
			</span>
			<!-- dashboard-acknowledgements REQ-ACK-004: admin read-receipt
			     report opener. Only shown to an editor when the active
			     dashboard carries at least one acknowledgement requirement. -->
			<NcButton
				v-if="canShareActiveDashboard"
				variant="tertiary"
				:aria-label="t('launchpad', 'Share')"
				class="mydash-share-action"
				data-test="dashboard-share-action"
				@click="openShareDrawer">
				<template #icon>
					<ShareVariant :size="20" />
				</template>
				{{ t('launchpad', 'Share') }}
			</NcButton>
			<NcButton
				v-if="canEdit && acknowledgementAnnouncementKeys.length > 0"
				variant="secondary"
				:aria-label="t('launchpad', 'Read receipts')"
				data-testid="open-acknowledgement-report"
				@click="
					openAcknowledgementReport(acknowledgementAnnouncementKeys[0])
				">
				{{ t('launchpad', 'Read receipts') }}
			</NcButton>
		</div>

		<!-- Admin read-receipt report (REQ-ACK-004/006). -->
		<AcknowledgementReportModal
			:open="ackReportOpen"
			:announcementKey="ackReportKey"
			@close="ackReportOpen = false" />

		<!-- Main dashboard grid -->
		<div
			class="launchpad-container"
			:class="{ 'launchpad-edit-mode': isEditMode }">
			<CnDashboardGrid
				v-if="activeDashboard"
				:layout="widgetPlacements"
				:editable="isEditMode"
				:columns="activeDashboard.gridColumns || 12"
				:cellHeight="60"
				:margin="8"
				:columnOpts="dashboardColumnOpts"
				cellHeightCssVar="--launchpad-cell-height"
				:itemKey="placementItemKey"
				@layoutChange="updatePlacements">
				<template #widget="{ item }">
					<!-- tile-quick-search. Dimming is REACTIVE: the `search`
					     widget writes the current match set into the tileSearch
					     store and this binding renders the consequence, so no
					     component reaches in to toggle classes and nothing reads
					     the placement id back out of the DOM (ADR-004).
					     `data-placement-id` remains as the addressing mechanism
					     for the two things that must stay imperative — scrolling
					     a chosen tile into view and clicking its link — and as
					     the e2e suite's selector. -->
					<div
						class="launchpad-grid-item"
						:class="{
							'launchpad-grid-item--dimmed': isTileDimmed(item),
						}"
						:data-placement-id="item.id"
						@contextmenu="onWidgetRightClick($event, item)">
						<!-- Tile placements render the launcher tile directly. -->
						<TileWidget
							v-if="isTilePlacement(item)"
							:tile="getTileData(item)"
							:editMode="isEditMode"
							:placementId="item.id"
							:healthPingEnabled="
								item.content
								&& item.content.healthPingEnabled === true
							"
							:pingInterval="item.content && item.content.pingInterval"
							@edit="openTileEditorForEdit(item)"
							@remove="removeWidget(item.id)" />
						<!-- All other placements render through the widget wrapper. -->
						<WidgetWrapper
							v-else
							:placement="item"
							:widget="getWidget(item.widgetId)"
							:availableWidgets="availableWidgets"
							:editMode="isEditMode"
							:outstandingAcknowledgement="
								isPlacementOutstanding(item)
							"
							@remove="removeWidget(item.id)"
							@style="openStyleEditor(item)"
							@edit="handleContextMenuEdit(item)"
							@acknowledged="onWidgetAcknowledged" />
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
						<NcButton variant="primary" @click="handleCreateDashboard">
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
			:placedWidgetIds="placedWidgetIds"
			@close="closeWidgetModal"
			@add="addWidget" />

		<!-- Custom widget add/edit modal — registry-driven host for label,
		     text, image, link-button, etc. (REQ-WDG-010..014). The modal does
		     no API calls itself; this view persists the emitted payload. -->
		<CnAddWidgetModal
			:show="isCustomWidgetModalOpen"
			:preselectedType="customWidgetPreselectedType"
			:editingWidget="customWidgetEditing"
			:uploadFn="iconUploadFn"
			:fileUploadFn="imageFileUpload"
			:calendarsFetcher="fetchCalendars"
			@close="closeCustomWidgetModal"
			@submit="saveCustomWidget" />

		<!-- Dashboard configuration modal (also used for creating a new dashboard) -->
		<DashboardConfigModal
			:open="isConfigModalOpen"
			:dashboard="configModalMode === 'create' ? null : activeDashboard"
			:mode="configModalMode"
			:canDelete="dashboards.length > 1"
			:defaultUuid="defaultDashboardUuid"
			:initialTab="configModalInitialTab"
			@close="closeConfigModal"
			@save="saveDashboardConfig"
			@delete="deleteCurrentDashboard"
			@setDefault="onModalSetDefault" />

		<!-- Style editor modal. `extra-icon-options` is gone: CnIconBrowser now
		     ships the NL Design sets itself (RVO lazily), so passing the pack here
		     only forced all ~2.3 MB of it into the eager bundle. -->
		<CnWidgetStyleEditorModal
			v-if="editingPlacement"
			:show="isStyleEditorOpen"
			:widget="styleEditorWidget"
			:deletable="!editingPlacement.isCompulsory"
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
			@move="grid.triggerMove()"
			@remove="grid.triggerRemove()"
			@visibilityRules="grid.triggerVisibilityRules()"
			@close="grid.closeContextMenu()" />

		<!-- Keyboard-operable move/resize panel (WCAG 2.1 SC 2.1.1). The
		     pointer-only GridStack drag has no keyboard equivalent, so the
		     context menu's "Move" item opens this panel, which nudges the
		     placement through the same pure `nudgePlacement()` helper the
		     drag path's collision model uses. -->
		<WidgetMovePanel
			:open="movePanelOpen"
			:placement="movePanelPlacement"
			:allPlacements="widgetPlacements"
			:gridColumns="activeDashboard?.gridColumns || 12"
			@save="handleMoveSave"
			@close="closeMovePanel" />

		<!-- Per-widget conditional-visibility editor (conditional-visibility
		     spec). Opened from the context menu's "Visibility rules…" item;
		     gated on `canEdit` so only dashboard owners reach it. -->
		<VisibilityRulesModal
			:open="isVisibilityModalOpen"
			:placementId="visibilityPlacementId"
			@close="closeVisibilityRules"
			@ruleAdded="onVisibilityRulesChanged"
			@ruleUpdated="onVisibilityRulesChanged"
			@ruleRemoved="onVisibilityRulesChanged" />
	</div>
</template>

<script>
import {
	CnAddWidgetModal,
	CnDashboardGrid,
	CnWidgetStyleEditorModal,
	getDashboardColumnOpts,
	NcButton,
	NcEmptyContent,
	NcLoadingIcon,
} from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { mapActions, mapState } from 'pinia'
import { computed, provide, reactive } from 'vue'
import MenuIcon from 'vue-material-design-icons/Menu.vue'
import ShareVariant from 'vue-material-design-icons/ShareVariant.vue'
// Icons
import ViewDashboard from 'vue-material-design-icons/ViewDashboard.vue'
import TileWidget from '../components/TileWidget.vue'
import WidgetContextMenu from '../components/Widgets/WidgetContextMenu.vue'
// Components
import WidgetWrapper from '../components/WidgetWrapper.vue'
import DashboardRowActions from '../components/Workspace/DashboardRowActions.vue'
import DashboardSwitcherSidebar from '../components/Workspace/DashboardSwitcherSidebar.vue'
import SidebarBackdrop from '../components/Workspace/SidebarBackdrop.vue'
import AcknowledgementReportModal from '../modals/AcknowledgementReportModal.vue'
import DashboardConfigModal from '../modals/DashboardConfigModal.vue'
import TileEditor from '../modals/TileEditor.vue'
import VisibilityRulesModal from '../modals/VisibilityRulesModal.vue'
import WidgetMovePanel from '../modals/WidgetMovePanel.vue'
import WidgetPickerModal from '../modals/WidgetPickerModal.vue'
// Composables
import { useGridManager } from '../composables/useGridManager.js'
import { getWidgetTypeEntry } from '../constants/widgetRegistry.js'
import { api } from '../services/api.js'
import { uploadDataUrl, uploadFile } from '../services/resourceService.js'
// Stores
import { useDashboardStore } from '../stores/dashboard.js'
import { useTileStore } from '../stores/tiles.js'
import { useTileSearchStore } from '../stores/tileSearch.js'
import { useWidgetStore } from '../stores/widgets.js'
import { logger } from '../utils/logger.js'

export default {
	// Multi-word per vue/multi-word-component-names. This is the `name` option
	// (devtools / findComponent), NOT the registration key — parents still
	// register and stub it as `Views`.
	name: 'DashboardViews',
	components: {
		NcButton,
		NcEmptyContent,
		NcLoadingIcon,
		ViewDashboard,
		MenuIcon,
		ShareVariant,
		CnDashboardGrid,
		WidgetWrapper,
		AcknowledgementReportModal,
		TileWidget,
		WidgetPickerModal,
		CnWidgetStyleEditorModal,
		TileEditor,
		DashboardConfigModal,
		CnAddWidgetModal,
		WidgetContextMenu,
		WidgetMovePanel,
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
		const canEditRef = reactive({ value: false })

		// `selectedWidget` from the popover may live in either of two
		// edit paths. The host-side callbacks resolve which one to use
		// (custom-type widgets → AddWidgetModal; nextcloud-widget tiles →
		// CnWidgetStyleEditorModal) and the placement-delete path is the same
		// `removeWidgetFromDashboard` action used by the existing remove
		// flow. The host wires these via methods after instantiation so
		// `this` is bound to the component when the callbacks fire.
		const grid = useGridManager({
			canEdit: canEditRef,
			/**
			 * Context-menu "Edit" — open the content editor for a placement.
			 *
			 * @param {object} widget Placement the context menu was opened on.
			 * @spec openspec/specs/dashboards/spec.md
			 */
			onEdit(widget) {
				// `this` is the Vue instance once we bind in `created()`.
				grid._host?.handleContextMenuEdit(widget)
			},
			/**
			 * Context-menu "Remove" — delete a placement from the dashboard.
			 *
			 * @param {object} widget Placement the context menu was opened on.
			 * @spec openspec/specs/dashboards/spec.md
			 */
			onRemove(widget) {
				grid._host?.handleContextMenuRemove(widget)
			},
			/**
			 * Context-menu "Move" — open the keyboard move/resize panel.
			 *
			 * @param {object} widget Placement the context menu was opened on.
			 * @spec openspec/specs/grid-layout/spec.md
			 */
			onMove(widget) {
				grid._host?.handleContextMenuMove(widget)
			},
			/**
			 * Context-menu "Visibility rules…" — open the conditional
			 * visibility editor for a placement.
			 *
			 * @param {object} widget Placement the context menu was opened on.
			 * @spec openspec/specs/conditional-visibility/spec.md
			 */
			onVisibilityRules(widget) {
				grid._host?.handleContextMenuVisibilityRules(widget)
			},
		})

		// nc-vue's CnNcDashboardWidgetForm (the `nc-widget` sub-form mounted by
		// CnAddWidgetModal) injects a `widgets` catalog to populate its
		// CnNcWidgetGridPicker — the same Nextcloud-native dashboard-widget
		// list already fetched into the widget store (`loadAvailableWidgets`,
		// whose own comment notes it feeds `CnNcWidgetWidget`'s runtime
		// renderer via `widgetBridge.setWidgetMetadata`). Nothing previously
		// provided it down this component's tree, so the picker always saw
		// nc-vue's inject default (`[]`) and rendered permanently empty.
		// `computed` keeps it reactive to the store across the fetch.
		provide(
			'widgets',
			computed(() => useWidgetStore().availableWidgets),
		)

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
			// Keyboard move/resize panel state (WCAG 2.1 SC 2.1.1). Opened
			// from the context menu's "Move" item for the placement in
			// `movePanelPlacement`.
			movePanelOpen: false,
			movePanelPlacement: null,
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
			// dashboard-acknowledgements REQ-ACK-004: admin read-receipt
			// report modal state.
			ackReportOpen: false,
			ackReportKey: '',
		}
	},

	computed: {
		/**
		 * tile-quick-search REQ-QSEARCH-002 — whether a given placement is
		 * currently de-emphasised by an active quick-search query.
		 *
		 * Returns `false` for every placement when no query is running, which
		 * is why a dashboard with no `search` widget never dims anything.
		 *
		 * A `search` placement NEVER dims itself. It is a placement like any
		 * other, so without this it de-emphasises the very bar the user is
		 * typing into the moment their query stops matching the word
		 * "Search" — the control fades out from under the cursor.
		 *
		 * Keyed on the widget type rather than on a single remembered id so a
		 * dashboard carrying more than one search widget behaves the same.
		 *
		 * @return {(placement: object) => boolean} the predicate.
		 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-002
		 */
		isTileDimmed() {
			const { isDimmed } = useTileSearchStore()
			return (placement) => {
				if (placement?.widgetId === 'search') {
					return false
				}
				return isDimmed(placement?.id)
			}
		},

		/**
		 * dashboard-acknowledgements REQ-ACK-004: distinct announcement keys of
		 * the placements on the active dashboard that require acknowledgement.
		 * Drives the admin "Read receipts" affordance (shown only when at least
		 * one placement carries a requirement and the user can edit).
		 *
		 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
		 * @return {string[]} the announcement keys.
		 */
		acknowledgementAnnouncementKeys() {
			const keys = (this.widgetPlacements || [])
				.filter(
					(p) =>
						Number(p.requiresAcknowledgement) === 1 && p.announcementKey,
				)
				.map((p) => p.announcementKey)
			return [...new Set(keys)]
		},

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

		...mapState(useDashboardStore, [
			'dashboards',
			'activeDashboard',
			'widgetPlacements',
			'permissionLevel',
			'loading',
			'userDashboards',
			'groupSharedDashboards',
			'defaultGroupDashboards',
			'sharedWithMeDashboards',
			// dashboard-quota-limits REQ-QUOTA-006: drive the disabled
			// create affordance + tooltip from the store getters.
			'dashboardQuotaReached',
			'dashboardQuotaTooltip',
			// dashboard-acknowledgements REQ-ACK-002: outstanding count +
			// per-placement outstanding predicate for the forced-delivery gate.
			'outstandingAcknowledgementCount',
			'isPlacementOutstanding',
		]),

		...mapState(useWidgetStore, ['availableWidgets']),
		/*
		 * `tiles` is deliberately NOT mapped: `getTileData()` builds a tile
		 * from the placement row itself, so nothing here reads the store list.
		 */

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
		 * @spec openspec/specs/dashboard-sharing/spec.md
		 */
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
		 * @spec openspec/specs/dashboards/spec.md
		 */
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
		 * `reactive()` getter, so subsequent state changes (open via
		 * right-click, close via outside-click / Cancel) correctly mount
		 * and unmount the popover.
		 * @spec openspec/specs/dashboards/spec.md
		 */
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
			return this.widgetPlacements.map((p) => p.widgetId)
		},

		/**
		 * Combined input for the sidebar's `groupDashboards` prop —
		 * primary-group + default-group + shared-with-me rows, each carrying
		 * their `source` discriminator from `/api/dashboards/visible`
		 * (REQ-DASH-013, REQ-SHARE-002). The sidebar re-splits them by
		 * `source` into its own sections; anything omitted here is dropped
		 * from the switcher entirely, which is how `shared` rows went
		 * missing once the store refreshed over the server-rendered
		 * initial state.
		 *
		 * @return {Array<object>} Concatenated group + default + shared dashboards.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		sidebarGroupDashboards() {
			return [
				...this.groupSharedDashboards,
				...this.defaultGroupDashboards,
				...this.sharedWithMeDashboards,
			]
		},

		/**
		 * Section discriminator for the currently active dashboard, so the
		 * top-right active-dashboard cog (DashboardRowActions) gates its
		 * owner-only entries (Configure / Delete) exactly like the matching
		 * sidebar row. Resolved by locating the active id in the same buckets
		 * the sidebar renders; falls back to the dashboard's own `isOwner`
		 * flag when the record is not in any bucket yet.
		 *
		 * @return {'group'|'default'|'user'|'shared'} The active dashboard's source.
		 * @spec openspec/specs/dashboard-switcher/spec.md
		 */
		activeDashboardSource() {
			const id = this.activeDashboard?.id
			if (id !== null && id !== undefined) {
				if (this.userDashboards?.some((d) => d.id === id)) {
					return 'user'
				}
				if (this.defaultGroupDashboards?.some((d) => d.id === id)) {
					return 'default'
				}
				if (this.groupSharedDashboards?.some((d) => d.id === id)) {
					return 'group'
				}
				// REQ-SHARE-002: a dashboard reached through a share is not
				// the caller's own, so the cog must gate its owner-only
				// entries exactly as it does for a group row.
				if (this.sharedWithMeDashboards?.some((d) => d.id === id)) {
					return 'shared'
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
		 * @spec openspec/specs/dashboards/spec.md
		 */
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
		 * @spec openspec/specs/dashboards/spec.md
		 */
		emptyStateDescription() {
			if (this.allowUserDashboards) {
				return this.t(
					'launchpad',
					'Create your first dashboard to get started',
				)
			}
			return this.t(
				'launchpad',
				'Personal dashboards are not enabled by your administrator',
			)
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
			/**
			 * Mirror the computed edit permission into the ref the grid
			 * manager closes over.
			 *
			 * @param {boolean} value Whether the user may currently edit.
			 * @spec openspec/specs/dashboards/spec.md
			 */
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
			/**
			 * Fire a view-event when the active dashboard changes.
			 *
			 * @param {string|null} uuid UUID of the newly-active dashboard.
			 * @param {string|null} prevUuid UUID that was active before.
			 * @spec openspec/specs/dashboards/spec.md
			 */
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

		// dashboard-acknowledgements REQ-ACK-002: load the user's outstanding
		// mandatory-read items so the forced-delivery gate and the outstanding
		// count reflect reality on first render. Non-fatal on failure.
		dashboardStore.fetchPendingAcknowledgements()

		// Wave3.7 — fetch the user's pinned default-dashboard UUID
		// once on mount so the per-row cog can render the right "Set
		// as default" / "Default dashboard" state. Failure here is
		// non-fatal: the cog falls back to "Set as default" for every
		// row when the pref can't be read.
		try {
			const res = await api.getDefaultDashboardPreference()
			this.defaultDashboardUuid = res?.data?.uuid ?? ''
		} catch (error) {
			logger.error(
				'[Views] Failed to load default-dashboard preference:',
				error,
			)
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
	beforeUnmount() {
		this.grid.detach()
		// Drop the host pointer to avoid retaining the Vue instance.
		this.grid._host = null

		window.removeEventListener('popstate', this.handleHistoryPopState)
	},

	methods: {
		t,

		/**
		 * Per-item render key for CnDashboardGrid.
		 *
		 * The key is the placement id and NOTHING else. It previously also
		 * folded in `updatedAt` and a JSON dump of `styleConfig` to force a
		 * re-render after an edit — but that made the key change on every
		 * persist, so Vue tore down and recreated the grid item's DOM node.
		 * Any element focused inside it lost focus, which breaks keyboard
		 * move/resize outright (WCAG 2.1 SC 2.1.1): after one arrow-key move
		 * the user's focus was thrown back to the document and a second move
		 * was impossible without re-navigating the whole grid.
		 *
		 * Re-rendering on content change does not need a key change — the
		 * placement objects are replaced wholesale by the store, so the
		 * bound props update and the child re-renders normally.
		 *
		 * NOTE: the "Placement key regeneration" scenario at
		 * openspec/specs/grid-layout/spec.md:405 — filed, oddly, under
		 * REQ-GRID-010 (Grid Styling) — still requires the superseded
		 * `updatedAt` + `styleConfig` key via a `getPlacementKey()` that no
		 * longer exists. That spelling is precisely what broke keyboard
		 * move/resize, so the scenario is stale and this method deliberately
		 * does not satisfy it. REQ-GRID-008 below is the requirement this
		 * implementation serves. Tracked for spec correction in launchpad#101.
		 *
		 * @spec openspec/specs/grid-layout/spec.md#req-grid-008
		 * @param {object} placement the placement (CnDashboardGrid layout item).
		 * @return {string} the render key — stable for the placement's lifetime.
		 */
		placementItemKey(placement) {
			return String(placement.id)
		},

		/**
		 * Resolve the available-widget definition for a placement's widgetId.
		 *
		 * @param {string} widgetId the placement's widget id.
		 * @return {object|undefined} the widget definition, if registered.
		 */
		getWidget(widgetId) {
			return this.availableWidgets.find((w) => w.id === widgetId)
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
		 * @spec openspec/specs/grid-layout/spec.md#req-grid-009
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

		// dashboard-acknowledgements REQ-ACK-002: after a recipient signs off
		// on a widget, refresh the outstanding set so the dashboard-level
		// count stays accurate. The store already dropped the acknowledged
		// item optimistically; the re-fetch reconciles with the server.
		/** @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md */
		onWidgetAcknowledged() {
			const dashboardStore = useDashboardStore()
			dashboardStore.fetchPendingAcknowledgements()
		},

		/**
		 * Open the admin read-receipt report for an announcement
		 * (REQ-ACK-004).
		 *
		 * @param {string} announcementKey Key identifying the announcement
		 *   whose acknowledgement report should be shown.
		 * @spec openspec/changes/dashboard-acknowledgements/specs/dashboard-acknowledgements/spec.md
		 */
		openAcknowledgementReport(announcementKey) {
			this.ackReportKey = announcementKey
			this.ackReportOpen = true
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
		 * @spec openspec/specs/dashboards/spec.md
		 */
		recordViewEventDebounced(uuid) {
			if (!uuid) {
				return
			}
			const now = Date.now()
			const last = this.viewEventLastSent[uuid] || 0
			if (now - last < 1000) {
				return
			}
			this.viewEventLastSent[uuid] = now
			this.recordViewEvent(uuid)
		},

		/*
		 * The tile-store actions are deliberately NOT mapped. `createTile` and
		 * `updateTile` had no caller, and the mapped `deleteTile` was SHADOWED
		 * by the local `deleteTile()` defined later in this same object — a
		 * later key wins, so the store action could never run. The local one
		 * (which delegates to `removeWidget`) is what `@delete` invokes.
		 */

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
		 * @spec openspec/specs/dashboards/spec.md
		 */
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
		 * @spec openspec/specs/dashboards/spec.md
		 */
		handleContextMenuEdit(placement) {
			// Placements only carry `widgetId` — never a `type` field. For
			// registry-driven custom widgets (label, text, image, link, …)
			// the `widgetId` IS the registry type key, so resolve the type
			// from the registry and open the content editor (AddWidgetModal)
			// with `type` set so `loadEditingWidget` can pre-fill the form.
			// `tile` now resolves to the shared registry entry (CnDashTileWidget
			// + CnDashTileWidgetForm), so preset/registry tiles open the content
			// editor below; the form reads their flat tile* columns. Legacy
			// `custom` tiles are handled earlier via TileEditor (isTilePlacement).
			// Stock Nextcloud-widget placements still have no registry entry and
			// fall through to the legacy style editor.
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
		 * @spec openspec/specs/dashboards/spec.md
		 */
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
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async handleContextMenuRemove(placement) {
			if (!placement?.id) {
				return
			}
			try {
				await this.removeWidget(placement.id)
			} catch (error) {
				logger.error(
					'[Views] Failed to remove widget via context menu:',
					error,
				)
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
		 * @spec openspec/specs/conditional-visibility/spec.md
		 */
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

		/**
		 * Context-menu "Move" — open the keyboard move/resize panel for a
		 * placement. This is the WCAG 2.1 SC 2.1.1 keyboard equivalent of
		 * GridStack's pointer-only drag; reaching the menu already required
		 * `canEdit`, so no extra gate is needed here.
		 *
		 * @param {object} placement the placement to move or resize
		 * @spec openspec/specs/grid-layout/spec.md
		 */
		handleContextMenuMove(placement) {
			if (!placement?.id) {
				return
			}
			this.movePanelPlacement = placement
			this.movePanelOpen = true
		},

		/** @spec openspec/specs/grid-layout/spec.md */
		closeMovePanel() {
			this.movePanelOpen = false
			this.movePanelPlacement = null
		},

		/**
		 * Persist a confirmed keyboard move/resize. The panel hands back the
		 * clamped rectangle plus any placements `nudgePlacement()` had to
		 * push down to avoid overlap; both are folded into one
		 * `updatePlacements` call so the whole layout is written atomically
		 * on the same debounced path the drag handler uses.
		 *
		 * @param {{gridX: number, gridY: number, gridWidth: number, gridHeight: number, pushed: Array<{id: (number|string), gridY: number}>}} rect
		 *   Confirmed geometry emitted by `WidgetMovePanel`.
		 * @spec openspec/specs/grid-layout/spec.md
		 */
		async handleMoveSave(rect) {
			const movedId = this.movePanelPlacement?.id
			if (!movedId || !rect) {
				this.closeMovePanel()
				return
			}
			const pushedById = new Map(
				(rect.pushed || []).map((p) => [p.id, p.gridY]),
			)
			const next = (this.widgetPlacements || []).map((placement) => {
				if (placement.id === movedId) {
					return {
						...placement,
						gridX: rect.gridX,
						gridY: rect.gridY,
						gridWidth: rect.gridWidth,
						gridHeight: rect.gridHeight,
					}
				}
				if (pushedById.has(placement.id)) {
					return { ...placement, gridY: pushedById.get(placement.id) }
				}
				return placement
			})
			this.closeMovePanel()
			try {
				await this.updatePlacements(next)
			} catch (error) {
				logger.error('[Views] Failed to persist keyboard move:', error)
			}
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
		 * @spec openspec/specs/dashboards/spec.md
		 */
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
		 * @spec openspec/specs/dashboards/spec.md
		 */
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
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async saveCustomWidget(payload) {
			try {
				const chrome = payload.chrome || {}
				if (this.customWidgetEditing?.id) {
					await this.updateWidgetPlacement(this.customWidgetEditing.id, {
						content: payload.content,
						...this.chromePatch(
							chrome,
							this.customWidgetEditing.styleConfig,
						),
					})
				} else {
					// Create the placement, then apply the chrome (title /
					// background / icon) from the same modal as a follow-up
					// patch against the new id.
					const created = await this.addWidgetToDashboard({
						type: payload.type,
						content: payload.content,
					})
					if (created?.id) {
						await this.updateWidgetPlacement(
							created.id,
							this.chromePatch(chrome, created.styleConfig),
						)
					}
				}
				this.closeCustomWidgetModal()
			} catch (error) {
				logger.error('[Views] Failed to save custom widget:', error)
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
		 * Raw-file upload transport for the shared CnAddWidgetModal's image
		 * widget sub-form. Streams the picked file to LaunchPad's resource
		 * service as multipart (no base64) on submit and returns the hosted
		 * URL in the `{ url }` shape the sub-form's uploadFn expects.
		 *
		 * @param {File} file the picked image file.
		 * @return {Promise<{url: string}>} the hosted resource URL.
		 * @spec openspec/specs/resource-uploads/spec.md
		 */
		imageFileUpload(file) {
			return uploadFile(file).then((result) => ({ url: result.url }))
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
		 * @spec openspec/specs/dashboard-sharing/spec.md
		 */
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

		/**
		 * Place a widget on the active dashboard.
		 *
		 * @param {string} widgetId Registry key or Nextcloud widget id to add.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async addWidget(widgetId) {
			await this.addWidgetToDashboard(widgetId)
		},

		/**
		 * Remove a placement from the active dashboard.
		 *
		 * @param {number|string} placementId Id of the placement to remove.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async removeWidget(placementId) {
			await this.removeWidgetFromDashboard(placementId)
		},

		/**
		 * Open the per-widget style editor for a placement.
		 *
		 * @param {object} placement Placement whose chrome/styling to edit.
		 * @spec openspec/specs/dashboards/spec.md
		 */
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

		/**
		 * Open the launcher-tile editor, entering edit mode if needed.
		 *
		 * @param {object|null} [tile] Existing tile to edit; null opens the
		 *   editor for a brand-new tile.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		openTileEditor(tile = null) {
			if (!this.isEditMode) {
				this.isEditMode = true
			}
			this.editingTile = tile
			this.isTileEditorOpen = true
		},

		/**
		 * Open the tile editor pre-filled from an existing tile placement.
		 *
		 * @param {object} placement Tile placement whose `content` blob seeds
		 *   the editor form.
		 * @spec openspec/specs/dashboards/spec.md
		 * @spec openspec/specs/service-health-ping/spec.md
		 */
		openTileEditorForEdit(placement) {
			const content = placement.content || {}
			const tileData = {
				id: placement.id,
				title: placement.tileTitle,
				icon: placement.tileIcon,
				iconType: placement.tileIconType,
				backgroundColor: placement.tileBackgroundColor,
				textColor: placement.tileTextColor,
				linkType: placement.tileLinkType,
				linkValue: placement.tileLinkValue,
				// service-health-ping: config lives in the placement's
				// `content` JSON blob, no schema change (REQ-HPING-001).
				healthPingEnabled: content.healthPingEnabled === true,
				healthUrl: content.healthUrl || '',
				expectedStatus: content.expectedStatus || 200,
				pingInterval: content.pingInterval || 60,
			}
			this.openTileEditor(tileData)
		},

		/** @spec openspec/specs/dashboards/spec.md */
		closeTileEditor() {
			this.isTileEditorOpen = false
			this.editingTile = null
		},

		/**
		 * Create or update a launcher tile from the editor's form state.
		 *
		 * @param {object} tileData Assembled tile fields (title, icon, link
		 *   target, colours, health-ping config, …).
		 * @spec openspec/specs/dashboards/spec.md
		 * @spec openspec/specs/service-health-ping/spec.md
		 */
		async saveTile(tileData) {
			// service-health-ping: config lives in the placement's `content`
			// JSON blob, no schema change (REQ-HPING-001).
			const healthPingContent = {
				healthPingEnabled: tileData.healthPingEnabled === true,
				healthUrl: tileData.healthUrl || '',
				expectedStatus: tileData.expectedStatus || 200,
				pingInterval: tileData.pingInterval || 60,
			}
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
						content: healthPingContent,
					})
				} else {
					const newPlacement = await this.addTileToDashboard(tileData)
					// `addTile` has no `content` parameter (mirrors the
					// `addWidget` create-then-patch pattern above) — persist
					// the health-ping block with a follow-up patch only when
					// the author actually enabled it.
					if (newPlacement?.id && healthPingContent.healthPingEnabled) {
						await this.updateWidgetPlacement(newPlacement.id, {
							content: healthPingContent,
						})
					}
				}
				this.closeTileEditor()
			} catch (error) {
				logger.error('[Views] Failed to save tile:', error)
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

		/**
		 * Create or update a dashboard from the config modal's form state.
		 *
		 * @param {object} config Dashboard attributes from the modal.
		 * @param {number|null} config.id Dashboard id; null creates a new one.
		 * @param {string} config.name Display name.
		 * @param {string} config.description Short description.
		 * @param {string} config.icon Icon key or URL.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async saveDashboardConfig({ id, name, description, icon }) {
			try {
				if (id === null || id === undefined) {
					await this.createDashboard({ name, description, icon })
				} else {
					await api.updateDashboard(id, { name, description, icon })
					await this.loadDashboards()
				}
				this.closeConfigModal()
			} catch (error) {
				logger.error('Failed to save dashboard:', error)
			}
		},

		/**
		 * Delete a dashboard after an explicit user confirmation.
		 *
		 * @param {object} dashboard Dashboard record to delete.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async deleteCurrentDashboard(dashboard) {
			if (
				!confirm(
					this.t(
						'launchpad',
						'Are you sure you want to delete this dashboard?',
					),
				)
			) {
				return
			}

			try {
				await api.deleteDashboard(dashboard.id)
				await this.loadDashboards()
				this.closeConfigModal()
			} catch (error) {
				logger.error('Failed to delete dashboard:', error)
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
		 * @spec openspec/specs/dashboards/spec.md
		 */

		// REQ-SWITCH-002 contract and is kept in the signature so per-source
		// endpoints can land without re-touching this view.
		async onSidebarSwitch(id, _source) {
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
		 * @spec openspec/specs/dashboards/spec.md
		 */
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
		 *
		 * @spec openspec/specs/dashboards/spec.md
		 */
		replaceUrlFromInitialState() {
			const target = this.buildDeepLinkUrl(this.injectedDeepLinkPath)
			if (!target) {
				return
			}
			if (
				window.location.pathname.replace(/\/+$/, '')
				=== target.replace(/\/+$/, '')
			) {
				return
			}
			try {
				window.history.replaceState(
					{
						uuid: this.activeDashboard?.uuid ?? null,
						source: 'launchpad-deeplink',
					},
					'',
					target,
				)
			} catch (e) {
				// SecurityError when running outside the page's origin
				// (jsdom test harnesses, sandboxed iframes). Failure is
				// non-fatal — the URL just stays out of sync.
				logger.warn('[Views] history.replaceState failed:', e)
			}
		},

		/**
		 * Outbound URL sync — fetch the canonical path for the active
		 * dashboard and `pushState` it. Called from the
		 * `activeDashboard.uuid` watcher AFTER the initial hydration
		 * (the bootstrap render uses `replaceUrlFromInitialState`
		 * instead). Failures are non-fatal; the URL just stays at its
		 * previous value while the active dashboard moves on.
		 *
		 * @spec openspec/specs/dashboards/spec.md
		 */
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
				logger.warn('[Views] failed to push URL for active dashboard:', e)
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
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async handleHistoryPopState(event) {
			const targetUuid = event?.state?.uuid ?? null
			if (targetUuid && targetUuid === this.activeDashboard?.uuid) {
				return
			}

			const prefix = generateUrl('/apps/launchpad')
			const pathname = window.location.pathname
			let suffix = ''
			if (pathname.startsWith(prefix)) {
				suffix = pathname
					.slice(prefix.length)
					.replace(/^\/+/, '')
					.replace(/\/+$/, '')
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
				logger.warn('[Views] popstate path resolution failed:', e)
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
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async onRowToggleEdit(dashboard, source) {
			await this.maybeSwitchTo(dashboard.id, source)
			this.toggleEditMode()
		},

		/**
		 * Row cog "Configure" — switch to the row's dashboard, then open the
		 * dashboard config modal.
		 *
		 * @param {object} dashboard Row payload (`id`, `name`, `isOwner`, …).
		 * @param {'group'|'default'|'user'} source Row section discriminator.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async onRowOpenConfig(dashboard, source) {
			await this.maybeSwitchTo(dashboard.id, source)
			this.openConfigModal()
		},

		/**
		 * Row cog "Add widget" — switch to the row's dashboard, then open the
		 * custom-widget picker.
		 *
		 * @param {object} dashboard Row payload (`id`, `name`, `isOwner`, …).
		 * @param {'group'|'default'|'user'} source Row section discriminator.
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async onRowAddCustomWidget(dashboard, source) {
			await this.maybeSwitchTo(dashboard.id, source)
			this.openCustomWidgetModal()
		},

		/**
		 * Wave3.7 — pin the row's dashboard as the user's default.
		 *
		 * Toggle semantics: clicking the already-pinned row clears the pin
		 * (so the cog shows "Set as default" again on next open); clicking
		 * any other row replaces the pin with that dashboard's UUID. The new
		 * preference takes effect on the next page load — visiting
		 * `/apps/launchpad/` resolves to this dashboard via the resolver's
		 * Step 0.
		 *
		 * @param {object} dashboard Row payload; only `uuid` is read.
		 * @param {'group'|'default'|'user'} source Row section discriminator,
		 *   accepted for signature parity with the other row handlers.
		 * @spec openspec/specs/dashboards/spec.md
		 */

		// but kept so every row-cog handler shares one signature.
		async onRowSetDefault(dashboard, _source) {
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
				logger.error(
					'[Views] Failed to update default-dashboard preference:',
					error,
				)
			}
		},

		/*
		 * Wave3.8 — modal `set-default` handler. Honours the explicit
		 * boolean from the toggle (unlike the row-cog toggle which
		 * inverts based on current pin state). Lets the user clear
		 * the pin from the dashboard's own configuration without
		 * having to hunt for the same dashboard's row.
		 */
		/**
		 * @param {object} payload Emitted by the dashboard config modal.
		 * @param {string} payload.uuid UUID of the dashboard in the modal.
		 * @param {boolean} payload.isDefault True to pin it as the user's
		 *   default; false clears the pin when this dashboard held it.
		 * @spec openspec/specs/dashboards/spec.md
		 */
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
				logger.error(
					'[Views] Failed to update default-dashboard preference from modal:',
					error,
				)
			}
		},

		/**
		 * Switch the active dashboard only when the requested id differs
		 * from the current one. Avoids a redundant API round-trip when a
		 * per-row action targets the row that is already active.
		 *
		 * @param {string|number} id Dashboard id the row action targets.
		 * @param {'group'|'default'|'user'} source Row section discriminator.
		 * @spec openspec/specs/dashboards/spec.md
		 */
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
		 *
		 * @spec openspec/specs/dashboards/spec.md
		 */
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
		 * @spec openspec/specs/dashboards/spec.md
		 */
		async onSidebarDeleteDashboard(id) {
			if (
				!confirm(
					this.t(
						'launchpad',
						'Are you sure you want to delete this dashboard?',
					),
				)
			) {
				return
			}
			try {
				await api.deleteDashboard(id)
				await this.loadDashboards()
			} catch (error) {
				logger.error('Failed to delete dashboard:', error)
			}
		},
	},
}
</script>

<style scoped>
#launchpad-app {
	width: 100%;
	background: transparent;
}

/* Nextcloud insets the content area horizontally but not at the top, so the
   grid sat flush under the navbar (8px top gap from the grid margin vs 16px
   on the sides). Add a matching top inset so the dashboard breathes evenly.
   Note: the layout properties (flex, overflow, min-height) are consolidated
   in the single .launchpad-container rule further below. */

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
.launchpad-container
	:deep(.grid-stack-item-content:has(.cn-widget-wrapper--borderless)),
.launchpad-container
	:deep(.grid-stack-item-content:has(.cn-widget-wrapper--nc-dashboard)),
.launchpad-container :deep(.grid-stack-item-content:has(.tile-widget)) {
	background: transparent;
	backdrop-filter: none;
	-webkit-backdrop-filter: none;
}

.launchpad-grid-item {
	width: 100%;
	height: 100%;
	transition: opacity 0.15s ease;
}

/* tile-quick-search REQ-QSEARCH-002 "Typing filters tiles by label":
   non-matching tiles are de-emphasised, NOT removed from the grid layout,
   so the grid never reflows while the user types. The class is bound
   reactively from the tileSearch store in this component's own template
   (see `isTileDimmed`) — no other component reaches in to set it. */
.launchpad-grid-item--dimmed {
	opacity: 0.35;
}

@media (prefers-reduced-motion: reduce) {
	.launchpad-grid-item {
		transition: none;
	}
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

.launchpad-ack-indicator {
	/* dashboard-acknowledgements REQ-ACK-002 outstanding-count pill. */
	display: inline-flex;
	align-items: center;
	padding: 2px 10px;
	border-radius: var(--border-radius-pill, 100px);
	background: var(--color-warning, #d97706);
	color: var(--color-primary-text, #fff);
	font-size: 0.8em;
	font-weight: 600;
	white-space: nowrap;
}

.launchpad-sidebar-toggle {
	/* Hint that the sidebar opens from the left even though the toggle
	   itself lives in the top-right cluster. */
	margin-right: auto;
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
	padding: 8px 0 0;
	overflow: auto;
	min-height: calc(
		100vh - var(--header-height, 50px) - var(--body-container-margin, 8px)
	);
}

.launchpad-empty,
.launchpad-loading {
	display: flex;
	align-items: center;
	justify-content: center;
	height: 100%;
	min-height: calc(
		100vh - var(--header-height, 50px) - var(--body-container-margin, 8px)
	);
}
</style>
