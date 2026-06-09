<!--
  - SPDX-FileCopyrightText: 2026 MyDash Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="catalog-view" data-test="catalog-view">
		<div class="catalog-view__header">
			<h2 class="catalog-view__title">
				{{ t('mydash', 'Widget catalog') }}
			</h2>
			<p class="catalog-view__subtitle">
				{{ t('mydash', 'Browse every widget you can add to a dashboard, grouped by category.') }}
			</p>
		</div>

		<!-- Sticky filter strip -->
		<div class="catalog-view__filters" data-test="catalog-filters">
			<button
				v-for="filter in filters"
				:key="filter.id"
				type="button"
				class="catalog-view__filter"
				:class="{ 'catalog-view__filter--active': activeFilter === filter.id }"
				:data-test="`catalog-filter-${filter.id}`"
				@click="activeFilter = filter.id">
				{{ filter.label }}
			</button>
		</div>

		<div
			v-for="group in visibleGroups"
			:key="group.id"
			class="catalog-view__group"
			:data-test="`catalog-group-${group.id}`">
			<button
				type="button"
				class="catalog-view__group-header"
				:data-test="`catalog-group-toggle-${group.id}`"
				@click="toggleGroup(group.id)">
				<ChevronDown v-if="isOpen(group.id)" :size="20" />
				<ChevronRight v-else :size="20" />
				<span>{{ group.label }}</span>
				<span class="catalog-view__group-count">{{ group.entries.length }}</span>
			</button>

			<div v-if="isOpen(group.id)" class="catalog-view__cards">
				<div
					v-for="entry in group.entries"
					:key="`${group.id}:${entry.type}`"
					class="catalog-view__card"
					data-test="catalog-card">
					<IconRenderer :name="entry.icon" :size="28" />
					<span class="catalog-view__card-name">{{ entry.displayName }}</span>
					<span class="catalog-view__card-type">{{ entry.type }}</span>
				</div>
			</div>
		</div>

		<NcEmptyContent
			v-if="visibleGroups.length === 0"
			:name="t('mydash', 'No widgets match this filter')">
			<template #icon>
				<ViewGridPlus :size="48" />
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import { NcEmptyContent } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import ViewGridPlus from 'vue-material-design-icons/ViewGridPlus.vue'
import IconRenderer from '../components/Dashboard/IconRenderer.vue'
import { listCatalogEntries, CATALOG_CATEGORIES } from '../constants/widgetRegistry.js'
import { widgetBridge } from '../services/widgetBridge.js'

/**
 * localStorage key for the persisted open / closed state of catalog category
 * groups (widgets spec: groups MUST remember their open state across reloads).
 *
 * @type {string}
 */
export const CATALOG_OPEN_GROUPS_KEY = 'mydash.catalog.openGroups'

/**
 * CatalogView — full-region widget browse view for the Catalog SUB_PAGE
 * (widgets + legacy-widget-bridge specs). Lists every registered widget
 * grouped by category (Built-in, Custom Tiles, Bridge), with a sticky filter
 * strip and per-group collapse state persisted to localStorage. This is a
 * read-only discovery surface — separate from the modal `WidgetPicker` that
 * adds widgets to the canvas.
 */
export default {
	name: 'CatalogView',

	components: {
		NcEmptyContent,
		IconRenderer,
		ChevronDown,
		ChevronRight,
		ViewGridPlus,
	},

	props: {
		/**
		 * Optional explicit bridge widget ids (injectable for tests). When
		 * omitted the runtime `widgetBridge` adapter is consulted.
		 */
		bridgeIds: {
			type: Array,
			default: null,
		},
	},

	data() {
		return {
			activeFilter: 'all',
			openGroups: this.loadOpenGroups(),
		}
	},

	computed: {
		/**
		 * Resolved catalog entries — registry types plus runtime bridge ids.
		 *
		 * @return {Array<object>}
		 */
		entries() {
			const ids = this.bridgeIds !== null
				? this.bridgeIds
				: this.resolveBridgeIds()
			return listCatalogEntries(ids)
		},

		/**
		 * Filter buttons — one per category plus an "All" option.
		 *
		 * @return {Array<{id: string, label: string}>}
		 */
		filters() {
			return [
				{ id: 'all', label: t('mydash', 'All') },
				{ id: CATALOG_CATEGORIES.BUILT_IN, label: t('mydash', 'Built-in') },
				{ id: CATALOG_CATEGORIES.CUSTOM_TILE, label: t('mydash', 'Custom Tiles') },
				{ id: CATALOG_CATEGORIES.BRIDGE, label: t('mydash', 'Bridge') },
			]
		},

		/**
		 * Entries grouped by category, honouring the active filter. Empty
		 * groups are dropped so the layout never shows a bare heading.
		 *
		 * @return {Array<{id: string, label: string, entries: Array<object>}>}
		 */
		visibleGroups() {
			const groupDefs = [
				{ id: CATALOG_CATEGORIES.BUILT_IN, label: t('mydash', 'Built-in') },
				{ id: CATALOG_CATEGORIES.CUSTOM_TILE, label: t('mydash', 'Custom Tiles') },
				{ id: CATALOG_CATEGORIES.BRIDGE, label: t('mydash', 'Bridge') },
			]
			return groupDefs
				.filter(g => this.activeFilter === 'all' || this.activeFilter === g.id)
				.map(g => ({
					...g,
					entries: this.entries.filter(e => e.category === g.id),
				}))
				.filter(g => g.entries.length > 0)
		},
	},

	methods: {
		t,

		/**
		 * Resolve the runtime bridge widget ids defensively — the bridge may
		 * be unavailable in a test or SSR context.
		 *
		 * @return {string[]}
		 */
		resolveBridgeIds() {
			try {
				if (widgetBridge && typeof widgetBridge.getRegisteredWidgetIds === 'function') {
					return widgetBridge.getRegisteredWidgetIds()
				}
			} catch (e) {
				// Bridge not ready — show the catalog without bridge widgets.
			}
			return []
		},

		/**
		 * Read the persisted open-groups map from localStorage. Defaults to
		 * all groups open on first visit.
		 *
		 * @return {Object<string, boolean>}
		 */
		loadOpenGroups() {
			const fallback = {
				[CATALOG_CATEGORIES.BUILT_IN]: true,
				[CATALOG_CATEGORIES.CUSTOM_TILE]: true,
				[CATALOG_CATEGORIES.BRIDGE]: true,
			}
			try {
				if (typeof localStorage === 'undefined') {
					return fallback
				}
				const raw = localStorage.getItem(CATALOG_OPEN_GROUPS_KEY)
				if (!raw) {
					return fallback
				}
				const parsed = JSON.parse(raw)
				return { ...fallback, ...parsed }
			} catch (e) {
				return fallback
			}
		},

		/**
		 * Whether a category group is currently open.
		 *
		 * @param {string} id the group id
		 * @return {boolean}
		 */
		isOpen(id) {
			return this.openGroups[id] !== false
		},

		/**
		 * Toggle a group's open state and persist the new map.
		 *
		 * @param {string} id the group id
		 * @return {void}
		 */
		toggleGroup(id) {
			this.openGroups = {
				...this.openGroups,
				[id]: !this.isOpen(id),
			}
			try {
				if (typeof localStorage !== 'undefined') {
					localStorage.setItem(CATALOG_OPEN_GROUPS_KEY, JSON.stringify(this.openGroups))
				}
			} catch (e) {
				// Persistence best-effort; state still toggles for the session.
			}
		},
	},
}
</script>

<style scoped>
.catalog-view {
	padding: 24px;
	max-width: 1000px;
	margin: 0 auto;
}

.catalog-view__title {
	margin: 0 0 4px;
}

.catalog-view__subtitle {
	color: var(--color-text-maxcontrast);
	margin: 0 0 16px;
}

.catalog-view__filters {
	position: sticky;
	top: 0;
	z-index: 2;
	display: flex;
	flex-wrap: wrap;
	gap: 8px;
	padding: 8px 0;
	background: var(--color-main-background);
	border-bottom: 1px solid var(--color-border);
	margin-bottom: 16px;
}

.catalog-view__filter {
	background: transparent;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-pill);
	padding: 4px 14px;
	cursor: pointer;
	font: inherit;
	color: var(--color-text-maxcontrast);
}

.catalog-view__filter--active {
	background: var(--color-primary-element);
	color: var(--color-primary-text);
	border-color: var(--color-primary-element);
}

.catalog-view__group {
	margin-bottom: 16px;
}

.catalog-view__group-header {
	display: flex;
	align-items: center;
	gap: 8px;
	width: 100%;
	background: transparent;
	border: 0;
	padding: 8px 0;
	cursor: pointer;
	font: inherit;
	font-weight: 600;
	color: var(--color-main-text);
}

.catalog-view__group-count {
	color: var(--color-text-maxcontrast);
	font-weight: 400;
}

.catalog-view__cards {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
	gap: 12px;
	margin-top: 8px;
}

.catalog-view__card {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 6px;
	padding: 16px;
	background: var(--color-background-dark);
	border-radius: var(--border-radius);
	text-align: center;
}

.catalog-view__card-name {
	font-weight: 500;
}

.catalog-view__card-type {
	font-size: 12px;
	color: var(--color-text-maxcontrast);
}
</style>
