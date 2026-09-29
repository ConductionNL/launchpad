<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div
		v-if="filterable.length > 0"
		class="detail-filter"
		data-testid="detail-filter">
		<NcSelect
			v-model="field"
			:inputLabel="t('launchpad', 'Filter by detail')"
			:options="filterable"
			label="label"
			trackBy="key"
			@update:modelValue="onField" />
		<NcSelect
			v-if="field"
			v-model="value"
			:inputLabel="field.label"
			:options="field.options || []"
			data-testid="detail-filter-value"
			@update:modelValue="emitFilter" />
	</div>
</template>

<script>
import { NcSelect } from '@conduction/nextcloud-vue'
import { t } from '@nextcloud/l10n'

/**
 * DetailFilter: "Filter by detail" in the dashboard switcher. Offered only
 * when a select or multi-select detail field exists. Emits `change` with
 * `{<key>: <value>}`, or null when cleared (REQ-MDUI-003).
 *
 * @spec openspec/changes/dashboard-language-and-details-tabs/specs/dashboard-metadata-fields/spec.md
 */
export default {
	name: 'DetailFilter',

	components: {
		NcSelect,
	},

	props: {
		/** Detail field definitions (`key`, `label`, `type`, `options`). */
		fields: {
			type: Array,
			default: () => [],
		},
	},

	emits: ['change'],

	/** @spec openspec/changes/dashboard-language-and-details-tabs/specs/dashboard-metadata-fields/spec.md */
	data() {
		return { field: null, value: null }
	},

	computed: {
		/**
		 * @return {Array<object>} Fields a list can be filtered by.
		 * @spec openspec/changes/dashboard-language-and-details-tabs/specs/dashboard-metadata-fields/spec.md
		 */
		filterable() {
			return this.fields.filter(
				(f) =>
					(f.type === 'select' || f.type === 'multi-select')
					&& Array.isArray(f.options)
					&& f.options.length > 0,
			)
		},
	},

	methods: {
		t,

		/**
		 * A new field was chosen: start without a value.
		 *
		 * @spec openspec/changes/dashboard-language-and-details-tabs/specs/dashboard-metadata-fields/spec.md
		 */
		onField() {
			this.value = null
			this.emitFilter()
		},

		/**
		 * Tell the host the filter to apply.
		 *
		 * @spec openspec/changes/dashboard-language-and-details-tabs/specs/dashboard-metadata-fields/spec.md
		 */
		emitFilter() {
			if (this.field && this.value) {
				this.$emit('change', { [this.field.key]: this.value })
			} else {
				this.$emit('change', null)
			}
		},
	},
}
</script>

<style scoped>
.detail-filter {
	display: flex;
	flex-direction: column;
	gap: 4px;
	padding: 0 8px 8px;
}
</style>
