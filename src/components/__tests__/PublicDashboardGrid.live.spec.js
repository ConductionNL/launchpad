/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The live widgets on the public grid: news, weather and live data render
 * through the link's or playlist's token-scoped read (REQ-PSHR-011).
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import PublicDashboardGrid from '../PublicDashboardGrid.vue'

vi.mock('@nextcloud/l10n', async (importOriginal) => ({
	...(await importOriginal()),
	translate: (_app, text) => text,
	t: (_app, text) => text,
}))

async function flushPromises() {
	for (let i = 0; i < 10; i++) {
		await new Promise((resolve) => setTimeout(resolve, 0))
	}
}

const placements = [
	{ id: 1, widgetId: 'announcements', gridX: 0, gridY: 0, content: { category: '', limit: 5 } },
	{ id: 2, widgetId: 'weather', gridX: 4, gridY: 0, content: { location: 'Zuiddrecht' } },
	{ id: 3, widgetId: 'livetile', gridX: 8, gridY: 0, content: { label: 'Waiting time' } },
]

/**
 * A token-scoped read answering per widget.
 *
 * @return {Function} The read.
 */
function reader() {
	return vi.fn(async (placementId) => {
		if (placementId === 1) {
			return { widget: 'announcements', data: { announcements: [{ uuid: 'a', title: 'Canteen closed Friday' }] } }
		}
		if (placementId === 2) {
			return { widget: 'weather', data: { location: 'Zuiddrecht', tempValue: 12, units: 'metric', condition: 'rain', conditionText: 'Light rain', stale: false } }
		}
		return { widget: 'livetile', data: { value: 7, stale: false } }
	})
}

describe('PublicDashboardGrid live widgets', () => {
	it('renders news, weather and live data with the token-scoped read', async () => {
		const readWidget = reader()
		const wrapper = mount(PublicDashboardGrid, { props: { placements, readWidget } })
		// The live widgets load on demand.
		await vi.waitFor(() => expect(readWidget).toHaveBeenCalledTimes(3), { timeout: 5000 })
		await flushPromises()

		expect(readWidget).toHaveBeenCalledWith(1)
		expect(readWidget).toHaveBeenCalledWith(2)
		expect(readWidget).toHaveBeenCalledWith(3)
		expect(wrapper.find('[data-test="public-news-widget"]').text()).toContain('Canteen closed Friday')
		expect(wrapper.findComponent({ name: 'WeatherWidget' }).vm.reading.tempValue).toBe(12)
		expect(wrapper.findComponent({ name: 'LiveTileWidget' }).vm.reading.value).toBe(7)
		expect(wrapper.find('.public-share-view__widget-restricted').exists()).toBe(false)
	})

	it('keeps them behind the sign-in notice without a read (an embed)', () => {
		const wrapper = mount(PublicDashboardGrid, { props: { placements } })

		expect(wrapper.findAll('.public-share-view__widget-restricted')).toHaveLength(3)
		expect(wrapper.findComponent({ name: 'WeatherWidget' }).exists()).toBe(false)
		expect(wrapper.find('[data-test="public-news-widget"]').exists()).toBe(false)
	})

	it('hands the widgets the reading itself, not the {widget, data} envelope', async () => {
		const readWidget = vi.fn().mockResolvedValue(undefined)
		const wrapper = mount(PublicDashboardGrid, { props: { placements: [placements[1]], readWidget } })

		expect(await wrapper.vm.readingSource(2)).toEqual({})
		expect(wrapper.vm.placementOf({ id: 99 })).toEqual({ id: 99 })
	})

	it('still restricts a widget that is not a live one', () => {
		const wrapper = mount(PublicDashboardGrid, {
			props: { placements: [{ id: 4, widgetId: 'calendar', gridX: 0, gridY: 0 }], readWidget: reader() },
		})

		expect(wrapper.find('.public-share-view__widget-restricted').exists()).toBe(true)
	})
})
