/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * ColleagueActivityWidget: the server's filtered list and totals, shown as
 * they come (openspec/changes/dashboards-and-who-may-see-them REQ-DWMS-007).
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const interpolate = (text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`)
vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, text, vars = {}) => interpolate(text, vars),
	translatePlural: (_app, one, many, count, vars = {}) => interpolate(count === 1 ? one : many, vars),
}))
vi.mock('../../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const loadColleagueActivity = vi.fn()
vi.mock('../../../../services/colleagueActivity.js', () => ({
	loadColleagueActivity: (...args) => loadColleagueActivity(...args),
}))

import ColleagueActivityWidget from '../ColleagueActivityWidget.vue'

function entry(id, actorDisplayName, type, title = '') {
	return { id, actor: actorDisplayName.toLowerCase(), actorDisplayName, app: 'launchpad', type, subject: type, title, link: `/x/${id}`, timestamp: 1_800_000_000 }
}

async function mountWith(result, content = {}) {
	loadColleagueActivity.mockResolvedValue(result)
	const wrapper = mount(ColleagueActivityWidget, { props: { content } })
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	loadColleagueActivity.mockReset()
})

describe('ColleagueActivityWidget', () => {
	it('lists the entries and shows the server total, not a count of its own', async () => {
		const wrapper = await mountWith({
			available: true,
			days: 7,
			entries: [entry(1, 'Bob', 'dashboard_updated', 'Team'), entry(2, 'Carol', 'file_changed')],
			total: 5,
			counts: {},
			actors: 2,
		})

		expect(wrapper.findAll('li')).toHaveLength(2)
		expect(wrapper.find('[data-testid="colleague-activity-entry-1"]').text()).toContain('Team (dashboard updated)')
		expect(wrapper.find('[data-testid="colleague-activity-total"]').text()).toBe('5 actions by colleagues in the last 7 days')
	})

	it('asks for the configured, bounded limit and period', async () => {
		await mountWith({ available: true, days: 90, entries: [], total: 0, counts: {}, actors: 0 }, { limit: 500, days: 400 })

		expect(loadColleagueActivity).toHaveBeenCalledWith({ limit: 50, days: 90 })
	})

	it('falls back to the defaults for settings that are not numbers', async () => {
		await mountWith({ available: true, days: 7, entries: [], total: 0, counts: {}, actors: 0 }, { limit: 'x', days: -1 })

		expect(loadColleagueActivity).toHaveBeenCalledWith({ limit: 10, days: 7 })
	})

	it('says so when nothing is visible', async () => {
		const wrapper = await mountWith({ available: true, days: 7, entries: [], total: 0, counts: {}, actors: 0 })

		expect(wrapper.find('[data-testid="colleague-activity-empty"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="colleague-activity-total"]').text()).toBe('0 actions by colleagues in the last 7 days')
	})

	it('says the Activity app is needed when the server cannot read it', async () => {
		const wrapper = await mountWith({ available: false, days: 7, entries: [], total: 0, counts: {}, actors: 0 })

		expect(wrapper.find('[data-testid="colleague-activity-unavailable"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="colleague-activity-list"]').exists()).toBe(false)
	})

	it('shows an error when the load fails', async () => {
		loadColleagueActivity.mockRejectedValue(new Error('500'))
		const wrapper = mount(ColleagueActivityWidget, { props: { content: {} } })
		await flushPromises()

		expect(wrapper.find('[data-testid="colleague-activity-error"]').exists()).toBe(true)
	})

	it('filters the rows in view state and leaves the content alone', async () => {
		const content = { limit: 10, days: 7 }
		const wrapper = await mountWith({
			available: true,
			days: 7,
			entries: [entry(1, 'Bob', 'dashboard_updated', 'Team'), entry(2, 'Carol', 'file_changed')],
			total: 2,
			counts: {},
			actors: 2,
		}, content)

		await wrapper.find('input').setValue('carol')

		expect(wrapper.findAll('li')).toHaveLength(1)
		expect(wrapper.find('[data-testid="colleague-activity-entry-2"]').exists()).toBe(true)
		expect(content).toEqual({ limit: 10, days: 7 })
		expect(wrapper.find('[data-testid="colleague-activity-total"]').text()).toBe('2 actions by colleagues in the last 7 days')
	})
})
