/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The optional "Date field for the period" of a widget with a register
 * source (dashboard-period-and-download REQ-DPD-002): the date fields read
 * from a sample of the source's records, the select, and the chart form that
 * stores the choice as the source's periodField.
 *
 * @spec openspec/changes/dashboard-period-and-download/specs/dashboards/spec.md#requirement-a-dashboard-can-have-a-period-that-its-figures-follow-req-dpd-002
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({ ...(await importOriginal()), translate: (_app, text) => text }))
vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', async (importOriginal) => ({ ...(await importOriginal()), generateUrl: (path, params) => `/index.php${path.replace('{register}', params.register).replace('{schema}', params.schema)}` }))

import axios from '@nextcloud/axios'
import ChartSourceForm from '../ChartSourceForm.vue'
import PeriodFieldSelect from '../PeriodFieldSelect.vue'
import { getDefaultContent } from '../../../../constants/widgetRegistry.js'
import { dateFieldsOf, fetchDateFields } from '../../../../utils/periodDateFields.js'

const records = [
	{ '@self': { created: '2026-01-01' }, title: 'Bouwvergunning', startDate: '2026-10-02', closedAt: null, decidedOn: '2026-11-01T09:30:00+01:00', count: 3 },
	{ title: 'Kapvergunning', startDate: '2026-11-15', closedAt: '2026-11-20T12:00:00Z', decidedOn: 'later', count: 1 },
]

const NcSelect = {
	name: 'NcSelect',
	props: ['modelValue', 'options', 'inputLabel', 'placeholder', 'clearable', 'loading'],
	emits: ['update:modelValue'],
	template: '<div class="nc-select" />',
}
const CnChartWidgetForm = { name: 'CnChartWidgetForm', props: ['value'], emits: ['update:content'], template: '<div />' }

beforeEach(() => {
	axios.get.mockReset()
})

describe('dateFieldsOf', () => {
	it('keeps the fields whose every value is an ISO date, metadata and empty values left out', () => {
		expect(dateFieldsOf(records)).toEqual(['closedAt', 'startDate'])
	})

	it('reads nothing from no records', () => {
		expect(dateFieldsOf([])).toEqual([])
		expect(dateFieldsOf(null)).toEqual([])
		expect(dateFieldsOf([null, 'x'])).toEqual([])
	})
})

describe('fetchDateFields', () => {
	it('samples the source\'s records once per register and schema', async () => {
		axios.get.mockResolvedValue({ data: { results: records } })
		expect(await fetchDateFields('zaken', 'zaak')).toEqual(['closedAt', 'startDate'])
		expect(await fetchDateFields('zaken', 'zaak')).toEqual(['closedAt', 'startDate'])
		expect(axios.get).toHaveBeenCalledTimes(1)
		expect(axios.get).toHaveBeenCalledWith('/index.php/apps/openregister/api/objects/zaken/zaak', { params: { _limit: 5 } })
	})

	it('answers no fields without a register or schema, and when the records cannot be read', async () => {
		expect(await fetchDateFields('', 'zaak')).toEqual([])
		axios.get.mockRejectedValue(new Error('403'))
		expect(await fetchDateFields('geheim', 'zaak')).toEqual([])
		expect(axios.get).toHaveBeenCalledTimes(1)
	})
})

describe('PeriodFieldSelect', () => {
	it('offers the date fields, keeps a stored choice and emits an empty choice as none', async () => {
		axios.get.mockResolvedValue({ data: { results: records } })
		const wrapper = mount(PeriodFieldSelect, { props: { register: 'meldingen', schema: 'melding', modelValue: 'reportedOn' }, global: { stubs: { NcSelect } } })
		await flushPromises()
		const select = wrapper.getComponent(NcSelect)
		expect(select.props('options')).toEqual(['reportedOn', 'closedAt', 'startDate'])
		expect(select.props('inputLabel')).toBe('Date field for the period')

		select.vm.$emit('update:modelValue', 'startDate')
		select.vm.$emit('update:modelValue', null)
		expect(wrapper.emitted('update:modelValue')).toEqual([['startDate'], ['']])
	})
})

describe('ChartSourceForm period field', () => {
	it('stores the chosen date field on the register query and drops it again', async () => {
		const wrapper = mount(ChartSourceForm, { props: { value: getDefaultContent('graph') }, global: { stubs: { CnChartWidgetForm, PeriodFieldSelect: true, NcCheckboxRadioSwitch: true, NcSelect: true, NcTextField: true, NcButton: true, Delete: true } } })
		wrapper.getComponent(CnChartWidgetForm).vm.$emit('update:content', { chartKind: 'bar', dataSource: { register: 'zaken', schema: 'zaak' } })
		await wrapper.vm.$nextTick()
		const select = wrapper.findComponent({ name: 'PeriodFieldSelect' })
		expect(select.props('register')).toBe('zaken')
		expect(select.props('schema')).toBe('zaak')

		select.vm.$emit('update:modelValue', 'startDate')
		expect(wrapper.emitted('update:content').at(-1)[0].dataSource).toEqual({ register: 'zaken', schema: 'zaak', periodField: 'startDate' })

		select.vm.$emit('update:modelValue', '')
		expect(wrapper.emitted('update:content').at(-1)[0].dataSource).toEqual({ register: 'zaken', schema: 'zaak' })
	})

	it('reopens with the stored date field', () => {
		const content = { ...getDefaultContent('graph'), dataSource: { register: 'zaken', schema: 'zaak', periodField: 'startDate' } }
		const wrapper = mount(ChartSourceForm, { props: { editingWidget: { content } }, global: { stubs: { CnChartWidgetForm, PeriodFieldSelect: true, NcCheckboxRadioSwitch: true, NcSelect: true, NcTextField: true, NcButton: true, Delete: true } } })
		expect(wrapper.findComponent({ name: 'PeriodFieldSelect' }).props('modelValue')).toBe('startDate')
	})
})
