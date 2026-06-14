<!--
  - SPDX-FileCopyrightText: 2026 LaunchPad Contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<div class="spend-analytics-form">
		<NcSelect
			:value="viewMode"
			:options="viewModeOptions"
			:input-label="t('launchpad', 'View mode')"
			:reduce="(option) => option.value"
			label="label"
			:clearable="false"
			@input="updateField('viewMode', $event)" />

		<NcSelect
			:value="period"
			:options="periodOptions"
			:input-label="t('launchpad', 'Period')"
			:reduce="(option) => option.value"
			label="label"
			:clearable="false"
			@input="updateField('period', $event)" />

		<NcTextField
			:value="categoryIdsString"
			:label="t('launchpad', 'Category filter (comma separated CPV / category ids)')"
			:placeholder="t('launchpad', 'e.g. 30190000, 48000000')"
			@update:value="updateListField('categoryIds', $event)" />

		<NcTextField
			:value="departmentIdsString"
			:label="t('launchpad', 'Department filter (comma separated cost-centre ids)')"
			@update:value="updateListField('departmentIds', $event)" />

		<NcTextField
			:value="vendorIdsString"
			:label="t('launchpad', 'Vendor filter (comma separated supplier ids)')"
			@update:value="updateListField('vendorIds', $event)" />

		<NcSelect
			:value="drillThroughTarget"
			:options="drillThroughOptions"
			:input-label="t('launchpad', 'Drill-through behaviour')"
			:reduce="(option) => option.value"
			label="label"
			:clearable="false"
			@input="updateField('drillThroughTarget', $event)" />

		<label class="spend-analytics-form__toggle">
			<input
				type="checkbox"
				:checked="attachEvidence"
				@change="updateField('attachEvidence', $event.target.checked)">
			{{ t('launchpad', 'Surface attached evidence documents') }}
		</label>

		<label class="spend-analytics-form__toggle">
			<input
				type="checkbox"
				:checked="aiInsightsEnabled"
				@change="updateAiInsights($event.target.checked)">
			{{ t('launchpad', 'Show AI-generated narrative (via local LLM)') }}
		</label>
	</div>
</template>

<script>
import { NcTextField, NcSelect } from '@conduction/nextcloud-vue'

const VIEW_MODES = Object.freeze(['summary', 'trend', 'top-vendors', 'top-categories'])
const PERIODS = Object.freeze(['month', 'quarter', 'ytd', 'fy'])
const DRILL_TARGETS = Object.freeze(['detail-page', 'sibling-app'])

const DEFAULT_CONTENT = Object.freeze({
	viewMode: 'summary',
	period: 'quarter',
	filters: { categoryIds: [], departmentIds: [], vendorIds: [] },
	drillThroughTarget: 'detail-page',
	attachEvidence: true,
	aiInsights: { enabled: false },
})

/**
 * SpendAnalyticsForm — sub-form for the AddWidgetModal when the user is
 * creating or editing a `spend-analytics` placement (REQ-SAW-003).
 *
 * Persists the content contract: `viewMode`, `period`, `filters`
 * (categoryIds / departmentIds / vendorIds), `drillThroughTarget`,
 * `attachEvidence`, `aiInsights.enabled`. `validate()` rejects an
 * out-of-enum `viewMode` / `period` so the modal keeps Add/Save
 * disabled (REQ-SAW-003 scenario "Invalid viewMode rejected").
 */
export default {
	name: 'SpendAnalyticsForm',

	components: {
		NcTextField,
		NcSelect,
	},

	props: {
		editingWidget: {
			type: Object,
			default: null,
		},
		value: {
			type: Object,
			default: () => ({ ...DEFAULT_CONTENT }),
		},
	},

	emits: ['update:content'],

	data() {
		const initial = this.editingWidget?.content || this.value || {}
		const filters = initial.filters && typeof initial.filters === 'object' ? initial.filters : {}
		return {
			viewMode: VIEW_MODES.includes(initial.viewMode) ? initial.viewMode : DEFAULT_CONTENT.viewMode,
			period: PERIODS.includes(initial.period) ? initial.period : DEFAULT_CONTENT.period,
			categoryIds: this.coerceList(filters.categoryIds),
			departmentIds: this.coerceList(filters.departmentIds),
			vendorIds: this.coerceList(filters.vendorIds),
			drillThroughTarget: DRILL_TARGETS.includes(initial.drillThroughTarget) ? initial.drillThroughTarget : DEFAULT_CONTENT.drillThroughTarget,
			attachEvidence: typeof initial.attachEvidence === 'boolean' ? initial.attachEvidence : DEFAULT_CONTENT.attachEvidence,
			aiInsightsEnabled: initial.aiInsights?.enabled === true,
		}
	},

	computed: {
		/** @spec openspec/specs/launchpad-spend-analytics-widget/spec.md */
		viewModeOptions() {
			return [
				{ value: 'summary', label: t('launchpad', 'Summary') },
				{ value: 'trend', label: t('launchpad', 'Trend') },
				{ value: 'top-vendors', label: t('launchpad', 'Top vendors') },
				{ value: 'top-categories', label: t('launchpad', 'Top categories') },
			]
		},

		/** @spec openspec/specs/launchpad-spend-analytics-widget/spec.md */
		periodOptions() {
			return [
				{ value: 'month', label: t('launchpad', 'This month') },
				{ value: 'quarter', label: t('launchpad', 'This quarter') },
				{ value: 'ytd', label: t('launchpad', 'Year to date') },
				{ value: 'fy', label: t('launchpad', 'Fiscal year') },
			]
		},

		/** @spec openspec/specs/launchpad-spend-analytics-widget/spec.md */
		drillThroughOptions() {
			return [
				{ value: 'detail-page', label: t('launchpad', 'Open detail panel inside launchpad') },
				{ value: 'sibling-app', label: t('launchpad', 'Deep-link to the owning app') },
			]
		},

		/** @spec openspec/specs/launchpad-spend-analytics-widget/spec.md */
		categoryIdsString() {
			return this.categoryIds.join(', ')
		},

		/** @spec openspec/specs/launchpad-spend-analytics-widget/spec.md */
		departmentIdsString() {
			return this.departmentIds.join(', ')
		},

		/** @spec openspec/specs/launchpad-spend-analytics-widget/spec.md */
		vendorIdsString() {
			return this.vendorIds.join(', ')
		},

		/** @spec openspec/specs/launchpad-spend-analytics-widget/spec.md */
		assembledContent() {
			return {
				viewMode: this.viewMode,
				period: this.period,
				filters: {
					categoryIds: [...this.categoryIds],
					departmentIds: [...this.departmentIds],
					vendorIds: [...this.vendorIds],
				},
				drillThroughTarget: this.drillThroughTarget,
				attachEvidence: this.attachEvidence,
				aiInsights: { enabled: this.aiInsightsEnabled },
			}
		},
	},

	methods: {
		/** @spec openspec/specs/launchpad-spend-analytics-widget/spec.md */
		coerceList(raw) {
			if (!Array.isArray(raw)) {
				return []
			}
			return raw.filter((entry) => typeof entry === 'string' && entry.trim() !== '')
		},

		/** @spec openspec/specs/launchpad-spend-analytics-widget/spec.md */
		updateField(field, value) {
			this[field] = value
			this.$emit('update:content', this.assembledContent)
		},

		/** @spec openspec/specs/launchpad-spend-analytics-widget/spec.md */
		updateListField(field, value) {
			const raw = String(value || '')
			this[field] = raw
				.split(',')
				.map((entry) => entry.trim())
				.filter((entry) => entry !== '')
			this.$emit('update:content', this.assembledContent)
		},

		/** @spec openspec/specs/launchpad-spend-analytics-widget/spec.md */
		updateAiInsights(enabled) {
			this.aiInsightsEnabled = enabled === true
			this.$emit('update:content', this.assembledContent)
		},

		/**
		 * REQ-SAW-003: an out-of-enum `viewMode` or `period` MUST fail
		 * validation so the modal keeps the Add/Save button disabled.
		 *
		 * @return {string[]} validation errors
		 */
		/** @spec openspec/specs/launchpad-spend-analytics-widget/spec.md */
		validate() {
			const errors = []
			if (!VIEW_MODES.includes(this.viewMode)) {
				errors.push(t('launchpad', 'Invalid view mode'))
			}
			if (!PERIODS.includes(this.period)) {
				errors.push(t('launchpad', 'Invalid period'))
			}
			return errors
		},
	},
}
</script>

<style scoped>
.spend-analytics-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.spend-analytics-form__toggle {
	display: flex;
	align-items: center;
	gap: 8px;
	cursor: pointer;
}
</style>
