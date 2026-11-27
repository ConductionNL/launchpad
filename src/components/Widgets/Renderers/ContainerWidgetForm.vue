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

		<!-- REQ-CONT-009, board LpWellInstellen: an introduction, the app list
			switch in a ruled row, its settings indented under it. -->
		<section class="container-widget-form__content" data-testid="container-app-list" aria-labelledby="container-app-list-title">
			<p class="container-widget-form__intro">
				{{ t('launchpad', 'A well puts tiles together. The tiles come from the app menu, from a list you put together yourself, or both.') }}
			</p>
			<div class="container-widget-form__row">
				<span class="container-widget-form__row-text">
					<strong id="container-app-list-title" class="container-widget-form__apps-title">{{ t('launchpad', 'Apps from the app menu') }}</strong>
					<span class="container-widget-form__hint">{{ t('launchpad', 'A dynamic list: the well follows the viewer\'s app menu, so each viewer sees their own apps.') }}</span>
				</span>
				<NcCheckboxRadioSwitch
					type="switch"
					:modelValue="appList !== null"
					:aria-label="t('launchpad', 'Apps from the app menu')"
					data-testid="container-app-list-on"
					@update:modelValue="onAppListOn" />
			</div>
			<div v-if="appList" class="container-widget-form__apps" data-testid="container-app-list-settings">
				<div class="container-widget-form__pair">
					<div class="container-widget-form__field">
						<NcSelect
							:modelValue="appList.pinned"
							:options="appOptions"
							:reduce="(option) => option.value"
							:multiple="true"
							label="label"
							:inputLabel="t('launchpad', 'Pinned first')"
							data-testid="container-app-list-pinned"
							@update:modelValue="setAppList('pinned', $event)" />
						<span class="container-widget-form__hint">{{ t('launchpad', 'These apps come first, for every viewer who has them.') }}</span>
					</div>
					<div class="container-widget-form__field">
						<label for="container-app-list-limit" class="container-widget-form__label">
							{{ t('launchpad', 'Most used apps, at most') }}
						</label>
						<select
							id="container-app-list-limit"
							class="container-widget-form__select"
							:value="String(appList.mostUsedLimit)"
							data-testid="container-app-list-limit"
							@change="setAppList('mostUsedLimit', Math.max(0, Math.floor(Number($event.target.value) || 0)))">
							<option v-for="option in limitOptions" :key="option.value" :value="String(option.value)">
								{{ option.label }}
							</option>
						</select>
						<span class="container-widget-form__hint">{{ t('launchpad', 'After the pinned apps, by use.') }}</span>
					</div>
				</div>
				<div class="container-widget-form__field">
					<NcSelect
						:modelValue="appList.exclude"
						:options="appOptions"
						:reduce="(option) => option.value"
						:multiple="true"
						label="label"
						:inputLabel="t('launchpad', 'Leave out')"
						data-testid="container-app-list-exclude"
						@update:modelValue="setAppList('exclude', $event)" />
					<span class="container-widget-form__hint">{{ t('launchpad', 'These apps never come from the app menu into the well, not even when they are pinned.') }}</span>
				</div>
				<NcCheckboxRadioSwitch
					:modelValue="appList.showFilter"
					data-testid="container-app-list-filter"
					@update:modelValue="setAppList('showFilter', $event === true)">
					<strong class="container-widget-form__check-title">{{ t('launchpad', 'Show filter') }}</strong>
					<span class="container-widget-form__hint container-widget-form__hint--block">{{ t('launchpad', 'A search field above the tiles.') }}</span>
				</NcCheckboxRadioSwitch>
			</div>
		</section>
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

		/**
		 * The drawn choices for "Most used apps, at most" (board LpWellInstellen:
		 * 4, 8, 12, All), plus a stored number that is none of them, so a
		 * migrated limit stays visible and kept.
		 *
		 * @return {Array<{value: number, label: string}>}
		 * @spec openspec/changes/archive/2026-11-27-layout-widgets-replace-multi-tile/specs/container-widget/spec.md
		 */
		limitOptions() {
			const values = [4, 8, 12]
			const current = this.appList?.mostUsedLimit ?? 0
			if (current > 0 && !values.includes(current)) {
				values.push(current)
				values.sort((a, b) => a - b)
			}
			return [
				...values.map((value) => ({ value, label: String(value) })),
				{ value: 0, label: t('launchpad', 'All') },
			]
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
.container-widget-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

/* Board LpWellInstellen: intro, the switch in a ruled row, settings
   indented under a left rule. */
.container-widget-form__content {
	display: flex;
	flex-direction: column;
	gap: 12px;
	margin-top: 6px;
}

.container-widget-form__intro {
	margin: 0;
	font-size: 14px;
	line-height: 1.5;
	color: var(--color-text-maxcontrast);
}

.container-widget-form__row {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;
	padding: 12px 14px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-large, 8px);
}

.container-widget-form__row-text {
	display: flex;
	flex-direction: column;
	gap: 2px;
}

.container-widget-form__apps-title,
.container-widget-form__check-title {
	font-size: 14px;
	font-weight: 600;
}

.container-widget-form__hint {
	font-size: 13px;
	color: var(--color-text-maxcontrast);
}

.container-widget-form__hint--block {
	display: block;
	font-weight: 400;
}

.container-widget-form__apps {
	display: flex;
	flex-direction: column;
	gap: 12px;
	margin-inline-start: 14px;
	padding-inline-start: 14px;
	border-inline-start: 2px solid var(--color-border);
}

.container-widget-form__pair {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 12px 16px;
}

.container-widget-form__field {
	display: flex;
	flex-direction: column;
	gap: 6px;
	min-width: 0;
}

.container-widget-form__label {
	font-size: 14px;
	font-weight: 600;
}

.container-widget-form__select {
	height: 40px;
	width: 100%;
	margin: 0;
}

@media (max-width: 600px) {
	.container-widget-form__pair {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
