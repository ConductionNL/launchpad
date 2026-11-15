/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for `AdminAnalytics.vue`: loading the summary and the
 * top dashboards for the chosen period, the empty and error states, and
 * the CSV export download.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import AdminAnalytics from '../AdminAnalytics.vue'
import { api } from '../../../services/api.js'
import { logger } from '../../../utils/logger.js'

vi.mock('@nextcloud/l10n', () => ({ t: (_app, key) => key, translate: (_app, key) => key }))
vi.mock('../../../services/api.js', () => ({
	api: {
		getAnalyticsInstanceSummary: vi.fn(),
		getAnalyticsTopDashboards: vi.fn(),
		getAnalyticsCsvExport: vi.fn(),
	},
}))
vi.mock('../../../utils/logger.js', () => ({ logger: { error: vi.fn() } }))

const TileClickReportStub = { name: 'TileClickReport', props: ['period'], template: '<div class="tile-report-stub" />' }

const SUMMARY = { totalViewCount: 120, totalUniqueViewers: 30, dashboardCount: 4 }
const TOP = [
	{ dashboardUuid: 'u1', name: 'Sales', viewCount: 80, uniqueViewerCount: 20 },
	{ dashboardUuid: 'u2', name: '', viewCount: 40, uniqueViewerCount: 10 },
]

function mountAnalytics() {
	return mount(AdminAnalytics, { global: { stubs: { TileClickReport: TileClickReportStub } } })
}

beforeEach(() => {
	vi.clearAllMocks()
	api.getAnalyticsInstanceSummary.mockResolvedValue({ data: SUMMARY })
	api.getAnalyticsTopDashboards.mockResolvedValue({ data: TOP })
})

afterEach(() => {
	vi.useRealTimers()
	vi.restoreAllMocks()
})

describe('AdminAnalytics', () => {
	it('loads the 30-day summary and top 10 on creation', async () => {
		const wrapper = mountAnalytics()
		await wrapper.vm.$nextTick()
		expect(wrapper.find('.launchpad-analytics__loading').exists()).toBe(true)
		await flushPromises()

		expect(api.getAnalyticsInstanceSummary).toHaveBeenCalledWith('30d')
		expect(api.getAnalyticsTopDashboards).toHaveBeenCalledWith('30d', 10)
		expect(wrapper.findAll('.launchpad-analytics__metric-value').map((v) => v.text())).toEqual(['120', '30', '4'])
		const rows = wrapper.findAll('tbody tr').map((r) => r.findAll('td').map((c) => c.text()))
		expect(rows).toEqual([['Sales', '80', '20'], ['u2', '40', '10']])
		expect(wrapper.findComponent(TileClickReportStub).props('period')).toBe('30d')
	})

	it('reloads for a new period and passes it to the tile report', async () => {
		const wrapper = mountAnalytics()
		await flushPromises()

		await wrapper.find('select').setValue('7d')
		await flushPromises()

		expect(api.getAnalyticsInstanceSummary).toHaveBeenLastCalledWith('7d')
		expect(api.getAnalyticsTopDashboards).toHaveBeenLastCalledWith('7d', 10)
		expect(wrapper.findComponent(TileClickReportStub).props('period')).toBe('7d')
	})

	it('shows zeros and the empty hint without data', async () => {
		api.getAnalyticsInstanceSummary.mockResolvedValue({ data: null })
		api.getAnalyticsTopDashboards.mockResolvedValue({ data: { not: 'a list' } })
		const wrapper = mountAnalytics()
		await flushPromises()

		expect(wrapper.findAll('.launchpad-analytics__metric-value').map((v) => v.text())).toEqual(['0', '0', '0'])
		expect(wrapper.find('table').exists()).toBe(false)
		expect(wrapper.text()).toContain('No view events recorded yet for this period.')
	})

	it('shows an error when either request fails', async () => {
		api.getAnalyticsTopDashboards.mockRejectedValue(new Error('500'))
		const wrapper = mountAnalytics()
		await flushPromises()

		expect(wrapper.find('.launchpad-analytics__error').text()).toBe('Failed to load analytics data. Please try again.')
		expect(logger.error).toHaveBeenCalledWith('Failed to load analytics:', expect.any(Error))
	})

	it('downloads the CSV export named after today', async () => {
		vi.useFakeTimers({ toFake: ['Date'] })
		vi.setSystemTime(new Date('2026-03-11T12:00:00Z'))
		api.getAnalyticsCsvExport.mockResolvedValue({ data: 'uuid,views\n' })
		URL.createObjectURL = vi.fn(() => 'blob:csv')
		URL.revokeObjectURL = vi.fn()
		const clicks = []
		vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function() {
			clicks.push({ href: this.getAttribute('href'), download: this.download })
		})
		const wrapper = mountAnalytics()
		await flushPromises()

		await wrapper.find('.launchpad-analytics__button').trigger('click')
		await flushPromises()

		expect(api.getAnalyticsCsvExport).toHaveBeenCalledWith('30d')
		expect(URL.createObjectURL.mock.calls[0][0]).toBeInstanceOf(Blob)
		expect(clicks).toEqual([{ href: 'blob:csv', download: 'dashboard-analytics-2026-03-11.csv' }])
		expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:csv')
		expect(wrapper.vm.exporting).toBe(false)
	})

	it('shows an error when the export fails', async () => {
		api.getAnalyticsCsvExport.mockRejectedValue(new Error('x'))
		const wrapper = mountAnalytics()
		await flushPromises()

		await wrapper.find('.launchpad-analytics__button').trigger('click')
		await flushPromises()

		expect(wrapper.find('.launchpad-analytics__error').text()).toBe('Failed to export analytics CSV. Please try again.')
		expect(logger.error).toHaveBeenCalledWith('Failed to export analytics CSV:', expect.any(Error))
	})
})
