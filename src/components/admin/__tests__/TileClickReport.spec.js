/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * TileClickReport: most clicked tiles with dashboard, tile, clicks and
 * distinct people, the CSV export, and nothing but a message when tracking
 * is off (launcher-tile-click-report REQ-TILEUI-001, REQ-TILEUI-002).
 * Shapes are TileAnalyticsController's: config `{enabled}`, top tiles and
 * breakdown as arrays of TileAnalyticsService rows with names added.
 */

import { flushPromises, mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import TileClickReport from '../TileClickReport.vue'
import { api } from '../../../services/api.js'

vi.mock('@nextcloud/l10n', () => ({
	translate: (_app, text, vars = {}) =>
		text.replace(/\{(\w+)\}/g, (_m, k) => vars[k] ?? `{${k}}`),
}))
vi.mock('../../../services/api.js', () => ({
	api: {
		getTileAnalyticsConfig: vi.fn(),
		getAnalyticsTopTiles: vi.fn(),
		getAnalyticsTileDashboardBreakdown: vi.fn(),
		getTileAnalyticsCsvExport: vi.fn(),
	},
}))

const top = [
	{
		placementUuid: '7',
		dashboardUuid: 'team',
		clickCount: 12,
		uniqueActorCount: 2,
		tileTitle: 'Zaaksysteem',
		dashboardName: 'Team',
	},
	{
		placementUuid: '8',
		dashboardUuid: 'team',
		clickCount: 3,
		uniqueActorCount: 1,
		tileTitle: null,
		dashboardName: 'Team',
	},
]

beforeEach(() => {
	vi.clearAllMocks()
	globalThis.URL.createObjectURL = vi.fn(() => 'blob:x')
	globalThis.URL.revokeObjectURL = vi.fn()
})

describe('TileClickReport', () => {
	it('REQ-TILEUI-001: top tiles for the period, with names and people', async () => {
		api.getTileAnalyticsConfig.mockResolvedValue({ data: { enabled: true } })
		api.getAnalyticsTopTiles.mockResolvedValue({ data: top })
		const wrapper = mount(TileClickReport, { props: { period: '30d' } })
		await flushPromises()
		expect(api.getAnalyticsTopTiles).toHaveBeenCalledWith('30d', 10)
		const rows = wrapper.findAll('[data-testid="tile-click-row"]')
		expect(rows[0].text()).toContain('Team')
		expect(rows[0].text()).toContain('Zaaksysteem')
		expect(rows[0].text()).toContain('12')
		expect(rows[0].text()).toContain('2')
		expect(rows[1].text()).toContain('Removed tile')
		expect(wrapper.find('caption').exists()).toBe(true)
	})

	it('REQ-TILEUI-001: export downloads the file from the export endpoint', async () => {
		api.getTileAnalyticsConfig.mockResolvedValue({ data: { enabled: true } })
		api.getAnalyticsTopTiles.mockResolvedValue({ data: top })
		api.getTileAnalyticsCsvExport.mockResolvedValue({ data: 'a,b\r\n' })
		const wrapper = mount(TileClickReport, { props: { period: '30d' } })
		await flushPromises()
		await wrapper.find('[data-testid="tile-export"]').trigger('click')
		await flushPromises()
		expect(api.getTileAnalyticsCsvExport).toHaveBeenCalledWith('30d')
		expect(URL.createObjectURL).toHaveBeenCalled()
	})

	it('REQ-TILEUI-002: tracking off shows a message and no table or export', async () => {
		api.getTileAnalyticsConfig.mockResolvedValue({ data: { enabled: false } })
		const wrapper = mount(TileClickReport, { props: { period: '30d' } })
		await flushPromises()
		expect(wrapper.find('[data-testid="tile-tracking-off"]').exists()).toBe(true)
		expect(wrapper.find('[data-testid="tile-click-table"]').exists()).toBe(false)
		expect(wrapper.find('[data-testid="tile-export"]').exists()).toBe(false)
		expect(api.getAnalyticsTopTiles).not.toHaveBeenCalled()
	})

	it('opens the per-dashboard view and reloads on a new period', async () => {
		api.getTileAnalyticsConfig.mockResolvedValue({ data: { enabled: true } })
		api.getAnalyticsTopTiles.mockResolvedValue({ data: top })
		api.getAnalyticsTileDashboardBreakdown.mockResolvedValue({
			data: [
				{
					placementUuid: '7',
					clickCount: 12,
					uniqueActorCount: 2,
					tileTitle: 'Zaaksysteem',
				},
			],
		})
		const wrapper = mount(TileClickReport, { props: { period: '30d' } })
		await flushPromises()
		await wrapper.find('.tile-click-report__link').trigger('click')
		await flushPromises()
		expect(api.getAnalyticsTileDashboardBreakdown).toHaveBeenCalledWith(
			'team',
			'30d',
		)
		expect(wrapper.find('[data-testid="tile-breakdown"]').text()).toContain(
			'Zaaksysteem: 12 clicks by 2 people',
		)
		await wrapper.setProps({ period: '7d' })
		await flushPromises()
		expect(api.getAnalyticsTopTiles).toHaveBeenLastCalledWith('7d', 10)
	})
})
