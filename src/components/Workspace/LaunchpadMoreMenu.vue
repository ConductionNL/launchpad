<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
  -
  - LaunchpadMoreMenu: the one More menu of launchpad, the same on every page
  - (board LpMeer). A 600 px popover with two columns: Dashboards on the
  - left, This dashboard and below it the expandable Settings item on the
  - right; one column under 960 px, in that same order.
  -
  - Settings is one menu item (cog, chevron, aria-haspopup, aria-expanded)
  - below a rule. It unfolds in place as an indented group with a rule at its
  - start, and starts folded every time the menu opens.
  -
  - Keyboard: the arrow keys move through every visible item over both
  - columns, Home and End jump to the first and last, Right unfolds Settings
  - and Left folds it again, Escape closes the menu and returns focus to More.
-->
<template>
	<NcPopover
		v-model:shown="open"
		class="lp-more-menu"
		placement="bottom-end"
		popupRole="menu"
		:setReturnFocus="returnFocus"
		data-testid="launchpad-more-menu-popover"
		@afterShow="focusItem(0)">
		<template #trigger="{ attrs }">
			<NcButton
				ref="trigger"
				v-bind="attrs"
				variant="secondary"
				class="lp-more-menu__trigger"
				data-testid="launchpad-more-menu-trigger">
				<template #icon>
					<DotsHorizontal :size="20" />
				</template>
				{{ triggerLabel }}
			</NcButton>
		</template>
		<div
			ref="panel"
			class="lp-more-menu__panel"
			:class="{ 'lp-more-menu__panel--single': !twoColumns }"
			role="menu"
			:aria-label="triggerLabel"
			data-testid="launchpad-more-menu-panel"
			@keydown="onKeydown">
			<div v-if="dashboards.length > 0" class="lp-more-menu__column">
				<div
					class="lp-more-menu__group"
					role="group"
					:aria-labelledby="`${domId}-dashboards`"
					data-testid="launchpad-more-menu-group-dashboards">
					<span :id="`${domId}-dashboards`" class="lp-more-menu__title">
						{{ dashboardsExpanded ? t('launchpad', 'All dashboards') : t('launchpad', 'Dashboards') }}
					</span>
					<!-- REQ-SHELL-019: above the full list, e.g. Filter by detail. -->
					<slot v-if="dashboardsExpanded" name="dashboardsTop" />
					<div
						class="lp-more-menu__dashboard-list"
						:class="{ 'lp-more-menu__dashboard-list--all': dashboardsExpanded }"
						data-testid="launchpad-more-menu-dashboard-list">
						<div
							v-for="item in dashboardRows"
							:key="item.id"
							class="lp-more-menu__row"
							:data-depth="item.depth"
							:style="item.depth ? { paddingInlineStart: `${item.depth * 20}px` } : undefined">
							<!-- REQ-SHELL-019 / REQ-TREEUI-001: a parent's children show under it. -->
							<button
								v-if="item.tree"
								type="button"
								class="lp-more-menu__tree"
								:aria-expanded="String(item.tree.open)"
								:aria-label="item.tree.open
									? t('launchpad', 'Collapse {name}', { name: item.label })
									: t('launchpad', 'Expand {name}', { name: item.label })"
								tabindex="-1"
								:data-testid="`tree-expand-${item.tree.uuid}`"
								@click.stop="item.tree.onToggle()">
								<ChevronDown v-if="item.tree.open" :size="18" />
								<ChevronRight v-else :size="18" />
							</button>
							<p
								v-if="item.hint === true"
								class="lp-more-menu__hint"
								:data-testid="`launchpad-more-menu-${item.id}`">
								{{ item.label }}
							</p>
							<LaunchpadMoreMenuItem
								v-else
								:item="item"
								:aria-expanded="item.keepOpen ? String(item.expanded === true) : undefined"
								@click="choose($event, item)" />
							<!-- REQ-SHELL-016: a star on every dashboard row, short or expanded. -->
							<button
								v-if="item.favourite !== undefined"
								type="button"
								class="lp-more-menu__star"
								:class="{ 'lp-more-menu__star--on': item.favourite }"
								:aria-pressed="String(item.favourite)"
								:aria-label="item.favourite
									? t('launchpad', 'Remove from favourites: {name}', { name: item.label })
									: t('launchpad', 'Add to favourites: {name}', { name: item.label })"
								tabindex="-1"
								:data-testid="`launchpad-more-menu-star-${item.id}`"
								@click.stop="item.onToggleFavourite()">
								<Star v-if="item.favourite" :size="18" />
								<StarOutline v-else :size="18" />
							</button>
						</div>
					</div>
					<!-- REQ-SHELL-015: New dashboard and More/Less stand below the list, outside its scroll. -->
					<!-- Boards LpMeer / LpMeerAlle: the foot rows are 38px with a rule above the first; New dashboard in the brand colour. -->
					<LaunchpadMoreMenuItem
						v-for="(item, index) in dashboardFoot"
						:key="item.id"
						:item="item"
						class="lp-more-menu__foot"
						:class="{
							'lp-more-menu__foot--first': index === 0,
							'lp-more-menu__item--primary': item.id === 'new-dashboard',
						}"
						:aria-expanded="item.keepOpen ? String(item.expanded === true) : undefined"
						@click="choose($event, item)" />
				</div>
			</div>
			<div v-if="hasEndColumn" class="lp-more-menu__column">
				<div
					v-if="hasActions"
					class="lp-more-menu__group"
					role="group"
					:aria-labelledby="`${domId}-actions`"
					data-testid="launchpad-more-menu-group-this-dashboard">
					<span :id="`${domId}-actions`" class="lp-more-menu__title">
						{{ t('launchpad', 'This dashboard') }}
					</span>
					<LaunchpadMoreMenuItem
						v-for="item in actions"
						:key="item.id"
						:item="item"
						@click="choose($event, item)" />
				</div>
				<template v-if="settings.length > 0">
					<button
						type="button"
						class="lp-more-menu__item lp-more-menu__settings"
						:class="{ 'lp-more-menu__settings--open': settingsOpen }"
						role="menuitem"
						aria-haspopup="true"
						:aria-expanded="settingsOpen ? 'true' : 'false'"
						:aria-controls="`${domId}-settings`"
						tabindex="-1"
						data-testid="launchpad-more-menu-settings"
						@click="settingsOpen = !settingsOpen">
						<span class="lp-more-menu__icon" aria-hidden="true">
							<Cog :size="18" />
						</span>
						<span class="lp-more-menu__label">{{ t('launchpad', 'Settings') }}</span>
						<span class="lp-more-menu__chevron" aria-hidden="true">
							<ChevronDown v-if="settingsOpen" :size="18" />
							<ChevronRight v-else :size="18" />
						</span>
					</button>
					<div
						v-show="settingsOpen"
						:id="`${domId}-settings`"
						class="lp-more-menu__subgroup"
						role="group"
						:aria-label="t('launchpad', 'Settings')"
						data-testid="launchpad-more-menu-group-settings">
						<LaunchpadMoreMenuItem
							v-for="item in settings"
							:key="item.id"
							:item="item"
							class="lp-more-menu__subitem"
							@click="choose($event, item)" />
					</div>
				</template>
			</div>
			<!-- Ruben's review item 7: "Powered by" under both columns. -->
			<MoreMenuFooter v-if="!hideFooter" class="lp-more-menu__footer" />
		</div>
	</NcPopover>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { NcButton, NcPopover } from '@nextcloud/vue'
import ChevronDown from 'vue-material-design-icons/ChevronDown.vue'
import ChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import Cog from 'vue-material-design-icons/Cog.vue'
import DotsHorizontal from 'vue-material-design-icons/DotsHorizontal.vue'
import Star from 'vue-material-design-icons/Star.vue'
import StarOutline from 'vue-material-design-icons/StarOutline.vue'
import LaunchpadMoreMenuItem from './LaunchpadMoreMenuItem.vue'
import MoreMenuFooter from './MoreMenuFooter.vue'

let menuCounter = 0

/** The Dashboards column's items that stand below the list. */
const FOOT_ITEMS = new Set(['new-dashboard', 'all-dashboards'])

export default {
	name: 'LaunchpadMoreMenu',

	components: {
		ChevronDown,
		ChevronRight,
		Cog,
		DotsHorizontal,
		LaunchpadMoreMenuItem,
		NcButton,
		MoreMenuFooter,
		Star,
		StarOutline,
		NcPopover,
	},

	props: {
		/**
		 * The Dashboards group: `{ id, label, icon?, meta?, checked?, href?,
		 * to?, onClick? }`. `checked` (true or false) makes a radio item.
		 *
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		dashboards: {
			type: Array,
			default: () => [],
		},

		/** The items under Settings: `{ id, label, icon?, href?, target?, to?, onClick? }`. */
		settings: {
			type: Array,
			default: () => [],
		},

		/**
		 * The This dashboard group: `{ id, label, icon?, destructive?,
		 * onClick? }`. A destructive item goes last, in the error colour.
		 *
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		actions: {
			type: Array,
			default: () => [],
		},

		/**
		 * Leave out the "Powered by" footer under both columns.
		 *
		 * @spec openspec/changes/more-menu-favourites/tasks.md#task-5
		 */
		hideFooter: {
			type: Boolean,
			default: false,
		},
	},

	emits: ['open', 'select'],

	data() {
		return {
			open: false,
			settingsOpen: false,
			domId: `lp-more-menu-${++menuCounter}`,
		}
	},

	computed: {
		/**
		 * The dashboard rows (and the no-favourites line) inside the scrolling list.
		 *
		 * @return {object[]}
		 * @spec openspec/changes/more-menu-favourites/tasks.md#task-3
		 */
		dashboardRows() {
			return this.dashboards.filter((item) => !FOOT_ITEMS.has(item.id))
		},

		/**
		 * New dashboard and More/Less, below the list.
		 *
		 * @return {object[]}
		 * @spec openspec/changes/more-menu-favourites/tasks.md#task-3
		 */
		dashboardFoot() {
			return this.dashboards.filter((item) => FOOT_ITEMS.has(item.id))
		},

		/**
		 * The button's text and the menu's name.
		 *
		 * @return {string}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		triggerLabel() {
			return t('launchpad', 'More')
		},

		/**
		 * Whether the This dashboard column is drawn.
		 *
		 * @return {boolean}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		hasActions() {
			return this.actions.length > 0
		},

		/**
		 * Whether the right column is drawn: This dashboard, Settings or both.
		 *
		 * @return {boolean}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		hasEndColumn() {
			return this.hasActions || this.settings.length > 0
		},

		/**
		 * Whether the Dashboards column lists every dashboard.
		 *
		 * @return {boolean}
		 * @spec openspec/changes/more-menu-favourites/tasks.md#task-3
		 */
		dashboardsExpanded() {
			return this.dashboards.some((item) => item.keepOpen === true && item.expanded === true)
		},

		/**
		 * Whether the menu has two columns: Dashboards beside the right column.
		 *
		 * @return {boolean}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		twoColumns() {
			return this.dashboards.length > 0 && this.hasEndColumn
		},
	},

	watch: {
		/**
		 * Opening folds Settings again and tells the host.
		 *
		 * @param {boolean} isOpen Whether the menu is open.
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		open(isOpen) {
			if (isOpen) {
				this.settingsOpen = false
				this.$emit('open')
			}
		},
	},

	methods: {
		t,

		/**
		 * Choose an item: run it, report it and close the menu. An item that
		 * keeps the menu open (More / Fewer of the Dashboards column) leaves
		 * it open.
		 *
		 * @param {MouseEvent} event The click.
		 * @param {object} item The item.
		 * @return {void}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 * @spec openspec/changes/more-menu-favourites/tasks.md#task-3
		 */
		choose(event, item) {
			if (typeof item.onClick === 'function') {
				item.onClick(item)
			}
			this.$emit('select', item)
			if (item.keepOpen === true) {
				return
			}
			this.open = false
		},

		/**
		 * The visible items in reading order, as elements; a row's star
		 * follows its row.
		 *
		 * @return {HTMLElement[]}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		itemElements() {
			const panel = this.$refs.panel
			if (!panel) {
				return []
			}
			return [...panel.querySelectorAll('.lp-more-menu__item, .lp-more-menu__star')]
				.filter((el) => !el.closest('.lp-more-menu__subgroup') || this.settingsOpen)
		},

		/**
		 * Move focus to the item at an index, wrapping around.
		 *
		 * @param {number} index The index.
		 * @return {void}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		focusItem(index) {
			const items = this.itemElements()
			if (items.length === 0) {
				return
			}
			const at = ((index % items.length) + items.length) % items.length
			items[at].focus()
		},

		/**
		 * Unfold or fold Settings from the keyboard, keeping focus on a
		 * visible item.
		 *
		 * @param {boolean} unfold Whether to unfold.
		 * @param {EventTarget} from The focused element.
		 * @return {Promise<boolean>} Whether the key was used.
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		async toggleSettingsFromKey(unfold, from) {
			const toggle = this.$refs.panel?.querySelector('.lp-more-menu__settings')
			if (!toggle) {
				return false
			}
			if (unfold && from === toggle) {
				this.settingsOpen = true
				await this.$nextTick()
				this.$refs.panel.querySelector('.lp-more-menu__subitem')?.focus()
				return true
			}
			const inGroup = from instanceof Element && from.closest('.lp-more-menu__subgroup')
			if (!unfold && (from === toggle || inGroup)) {
				this.settingsOpen = false
				toggle.focus()
				return true
			}
			return false
		},

		/**
		 * The menu's keys.
		 *
		 * @param {KeyboardEvent} event The key.
		 * @return {Promise<void>}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		async onKeydown(event) {
			if (event.key === 'Escape') {
				event.preventDefault()
				event.stopPropagation()
				this.open = false
				this.$refs.trigger?.$el?.focus?.()
				return
			}
			if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
				event.preventDefault()
				await this.toggleSettingsFromKey(event.key === 'ArrowRight', document.activeElement)
				return
			}
			const items = this.itemElements()
			const current = items.indexOf(document.activeElement)
			const moves = {
				ArrowDown: current + 1,
				ArrowUp: current < 0 ? items.length - 1 : current - 1,
				Home: 0,
				End: items.length - 1,
			}
			if (!(event.key in moves)) {
				return
			}
			event.preventDefault()
			this.focusItem(moves[event.key])
		},

		/**
		 * Where focus goes when the menu closes: back to More.
		 *
		 * @return {HTMLElement|false}
		 * @spec openspec/specs/runtime-shell/spec.md#requirement-req-shell-013-one-more-menu-the-same-on-every-page
		 */
		returnFocus() {
			return this.$refs.trigger?.$el ?? false
		},
	},
}
</script>

<style>
/* Not scoped: LaunchpadMoreMenuItem draws the items with these classes. */

/* The More button is 40px high, and open it is tinted with a brand border
   (LpWerkplek, LpVandaag). */
.lp-more-menu__trigger {
	--default-clickable-area: 40px;
	min-height: 40px;
}

.lp-more-menu__trigger[aria-expanded='true']:not(#lp-more-menu-a) {
	border-color: var(--color-primary-element) !important;
	background-color: var(--color-primary-element-light) !important;
	color: var(--color-primary-element-light-text) !important;
}

.lp-more-menu__panel {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 4px 12px;
	box-sizing: border-box;
	width: min(600px, calc(100vw - 32px));
	max-height: calc(100vh - 120px);
	overflow: auto;
	padding: 10px 10px 12px;
}

.lp-more-menu__panel--single {
	grid-template-columns: minmax(0, 1fr);
	width: min(320px, calc(100vw - 32px));
}

.lp-more-menu__column,
.lp-more-menu__group,
.lp-more-menu__subgroup {
	display: flex;
	flex-direction: column;
	gap: 2px;
	min-width: 0;
}

.lp-more-menu__title {
	padding: 10px 10px 4px;
	font-size: 12px;
	font-weight: 600;
	letter-spacing: 0.06em;
	text-transform: uppercase;
	color: var(--color-text-maxcontrast);
}

.lp-more-menu__item {
	display: flex;
	align-items: center;
	gap: 10px;
	width: 100%;
	min-height: 32px;
	margin: 0;
	padding: 0 10px;
	border: none;
	border-radius: var(--border-radius-element, 6px);
	background: transparent;
	color: var(--color-main-text);
	font: inherit;
	font-size: 14px;
	text-align: start;
	text-decoration: none;
	cursor: pointer;
}

/* A theme that underlines, colours and bolds every link and button with
   `!important` and an id in its selector made the link items look different
   from the button items. The two `:not(#…)` raise this rule above it. */
/* The same theme also gives every button a tinted background, a height and
   padding of its own and no border, so each row read as a selected button
   and the rules above Delete and Settings were gone (LpMeer). */
/* Nextcloud's own button rule adds a 3px margin above and below and its
   15px text to every item, so rows stood 42px apart instead of the 34px
   (32px row, 2px gap, 14px text) boards LpMeer and LpMeerAlle draw. */
.lp-more-menu__item:not(#lp-more-menu-a):not(#lp-more-menu-b) {
	min-height: 32px !important;
	margin: 0 !important;
	padding: 0 10px !important;
	font-size: 14px !important;
	border-top: none !important;
	background: transparent !important;
	color: var(--color-main-text) !important;
	font-weight: 400 !important;
	text-decoration: none !important;
}

.lp-more-menu__item:not(#lp-more-menu-a):not(#lp-more-menu-b):hover,
.lp-more-menu__item:not(#lp-more-menu-a):not(#lp-more-menu-b):focus-visible {
	background: var(--color-background-hover) !important;
}

.lp-more-menu__item--checked:not(#lp-more-menu-a):not(#lp-more-menu-b) {
	background: var(--color-primary-element-light) !important;
	color: var(--color-primary-element-light-text) !important;
	font-weight: 600 !important;
}

.lp-more-menu__item--destructive:not(#lp-more-menu-a):not(#lp-more-menu-b) {
	margin-top: 4px !important;
	border-top: 1px solid var(--color-border) !important;
	color: var(--color-error-text, var(--color-error)) !important;
}

.lp-more-menu__settings:not(#lp-more-menu-a):not(#lp-more-menu-b) {
	min-height: 38px !important;
	margin-top: 6px !important;
	padding-top: 6px !important;
	border-top: 1px solid var(--color-border) !important;
}

.lp-more-menu__column > .lp-more-menu__settings:first-child:not(#lp-more-menu-a):not(#lp-more-menu-b) {
	margin-top: 0 !important;
	padding-top: 0 !important;
	border-top: none !important;
}

.lp-more-menu__foot:not(#lp-more-menu-a):not(#lp-more-menu-b) {
	min-height: 38px !important;
	border-radius: 0 !important;
}

.lp-more-menu__foot--first:not(#lp-more-menu-a):not(#lp-more-menu-b) {
	margin-top: 4px !important;
	padding-top: 4px !important;
	border-top: 1px solid var(--color-border) !important;
}

.lp-more-menu__item--primary:not(#lp-more-menu-a):not(#lp-more-menu-b) {
	color: var(--color-primary-element) !important;
	font-weight: 600 !important;
}

.lp-more-menu__settings--open:not(#lp-more-menu-a):not(#lp-more-menu-b) {
	font-weight: 600 !important;
}

.lp-more-menu__item:hover,
.lp-more-menu__item:focus-visible {
	background: var(--color-background-hover);
}

.lp-more-menu__item:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: -2px;
}

.lp-more-menu__item--checked {
	background: var(--color-primary-element-light);
}

.lp-more-menu__settings {
	min-height: 38px;
	margin-top: 6px;
	padding-top: 6px;
	border-top: 1px solid var(--color-border);
	border-radius: 0;
}

/* Settings alone at the top of a column needs no rule above it. */
.lp-more-menu__column > .lp-more-menu__settings:first-child {
	margin-top: 0;
	padding-top: 0;
	border-top: none;
}

.lp-more-menu__subgroup {
	margin: 0 0 4px 18px;
	padding-inline-start: 8px;
	border-inline-start: 2px solid var(--color-border);
}

.lp-more-menu__item--destructive {
	margin-top: 4px;
	border-top: 1px solid var(--color-border);
	border-radius: 0 0 var(--border-radius-element, 6px) var(--border-radius-element, 6px);
}

.lp-more-menu__icon,
.lp-more-menu__chevron {
	display: inline-flex;
	flex: none;
	width: 18px;
	justify-content: center;
}

.lp-more-menu__chevron {
	color: var(--color-text-maxcontrast);
}

.lp-more-menu__label {
	flex: 1 1 auto;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.lp-more-menu__meta {
	flex: none;
	font-size: 12px;
	font-weight: 400;
	color: var(--color-text-maxcontrast);
}

/* Ruben's review items 5-7: the full dashboard list scrolls inside the menu,
   a star sits at the end of each of its rows, and "Powered by" spans both
   columns at the bottom. */
.lp-more-menu__dashboard-list {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.lp-more-menu__dashboard-list--all {
	max-height: min(360px, calc(100vh - 260px));
	overflow-y: auto;
}

.lp-more-menu__row {
	display: flex;
	align-items: center;
	gap: 2px;
	min-width: 0;
}

.lp-more-menu__row > .lp-more-menu__item {
	flex: 1 1 auto;
	min-width: 0;
}

.lp-more-menu__star {
	display: inline-flex;
	flex: none;
	align-items: center;
	justify-content: center;
	width: 32px;
	height: 32px;
	margin: 0;
	padding: 0;
	border: none;
	border-radius: var(--border-radius-element, 6px);
	background: transparent;
	color: var(--color-text-maxcontrast);
	cursor: pointer;
}

.lp-more-menu__star:hover,
.lp-more-menu__star:focus-visible {
	background: var(--color-background-hover);
}

.lp-more-menu__star--on {
	color: #f0b400;
}

/* The theme that tints every button (see the items above) also tinted the
   star and recoloured it; the raised selector keeps it a plain icon button. */
/* Nextcloud's button margin (3px above and below) also stretched every
   dashboard row to 40px through its star; the rows are 34px apart. */
.lp-more-menu__star:not(#lp-more-menu-a):not(#lp-more-menu-b),
.lp-more-menu__tree:not(#lp-more-menu-a):not(#lp-more-menu-b) {
	min-height: 32px !important;
	margin: 0 !important;
	padding: 0 !important;
	border: none !important;
	background: transparent !important;
	color: var(--color-text-maxcontrast) !important;
}

.lp-more-menu__star:not(#lp-more-menu-a):not(#lp-more-menu-b):hover,
.lp-more-menu__star:not(#lp-more-menu-a):not(#lp-more-menu-b):focus-visible {
	background: var(--color-background-hover) !important;
}

.lp-more-menu__star--on:not(#lp-more-menu-a):not(#lp-more-menu-b) {
	color: #f0b400 !important;
}

.lp-more-menu__hint {
	margin: 0;
	padding: 4px 10px 6px;
	font-size: 13px;
	line-height: 1.4;
	color: var(--color-text-maxcontrast);
}

.lp-more-menu__footer {
	grid-column: 1 / -1;
	margin-top: 10px;
	border-top: 1px solid var(--color-border);
}

@media (max-width: 960px) {
	.lp-more-menu__panel {
		grid-template-columns: minmax(0, 1fr);
		width: min(320px, calc(100vw - 32px));
	}

	/* Stacked, Settings follows the Dashboards group and keeps its rule. */
	.lp-more-menu__column + .lp-more-menu__column > .lp-more-menu__settings:first-child,
	.lp-more-menu__column + .lp-more-menu__column > .lp-more-menu__settings:first-child:not(#lp-more-menu-a):not(#lp-more-menu-b):not(#lp-more-menu-c) {
		margin-top: 6px !important;
		padding-top: 6px !important;
		border-top: 1px solid var(--color-border) !important;
	}
}

.lp-more-menu__tree {
	display: inline-flex;
	flex: none;
	align-items: center;
	justify-content: center;
	width: 24px;
	height: 32px;
	padding: 0;
	border: none;
	background: transparent;
	color: var(--color-text-maxcontrast);
	cursor: pointer;
}
</style>
