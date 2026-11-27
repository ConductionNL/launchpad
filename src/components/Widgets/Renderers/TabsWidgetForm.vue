<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="tabs-widget-form" data-testid="tabs-widget-form">
		<NcTextField
			:modelValue="title"
			:label="t('launchpad', 'Title (optional)')"
			data-testid="tabs-widget-form-title"
			@update:modelValue="onTitle" />

		<fieldset class="tabs-widget-form__tabs">
			<legend>{{ t('launchpad', 'Tabs') }}</legend>
			<p v-if="tabs.length === 0" class="tabs-widget-form__hint">
				{{ t('launchpad', 'No tabs yet.') }}
			</p>
			<ol v-else class="tabs-widget-form__list">
				<li
					v-for="(tab, index) in tabs"
					:key="tab.id"
					class="tabs-widget-form__row"
					:data-testid="`tabs-widget-form-row-${index}`">
					<NcTextField
						:modelValue="tab.title"
						:label="t('launchpad', 'Name of tab {number}', { number: index + 1 })"
						:data-testid="`tabs-widget-form-name-${index}`"
						@update:modelValue="rename(index, $event)" />
					<NcButton
						variant="tertiary"
						:disabled="index === 0"
						:aria-label="t('launchpad', 'Move tab {number} up', { number: index + 1 })"
						:data-testid="`tabs-widget-form-up-${index}`"
						@click="move(index, -1)">
						<template #icon>
							<ArrowUp :size="20" />
						</template>
					</NcButton>
					<NcButton
						variant="tertiary"
						:disabled="index === tabs.length - 1"
						:aria-label="t('launchpad', 'Move tab {number} down', { number: index + 1 })"
						:data-testid="`tabs-widget-form-down-${index}`"
						@click="move(index, 1)">
						<template #icon>
							<ArrowDown :size="20" />
						</template>
					</NcButton>
					<NcButton
						variant="tertiary"
						:aria-label="t('launchpad', 'Remove tab {number}', { number: index + 1 })"
						:data-testid="`tabs-widget-form-remove-${index}`"
						@click="askRemove(index)">
						<template #icon>
							<Delete :size="20" />
						</template>
					</NcButton>
				</li>
			</ol>
			<NcButton
				variant="secondary"
				data-testid="tabs-widget-form-add"
				@click="add">
				<template #icon>
					<Plus :size="20" />
				</template>
				{{ t('launchpad', 'Add tab') }}
			</NcButton>
		</fieldset>

		<!-- Removing a tab with widgets loses them, so it asks first. -->
		<CnConfirmDialog
			v-if="removing !== null"
			:dialogTitle="t('launchpad', 'Remove this tab?')"
			:message="t('launchpad', 'The tab {name} holds {count} widgets. They are removed with it.', { name: removingName, count: removingCount })"
			:confirmLabel="t('launchpad', 'Remove')"
			variant="error"
			@confirm="confirmRemove"
			@close="removing = null" />
	</div>
</template>

<script>
import { CnConfirmDialog, NcButton, NcTextField } from '@conduction/nextcloud-vue'
import { translate as t } from '@nextcloud/l10n'
import ArrowDown from 'vue-material-design-icons/ArrowDown.vue'
import ArrowUp from 'vue-material-design-icons/ArrowUp.vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import Plus from 'vue-material-design-icons/Plus.vue'
import { NULLABLE_OBJECT_PROP, objectProp } from '../../../constants/propDefinitions.js'
import { moveTab, newTab, normaliseTabs, tabHasWidgets } from '../../../utils/tabsWidget.js'

/**
 * TabsWidgetForm: add, rename, move and remove the tabs of a Tabs widget
 * (layout-widgets-replace-multi-tile D1). Removing a tab that holds widgets
 * asks first. A tab's widgets are placed on its own grid in edit mode.
 *
 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
 */
export default {
	name: 'TabsWidgetForm',

	components: {
		ArrowDown,
		ArrowUp,
		CnConfirmDialog,
		Delete,
		NcButton,
		NcTextField,
		Plus,
	},

	props: {
		editingWidget: NULLABLE_OBJECT_PROP,

		value: objectProp({ tabs: [], title: '' }),
	},

	emits: ['update:content'],

	data() {
		const source = this.editingWidget?.content ?? this.value ?? {}
		const tabs = normaliseTabs(source.tabs)
		return {
			content: { ...source },
			tabs: tabs.length > 0 ? tabs : [newTab([], t('launchpad', 'Tab {number}', { number: 1 }))],
			title: typeof source.title === 'string' ? source.title : '',
			removing: null,
		}
	},

	computed: {
		/** @return {string} The name of the tab being removed. */
		removingName() {
			return this.tabs[this.removing]?.title ?? ''
		},

		/** @return {number} How many widgets the tab being removed holds. */
		removingCount() {
			return this.tabs[this.removing]?.placements?.length ?? 0
		},
	},

	/** @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1 */
	mounted() {
		this.emit()
	},

	methods: {
		t,

		/**
		 * Store the widget's title.
		 *
		 * @param {string} value The title.
		 * @return {void}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
		 */
		onTitle(value) {
			this.title = value
			this.emit()
		},

		/**
		 * Add an empty tab at the end.
		 *
		 * @return {void}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
		 */
		add() {
			this.tabs = [...this.tabs, newTab(this.tabs, t('launchpad', 'Tab {number}', { number: this.tabs.length + 1 }))]
			this.emit()
		},

		/**
		 * Rename a tab.
		 *
		 * @param {number} index The tab.
		 * @param {string} value Its new name.
		 * @return {void}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
		 */
		rename(index, value) {
			this.tabs = this.tabs.map((tab, i) => (i === index ? { ...tab, title: value } : tab))
			this.emit()
		},

		/**
		 * Move a tab one place.
		 *
		 * @param {number} index The tab.
		 * @param {number} step -1 up, +1 down.
		 * @return {void}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
		 */
		move(index, step) {
			this.tabs = moveTab(this.tabs, index, step)
			this.emit()
		},

		/**
		 * Remove a tab; one with widgets asks first.
		 *
		 * @param {number} index The tab.
		 * @return {void}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
		 */
		askRemove(index) {
			if (tabHasWidgets(this.tabs[index])) {
				this.removing = index
				return
			}
			this.remove(index)
		},

		/**
		 * Remove the tab the confirmation was for.
		 *
		 * @return {void}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
		 */
		confirmRemove() {
			const index = this.removing
			this.removing = null
			if (index !== null) {
				this.remove(index)
			}
		},

		/**
		 * Take a tab out of the list.
		 *
		 * @param {number} index The tab.
		 * @return {void}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
		 */
		remove(index) {
			this.tabs = this.tabs.filter((_tab, i) => i !== index)
			this.emit()
		},

		/**
		 * Tell the host the whole content.
		 *
		 * @return {void}
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
		 */
		emit() {
			this.$emit('update:content', { ...this.content, title: this.title, tabs: this.tabs })
		},

		/**
		 * A Tabs widget needs at least one tab.
		 *
		 * @return {Array<string>} The errors.
		 * @spec openspec/changes/layout-widgets-replace-multi-tile/tasks.md#task-1
		 */
		validate() {
			return this.tabs.length === 0 ? [t('launchpad', 'Add at least one tab.')] : []
		},
	},
}
</script>

<style scoped>
.tabs-widget-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.tabs-widget-form__tabs {
	display: flex;
	flex-direction: column;
	gap: 8px;
	border: none;
	padding: 0;
}

.tabs-widget-form__list {
	display: flex;
	flex-direction: column;
	gap: 4px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.tabs-widget-form__row {
	display: flex;
	align-items: flex-end;
	gap: 4px;
}

.tabs-widget-form__hint {
	color: var(--color-text-maxcontrast);
}
</style>
