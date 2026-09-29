/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The reactions bar (REQ-RXN-010): counts, own reaction marked, toggle,
 * only the allowed emoji, absent when reactions are off. The summary is
 * ReactionService::buildSummary's `{counts, mine, enabled, allowed}`.
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import DashboardReactions from '../DashboardReactions.vue'

vi.mock('@nextcloud/axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))
vi.mock('@nextcloud/router', () => ({ generateUrl: (path) => `/index.php${path}` }))
vi.mock('@nextcloud/dialogs', () => ({ showError: vi.fn(), showSuccess: vi.fn() }))
vi.mock('@nextcloud/l10n', () => ({
	t: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
	translate: (_app, text) => text,
	translatePlural: (_app, one) => one,
	n: (_app, one) => one,
}))

const allowed = ['👍', '❤️', '🎉', '😂', '🤔', '😢']
function summary (counts, mine, enabled = true) {
  return {
	data: enabled
		? { counts, mine, enabled, allowed }
		: { counts: {}, mine: [], enabled: false },
}
}
const url = '/index.php/apps/launchpad/api/dashboards/team/reactions'

beforeEach(() => {
	setActivePinia(createPinia())
	vi.clearAllMocks()
})

describe('DashboardReactions', () => {
	it('REQ-RXN-010: add then take back a thumbs up', async () => {
		axios.get.mockResolvedValueOnce(summary({ '👍': 2 }, []))
		axios.post.mockResolvedValue(summary({ '👍': 3 }, ['👍']))
		axios.delete.mockResolvedValue({ data: {} })
		axios.get.mockResolvedValueOnce(summary({ '👍': 2 }, []))
		const wrapper = mount(DashboardReactions, {
			props: { dashboardUuid: 'team' },
		})
		await flushPromises()
		const chip = () =>
			wrapper
				.findAll('.dashboard-reactions__chip')
				.find((b) => b.attributes('data-testid') === 'reaction-👍')
		expect(chip().text()).toContain('2')
		expect(chip().attributes('aria-pressed')).toBe('false')
		await chip().trigger('click')
		await flushPromises()
		expect(axios.post).toHaveBeenCalledWith(url, { emoji: '👍' })
		expect(chip().text()).toContain('3')
		expect(chip().attributes('aria-pressed')).toBe('true')
		await chip().trigger('click')
		await flushPromises()
		expect(axios.delete).toHaveBeenCalled()
		expect(chip().text()).toContain('2')
	})

	it('REQ-RXN-010: offers only the allowed emoji', async () => {
		axios.get.mockResolvedValue(summary({ '👍': 1 }, []))
		const wrapper = mount(DashboardReactions, {
			props: { dashboardUuid: 'team' },
		})
		await flushPromises()
		const offered = wrapper
			.findAll('[data-testid^="reaction-add-"]')
			.map((b) => b.attributes('data-testid').replace('reaction-add-', ''))
		expect(offered).toEqual(allowed.filter((e) => e !== '👍'))
	})

	it('REQ-RXN-010: reactions off shows no bar', async () => {
		axios.get.mockResolvedValue(summary({}, [], false))
		const wrapper = mount(DashboardReactions, {
			props: { dashboardUuid: 'board' },
		})
		await flushPromises()
		expect(wrapper.find('[data-testid="dashboard-reactions"]').exists()).toBe(
			false,
		)
	})

	it('names who reacted on hover', async () => {
		axios.get.mockResolvedValueOnce(summary({ '👍': 2 }, []))
		axios.get.mockResolvedValueOnce({
			data: {
				items: [
					{ userId: 'sanne', displayName: 'Sanne', reactedAt: '' },
					{ userId: 'pieter', displayName: 'Pieter', reactedAt: '' },
				],
				nextCursor: null,
				total: 2,
			},
		})
		const wrapper = mount(DashboardReactions, {
			props: { dashboardUuid: 'team' },
		})
		await flushPromises()
		await wrapper.findAll('.dashboard-reactions__chip')[0].trigger('mouseenter')
		await flushPromises()
		expect(
			wrapper.findAll('.dashboard-reactions__chip')[0].attributes('title'),
		).toBe('Sanne, Pieter')
	})
})
