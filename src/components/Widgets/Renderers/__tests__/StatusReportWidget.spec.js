/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * StatusReportWidget: prefilled by the product, edited by a person, kept
 * with the record of which is which (dashboards-and-who-may-see-them REQ-DWMS-004).
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const interpolate = (text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`)
vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, text, vars = {}) => interpolate(text, vars),
}))
vi.mock('@nextcloud/vue', () => ({
	NcButton: { name: 'NcButton', props: ['disabled', 'type', 'variant'], emits: ['click'], template: '<button :type="type || \'button\'" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>' },
	NcSelect: { name: 'NcSelect', props: ['modelValue', 'options', 'inputLabel', 'clearable', 'reduce', 'label'], emits: ['update:modelValue'], template: '<div class="select-stub" :data-value="modelValue" />' },
	NcTextArea: { name: 'NcTextArea', props: ['modelValue', 'label', 'maxlength', 'resize'], emits: ['update:modelValue'], template: '<textarea :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
}))
vi.mock('../../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const draftStatusReport = vi.fn()
const keepStatusReport = vi.fn()
vi.mock('../../../../services/statusReport.js', async () => {
	const actual = await vi.importActual('../../../../services/statusReport.js')
	return {
		subjectOf: actual.subjectOf,
		draftStatusReport: (...args) => draftStatusReport(...args),
		keepStatusReport: (...args) => keepStatusReport(...args),
	}
})

import StatusReportWidget from '../StatusReportWidget.vue'

const CONTENT = { register: 'dossiq', schema: 'case', title: 'Programme X', deadline: '2026-12-31', closedValues: ['done'] }
const SUBJECT = { register: 'dossiq', schema: 'case', title: 'Programme X', deadline: '2026-12-31', closedValues: ['done'] }

function draft() {
	return {
		date: '2026-11-16',
		subject: SUBJECT,
		health: 'at-risk',
		summary: '4 of 10 items are closed; 2 are overdue.',
		figures: { total: 10, open: 6, closed: 4, overdue: 2, percentClosed: 40, daysToDeadline: 45 },
		provenance: { date: 'computed', health: 'computed', summary: 'computed', figures: {} },
	}
}

beforeEach(() => {
	draftStatusReport.mockReset()
	keepStatusReport.mockReset()
})

async function prepared() {
	draftStatusReport.mockResolvedValue({ refused: false, report: draft() })
	const wrapper = mount(StatusReportWidget, { props: { content: CONTENT } })
	await wrapper.find('[data-testid="status-report-prepare"]').trigger('click')
	await flushPromises()
	return wrapper
}

describe('StatusReportWidget', () => {
	it('asks for its settings when no register and schema are set', () => {
		const wrapper = mount(StatusReportWidget, { props: { content: { title: 'x' } } })

		expect(wrapper.find('[data-testid="status-report-unconfigured"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="status-report-prepare"]').exists()).toBe(false)
	})

	it('names the body of work and its deadline', () => {
		const wrapper = mount(StatusReportWidget, { props: { content: CONTENT } })

		expect(wrapper.find('[data-testid="status-report-subject"]').text()).toBe('Status of Programme X, deadline 2026-12-31')
	})

	it('prefills the figures, the rating and the summary from the draft', async () => {
		const wrapper = await prepared()

		expect(draftStatusReport).toHaveBeenCalledWith(SUBJECT)
		expect(wrapper.find('[data-testid="status-report-figure-total"]').text()).toBe('10')
		expect(wrapper.find('[data-testid="status-report-figure-percentClosed"]').text()).toBe('40%')
		expect(wrapper.find('[data-testid="status-report-figure-daysToDeadline"]').text()).toBe('45')
		expect(wrapper.find('.select-stub').attributes('data-value')).toBe('at-risk')
		expect(wrapper.find('textarea').element.value).toBe('4 of 10 items are closed; 2 are overdue.')
	})

	it('keeps the report with the summary the person wrote and shows it as written', async () => {
		const wrapper = await prepared()
		keepStatusReport.mockResolvedValue({
			refused: false,
			report: { ...draft(), summary: 'Behind, recovering.', provenance: { date: 'computed', health: 'computed', summary: 'written', figures: {} } },
		})

		await wrapper.find('textarea').setValue('Behind, recovering.')
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(keepStatusReport).toHaveBeenCalledWith(SUBJECT, 'at-risk', 'Behind, recovering.')
		expect(wrapper.find('[data-testid="status-report-kept"]').text()).toContain('Behind, recovering.')
		expect(wrapper.find('[data-testid="status-report-provenance-summary"]').text()).toBe('Summary: written by a person')
		expect(wrapper.find('[data-testid="status-report-provenance-health"]').text()).toBe('Health rating: computed by LaunchPad')
	})

	it('sends the rating the person picked', async () => {
		const wrapper = await prepared()
		keepStatusReport.mockResolvedValue({ refused: false, report: { ...draft(), health: 'off-track' } })

		await wrapper.findComponent({ name: 'NcSelect' }).vm.$emit('update:modelValue', 'off-track')
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(keepStatusReport).toHaveBeenCalledWith(SUBJECT, 'off-track', '4 of 10 items are closed; 2 are overdue.')
		expect(wrapper.find('[data-testid="status-report-kept"]').text()).toContain('Off track')
	})

	it('does not keep an empty summary', async () => {
		const wrapper = await prepared()

		await wrapper.find('textarea').setValue('   ')
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(keepStatusReport).not.toHaveBeenCalled()
		expect(wrapper.find('[data-testid="status-report-keep"]').attributes('disabled')).toBeDefined()
	})

	it('says OpenRegister is needed when keeping is refused with 503', async () => {
		const wrapper = await prepared()
		keepStatusReport.mockResolvedValue({ refused: true, status: 503 })

		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(wrapper.find('[data-testid="status-report-refused"]').text()).toBe('Status reports need OpenRegister, which is not available.')
		expect(wrapper.find('[data-testid="status-report-kept"]').exists()).toBe(false)
	})

	it('says the settings are wrong when the draft is refused with 400', async () => {
		draftStatusReport.mockResolvedValue({ refused: true, status: 400 })
		const wrapper = mount(StatusReportWidget, { props: { content: CONTENT } })
		await wrapper.find('[data-testid="status-report-prepare"]').trigger('click')
		await flushPromises()

		expect(wrapper.find('[data-testid="status-report-refused"]').text()).toContain('check the register, the schema and the deadline')
		expect(wrapper.find('[data-testid="status-report-draft"]').exists()).toBe(false)
	})

	it('starts over after a report was kept', async () => {
		const wrapper = await prepared()
		keepStatusReport.mockResolvedValue({ refused: false, report: draft() })
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		await wrapper.find('[data-testid="status-report-new"]').trigger('click')

		expect(wrapper.find('[data-testid="status-report-kept"]').exists()).toBe(false)
		expect(wrapper.find('[data-testid="status-report-prepare"]').text()).toBe('Prepare report')
	})
})
