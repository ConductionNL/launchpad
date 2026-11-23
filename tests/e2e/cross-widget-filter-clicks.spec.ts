/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for widget clicks on the cross-widget filter
 * bus (drill-down-cross-widget-filter): the click half of Task 22, the
 * hierarchy breadcrumb of Task 23, the conflict toast of Task 24, removing a
 * clicked clause (Task 26) and the `apply` telemetry row of a click (Task 28).
 *
 * Needs the `cwf-perf` data set (see tests/e2e/support/cwfPerfSeed.ts):
 * without it every test here is skipped with that reason. Each test seeds its
 * own dashboard as the admin, stores the nl-administrative hierarchy on it and
 * makes it the admin's active dashboard, so no ambient widget is on the page.
 * Charts use the HTML `bar-list` kind, whose rows are the click targets and
 * whose labels are the grouped codes.
 *
 * Gate-19 @e2e traceability:
 *   @e2e cross-widget-filter::a-click-filters-the-consumers-and-the-url
 *   @e2e cross-widget-filter::drilling-down-infers-ancestor-filters
 *   @e2e cross-widget-filter::going-back-up-the-breadcrumb-clears-the-levels-below
 *   @e2e cross-widget-filter::a-second-publisher-replaces-the-filter-with-a-toast
 *   @e2e cross-widget-filter::removing-a-clicked-clause-unfilters-the-consumers
 *   @e2e cross-widget-filter::telemetry-records-an-apply-without-a-value
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { Placement } from './support/widgetPlacements.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { BASE_URL } from './support/baseUrl.ts'
import { installBundleOverride } from './support/bundleOverride.ts'
import { hierarchy, PERF_OBJECT_COUNT, PERF_REGISTER, PERF_SCHEMA, seededCount } from './support/cwfPerfSeed.ts'
import { clearSeededWidgets } from './support/dashboardFixture.ts'
import { chip, currentFilterParam, decodeFilterParam, OBJECTS, putAdminSettings } from './support/filterBus.ts'
import { NL_ADMINISTRATIVE, storeHierarchies } from './support/hierarchies.ts'
import { adminApi, gridItem, LP_API, openWorkspace, Seeds } from './support/widgetPlacements.ts'

const SOURCE = { register: PERF_REGISTER, schema: PERF_SCHEMA }
const ROW = '[data-testid="cn-chart-widget-bar-row"]'
const LABEL = '.cn-chart-widget__bar-label'
const ADMIN_USER = process.env.NC_ADMIN_USER ?? 'admin'
const ADMIN_PASS = process.env.NC_ADMIN_PASS ?? 'admin'

/** The charts of one seeded dashboard. */
type Board = { dash: SeededDashboard; a: Placement; a2: Placement; b: Placement; c: Placement }

/**
 * Place a titled bar-list chart on a dashboard.
 *
 * @param seeds the admin's seeds.
 * @param dash the dashboard.
 * @param gridX the column.
 * @param gridY the row.
 * @param content the chart content, with the title the panel names it by.
 * @return the placement.
 */
async function placeChart(seeds: Seeds, dash: SeededDashboard, gridX: number, gridY: number, content: { title: string } & object): Promise<Placement> {
	const placement = await seeds.widget(dash, {
		widgetId: 'chart', gridX, gridY, gridWidth: 6, gridHeight: 4,
		content: { chartKind: 'bar-list', ...content },
	})
	// The placement's own title is the name the active-filters panel gives a click;
	// the create call does not take one, so it is set afterwards.
	const titled = await seeds.api.put(`${LP_API}/widgets/${placement.id}`, { data: { customTitle: content.title } })
	expect(titled.status(), await titled.text()).toBeLessThan(300)
	return placement
}

/**
 * Seed a dashboard with the nl-administrative hierarchy and four bar-list
 * charts: A and A2 publish gemeenteCode, B shows wijken and consumes
 * gemeenteCode, C shows statuses and consumes nothing.
 *
 * @param seeds the admin's seeds.
 * @return the dashboard and its charts.
 */
async function seedBoard(seeds: Seeds): Promise<Board> {
	const dash = await seeds.dashboard(`E2E filter clicks ${Date.now()}`)
	await clearSeededWidgets(seeds.api, dash.id)
	await storeHierarchies(seeds.api, dash.id, [NL_ADMINISTRATIVE])
	const chart = (gridX: number, gridY: number, content: { title: string } & object) => placeChart(seeds, dash, gridX, gridY, content)
	const publishes = [{ dimension: 'gemeenteCode', fromField: 'gemeenteCode' }]
	const gemeenten = { ...SOURCE, groupBy: { field: 'gemeenteCode', sort: 'desc', limit: 5 } }
	const a = await chart(0, 0, { title: 'Gemeenten', dataSource: gemeenten, publishes })
	const a2 = await chart(6, 0, { title: 'Gemeenten twee', dataSource: gemeenten, publishes })
	const b = await chart(0, 4, { title: 'Wijken', dataSource: { ...SOURCE, groupBy: { field: 'wijkCode', sort: 'desc', limit: 30 } }, consumes: ['gemeenteCode'] })
	const c = await chart(6, 4, { title: 'Status', dataSource: { ...SOURCE, groupBy: { field: 'status' } } })
	return { dash, a, a2, b, c }
}

/**
 * Open the admin's active dashboard (through the bundle override when one is
 * set) and wait until every chart shows rows.
 *
 * @param page the page.
 * @param board the seeded dashboard.
 * @return nothing.
 */
async function openBoard(page: Page, board: Board): Promise<void> {
	await installBundleOverride(page.context())
	await openWorkspace(page, board.a.id)
	for (const placement of [board.a, board.a2, board.b, board.c]) {
		await expect(gridItem(page, placement.id).locator(ROW).first()).toBeVisible({ timeout: 60_000 })
	}
}

/**
 * The labels a chart shows.
 *
 * @param page the page.
 * @param placement the chart.
 * @return the labels.
 */
async function labels(page: Page, placement: Placement): Promise<string[]> {
	return (await gridItem(page, placement.id).locator(LABEL).allTextContents()).map((text) => text.trim())
}

/**
 * Wait until chart B shows only the wijken of one gemeente.
 *
 * @param page the page.
 * @param board the seeded dashboard.
 * @param gemeente the gemeente code (GMnnnn).
 * @return nothing.
 */
async function expectWijkenOf(page: Page, board: Board, gemeente: string): Promise<void> {
	const prefix = `WK${gemeente.slice(2)}`
	await expect.poll(async () => {
		const shown = await labels(page, board.b)
		return shown.length > 0 && shown.every((label) => label.startsWith(prefix))
	}, { timeout: 30_000 }).toBe(true)
}

/**
 * The gemeenteCode value the URL's `f` holds, or null.
 *
 * @param page the page.
 * @return the value or null.
 */
function gemeenteInUrl(page: Page): unknown {
	const value = currentFilterParam(page)
	if (value === null) {
		return null
	}
	return decodeFilterParam(value).find((clause) => clause.d === 'gemeenteCode')?.v ?? null
}

/**
 * Record the OpenRegister aggregation requests the page makes, grouped field
 * included.
 *
 * @param page the page.
 * @return the live list of request URLs.
 */
function aggregationRequests(page: Page): string[] {
	const urls: string[] = []
	page.on('request', (req) => {
		if (req.url().includes('/apps/openregister/api/objects/aggregations/')) {
			urls.push(decodeURIComponent(req.url()))
		}
	})
	return urls
}

let available = 0

test.beforeAll(async () => {
	available = await seededCount(BASE_URL)
})

test.describe('widget clicks on the filter bus (REQ-CWF-001, REQ-CWF-002, REQ-CWF-003, REQ-CWF-005, REQ-CWF-007)', () => {
	test.beforeEach(() => {
		test.skip(available < PERF_OBJECT_COUNT, `needs the ${PERF_REGISTER} data set (${available} objects); run tests/e2e/support/cwfPerfSeed.cli.mts`)
		test.setTimeout(240_000)
	})

	// @e2e cross-widget-filter::a-click-filters-the-consumers-and-the-url
	test('a click filters the consumer and the URL, not the non-consumer; a second click toggles; the URL restores elsewhere', async ({ page, browser }) => {
		const seeds = new Seeds(await adminApi())
		try {
			const board = await seedBoard(seeds)
			const requests = aggregationRequests(page)
			await openBoard(page, board)
			const gemeenten = await labels(page, board.a)
			expect(gemeenten.length).toBeGreaterThan(1)
			const [first, second] = gemeenten

			const before = requests.length
			await gridItem(page, board.a.id).locator(ROW).nth(0).click()
			await expect(chip(page, 'gemeenteCode')).toContainText(first)
			await expectWijkenOf(page, board, first)
			await expect.poll(() => gemeenteInUrl(page), { timeout: 5_000 }).toBe(first)
			// C consumes nothing: it does not ask again.
			expect(requests.slice(before).filter((url) => url.includes('groupBy') && url.includes('status'))).toEqual([])

			await gridItem(page, board.a.id).locator(ROW).nth(1).click()
			await expect(chip(page, 'gemeenteCode')).toContainText(second)
			await expectWijkenOf(page, board, second)
			await expect.poll(() => gemeenteInUrl(page), { timeout: 5_000 }).toBe(second)

			// The same pick again toggles the filter off.
			await gridItem(page, board.a.id).locator(ROW).filter({ hasText: second }).click()
			await expect(chip(page, 'gemeenteCode')).toHaveCount(0)
			await expect.poll(() => currentFilterParam(page), { timeout: 5_000 }).toBeNull()

			await gridItem(page, board.a.id).locator(ROW).filter({ hasText: first }).click()
			await expect.poll(() => gemeenteInUrl(page), { timeout: 5_000 }).toBe(first)
			const copied = page.url()

			// A separate login opens the copied address, the way an incognito window would.
			const other = await loginAs(browser, ADMIN_USER, ADMIN_PASS)
			try {
				await other.page.goto(copied)
				await expect(chip(other.page, 'gemeenteCode')).toContainText(first, { timeout: 30_000 })
				await expectWijkenOf(other.page, board, first)
			} finally {
				await other.context.close()
			}
		} finally {
			await seeds.cleanup()
		}
	})

	// @e2e cross-widget-filter::drilling-down-infers-ancestor-filters
	// @e2e cross-widget-filter::going-back-up-the-breadcrumb-clears-the-levels-below
	test('a wijk click shows its provincie and gemeente in the breadcrumb; going back up clears the levels below; another wijk moves them', async ({ page }) => {
		const seeds = new Seeds(await adminApi())
		try {
			const dash = await seeds.dashboard(`E2E filter breadcrumb ${Date.now()}`)
			await clearSeededWidgets(seeds.api, dash.id)
			await storeHierarchies(seeds.api, dash.id, [NL_ADMINISTRATIVE])
			const wijkChart = await placeChart(seeds, dash, 0, 0, {
				title: 'Wijken',
				dataSource: { ...SOURCE, groupBy: { field: 'wijkCode', sort: 'desc', limit: 30 } },
				publishes: [{ dimension: 'wijkCode', fromField: 'wijkCode' }],
			})
			const gemeenteChart = await placeChart(seeds, dash, 6, 0, {
				title: 'Gemeenten',
				dataSource: { ...SOURCE, groupBy: { field: 'gemeenteCode', sort: 'desc', limit: 60 } },
				consumes: ['provincieCode', 'gemeenteCode'],
			})
			await installBundleOverride(page.context())
			await openWorkspace(page, wijkChart.id)
			for (const placement of [wijkChart, gemeenteChart]) {
				await expect(gridItem(page, placement.id).locator(ROW).first()).toBeVisible({ timeout: 60_000 })
			}

			const provinceOf = new Map(hierarchy().map((gemeente) => [gemeente.code, gemeente.provincie]))
			const gemeenteOf = (wijk: string) => `GM${wijk.slice(2, 6)}`
			const steps = page.locator('[data-testid="filter-breadcrumb"] [data-testid="filter-breadcrumb-step"]')
			const wijken = await labels(page, wijkChart)
			const first = wijken[0]
			const firstGemeente = gemeenteOf(first)
			const firstProvince = provinceOf.get(firstGemeente)

			await gridItem(page, wijkChart.id).locator(ROW).filter({ hasText: first }).click()
			await expect(steps).toHaveText([`Province ${firstProvince}`, `Municipality ${firstGemeente}`, `District ${first}`], { timeout: 15_000 })
			await expect(chip(page, 'gemeenteCode')).toContainText(firstGemeente)
			await expect(chip(page, 'provincieCode')).toContainText(String(firstProvince))
			await expect.poll(() => labels(page, gemeenteChart), { timeout: 30_000 }).toEqual([firstGemeente])

			// Back up to the provincie: the gemeente and wijk go, the provincie stays.
			await steps.filter({ hasText: `Province ${firstProvince}` }).click()
			await expect(steps).toHaveText([`Province ${firstProvince}`])
			await expect(chip(page, 'gemeenteCode')).toHaveCount(0)
			await expect(chip(page, 'wijkCode')).toHaveCount(0)
			await expect.poll(async () => {
				const shown = await labels(page, gemeenteChart)
				return shown.length > 1 && shown.every((code) => provinceOf.get(code) === firstProvince)
			}, { timeout: 30_000 }).toBe(true)

			// A wijk of another gemeente, in another provincie when the chart shows one, moves both ancestors.
			const others = wijken.filter((wijk) => gemeenteOf(wijk) !== firstGemeente)
			expect(others.length).toBeGreaterThan(0)
			const other = others.find((wijk) => provinceOf.get(gemeenteOf(wijk)) !== firstProvince) ?? others[0]
			await gridItem(page, wijkChart.id).locator(ROW).filter({ hasText: other }).click()
			await expect(steps).toHaveText([`Province ${provinceOf.get(gemeenteOf(other))}`, `Municipality ${gemeenteOf(other)}`, `District ${other}`], { timeout: 15_000 })
			await expect.poll(() => labels(page, gemeenteChart), { timeout: 30_000 }).toEqual([gemeenteOf(other)])
		} finally {
			await seeds.cleanup()
		}
	})

	// @e2e cross-widget-filter::a-second-publisher-replaces-the-filter-with-a-toast
	test('a pick on a second chart replaces the first chart\'s filter and says so in a toast', async ({ page }) => {
		const seeds = new Seeds(await adminApi())
		try {
			const board = await seedBoard(seeds)
			await openBoard(page, board)
			const [first, second] = await labels(page, board.a)

			await gridItem(page, board.a.id).locator(ROW).nth(0).click()
			await expect(chip(page, 'gemeenteCode')).toContainText(first)
			await expectWijkenOf(page, board, first)

			await gridItem(page, board.a2.id).locator(ROW).filter({ hasText: second }).click()
			await expect(page.getByText(`Municipality filter changed: ${first} → ${second}`, { exact: true })).toBeVisible({ timeout: 10_000 })
			await expect(page.locator('[data-testid="active-filters"] [data-dimension]')).toHaveCount(1)
			await expect(chip(page, 'gemeenteCode')).toContainText(second)
			await expect(chip(page, 'gemeenteCode').locator('[data-testid="active-filter-source"]')).toContainText('Gemeenten twee')
			await expectWijkenOf(page, board, second)
			await expect.poll(() => gemeenteInUrl(page), { timeout: 5_000 }).toBe(second)
		} finally {
			await seeds.cleanup()
		}
	})

	// @e2e cross-widget-filter::removing-a-clicked-clause-unfilters-the-consumers
	test('a clicked clause names its chart, and removing it unfilters the consumer and drops f', async ({ page }) => {
		const seeds = new Seeds(await adminApi())
		try {
			const board = await seedBoard(seeds)
			await openBoard(page, board)
			const unfiltered = await labels(page, board.b)
			const [first] = await labels(page, board.a)

			await gridItem(page, board.a.id).locator(ROW).nth(0).click()
			await expect(chip(page, 'gemeenteCode').locator('[data-testid="active-filter-source"]')).toContainText('Gemeenten')
			await expectWijkenOf(page, board, first)

			await chip(page, 'gemeenteCode').locator('[data-testid="active-filter-remove"]').click()
			await expect(page.locator('[data-testid="active-filters"]')).toHaveCount(0)
			await expect.poll(() => currentFilterParam(page), { timeout: 5_000 }).toBeNull()
			await expect.poll(() => labels(page, board.b), { timeout: 30_000 }).toEqual(unfiltered)
		} finally {
			await seeds.cleanup()
		}
	})

	// @e2e cross-widget-filter::telemetry-records-an-apply-without-a-value
	test('with telemetry on, a click writes an apply row with the dimension and the chart but no value', async ({ page }) => {
		const seeds = new Seeds(await adminApi())
		try {
			const board = await seedBoard(seeds)
			await putAdminSettings({ filterTelemetryEnabled: true })
			await openBoard(page, board)
			await gridItem(page, board.a.id).locator(ROW).nth(0).click()
			await expect(chip(page, 'gemeenteCode')).toHaveCount(1)

			let rows: Array<Record<string, any>> = []
			await expect.poll(async () => {
				rows = (await eventsOf(seeds.api, board.dash.uuid)).filter((row) => row.eventType === 'apply')
				return rows.length
			}, { timeout: 15_000 }).toBe(1)
			const [row] = rows
			expect(row.userId).toBe(ADMIN_USER)
			expect(String(row.widgetId)).toBe(String(board.a.id))
			expect(row.filterDelta).toHaveLength(1)
			expect(row.filterDelta[0]).toMatchObject({ dimension: 'gemeenteCode', operator: 'eq' })
			expect(row.filterDelta[0]).not.toHaveProperty('value')
		} finally {
			await putAdminSettings({ filterTelemetryEnabled: false })
			await seeds.cleanup()
		}
	})
})

/**
 * The `filter_event` rows of one dashboard, read as the administrator.
 *
 * @param api the admin context.
 * @param dashboardUuid the dashboard.
 * @return the rows.
 */
async function eventsOf(api: APIRequestContext, dashboardUuid: string): Promise<Array<Record<string, any>>> {
	const res = await api.get(`${OBJECTS}/filter_event`, { params: { dashboardId: dashboardUuid, _limit: 100 } })
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const rows: Array<Record<string, any>> = body.results ?? body.data ?? body.objects ?? []
	return rows.filter((row) => row.dashboardId === dashboardUuid)
}
