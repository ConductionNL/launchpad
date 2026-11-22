<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<ChartSourceForm
		ref="source"
		kind="pie"
		:editingWidget="editingWidget"
		:value="value"
		@update:content="$emit('update:content', $event)" />
</template>

<script>
import ChartSourceForm from './ChartSourceForm.vue'
import { NULLABLE_OBJECT_PROP, OBJECT_PROP } from '../../../constants/propDefinitions.js'

/**
 * PieChartWidgetForm: the form of the `pie-chart` widget, the chart source form for a pie chart.
 *
 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md#requirement-a-chart-reads-a-register-query-or-entered-values-req-chartp-003
 */
export default {
	name: 'PieChartWidgetForm',

	components: { ChartSourceForm },

	props: {
		/**
		 * The placement being edited, or `null` in create mode.
		 *
		 * @type {{content: object}|null}
		 */
		editingWidget: NULLABLE_OBJECT_PROP,

		/**
		 * The preset content, used when not editing.
		 *
		 * @type {object}
		 */
		value: OBJECT_PROP,
	},

	emits: ['update:content'],

	methods: {
		/**
		 * Validate through the source form.
		 *
		 * @return {string[]} The validation errors.
		 */
		validate() {
			return this.$refs.source?.validate() ?? []
		},
	},
}
</script>
