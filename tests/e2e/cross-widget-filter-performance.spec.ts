/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Drill-down performance of the cross-widget filter bus on a populated
 * OpenRegister (drill-down-cross-widget-filter Tasks 10 and 35, REQ-CWF-002).
 *
 * Needs the `cwf-perf` data set (50,000 objects along the Dutch administrative
 * hierarchy, see tests/e2e/support/cwfPerfSeed.ts); without it every test here
 * is skipped with that reason.
 *
 * The dashboard holds a gemeente chart A that publishes gemeenteCode, a wijk
 * chart B that consumes gemeenteCode and publishes wijkCode, a status chart E
 * that consumes gemeenteCode and wijkCode, and a province chart C that consumes
 * nothing. Each dashboard stores the nl-administrative hierarchy through the
 * dashboard API (REQ-CWF-011), so every click, publish, refetch, OpenRegister
 * query and render is the live one.
 *
 * Each click is measured in the page's own clock, from the pointerdown:
 * to B showing only the clicked gemeente's wijken, to E's first DOM update
 * after its filtered response, and the server time (request start to first
 * byte) of every filtered request. The same drill-down runs once with
 * ApexCharts bars and once with the HTML `bar-list` chart kind, so the time
 * the chart drawing takes is told apart from the bus and the query. The
 * numbers are attached as JSON and printed.
 *
 * Gate-19 @e2e traceability:
 *   @e2e cross-widget-filter::stored-hierarchies-put-consuming-widgets-on-the-bus
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-10
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-35
 * @spec openspec/changes/drill-down-cross-widget-filter/tasks.md#task-41
 */

import type { CDPSession, Page, TestInfo } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'
import { PERF_OBJECT_COUNT, PERF_REGISTER, PERF_SCHEMA, seededCount } from './support/cwfPerfSeed.ts'
import { clearSeededWidgets } from './support/dashboardFixture.ts'
import { NL_ADMINISTRATIVE, storeHierarchies } from './support/hierarchies.ts'
import { adminApi, gridItem, openWorkspace, Seeds } from './support/widgetPlacements.ts'

const BOUND_MS = 300
const MEMORY_BOUND_BYTES = 1024 * 1024
const REPETITIONS = 12

const SOURCE = { register: PERF_REGISTER, schema: PERF_SCHEMA }
const QUIET = { chart: { animations: { enabled: false } } }

/** How to find and click the categories of one chart kind. */
type Renderer = { kind: string; item: string; label: string; options?: object }

const APEX: Renderer = {
	kind: 'bar',
	item: '.apexcharts-bar-area[j="{i}"]',
	label: '.apexcharts-xaxis-label',
	options: QUIET,
}
const BAR_LIST: Renderer = {
	kind: 'bar-list',
	item: '[data-testid="cn-chart-widget-bar-row"] >> nth={i}',
	label: '.cn-chart-widget__bar-label',
}

/**
 * Page-side instruments: the time of the last press, a large resource timing
 * buffer, a mutation log per watched element, long tasks, and a wait for a
 * chart whose every label starts with a prefix.
 *
 * @param page the page.
 * @return nothing.
 */
async function instrument(page: Page): Promise<void> {
	await page.addInitScript(() => {
		const w = window as any
		w.__cwf = { click: 0, mutations: {}, longtasks: [] }
		performance.setResourceTimingBufferSize(10_000)
		// ApexCharts selects on the press, so a click starts at pointerdown.
		document.addEventListener('pointerdown', () => { w.__cwf.click = performance.now() }, true)
		new PerformanceObserver((list) => {
			for (const entry of list.getEntries()) {
				w.__cwf.longtasks.push([entry.startTime, entry.duration])
			}
		}).observe({ type: 'longtask', buffered: true })
		// A widget and the shadow roots inside it: CnChartWidget draws ApexCharts
		// inside its canvas box's shadow root, which neither a MutationObserver
		// nor querySelectorAll on the widget sees into.
		w.__cwfRoots = (selector: string) => {
			const host = document.querySelector(selector)!
			const shadows = Array.from(host.querySelectorAll('.cn-chart-widget__canvas'))
				.map((box) => (box as Element).shadowRoot)
				.filter((root): root is ShadowRoot => root !== null)
			return [host, ...shadows]
		}
		const observeAll = (observer: MutationObserver, selector: string) => {
			for (const root of w.__cwfRoots(selector)) {
				observer.observe(root, { subtree: true, childList: true, attributes: true, characterData: true })
			}
		}
		w.__cwfWatch = (key: string, selector: string) => {
			const log: number[] = []
			w.__cwf.mutations[key] = log
			observeAll(new MutationObserver(() => log.push(performance.now())), selector)
		}
		w.__cwfLabels = (selector: string, label: string) => (w.__cwfRoots(selector) as ParentNode[])
			.flatMap((root) => Array.from(root.querySelectorAll(label)))
			.map((node) => (node.querySelector('title')?.textContent ?? node.textContent ?? '').trim())
		w.__cwfWaitLabels = (key: string, selector: string, label: string, prefix: string) => {
			const observer = new MutationObserver(() => {
				// ApexCharts blanks an x-axis label it drops for overlap (an empty
				// <title>); that bar is still this gemeente's, so judge the named ones.
				const labels = w.__cwfLabels(selector, label).filter((text: string) => text !== '')
				if (labels.length > 0 && labels.every((text: string) => text.startsWith(prefix))) {
					observer.disconnect()
					w.__cwf[key] = performance.now()
				}
			})
			observeAll(observer, selector)
		}
	})
}

type Timing = { url: string; start: number; serverMs: number; responseEnd: number }

/**
 * The page's resource timings of OpenRegister aggregation requests since a moment.
 *
 * @param page the page.
 * @param since a performance.now() value.
 * @return the timings.
 */
async function aggregationsSince(page: Page, since: number): Promise<Timing[]> {
	return page.evaluate((from) => (performance.getEntriesByType('resource') as PerformanceResourceTiming[])
		.filter((entry) => entry.name.includes('/apps/openregister/api/objects/aggregations/') && entry.startTime >= from)
		.map((entry) => ({
			url: decodeURIComponent(entry.name),
			start: entry.startTime,
			serverMs: entry.responseStart - entry.requestStart,
			responseEnd: entry.responseEnd,
		})), since)
}

/**
 * The first mutation of a watched element at or after a moment.
 *
 * @param page the page.
 * @param key the watch.
 * @param after a performance.now() value.
 * @return the time.
 */
async function firstMutationAfter(page: Page, key: string, after: number): Promise<number> {
	await expect.poll(() => page.evaluate(({ k, t }) => ((window as any).__cwf.mutations[k] as number[]).some((m) => m >= t), { k: key, t: after }), { timeout: 30_000 }).toBe(true)
	return page.evaluate(({ k, t }) => ((window as any).__cwf.mutations[k] as number[]).find((m) => m >= t)!, { k: key, t: after })
}

/**
 * Median and 95th percentile (nearest rank) of a list.
 *
 * @param values the values.
 * @return the statistics, rounded to 0.1 ms.
 */
function stats(values: number[]): { n: number; median: number; p95: number; max: number } {
	const sorted = [...values].sort((a, b) => a - b)
	const round = (v: number) => Math.round(v * 10) / 10
	const mid = sorted.length % 2 === 1
		? sorted[(sorted.length - 1) / 2]
		: (sorted[sorted.length / 2 - 1] + sorted[sorted.length / 2]) / 2
	const p95 = sorted[Math.min(sorted.length - 1, Math.ceil(0.95 * sorted.length) - 1)]
	return { n: sorted.length, median: round(mid), p95: round(p95), max: round(sorted[sorted.length - 1]) }
}

/**
 * Attach and print a measurement.
 *
 * @param info the test info.
 * @param name the attachment name.
 * @param data the numbers.
 * @return nothing.
 */
async function report(info: TestInfo, name: string, data: unknown): Promise<void> {
	const text = JSON.stringify(data, null, 2)
	console.log(`[${name}] ${text}`)
	await info.attach(name, { body: text, contentType: 'application/json' })
}

/**
 * Seed the four-chart dashboard, click a gemeente on A and then the largest
 * wijk on B, REPETITIONS times, and measure every step.
 *
 * @param page the page.
 * @param renderer the chart kind.
 * @return the summary.
 */
async function drillDown(page: Page, renderer: Renderer): Promise<Record<string, any>> {
	const seeds = new Seeds(await adminApi())
	try {
		const dash = await seeds.dashboard(`E2E drill-down ${renderer.kind} ${Date.now()}`)
		await clearSeededWidgets(seeds.api, dash.id)
		await storeHierarchies(seeds.api, dash.id, [NL_ADMINISTRATIVE])
		const chart = (gridX: number, gridY: number, gridWidth: number, content: object) => seeds.widget(dash, {
			widgetId: 'chart', gridX, gridY, gridWidth, gridHeight: 4,
			content: { chartKind: renderer.kind, ...(renderer.options ? { options: renderer.options } : {}), ...content },
		})
		const a = await chart(0, 0, 6, { title: 'Gemeenten', dataSource: { ...SOURCE, groupBy: { field: 'gemeenteCode', sort: 'desc', limit: REPETITIONS } }, publishes: [{ dimension: 'gemeenteCode', fromField: 'gemeenteCode' }] })
		const c = await chart(6, 0, 3, { title: 'Provincies', dataSource: { ...SOURCE, groupBy: { field: 'provincieCode' } } })
		const e = await chart(9, 0, 3, { title: 'Status', dataSource: { ...SOURCE, groupBy: { field: 'status' } }, consumes: ['gemeenteCode', 'wijkCode'] })
		const b = await chart(0, 4, 12, { title: 'Wijken', dataSource: { ...SOURCE, groupBy: { field: 'wijkCode', sort: 'desc', limit: 30 } }, consumes: ['gemeenteCode'], publishes: [{ dimension: 'wijkCode', fromField: 'wijkCode' }] })
		const sel = (id: number) => `.launchpad-grid-item[data-placement-id="${id}"]`
		const item = (id: number, i: number) => gridItem(page, id).locator(renderer.item.replace('{i}', String(i)))

		const cached: boolean[] = []
		page.on('response', async (response) => {
			if (response.url().includes('/aggregations/') && response.url().includes('filter')) {
				try {
					cached.push((await response.json()).cached === true)
				} catch {
					// a body that is gone is not counted
				}
			}
		})
		await instrument(page)
		await openWorkspace(page, b.id)
		for (const id of [a.id, b.id, c.id, e.id]) {
			await expect(item(id, 0)).toBeVisible({ timeout: 120_000 })
		}
		await page.waitForTimeout(2_000)
		const gemeenten: string[] = await page.evaluate(({ s, l }) => (window as any).__cwfLabels(s, l), { s: sel(a.id), l: renderer.label })
		expect(gemeenten.length).toBe(REPETITIONS)
		const page0 = await page.evaluate(() => ({ elements: document.getElementsByTagName('*').length }))
		await page.evaluate((s) => (window as any).__cwfWatch('c', s), sel(c.id))
		const startedAt: number = await page.evaluate(() => performance.now())

		const rows: Array<Record<string, any>> = []
		for (let i = 0; i < REPETITIONS; i++) {
			const gemeente = gemeenten[i]
			await page.evaluate(({ key, s }) => (window as any).__cwfWatch(key, s), { key: `e${i}`, s: sel(e.id) })
			await page.evaluate(({ key, s, l, prefix }) => (window as any).__cwfWaitLabels(key, s, l, prefix), { key: `b${i}`, s: sel(b.id), l: renderer.label, prefix: `WK${gemeente.slice(2)}` })
			await item(a.id, i).click({ force: true })
			await page.waitForFunction((key) => typeof (window as any).__cwf[key] === 'number', `b${i}`, { timeout: 60_000 })
			const bReady: number = await page.evaluate((key) => (window as any).__cwf[key], `b${i}`)
			const click: number = await page.evaluate(() => (window as any).__cwf.click)
			const filtered = (t: Timing, field: string) => t.url.includes(`groupBy=${field}`) && t.url.includes(`filter[gemeenteCode]=${gemeente}`)
			await expect.poll(async () => (await aggregationsSince(page, click)).some((t) => filtered(t, 'status')), { timeout: 30_000 }).toBe(true)
			const step1 = await aggregationsSince(page, click)
			const eRequest = step1.find((t) => filtered(t, 'status'))!
			const bRequest = step1.find((t) => filtered(t, 'wijkCode'))!
			expect(bRequest, JSON.stringify(step1)).toBeTruthy()
			const eRender = await firstMutationAfter(page, `e${i}`, eRequest.responseEnd)

			// One level down: the largest wijk of this gemeente on B.
			await page.waitForTimeout(500)
			const wijk: string = (await page.evaluate(({ s, l }) => (window as any).__cwfLabels(s, l), { s: sel(b.id), l: renderer.label }))[0]
			await page.evaluate(({ key, s }) => (window as any).__cwfWatch(key, s), { key: `w${i}`, s: sel(e.id) })
			await item(b.id, 0).click({ force: true })
			const wijkClick: number = await page.evaluate(() => (window as any).__cwf.click)
			await expect.poll(async () => (await aggregationsSince(page, wijkClick)).some((t) => t.url.includes(`filter[wijkCode]=${wijk}`)), { timeout: 30_000 }).toBe(true)
			const wRequest = (await aggregationsSince(page, wijkClick)).find((t) => t.url.includes('groupBy=status') && t.url.includes(`filter[wijkCode]=${wijk}`))!
			const wRender = await firstMutationAfter(page, `w${i}`, wRequest.responseEnd)

			rows.push({
				gemeente,
				wijk,
				clickToB: bReady - click,
				clickToE: eRender - click,
				clickToAllConsumers: Math.max(bReady, eRender) - click,
				clickToRequestSent: Math.min(bRequest.start, eRequest.start) - click,
				responseToB: bReady - bRequest.responseEnd,
				serverB: bRequest.serverMs,
				serverE: eRequest.serverMs,
				wijkClickToE: wRender - wijkClick,
				serverEWijk: wRequest.serverMs,
			})
			await page.waitForTimeout(500)
		}

		const all = await aggregationsSince(page, startedAt)
		const longtasks: number[][] = await page.evaluate((from) => (window as any).__cwf.longtasks.filter((t: number[]) => t[0] >= from), startedAt)
		const cMutations: number = await page.evaluate(() => (window as any).__cwf.mutations.c.length)
		const col = (key: string) => rows.map((row) => Number(row[key]))
		return {
			renderer: renderer.kind,
			volume: { objects: await seededCount(BASE_URL), register: PERF_REGISTER, schema: PERF_SCHEMA },
			repetitions: REPETITIONS,
			pageElements: page0.elements,
			clickToAllConsumers: stats(col('clickToAllConsumers')),
			clickToB: stats(col('clickToB')),
			clickToE: stats(col('clickToE')),
			clickToRequestSent: stats(col('clickToRequestSent')),
			responseToB: stats(col('responseToB')),
			wijkClickToE: stats(col('wijkClickToE')),
			serverGemeenteStep: stats([...col('serverB'), ...col('serverE')]),
			serverWijkStep: stats(col('serverEWijk')),
			longTasksOver200ms: longtasks.filter((t) => t[1] > 200).length,
			longestTaskMs: Math.round(Math.max(0, ...longtasks.map((t) => t[1]))),
			nonConsumerRequests: all.filter((t) => t.url.includes('groupBy=provincieCode') || t.url.includes('groupBy=gemeenteCode')).length,
			nonConsumerMutations: cMutations,
			publisherRefetchOnOwnPick: all.filter((t) => t.url.includes('groupBy=wijkCode') && t.url.includes('filter[wijkCode]')).length,
			cachedResponses: cached.filter(Boolean).length,
			filteredResponses: cached.length,
			rows,
		}
	} finally {
		await seeds.cleanup()
	}
}

let available = 0

test.beforeAll(async () => {
	available = await seededCount(BASE_URL)
})

test.describe('cross-widget filter performance on 50,000 objects (REQ-CWF-002)', () => {
	for (const renderer of [BAR_LIST, APEX]) {
		test(`${renderer.kind} charts: gemeente then wijk clicks refetch only the consumers, within ${BOUND_MS} ms`, async ({ page }, info) => {
			test.skip(available < PERF_OBJECT_COUNT, `needs the ${PERF_REGISTER} data set (${available} objects); run tests/e2e/support/cwfPerfSeed.cli.mts`)
			test.setTimeout(900_000)
			const summary = await drillDown(page, renderer)
			await report(info, `cwf-drilldown-${renderer.kind}`, summary)

			// C and A consume nothing and B does not consume wijkCode: none of them asked again, C did not redraw.
			expect(summary.nonConsumerRequests).toBe(0)
			expect(summary.nonConsumerMutations).toBe(0)
			expect(summary.publisherRefetchOnOwnPick).toBe(0)
			// Task 35: the drill-down queries alone fit in the bound on the OpenRegister side.
			expect(summary.serverGemeenteStep.p95, 'OpenRegister time of the gemeente step, p95').toBeLessThanOrEqual(BOUND_MS)
			expect(summary.serverWijkStep.p95, 'OpenRegister time of the wijk step, p95').toBeLessThanOrEqual(BOUND_MS)
			// REQ-CWF-002: every consumer shows the filtered data within 300 ms of the click.
			expect(summary.clickToAllConsumers.p95, 'click to every consumer re-rendered, p95').toBeLessThanOrEqual(BOUND_MS)
			expect(summary.wijkClickToE.p95, 'wijk click to the consumer re-rendered, p95').toBeLessThanOrEqual(BOUND_MS)
		})
	}

	test('110 charts on the bus cost under 1 MB of heap over the same charts off it', async ({ page }, info) => {
		test.skip(available < PERF_OBJECT_COUNT, `needs the ${PERF_REGISTER} data set (${available} objects); run tests/e2e/support/cwfPerfSeed.cli.mts`)
		test.setTimeout(900_000)
		const COUNT = 110
		const seeds = new Seeds(await adminApi())
		try {
			const variants: Record<string, { dash: SeededDashboard; first: number }> = {}
			for (const variant of ['off', 'on']) {
				const dash = await seeds.dashboard(`E2E bus memory ${variant} ${Date.now()}`)
				await clearSeededWidgets(seeds.api, dash.id)
				await storeHierarchies(seeds.api, dash.id, [NL_ADMINISTRATIVE])
				let first = 0
				for (let i = 0; i < COUNT; i++) {
					const bus = variant === 'on'
						? { consumes: ['gemeenteCode', 'wijkCode'], publishes: [{ dimension: 'gemeenteCode', fromField: 'gemeenteCode' }] }
						: {}
					const placed = await seeds.widget(dash, {
						widgetId: 'chart', gridX: (i % 6) * 2, gridY: Math.floor(i / 6) * 2, gridWidth: 2, gridHeight: 2,
						content: { title: `Chart ${i}`, chartKind: 'bar-list', series: [{ name: 'n', data: [3, 1, 2] }], categories: ['a', 'b', 'c'], ...bus },
					})
					first = first || placed.id
				}
				variants[variant] = { dash, first }
			}

			const cdp: CDPSession = await page.context().newCDPSession(page)
			await cdp.send('HeapProfiler.enable')
			const heaps: Record<string, number[]> = { off: [], on: [] }
			for (let round = 0; round < 4; round++) {
				for (const variant of ['off', 'on']) {
					await seeds.activate(variants[variant].dash)
					await openWorkspace(page, variants[variant].first)
					await expect(page.locator('.launchpad-grid-item[data-placement-id]')).toHaveCount(COUNT, { timeout: 60_000 })
					await expect(page.locator('[data-testid="cn-chart-widget-bar-row"]')).toHaveCount(COUNT * 3, { timeout: 120_000 })
					await page.waitForTimeout(3_000)
					for (let gc = 0; gc < 3; gc++) {
						await cdp.send('HeapProfiler.collectGarbage')
					}
					const usage = await cdp.send('Runtime.getHeapUsage')
					heaps[variant].push(usage.usedSize)
				}
			}
			const median = (values: number[]) => stats(values).median
			const overhead = median(heaps.on) - median(heaps.off)
			await report(info, 'cwf-bus-memory', {
				widgets: COUNT,
				heapOffBytes: heaps.off,
				heapOnBytes: heaps.on,
				medianOffBytes: median(heaps.off),
				medianOnBytes: median(heaps.on),
				overheadBytes: overhead,
				boundBytes: MEMORY_BOUND_BYTES,
			})
			expect(overhead).toBeLessThan(MEMORY_BOUND_BYTES)
		} finally {
			await seeds.cleanup()
		}
	})
})
