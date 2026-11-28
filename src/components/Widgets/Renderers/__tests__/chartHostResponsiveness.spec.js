/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * 300 ms responsiveness (drill-down-cross-widget-filter task 30;
 * REQ-CWF-002): after a publish every consuming chart gets its filtered data
 * source on the next tick, so the only wait is its own fetch. Two charts
 * whose fetch takes 100 ms and 250 ms both show the filtered data within
 * 300 ms of the publish; a chart that consumes nothing never refetches.
 */

import { mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { computed, defineComponent, h, nextTick } from 'vue'
import ChartHost from '../ChartHost.vue'
import StatsBlockHost from '../StatsBlockHost.vue'
import { useFilterBus } from '../../../../composables/useFilterBus.js'
import { logger } from '../../../../utils/logger.js'

const HIERARCHY = { id: 'nl', levels: [{ dimension: 'gemeenteCode' }] }

/**
 * A chart stub that "fetches" for `delay` ms whenever its data source changes
 * and then renders the filter it fetched with.
 *
 * @param {Array<object>} log Where each finished fetch is logged.
 * @return {object}
 */
function fetchingChart(log) {
	return defineComponent({
		name: 'CnChartWidget',
		props: ['dataSource', 'widgetId', 'type'],
		emits: ['data'],
		data: () => ({ shown: null }),
		watch: {
			dataSource: {
				immediate: true,
				handler(source) {
					const delay = source?.delay ?? 0
					setTimeout(() => {
						this.shown = source?.filter?.gemeente ?? null
						log.push({ widget: this.widgetId, gemeente: this.shown, at: Date.now() })
						this.$emit('data', { total: 1 })
					}, delay)
				},
			},
		},
		render() {
			return h('div', { class: 'chart', 'data-widget': this.widgetId }, String(this.shown))
		},
	})
}

beforeEach(() => {
	vi.useFakeTimers()
	vi.setSystemTime(0)
})
afterEach(() => vi.useRealTimers())

describe('consuming charts re-render within 300 ms of a publish', () => {
	it('fetches of 100 ms and 250 ms both land inside the bound; a non-consumer does not refetch', async () => {
		const bus = useFilterBus({ hierarchies: [HIERARCHY] })
		const log = []
		const stub = fetchingChart(log)
		const consumes = [{ dimension: 'gemeenteCode', field: 'gemeente' }]
		const charts = [
			{ id: 'fast', content: { dataSource: { schema: 'zaak', delay: 100 }, consumes } },
			{ id: 'slow', content: { dataSource: { schema: 'zaak', delay: 250 }, consumes } },
			{ id: 'other', content: { dataSource: { schema: 'zaak', delay: 10 } } },
		].map(({ id, content }) => mount(ChartHost, {
			props: { content, placement: { id } },
			global: { stubs: { CnChartWidget: stub }, provide: { dashboardFilterBus: computed(() => bus) } },
		}))
		await vi.advanceTimersByTimeAsync(300)
		log.length = 0
		const publishedAt = Date.now()

		bus.publish({ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355', source: 'widgetClick' })
		await nextTick()
		await vi.advanceTimersByTimeAsync(300)

		expect(log.map(({ widget, gemeente }) => [widget, gemeente])).toEqual([['fast', 'GM0355'], ['slow', 'GM0355']])
		for (const entry of log) {
			expect(entry.at - publishedAt).toBeLessThanOrEqual(300)
		}
		expect(charts.map((chart) => chart.find('.chart').text())).toEqual(['GM0355', 'GM0355', 'null'])
	})
})

describe('a slow refetch after a filter change is logged (Task 35)', () => {
	it('warns for a consumer whose data lands over 200 ms after the change, not for a fast one or a non-consumer', async () => {
		const warn = vi.spyOn(logger, 'warn').mockImplementation(() => {})
		const bus = useFilterBus({ hierarchies: [HIERARCHY] })
		const stub = fetchingChart([])
		const consumes = [{ dimension: 'gemeenteCode', field: 'gemeente' }]
		const mountChart = (id, content) => mount(ChartHost, {
			props: { content, placement: { id } },
			global: { stubs: { CnChartWidget: stub }, provide: { dashboardFilterBus: computed(() => bus) } },
		})
		mountChart('fast', { dataSource: { schema: 'zaak', delay: 150 }, consumes })
		mountChart('slow', { dataSource: { schema: 'zaak', delay: 260 }, consumes })
		mountChart('other', { dataSource: { schema: 'zaak', delay: 400 } })
		await vi.advanceTimersByTimeAsync(500)
		expect(warn).not.toHaveBeenCalled()

		bus.publish({ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355', source: 'widgetClick' })
		await nextTick()
		await vi.advanceTimersByTimeAsync(500)

		expect(warn).toHaveBeenCalledTimes(1)
		expect(warn).toHaveBeenCalledWith('[launchpad] widget slow took 260 ms to show its data after a filter change (over 200 ms): {"gemeente":"GM0355"}')
		warn.mockRestore()
	})

	it('a consuming stats block is timed the same way', async () => {
		const warn = vi.spyOn(logger, 'warn').mockImplementation(() => {})
		const bus = useFilterBus({ hierarchies: [HIERARCHY] })
		const slowStats = defineComponent({
			name: 'CnStatsBlockWidget',
			props: ['dataSource'],
			emits: ['data'],
			watch: {
				dataSource: {
					immediate: true,
					handler() {
						setTimeout(() => this.$emit('data', { count: 1 }), 300)
					},
				},
			},
			render: () => h('div'),
		})
		mount(StatsBlockHost, {
			props: { content: { dataSource: { schema: 'zaak' }, consumes: [{ dimension: 'gemeenteCode', field: 'gemeente' }] }, placement: { id: 9 } },
			global: { stubs: { CnStatsBlockWidget: slowStats }, provide: { dashboardFilterBus: computed(() => bus) } },
		})
		await vi.advanceTimersByTimeAsync(400)

		bus.publish({ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355', source: 'widgetClick' })
		await nextTick()
		await vi.advanceTimersByTimeAsync(400)

		expect(warn).toHaveBeenCalledWith('[launchpad] widget 9 took 300 ms to show its data after a filter change (over 200 ms): {"gemeente":"GM0355"}')
		warn.mockRestore()
	})
})
