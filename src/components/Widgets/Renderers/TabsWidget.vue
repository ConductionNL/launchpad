<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="tabs-widget" data-testid="tabs-widget">
		<div
			v-if="tabs.length > 0"
			class="tabs-widget__strip"
			role="tablist"
			:aria-label="stripLabel"
			@keydown="onKeydown">
			<button
				v-for="(tab, index) in tabs"
				:id="tabId(index)"
				:key="tab.id"
				ref="tabButtons"
				type="button"
				role="tab"
				class="tabs-widget__tab"
				:class="{ 'tabs-widget__tab--active': index === activeIndex }"
				:aria-selected="index === activeIndex ? 'true' : 'false'"
				:aria-controls="panelId(index)"
				:tabindex="index === activeIndex ? 0 : -1"
				:data-testid="`tabs-widget-tab-${index}`"
				@click="select(index)">
				{{ tabTitle(tab, index) }}
			</button>
		</div>
		<p v-else class="tabs-widget__empty" data-testid="tabs-widget-empty">
			{{ t('launchpad', 'No tabs yet. Add one in the widget settings.') }}
		</p>

		<div
			v-if="activeTab"
			:id="panelId(activeIndex)"
			:key="activeTab.id"
			role="tabpanel"
			class="tabs-widget__panel"
			:aria-labelledby="tabId(activeIndex)"
			data-testid="tabs-widget-panel">
			<ContainerWidget
				:content="panelContent"
				:placement="panelPlacement"
				:editMode="editMode"
				@update:content="onPanelContent" />
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import ContainerWidget from './ContainerWidget.vue'
import { FLAG_PROP, OBJECT_PROP } from '../../../constants/propDefinitions.js'
import { normaliseTabs } from '../../../utils/tabsWidget.js'

let instanceCounter = 0

/**
 * TabsWidget: the `tabs` widget type (layout-widgets-replace-multi-tile D1).
 * A container with tabs: `content.tabs` is a list of `{id, title,
 * placements}`, and the open tab's placements render on the container's inner
 * grid (REQ-CONT-002, -003), so each tab holds any widget a well can hold.
 * The strip follows the WAI-ARIA tabs pattern: arrow keys move between tabs,
 * Home and End jump to the first and last. The open tab is kept for the page
 * load only.
 *
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
 */
export default {
	name: 'TabsWidget',

	components: {
		ContainerWidget,
	},

	props: {
		content: OBJECT_PROP,

		/** The widget's own placement. */
		placement: OBJECT_PROP,

		editMode: FLAG_PROP,
	},

	emits: ['update:content'],

	data() {
		instanceCounter += 1
		return {
			activeIndex: 0,
			uid: `tabs-widget-${instanceCounter}`,
		}
	},

	computed: {
		/**
		 * The stored tabs, each with an id, a title and a placements list.
		 *
		 * @return {Array<{id: string, title: string, placements: Array<object>}>}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
		 */
		tabs() {
			return normaliseTabs(this.content?.tabs)
		},

		/**
		 * The open tab, or null when there are none.
		 *
		 * @return {object|null}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
		 */
		activeTab() {
			return this.tabs[Math.min(this.activeIndex, this.tabs.length - 1)] ?? null
		},

		/**
		 * The open tab as a well's content: its placements on the widget's padding and background.
		 *
		 * @return {object}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
		 */
		panelContent() {
			return {
				placements: this.activeTab?.placements ?? [],
				padding: this.content?.padding ?? 'small',
				backgroundColor: this.content?.backgroundColor ?? 'transparent',
				title: '',
			}
		},

		/**
		 * The inner grid's owner. Without an id of its own, the group tile form
		 * (which edits a well's `content.placements`) is not offered inside a tab.
		 *
		 * @return {object}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
		 */
		panelPlacement() {
			return { uuid: `${this.placement?.id ?? this.uid}-${this.activeTab?.id ?? ''}` }
		},

		/**
		 * The strip's accessible name: the widget's own, or its title.
		 *
		 * @return {string}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
		 */
		stripLabel() {
			const label = this.content?.ariaLabel || this.content?.title || ''
			return label !== '' ? label : t('launchpad', 'Tabs')
		},
	},

	watch: {
		/**
		 * Keep the open tab in range when tabs are removed.
		 *
		 * @param {number} count The number of tabs.
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
		 */
		'tabs.length': function(count) {
			if (this.activeIndex >= count) {
				this.activeIndex = Math.max(0, count - 1)
			}
		},
	},

	methods: {
		t,

		/**
		 * The id of a tab's button.
		 *
		 * @param {number} index The tab's position.
		 * @return {string}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
		 */
		tabId(index) {
			return `${this.uid}-tab-${index}`
		},

		/**
		 * The id of a tab's panel.
		 *
		 * @param {number} index The tab's position.
		 * @return {string}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
		 */
		panelId(index) {
			return `${this.uid}-panel-${index}`
		},

		/**
		 * A tab's title, or "Tab n" when it has none.
		 *
		 * @param {object} tab The tab.
		 * @param {number} index Its position.
		 * @return {string}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
		 */
		tabTitle(tab, index) {
			return tab.title.trim() !== '' ? tab.title : t('launchpad', 'Tab {number}', { number: index + 1 })
		},

		/**
		 * Open a tab.
		 *
		 * @param {number} index The tab's position.
		 * @return {void}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
		 */
		select(index) {
			this.activeIndex = index
		},

		/**
		 * Arrow keys move to the previous or next tab (wrapping), Home and End
		 * to the first and last; the new tab opens and takes focus.
		 *
		 * @param {KeyboardEvent} event The key press on the strip.
		 * @return {void}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
		 */
		onKeydown(event) {
			const count = this.tabs.length
			const next = {
				ArrowRight: (this.activeIndex + 1) % count,
				ArrowLeft: (this.activeIndex - 1 + count) % count,
				Home: 0,
				End: count - 1,
			}[event.key]
			if (next === undefined) {
				return
			}
			event.preventDefault()
			this.select(next)
			this.$nextTick(() => this.$refs.tabButtons?.[next]?.focus())
		},

		/**
		 * The open tab's inner grid changed: write its placements back into
		 * that tab and hand the whole content to the host.
		 *
		 * @param {object} panel The well content the inner grid emitted.
		 * @return {void}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
		 */
		onPanelContent(panel) {
			const index = this.tabs.indexOf(this.activeTab)
			const tabs = this.tabs.map((tab, i) => (i === index
				? { ...tab, placements: Array.isArray(panel?.placements) ? panel.placements : tab.placements }
				: tab))
			this.$emit('update:content', { ...this.content, tabs })
		},
	},
}
</script>

<style scoped>
.tabs-widget {
	display: flex;
	flex-direction: column;
	width: 100%;
	height: 100%;
}

.tabs-widget__strip {
	display: flex;
	gap: 4px;
	border-bottom: 1px solid var(--color-border);
	overflow-x: auto;
}

.tabs-widget__tab {
	padding: 6px 12px;
	border: none;
	border-bottom: 2px solid transparent;
	border-radius: 0;
	background: none;
	color: var(--color-main-text);
	white-space: nowrap;
	cursor: pointer;
}

.tabs-widget__tab--active {
	border-bottom-color: var(--color-primary-element);
	font-weight: bold;
}

.tabs-widget__tab:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -2px;
}

.tabs-widget__panel {
	flex: 1;
	min-height: 0;
}

.tabs-widget__empty {
	padding: 8px;
	color: var(--color-text-maxcontrast);
}
</style>
