/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * DashboardPublicShareView: the error state puts the alert icon in the
 * empty-content icon slot, next to the not-found title and description.
 * Only HTTP, the share store and the layout components are doubled; the
 * icon itself is the real one.
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { h } from 'vue'
import DashboardPublicShareView from '../DashboardPublicShareView.vue'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path) => '/index.php' + path,
}))
vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	translate: (_app, text) => text,
}))

const shareStore = {
	isUnlocked: vi.fn(),
	clearUnlocked: vi.fn(),
	markUnlocked: vi.fn(),
}
vi.mock('../../stores/publicShares.js', () => ({
	usePublicShareStore: () => shareStore,
}))

beforeEach(() => {
	vi.clearAllMocks()
	shareStore.isUnlocked.mockReturnValue(false)
})

describe('DashboardPublicShareView error icon', () => {
	it('renders the alert icon in the empty-content icon slot for a missing share', async () => {
		axios.get.mockRejectedValue(Object.assign(new Error('HTTP 404'), { response: { status: 404, data: {} } }))
		const wrapper = mount(DashboardPublicShareView, {
			props: { token: 'tok' },
			global: {
				stubs: {
					PublicSharePasswordDialog: true,
					PublicDashboardGrid: true,
					NcLoadingIcon: true,
					NcEmptyContent: {
						name: 'NcEmptyContent',
						props: ['name', 'description'],
						render() {
							return h('div', { class: 'empty' }, [
								h('span', { class: 'empty__icon' }, this.$slots.icon?.()),
								h('span', { class: 'empty__name' }, this.name),
								h('span', { class: 'empty__description' }, this.description),
							])
						},
					},
				},
			},
		})
		await flushPromises()

		expect(axios.get).toHaveBeenCalledWith('/index.php/apps/launchpad/s/tok/data', { headers: {} })
		expect(wrapper.find('.public-share-view__error').exists()).toBe(true)
		expect(wrapper.find('.empty__icon .alert-circle-icon svg').exists()).toBe(true)
		expect(wrapper.find('.empty__name').text()).toBe('Not found')
		expect(wrapper.find('.empty__description').text()).toBe('This shared dashboard does not exist or has expired.')
	})
})
