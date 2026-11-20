<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<dl v-if="rows.length > 0" class="calculated-values" data-testid="calculated-values">
		<div
			v-for="row in rows"
			:key="row.name"
			class="calculated-values__row"
			:data-field="row.name">
			<dt class="calculated-values__label">
				{{ row.label }}
			</dt>
			<dd class="calculated-values__value">
				<span
					v-if="row.error"
					class="calculated-values__error"
					role="img"
					tabindex="0"
					:title="row.error"
					:aria-label="row.error"
					data-testid="calculated-value-error">
					<AlertCircle :size="18" aria-hidden="true" />
				</span>
				<span v-else data-testid="calculated-value">{{ row.text }}</span>
			</dd>
		</div>
	</dl>
</template>

<script>
import { getLanguage } from '@nextcloud/l10n'
import { mapState } from 'pinia'
import AlertCircle from 'vue-material-design-icons/AlertCircle.vue'
import { OBJECT_PROP } from '../../../constants/propDefinitions.js'
import { useCalculatedFieldsStore } from '../../../stores/calculatedFields.js'
import { compiledFieldsOf } from '../../../utils/calculatedFields/dashboardValues.js'
import { calculatedFieldErrorText } from '../../../utils/calculatedFields/errorText.js'
import { formatCalculatedValue } from '../../../utils/calculatedFields/formatOptions.js'

/**
 * The calculated fields of a placed widget, shown beside its own data
 * (widget-calculated-fields REQ-CALC-008, REQ-CALC-010): each field's label
 * and its value formatted for display only, or an error icon whose hover
 * text names what went wrong, the formula and the values it read. The value
 * in the store stays native for export and drill-down.
 */
export default {
	name: 'CalculatedValues',

	components: { AlertCircle },

	props: {
		/** The placement record, carrying `id` and `calculatedFields`. */
		placement: OBJECT_PROP,
	},

	computed: {
		...mapState(useCalculatedFieldsStore, ['valuesOf', 'errorsOf']),

		/**
		 * One row per calculated field: its label and display text, or its error text.
		 *
		 * @return {Array<{name: string, label: string, text: string, error: string|null}>}
		 * @spec openspec/changes/widget-calculated-fields/tasks.md#task-19
		 */
		rows() {
			const id = String(this.placement?.id ?? '')
			const values = this.valuesOf(id)
			const errors = this.errorsOf(id)
			const locale = getLanguage()
			return compiledFieldsOf(this.placement).map((field) => ({
				name: field.name,
				label: field.label || field.name,
				text: formatCalculatedValue(values[field.name] ?? null, field, locale),
				error: errors[field.name] ? calculatedFieldErrorText(errors[field.name]) : null,
			}))
		},
	},
}
</script>

<style scoped>
.calculated-values {
	display: flex;
	flex-wrap: wrap;
	gap: 4px 16px;
	margin: 8px 0 0;
	padding: 0 12px 8px;
}

.calculated-values__row {
	display: flex;
	gap: 6px;
	align-items: baseline;
}

.calculated-values__label {
	color: var(--color-text-maxcontrast);
}

.calculated-values__value {
	margin: 0;
	font-weight: bold;
	font-variant-numeric: tabular-nums;
}

.calculated-values__error {
	display: inline-flex;
	color: var(--color-error-text);
	cursor: help;
}
</style>
