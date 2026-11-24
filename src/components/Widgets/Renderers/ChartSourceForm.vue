<!--
  - SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
  - SPDX-License-Identifier: EUPL-1.2
-->

<template>
	<div class="chart-source-form">
		<fieldset class="chart-source-form__source">
			<legend>{{ t('launchpad', 'Data source') }}</legend>
			<NcCheckboxRadioSwitch
				:modelValue="source"
				value="register"
				:name="`${domId}-source`"
				type="radio"
				data-testid="chart-source-register"
				@update:modelValue="setSource('register')">
				{{ t('launchpad', 'A register query') }}
			</NcCheckboxRadioSwitch>
			<NcCheckboxRadioSwitch
				:modelValue="source"
				value="manual"
				:name="`${domId}-source`"
				type="radio"
				data-testid="chart-source-manual"
				@update:modelValue="setSource('manual')">
				{{ t('launchpad', 'Values you enter') }}
			</NcCheckboxRadioSwitch>
		</fieldset>

		<CnChartWidgetForm
			v-if="source === 'register'"
			ref="registerForm"
			:value="registerValue"
			@update:content="onRegisterContent" />

		<PeriodFieldSelect
			v-if="source === 'register'"
			:register="registerSource.register"
			:schema="registerSource.schema"
			:modelValue="periodField"
			@update:modelValue="setPeriodField" />

		<template v-else-if="kind === 'pie'">
			<div
				v-for="(row, index) in slices"
				:key="`slice-${index}`"
				class="chart-source-form__row"
				data-testid="chart-source-slice">
				<NcTextField
					:modelValue="row.label"
					:label="t('launchpad', 'Label')"
					@update:modelValue="setSlice(index, { label: String($event) })" />
				<NcTextField
					:modelValue="String(row.value)"
					type="number"
					:label="t('launchpad', 'Value')"
					@update:modelValue="setSlice(index, { value: Number($event) })" />
				<NcTextField
					:modelValue="row.colour"
					:label="t('launchpad', 'Colour (optional)')"
					placeholder="#3669a5"
					@update:modelValue="setSlice(index, { colour: String($event) })" />
				<NcButton variant="tertiary" :aria-label="t('launchpad', 'Remove this value')" @click="removeSlice(index)">
					<template #icon>
						<Delete :size="18" />
					</template>
				</NcButton>
			</div>
			<NcButton data-testid="chart-source-add-slice" @click="addSlice">
				{{ t('launchpad', 'Add a value') }}
			</NcButton>
		</template>

		<template v-else>
			<NcTextField
				:modelValue="categoriesText"
				:label="t('launchpad', 'Labels on the horizontal axis, separated by commas')"
				placeholder="apr, mei, jun"
				data-testid="chart-source-categories"
				@update:modelValue="setCategories(String($event))" />
			<div
				v-for="(row, index) in lines"
				:key="`series-${index}`"
				class="chart-source-form__row"
				data-testid="chart-source-series">
				<NcTextField
					:modelValue="row.name"
					:label="t('launchpad', 'Series name')"
					@update:modelValue="setLine(index, { name: String($event) })" />
				<NcSelect
					:modelValue="row.type"
					:options="['column', 'line']"
					:inputLabel="t('launchpad', 'Drawn as')"
					:clearable="false"
					@update:modelValue="setLine(index, { type: $event })">
					<template #option="{ label }">
						{{ seriesTypeLabel(label) }}
					</template>
					<template #selected-option="{ label }">
						{{ seriesTypeLabel(label) }}
					</template>
				</NcSelect>
				<NcTextField
					:modelValue="row.valuesText"
					:label="t('launchpad', 'Values, separated by commas')"
					placeholder="112, 98, 130"
					@update:modelValue="setLine(index, { valuesText: String($event) })" />
				<NcButton variant="tertiary" :aria-label="t('launchpad', 'Remove this series')" @click="removeLine(index)">
					<template #icon>
						<Delete :size="18" />
					</template>
				</NcButton>
			</div>
			<NcButton data-testid="chart-source-add-series" @click="addLine">
				{{ t('launchpad', 'Add a series') }}
			</NcButton>
		</template>
	</div>
</template>

<script>
import { CnChartWidgetForm } from '@conduction/nextcloud-vue'
import { translate as t } from '@nextcloud/l10n'
import { NcButton, NcCheckboxRadioSwitch, NcSelect, NcTextField } from '@nextcloud/vue'
import Delete from 'vue-material-design-icons/Delete.vue'
import PeriodFieldSelect from './PeriodFieldSelect.vue'
import { NULLABLE_OBJECT_PROP, OBJECT_PROP } from '../../../constants/propDefinitions.js'

let domIdCounter = 0

/**
 * The numbers in a comma-separated text; a part that is no number is 0.
 *
 * @param {string} text The text.
 * @return {number[]}
 */
function numbersOf(text) {
	return String(text || '').split(',').map((part) => part.trim()).filter((part) => part !== '').map((part) => Number(part.replace(',', '.')) || 0)
}

/**
 * Whether content is a pie chart or a graph, by its chart kind.
 *
 * @param {object} content The content.
 * @return {string} `pie` or `graph`.
 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md#requirement-a-chart-reads-a-register-query-or-entered-values-req-chartp-003
 */
export function kindOf(content) {
	return ['donut', 'pie'].includes(content?.chartKind) ? 'pie' : 'graph'
}

/**
 * The entered slices of a pie chart's content, for the form.
 *
 * @param {object} content The content.
 * @return {Array<{label: string, value: number, colour: string}>}
 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md#requirement-a-chart-reads-a-register-query-or-entered-values-req-chartp-003
 */
function slicesOf(content) {
	const labels = Array.isArray(content.labels) ? content.labels : []
	const values = Array.isArray(content.series) ? content.series : []
	const colours = content.colorMap && typeof content.colorMap === 'object' ? content.colorMap : {}
	return labels.map((label, index) => ({ label: String(label), value: Number(values[index]) || 0, colour: String(colours[label] || '') }))
}

/**
 * The entered series of a graph's content, for the form.
 *
 * @param {object} content The content.
 * @return {Array<{name: string, type: string, valuesText: string}>}
 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md#requirement-a-chart-reads-a-register-query-or-entered-values-req-chartp-003
 */
function linesOf(content) {
	return (Array.isArray(content.series) ? content.series : []).map((line) => ({
		name: String(line?.name || ''),
		type: line?.type === 'line' ? 'line' : 'column',
		valuesText: Array.isArray(line?.data) ? line.data.join(', ') : '',
	}))
}

/**
 * ChartSourceForm: the data source of a pie chart or a graph (boards
 * LpManagement "Cirkeldiagram" and "Grafiek"). The source is a register
 * query, edited by the shared chart form, or values the author enters: label,
 * value and colour per slice for a pie chart; axis labels and named series,
 * each drawn as bars or a line, for a graph. Whether it edits a pie chart or a
 * graph follows the content's chart kind, so both widget types use this form.
 *
 * Emits the whole content: the preset keys of the widget kept, `source`, and
 * either `chartKind` with `dataSource`, or the manual `labels`/`series`/
 * `colorMap` (pie) or `categories`/`series` (graph).
 *
 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md
 */
export default {
	name: 'ChartSourceForm',

	components: { CnChartWidgetForm, Delete, NcButton, NcCheckboxRadioSwitch, NcSelect, NcTextField, PeriodFieldSelect },

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

	data() {
		const initial = this.editingWidget?.content || this.value || {}
		const kind = kindOf(initial)
		return {
			domId: `chart-source-${++domIdCounter}`,
			kind,
			initial,
			source: initial.source === 'manual' ? 'manual' : 'register',
			registerContent: { chartKind: initial.chartKind, dataSource: initial.dataSource },
			slices: kind === 'pie' ? slicesOf(initial) : [],
			categoriesText: Array.isArray(initial.categories) ? initial.categories.join(', ') : '',
			lines: kind === 'graph' ? linesOf(initial) : [],
			periodField: typeof initial.dataSource?.periodField === 'string' ? initial.dataSource.periodField : '',
		}
	},

	computed: {
		/**
		 * @return {object} What the shared chart form starts from.
		 *
		 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md#requirement-a-chart-reads-a-register-query-or-entered-values-req-chartp-003
		 */
		registerValue() {
			return { chartKind: this.initial.chartKind, dataSource: this.initial.dataSource }
		},

		/**
		 * The register and schema the register query reads, for the period's date field.
		 *
		 * @return {{register: string, schema: string}}
		 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
		 */
		registerSource() {
			const dataSource = this.registerContent?.dataSource || {}
			return { register: String(dataSource.register || ''), schema: String(dataSource.schema || '') }
		},

		/**
		 * The content the widget saves.
		 *
		 * @return {object}
		 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md#requirement-a-chart-reads-a-register-query-or-entered-values-req-chartp-003
		 */
		assembledContent() {
			const { labels, series, colorMap, categories, dataSource, ...preset } = this.initial
			if (this.source === 'register') {
				return { ...preset, source: 'register', ...this.registerContent, dataSource: this.datedSource(this.registerContent.dataSource) }
			}
			if (this.kind === 'pie') {
				const shown = this.slices.filter((row) => row.label.trim() !== '')
				return {
					...preset,
					source: 'manual',
					labels: shown.map((row) => row.label.trim()),
					series: shown.map((row) => row.value),
					colorMap: Object.fromEntries(shown.filter((row) => row.colour.trim() !== '').map((row) => [row.label.trim(), row.colour.trim()])),
				}
			}
			return {
				...preset,
				source: 'manual',
				categories: this.categoriesText.split(',').map((part) => part.trim()).filter((part) => part !== ''),
				series: this.lines.map((row) => ({ name: row.name.trim(), type: row.type, data: numbersOf(row.valuesText) })),
			}
		},
	},

	methods: {
		t,

		/**
		 * @param {string} type `column` or `line`.
		 * @return {string} How the series is drawn, in words.
		 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md#requirement-a-chart-reads-a-register-query-or-entered-values-req-chartp-003
		 */
		seriesTypeLabel(type) {
			return type === 'line' ? t('launchpad', 'Line') : t('launchpad', 'Bars')
		},

		/**
		 * Tell the modal the whole content.
		 *
		 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md#requirement-a-chart-reads-a-register-query-or-entered-values-req-chartp-003
		 */
		emitContent() {
			this.$emit('update:content', this.assembledContent)
		},

		/**
		 * @param {string} source `register` or `manual`.
		 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md#requirement-a-chart-reads-a-register-query-or-entered-values-req-chartp-003
		 */
		setSource(source) {
			this.source = source
			if (source === 'manual' && this.kind === 'pie' && this.slices.length === 0) {
				this.addSlice()
				return
			}
			if (source === 'manual' && this.kind === 'graph' && this.lines.length === 0) {
				this.addLine()
				return
			}
			this.emitContent()
		},

		/**
		 * The register query's data source with the period's date field, or without one when none is chosen.
		 *
		 * @param {object|null} dataSource The data source.
		 * @return {object|null}
		 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
		 */
		datedSource(dataSource) {
			if (!dataSource || typeof dataSource !== 'object') {
				return dataSource
			}
			const { periodField, ...rest } = dataSource
			return this.periodField === '' ? rest : { ...rest, periodField: this.periodField }
		},

		/**
		 * @param {string} field The date field for the period, '' for none.
		 *
		 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
		 */
		setPeriodField(field) {
			this.periodField = typeof field === 'string' ? field : ''
			this.emitContent()
		},

		/**
		 * @param {object} content What the shared chart form emits.
		 *
		 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md#requirement-a-chart-reads-a-register-query-or-entered-values-req-chartp-003
		 */
		onRegisterContent(content) {
			this.registerContent = { chartKind: content.chartKind, dataSource: content.dataSource }
			this.emitContent()
		},

		/**
		 * Add an empty slice.
		 *
		 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md#requirement-a-chart-reads-a-register-query-or-entered-values-req-chartp-003
		 */
		addSlice() {
			this.slices.push({ label: '', value: 0, colour: '' })
			this.emitContent()
		},

		/**
		 * @param {number} index The slice.
		 * @param {object} change The changed keys.
		 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md#requirement-a-chart-reads-a-register-query-or-entered-values-req-chartp-003
		 */
		setSlice(index, change) {
			Object.assign(this.slices[index], change)
			this.emitContent()
		},

		/**
		 * @param {number} index The slice.
		 *
		 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md#requirement-a-chart-reads-a-register-query-or-entered-values-req-chartp-003
		 */
		removeSlice(index) {
			this.slices.splice(index, 1)
			this.emitContent()
		},

		/**
		 * @param {string} text The comma-separated axis labels.
		 *
		 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md#requirement-a-chart-reads-a-register-query-or-entered-values-req-chartp-003
		 */
		setCategories(text) {
			this.categoriesText = text
			this.emitContent()
		},

		/**
		 * Add an empty series, as bars.
		 *
		 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md#requirement-a-chart-reads-a-register-query-or-entered-values-req-chartp-003
		 */
		addLine() {
			this.lines.push({ name: '', type: 'column', valuesText: '' })
			this.emitContent()
		},

		/**
		 * @param {number} index The series.
		 * @param {object} change The changed keys.
		 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md#requirement-a-chart-reads-a-register-query-or-entered-values-req-chartp-003
		 */
		setLine(index, change) {
			Object.assign(this.lines[index], change)
			this.emitContent()
		},

		/**
		 * @param {number} index The series.
		 *
		 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md#requirement-a-chart-reads-a-register-query-or-entered-values-req-chartp-003
		 */
		removeLine(index) {
			this.lines.splice(index, 1)
			this.emitContent()
		},

		/**
		 * Validate: the register query through the shared form; entered values
		 * need at least one labelled slice, or axis labels and a named series.
		 *
		 * @return {string[]} The validation errors.
		 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md#requirement-a-chart-reads-a-register-query-or-entered-values-req-chartp-003
		 */
		validate() {
			if (this.source === 'register') {
				const form = this.$refs.registerForm
				return typeof form?.validate === 'function' ? form.validate() : []
			}
			const content = this.assembledContent
			if (this.kind === 'pie') {
				return content.labels.length > 0 ? [] : [t('launchpad', 'Enter at least one value with a label')]
			}
			const named = content.series.filter((line) => line.name !== '' && line.data.length > 0)
			return content.categories.length > 0 && named.length > 0
				? []
				: [t('launchpad', 'Enter the axis labels and at least one named series with values')]
		},
	},
}
</script>

<style scoped>
.chart-source-form {
	display: flex;
	flex-direction: column;
	gap: 12px;
}

.chart-source-form__source {
	margin: 0;
	padding: 0;
	border: 0;
}

.chart-source-form__row {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-end;
	gap: 8px;
}

.chart-source-form__row > * {
	flex: 1 1 140px;
}

.chart-source-form__row > .button-vue {
	flex: 0 0 auto;
}
</style>
