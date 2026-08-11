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
		     When closed, the floating sidebar-toggle in launchpad-floating-controls
		     (top-right) is the only entry point. -->
		<div v-if="sidebarOpen" class="workspace-shell__strip">
			<NcButton
				type="tertiary"
				:aria-label="t('launchpad', 'Open menu')"
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

		<!-- Region 3 (edit toolbar) removed: editing actions (Edit/Save
		     dashboard, Add custom widget) live in the per-dashboard cog
		     menu (DashboardRowActions) so the page chrome stays clean. -->

		<!-- Region 3.5: quick-search / launcher bar (tile-quick-search
		     REQ-QSEARCH-001). Always visible above the grid regardless of
		     sidebar state — `/` and Ctrl+K focus it from anywhere on the
		     page. Filtering/activation reach into Views.vue's rendered grid
		     via a plain DOM query (see `applySearchDimming` /
		     `activateSearchResult` below) because the grid DOM lives in a
		     sibling component's tree. -->
		<div v-if="hasActiveDashboard" class="workspace-shell__search-bar">
			<RuntimeShellSearch
				:items="searchableTiles"
				:fallback-target="quicksearchFallbackTarget"
				@open="onSearchOpen"
				@filter="onSearchFilter"
				@fallback="onSearchFallback"
				@clear="onSearchClear" />
		</div>

		<!-- Region 4: grid surface (or empty state).
		     The empty state branches on `allowUserDashboards`
		     (REQ-SHELL-005). When an active dashboard is resolved we
		     defer to the existing Views component which owns the
		     grid + per-widget modals — runtime-shell does not duplicate
		     widget machinery. `tabindex="-1"` makes the grid a valid
		     programmatic focus target for the quick-search Esc contract
		     (REQ-QSEARCH-003 "Escape clears and returns focus"). -->
		<div
			id="launchpad-main-content"
			class="workspace-shell__grid"
			tabindex="-1">
			<Views
				v-if="hasActiveDashboard"
				ref="viewsRef" />
			<div v-else class="workspace-shell__empty">
				<p class="workspace-shell__empty-title">
					{{ t('launchpad', 'No dashboards available') }}
				</p>
				<p v-if="injectedAllowUserDashboards" class="workspace-shell__empty-hint">
					{{ t('launchpad', 'Create your first dashboard') }}
				</p>
				<p v-else class="workspace-shell__empty-hint">
					{{ t('launchpad', 'Contact your administrator') }}
				</p>
				<button
					v-if="injectedAllowUserDashboards"
					type="button"
					class="workspace-shell__empty-cta"
					@click="onCreateFirstDashboard">
					{{ t('launchpad', 'Create your first dashboard') }}
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
import { mapState } from 'pinia'
import MenuIcon from 'vue-material-design-icons/Menu.vue'

import Views from './Views.vue'
import DashboardFooter from '../components/DashboardFooter.vue'
import OrgNavigationPanel from '../components/OrgNavigationPanel.vue'
import RuntimeShellSearch from '../components/RuntimeShellSearch.vue'
import SidebarBackdrop from '../components/Workspace/SidebarBackdrop.vue'

import { useDashboardStore } from '../stores/dashboard.js'
import { useOrgNavigationStore } from '../stores/orgNavigation.js'
import { useWidgetStore } from '../stores/widgets.js'

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
		RuntimeShellSearch,
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
		/**
		 * tile-quick-search REQ-QSEARCH-004 — the admin-configured no-match
		 * fallback target. Optional initial-state key (mirrors
		 * `deepLinkPath`'s pattern): older servers that haven't deployed the
		 * PHP side yet simply omit it, and the default keeps the reader
		 * typed. See `src/utils/loadInitialState.js`.
		 */
		injectedQuicksearchFallbackTarget: {
			from: 'quicksearchFallbackTarget',
			default: 'none',
		},
	},

	data() {
		return {
			sidebarOpen: false,
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

		...mapState(useDashboardStore, ['widgetPlacements']),
		...mapState(useWidgetStore, ['availableWidgets']),

		/**
		 * tile-quick-search REQ-QSEARCH-002 — the current dashboard's
		 * searchable items, `{id, label, placement}`. Sourced from the live
		 * Pinia store (not the static initial-state snapshot) so the quick
		 * search bar re-filters correctly after any placement add/remove/
		 * dashboard switch without a page reload.
		 *
		 * @return {Array<{id: string, label: string, placement: object}>}
		 * @spec openspec/specs/tile-quick-search/spec.md
		 */
		searchableTiles() {
			return (this.widgetPlacements || []).map((placement) => ({
				id: placement.id,
				label: this.tileSearchLabel(placement),
				placement,
			}))
		},

		/**
		 * tile-quick-search REQ-QSEARCH-004 — the admin-configured no-match
		 * fallback target, straight from the typed initial-state contract.
		 * `none` is the default, which is the REQ-QSEARCH-004 "No fallback
		 * configured" branch — an unset server never silently navigates.
		 *
		 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-004
		 * @return {string}
		 */
		quicksearchFallbackTarget() {
			return this.injectedQuicksearchFallbackTarget
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
		 * @spec openspec/specs/navigation-editor-org/spec.md#req-onav-005
		 * @return {string[]}
		 */
		orgNavWrapperClass() {
			if (!this.orgNavStore.shouldRender) {
				return []
			}
			return ['workspace-shell--org-nav-' + (this.orgNavStore.position || 'hidden')]
		},
	},

	mounted() {
		document.addEventListener('keydown', this.onDocumentKeydown)
	},

	beforeUnmount() {
		document.removeEventListener('keydown', this.onDocumentKeydown)
	},

	methods: {
		t,

		/**
		 * Escape closes the slide-in sidebar (WCAG 2.2 AA SC 2.1.1 Keyboard).
		 *
		 * The sidebar could be dismissed two ways, and both needed a mouse:
		 * clicking the backdrop, or clicking the hamburger again. The
		 * backdrop is a `role="presentation"` overlay that never takes focus,
		 * so a `@keydown` bound to the backdrop element itself could never
		 * fire — the listener has to be on the document to be reachable at
		 * all, whatever happens to hold focus when the sidebar opens.
		 *
		 * Guarded on `sidebarOpen` so this does not swallow Escape from the
		 * quick-search bar (REQ-QSEARCH-003), which owns its own Escape
		 * contract and is only reachable while the sidebar is closed.
		 *
		 * @param {KeyboardEvent} event the document keydown.
		 * @return {void}
		 * @spec openspec/specs/runtime-shell/spec.md
		 */
		onDocumentKeydown(event) {
			if (event.key === 'Escape' && this.sidebarOpen) {
				this.closeSidebar()
			}
		},

		/** @spec openspec/changes/runtime-shell/tasks.md#task-6 */
		toggleSidebar() {
			this.sidebarOpen = !this.sidebarOpen
		},

		/** @spec openspec/changes/runtime-shell/tasks.md#task-6 */
		closeSidebar() {
			this.sidebarOpen = false
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
					name: this.t('launchpad', 'My dashboard'),
				})
			} catch (error) {
				console.error('[WorkspaceApp] Failed to create first dashboard:', error)
			}
		},

		/**
		 * Resolve a placement's quick-search display label
		 * (tile-quick-search REQ-QSEARCH-002). Mirrors the exact rule the
		 * grid itself uses so search results read like the rendered tile
		 * titles: tile placements use `tileTitle`; every other placement
		 * uses `customTitle || widget.title || 'Widget'`
		 * (`WidgetWrapper.vue`'s `widgetTitle` computed).
		 *
		 * @param {object} placement a `widgetPlacements` row.
		 * @return {string} the label to search/display for this placement.
		 * @spec openspec/specs/tile-quick-search/spec.md
		 */
		tileSearchLabel(placement) {
			if (placement.tileType === 'custom') {
				return placement.tileTitle || this.t('launchpad', 'Tile')
			}
			const widget = (this.availableWidgets || []).find((w) => w.id === placement.widgetId)
			return placement.customTitle || widget?.title || this.t('launchpad', 'Widget')
		},

		/**
		 * REQ-QSEARCH-003 "Enter opens the selected tile" scenario. `item`
		 * is a `searchableTiles` entry (`{id, label, placement}`); the
		 * actual DOM activation is a plain-query concern because the grid
		 * lives in the sibling `Views` component's tree, not this
		 * component's own template.
		 *
		 * @param {{id: string, label: string, placement: object}} item the
		 *   opened search result.
		 * @return {void}
		 * @spec openspec/specs/tile-quick-search/spec.md
		 */
		onSearchOpen(item) {
			this.activateSearchResult(item)
		},

		/**
		 * REQ-QSEARCH-002 "Typing filters tiles by label" scenario:
		 * non-matching tiles are de-emphasised (dimmed via CSS class), not
		 * removed from the grid layout. `matchIds` is `null` when the query
		 * is empty (undim everything) or an array of matching placement ids
		 * otherwise (may be empty — dim everything).
		 *
		 * @param {Array<string>|null} matchIds the current matching ids.
		 * @return {void}
		 * @spec openspec/specs/tile-quick-search/spec.md
		 */
		onSearchFilter(matchIds) {
			this.applySearchDimming(matchIds)
		},

		/**
		 * REQ-QSEARCH-004 no-match fallback. `RuntimeShellSearch` only
		 * resolves *which* action to take (pure decision, unit-tested in
		 * `useTileSearch.spec.js`); this page performs the actual
		 * side-effect since window/navigation access is a host-page concern.
		 *
		 * @param {{type: string, url?: string, query?: string}} action the
		 *   resolved fallback action.
		 * @return {void}
		 * @spec openspec/specs/tile-quick-search/spec.md
		 */
		onSearchFallback(action) {
			if (!action) {
				return
			}
			if (action.type === 'web-search' && action.url) {
				window.open(action.url, '_blank', 'noopener,noreferrer')
				return
			}
			if (action.type === 'unified-search') {
				// Best-effort hand-off (REQ-QSEARCH-004 "Unified-search
				// fallback" scenario): dispatch a CustomEvent Nextcloud's
				// own unified-search UI can listen for. Nothing else is
				// touched — if no listener is present the dashboard simply
				// stays put, satisfying "MUST NOT navigate away from the
				// dashboard on its own beyond what the unified-search
				// integration does".
				window.dispatchEvent(new CustomEvent('nextcloud:unified-search.search', {
					detail: { query: action.query },
				}))
			}
			// 'none' — RuntimeShellSearch already renders the accessible
			// no-results message; no further action here.
		},

		/**
		 * REQ-QSEARCH-003 "Escape clears and returns focus" scenario:
		 * undim every tile and move focus to the grid container.
		 *
		 * @return {void}
		 * @spec openspec/specs/tile-quick-search/spec.md
		 */
		onSearchClear() {
			this.applySearchDimming(null)
			this.focusGrid()
		},

		/**
		 * Toggle the `launchpad-grid-item--dimmed` class on every rendered
		 * grid item (queried via `data-placement-id`, set by `Views.vue`).
		 * `null` undims everything; an array dims every item whose id is
		 * NOT present (an empty array therefore dims everything).
		 *
		 * @param {Array<string|number>|null} matchIds the current matching ids
		 *   (numbers off the API row, strings once normalised below).
		 * @return {void}
		 * @spec openspec/specs/tile-quick-search/spec.md
		 */
		applySearchDimming(matchIds) {
			if (typeof document === 'undefined') {
				return
			}
			const items = this.$el?.querySelectorAll('.launchpad-grid-item[data-placement-id]')
			if (!items) {
				return
			}
			/*
			 * A PLACEMENT ID IS A NUMBER; A DOM ATTRIBUTE IS A STRING.
			 * `matchIds` comes from `searchableTiles()`, which copies
			 * `placement.id` straight off the API row — an integer. The value
			 * read back out of a rendered cell is `getAttribute()`, which is
			 * always a string. `Array.prototype.includes` compares with
			 * SameValueZero, i.e. no coercion at all, so `[7].includes('7')`
			 * is `false` — EVERY tile was dimmed on every query, including
			 * the matches the user was looking for (launchpad#95).
			 * Normalising both sides to strings makes the comparison one
			 * between two values of the same type.
			 */
			const wanted = matchIds === null ? null : matchIds.map((id) => String(id))
			items.forEach((el) => {
				if (wanted === null) {
					el.classList.remove('launchpad-grid-item--dimmed')
					return
				}
				const id = el.getAttribute('data-placement-id')
				el.classList.toggle('launchpad-grid-item--dimmed', wanted.includes(id) === false)
			})
		},

		/**
		 * Activate a search result's rendered tile in the grid: scroll it
		 * into view, then click its link (honouring whatever
		 * `target="_blank"`/`_self` `TileWidget.vue` already rendered —
		 * REQ-QSEARCH-003 "honouring its configured link target"). Non-tile
		 * placements without a link are focused instead, best-effort.
		 *
		 * @param {{id: (string|number), placement: object}} item the opened
		 *   search result. `placement.id` is an INTEGER off the API row.
		 * @return {void}
		 * @spec openspec/specs/tile-quick-search/spec.md
		 */
		activateSearchResult(item) {
			/*
			 * `String(...)`, not the raw value. `placement.id` is an INTEGER
			 * off the API row, and the line below used to call
			 * `placementId.replace(...)` on it. `Number.prototype.replace`
			 * does not exist, so this threw a `TypeError` on every single
			 * activation — inside a Vue event handler, where nothing surfaces
			 * it, so pressing Enter on a search result silently did nothing
			 * (launchpad#95). The truthiness guard above did not catch it: a
			 * non-zero integer is truthy.
			 *
			 * `?? ''` rather than a bare cast so that `null`/`undefined`
			 * become the empty string and are rejected by the guard, instead
			 * of being stringified into the literal `"null"` and sent to
			 * `querySelector` as a real id to look for.
			 */
			const placementId = String(item?.placement?.id ?? '')
			if (!placementId || !this.$el) {
				return
			}
			const el = this.$el.querySelector(
				`.launchpad-grid-item[data-placement-id="${placementId.replace(/"/g, '\\"')}"]`,
			)
			if (!el) {
				return
			}
			if (typeof el.scrollIntoView === 'function') {
				el.scrollIntoView({ behavior: 'smooth', block: 'center' })
			}
			const link = el.querySelector('a[href]')
			if (link && typeof link.click === 'function') {
				link.click()
				return
			}
			if (typeof el.focus === 'function') {
				if (!el.hasAttribute('tabindex')) {
					el.setAttribute('tabindex', '-1')
				}
				el.focus({ preventScroll: true })
			}
		},

		/**
		 * Move focus to the grid container (REQ-QSEARCH-003 "Escape clears
		 * and returns focus" — "focus MUST return to the tile grid"). The
		 * search bar is a sibling of the grid, so it cannot move focus there
		 * itself; it emits `clear` and this owner does it.
		 *
		 * @spec openspec/specs/tile-quick-search/spec.md#req-qsearch-003
		 * @return {void}
		 */
		focusGrid() {
			const grid = this.$el?.querySelector('.workspace-shell__grid')
			if (grid && typeof grid.focus === 'function') {
				grid.focus({ preventScroll: true })
			}
		},
	},
}
</script>

<style scoped>
.workspace-shell {
	/* Bound to the content area (height:100%) and allow flex children to
	   shrink (min-height:0) so .workspace-shell__grid scrolls internally
	   rather than the shell overflowing #content unbounded. */
	height: 100%;
	min-height: 0;
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
	/* min-height:0 is required for the flex item to shrink below its content
	   height; without it overflow:auto never engages and the grid grows
	   unbounded, clipping silently out of the viewport. */
	min-height: 0;
	overflow: auto;
}

/* `tabindex="-1"` makes the grid a programmatic-only focus target (REQ-
   QSEARCH-003 Esc-returns-focus scenario) — it never joins the tab order,
   so it needs no visible focus ring for keyboard *tabbing* users, but a
   ring on programmatic focus still helps orient someone who just pressed
   Esc from the search bar. */
.workspace-shell__grid:focus-visible {
	outline: 2px solid var(--color-primary-element, #0082c9);
	outline-offset: -2px;
}

/* tile-quick-search REQ-QSEARCH-001: the bar sits above the tile grid,
   full width, matching the title-strip's horizontal padding. */
.workspace-shell__search-bar {
	padding: 8px 16px;
	background: var(--color-main-background, #fff);
	border-bottom: 1px solid var(--color-border, #ddd);
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
  `#app-workspace` is a `display: flex` row container. LaunchPad opts out of
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
	/* See src/styles/workspace.css — fill the content area, don't grow past it. */
	height: 100%;
	min-height: 0;
}
</style>
