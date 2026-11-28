<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="container-widget-form">
		<CnContainerWidgetForm
			:editingWidget="editingWidget"
			:value="value"
			@update:content="onCommunalContent" />

		<NcSelect
			:modelValue="sortBy"
			:options="sortOptions"
			:inputLabel="t('launchpad', 'Sort tiles')"
			:reduce="(option) => option.value"
			label="label"
			:clearable="false"
			data-testid="container-sort-by"
			@update:modelValue="onSortBy" />

		<!-- REQ-CONT-009: the well fills itself with each viewer's apps. No board draws this yet. -->
		<fieldset class="container-widget-form__apps" data-testid="container-app-list">
			<legend>{{ t('launchpad', 'Apps from the app menu') }}</legend>
			<NcCheckboxRadioSwitch
				type="switch"
				:modelValue="appList !== null"
				data-testid="container-app-list-on"
				@update:modelValue="onAppListOn">
				{{ t('launchpad', 'Show each viewer the apps of their own app menu') }}
			</NcCheckboxRadioSwitch>
			<template v-if="appList">
				<NcSelect
					:modelValue="appList.pinned"
					:options="appOptions"
					:reduce="(option) => option.value"
					:multiple="true"
					label="label"
					:inputLabel="t('launchpad', 'Pinned first')"
					data-testid="container-app-list-pinned"
					@update:modelValue="setAppList('pinned', $event)" />
				<NcSelect
					:modelValue="appList.exclude"
					:options="appOptions"
					:reduce="(option) => option.value"
					:multiple="true"
					label="label"
					:inputLabel="t('launchpad', 'Leave out')"
					data-testid="container-app-list-exclude"
					@update:modelValue="setAppList('exclude', $event)" />
				<label class="container-widget-form__limit">
					{{ t('launchpad', 'Most used apps after the pinned ones (0 shows all)') }}
					<input
						type="number"
						min="0"
						:value="appList.mostUsedLimit"
						data-testid="container-app-list-limit"
						@input="setAppList('mostUsedLimit', Math.max(0, Math.floor(Number($event.target.value) || 0)))">
				</label>
				<NcCheckboxRadioSwitch
					:modelValue="appList.showFilter"
					data-testid="container-app-list-filter"
					@update:modelValue="setAppList('showFilter', $event === true)">
					{{ t('launchpad', 'Show a search field above the apps') }}
				</NcCheckboxRadioSwitch>
			</template>
		</fieldset>
	</div>
</template>

<script>
import { CnContainerWidgetForm } from '@conduction/nextcloud-vue'
import { translate as t } from '@nextcloud/l10n'
import { NcCheckboxRadioSwitch, NcSelect } from '@nextcloud/vue'
import { NULLABLE_OBJECT_PROP, objectProp } from '../../../constants/propDefinitions.js'
import { normaliseSortMode } from '../../../utils/tileSort.js'
import { normaliseAppList, viewerAppMenu } from '../../../utils/wellAppList.js'

/**
 * ContainerWidgetForm: the communal container fields (background, padding,
 * title) plus "Sort tiles" (REQ-CONT-007 as LaunchPad extends it). The
 * communal form stays unchanged for the other apps; this form adds `sortBy`
 * to the content it emits.
 *
 * @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md
 */
export default {
	name: 'ContainerWidgetForm',

	components: {
		CnContainerWidgetForm,
		NcCheckboxRadioSwitch,
		NcSelect,
	},

	props: {
		editingWidget: NULLABLE_OBJECT_PROP,

		value: objectProp({ placements: [], backgroundColor: 'transparent', padding: 'medium', title: '', sortBy: 'manual' }),
	},

	emits: ['update:content'],

	/** @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md */
	data() {
		const initial = this.editingWidget?.content || this.value || {}
		return {
			content: { ...initial },
			sortBy: normaliseSortMode(initial.sortBy),
			appList: normaliseAppList(initial.appList),
		}
	},

	computed: {
		/**
		 * The apps of the editor's app menu to pin or leave out, plus any
		 * already chosen that the editor does not have.
		 *
		 * @return {Array<{value: string, label: string}>}
		 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-3
		 */
		appOptions() {
			const options = (viewerAppMenu() ?? [])
				.filter((app) => app.id !== 'launchpad')
				.map((app) => ({ value: app.id, label: app.label }))
			const known = new Set(['launchpad', ...options.map((option) => option.value)])
			for (const id of [...(this.appList?.pinned ?? []), ...(this.appList?.exclude ?? [])]) {
				if (!known.has(id)) {
					known.add(id)
					options.push({ value: id, label: id })
				}
			}
			return options
		},

		/** @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md */
		sortOptions() {
			return [
				{ value: 'manual', label: t('launchpad', 'By hand') },
				{ value: 'alphabetical', label: t('launchpad', 'Alphabetically') },
				{ value: 'most-used', label: t('launchpad', 'Most used') },
				{ value: 'last-used', label: t('launchpad', 'Last used') },
				{ value: 'random', label: t('launchpad', 'At random') },
			]
		},
	},

	methods: {
		t,

		/**
		 * Keep what the communal fields emit and pass it on with the sort.
		 *
		 * @param {object} content The communal form's content.
		 * @return {void}
		 * @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md
		 */
		onCommunalContent(content) {
			this.content = { ...this.content, ...content }
			this.emit()
		},

		/**
		 * Store the chosen sort.
		 *
		 * @param {string} value The chosen mode.
		 * @return {void}
		 * @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md
		 */
		onSortBy(value) {
			this.sortBy = normaliseSortMode(value)
			this.emit()
		},

		/**
		 * Tell the host the whole content.
		 *
		 * @return {void}
		 * @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md
		 */
		emit() {
			const content = { ...this.content, sortBy: this.sortBy }
			if (this.appList) {
				content.appList = { ...this.appList }
			} else {
				delete content.appList
			}
			this.$emit('update:content', content)
		},

		/**
		 * Turn the app list on (the menu's apps, nothing pinned, no limit) or off.
		 *
		 * @param {boolean} on Whether the well lists the viewer's apps.
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-3
		 */
		onAppListOn(on) {
			this.appList = on === true ? normaliseAppList({ exclude: ['launchpad'] }) : null
			this.emit()
		},

		/**
		 * Change one setting of the app list.
		 *
		 * @param {string} key `pinned`, `exclude`, `mostUsedLimit` or `showFilter`.
		 * @param {string[]|number|boolean} value The new value.
		 * @return {void}
		 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/tasks.md#task-3
		 */
		setAppList(key, value) {
			this.appList = normaliseAppList({ ...this.appList, [key]: value })
			this.emit()
		},

		/**
		 * Nothing is required.
		 *
		 * @return {Array<string>} No errors.
		 * @spec openspec/changes/launcher-tile-sorting/specs/container-widget/spec.md
		 */
		validate() {
			return []
		},
	},
}
</script>

<style scoped>
.container-widget-form__apps {
	display: flex;
	flex-direction: column;
	gap: 8px;
	margin-top: 12px;
	border: none;
	padding: 0;
}

.container-widget-form__limit {
	display: flex;
	flex-direction: column;
	gap: 4px;
}

.container-widget-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}
</style>
