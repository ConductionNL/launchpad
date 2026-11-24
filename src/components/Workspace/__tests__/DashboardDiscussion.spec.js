/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The discussion of a dashboard renders on shared and group dashboards only,
 * and not at all without OpenRegister and Talk (openregister-leaf-integrations
 * tasks 4 to 6).
 *
 * @spec openspec/changes/openregister-leaf-integrations/tasks.md#task-4
 */

import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import DashboardDiscussion from '../DashboardDiscussion.vue'

const listShares = vi.hoisted(() => vi.fn())
vi.mock('../../../services/api.js', () => ({ api: { listShares } }))

const TabStub = {
	name: 'CnIntegrationTab',
	props: ['integrationId', 'register', 'schema', 'objectId'],
	template: '<div class="tab-stub" />',
}

/**
 * Mount the discussion.
 *
 * @param {object} props The props.
 * @return {import('@vue/test-utils').VueWrapper}
 */
function mountDiscussion(props) {
	return mount(DashboardDiscussion, { props, global: { stubs: { CnIntegrationTab: TabStub } } })
}

beforeEach(() => {
	window.OC = { ...(window.OC || {}), appswebroots: { openregister: '/apps/openregister', spreed: '/apps/spreed' } }
	listShares.mockReset()
})

afterEach(() => {
	delete window.OC.appswebroots
})

describe('DashboardDiscussion', () => {
	it('mounts the talk leaf on a group dashboard, keyed by the dashboard uuid', () => {
		const tab = mountDiscussion({ dashboard: { id: 1, uuid: 'g-1', type: 'group_shared' } }).findComponent(TabStub)
		expect(tab.props()).toEqual({ integrationId: 'talk', register: 'launchpad', schema: 'dashboard', objectId: 'g-1' })
	})

	it('mounts on my dashboard once its shares are read, and asks nothing for someone else\'s', async () => {
		listShares.mockResolvedValue({ data: [{ shareType: 'user', shareWith: 'bob' }] })
		const mine = mountDiscussion({ dashboard: { id: 7, uuid: 'u-7', type: 'user', userId: 'alice' }, currentUserId: 'alice' })
		expect(mine.find('[data-testid="dashboard-discussion"]').exists()).toBe(false)
		await flushPromises()
		expect(mine.find('[data-testid="dashboard-discussion"]').exists()).toBe(true)
		expect(listShares).toHaveBeenCalledWith(7)

		listShares.mockClear()
		mountDiscussion({ dashboard: { id: 8, uuid: 'u-8', type: 'user', userId: 'carol' }, currentUserId: 'alice', sharedWithMe: [{ id: 8 }] })
		expect(listShares).not.toHaveBeenCalled()
	})

	/** @spec openspec/changes/archive/2026-11-24-sharing-tab-embed-and-conversation/specs/dashboard-talk-leaf/spec.md#requirement-the-conversation-is-switched-on-from-the-sharing-tab-req-leaf-006 */
	it('renders nothing on a shared dashboard whose conversation is switched off', () => {
		const wrapper = mountDiscussion({ dashboard: { id: 1, uuid: 'g-1', type: 'group_shared', conversationEnabled: 0 } })
		expect(wrapper.find('[data-testid="dashboard-discussion"]').exists()).toBe(false)
	})

	it('renders nothing on my unshared dashboard', async () => {
		listShares.mockResolvedValue({ data: [] })
		const wrapper = mountDiscussion({ dashboard: { id: 7, uuid: 'u-7', type: 'user', userId: 'alice' }, currentUserId: 'alice' })
		await flushPromises()
		expect(wrapper.find('[data-testid="dashboard-discussion"]').exists()).toBe(false)
	})

	it('renders and requests nothing without Talk', async () => {
		window.OC.appswebroots = { openregister: '/apps/openregister' }
		const wrapper = mountDiscussion({ dashboard: { id: 7, uuid: 'u-7', type: 'user', userId: 'alice' }, currentUserId: 'alice' })
		await flushPromises()
		expect(listShares).not.toHaveBeenCalled()
		expect(mountDiscussion({ dashboard: { id: 1, uuid: 'g-1', type: 'group_shared' } }).find('[data-testid="dashboard-discussion"]').exists()).toBe(false)
		expect(wrapper.html()).toBe('<!--v-if-->')
	})
})
