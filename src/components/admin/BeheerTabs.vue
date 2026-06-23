<!--
  - SPDX-FileCopyrightText: 2026 LaunchPad Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="beheer-tabs" data-test="beheer-tabs">
		<div
			class="beheer-tabs__strip"
			role="tablist"
			:aria-label="t('launchpad', 'Administration areas')">
			<button
				v-for="tab in tabs"
				:key="tab.slug"
				type="button"
				role="tab"
				class="beheer-tabs__tab"
				:class="{ 'beheer-tabs__tab--active': tab.slug === activeTab }"
				:data-test="`tab-${tab.slug}`"
				:aria-selected="String(tab.slug === activeTab)"
				@click="selectTab(tab.slug)">
				{{ tab.label }}
			</button>
		</div>

		<div
			class="beheer-tabs__panel"
			role="tabpanel"
			:data-test="`panel-${activeTab}`">
			<slot :name="activeTab" />
		</div>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'

/**
 * Storage key for the persisted active Beheer tab. Exported so tests and
 * the admin shell agree on the exact key (REQ-ASET tabbed Beheer layout).
 *
 * @type {string}
 */
export const ACTIVE_TAB_STORAGE_KEY = 'mydash.admin.activeTab'

/**
 * BeheerTabs — a lightweight, router-free tab strip that organises the
 * admin settings page into the IA's discrete Beheer areas. The active tab
 * resolves with the precedence:
 *   1. a `?tab=` query string (deep-link round-trip), else
 *   2. the persisted `localStorage` value, else
 *   3. the `defaultTab` prop (Templates by default).
 *
 * Only the active tab's slot is rendered, so no other tab's content sits in
 * the DOM (admin-settings spec: "no other tab's content MUST be in the DOM").
 * Selecting a tab writes the slug to `localStorage` so it survives reloads.
 */
export default {
	name: 'BeheerTabs',

	props: {
		/**
		 * Ordered tab descriptors: `{ slug, label }`. The parent owns the
		 * labels so they stay translatable in one place.
		 */
		tabs: {
			type: Array,
			required: true,
			validator: (value) => Array.isArray(value)
				&& value.every((tab) => typeof tab.slug === 'string' && typeof tab.label === 'string'),
		},

		/**
		 * Slug used when neither a `?tab=` query nor a persisted value is
		 * present (admin-settings spec: Templates is the canonical landing).
		 */
		defaultTab: {
			type: String,
			default: '',
		},
	},

	emits: ['change'],

	data() {
		return {
			activeTab: '',
		}
	},

	/** @spec openspec/specs/admin-settings/spec.md */
	created() {
		this.activeTab = this.resolveInitialTab()
		this.$emit('change', this.activeTab)
	},

	methods: {
		t,

		/**
		 * Resolve the tab to show on first paint. The `?tab=` query string
		 * wins so deep-links round-trip on reload; otherwise the persisted
		 * value, otherwise the `defaultTab`, otherwise the first tab. The
		 * resolved value is always one of the known tab slugs.
		 *
		 * @return {string} The slug to activate.
		 */
		resolveInitialTab() {
			const known = this.tabs.map((tab) => tab.slug)
			const fallback = known.includes(this.defaultTab)
				? this.defaultTab
				: (known[0] || '')

			const fromQuery = this.readQueryTab()
			if (fromQuery && known.includes(fromQuery)) {
				return fromQuery
			}

			const fromStorage = this.readStoredTab()
			if (fromStorage && known.includes(fromStorage)) {
				return fromStorage
			}

			return fallback
		},

		/**
		 * Read the `tab` query-string parameter, tolerating environments
		 * (tests, SSR) where `window`/`URLSearchParams` is unavailable.
		 *
		 * @return {string|null} The requested tab slug, or null.
		 */
		readQueryTab() {
			try {
				if (typeof window === 'undefined' || !window.location) {
					return null
				}
				const params = new URLSearchParams(window.location.search || '')
				return params.get('tab')
			} catch (e) {
				return null
			}
		},

		/**
		 * Read the persisted tab slug from `localStorage`.
		 *
		 * @return {string|null} The persisted slug, or null.
		 */
		readStoredTab() {
			try {
				if (typeof localStorage === 'undefined') {
					return null
				}
				return localStorage.getItem(ACTIVE_TAB_STORAGE_KEY)
			} catch (e) {
				return null
			}
		},

		/**
		 * Activate a tab, persist the choice, and notify the parent. A
		 * no-op when the slug is already active.
		 *
		 * @param {string} slug The tab slug to activate.
		 * @return {void}
		 */
		selectTab(slug) {
			if (slug === this.activeTab) {
				return
			}
			this.activeTab = slug
			try {
				if (typeof localStorage !== 'undefined') {
					localStorage.setItem(ACTIVE_TAB_STORAGE_KEY, slug)
				}
			} catch (e) {
				// localStorage may be unavailable (private mode); the tab
				// still switches for the current session.
			}
			this.$emit('change', slug)
		},
	},
}
</script>

<style scoped>
.beheer-tabs__strip {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
	border-bottom: 1px solid var(--color-border);
	margin-bottom: 24px;
}

.beheer-tabs__tab {
	background: transparent;
	border: 0;
	border-bottom: 2px solid transparent;
	padding: 10px 16px;
	cursor: pointer;
	font: inherit;
	color: var(--color-text-maxcontrast);
	border-radius: var(--border-radius) var(--border-radius) 0 0;
}

.beheer-tabs__tab:hover,
.beheer-tabs__tab:focus-visible {
	background: var(--color-background-hover);
	color: var(--color-main-text);
	outline: none;
}

.beheer-tabs__tab--active {
	color: var(--color-main-text);
	border-bottom-color: var(--color-primary-element);
	font-weight: 600;
}

.beheer-tabs__panel {
	min-height: 120px;
}
</style>
