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
 * ChartSourceForm: the data source of a pie chart or a graph (boards
 * LpManagement "Cirkeldiagram" and "Grafiek"). The source is a register
 * query, edited by the shared chart form, or values the author enters: label,
 * value and colour per slice for a pie chart; axis labels and named series,
 * each drawn as bars or a line, for a graph.
 *
 * Emits the whole content: the preset keys of the widget kept, `source`, and
 * either `chartKind` with `dataSource`, or the manual `labels`/`series`/
 * `colorMap` (pie) or `categories`/`series` (graph).
 *
 * @spec openspec/changes/chart-presets/specs/chart-presets/spec.md
 */
export default {
	name: 'ChartSourceForm',

	components: { CnChartWidgetForm, Delete, NcButton, NcCheckboxRadioSwitch, NcSelect, NcTextField },

	props: {
		/** `pie` or `graph`. */
		kind: {
			type: String,
			default: 'pie',
			validator: (value) => ['pie', 'graph'].includes(value),
		},

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
		const labels = Array.isArray(initial.labels) ? initial.labels : []
		const values = Array.isArray(initial.series) && this.kind === 'pie' ? initial.series : []
		const colours = initial.colorMap && typeof initial.colorMap === 'object' ? initial.colorMap : {}
		const lines = Array.isArray(initial.series) && this.kind === 'graph' ? initial.series : []
		return {
			domId: `chart-source-${++domIdCounter}`,
			initial,
			source: initial.source === 'manual' ? 'manual' : 'register',
			registerContent: { chartKind: initial.chartKind, dataSource: initial.dataSource },
			slices: labels.map((label, index) => ({ label: String(label), value: Number(values[index]) || 0, colour: String(colours[label] || '') })),
			categoriesText: Array.isArray(initial.categories) ? initial.categories.join(', ') : '',
			lines: lines.map((line) => ({
				name: String(line?.name || ''),
				type: line?.type === 'line' ? 'line' : 'column',
				valuesText: Array.isArray(line?.data) ? line.data.join(', ') : '',
			})),
		}
	},

	computed: {
		/** @return {object} What the shared chart form starts from. */
		registerValue() {
			return { chartKind: this.initial.chartKind, dataSource: this.initial.dataSource }
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
				return { ...preset, source: 'register', ...this.registerContent }
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
		 */
		seriesTypeLabel(type) {
			return type === 'line' ? t('launchpad', 'Line') : t('launchpad', 'Bars')
		},

		/** Tell the modal the whole content. */
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

		/** @param {object} content What the shared chart form emits. */
		onRegisterContent(content) {
			this.registerContent = { chartKind: content.chartKind, dataSource: content.dataSource }
			this.emitContent()
		},

		/** Add an empty slice. */
		addSlice() {
			this.slices.push({ label: '', value: 0, colour: '' })
			this.emitContent()
		},

		/**
		 * @param {number} index The slice.
		 * @param {object} change The changed keys.
		 */
		setSlice(index, change) {
			Object.assign(this.slices[index], change)
			this.emitContent()
		},

		/** @param {number} index The slice. */
		removeSlice(index) {
			this.slices.splice(index, 1)
			this.emitContent()
		},

		/** @param {string} text The comma-separated axis labels. */
		setCategories(text) {
			this.categoriesText = text
			this.emitContent()
		},

		/** Add an empty series, as bars. */
		addLine() {
			this.lines.push({ name: '', type: 'column', valuesText: '' })
			this.emitContent()
		},

		/**
		 * @param {number} index The series.
		 * @param {object} change The changed keys.
		 */
		setLine(index, change) {
			Object.assign(this.lines[index], change)
			this.emitContent()
		},

		/** @param {number} index The series. */
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
