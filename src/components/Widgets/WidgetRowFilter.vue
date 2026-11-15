<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<input
		:value="modelValue"
		type="search"
		class="widget-row-filter"
		:aria-label="t('launchpad', 'Filter these results')"
		:placeholder="t('launchpad', 'Filter these results')"
		data-testid="widget-row-filter"
		@input="$emit('update:modelValue', $event.target.value)"
		@keydown.esc="$emit('update:modelValue', '')">
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { TEXT_PROP } from '../../constants/propDefinitions.js'

/**
 * WidgetRowFilter — the filter field over one widget's rendered rows
 * (REQ-DWMS-008).
 *
 * It holds nothing: the widget keeps the term in its own component state,
 * so the term is never persisted, never shared and gone after a reload, and
 * it never writes to the placement or its saved search.
 *
 * @spec openspec/changes/dashboards-and-who-may-see-them/specs/dashboards-and-who-may-see-them/spec.md#requirement-req-dwms-008-search-inside-a-widgets-results
 */
export default {
	name: 'WidgetRowFilter',

	props: {
		/**
		 * The filter term, owned by the widget's view state.
		 */
		modelValue: TEXT_PROP,
	},

	emits: ['update:modelValue'],

	methods: {
		t,
	},
}
</script>

<style scoped>
.widget-row-filter {
	width: 100%;
	margin: 0 0 8px;
	padding: 4px 8px;
	border: 1px solid var(--color-border-maxcontrast);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
}
</style>
