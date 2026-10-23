<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="container-widget-form">
		<CnContainerWidgetForm
			:editingWidget="editingWidget"
			:value="value"
			@update:content="onBaseContent" />
		<NcSelect
			:modelValue="sortBy"
			:options="sortOptions"
			:inputLabel="t('launchpad', 'Sort tiles')"
			:reduce="(option) => option.value"
			label="label"
			:clearable="false"
			@update:modelValue="onSortBy" />
	</div>
</template>

<script>
import { CnContainerWidgetForm } from '@conduction/nextcloud-vue'
import { NcSelect } from '@nextcloud/vue'
import { SORT_MODES } from '../../../utils/sortContainerTiles.js'

/**
 * ContainerWidgetForm: the communal container form (background, padding,
 * title) plus LaunchPad's "Sort tiles" select (launcher-tile-sorting,
 * REQ-CONT-007). The other apps that use the container keep the communal form.
 *
 * @spec openspec/specs/container-widget/spec.md
 */
export default {
	name: 'ContainerWidgetForm',

	components: { CnContainerWidgetForm, NcSelect },

	props: {
		/** The placement being edited, or null in create mode. */
		editingWidget: {
			type: Object,
			default: null,
		},

		/** Initial content values (registry defaults when not editing). */
		value: {
			type: Object,
			default: () => ({}),
		},
	},

	emits: ['update:content'],

	data() {
		const initial = this.editingWidget?.content || this.value || {}
		return {
			base: {
				placements: Array.isArray(initial.placements)
					? initial.placements
					: [],

				backgroundColor:
					typeof initial.backgroundColor === 'string'
						? initial.backgroundColor
						: 'transparent',

				padding:
					typeof initial.padding === 'string' ? initial.padding : 'medium',

				title: typeof initial.title === 'string' ? initial.title : '',
			},

			sortBy: SORT_MODES.includes(initial.sortBy) ? initial.sortBy : 'manual',
		}
	},

	computed: {
		/** @spec openspec/specs/container-widget/spec.md */
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
		/**
		 * Take the communal fields and emit the full content.
		 *
		 * @param {object} content The communal form's content.
		 * @spec openspec/specs/container-widget/spec.md
		 */
		onBaseContent(content) {
			this.base = { ...this.base, ...content }
			this.emitContent()
		},

		/**
		 * Take a new sort mode and emit the full content.
		 *
		 * @param {string} value The sort mode.
		 * @spec openspec/specs/container-widget/spec.md
		 */
		onSortBy(value) {
			this.sortBy = SORT_MODES.includes(value) ? value : 'manual'
			this.emitContent()
		},

		/** @spec openspec/specs/container-widget/spec.md */
		emitContent() {
			this.$emit('update:content', { ...this.base, sortBy: this.sortBy })
		},

		/**
		 * The container has nothing that can be invalid.
		 *
		 * @return {Array<string>} Always empty.
		 * @spec openspec/specs/container-widget/spec.md
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
