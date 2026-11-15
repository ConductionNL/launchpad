<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<section
		v-if="clauses.length > 0"
		class="active-filters"
		:aria-label="t('launchpad', 'Active filters')"
		data-testid="active-filters">
		<span class="active-filters__heading">{{ t('launchpad', 'Active filters') }}</span>
		<ul class="active-filters__list">
			<li
				v-for="clause in clauses"
				:key="clause.dimension"
				class="active-filters__chip"
				:class="{ 'active-filters__chip--pinned': clause.pinned }"
				:data-dimension="clause.dimension">
				<Lock
					v-if="clause.pinned"
					:size="16"
					class="active-filters__lock"
					:title="t('launchpad', 'Pinned filter')"
					data-testid="active-filter-lock" />
				<span class="active-filters__label">{{ labelOf(clause) }}:</span>
				<span class="active-filters__value">{{ valueOf(clause) }}</span>
				<span
					v-if="sourceName(clause)"
					class="active-filters__source"
					data-testid="active-filter-source">({{ sourceName(clause) }})</span>
				<button
					v-if="!clause.pinned"
					type="button"
					class="active-filters__remove"
					:aria-label="t('launchpad', 'Remove filter: {label}', { label: labelOf(clause) })"
					data-testid="active-filter-remove"
					@click="$emit('remove', clause.dimension)">
					<Close :size="16" />
				</button>
			</li>
		</ul>
		<NcButton
			v-if="hasRemovable"
			variant="tertiary"
			data-testid="reset-filters"
			@click="$emit('reset')">
			<template #icon>
				<FilterRemove :size="20" />
			</template>
			{{ t('launchpad', 'Reset filters') }}
		</NcButton>
	</section>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { NcButton } from '@nextcloud/vue'
import Close from 'vue-material-design-icons/Close.vue'
import FilterRemove from 'vue-material-design-icons/FilterRemove.vue'
import Lock from 'vue-material-design-icons/Lock.vue'
import { LIST_PROP, OBJECT_PROP } from '../../constants/propDefinitions.js'

/**
 * ActiveFiltersPanel: the clauses of the dashboard's filter bus as chips
 * (drill-down-cross-widget-filter REQ-CWF-007), with "Reset filters"
 * (REQ-CWF-006). A clause a widget click made names that widget; a pinned
 * clause shows a lock and cannot be removed. The panel is absent while the
 * bus is empty, and "Reset filters" only shows when there is something it
 * would clear, so pressing it is never a no-op.
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-007-filter-stack-ui-shows-every-active-clause-with-its-source
 */
export default {
	name: 'ActiveFiltersPanel',

	components: { NcButton, Close, FilterRemove, Lock },

	props: {
		/** The bus's clauses (`dimension`, `operator`, `value`, `source`, `sourceWidgetId`, `pinned`). */
		clauses: LIST_PROP,
		/** A display label per dimension; the dimension itself when absent. */
		labels: OBJECT_PROP,
		/** A display name per widget id, for clauses a widget click made. */
		widgetNames: OBJECT_PROP,
	},

	emits: ['remove', 'reset'],

	computed: {
		/**
		 * Whether any clause is not pinned, so a reset would clear something.
		 *
		 * @return {boolean}
		 */
		hasRemovable() {
			return this.clauses.some((clause) => !clause.pinned)
		},
	},

	methods: {
		t,

		/**
		 * The clause's dimension label.
		 *
		 * @param {object} clause The clause.
		 * @return {string}
		 */
		labelOf(clause) {
			return this.labels[clause.dimension] || clause.dimension
		},

		/**
		 * The clause's value as text: a list joined, a range with a dash.
		 *
		 * @param {object} clause The clause.
		 * @return {string}
		 */
		valueOf(clause) {
			if (Array.isArray(clause.value)) {
				return clause.value.join(clause.operator === 'between' ? ' – ' : ', ')
			}
			return clause.value === undefined || clause.value === null ? '' : String(clause.value)
		},

		/**
		 * The widget a widget-click clause came from, or '' for any other source.
		 *
		 * @param {object} clause The clause.
		 * @return {string}
		 */
		sourceName(clause) {
			if (clause.source !== 'widgetClick' || !clause.sourceWidgetId) {
				return ''
			}
			return this.widgetNames[clause.sourceWidgetId] || ''
		},
	},
}
</script>

<style scoped>
.active-filters {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 8px;
	margin: 0 0 8px;
}

.active-filters__heading {
	font-weight: bold;
	color: var(--color-main-text);
}

.active-filters__list {
	display: flex;
	flex-wrap: wrap;
	gap: 6px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.active-filters__chip {
	display: inline-flex;
	align-items: center;
	gap: 4px;
	padding: 2px 4px 2px 10px;
	border-radius: var(--border-radius-pill);
	background-color: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
}

.active-filters__chip--pinned {
	padding-inline-end: 10px;
}

.active-filters__source {
	color: var(--color-text-maxcontrast);
}

.active-filters__remove {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 24px;
	min-height: 24px;
	margin: 0;
	padding: 0;
	border: none;
	border-radius: 50%;
	background: transparent;
	color: inherit;
	cursor: pointer;
}

.active-filters__remove:hover,
.active-filters__remove:focus-visible {
	background-color: var(--color-background-hover);
}
</style>
