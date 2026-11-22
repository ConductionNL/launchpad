/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * AssignedCasesWidget and its form (assigned-cases-widget REQ-CASES-001..004).
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	translate: (_app, text) => text,
	translatePlural: (_app, one, many, count) => (count === 1 ? one : many).replace('%n', String(count)),
}))
vi.mock('@nextcloud/auth', async (importOriginal) => ({ ...(await importOriginal()), getCurrentUser: () => ({ uid: 'pieter' }) }))
vi.mock('@nextcloud/router', async (importOriginal) => ({
	...(await importOriginal()),
	generateUrl: (path, params = {}) => `/index.php${path.replace(/\{(\w+)\}/g, (_m, key) => params[key])}`,
}))

const loadAssignedCases = vi.fn()
vi.mock('../../../../services/assignedCases.js', () => ({ loadAssignedCases: (...args) => loadAssignedCases(...args) }))

import AssignedCasesWidget from '../AssignedCasesWidget.vue'
import AssignedCasesWidgetForm from '../AssignedCasesWidgetForm.vue'

const CnStatusList = { name: 'CnStatusList', props: ['items', 'counts', 'loading', 'error', 'emptyLabel'], template: '<div class="list" />' }

const CASES = Array.from({ length: 6 }, (_, i) => ({
	id: `c${i}`,
	title: `Zaak ${i}`,
	identifier: `Z-2026-04${i}`,
	deadline: '2026-10-05',
	status: { key: 's1', label: 'In behandeling', variant: 'purple' },
}))

/**
 * Mount the widget.
 *
 * @param {object} content The widget content.
 * @param {object} integrationApps The apps enabled for the viewer.
 * @return {Promise<object>} The wrapper.
 */
async function mountWidget(content = {}, integrationApps = { dossiq: 'dossiq' }) {
	const wrapper = mount(AssignedCasesWidget, {
		props: { content, placement: {} },
		global: { stubs: { CnStatusList }, provide: { integrationApps } },
	})
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	loadAssignedCases.mockReset().mockResolvedValue({
		state: 'ok',
		cases: CASES,
		counts: [{ key: 's1', label: 'In behandeling', variant: 'purple', count: 6 }],
		total: 6,
	})
})

describe('AssignedCasesWidget', () => {
	it('reads the viewer\'s cases from the dossiq register and lists the first four with deadline and status', async () => {
		const wrapper = await mountWidget()
		expect(loadAssignedCases).toHaveBeenCalledWith({ register: 'dossiq', schema: 'case', userId: 'pieter' })
		const list = wrapper.getComponent(CnStatusList)
		expect(list.props('items')).toHaveLength(4)
		expect(list.props('items')[0]).toEqual({
			id: 'c0',
			title: 'Zaak 0',
			href: '/index.php/apps/dossiq/cases/c0',
			meta: 'Z-2026-040',
			due: '2026-10-05',
			status: { label: 'In behandeling', variant: 'purple' },
		})
		expect(list.props('counts')).toEqual([{ key: 's1', label: 'In behandeling', variant: 'purple', count: 6 }])
		expect(wrapper.get('[data-testid="assigned-cases-all"]').text()).toBe('All 6 open cases')
		expect(wrapper.get('[data-testid="assigned-cases-all"]').attributes('href')).toBe('/index.php/apps/dossiq/cases')
	})

	it('links into the case app under the id it is installed as', async () => {
		const wrapper = await mountWidget({ limit: 1 }, { dossiq: 'procest' })
		expect(wrapper.getComponent(CnStatusList).props('items')[0].href).toBe('/index.php/apps/procest/cases/c0')
	})

	it('reads nothing without dossiq', async () => {
		const wrapper = await mountWidget({}, { dossiq: false })
		expect(loadAssignedCases).not.toHaveBeenCalled()
		expect(wrapper.find('[data-testid="assigned-cases-no-app"]').exists()).toBe(true)
	})

	it('shows the error without counts when reading fails', async () => {
		loadAssignedCases.mockResolvedValue({ state: 'error', cases: [], counts: [], total: 0 })
		const list = (await mountWidget()).getComponent(CnStatusList)
		expect(list.props('error')).toBe('The cases could not be loaded. Try again later.')
		expect(list.props('counts')).toEqual([])
	})
})

describe('AssignedCasesWidgetForm', () => {
	it('defaults to dossiq\'s register and needs a register, a schema and 1 to 20 cases', () => {
		const wrapper = mount(AssignedCasesWidgetForm, { global: { stubs: { NcTextField: true } } })
		expect(wrapper.vm.validate()).toEqual([])
		wrapper.vm.update({ register: ' ', limit: 0 })
		expect(wrapper.vm.validate()).toEqual(['Name the register and the schema', 'Show 1 to 20 cases'])
		expect(wrapper.emitted('update:content').at(-1)[0]).toEqual({ register: '', schema: 'case', limit: 0 })
	})
})
