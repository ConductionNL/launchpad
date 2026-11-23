<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
  -
  - LaunchpadMoreMenu: the one More menu of launchpad, the same on every page
  - (board LpMeer). A 600 px popover with two columns: Dashboards and the
  - expandable Settings item on the left, This dashboard on the right; one
  - column under 960 px, or when there is no dashboard to act on.
  -
  - Settings is one menu item (cog, chevron, aria-haspopup, aria-expanded)
  - that unfolds in place as an indented group with a rule at its start. It
  - starts folded every time the menu opens.
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
			:class="{ 'lp-more-menu__panel--single': !hasActions }"
			role="menu"
			:aria-label="triggerLabel"
			data-testid="launchpad-more-menu-panel"
			@keydown="onKeydown">
			<div class="lp-more-menu__column">
				<div
					v-if="dashboards.length > 0"
					class="lp-more-menu__group"
					role="group"
					:aria-labelledby="`${domId}-dashboards`"
					data-testid="launchpad-more-menu-group-dashboards">
					<span :id="`${domId}-dashboards`" class="lp-more-menu__title">
						{{ t('launchpad', 'Dashboards') }}
					</span>
					<component
						:is="itemTag(item)"
						v-for="item in dashboards"
						:key="item.id"
						v-bind="itemAttributes(item)"
						class="lp-more-menu__item"
						:class="{ 'lp-more-menu__item--checked': item.checked === true }"
						:role="item.checked === undefined ? 'menuitem' : 'menuitemradio'"
						:aria-checked="item.checked === undefined ? undefined : String(item.checked === true)"
						tabindex="-1"
						:data-testid="`launchpad-more-menu-item-${item.id}`"
						@click="choose($event, item)">
						<span class="lp-more-menu__icon" aria-hidden="true">
							<component :is="item.icon" v-if="item.icon" :size="18" />
						</span>
						<span class="lp-more-menu__label">{{ item.label }}</span>
						<span v-if="item.meta" class="lp-more-menu__meta">{{ item.meta }}</span>
					</component>
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
						<component
							:is="itemTag(item)"
							v-for="item in settings"
							:key="item.id"
							v-bind="itemAttributes(item)"
							class="lp-more-menu__item lp-more-menu__subitem"
							role="menuitem"
							tabindex="-1"
							:data-testid="`launchpad-more-menu-item-${item.id}`"
							@click="choose($event, item)">
							<span class="lp-more-menu__icon" aria-hidden="true">
								<component :is="item.icon" v-if="item.icon" :size="18" />
							</span>
							<span class="lp-more-menu__label">{{ item.label }}</span>
						</component>
					</div>
				</template>
			</div>
			<div v-if="hasActions" class="lp-more-menu__column">
				<div
					class="lp-more-menu__group"
					role="group"
					:aria-labelledby="`${domId}-actions`"
					data-testid="launchpad-more-menu-group-this-dashboard">
					<span :id="`${domId}-actions`" class="lp-more-menu__title">
						{{ t('launchpad', 'This dashboard') }}
					</span>
					<component
						:is="itemTag(item)"
						v-for="item in actions"
						:key="item.id"
						v-bind="itemAttributes(item)"
						class="lp-more-menu__item"
						:class="{ 'lp-more-menu__item--destructive': item.destructive === true }"
						role="menuitem"
						tabindex="-1"
						:data-testid="`launchpad-more-menu-item-${item.id}`"
						@click="choose($event, item)">
						<span class="lp-more-menu__icon" aria-hidden="true">
							<component :is="item.icon" v-if="item.icon" :size="18" />
						</span>
						<span class="lp-more-menu__label">{{ item.label }}</span>
					</component>
				</div>
			</div>
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

let menuCounter = 0

export default {
	name: 'LaunchpadMoreMenu',

	components: {
		ChevronDown,
		ChevronRight,
		Cog,
		DotsHorizontal,
		NcButton,
		NcPopover,
	},

	props: {
		/**
		 * The Dashboards group: `{ id, label, icon?, meta?, checked?, href?,
		 * to?, onClick? }`. `checked` (true or false) makes a radio item.
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
		 */
		actions: {
			type: Array,
			default: () => [],
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
		 * The button's text and the menu's name.
		 *
		 * @return {string}
		 */
		triggerLabel() {
			return t('launchpad', 'More')
		},

		/**
		 * Whether the This dashboard column is drawn.
		 *
		 * @return {boolean}
		 */
		hasActions() {
			return this.actions.length > 0
		},
	},

	watch: {
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
		 * The element an item renders as: a link, a router link or a button.
		 *
		 * @param {object} item The item.
		 * @return {string}
		 */
		itemTag(item) {
			if (item.href) {
				return 'a'
			}
			if (item.to) {
				return 'router-link'
			}
			return 'button'
		},

		/**
		 * The attributes of an item's element.
		 *
		 * @param {object} item The item.
		 * @return {object}
		 */
		itemAttributes(item) {
			const tag = this.itemTag(item)
			if (tag === 'a') {
				const target = item.target || undefined
				return { href: item.href, target, rel: target === '_blank' ? 'noopener noreferrer' : undefined }
			}
			if (tag === 'router-link') {
				return { to: item.to }
			}
			return { type: 'button' }
		},

		/**
		 * Choose an item: run it, report it and close the menu.
		 *
		 * @param {MouseEvent} event The click.
		 * @param {object} item The item.
		 * @return {void}
		 */
		choose(event, item) {
			if (typeof item.onClick === 'function') {
				item.onClick(item)
			}
			this.$emit('select', item)
			this.open = false
		},

		/**
		 * The visible items in reading order, as elements.
		 *
		 * @return {HTMLElement[]}
		 */
		itemElements() {
			const panel = this.$refs.panel
			if (!panel) {
				return []
			}
			return [...panel.querySelectorAll('.lp-more-menu__item')]
				.filter((el) => !el.closest('.lp-more-menu__subgroup') || this.settingsOpen)
		},

		/**
		 * Move focus to the item at an index, wrapping around.
		 *
		 * @param {number} index The index.
		 * @return {void}
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
		 */
		returnFocus() {
			return this.$refs.trigger?.$el ?? false
		},
	},
}
</script>

<style scoped>
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
.lp-more-menu__item:not(#lp-more-menu-a):not(#lp-more-menu-b) {
	color: var(--color-main-text) !important;
	font-weight: 400 !important;
	text-decoration: none !important;
}

.lp-more-menu__item--checked:not(#lp-more-menu-a):not(#lp-more-menu-b) {
	color: var(--color-primary-element-light-text) !important;
	font-weight: 600 !important;
}

.lp-more-menu__item--destructive:not(#lp-more-menu-a):not(#lp-more-menu-b) {
	color: var(--color-error-text, var(--color-error)) !important;
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

@media (max-width: 960px) {
	.lp-more-menu__panel {
		grid-template-columns: minmax(0, 1fr);
		width: min(320px, calc(100vw - 32px));
	}
}
</style>
