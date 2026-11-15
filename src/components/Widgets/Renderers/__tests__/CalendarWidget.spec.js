/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `CalendarWidget.vue`: the initial view mode, the
 * date window each mode requests, the loading / error / empty states, how
 * events land in the agenda, month and week views, the failure summary and
 * the per-calendar colour accent.
 *
 * "Today" is fixed to Wednesday 11 March 2026 so the month and week grids
 * are known. Event times are local (no zone suffix), so the day buckets do
 * not depend on the machine's timezone.
 */

import axios from '@nextcloud/axios'
import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import CalendarWidget from '../CalendarWidget.vue'

vi.mock('@nextcloud/axios', () => ({ default: { get: vi.fn() } }))
vi.mock('@nextcloud/router', () => ({
	generateUrl: (path, params = {}) =>
		'/index.php' + path.replace(/\{(\w+)\}/g, (_m, k) => String(params[k])),
}))

const TODAY = new Date(2026, 2, 11, 10, 0, 0)
const SOURCES = { internalCalendars: ['personal'], externalIcsUrls: [] }

const EVENTS = [
	{ title: 'Standup', start: '2026-03-11T09:30:00', calendarName: 'Work', color: '#ff0000' },
	{ title: 'Holiday', start: '2026-03-11T00:00:00', allDay: true },
	{ title: 'Review', start: '2026-03-12T14:05:00', color: '' },
]

function mountWidget(content = {}, placementId = 7) {
	return mount(CalendarWidget, {
		props: { content: { ...SOURCES, ...content }, placementId },
	})
}

/**
 * The `from`/`to` query of the n-th request.
 *
 * @param {number} n zero-based call index.
 * @return {{from: string, to: string}} the window.
 */
function windowOf(n = 0) {
	return axios.get.mock.calls[n][1].params
}

beforeEach(() => {
	vi.useFakeTimers({ toFake: ['Date'] })
	vi.setSystemTime(TODAY)
	globalThis.t = (_app, key) => key
	axios.get.mockReset()
	axios.get.mockResolvedValue({ data: { events: EVENTS, failures: [] } })
})

afterEach(() => {
	vi.useRealTimers()
})

describe('CalendarWidget: loading', () => {
	it('fetches the agenda window of the placement on mount', async () => {
		const wrapper = mountWidget({ daysAhead: 3 })
		await wrapper.vm.$nextTick()
		expect(wrapper.find('.calendar-widget__loading').exists()).toBe(true)
		await flushPromises()

		expect(axios.get).toHaveBeenCalledWith(
			'/index.php/apps/launchpad/api/widgets/calendar/7/events',
			{
				params: {
					from: new Date(2026, 2, 11, 0, 0, 0, 0).toISOString(),
					to: new Date(2026, 2, 14, 23, 59, 59, 999).toISOString(),
				},
			},
		)
		expect(wrapper.find('.calendar-widget__loading').exists()).toBe(false)
	})

	it.each([[undefined], [0], [-2], ['abc']])('falls back to 14 days ahead for %s', async (daysAhead) => {
		mountWidget({ daysAhead })
		await flushPromises()
		expect(windowOf().to).toBe(new Date(2026, 2, 25, 23, 59, 59, 999).toISOString())
	})

	it('requests nothing without a placement or without sources', async () => {
		const noPlacement = mountWidget({}, null)
		const noSources = mount(CalendarWidget, {
			props: { content: { internalCalendars: [], externalIcsUrls: 'x' }, placementId: 7 },
		})
		await flushPromises()

		expect(axios.get).not.toHaveBeenCalled()
		expect(noSources.find('.calendar-widget__empty').text()).toBe('No calendars configured')
		expect(noPlacement.vm.events).toEqual([])
	})

	it('counts an external ICS feed as a source', async () => {
		mount(CalendarWidget, {
			props: { content: { externalIcsUrls: ['https://ics.test/a.ics'] }, placementId: 1 },
		})
		await flushPromises()
		expect(axios.get).toHaveBeenCalledTimes(1)
	})

	it('shows an error with a retry that fetches again', async () => {
		axios.get.mockRejectedValueOnce(new Error('500'))
		const wrapper = mountWidget()
		await flushPromises()

		expect(wrapper.find('.calendar-widget__error').text()).toContain('Failed to load events')
		expect(wrapper.vm.events).toEqual([])

		await wrapper.find('.calendar-widget__retry').trigger('click')
		await flushPromises()

		expect(axios.get).toHaveBeenCalledTimes(2)
		expect(wrapper.find('.calendar-widget__error').exists()).toBe(false)
		expect(wrapper.findAll('.calendar-widget__agenda-row')).toHaveLength(3)
	})

	it('treats a malformed payload as no events and no failures', async () => {
		axios.get.mockResolvedValue({ data: { events: 'nope', failures: {} } })
		const wrapper = mountWidget({ daysAhead: 5 })
		await flushPromises()

		expect(wrapper.find('.calendar-widget__empty').text()).toBe('No events in the next 5 days')
		expect(wrapper.find('.calendar-widget__failures').exists()).toBe(false)
	})

	it('summarises unavailable sources', async () => {
		axios.get.mockResolvedValue({ data: { events: [], failures: [{ id: 'a' }, { id: 'b' }] } })
		const wrapper = mountWidget()
		await flushPromises()
		expect(wrapper.find('.calendar-widget__failures').text()).toBe('2 calendar source(s) unavailable')
	})

	it('refetches when the content changes', async () => {
		const wrapper = mountWidget()
		await flushPromises()
		await wrapper.setProps({ content: { ...SOURCES, daysAhead: 1 } })
		await flushPromises()

		expect(axios.get).toHaveBeenCalledTimes(2)
		expect(windowOf(1).to).toBe(new Date(2026, 2, 12, 23, 59, 59, 999).toISOString())
	})
})

describe('CalendarWidget: modes', () => {
	it('starts in the configured mode, else agenda', () => {
		expect(mountWidget({ viewMode: 'week' }).vm.activeMode).toBe('week')
		expect(mountWidget({ viewMode: 'year' }).vm.activeMode).toBe('agenda')
	})

	it('labels the mode buttons and marks the active one', () => {
		const buttons = mountWidget({ viewMode: 'month' }).findAll('.calendar-widget__mode-btn')
		expect(buttons.map((b) => b.text())).toEqual(['Month', 'Week', 'Agenda'])
		expect(buttons.map((b) => b.classes().includes('is-active'))).toEqual([true, false, false])
	})

	it('switching mode refetches with that mode\'s window; unknown modes are ignored', async () => {
		const wrapper = mountWidget()
		await flushPromises()

		await wrapper.findAll('.calendar-widget__mode-btn')[0].trigger('click')
		await flushPromises()
		expect(windowOf(1)).toEqual({
			from: new Date(2026, 2, 1).toISOString(),
			to: new Date(2026, 2, 31).toISOString(),
		})

		await wrapper.findAll('.calendar-widget__mode-btn')[1].trigger('click')
		await flushPromises()
		expect(windowOf(2)).toEqual({
			from: new Date(2026, 2, 8, 0, 0, 0, 0).toISOString(),
			to: new Date(2026, 2, 14, 23, 59, 59, 999).toISOString(),
		})

		wrapper.vm.setMode('year')
		await flushPromises()
		expect(wrapper.vm.activeMode).toBe('week')
		expect(axios.get).toHaveBeenCalledTimes(3)
	})
})

describe('CalendarWidget: views', () => {
	it('groups the agenda by day with times, all-day and calendar names', async () => {
		const wrapper = mountWidget()
		await flushPromises()

		expect(wrapper.findAll('.calendar-widget__agenda-header').map((h) => h.text())).toEqual([
			expect.stringMatching(/^Wed 11 /),
			expect.stringMatching(/^Thu 12 /),
		])
		const rows = wrapper.findAll('.calendar-widget__agenda-row')
		expect(rows.map((r) => r.find('.calendar-widget__agenda-time').text())).toEqual(['09:30', 'All day', '14:05'])
		expect(rows[0].find('.calendar-widget__agenda-cal').text()).toBe('Work')
		expect(rows[2].find('.calendar-widget__agenda-cal').exists()).toBe(false)
	})

	it('accents events by calendar colour unless turned off', async () => {
		const wrapper = mountWidget()
		await flushPromises()
		expect(wrapper.vm.eventStyle(EVENTS[0])).toEqual({ 'border-left': '3px solid #ff0000' })
		expect(wrapper.vm.eventStyle(EVENTS[2])).toEqual({})
		expect(wrapper.vm.eventStyle({ color: 5 })).toEqual({})

		const plain = mountWidget({ colorByCalendar: false })
		expect(plain.vm.eventStyle(EVENTS[0])).toEqual({})
	})

	it('draws the month from the Sunday before the 1st, with today and overflow', async () => {
		const many = Array.from({ length: 5 }, (_v, i) => ({ title: `E${i}`, start: '2026-03-20T08:00:00' }))
		axios.get.mockResolvedValue({ data: { events: [...EVENTS, ...many], failures: [] } })
		const wrapper = mountWidget({ viewMode: 'month' })
		await flushPromises()

		expect(wrapper.findAll('.calendar-widget__month-header').map((h) => h.text()))
			.toEqual(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'])
		const grid = wrapper.vm.monthGrid
		expect(grid).toHaveLength(35)
		expect(grid[0]).toMatchObject({ iso: '2026-03-01', isOtherMonth: false })
		expect(grid[31]).toMatchObject({ iso: '2026-04-01', isOtherMonth: true })
		expect(grid.filter((c) => c.isToday).map((c) => c.iso)).toEqual(['2026-03-11'])
		expect(grid[10].events.map((e) => e.title)).toEqual(['Standup', 'Holiday'])

		const busy = wrapper.findAll('.calendar-widget__month-cell')[19]
		expect(busy.findAll('.calendar-widget__month-event')).toHaveLength(3)
		expect(busy.find('.calendar-widget__month-overflow').text()).toBe('+2')
	})

	it('shows the week from Sunday to Saturday with the events per day', async () => {
		const wrapper = mountWidget({ viewMode: 'week' })
		await flushPromises()

		const cols = wrapper.findAll('.calendar-widget__week-col')
		expect(cols.map((c) => c.find('.calendar-widget__week-num').text())).toEqual(['8', '9', '10', '11', '12', '13', '14'])
		expect(cols.map((c) => c.classes().includes('is-today'))).toEqual([false, false, false, true, false, false, false])
		expect(cols[3].findAll('.calendar-widget__week-title').map((e) => e.text())).toEqual(['Standup', 'Holiday'])
		expect(cols[0].find('.calendar-widget__week-empty').text()).toBe('—')
	})

	it('files an event with a missing or invalid start under today', async () => {
		axios.get.mockResolvedValue({ data: { events: [{ title: 'A' }, { title: 'B', start: 'garbage' }], failures: [] } })
		const wrapper = mountWidget()
		await flushPromises()
		expect(Object.keys(wrapper.vm.eventsByDay)).toEqual(['2026-03-11'])
		expect(wrapper.vm.eventsByDay['2026-03-11']).toHaveLength(2)
	})
})
