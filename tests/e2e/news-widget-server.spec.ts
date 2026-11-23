/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the news widget against the REAL items
 * endpoint (openspec/specs/news-widget), written for e2e-coverage-programme.
 *
 * Nothing here stubs the server. Feeds the fixture can name either do not
 * exist (no feeds configured) or fail for certain (a `.invalid` host never
 * resolves; see tests/e2e/support/newsWidget.ts). A failed feed is therefore
 * the PROOF that the server tried to fetch: `feedsFailed: 0` with a failing
 * URL configured means no fetch happened, `feedsFailed: 1` means one did.
 * That is what lets the metadata-filter scenarios be observed end to end.
 *
 * The admin seeds one active dashboard; each test adds its own placements
 * (and metadata fields) and removes them afterwards.
 *
 * Gate-19 @e2e traceability:
 *   @e2e news-widget::limit-parameter-validation
 *   @e2e news-widget::missing-or-empty-feed-list
 *   @e2e news-widget::empty-state-when-no-feeds-configured
 *   @e2e news-widget::empty-state-when-no-items-found
 *   @e2e news-widget::all-feeds-fail
 *   @e2e news-widget::widget-with-no-metadata-filter-bypass-check
 *   @e2e news-widget::widget-with-metadata-filter-match-succeeds
 *   @e2e news-widget::widget-with-metadata-filter-no-match
 *   @e2e news-widget::metadata-field-missing-or-null
 *   @e2e news-widget::loading-state
 *
 * @spec openspec/specs/news-widget/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { NewsPlacement } from './support/newsWidget.ts'

import { expect, test } from '@playwright/test'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'
import {
	adminApi,
	createMetadataField,
	failingFeed,
	fetchItems,
	gotoDashboard,
	ITEMS_URL,
	newsWidget,
	removeMetadataField,
	removePlacements,
	seedNewsPlacement,
	setDashboardMetadata,
} from './support/newsWidget.ts'

const STAMP = `${Date.now()}`
const EMPTY_MESSAGE = 'No news yet — try adding feeds in the widget settings'

let api: APIRequestContext
let dashboard: SeededDashboard | null = null
const placements: NewsPlacement[] = []
let fieldId: number | null = null

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
 * A fresh text metadata field, removed after the test.
 *
 * @return its key.
 */
async function metadataField(): Promise<string> {
	const key = `e2e_news_dept_${STAMP}_${Math.floor(Math.random() * 10_000)}`
	fieldId = await createMetadataField(api, key)
	return key
}

test.beforeAll(async () => {
	api = await adminApi()
	dashboard = await seedActiveDashboard(api, `E2E News server ${STAMP}`)
})

test.afterEach(async () => {
	await removePlacements(api, placements)
	await removeMetadataField(api, fieldId)
	fieldId = null
})

test.afterAll(async () => {
	await removeSeededDashboard(api, dashboard)
	await api.dispose()
})

// @e2e news-widget::limit-parameter-validation
test('the items endpoint refuses a limit above 50 or below 1 with 400, and defaults it to 10', async () => {
	const placement = await place({ feedUrls: [] })

	for (const bad of [100, 51, 0, -1]) {
		const res = await fetchItems(api, placement, bad)
		expect(res.status, `limit=${bad}: ${JSON.stringify(res.body)}`).toBe(400)
	}
	for (const good of [1, 50]) {
		const res = await fetchItems(api, placement, good)
		expect(res.status, `limit=${good}: ${JSON.stringify(res.body)}`).toBe(200)
	}

	// Optional: no `?limit=` at all is a 200, not a refusal.
	const none = await fetchItems(api, placement)
	expect(none.status, JSON.stringify(none.body)).toBe(200)
	expect(Array.isArray(none.body.items)).toBe(true)
})

// @e2e news-widget::missing-or-empty-feed-list
test('a placement with no feeds answers 200 with no items and renders the empty state', async ({ page }) => {
	const placement = await place({ feedUrls: [] })

	const res = await fetchItems(api, placement, 10)
	expect(res.status, JSON.stringify(res.body)).toBe(200)
	expect(res.body.items).toEqual([])
	expect(res.body.feedsFailed).toBe(0)

	await gotoDashboard(page)
	const widget = newsWidget(page, placement)
	await expect(widget.locator('.news-widget__state--empty')).toHaveText(EMPTY_MESSAGE, { timeout: 20_000 })
	await expect(widget.locator('.news-widget__item')).toHaveCount(0)
})

// @e2e news-widget::empty-state-when-no-feeds-configured
test('the no-feeds empty state carries the message and an icon', async ({ page }) => {
	const placement = await place({ feedUrls: [] })

	await gotoDashboard(page)
	const empty = newsWidget(page, placement).locator('.news-widget__state--empty')
	await expect(empty).toContainText(EMPTY_MESSAGE, { timeout: 20_000 })
	// The scenario requires an icon (RSS or newspaper) beside the message. The
	// call-to-action is an implementation choice and is not asserted.
	await expect(empty.locator('svg, img, [class*="icon"]').first()).toBeVisible()
})

// @e2e news-widget::empty-state-when-no-items-found
test('feeds configured but every item filtered out shows the same empty state and no failure badge', async ({ page }) => {
	// "all are filtered": a metadata filter that does not match yields no items
	// and, because it short-circuits before any fetch, no failed feeds either.
	const key = await metadataField()
	const placement = await place({
		feedUrls: [failingFeed('filtered')],
		metadataFilter: { fieldKey: key, value: 'marketing' },
	})

	const res = await fetchItems(api, placement, 10)
	expect(res.status, JSON.stringify(res.body)).toBe(200)
	expect(res.body.items).toEqual([])
	expect(res.body.feedsFailed).toBe(0)

	await gotoDashboard(page)
	const widget = newsWidget(page, placement)
	await expect(widget.locator('.news-widget__state--empty')).toHaveText(EMPTY_MESSAGE, { timeout: 20_000 })
	await expect(widget.locator('.news-widget__badge')).toHaveCount(0)
})

// @e2e news-widget::all-feeds-fail
test('when every feed fails the endpoint answers 200 with no items and the widget shows the empty state plus "All N feeds failed"', async ({ page }) => {
	const urls = [failingFeed('one'), failingFeed('two'), failingFeed('three')]
	const placement = await place({ feedUrls: urls })

	const res = await fetchItems(api, placement, 10)
	expect(res.status, JSON.stringify(res.body)).toBe(200)
	expect(res.body.items).toEqual([])
	expect(res.body.feedsFailed).toBe(3)
	expect([...res.body.failedUrls].sort()).toEqual([...urls].sort())

	await gotoDashboard(page)
	const widget = newsWidget(page, placement)
	await expect(widget.locator('.news-widget__state--empty')).toHaveText(EMPTY_MESSAGE, { timeout: 30_000 })
	await expect(widget.locator('.news-widget__badge')).toHaveText(/All 3 feeds failed/i)
})

// @e2e news-widget::widget-with-no-metadata-filter-bypass-check
test('without a metadata filter the server goes ahead and fetches the feeds', async () => {
	const url = failingFeed('nofilter')
	const placement = await place({ feedUrls: [url] })

	// The fetch happened: the one feed is reported as failed. Whether a
	// metadata lookup ran has no outside surface; the filter's absence is what
	// lets the fetch go ahead, which is what is observable.
	const res = await fetchItems(api, placement, 10)
	expect(res.status, JSON.stringify(res.body)).toBe(200)
	expect(res.body.feedsFailed).toBe(1)
	expect(res.body.failedUrls).toEqual([url])
})

// @e2e news-widget::widget-with-metadata-filter-match-succeeds
test('a metadata filter that matches the dashboard lets the server fetch the feeds', async () => {
	const key = await metadataField()
	await setDashboardMetadata(api, dashboard!.uuid, { [key]: 'marketing' })
	const url = failingFeed('match')
	const placement = await place({
		feedUrls: [url],
		metadataFilter: { fieldKey: key, value: 'marketing' },
	})

	// The check passed, so the feed was fetched "normally": with the fixture's
	// failing feed, normal means one feed reported as failed. A filter that
	// wrongly refused would answer feedsFailed 0 without trying.
	const res = await fetchItems(api, placement, 10)
	expect(res.status, JSON.stringify(res.body)).toBe(200)
	expect(Array.isArray(res.body.items)).toBe(true)
	expect(res.body.feedsFailed, JSON.stringify(res.body)).toBe(1)
	expect(res.body.failedUrls).toEqual([url])
})

// @e2e news-widget::widget-with-metadata-filter-no-match
test('a metadata filter that does not match answers 200 with no items and fetches nothing', async () => {
	const key = await metadataField()
	await setDashboardMetadata(api, dashboard!.uuid, { [key]: 'engineering' })
	const placement = await place({
		feedUrls: [failingFeed('nomatch')],
		metadataFilter: { fieldKey: key, value: 'marketing' },
	})

	const res = await fetchItems(api, placement, 10)
	expect(res.status, JSON.stringify(res.body)).toBe(200)
	expect(res.body.items).toEqual([])
	// No fetch occurred: the failing feed was never tried.
	expect(res.body.feedsFailed).toBe(0)
	expect(res.body.failedUrls).toEqual([])
})

// @e2e news-widget::metadata-field-missing-or-null
test('a filter on a metadata field the dashboard has no value for fails and returns no items', async () => {
	// The field exists in the registry, but this dashboard never set it.
	const key = await metadataField()
	const placement = await place({
		feedUrls: [failingFeed('missing')],
		metadataFilter: { fieldKey: key, value: 'marketing' },
	})

	const res = await fetchItems(api, placement, 10)
	expect(res.status, JSON.stringify(res.body)).toBe(200)
	expect(res.body.items).toEqual([])
	expect(res.body.feedsFailed).toBe(0)

	// And a key no field definition carries at all behaves the same.
	const unknown = await place({
		feedUrls: [failingFeed('unknown')],
		metadataFilter: { fieldKey: `${key}_nonexistent`, value: 'marketing' },
	})
	const res2 = await fetchItems(api, unknown, 10)
	expect(res2.status, JSON.stringify(res2.body)).toBe(200)
	expect(res2.body.items).toEqual([])
	expect(res2.body.feedsFailed).toBe(0)
})

// @e2e news-widget::loading-state
test('while items load the widget shows a loading state and the page stays usable', async ({ page }) => {
	const placement = await place({ feedUrls: [] })

	// Hold the REAL request back until the assertions below are done, then let
	// it through to the server unchanged.
	let release: () => void = () => {}
	const held = new Promise<void>((resolve) => {
		release = resolve
	})
	await page.route(ITEMS_URL, async (route) => {
		const match = ITEMS_URL.exec(route.request().url())
		if (match && Number(match[1]) === placement.id) {
			await held
		}
		await route.continue()
	})

	await gotoDashboard(page)
	const widget = newsWidget(page, placement)
	await expect(widget.locator('.news-widget__state--loading')).toBeVisible({ timeout: 20_000 })

	// Not blocking: the More menu opens while the fetch is still in flight.
	await page.locator(MORE_MENU).locator('[data-testid="launchpad-more-menu-trigger"]').click()
	await expect(page.locator('[data-testid="launchpad-more-menu-item-all-dashboards"]')).toBeVisible()
	await expect(widget.locator('.news-widget__state--loading')).toBeVisible()
	await page.keyboard.press('Escape')

	release()
	await expect(widget.locator('.news-widget__state--loading')).toHaveCount(0, { timeout: 20_000 })
	await expect(widget.locator('.news-widget__state--empty')).toBeVisible()
})
