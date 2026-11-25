/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * PeopleHost: a New colleagues placement lists the colleagues of its window
 * through its own data source; without a start date field it says so; every
 * other placement renders CnPeopleWidget as is (REQ-PPL-014).
 *
 * @spec openspec/changes/people-new-colleagues/specs/people-widget/spec.md#requirement-the-people-widget-can-list-new-colleagues-req-ppl-014
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import PeopleHost from '../PeopleHost.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))
vi.mock('@nextcloud/l10n', () => ({ t: (_app, text) => text, getLanguage: () => 'nl' }))
vi.mock('@conduction/nextcloud-vue', () => ({
	CnPeopleWidget: {
		name: 'CnPeopleWidget',
		props: ['content', 'dataSource'],
		template: '<div class="cn-people" />',
	},
}))

beforeEach(() => {
	axios.get.mockReset()
	vi.useRealTimers()
})

describe('PeopleHost', () => {
	it('renders a directory placement as is, without its own source', () => {
		const content = { selectionMode: 'filter', filters: [] }
		const widget = mount(PeopleHost, { props: { content } }).findComponent({ name: 'CnPeopleWidget' })

		expect(widget.props('content')).toEqual(content)
		expect(widget.props('dataSource')).toBeUndefined()
	})

	it('lists the new colleagues of the placement\'s window, Yasmina first with her start day', async () => {
		vi.useFakeTimers({ toFake: ['Date'] })
		vi.setSystemTime(new Date('2026-10-05T09:00:00Z'))
		axios.get.mockResolvedValue({ data: { configured: true, users: [{ id: 'yasmina', displayName: 'Yasmina El Amrani', startDate: '2026-10-12', starts: true, role: 'Adviseur' }, { id: 'lotte', displayName: 'Lotte Verhoeven', startDate: '2026-10-01', starts: false }] } })
		const wrapper = mount(PeopleHost, { props: { content: { selectionMode: 'new', newSinceDays: 20 } } })
		const widget = wrapper.findComponent({ name: 'CnPeopleWidget' })

		const page = await widget.props('dataSource').fetchPeople({ offset: 0, limit: 50 })

		expect(axios.get).toHaveBeenCalledWith('/index.php/apps/launchpad/api/people/new', { params: { since: 20, until: 14 } })
		expect(page.users.map((user) => user.uid)).toEqual(['yasmina', 'lotte'])
		expect(page.users[0].customFields[0].values).toEqual(['maandag'])
		expect(widget.props('content').filters).toEqual([{ fieldName: 'startDateWindow', operator: 'between', values: [20, 14] }])
		expect(await widget.props('dataSource').fetchPeople({ offset: 50 })).toEqual({ users: [], total: 0, hasMore: false })
	})

	it('says the administrator has not set a start date field', async () => {
		axios.get.mockResolvedValue({ data: { configured: false, users: [] } })
		const wrapper = mount(PeopleHost, { props: { content: { selectionMode: 'new' } } })
		await wrapper.findComponent({ name: 'CnPeopleWidget' }).props('dataSource').fetchPeople({ offset: 0 })
		await flushPromises()

		expect(wrapper.findComponent({ name: 'CnPeopleWidget' }).exists()).toBe(false)
		expect(wrapper.text()).toContain('The administrator has not set a start date field')
	})
})
