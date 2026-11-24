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
import DashboardReactions, { lastChangeText } from '../DashboardReactions.vue'

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
	getCanonicalLocale: () => 'nl-NL',
}))

const allowed = ['👍', '❤️', '🎉', '😂', '🤔', '😢']
function summary(counts, mine, enabled = true) {
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

describe('DashboardReactions at the foot (reactions-bar-at-the-foot)', () => {
	/**
	 * @spec openspec/changes/archive/2026-11-24-reactions-bar-at-the-foot/specs/dashboard-reactions/spec.md#requirement-req-rxn-010-a-viewer-can-react-from-the-dashboard
	 */
	it('asks the question before the reactions and names the last change after them', async () => {
		const at = new Date()
		at.setHours(8, 30, 0, 0)
		axios.get.mockResolvedValueOnce({
			data: { counts: { '👍': 2 }, mine: [], enabled: true, allowed, lastChange: { name: 'Communicatie', at: at.toISOString() } },
		})
		const wrapper = mount(DashboardReactions, { props: { dashboardUuid: 'team' } })
		await flushPromises()

		const bar = wrapper.find('[data-testid="dashboard-reactions"]')
		const parts = bar.element.children
		expect(parts[0].textContent.trim()).toBe('What do you think of this dashboard?')
		expect(parts[1].querySelector('[data-testid="reaction-👍"]')).toBeTruthy()
		expect(parts[2].getAttribute('data-testid')).toBe('dashboard-reactions-last-change')
		expect(parts[2].textContent.trim()).toMatch(/^Last updated by Communicatie, today 08[:.]30$/)
	})

	it('leaves the last-change line out when the change is unknown', async () => {
		axios.get.mockResolvedValueOnce({ data: { counts: {}, mine: [], enabled: true, allowed, lastChange: null } })
		const wrapper = mount(DashboardReactions, { props: { dashboardUuid: 'team' } })
		await flushPromises()

		expect(wrapper.find('[data-testid="dashboard-reactions-question"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="dashboard-reactions-last-change"]').exists()).toBe(false)
	})
})

describe('lastChangeText', () => {
	const now = new Date(2026, 10, 24, 12, 0, 0)

	it('names who changed it today and at what time', () => {
		expect(lastChangeText({ name: 'Communicatie', at: new Date(2026, 10, 24, 8, 30).toISOString() }, now, 'nl-NL'))
			.toMatch(/^Last updated by Communicatie, today 08[:.]30$/)
	})

	it('gives the weekday within the last week, the date before that', () => {
		const weekday = new Date(2026, 10, 22, 9, 0).toLocaleDateString('nl-NL', { weekday: 'long' })
		expect(lastChangeText({ name: 'Pieter', at: new Date(2026, 10, 22, 9, 0).toISOString() }, now, 'nl-NL'))
			.toBe(`Last updated by Pieter, ${weekday}`)
		const date = new Date(2026, 9, 1, 9, 0).toLocaleDateString('nl-NL', { day: 'numeric', month: 'long' })
		expect(lastChangeText({ name: 'Pieter', at: new Date(2026, 9, 1, 9, 0).toISOString() }, now, 'nl-NL'))
			.toBe(`Last updated by Pieter, ${date}`)
	})

	it('leaves the name out when it is unknown, and the line without a usable time', () => {
		expect(lastChangeText({ name: ' ', at: new Date(2026, 10, 24, 8, 30).toISOString() }, now, 'nl-NL'))
			.toMatch(/^Last updated today 08[:.]30$/)
		expect(lastChangeText({ name: 'Pieter', at: null }, now, 'nl-NL')).toBe('')
		expect(lastChangeText(null, now, 'nl-NL')).toBe('')
	})
})
