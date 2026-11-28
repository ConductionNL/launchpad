/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * WorkPackagesWidget and its form: the open work packages with status and due
 * date, and what the widget says without the integration or a connected
 * account (work-packages-widget REQ-WP-001..003).
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({ ...(await importOriginal()), translate: (_app, text) => text }))

const loadWorkPackages = vi.fn()
vi.mock('../../../../services/workPackages.js', () => ({ loadWorkPackages: (...args) => loadWorkPackages(...args) }))

import WorkPackagesWidget from '../WorkPackagesWidget.vue'
import WorkPackagesWidgetForm from '../WorkPackagesWidgetForm.vue'

const CnStatusList = { name: 'CnStatusList', props: ['items', 'loading', 'error', 'emptyLabel'], template: '<div class="list" />' }

const WELSTAND = { id: 12, subject: 'Welstandsadvies verwerken', status: 'In uitvoering', project: 'Havenpark', assignee: 'Pieter Jansen', dueDate: '2026-10-05', url: 'https://op/work_packages/12' }

/**
 * Mount the widget.
 *
 * @param {object} content The widget content.
 * @return {Promise<object>} The wrapper.
 */
async function mountWidget(content = {}) {
	const wrapper = mount(WorkPackagesWidget, { props: { content, placement: {} }, global: { stubs: { CnStatusList } } })
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	loadWorkPackages.mockReset().mockResolvedValue({ state: 'ok', workPackages: [WELSTAND], connectUrl: '' })
})

describe('WorkPackagesWidget', () => {
	it('lists the person\'s own work packages as links with project, due date and status', async () => {
		const wrapper = await mountWidget()
		expect(loadWorkPackages).toHaveBeenCalledWith({ scope: 'mine', project: '', limit: 4 })
		const list = wrapper.getComponent(CnStatusList)
		expect(list.props('items')).toEqual([{
			id: '12',
			title: 'Welstandsadvies verwerken',
			href: 'https://op/work_packages/12',
			external: true,
			meta: 'Havenpark',
			due: '2026-10-05',
			status: { label: 'In uitvoering', variant: 'info' },
		}])
		expect(list.props('emptyLabel')).toBe('You have no open work packages')
	})

	it('names the assignee on a project list', async () => {
		const wrapper = await mountWidget({ scope: 'project', project: 'havenpark', limit: 6 })
		expect(loadWorkPackages).toHaveBeenCalledWith({ scope: 'project', project: 'havenpark', limit: 6 })
		expect(wrapper.getComponent(CnStatusList).props('items')[0].meta).toBe('Havenpark · Pieter Jansen')
	})

	it('asks the person to connect OpenProject when their account is not connected', async () => {
		loadWorkPackages.mockResolvedValue({ state: 'not-connected', workPackages: [], connectUrl: '/index.php/settings/user/openproject' })
		const wrapper = await mountWidget()
		const notice = wrapper.get('[data-testid="work-packages-not-connected"]')
		expect(notice.text()).toContain('Connect your OpenProject account')
		expect(notice.get('a').attributes('href')).toBe('/index.php/settings/user/openproject')
	})

	it('says the integration is needed without it, and shows an error when reading fails', async () => {
		loadWorkPackages.mockResolvedValue({ state: 'not-installed', workPackages: [], connectUrl: '' })
		expect((await mountWidget()).find('[data-testid="work-packages-not-installed"]').exists()).toBe(true)
		loadWorkPackages.mockResolvedValue({ state: 'error', workPackages: [], connectUrl: '' })
		expect((await mountWidget()).getComponent(CnStatusList).props('error')).toBe('The work packages could not be loaded. Try again later.')
	})
})

describe('WorkPackagesWidgetForm', () => {
	it('needs a project for a project list and 1 to 25 work packages', () => {
		const wrapper = mount(WorkPackagesWidgetForm, { global: { stubs: { NcCheckboxRadioSwitch: true, NcTextField: true } } })
		expect(wrapper.vm.validate()).toEqual([])
		wrapper.vm.update({ scope: 'project' })
		expect(wrapper.vm.validate()).toEqual(['Name the project'])
		wrapper.vm.update({ project: ' havenpark ', limit: 30 })
		expect(wrapper.vm.validate()).toEqual(['Show 1 to 25 work packages'])
		expect(wrapper.emitted('update:content').at(-1)[0]).toEqual({ scope: 'project', project: 'havenpark', limit: 30 })
	})
})
