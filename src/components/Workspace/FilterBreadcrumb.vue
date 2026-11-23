<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div v-if="trails.length > 0" class="filter-breadcrumb" data-testid="filter-breadcrumbs">
		<nav
			v-for="trail in trails"
			:key="trail.id"
			class="filter-breadcrumb__trail"
			:aria-label="t('launchpad', 'Drill-down path')"
			:data-hierarchy="trail.id"
			data-testid="filter-breadcrumb">
			<ol class="filter-breadcrumb__list">
				<li
					v-for="(step, index) in trail.steps"
					:key="step.dimension"
					class="filter-breadcrumb__item"
					:data-dimension="step.dimension">
					<span
						v-if="index === trail.steps.length - 1"
						class="filter-breadcrumb__current"
						aria-current="location"
						data-testid="filter-breadcrumb-step">{{ crumbText(step) }}</span>
					<button
						v-else
						type="button"
						class="filter-breadcrumb__link"
						:aria-label="t('launchpad', 'Back to {step}', { step: crumbText(step) })"
						data-testid="filter-breadcrumb-step"
						@click="$emit('pop', step.dimension)">
						{{ crumbText(step) }}
					</button>
					<span
						v-if="index < trail.steps.length - 1"
						class="filter-breadcrumb__separator"
						aria-hidden="true">&gt;</span>
				</li>
			</ol>
		</nav>
	</div>
</template>

<script>
import { t } from '@nextcloud/l10n'
import { breadcrumbPath } from '../../composables/useFilterBus.js'
import { LIST_PROP, OBJECT_PROP } from '../../constants/propDefinitions.js'

/**
 * FilterBreadcrumb: the drill-down path of each of the dashboard's
 * hierarchies (drill-down-cross-widget-filter REQ-CWF-004), such as
 * "Province PV26 > Municipality GM0355 > District WK035501". Each level above
 * the deepest is a button that goes back up to that level (`pop` with its
 * dimension); the deepest level is the current one. A hierarchy with no
 * active level shows no path, and without any the component renders nothing.
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-004-drill-down-hierarchies-update-breadcrumb-and-descendant-filters
 */
export default {
	name: 'FilterBreadcrumb',

	props: {
		/** The dashboard's hierarchy descriptors. */
		hierarchies: LIST_PROP,
		/** The bus's clauses. */
		clauses: LIST_PROP,
		/** A display label per dimension; the stored level label when absent. */
		labels: OBJECT_PROP,
	},

	emits: ['pop'],

	computed: {
		/**
		 * The path of each hierarchy that has an active level.
		 *
		 * @return {Array<{id: string, steps: Array<object>}>}
		 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-004-drill-down-hierarchies-update-breadcrumb-and-descendant-filters
		 */
		trails() {
			return this.hierarchies
				.filter((hierarchy) => hierarchy && typeof hierarchy.id === 'string')
				.map((hierarchy) => ({ id: hierarchy.id, steps: breadcrumbPath(this.hierarchies, this.clauses, hierarchy.id) }))
				.filter((trail) => trail.steps.length > 0)
		},
	},

	methods: {
		t,

		/**
		 * A step as the reader sees it: the level's label and its value.
		 *
		 * @param {{dimension: string, label: string, value: unknown}} step The step.
		 * @return {string}
		 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md#requirement-req-cwf-004-drill-down-hierarchies-update-breadcrumb-and-descendant-filters
		 */
		crumbText(step) {
			const label = this.labels[step.dimension] || step.label || step.dimension
			const value = Array.isArray(step.value) ? step.value.join(', ') : String(step.value ?? '')
			return `${label} ${value}`
		},
	},
}
</script>

<style scoped>
.filter-breadcrumb {
	display: flex;
	flex-direction: column;
	gap: 4px;
	margin: 0 0 8px;
}

.filter-breadcrumb__list {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 4px;
	margin: 0;
	padding: 0;
	list-style: none;
}

.filter-breadcrumb__item {
	display: inline-flex;
	align-items: center;
	gap: 4px;
}

.filter-breadcrumb__link {
	min-height: 24px;
	margin: 0;
	padding: 0 4px;
	border: none;
	border-radius: var(--border-radius-small);
	background: transparent;
	color: var(--color-main-text);
	font: inherit;
	text-decoration: underline;
	cursor: pointer;
}

.filter-breadcrumb__link:hover,
.filter-breadcrumb__link:focus-visible {
	background-color: var(--color-background-hover);
}

.filter-breadcrumb__current {
	padding: 0 4px;
	font-weight: bold;
	color: var(--color-main-text);
}

.filter-breadcrumb__separator {
	color: var(--color-text-maxcontrast);
}
</style>
