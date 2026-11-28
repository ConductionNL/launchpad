/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The pie chart and graph presets (chart-presets REQ-CHARTP-001..003): the
 * donut's total in its centre, bars and a line in one graph, and a source
 * that is a register query or entered values.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({ ...(await importOriginal()), translate: (_app, text) => text }))

import { loadLazyComponent } from '@conduction/nextcloud-vue'
import ChartHost, { isMixedSeries, withDonutTotal } from '../ChartHost.vue'
import ChartSourceForm, { kindOf } from '../ChartSourceForm.vue'
import { getDefaultContent, getWidgetTypeEntry } from '../../../../constants/widgetRegistry.js'

const CnChartWidget = { name: 'CnChartWidget', props: ['type', 'dataSource', 'options', 'series', 'labels', 'categories', 'legendValues', 'legendPosition', 'colorMap', 'widgetId', 'selectable'], template: '<div />' }
const CnChartWidgetForm = {
	name: 'CnChartWidgetForm',
	props: ['value'],
	emits: ['update:content'],
	methods: { validate: () => ['Choose a register'] },
	template: '<div class="register-form" />',
}
const stubs = { CnChartWidget, CnChartWidgetForm, NcCheckboxRadioSwitch: true, NcSelect: true, NcTextField: true, NcButton: true, Delete: true }

/**
 * Mount the host.
 *
 * @param {object} content The content.
 * @return {object} The chart stub.
 */
function chartOf(content) {
	return mount(ChartHost, { props: { content, placement: { id: 1 } }, global: { stubs } }).getComponent(CnChartWidget)
}

describe('chart presets', () => {
	it('registers a pie chart and a graph on the chart host with the one source form', async () => {
		expect(await loadLazyComponent(getWidgetTypeEntry('pie-chart').renderer)).toBe(ChartHost)
		expect(await loadLazyComponent(getWidgetTypeEntry('graph').renderer)).toBe(ChartHost)
		expect(await loadLazyComponent(getWidgetTypeEntry('pie-chart').form)).toBe(ChartSourceForm)
		expect(await loadLazyComponent(getWidgetTypeEntry('graph').form)).toBe(ChartSourceForm)
		expect(kindOf({ chartKind: 'donut' })).toBe('pie')
		expect(kindOf({ chartKind: 'bar' })).toBe('graph')
		expect(getDefaultContent('pie-chart')).toMatchObject({ chartKind: 'donut', showTotal: true, legendValues: true })
		expect(getDefaultContent('graph')).toMatchObject({ chartKind: 'bar', dataSource: { bucket: { interval: 'month' } } })
	})

	it('writes the donut total in the centre and keeps the legend settings', () => {
		const chart = chartOf({ ...getDefaultContent('pie-chart'), source: 'manual', labels: ['In behandeling', 'Wacht op advies'], series: [138, 46] })
		expect(chart.props('type')).toBe('donut')
		expect(chart.props('dataSource')).toBeNull()
		expect(chart.props('labels')).toEqual(['In behandeling', 'Wacht op advies'])
		expect(chart.props('options').plotOptions.pie.donut.labels.total).toEqual({ show: true, label: 'Total' })
		expect(chart.props('legendValues')).toBe(true)
		expect(chart.props('legendPosition')).toBe('right')
	})

	it('draws bars and a line in one chart from entered series', () => {
		const content = { chartKind: 'bar', source: 'manual', categories: ['apr', 'mei'], series: [{ name: 'Aanvragen', type: 'column', data: [112, 98] }, { name: 'Besluiten', type: 'line', data: [95, 104] }] }
		expect(isMixedSeries(content)).toBe(true)
		expect(chartOf(content).props('type')).toBe('line')
		expect(isMixedSeries({ ...content, source: 'register' })).toBe(false)
	})

	it('keeps the author\'s own donut options', () => {
		expect(withDonutTotal({ plotOptions: { pie: { donut: { size: '60%', labels: { total: { label: 'Totaal' } } } } } }, 'Total').plotOptions.pie.donut)
			.toEqual({ size: '60%', labels: { show: true, total: { show: true, label: 'Totaal' } } })
	})
})

describe('ChartSourceForm', () => {
	it('emits the register query with the preset keys', () => {
		const wrapper = mount(ChartSourceForm, { props: { value: getDefaultContent('pie-chart') }, global: { stubs } })
		wrapper.getComponent(CnChartWidgetForm).vm.$emit('update:content', { chartKind: 'donut', dataSource: { register: 'dossiq', schema: 'case', groupBy: { field: 'status' } } })
		expect(wrapper.emitted('update:content').at(-1)[0]).toMatchObject({
			source: 'register',
			chartKind: 'donut',
			showTotal: true,
			dataSource: { register: 'dossiq', schema: 'case', groupBy: { field: 'status' } },
		})
		expect(wrapper.vm.validate()).toEqual(['Choose a register'])
	})

	it('builds a pie chart from entered slices with their colours', async () => {
		const wrapper = mount(ChartSourceForm, { props: { value: getDefaultContent('pie-chart') }, global: { stubs } })
		wrapper.vm.setSource('manual')
		expect(wrapper.vm.validate()).toEqual(['Enter at least one value with a label'])
		wrapper.vm.setSlice(0, { label: 'In behandeling', value: 138, colour: '#3669a5' })
		wrapper.vm.addSlice()
		wrapper.vm.setSlice(1, { label: 'Wacht op aanvrager', value: 74 })
		const content = wrapper.emitted('update:content').at(-1)[0]
		expect(content).toMatchObject({ source: 'manual', labels: ['In behandeling', 'Wacht op aanvrager'], series: [138, 74], colorMap: { 'In behandeling': '#3669a5' } })
		expect(content.dataSource).toBeUndefined()
		expect(wrapper.vm.validate()).toEqual([])
	})

	it('builds a graph from axis labels and named series', () => {
		const wrapper = mount(ChartSourceForm, { props: { value: getDefaultContent('graph') }, global: { stubs } })
		wrapper.vm.setSource('manual')
		expect(wrapper.vm.validate()).toHaveLength(1)
		wrapper.vm.setCategories('apr, mei, jun')
		wrapper.vm.setLine(0, { name: 'Aanvragen', valuesText: '112, 98, 130' })
		wrapper.vm.addLine()
		wrapper.vm.setLine(1, { name: 'Besluiten', type: 'line', valuesText: '95, x, 118' })
		expect(wrapper.emitted('update:content').at(-1)[0]).toMatchObject({
			source: 'manual',
			categories: ['apr', 'mei', 'jun'],
			series: [{ name: 'Aanvragen', type: 'column', data: [112, 98, 130] }, { name: 'Besluiten', type: 'line', data: [95, 0, 118] }],
		})
		expect(wrapper.vm.validate()).toEqual([])
	})

	it('reopens entered values from the placement', () => {
		const wrapper = mount(ChartSourceForm, {
			props: { editingWidget: { content: { chartKind: 'donut', source: 'manual', labels: ['A'], series: [3], colorMap: { A: 'red' } } } },
			global: { stubs },
		})
		expect(wrapper.vm.slices).toEqual([{ label: 'A', value: 3, colour: 'red' }])
	})
})
