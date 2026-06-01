<!-- SPDX-License-Identifier: EUPL-1.2 -->

<template>
	<div class="workspace-shell" :class="orgNavWrapperClass">
		<!-- Org-wide navigation rail (REQ-ONAV-005, REQ-ONAV-008).
		     Rendered above the shell when position='top', otherwise as
		     a side rail. The component itself decides whether to
		     render anything based on the empty-state + position rules. -->
		<OrgNavigationPanel v-if="orgNavStore.shouldRender" />

		<!-- Region 1: sidebar backdrop (REQ-SHELL-006).
		     Fixed-position overlay behind the slide-in sidebar. Clicking
		     the backdrop emits `close` → `sidebarOpen = false`. The
		     sidebar panel itself uses @click.stop so clicks inside never
		     propagate to the backdrop. Starts at top:50px to clear the
		     Nextcloud header chrome. -->
		<SidebarBackdrop
			v-if="sidebarOpen"
			@close="closeSidebar" />

		<!-- Region 2: hamburger + active-dashboard label strip
		     (REQ-SHELL-004). Always visible regardless of canEdit.
		     The hamburger uses NcButton tertiary so its hover/focus
		     ring matches the Nextcloud account-menu button. The
		     active dashboard's name is rendered as a static label —
		     dashboard switching is owned by the left sidebar
		     (REQ-SWITCH-002). -->
		<!-- Title strip is only visible when the sidebar is OPEN.
		     When closed, the floating sidebar-toggle in mydash-floating-controls
		     (top-right) is the only entry point. -->
		<div v-if="sidebarOpen" class="workspace-shell__strip">
			<NcButton
				type="tertiary"
				:aria-label="t('mydash', 'Open menu')"
				class="workspace-shell__hamburger"
				@click="toggleSidebar">
				<template #icon>
					<MenuIcon :size="20" />
				</template>
			</NcButton>
			<h1 class="workspace-shell__title">
				{{ activeDashboardName }}
			</h1>
		</div>

		<!-- Region 3: edit toolbar (REQ-SHELL-003, REQ-SHELL-002).
		     Uses v-if (NOT v-show) so non-edit users never see toolbar DOM.
		     Contains Add Widget dropdown and Save Layout button. The Save
		     button is disabled while a save request is in flight to prevent
		     double-submit (REQ-SHELL-003 save-in-flight scenario). -->
		<div v-if="canEdit && hasActiveDashboard" class="workspace-shell__toolbar">
			<div class="workspace-shell__add-widget-wrapper">
				<NcButton
					type="secondary"
					data-test="add-widget-toolbar-button"
					@click="showAddDropdown = !showAddDropdown">
					{{ t('mydash', 'Add Widget') }}
				</NcButton>
				<div v-if="showAddDropdown" class="workspace-shell__widget-dropdown">
					<button
						v-for="widget in injectedWidgets"
						:key="widget.id"
						type="button"
						class="workspace-shell__widget-type-item"
						@click="onAddWidget(widget)">
						{{ widget.name }}
					</button>
				</div>
			</div>
			<NcButton
				type="primary"
				class="workspace-shell__save-button"
				:disabled="saving"
				@click="saveLayout">
				{{ t('mydash', 'Save Layout') }}
			</NcButton>
		</div>

		<!-- Region 4: grid surface (or empty state).
		     The empty state branches on `allowUserDashboards`
		     (REQ-SHELL-005). When an active dashboard is resolved we
		     defer to the existing Views component which owns the
		     grid + per-widget modals — runtime-shell does not duplicate
		     widget machinery. -->
		<div class="workspace-shell__grid">
			<Views
				v-if="hasActiveDashboard"
				ref="viewsRef" />
			<div v-else class="workspace-shell__empty">
				<p class="workspace-shell__empty-title">
					{{ t('mydash', 'No dashboards available') }}
				</p>
				<p v-if="injectedAllowUserDashboards" class="workspace-shell__empty-hint">
					{{ t('mydash', 'Create your first dashboard') }}
				</p>
				<p v-else class="workspace-shell__empty-hint">
					{{ t('mydash', 'Contact your administrator') }}
				</p>
				<button
					v-if="injectedAllowUserDashboards"
					type="button"
					class="workspace-shell__empty-cta"
					@click="onCreateFirstDashboard">
					{{ t('mydash', 'Create your first dashboard') }}
				</button>
			</div>
		</div>

		<!-- Region 5 (footer-customization): branded footer below the
		     dashboard grid. Renders nothing when `effectiveFooter` is
		     null (REQ-FTR-001 disabled scenario, REQ-FTR-006 hidden
		     mode). -->
		<DashboardFooter
			:footer="effectiveFooter"
			:locale="injectedLocale" />
	</div>
</template>

<script>
import { NcButton } from '@nextcloud/vue'
import { t } from '@nextcloud/l10n'
import { showSuccess, showError } from '@nextcloud/dialogs'
import MenuIcon from 'vue-material-design-icons/Menu.vue'

import Views from './Views.vue'
import DashboardFooter from '../components/DashboardFooter.vue'
import OrgNavigationPanel from '../components/OrgNavigationPanel.vue'
import SidebarBackdrop from '../components/Workspace/SidebarBackdrop.vue'

import { useDashboardStore } from '../stores/dashboard.js'
import { useOrgNavigationStore } from '../stores/orgNavigation.js'
import { api } from '../services/api.js'

/**
 * WorkspaceApp — the runtime-shell page-level orchestrator (REQ-SHELL-001..007).
 *
 * Owns the five-region page chrome: org nav rail, slide-in sidebar backdrop,
 * hamburger + active-dashboard label strip, edit toolbar (Add Widget + Save
 * Layout, gated by canEdit), and the grid container that either shows the
 * dashboard (delegating to Views.vue for widget machinery) or the empty-state
 * branch (REQ-SHELL-005).
 *
 * Permission rule (REQ-SHELL-002): `canEdit = isAdmin || dashboardSource === 'user'`.
 * Gates the edit toolbar (REQ-SHELL-003) and per-widget context menu entries.
 *
 * Initial-state contract (REQ-INIT-002): every key consumed here is
 * injected from the root `provide` set up in `main.js`. Defaults match
 * the JS reader so a missing key never produces `undefined`.
 *
 * Lifecycle (REQ-SHELL-007):
 *  - `mounted()` registers `document.click` after `nextTick()` so the
 *    grid-container ref is non-null before the listener fires.
 *  - `beforeDestroy()` removes the listener.
 *  - GridStack is owned by Views.vue; its destroy hook fires when Views
 *    unmounts (satisfying REQ-SHELL-007 grid-destroy scenario).
 *
 * @spec openspec/changes/runtime-shell/tasks.md#task-1
 */
export default {
	name: 'WorkspaceApp',

	components: {
		NcButton,
		MenuIcon,
		Views,
		DashboardFooter,
		OrgNavigationPanel,
		SidebarBackdrop,
	},

	inject: {
		injectedIsAdmin: {
			from: 'isAdmin',
			default: false,
		},
		injectedDashboardSource: {
			from: 'dashboardSource',
			default: 'group',
		},
		injectedActiveDashboardId: {
			from: 'activeDashboardId',
			default: '',
		},
		injectedAllowUserDashboards: {
			from: 'allowUserDashboards',
			default: false,
		},
		injectedLayout: {
			from: 'layout',
			default: () => [],
		},
		injectedWidgets: {
			from: 'widgets',
			default: () => [],
		},
		injectedUserDashboards: {
			from: 'userDashboards',
			default: () => [],
		},
		injectedGroupDashboards: {
			from: 'groupDashboards',
			default: () => [],
		},
		injectedPrimaryGroupName: {
			from: 'primaryGroupName',
			default: '',
		},
		injectedEffectiveFooter: {
			from: 'effectiveFooter',
			default: null,
		},
		injectedLocale: {
			from: 'viewerLocale',
			default: 'en',
		},
	},

	data() {
		return {
			sidebarOpen: false,
			// REQ-SHELL-003: true while a save PUT is in flight (disables Save button).
			saving: false,
			// REQ-SHELL-003: controls the Add Widget dropdown visibility.
			showAddDropdown: false,
			// Click handler kept on `this` so addEventListener and
			// removeEventListener see the same function reference.
			outsideClickHandler: null,
		}
	},

	computed: {
		/**
		 * REQ-SHELL-002 — admins can edit any dashboard; regular users
		 * can edit only their own personal dashboards.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/runtime-shell/tasks.md#task-3
		 */
		canEdit() {
			return Boolean(this.injectedIsAdmin) || this.injectedDashboardSource === 'user'
		},

		/**
		 * Whether the resolver returned an active dashboard. Drives the
		 * empty-state branch in Region 4 (REQ-SHELL-005).
		 *
		 * @return {boolean}
		 */
		hasActiveDashboard() {
			return Boolean(this.injectedActiveDashboardId)
		},

		/**
		 * Active dashboard's display name (REQ-SHELL-004 active-name
		 * scenario). Resolved from the union of group + user dashboards.
		 * Empty string when no active dashboard is resolved.
		 *
		 * @return {string}
		 * @spec openspec/changes/runtime-shell/tasks.md#task-6
		 */
		activeDashboardName() {
			if (!this.injectedActiveDashboardId) {
				return ''
			}
			const all = [
				...(this.injectedUserDashboards || []),
				...(this.injectedGroupDashboards || []),
			]
			const match = all.find(d => d && d.id === this.injectedActiveDashboardId)
			return match ? (match.name || '') : ''
		},

		/**
		 * Effective footer payload (REQ-FTR-001, REQ-FTR-004, REQ-FTR-006).
		 *
		 * @return {object | null}
		 */
		effectiveFooter() {
			if (!this.injectedActiveDashboardId) {
				return this.injectedEffectiveFooter
			}
			const all = [
				...(this.injectedUserDashboards || []),
				...(this.injectedGroupDashboards || []),
			]
			const match = all.find(d => d && d.id === this.injectedActiveDashboardId)
			if (match && Object.prototype.hasOwnProperty.call(match, 'effectiveFooter')) {
				return match.effectiveFooter
			}
			return this.injectedEffectiveFooter
		},

		/**
		 * REQ-ONAV-005 — exposed so the template can check `shouldRender`
		 * AND read `position` to drive the wrapper layout class.
		 *
		 * @return {object}
		 */
		orgNavStore() {
			return useOrgNavigationStore()
		},

		/**
		 * REQ-ONAV-005 — flex direction follows the rail position.
		 *
		 * @return {string[]}
		 */
		orgNavWrapperClass() {
			if (!this.orgNavStore.shouldRender) {
				return []
			}
			return ['workspace-shell--org-nav-' + (this.orgNavStore.position || 'hidden')]
		},
	},

	/**
	 * REQ-SHELL-007 — register the document-level click listener after
	 * `nextTick()` so the grid-container ref is non-null before the
	 * listener fires.
	 *
	 * @spec openspec/changes/runtime-shell/tasks.md#task-7
	 */
	mounted() {
		this.outsideClickHandler = (event) => {
			this.handleClickOutside(event)
		}
		this.$nextTick(() => {
			document.addEventListener('click', this.outsideClickHandler)
		})
	},

	/**
	 * REQ-SHELL-007 — drop the document listener and close any open
	 * dropdown so nothing leaks across mounts.
	 *
	 * @spec openspec/changes/runtime-shell/tasks.md#task-7
	 */
	beforeDestroy() {
		if (this.outsideClickHandler) {
			document.removeEventListener('click', this.outsideClickHandler)
			this.outsideClickHandler = null
		}
	},

	methods: {
		t,

		/** @spec openspec/changes/runtime-shell/tasks.md#task-6 */
		toggleSidebar() {
			this.sidebarOpen = !this.sidebarOpen
		},

		/** @spec openspec/changes/runtime-shell/tasks.md#task-6 */
		closeSidebar() {
			this.sidebarOpen = false
		},

		/**
		 * Document-click handler. Closes the Add Widget dropdown when the
		 * user clicks outside it (REQ-SHELL-003 dismiss flow).
		 *
		 * @param {MouseEvent} event the click event
		 * @return {void}
		 * @spec openspec/changes/runtime-shell/tasks.md#task-7
		 */
		handleClickOutside(event) {
			if (this.showAddDropdown) {
				const wrapper = this.$el && this.$el.querySelector('.workspace-shell__add-widget-wrapper')
				if (wrapper && !wrapper.contains(event.target)) {
					this.showAddDropdown = false
				}
			}
		},

		/**
		 * Open the widget picker for a selected type. Delegates to the
		 * embedded Views component so the widget-add-edit-modal capability
		 * remains the single owner of the type → submit pipeline
		 * (REQ-SHELL-003).
		 *
		 * @param {object} widget widget descriptor from the type registry
		 * @return {void}
		 * @spec openspec/changes/runtime-shell/tasks.md#task-4
		 */
		onAddWidget(widget) {
			this.showAddDropdown = false
			if (this.$refs.viewsRef && this.$refs.viewsRef.openCustomWidgetModal) {
				this.$refs.viewsRef.openCustomWidgetModal(widget)
			}
		},

		/**
		 * Persist the current widget layout to the backend (REQ-SHELL-003,
		 * REQ-SHELL-005). Routes the PUT by dashboardSource:
		 * - user dashboards → `PUT /api/dashboard/{uuid}`
		 * - group/default dashboards → `PUT /api/dashboard/{uuid}` via the
		 *   same endpoint (admin-gated on the backend side).
		 * Sets `saving = true` until the response resolves so the Save
		 * button is disabled during the in-flight request.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/runtime-shell/tasks.md#task-5
		 */
		async saveLayout() {
			if (!this.injectedActiveDashboardId || this.saving) {
				return
			}
			this.saving = true
			try {
				const store = useDashboardStore()
				const layout = store.widgetPlacements || this.injectedLayout
				await api.updateDashboard(this.injectedActiveDashboardId, { layout })
				showSuccess(this.t('mydash', 'Layout saved'))
			} catch (error) {
				showError(this.t('mydash', 'Failed to save layout'))
			} finally {
				this.saving = false
			}
		},

		/**
		 * Create the user's first personal dashboard from the empty-state
		 * CTA (REQ-SHELL-005 enabled scenario). Delegates to the dashboard
		 * store so the existing `POST /api/dashboard` flow is reused.
		 *
		 * @return {Promise<void>}
		 * @spec openspec/changes/runtime-shell/tasks.md#task-6
		 */
		async onCreateFirstDashboard() {
			const store = useDashboardStore()
			try {
				await store.createDashboard({
					name: this.t('mydash', 'My dashboard'),
				})
			} catch (error) {
				console.error('[WorkspaceApp] Failed to create first dashboard:', error)
			}
		},
	},
}
</script>

<style scoped>
.workspace-shell {
	min-height: 100vh;
	width: 100%;
	display: flex;
	flex-direction: column;
}

/* REQ-ONAV-005 — rail position drives the outer layout. */
.workspace-shell--org-nav-left,
.workspace-shell--org-nav-right {
	flex-direction: row;
}

.workspace-shell--org-nav-right {
	flex-direction: row-reverse;
}

.workspace-shell--org-nav-top {
	flex-direction: column;
}

.workspace-shell__strip {
	display: flex;
	align-items: center;
	gap: 12px;
	padding: 8px 16px;
	border-bottom: 1px solid var(--color-border, #ddd);
	background: var(--color-main-background, #fff);
}

.workspace-shell__hamburger {
	flex: 0 0 auto;
}

.workspace-shell__title {
	font-weight: 600;
	font-size: 1.05em;
	color: var(--color-main-text, #222);
	margin: 0;
}

/* REQ-SHELL-003: edit toolbar — only rendered when canEdit=true via v-if. */
.workspace-shell__toolbar {
	display: flex;
	align-items: center;
	gap: 8px;
	padding: 8px 16px;
	background: var(--color-main-background, #fff);
	border-bottom: 1px solid var(--color-border, #ddd);
}

.workspace-shell__add-widget-wrapper {
	position: relative;
}

/* Add Widget dropdown — appears below the Add Widget button. */
.workspace-shell__widget-dropdown {
	position: absolute;
	top: calc(100% + 4px);
	left: 0;
	z-index: 1000;
	background: var(--color-main-background, #fff);
	border: 1px solid var(--color-border, #ddd);
	border-radius: var(--border-radius, 4px);
	min-width: 180px;
	box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.workspace-shell__widget-type-item {
	display: block;
	width: 100%;
	padding: 8px 12px;
	background: none;
	border: none;
	text-align: left;
	cursor: pointer;
	font: inherit;
	color: var(--color-main-text, #222);
}

.workspace-shell__widget-type-item:hover {
	background: var(--color-background-hover, #f5f5f5);
}

.workspace-shell__grid {
	flex: 1;
	overflow: auto;
}

.workspace-shell__empty {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	height: 100%;
	min-height: 60vh;
	text-align: center;
	gap: 12px;
}

.workspace-shell__empty-title {
	font-size: 1.3em;
	font-weight: 600;
	margin: 0;
}

.workspace-shell__empty-hint {
	color: var(--color-text-maxcontrast, #555);
	margin: 0;
}

.workspace-shell__empty-cta {
	background: var(--color-primary-element, #1976d2);
	color: var(--color-primary-element-text, #fff);
	border: none;
	border-radius: var(--border-radius, 4px);
	padding: 8px 16px;
	cursor: pointer;
	font: inherit;
	margin-top: 8px;
}
</style>

<!--
  Global (unscoped) layout rules for the chrome wrapper. Nextcloud's
  `#app-workspace` is a `display: flex` row container. MyDash opts out of
  the navigation rail (PageController sets `id-app-navigation: null`), so
  the workspace wrapper must claim the full available width — without this,
  `.launchpad-workspace` collapses to 0px and the dashboard grid renders empty.
-->
<style>
.launchpad-workspace,
#app-workspace.launchpad-workspace,
#app-workspace.launchpad-workspace #workspace-vue {
	flex: 1 1 auto;
	min-width: 0;
	width: 100%;
	min-height: 100vh;
}
</style>
