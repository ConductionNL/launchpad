/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Vitest unit tests for the thin `ChartHost.vue` and `StatsBlockHost.vue`
 * adapters: which props they hand the library widget, where each comes from
 * (top-level content or `content.props`) and their defaults.
 */

import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import ChartHost from '../ChartHost.vue'
import StatsBlockHost from '../StatsBlockHost.vue'

const ChartStub = {
	name: 'CnChartWidget',
	inheritAttrs: false,
	props: ['type', 'dataSource', 'widgetId'],
	template: '<div class="chart-stub" />',
}

const StatsStub = {
	name: 'CnStatsBlockWidget',
	props: ['title', 'dataSource', 'countLabel', 'variant', 'showZeroCount', 'horizontal', 'route', 'iconClass'],
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
		expect(chart.props()).toEqual({ type: 'area', dataSource: null, widgetId: '12' })
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
