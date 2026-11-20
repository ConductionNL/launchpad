/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * ActivityReportWidget: the purpose on a report about a named person, the
 * refusal said in words, counts and a calendar from the server's figures
 * (openspec/changes/archive/2026-11-19-dashboards-and-who-may-see-them REQ-DWMS-005/006).
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const interpolate = (text, vars = {}) => text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`)
vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, text, vars = {}) => interpolate(text, vars),
	translatePlural: (_app, one, many, count, vars = {}) => interpolate(count === 1 ? one : many, vars),
}))
vi.mock('@nextcloud/vue', () => ({
	NcTextField: { name: 'NcTextField', props: ['modelValue', 'label', 'type'], emits: ['update:modelValue'], template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)">' },
	NcButton: { name: 'NcButton', template: '<button type="submit"><slot /></button>' },
}))
vi.mock('../../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const loadActivityReport = vi.fn()
vi.mock('../../../../services/activityReport.js', () => ({
	loadActivityReport: (...args) => loadActivityReport(...args),
	activityReportCsvUrl: ({ user, from, to }) => `/csv?user=${user}&from=${from}&to=${to}`,
}))

import ActivityReportWidget from '../ActivityReportWidget.vue'


function report(overrides = {}) {
	return {
		refused: false,
		subject: 'alice',
		self: false,
		from: '2026-09-01',
		to: '2026-09-05',
		purpose: 'workload balancing',
		available: true,
		counts: { dashboard_updated: 3, file_changed: 1 },
		days: { '2026-09-02': 1, '2026-09-04': 3 },
		total: 4,
		...overrides,
	}
}

async function mountWith(result, content = {}) {
	loadActivityReport.mockResolvedValue(result)
	const wrapper = mount(ActivityReportWidget, { props: { content } })
	await flushPromises()
	return wrapper
}

beforeEach(() => {
	loadActivityReport.mockReset()
})

describe('ActivityReportWidget', () => {
	it('shows the purpose on a report about a named person', async () => {
		const wrapper = await mountWith(report())

		expect(wrapper.find('[data-testid="activity-report-purpose"]').text()).toContain('workload balancing')
		expect(wrapper.find('[data-testid="activity-report-summary"]').text()).toBe('Activity of alice from 2026-09-01 to 2026-09-05: 4 actions')
	})

	it('shows no purpose block on your own report when none was recorded', async () => {
		const wrapper = await mountWith(report({ self: true, purpose: null }))

		expect(wrapper.find('[data-testid="activity-report-purpose"]').exists()).toBe(false)
		expect(wrapper.find('[data-testid="activity-report-summary"]').text()).toBe('Your activity from 2026-09-01 to 2026-09-05: 4 actions')
	})

	it('lists the counts per type and the server total', async () => {
		const wrapper = await mountWith(report())

		const rows = wrapper.findAll('[data-testid="activity-report-counts"] tbody tr')
		expect(rows.map((row) => row.text())).toEqual(['dashboard updated3', 'file changed1'])
		expect(wrapper.find('[data-testid="activity-report-total"]').text()).toBe('4')
	})

	it('draws one cell per day, an empty day at level 0 and the busiest at level 4', async () => {
		const wrapper = await mountWith(report())

		const cells = wrapper.findAll('[data-testid="activity-report-calendar"] li')
		expect(cells).toHaveLength(5)
		expect(wrapper.find('[data-testid="activity-report-day-2026-09-01"]').classes()).toContain('activity-report__day--level-0')
		expect(wrapper.find('[data-testid="activity-report-day-2026-09-02"]').classes()).toContain('activity-report__day--level-2')
		expect(wrapper.find('[data-testid="activity-report-day-2026-09-04"]').classes()).toContain('activity-report__day--level-4')
		expect(wrapper.find('[data-testid="activity-report-day-2026-09-04"]').attributes('aria-label')).toBe('2026-09-04: 3 actions')
	})

	it('links the CSV of the report as shown', async () => {
		const wrapper = await mountWith(report())

		expect(wrapper.find('[data-testid="activity-report-export"]').attributes('href')).toBe('/csv?user=alice&from=2026-09-01&to=2026-09-05')
	})

	it('says the capability is off and how to turn it on when the server refuses for that', async () => {
		const wrapper = await mountWith({ refused: true, status: 403, message: 'off', enabled: false })

		const text = wrapper.find('[data-testid="activity-report-refused"]').text()
		expect(text).toContain('Activity reporting on named people is off.')
		expect(text).toContain('An administrator can turn it on in the LaunchPad admin settings')
		expect(wrapper.find('[data-testid="activity-report-counts"]').exists()).toBe(false)
	})

	it('says only an administrator may read another person when it is on', async () => {
		const wrapper = await mountWith({ refused: true, status: 403, message: 'no', enabled: true })

		expect(wrapper.find('[data-testid="activity-report-refused"]').text()).toBe('Only an administrator can read another person\'s activity. You can always see your own.')
	})

	it('says what is wrong with a refused period', async () => {
		const wrapper = await mountWith({ refused: true, status: 400, message: 'bad', enabled: true })

		expect(wrapper.find('[data-testid="activity-report-refused"]').text()).toContain('at most 366 days apart')
	})

	it('says the Activity app is needed when the server cannot read events', async () => {
		const wrapper = await mountWith(report({ available: false, counts: {}, days: {}, total: 0 }))

		expect(wrapper.find('[data-testid="activity-report-unavailable"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="activity-report-calendar"]').exists()).toBe(false)
	})

	it('asks for yourself over the configured number of days, ending today', async () => {
		await mountWith(report(), { days: 7 })

		const request = loadActivityReport.mock.calls[0][0]
		expect(request.user).toBe('')
		const span = (Date.parse(request.to) - Date.parse(request.from)) / 86400000
		expect(span).toBe(6)
	})

	it('asks again for the person typed in', async () => {
		const wrapper = await mountWith(report())
		loadActivityReport.mockResolvedValue(report({ subject: 'bob' }))

		await wrapper.find('[data-testid="activity-report-user"]').setValue('bob')
		await wrapper.find('form').trigger('submit')
		await flushPromises()

		expect(loadActivityReport).toHaveBeenLastCalledWith(expect.objectContaining({ user: 'bob' }))
		expect(wrapper.find('[data-testid="activity-report-summary"]').text()).toContain('Activity of bob')
	})

	it('says so when the report cannot be loaded', async () => {
		loadActivityReport.mockRejectedValue(new Error('down'))
		const wrapper = mount(ActivityReportWidget, { props: { content: {} } })
		await flushPromises()

		expect(wrapper.find('[data-testid="activity-report-error"]').exists()).toBe(true)
	})
})
