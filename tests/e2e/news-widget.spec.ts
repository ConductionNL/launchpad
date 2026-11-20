/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for registering, adding and configuring the
 * news widget (openspec/specs/news-widget), written for
 * e2e-coverage-programme.
 *
 * Configuration goes through the product's own controls: the More menu's
 * "Add custom widget…" modal with the News type, and the edit-mode context
 * menu's Edit for an existing placement. The API only seeds placements and
 * reads them back. Where a response has to carry items, the app's own items
 * endpoint is stubbed (see tests/e2e/support/newsWidget.ts for why the CI
 * fixture cannot make the server produce any).
 *
 * The other two files of this capability:
 *   news-widget-server.spec.ts     the real items endpoint
 *   news-widget-rendering.spec.ts  layouts, badge, dates, summaries, links
 *
 * Gate-19 @e2e traceability:
 *   @e2e news-widget::widget-registration-on-bootstrap
 *   @e2e news-widget::widget-picker-includes-news-widget
 *   @e2e news-widget::multiple-news-widget-instances
 *   @e2e news-widget::widget-metadata-is-discoverable
 *   @e2e news-widget::configure-feed-urls
 *   @e2e news-widget::configure-layout-mode
 *   @e2e news-widget::configure-item-limit
 *   @e2e news-widget::optional-metadata-filtering-configuration
 *   @e2e news-widget::layout-switching
 *
 * @spec openspec/specs/news-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { NewsPlacement } from './support/newsWidget.ts'

import { expect, test } from '@playwright/test'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'
import {
	adminApi,
	API,
	chooseFormOption,
	failingFeed,
	fetchItems,
	formOptionLabels,
	gotoDashboard,
	ITEMS_URL,
	newsItem,
	newsWidget,
	openAddNewsForm,
	openNewsEditor,
	readContent,
	readPlacements,
	removePlacements,
	seedNewsPlacement,
	stubNewsItems,
} from './support/newsWidget.ts'

const STAMP = `${Date.now()}`
const NC_WIDGET_ID = 'launchpad_news'

let api: APIRequestContext
let dashboard: SeededDashboard | null = null
const placements: NewsPlacement[] = []

/**
 * Seed a news placement on the shared dashboard and remember it for cleanup.
 *
 * @param content the news config.
 * @return the placement.
 */
async function place(content: Record<string, unknown>): Promise<NewsPlacement> {
	const placement = await seedNewsPlacement(api, dashboard!.id, content, { y: placements.length * 6 })
	placements.push(placement)
	return placement
}

/**
 * Fill the n-th feed URL row of the news form, adding rows as needed.
 *
 * @param dialog the dialog holding the form.
 * @param index the row.
 * @param url the value.
 * @return nothing.
 */
async function fillFeedUrl(dialog: Locator, index: number, url: string): Promise<void> {
	const rows = dialog.getByLabel('Feed URL', { exact: true })
	while ((await rows.count()) <= index) {
		await dialog.getByRole('button', { name: /add feed url/i }).click()
	}
	await rows.nth(index).fill(url)
}

/**
 * Press the modal's Add/Save button and return the placement the server
 * answered with.
 *
 * @param page the page.
 * @param dialog the dialog.
 * @param method POST for a new placement, PUT for an edit.
 * @return the placement id from the response.
 */
async function submit(page: Page, dialog: Locator, method: 'POST' | 'PUT'): Promise<number> {
	const save = dialog.locator('[data-testid="add-widget-save"]')
	await expect(save).toBeEnabled({ timeout: 5_000 })
	const answered = page.waitForResponse((res) => res.request().method() === method
		&& (method === 'POST'
			? res.url().includes(`/api/dashboard/${dashboard!.id}/widgets`)
			: /\/api\/widgets\/\d+(\?|$)/.test(res.url())))
	await save.click()
	const res = await answered
	expect(res.status(), await res.text()).toBeLessThan(300)
	await expect(dialog).not.toBeVisible({ timeout: 10_000 })
	const body = await res.json()
	return Number(body.id ?? body.data?.id ?? body.placement?.id)
}

test.beforeAll(async () => {
	api = await adminApi()
	dashboard = await seedActiveDashboard(api, `E2E News config ${STAMP}`)
})

test.afterEach(async () => {
	// Placements the UI created are not in `placements`; sweep the dashboard.
	for (const p of await readPlacements(api, dashboard!.id)) {
		placements.push({ id: Number(p.id), dashboardId: dashboard!.id })
	}
	await removePlacements(api, placements)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, dashboard)
	await api.dispose()
})

// ── REQ-NEWS-001: registration ────────────────────────────────────────────

// @e2e news-widget::widget-registration-on-bootstrap
test('Nextcloud\'s dashboard registry lists launchpad_news with a title, an icon and API v2 item loading', async () => {
	// IManager::registerWidget() has one outside effect: the widget appears in
	// Nextcloud's own dashboard API, with the versions of the item API it
	// implements (2 for IAPIWidgetV2).
	const res = await api.get('/ocs/v2.php/apps/dashboard/api/v1/widgets?format=json')
	expect(res.status(), await res.text()).toBe(200)
	const widgets = (await res.json()).ocs.data as Record<string, any>
	const news = widgets[NC_WIDGET_ID]
	expect(news, `registered widgets: ${Object.keys(widgets).join(', ')}`).toBeTruthy()
	expect(String(news.title ?? '').trim()).not.toBe('')
	expect(String(news.icon_url ?? '').trim()).not.toBe('')
	expect(news.item_api_versions).toContain(2)
})

// @e2e news-widget::widget-picker-includes-news-widget
test('the widget picker lists the news widget among the discovered widgets, and it can be placed', async ({ page }) => {
	await gotoDashboard(page)
	await page.locator(MORE_MENU).locator('[data-testid="cn-grouped-menu-trigger"]').click()
	await page.locator('[data-testid="cn-grouped-menu-item-add-widget"]').click()
	const picker = page.locator('.widget-picker')
	await expect(picker).toBeVisible({ timeout: 10_000 })

	// The discovered Nextcloud widgets are the picker's top-level entries, not
	// the catalogue, office or server-apps sections.
	const news = picker
		.locator('button.widget-picker__widget:not([data-testid^="widget-picker-"] *)')
		.filter({ has: page.locator('.widget-picker__widget-title', { hasText: /news/i }) })
		.first()
	await expect(news).toBeVisible({ timeout: 10_000 })
	await news.click()

	await expect.poll(async () => (await readPlacements(api, dashboard!.id))
		.filter((p) => p.widgetId === NC_WIDGET_ID).length, { timeout: 15_000 }).toBe(1)
})

// @e2e news-widget::multiple-news-widget-instances
test('the news widget can be added twice, and each placement keeps its own configuration and fetch', async ({ page }) => {
	await gotoDashboard(page)
	const requested = new Set<number>()
	page.on('request', (req) => {
		const match = ITEMS_URL.exec(req.url())
		if (match) {
			requested.add(Number(match[1]))
		}
	})

	const first = await openAddNewsForm(page)
	await fillFeedUrl(first, 0, 'https://first.example/rss')
	const idA = await submit(page, first, 'POST')

	// The picker is opened AGAIN and offers News again: duplicates allowed.
	const second = await openAddNewsForm(page)
	await fillFeedUrl(second, 0, 'https://second.example/rss')
	await chooseFormOption(page, second, 0, 'Grid')
	const idB = await submit(page, second, 'POST')
	expect(idB).not.toBe(idA)

	const all = await readPlacements(api, dashboard!.id)
	const news = all.filter((p) => p.widgetId === 'news')
	expect(news.map((p) => Number(p.id)).sort()).toEqual([idA, idB].sort())

	const a = await readContent(api, { id: idA, dashboardId: dashboard!.id })
	const b = await readContent(api, { id: idB, dashboardId: dashboard!.id })
	expect(a.feedUrls).toEqual(['https://first.example/rss'])
	expect(a.layout).toBe('list')
	expect(b.feedUrls).toEqual(['https://second.example/rss'])
	expect(b.layout).toBe('grid')

	await expect(newsWidget(page, { id: idA, dashboardId: dashboard!.id })).toHaveClass(/news-widget--list/, { timeout: 15_000 })
	await expect(newsWidget(page, { id: idB, dashboardId: dashboard!.id })).toHaveClass(/news-widget--grid/)
	// Each placement fetches its own items.
	await expect.poll(() => requested.has(idA) && requested.has(idB), { timeout: 15_000 }).toBe(true)
})

// @e2e news-widget::widget-metadata-is-discoverable
test('GET /api/widgets includes launchpad_news with a title and an icon URL', async () => {
	const res = await api.get(`${API}/widgets`)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	const list: Array<Record<string, any>> = Array.isArray(body) ? body : Object.values(body.widgets ?? body.data ?? body)
	const news = list.find((w) => w.id === NC_WIDGET_ID)
	expect(news, `ids: ${list.map((w) => w.id).join(', ')}`).toBeTruthy()
	expect(String(news!.title ?? '').trim()).not.toBe('')
	expect(String(news!.icon_url ?? news!.iconUrl ?? '').trim()).not.toBe('')
})

// ── REQ-NEWS-002: per-placement configuration ─────────────────────────────

// @e2e news-widget::configure-feed-urls
test('editing feed URLs refuses ftp:// and file://, and saves HTTP(S) URLs into the placement', async ({ page }) => {
	const placement = await place({ feedUrls: [] })
	await gotoDashboard(page)
	const dialog = await openNewsEditor(page, placement)
	const save = dialog.locator('[data-testid="add-widget-save"]')

	for (const bad of ['ftp://example.com/feed', 'file:///etc/passwd']) {
		await fillFeedUrl(dialog, 0, bad)
		await expect(save, `${bad} must be refused`).toBeDisabled()
		await expect(save).toHaveAttribute('title', /must start with http:\/\/ or https:\/\//i)
	}

	await fillFeedUrl(dialog, 0, 'https://example.com/feed')
	await fillFeedUrl(dialog, 1, 'https://other.org/rss')
	// The editor saves through PUT /api/widgets/{placementId}.
	await submit(page, dialog, 'PUT')

	const content = await readContent(api, placement)
	expect(content.feedUrls).toEqual(['https://example.com/feed', 'https://other.org/rss'])
})

// @e2e news-widget::configure-layout-mode
test('a grid placement renders as cards, and the layout control offers exactly list, grid and carousel', async ({ page }) => {
	const grid = await place({ feedUrls: [], layout: 'grid' })
	await stubNewsItems(page, new Map([[grid.id, {
		items: [newsItem({ guid: 'layout-1' }), newsItem({ guid: 'layout-2' })],
		feedsFailed: 0,
		failedUrls: [],
	}]]))
	await gotoDashboard(page)

	const widget = newsWidget(page, grid)
	await expect(widget.locator('.news-widget__item')).toHaveCount(2, { timeout: 20_000 })
	await expect(widget).toHaveClass(/news-widget--grid/)
	await expect(widget).not.toHaveClass(/news-widget--list/)
	expect(await widget.locator('.news-widget__list').evaluate((el) => getComputedStyle(el).display)).toBe('grid')

	const dialog = await openAddNewsForm(page)
	expect(await formOptionLabels(page, dialog, 0)).toEqual(['List', 'Grid', 'Carousel'])
	await dialog.getByRole('button', { name: /cancel/i }).click()
})

// @e2e news-widget::configure-item-limit
test('the item limit travels to the items request, defaults to 10, and is capped at 50', async ({ page }) => {
	// Slicing the merged items to the limit happens server-side on fetched
	// feeds, which the fixture cannot produce; it is pinned by
	// tests/Unit/Service/NewsWidgetServiceFetchTest.php::testFeedsAreMergedDedupedSortedAndLimited.
	// What is observable here: the configured limit becomes the request's
	// `?limit=` (the request-time value the server obeys), the default, and
	// the 50 ceiling at both the form and the endpoint.
	const fifteen = await place({ feedUrls: [], itemLimit: 15 })
	const unset = await place({ feedUrls: [] })
	const limits = new Map<number, string | null>()
	page.on('request', (req) => {
		const match = ITEMS_URL.exec(req.url())
		if (match) {
			limits.set(Number(match[1]), new URL(req.url()).searchParams.get('limit'))
		}
	})
	await gotoDashboard(page)
	await expect.poll(() => limits.get(fifteen.id), { timeout: 20_000 }).toBe('15')
	await expect.poll(() => limits.get(unset.id), { timeout: 20_000 }).toBe('10')

	expect((await fetchItems(api, fifteen, 50)).status).toBe(200)
	expect((await fetchItems(api, fifteen, 51)).status).toBe(400)

	const dialog = await openAddNewsForm(page)
	await dialog.getByLabel(/item limit/i).fill('80')
	const id = await submit(page, dialog, 'POST')
	const content = await readContent(api, { id, dashboardId: dashboard!.id })
	expect(content.itemLimit).toBe(50)
})

// @e2e news-widget::optional-metadata-filtering-configuration
test('a metadata filter set in the form is stored, and a dashboard that does not match gets no fetch', async ({ page }) => {
	await gotoDashboard(page)
	const url = failingFeed('formfilter')
	const dialog = await openAddNewsForm(page)
	await fillFeedUrl(dialog, 0, url)
	await dialog.getByText('Filter by dashboard metadata').click()
	await dialog.getByLabel('Metadata field key').fill('department')
	await dialog.getByLabel('Metadata value to match').fill('marketing')
	const id = await submit(page, dialog, 'POST')
	const placement = { id, dashboardId: dashboard!.id }

	const content = await readContent(api, placement)
	expect(content.metadataFilter).toEqual({ fieldKey: 'department', value: 'marketing' })
	expect(content.feedUrls).toEqual([url])

	// This fresh dashboard has no `department`, so the filter does not match:
	// no items, and the failing feed was never tried (see REQ-NEWS-008).
	const res = await fetchItems(api, placement, 10)
	expect(res.status, JSON.stringify(res.body)).toBe(200)
	expect(res.body.items).toEqual([])
	expect(res.body.feedsFailed).toBe(0)
	await expect(newsWidget(page, placement).locator('.news-widget__state--empty')).toBeVisible({ timeout: 20_000 })
	await expect(newsWidget(page, placement).locator('.news-widget__badge')).toHaveCount(0)
})

// ── REQ-NEWS-009: layout switching ────────────────────────────────────────

// @e2e news-widget::layout-switching
test('switching list to grid in the editor re-renders immediately without fetching the items again', async ({ page }) => {
	const placement = await place({ feedUrls: [], layout: 'list' })
	let fetches = 0
	await stubNewsItems(page, new Map([[placement.id, async (route) => {
		fetches++
		await route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify({ items: [newsItem({ guid: 'switch-1' }), newsItem({ guid: 'switch-2' })], feedsFailed: 0, failedUrls: [] }),
		})
	}]]))
	await gotoDashboard(page)
	const widget = newsWidget(page, placement)
	await expect(widget.locator('.news-widget__item')).toHaveCount(2, { timeout: 20_000 })
	await expect(widget).toHaveClass(/news-widget--list/)
	const before = fetches

	const dialog = await openNewsEditor(page, placement)
	await chooseFormOption(page, dialog, 0, 'Grid')
	await submit(page, dialog, 'PUT')

	await expect(widget).toHaveClass(/news-widget--grid/, { timeout: 5_000 })
	await expect(widget.locator('.news-widget__item')).toHaveCount(2)
	expect(fetches, 'a layout change must not re-fetch the items').toBe(before)
	expect((await readContent(api, placement)).layout).toBe('grid')
})
