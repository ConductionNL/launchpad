/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the thin `ChartHost.vue` and `StatsBlockHost.vue`
 * adapters: which props they hand the library widget, where each comes from
 * (top-level content or `content.props`) and their defaults.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'
import { computed, nextTick } from 'vue'
import ChartHost from '../ChartHost.vue'
import StatsBlockHost from '../StatsBlockHost.vue'
import { useFilterBus } from '../../../../composables/useFilterBus.js'
import { logger } from '../../../../utils/logger.js'

const dialogs = vi.hoisted(() => ({ showInfo: vi.fn(), showWarning: vi.fn() }))
vi.mock('@nextcloud/dialogs', () => dialogs)

const ChartStub = {
	name: 'CnChartWidget',
	inheritAttrs: false,
	props: ['type', 'dataSource', 'widgetId', 'selectable'],
	emits: ['select', 'data'],
	template: '<div class="chart-stub" />',
}

const StatsStub = {
	name: 'CnStatsBlockWidget',
	props: ['title', 'dataSource', 'countLabel', 'variant', 'showZeroCount', 'horizontal', 'route', 'iconClass'],
	emits: ['data'],
	template: '<div class="stats-stub" />',
}

function mountChart(content, placement = {}) {
	return mount(ChartHost, {
		props: { content, placement },
		global: { stubs: { CnChartWidget: ChartStub } },
	}).findComponent(ChartStub)
}

function mountStats(content) {
	return mount(StatsBlockHost, {
		props: { content, placement: {} },
		global: { stubs: { CnStatsBlockWidget: StatsStub } },
	}).findComponent(StatsStub)
}

describe('ChartHost', () => {
	it('defaults to an area chart without data source, named after the placement id', () => {
		const chart = mountChart({}, { id: 12 })
		expect(chart.props()).toEqual({ type: 'area', dataSource: null, widgetId: '12', selectable: false })
		expect(chart.vm.$attrs).toEqual({})
	})

	it('reads kind and source from content first, then from content.props', () => {
		const source = { url: '/a' }
		expect(mountChart({ chartKind: 'bar', dataSource: source, props: { chartKind: 'pie' } }).props())
			.toMatchObject({ type: 'bar', dataSource: source })
		expect(mountChart({ props: { chartKind: 'pie', dataSource: source } }).props())
			.toMatchObject({ type: 'pie', dataSource: source })
	})

	it('falls back from the placement id to its widget id, then to "chart"', () => {
		expect(mountChart({}, { widgetId: 'w-3' }).props('widgetId')).toBe('w-3')
		expect(mountChart({}, {}).props('widgetId')).toBe('chart')
	})

	it('passes only the known chart keys through, top-level content winning', () => {
		const chart = mountChart({
			height: 300,
			stacked: true,
			unknown: 'dropped',
			props: { height: 100, colors: ['#111'], other: 'dropped' },
		})
		expect(chart.vm.$attrs).toEqual({ height: 300, stacked: true, colors: ['#111'] })
	})
})

describe('StatsBlockHost', () => {
	it('hands title, source and the display props to the stats widget', () => {
		const source = { url: '/count' }
		const stats = mountStats({
			title: 'Open cases',
			dataSource: source,
			props: { countLabel: 'cases', variant: 'primary', showZeroCount: true, horizontal: false, route: '/cases', iconClass: 'icon-x' },
		})
		expect(stats.props()).toEqual({
			title: 'Open cases',
			dataSource: source,
			countLabel: 'cases',
			variant: 'primary',
			showZeroCount: true,
			horizontal: false,
			route: '/cases',
			iconClass: 'icon-x',
		})
	})

	it('defaults to an empty title, no source and no display props', () => {
		expect(mountStats({}).props()).toEqual({
			title: '',
			dataSource: null,
			countLabel: undefined,
			variant: undefined,
			showZeroCount: undefined,
			horizontal: undefined,
			route: undefined,
			iconClass: undefined,
		})
	})
})

describe('ChartHost on the filter bus (REQ-CWF-002, REQ-CWF-008)', () => {
	const HIERARCHY = { id: 'nl', levels: [{ dimension: 'gemeenteCode' }, { dimension: 'wijkCode', parentDimension: 'gemeenteCode' }] }

	/**
	 * Mount a chart under a bus.
	 *
	 * @param {object} content The placement content.
	 * @param {object} bus The bus.
	 * @return {import('@vue/test-utils').VueWrapper}
	 */
	function mountOnBus(content, bus) {
		return mount(ChartHost, {
			props: { content, placement: { id: 3 } },
			global: { stubs: { CnChartWidget: ChartStub }, provide: { dashboardFilterBus: computed(() => bus) } },
		})
	}

	it('a consuming chart filters its data source on the bus, and follows it', async () => {
		const bus = useFilterBus({ hierarchies: [HIERARCHY] })
		const content = { dataSource: { schema: 'zaak', filter: { type: 'bezwaar' } }, consumes: [{ dimension: 'gemeenteCode', field: 'gemeente' }] }
		const wrapper = mountOnBus(content, bus)
		expect(wrapper.findComponent(ChartStub).props('dataSource')).toEqual(content.dataSource)

		bus.publish({ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355', source: 'widgetClick' })
		await nextTick()

		expect(wrapper.findComponent(ChartStub).props('dataSource')).toEqual({ schema: 'zaak', filter: { type: 'bezwaar', gemeente: 'GM0355' } })
	})

	it('a chart consuming a dimension outside the dashboard hierarchies stays unfiltered, with a warning', async () => {
		const warn = vi.spyOn(logger, 'warn').mockImplementation(() => {})
		const bus = useFilterBus({ hierarchies: [HIERARCHY] })
		bus.publish({ dimension: 'status', operator: 'eq', value: 'open' })
		const content = { dataSource: { schema: 'zaak' }, consumes: ['status'] }

		const wrapper = mountOnBus(content, bus)

		expect(wrapper.findComponent(ChartStub).props('dataSource')).toEqual({ schema: 'zaak' })
		expect(warn).toHaveBeenCalledWith('[launchpad] widget 3 is not on the filter bus: consumes-unknown-dimension (status)')
		warn.mockRestore()
	})

	it('a chart without declarations ignores the bus', () => {
		const bus = useFilterBus({ hierarchies: [HIERARCHY] })
		bus.publish({ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355' })
		const content = { dataSource: { schema: 'zaak' } }

		expect(mountOnBus(content, bus).findComponent(ChartStub).props('dataSource')).toEqual({ schema: 'zaak' })
	})
})

describe('StatsBlockHost on the filter bus (REQ-CWF-002, REQ-CWF-008)', () => {
	const HIERARCHY = { id: 'nl', levels: [{ dimension: 'gemeenteCode' }, { dimension: 'wijkCode', parentDimension: 'gemeenteCode' }] }

	/**
	 * Mount a stats block under a bus.
	 *
	 * @param {object} content The placement content.
	 * @param {object} bus The bus.
	 * @return {import('@vue/test-utils').VueWrapper}
	 */
	function mountOnBus(content, bus) {
		return mount(StatsBlockHost, {
			props: { content, placement: { id: 8 } },
			global: { stubs: { CnStatsBlockWidget: StatsStub }, provide: { dashboardFilterBus: computed(() => bus) } },
		})
	}

	it('a consuming stats block counts on the bus filter, follows it, and ignores other dimensions', async () => {
		const bus = useFilterBus({ hierarchies: [HIERARCHY] })
		const content = { dataSource: { register: 'r', schema: 'zaak', filter: { type: 'bezwaar' } }, consumes: [{ dimension: 'gemeenteCode', field: 'gemeente' }] }
		const wrapper = mountOnBus(content, bus)
		expect(wrapper.findComponent(StatsStub).props('dataSource')).toEqual(content.dataSource)

		bus.publish({ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355', source: 'widgetClick' })
		await nextTick()
		expect(wrapper.findComponent(StatsStub).props('dataSource')).toEqual({ register: 'r', schema: 'zaak', filter: { type: 'bezwaar', gemeente: 'GM0355' } })

		bus.publish({ dimension: 'wijkCode', operator: 'eq', value: 'WK035501', source: 'widgetClick' }, { row: { gemeenteCode: 'GM0355' } })
		await nextTick()
		expect(wrapper.findComponent(StatsStub).props('dataSource').filter).toEqual({ type: 'bezwaar', gemeente: 'GM0355' })
	})

	it('a stats block consuming a dimension outside the hierarchies stays unfiltered, with a warning', () => {
		const warn = vi.spyOn(logger, 'warn').mockImplementation(() => {})
		const bus = useFilterBus({ hierarchies: [HIERARCHY] })
		bus.publish({ dimension: 'status', operator: 'eq', value: 'open' })

		const wrapper = mountOnBus({ dataSource: { schema: 'zaak' }, consumes: ['status'] }, bus)

		expect(wrapper.findComponent(StatsStub).props('dataSource')).toEqual({ schema: 'zaak' })
		expect(warn).toHaveBeenCalledWith('[launchpad] widget 8 is not on the filter bus: consumes-unknown-dimension (status)')
		warn.mockRestore()
	})

	it('a stats block without consumes ignores the bus', () => {
		const bus = useFilterBus({ hierarchies: [HIERARCHY] })
		bus.publish({ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355' })
		const source = { schema: 'zaak' }

		expect(mountOnBus({ dataSource: source }, bus).findComponent(StatsStub).props('dataSource')).toEqual(source)
	})
})

describe('ChartHost publishing a pick (REQ-CWF-001, REQ-CWF-005)', () => {
	const HIERARCHY = { id: 'nl', levels: [{ dimension: 'gemeenteCode', label: 'Gemeente' }, { dimension: 'wijkCode', parentDimension: 'gemeenteCode' }] }
	const CONTENT = { dataSource: { schema: 'zaak', groupBy: 'gemeente' }, publishes: [{ dimension: 'gemeenteCode', fromField: 'gemeente' }] }

	/**
	 * Mount a chart under a bus.
	 *
	 * @param {object} content The placement content.
	 * @param {object|null} bus The bus.
	 * @param {number} id The placement id.
	 * @return {import('@vue/test-utils').VueWrapper}
	 */
	function mountOnBus(content, bus, id = 4) {
		return mount(ChartHost, {
			props: { content, placement: { id } },
			global: { stubs: { CnChartWidget: ChartStub }, provide: { dashboardFilterBus: computed(() => bus) } },
		})
	}

	it('a publishing chart is selectable, and a pick publishes a widget click', () => {
		const bus = useFilterBus({ hierarchies: [HIERARCHY] })
		const chart = mountOnBus(CONTENT, bus).findComponent(ChartStub)
		expect(chart.props('selectable')).toBe(true)

		chart.vm.$emit('select', { field: 'gemeente', value: 'GM0355', label: 'Zeist', index: 0 })

		expect(bus.clauses.value).toEqual([
			{ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355', source: 'widgetClick', sourceWidgetId: '4', pinned: false },
		])
	})

	it('a pick on another field publishes nothing', () => {
		const bus = useFilterBus({ hierarchies: [HIERARCHY] })
		mountOnBus(CONTENT, bus).findComponent(ChartStub).vm.$emit('select', { field: 'status', value: 'open' })
		expect(bus.clauses.value).toEqual([])
	})

	it('a chart without publishes, or without a bus, is not selectable', () => {
		const bus = useFilterBus({ hierarchies: [HIERARCHY] })
		expect(mountOnBus({ dataSource: { schema: 'zaak' } }, bus).findComponent(ChartStub).props('selectable')).toBe(false)
		expect(mountOnBus(CONTENT, null).findComponent(ChartStub).props('selectable')).toBe(false)
	})

	it('a pick that replaces another widget\'s filter says so, with the level label', () => {
		dialogs.showInfo.mockClear()
		const bus = useFilterBus({ hierarchies: [HIERARCHY] })
		mountOnBus(CONTENT, bus, 5).findComponent(ChartStub).vm.$emit('select', { field: 'gemeente', value: 'GM0355' })
		mountOnBus(CONTENT, bus, 6).findComponent(ChartStub).vm.$emit('select', { field: 'gemeente', value: 'GM0321' })

		expect(dialogs.showInfo).toHaveBeenCalledTimes(1)
		expect(dialogs.showInfo).toHaveBeenCalledWith('Gemeente filter changed: GM0355 → GM0321')
		expect(bus.clauses.value.map((clause) => clause.value)).toEqual(['GM0321'])
	})

	it('a pick the reader may not filter on is refused with a warning, and the bus is left as it was', () => {
		dialogs.showWarning.mockClear()
		const bus = useFilterBus({ hierarchies: [HIERARCHY], canAccess: () => false })
		mountOnBus(CONTENT, bus).findComponent(ChartStub).vm.$emit('select', { field: 'gemeente', value: 'GM0355' })

		expect(bus.clauses.value).toEqual([])
		expect(dialogs.showWarning).toHaveBeenCalledWith('You have no access to this scope: Gemeente')
	})
})

describe('hosts forward what the library widget shows (widget-calculated-fields task 19)', () => {
	it('ChartHost and StatsBlockHost re-emit the library widget\'s data', () => {
		const chart = mount(ChartHost, { props: { content: {}, placement: { id: 1 } }, global: { stubs: { CnChartWidget: ChartStub } } })
		chart.findComponent(ChartStub).vm.$emit('data', { total: 5 })
		expect(chart.emitted('data')).toEqual([[{ total: 5 }]])

		const stats = mount(StatsBlockHost, { props: { content: {}, placement: {} }, global: { stubs: { CnStatsBlockWidget: StatsStub } } })
		stats.findComponent(StatsStub).vm.$emit('data', { count: 3 })
		expect(stats.emitted('data')).toEqual([[{ count: 3 }]])
	})
})

