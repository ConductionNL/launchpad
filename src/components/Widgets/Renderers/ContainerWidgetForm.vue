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
	</div>
</template>

<script>
import { CnContainerWidgetForm } from '@conduction/nextcloud-vue'
import { translate as t } from '@nextcloud/l10n'
import { NcSelect } from '@nextcloud/vue'
import { NULLABLE_OBJECT_PROP, objectProp } from '../../../constants/propDefinitions.js'
import { normaliseSortMode } from '../../../utils/tileSort.js'

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
		}
	},

	computed: {
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
			this.$emit('update:content', { ...this.content, sortBy: this.sortBy })
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
</style>
