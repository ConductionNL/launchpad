<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<NcSelect
		class="period-field-select"
		:modelValue="modelValue || null"
		:options="options"
		:inputLabel="t('launchpad', 'Date field for the period')"
		:placeholder="t('launchpad', 'None: the widget does not follow the period')"
		:clearable="true"
		:loading="loading"
		@update:modelValue="$emit('update:modelValue', $event || '')" />
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcSelect } from '@nextcloud/vue'
import { TEXT_PROP } from '../../../constants/propDefinitions.js'
import { fetchDateFields } from '../../../utils/periodDateFields.js'

/**
 * PeriodFieldSelect: the optional "Date field for the period" of a widget
 * with a register source. The options are the source's date fields; the
 * chosen one is stored as the source's `periodField`, so the widget's figures
 * follow the dashboard's period (dashboard-period-and-download REQ-DPD-002).
 * Empty means the widget ignores the period.
 *
 * @spec openspec/changes/archive/2026-11-25-dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
 */
export default {
	name: 'PeriodFieldSelect',

	components: { NcSelect },

	props: {
		/** The source's register. */
		register: TEXT_PROP,

		/** The source's schema. */
		schema: TEXT_PROP,

		/** The chosen date field, '' for none. */
		modelValue: TEXT_PROP,
	},

	emits: ['update:modelValue'],

	data() {
		return { fields: [], loading: false }
	},

	computed: {
		/**
		 * The date fields, with the chosen one kept when the sample no longer shows it.
		 *
		 * @return {string[]}
		 * @spec openspec/changes/archive/2026-11-25-dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
		 */
		options() {
			if (this.modelValue && !this.fields.includes(this.modelValue)) {
				return [this.modelValue, ...this.fields]
			}
			return this.fields
		},
	},

	watch: {
		register: 'loadFields',
		schema: 'loadFields',
	},

	mounted() {
		this.loadFields()
	},

	methods: {
		t,

		/**
		 * Read the source's date fields.
		 *
		 * @spec openspec/changes/archive/2026-11-25-dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
		 */
		async loadFields() {
			this.loading = true
			try {
				this.fields = await fetchDateFields(this.register, this.schema)
			} finally {
				this.loading = false
			}
		},
	},
}
</script>
