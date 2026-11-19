/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for how the news widget RENDERS a response
 * (openspec/specs/news-widget), written for e2e-coverage-programme.
 *
 * WHY THE ITEMS ENDPOINT IS STUBBED HERE, AND ONLY HERE
 * ====================================================
 * The CI fixture cannot make the server return items: every feed must
 * resolve to a public address, and the fixture has no public feed (see
 * tests/e2e/support/newsWidget.ts). Every scenario in this file is about what
 * the widget does WITH a response, so `page.route` hands the renderer the
 * JSON the endpoint documents, exactly as `NewsWidgetService` shapes it:
 * `{items, feedsFailed, failedUrls}`. The placement, its stored content, the
 * dashboard grid, the renderer, its DOMPurify pass and the browser are real.
 *
 * For the summary-sanitisation scenarios this exercises the renderer's own
 * pass (`truncateSummaryHtml`), which the summaries go through on every
 * render. The server's first pass over feed content is pinned separately by
 * tests/Unit/Service/NewsWidgetServiceTest.php (testSanitiseSummary*).
 *
 * Item links are a different matter: NEITHER layer touches them. The server
 * copies `<link>` verbatim and the renderer binds it to `href` as is, so the
 * stub returns exactly what the server would hand on.
 *
 * Gate-19 @e2e traceability:
 *   @e2e news-widget::configure-thumbnail-and-summary-display
 *   @e2e news-widget::configure-date-format
 *   @e2e news-widget::allow-whitelisted-tags
 *   @e2e news-widget::strip-dangerous-tags
 *   @e2e news-widget::enforce-rel-attributes-on-links
 *   @e2e news-widget::allowed-tag-whitelist
 *   @e2e news-widget::xss-prevention-in-summary-content
 *   @e2e news-widget::single-feed-fails-others-succeed
 *   @e2e news-widget::list-layout-default
 *   @e2e news-widget::grid-layout
 *   @e2e news-widget::carousel-layout
 *   @e2e news-widget::responsive-layout
 *   @e2e news-widget::failure-badge-single-failure
 *   @e2e news-widget::failure-badge-multiple-failures
 *   @e2e news-widget::fetch-error-message
 *   @e2e news-widget::click-on-item-title-or-link
 *   @e2e news-widget::link-security-noopener
 *   @e2e news-widget::link-security-noreferrer
 *   @e2e news-widget::missing-link-in-feed-item
 *   @e2e news-widget::malformed-or-unsafe-link
 *
 * @spec openspec/specs/news-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { NewsItem, NewsPayload, NewsPlacement } from './support/newsWidget.ts'

import { expect, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import {
	adminApi,
	gotoDashboard,
	newsItem,
	newsWidget,
	removePlacements,
	seedNewsPlacement,
	stubNewsItems,
} from './support/newsWidget.ts'

const STAMP = `${Date.now()}`
// Same-origin, so a thumbnail renders without outbound internet.
const THUMB = `${BASE_URL}/core/img/logo/logo.svg`
const LONG_TEXT = 'Gemeenteraad bespreekt het nieuwe parkeerbeleid voor de binnenstad. '.repeat(8)

let api: APIRequestContext
let dashboard: SeededDashboard | null = null
const placements: NewsPlacement[] = []

/**
 * Seed a news placement on the shared dashboard and remember it for cleanup.
 *
 * @param content the news config.
 * @param grid optional grid size.
 * @param grid.w width in grid columns.
 * @param grid.h height in grid rows.
 * @return the placement.
 */
async function place(content: Record<string, unknown>, grid: { w?: number; h?: number } = {}): Promise<NewsPlacement> {
	const placement = await seedNewsPlacement(api, dashboard!.id, content, { y: placements.length * 8, ...grid })
	placements.push(placement)
	return placement
}

/**
 * A response carrying these items and no failures.
 *
 * @param items the items.
 * @return the payload.
 */
function ok(items: NewsItem[]): NewsPayload {
	return { items, feedsFailed: 0, failedUrls: [] }
}

/**
 * Seed one placement, stub its response, open the dashboard and wait for its
 * first item.
 *
 * @param page the page.
 * @param content the news config.
 * @param payload the response to render.
 * @param grid optional grid size.
 * @param grid.w width in grid columns.
 * @param grid.h height in grid rows.
 * @return the placement and its widget.
 */
async function render(
	page: Page,
	content: Record<string, unknown>,
	payload: NewsPayload,
	grid: { w?: number; h?: number } = {},
): Promise<{ placement: NewsPlacement; widget: Locator }> {
	const placement = await place(content, grid)
	await stubNewsItems(page, new Map([[placement.id, payload]]))
	await gotoDashboard(page)
	const widget = newsWidget(page, placement)
	if (payload.items.length > 0) {
		await expect(widget.locator('.news-widget__item').first()).toBeVisible({ timeout: 20_000 })
	} else {
		await expect(widget).toBeVisible({ timeout: 20_000 })
	}
	return { placement, widget }
}

/**
 * Serve a tiny HTML page for every request to the fake article host, and
 * record each request's headers.
 *
 * @param page the page whose context the route belongs to.
 * @return the recorded requests.
 */
async function serveArticles(page: Page): Promise<Array<{ url: string; headers: Record<string, string> }>> {
	const seen: Array<{ url: string; headers: Record<string, string> }> = []
	await page.context().route('https://news.e2e.test/**', async (route) => {
		seen.push({ url: route.request().url(), headers: route.request().headers() })
		await route.fulfill({ status: 200, contentType: 'text/html', body: '<!doctype html><title>article</title><p>article</p>' })
	})
	return seen
}

/**
 * The number of tracks in a computed `grid-template-columns`.
 *
 * @param list the list element.
 * @return the column count.
 */
async function gridColumns(list: Locator): Promise<number> {
	const template = await list.evaluate((el) => getComputedStyle(el).gridTemplateColumns)
	return template.trim().split(/\s+/).filter((t) => t !== '' && t !== 'none').length
}

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	api = await adminApi()
	dashboard = await seedActiveDashboard(api, `E2E News rendering ${STAMP}`)
})

test.afterEach(async () => {
	await removePlacements(api, placements)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, dashboard)
	await api.dispose()
})

// ── REQ-NEWS-002: presentation config ─────────────────────────────────────

// @e2e news-widget::configure-thumbnail-and-summary-display
test('showThumbnails false hides thumbnails, summaries truncate to summaryMaxChars, and the defaults are on/on/200', async ({ page }) => {
	const items = [newsItem({ guid: 'thumbs-1', thumbnailUrl: THUMB, summary: LONG_TEXT })]
	const configured = await place({ feedUrls: [], showThumbnails: false, showSummary: true, summaryMaxChars: 150 })
	// No presentation keys at all: the defaults apply.
	const defaults = await place({ feedUrls: [] })
	await stubNewsItems(page, new Map([[configured.id, ok(items)], [defaults.id, ok(items)]]))
	await gotoDashboard(page)

	const a = newsWidget(page, configured)
	await expect(a.locator('.news-widget__item')).toHaveCount(1, { timeout: 20_000 })
	await expect(a.locator('img')).toHaveCount(0)
	const summaryA = a.locator('.news-widget__summary')
	await expect(summaryA).toBeVisible()
	const textA = (await summaryA.innerText()).trim().replace(/…$/, '').trim()
	expect(textA).toBe(LONG_TEXT.slice(0, 150).trim())

	const b = newsWidget(page, defaults)
	await expect(b.locator('.news-widget__item')).toHaveCount(1, { timeout: 20_000 })
	await expect(b.locator('img.news-widget__thumb')).toBeVisible()
	const summaryB = b.locator('.news-widget__summary')
	await expect(summaryB).toBeVisible()
	const textB = (await summaryB.innerText()).trim().replace(/…$/, '').trim()
	expect(textB).toBe(LONG_TEXT.slice(0, 200).trim())
})

// @e2e news-widget::configure-date-format
test('relative dates read "2 hours ago" / "1 day ago"; absolute dates read as a date and time', async ({ page }) => {
	const now = Date.now()
	const relativeItems = [
		newsItem({ guid: 'rel-hours', pubDate: new Date(now - (2 * 3600_000 + 5 * 60_000)).toISOString() }),
		newsItem({ guid: 'rel-day', pubDate: new Date(now - 26 * 3600_000).toISOString() }),
	]
	const absoluteItems = [newsItem({ guid: 'abs', pubDate: '2026-05-01T14:30:00Z' })]
	const relative = await place({ feedUrls: [], dateFormat: 'relative' })
	const absolute = await place({ feedUrls: [], dateFormat: 'absolute' })
	await stubNewsItems(page, new Map([[relative.id, ok(relativeItems)], [absolute.id, ok(absoluteItems)]]))
	await gotoDashboard(page)

	const rel = newsWidget(page, relative).locator('.news-widget__date')
	await expect(rel).toHaveCount(2, { timeout: 20_000 })
	await expect(rel.nth(0)).toHaveText('2 hours ago')
	await expect(rel.nth(1)).toHaveText('1 day ago')

	const abs = newsWidget(page, absolute).locator('.news-widget__date')
	await expect(abs).toHaveCount(1, { timeout: 20_000 })
	// ISO 8601 ("2026-05-01 14:30" / "2026-05-01T14:30") or human-readable
	// ("May 1, 2026 14:30") both satisfy the scenario.
	await expect(abs).toHaveText(/2026-05-01[ T]14:30|May 1, 2026,? 14:30/)
})

// ── REQ-NEWS-005: summary sanitisation, as rendered ───────────────────────

// @e2e news-widget::allow-whitelisted-tags
test('p, strong and a survive in a rendered summary with their formatting', async ({ page }) => {
	const summary = '<p>Read our <strong>latest</strong> post <a href="https://example.com">here</a></p>'
	const { widget } = await render(page, { feedUrls: [], summaryMaxChars: 500 }, ok([newsItem({ guid: 'allow', link: '', summary })]))

	const rendered = widget.locator('.news-widget__summary')
	await expect(rendered).toContainText('Read our latest post here')
	await expect(rendered.locator('p')).toHaveCount(1)
	await expect(rendered.locator('strong')).toHaveText('latest')
	expect(await rendered.locator('strong').evaluate((el) => Number(getComputedStyle(el).fontWeight))).toBeGreaterThanOrEqual(600)
	await expect(rendered.locator('a')).toHaveText('here')
	await expect(rendered.locator('a')).toHaveAttribute('href', 'https://example.com')
})

// @e2e news-widget::strip-dangerous-tags
test('script, iframe, svg and img onerror are removed from a rendered summary', async ({ page }) => {
	await page.addInitScript(() => {
		(window as any).__newsXss = []
	})
	const summary = 'Safe <script>window.__newsXss.push("script")</script>text'
		+ '<iframe src="about:blank"></iframe>'
		+ '<svg onload="window.__newsXss.push(\'svg\')"><circle r="4"></circle></svg>'
		+ '<img src="x" onerror="window.__newsXss.push(\'img\')"> after'
	const { widget } = await render(page, { feedUrls: [], summaryMaxChars: 500 }, ok([newsItem({ guid: 'strip', link: '', summary })]))

	const rendered = widget.locator('.news-widget__summary')
	await expect(rendered).toContainText('Safe')
	await expect(rendered).toContainText('after')
	for (const tag of ['script', 'iframe', 'svg', 'img']) {
		await expect(rendered.locator(tag), `<${tag}> must be removed`).toHaveCount(0)
	}
	expect(await page.evaluate(() => (window as any).__newsXss)).toEqual([])
})

// @e2e news-widget::enforce-rel-attributes-on-links
test('every link in a rendered summary carries rel="noopener noreferrer"', async ({ page }) => {
	const summary = '<a href="https://external.com">Link</a> and <a href="https://other.example" rel="opener">Other</a>'
	const { widget } = await render(page, { feedUrls: [], summaryMaxChars: 500 }, ok([newsItem({ guid: 'rel', link: '', summary })]))

	const anchors = widget.locator('.news-widget__summary a')
	await expect(anchors).toHaveCount(2)
	for (const anchor of await anchors.all()) {
		const rel = ((await anchor.getAttribute('rel')) ?? '').split(/\s+/)
		expect(rel).toContain('noopener')
		expect(rel).toContain('noreferrer')
		expect(rel).not.toContain('opener')
	}
	await expect(anchors.first()).toHaveAttribute('href', 'https://external.com')
	await expect(anchors.first()).toHaveText('Link')
})

// @e2e news-widget::allowed-tag-whitelist
test('only p, a, strong, em, br, ul, ol and li survive, and only safe attributes on them', async ({ page }) => {
	const summary = '<p>Intro <em>emph</em><br>line</p>'
		+ '<ul><li>one</li></ul><ol><li>two</li></ol>'
		+ '<strong data-track="1">bold</strong>'
		+ '<span data-x="y">span</span><div class="box">div</div><h1>heading</h1><table><tr><td>cell</td></tr></table>'
		+ '<a href="https://ok.example" onclick="window.__newsClicked = 1" data-id="7" style="color:red">ok</a>'
	const { widget } = await render(page, { feedUrls: [], summaryMaxChars: 1000 }, ok([newsItem({ guid: 'whitelist', link: '', summary })]))

	const rendered = widget.locator('.news-widget__summary')
	for (const tag of ['p', 'a', 'strong', 'em', 'br', 'ul', 'ol', 'li']) {
		expect(await rendered.locator(tag).count(), `<${tag}> is allowed`).toBeGreaterThan(0)
	}
	for (const tag of ['span', 'div', 'h1', 'table', 'td']) {
		await expect(rendered.locator(tag), `<${tag}> must be stripped`).toHaveCount(0)
	}
	const attributes = await rendered.evaluate((root) => Array.from(root.querySelectorAll('*'))
		.flatMap((el) => Array.from(el.attributes).map((a) => `${el.tagName.toLowerCase()}[${a.name}]`)))
	for (const attr of attributes) {
		expect(attr, 'no data-, event or style attribute may survive').not.toMatch(/\[(data-|on|style)/)
	}
	await expect(rendered.locator('a')).toHaveAttribute('href', 'https://ok.example')
})

// @e2e news-widget::xss-prevention-in-summary-content
test('a javascript: href in a summary is stripped or replaced with "#" and the link text stays', async ({ page }) => {
	const summary = '<p>Click here: <a href="javascript:void(0)">x</a> and <a href=" JaVaScRiPt:alert(1)">y</a></p>'
	const { widget } = await render(page, { feedUrls: [], summaryMaxChars: 500 }, ok([newsItem({ guid: 'xss', link: '', summary })]))

	const anchors = widget.locator('.news-widget__summary a')
	await expect(anchors).toHaveCount(2)
	await expect(anchors.nth(0)).toHaveText('x')
	await expect(anchors.nth(1)).toHaveText('y')
	for (const anchor of await anchors.all()) {
		const href = await anchor.getAttribute('href')
		expect(href === null || href === '#', `href ${href} must be gone or "#"`).toBe(true)
	}
})

// ── REQ-NEWS-008 / REQ-NEWS-010: failures and messaging ───────────────────

// @e2e news-widget::single-feed-fails-others-succeed
test('items of the feeds that worked render, with a "1 feed failed" badge naming the failed host', async ({ page }) => {
	// The response the server produces for good.com + bad.com + good2.com when
	// bad.com fails (the fetch itself: NewsWidgetServiceFetchTest).
	const payload: NewsPayload = {
		items: [
			newsItem({ guid: 'good-1', title: 'From good', sourceUrl: 'https://good.com/rss', sourceTitle: 'good.com' }),
			newsItem({ guid: 'good2-1', title: 'From good2', sourceUrl: 'https://good2.com/rss', sourceTitle: 'good2.com' }),
		],
		feedsFailed: 1,
		failedUrls: ['https://bad.com/rss'],
	}
	const { widget } = await render(page, { feedUrls: ['https://good.com/rss', 'https://bad.com/rss', 'https://good2.com/rss'] }, payload)

	await expect(widget.locator('.news-widget__item')).toHaveCount(2)
	await expect(widget.locator('.news-widget__title').nth(0)).toHaveText('From good')
	await expect(widget.locator('.news-widget__title').nth(1)).toHaveText('From good2')
	const badge = widget.locator('.news-widget__badge')
	await expect(badge).toHaveText('1 feed failed')
	await badge.hover()
	await expect(badge).toHaveAttribute('title', /bad\.com/)
	await expect(badge).not.toHaveAttribute('title', /good\.com|good2\.com/)

	// Top-right corner of the widget.
	const w = (await widget.boundingBox())!
	const b = (await badge.boundingBox())!
	expect(w.x + w.width - (b.x + b.width)).toBeLessThan(24)
	expect(b.y - w.y).toBeLessThan(24)
})

// @e2e news-widget::failure-badge-single-failure
test('one of three feeds failed: a small top-right badge reads "1 feed failed" and its tooltip names it', async ({ page }) => {
	const payload: NewsPayload = {
		items: [newsItem({ guid: 'single-a' }), newsItem({ guid: 'single-b' })],
		feedsFailed: 1,
		failedUrls: ['https://bad.com/rss'],
	}
	const { widget } = await render(page, { feedUrls: ['https://a.example/rss', 'https://bad.com/rss', 'https://b.example/rss'] }, payload)

	const badge = widget.locator('.news-widget__badge')
	await expect(badge).toHaveText('1 feed failed')
	await badge.hover()
	await expect(badge).toHaveAttribute('title', /^Failed: .*bad\.com/)
	const w = (await widget.boundingBox())!
	const b = (await badge.boundingBox())!
	expect(b.x + b.width / 2).toBeGreaterThan(w.x + w.width / 2)
	expect(b.y + b.height / 2).toBeLessThan(w.y + w.height / 2)
	expect(b.height).toBeLessThan(40)
})

// @e2e news-widget::failure-badge-multiple-failures
test('three of five feeds failed: the badge reads "3 feeds failed" and the tooltip lists every failed URL', async ({ page }) => {
	const failed = ['https://bad-1.example/rss', 'https://bad-2.example/rss', 'https://bad-3.example/rss']
	const payload: NewsPayload = {
		items: [newsItem({ guid: 'multi-a' }), newsItem({ guid: 'multi-b' })],
		feedsFailed: 3,
		failedUrls: failed,
	}
	const { widget } = await render(page, { feedUrls: [...failed, 'https://ok-1.example/rss', 'https://ok-2.example/rss'] }, payload)

	const badge = widget.locator('.news-widget__badge')
	await expect(badge).toHaveText('3 feeds failed')
	const title = (await badge.getAttribute('title')) ?? ''
	for (const url of failed) {
		expect(title).toContain(url)
	}
})

// @e2e news-widget::fetch-error-message
test('an unexpected server error shows a friendly message and no technical detail', async ({ page }) => {
	const placement = await place({ feedUrls: [] })
	const technical = 'SQLSTATE[08006] connection to server at "db" lost in /var/www/html/lib/Db/Connection.php:42'
	await stubNewsItems(page, new Map([[placement.id, async (route) => {
		await route.fulfill({ status: 500, contentType: 'application/json', body: JSON.stringify({ message: technical }) })
	}]]))
	await gotoDashboard(page)

	// The server-side log line has no browser surface; the user-facing half is
	// what this asserts.
	const widget = newsWidget(page, placement)
	await expect(widget.locator('.news-widget__state--error')).toHaveText('Unable to load news. Please try again later.', { timeout: 20_000 })
	await expect(widget).not.toContainText('SQLSTATE')
	await expect(widget).not.toContainText('Connection.php')
	await expect(widget).not.toContainText('500')
})

// ── REQ-NEWS-009: layouts ─────────────────────────────────────────────────

// @e2e news-widget::list-layout-default
test('the default layout is a single-column list whose items show thumbnail, title, summary, link, date and source', async ({ page }) => {
	const items = [
		newsItem({ guid: 'list-1', title: 'First story', thumbnailUrl: THUMB, sourceTitle: 'BBC News' }),
		newsItem({ guid: 'list-2', title: 'Second story', thumbnailUrl: THUMB, sourceTitle: 'BBC News' }),
	]
	const { widget } = await render(page, { feedUrls: [] }, ok(items))

	await expect(widget).toHaveClass(/news-widget--list/)
	const list = widget.locator('.news-widget__list')
	expect(await list.evaluate((el) => {
		const s = getComputedStyle(el)
		return s.display === 'flex' ? s.flexDirection : s.display
	})).toBe('column')
	const [one, two] = await widget.locator('.news-widget__item').all()
	const b1 = (await one.boundingBox())!
	const b2 = (await two.boundingBox())!
	expect(Math.abs(b1.x - b2.x)).toBeLessThan(2)
	expect(b2.y).toBeGreaterThanOrEqual(b1.y + b1.height - 1)

	const first = widget.locator('.news-widget__item').first()
	await expect(first.locator('img.news-widget__thumb')).toBeVisible()
	await expect(first.locator('.news-widget__title')).toHaveText('First story')
	expect(Number(await first.locator('.news-widget__title').evaluate((el) => getComputedStyle(el).fontWeight))).toBeGreaterThanOrEqual(600)
	await expect(first.locator('.news-widget__summary')).toHaveText('Summary of list-1')
	await expect(first.locator('a.news-widget__item-link')).toHaveAttribute('href', 'https://news.e2e.test/list-1')
	await expect(first.locator('.news-widget__date')).toHaveText('3 hours ago')
	await expect(first.locator('.news-widget__source')).toContainText('BBC News')
})

// @e2e news-widget::grid-layout
test('grid layout shows bordered, shadowed cards in 2 to 4 columns, thumbnail on top', async ({ page }) => {
	const items = [1, 2, 3, 4, 5, 6].map((n) => newsItem({ guid: `grid-${n}`, thumbnailUrl: THUMB, sourceTitle: 'Grid Source' }))
	const { widget } = await render(page, { feedUrls: [], layout: 'grid' }, ok(items), { w: 6, h: 8 })

	await expect(widget).toHaveClass(/news-widget--grid/)
	const list = widget.locator('.news-widget__list')
	expect(await list.evaluate((el) => getComputedStyle(el).display)).toBe('grid')
	const columns = await gridColumns(list)
	expect(columns).toBeGreaterThanOrEqual(2)
	expect(columns).toBeLessThanOrEqual(4)

	const card = widget.locator('.news-widget__item').first()
	const thumb = card.locator('img.news-widget__thumb')
	const title = card.locator('.news-widget__title')
	await expect(thumb).toBeVisible()
	expect((await thumb.boundingBox())!.y).toBeLessThan((await title.boundingBox())!.y)
	await expect(title).toBeVisible()
	await expect(card.locator('.news-widget__summary')).toBeVisible()
	await expect(card.locator('a')).toHaveAttribute('href', 'https://news.e2e.test/grid-1')
	await expect(card.locator('.news-widget__date')).not.toBeEmpty()
	await expect(card.locator('.news-widget__source')).toHaveText('Grid Source')

	// "card styling MUST use border and shadow for visual separation".
	const style = await card.evaluate((li) => {
		const has = (el: Element) => {
			const s = getComputedStyle(el)
			return { border: s.borderTopStyle !== 'none' && parseFloat(s.borderTopWidth) > 0, shadow: s.boxShadow !== 'none' }
		}
		const parts = [li, ...Array.from(li.children)].map(has)
		return { border: parts.some((p) => p.border), shadow: parts.some((p) => p.shadow) }
	})
	expect(style.border, 'grid cards need a border').toBe(true)
	expect(style.shadow, 'grid cards need a shadow').toBe(true)
})

// @e2e news-widget::carousel-layout
test('carousel layout shows one item at a time with previous/next arrows that browse the items', async ({ page }) => {
	const items = [1, 2, 3].map((n) => newsItem({ guid: `car-${n}`, title: `Slide ${n}`, thumbnailUrl: THUMB }))
	const { widget } = await render(page, { feedUrls: [], layout: 'carousel' }, ok(items), { w: 6, h: 8 })

	await expect(widget).toHaveClass(/news-widget--carousel/)

	/** Titles of the items whose box lies inside the widget's visible area. */
	const visibleTitles = async (): Promise<string[]> => widget.evaluate((root) => {
		const frame = root.getBoundingClientRect()
		return Array.from(root.querySelectorAll('.news-widget__item'))
			.filter((li) => {
				const r = li.getBoundingClientRect()
				const overlap = Math.min(r.right, frame.right) - Math.max(r.left, frame.left)
				return overlap > r.width / 2
			})
			.map((li) => li.querySelector('.news-widget__title')?.textContent?.trim() ?? '')
	})
	expect(await visibleTitles()).toEqual(['Slide 1'])

	const next = widget.getByRole('button', { name: /next/i })
	const previous = widget.getByRole('button', { name: /prev/i })
	await expect(next).toBeVisible()
	await expect(previous).toBeVisible()
	await next.click()
	await expect.poll(visibleTitles).toEqual(['Slide 2'])
	await previous.click()
	await expect.poll(visibleTitles).toEqual(['Slide 1'])

	// Every card shows all of its information.
	const card = widget.locator('.news-widget__item').first()
	await expect(card.locator('img.news-widget__thumb')).toBeVisible()
	await expect(card.locator('.news-widget__title')).toHaveText('Slide 1')
	await expect(card.locator('.news-widget__summary')).toBeVisible()
	await expect(card.locator('a')).toHaveAttribute('href', 'https://news.e2e.test/car-1')
	await expect(card.locator('.news-widget__date')).not.toBeEmpty()
	await expect(card.locator('.news-widget__source')).toHaveText('E2E News')
})

// @e2e news-widget::responsive-layout
test('on a narrow viewport the grid layout collapses to one column', async ({ page }) => {
	await page.setViewportSize({ width: 375, height: 800 })
	const items = [1, 2, 3].map((n) => newsItem({ guid: `narrow-${n}` }))
	const { widget } = await render(page, { feedUrls: [], layout: 'grid' }, ok(items))

	const list = widget.locator('.news-widget__list')
	expect(await list.evaluate((el) => getComputedStyle(el).display)).toBe('grid')
	expect(await gridColumns(list)).toBe(1)
})

// ── REQ-NEWS-011: links ───────────────────────────────────────────────────

// @e2e news-widget::click-on-item-title-or-link
test('clicking an item\'s title or summary opens its link in a new tab; the anchor is target=_blank rel=noopener noreferrer', async ({ page }) => {
	await serveArticles(page)
	const link = 'https://news.e2e.test/article'
	const { widget } = await render(page, { feedUrls: [] }, ok([newsItem({ guid: 'click', link })]))

	const anchor = widget.locator('a.news-widget__item-link').first()
	await expect(anchor).toHaveAttribute('href', link)
	await expect(anchor).toHaveAttribute('target', '_blank')
	const rel = ((await anchor.getAttribute('rel')) ?? '').split(/\s+/)
	expect(rel).toEqual(expect.arrayContaining(['noopener', 'noreferrer']))

	for (const part of ['.news-widget__title', '.news-widget__summary']) {
		const opened = page.context().waitForEvent('page')
		await widget.locator(part).first().click()
		const tab = await opened
		await tab.waitForLoadState()
		expect(tab.url()).toBe(link)
		expect(page.url()).toContain('/apps/launchpad')
		await tab.close()
	}
})

// @e2e news-widget::link-security-noopener
test('the tab an item opens has no window.opener', async ({ page }) => {
	await serveArticles(page)
	const { widget } = await render(page, { feedUrls: [] }, ok([newsItem({ guid: 'opener', link: 'https://news.e2e.test/opener' })]))

	await expect(widget.locator('a.news-widget__item-link').first()).toHaveAttribute('rel', /(^|\s)noopener(\s|$)/)
	const opened = page.context().waitForEvent('page')
	await widget.locator('.news-widget__title').first().click()
	const tab = await opened
	await tab.waitForLoadState()
	expect(await tab.evaluate(() => window.opener)).toBeNull()
	await tab.close()
})

// @e2e news-widget::link-security-noreferrer
test('opening an item sends no Referer to the destination', async ({ page }) => {
	const seen = await serveArticles(page)
	const link = 'https://news.e2e.test/referrer'
	const { widget } = await render(page, { feedUrls: [] }, ok([newsItem({ guid: 'referrer', link })]))

	await expect(widget.locator('a.news-widget__item-link').first()).toHaveAttribute('rel', /(^|\s)noreferrer(\s|$)/)
	const opened = page.context().waitForEvent('page')
	await widget.locator('.news-widget__title').first().click()
	const tab = await opened
	await tab.waitForLoadState()
	const request = seen.find((r) => r.url === link)
	expect(request, JSON.stringify(seen)).toBeTruthy()
	expect(request!.headers.referer).toBeUndefined()
	expect(await tab.evaluate(() => document.referrer)).toBe('')
	await tab.close()
})

// @e2e news-widget::missing-link-in-feed-item
test('an item without a link still shows, is not clickable, and logs no error', async ({ page }) => {
	const errors: string[] = []
	page.on('pageerror', (error) => errors.push(error.message))
	page.on('console', (message) => {
		if (message.type() === 'error' && /news/i.test(message.text())) {
			errors.push(message.text())
		}
	})
	const items = [
		newsItem({ guid: 'nolink-empty', title: 'No link here', link: '', thumbnailUrl: THUMB }),
		newsItem({ guid: 'nolink-null', title: 'Null link here', link: null as unknown as string }),
	]
	const { widget } = await render(page, { feedUrls: [] }, ok(items))

	const rows = widget.locator('.news-widget__item')
	await expect(rows).toHaveCount(2)
	for (const [index, title] of ['No link here', 'Null link here'].entries()) {
		const row = rows.nth(index)
		await expect(row.locator('.news-widget__title')).toHaveText(title)
		await expect(row.locator('a')).toHaveCount(0)
		await expect(row.locator('.news-widget__item-link--inert')).toBeVisible()
	}
	await expect(rows.nth(0).locator('.news-widget__summary')).toHaveText('Summary of nolink-empty')
	await expect(rows.nth(0).locator('img.news-widget__thumb')).toBeVisible()

	const pages = page.context().pages().length
	await rows.nth(0).locator('.news-widget__title').click()
	expect(page.context().pages().length).toBe(pages)
	expect(page.url()).toContain('/apps/launchpad')
	expect(errors).toEqual([])
})

// @e2e news-widget::malformed-or-unsafe-link
test('a javascript: or data: item link is replaced with "#" or removed, and clicking runs no script', async ({ page }) => {
	await page.addInitScript(() => {
		(window as any).__newsLinkRan = false
	})
	const dialogs: string[] = []
	page.on('dialog', async (dialog) => {
		dialogs.push(dialog.message())
		await dialog.dismiss()
	})
	const items = [
		newsItem({ guid: 'js', title: 'Javascript link', link: 'javascript:window.__newsLinkRan=true;alert(\'xss\')' }),
		newsItem({ guid: 'data', title: 'Data link', link: 'data:text/html,<script>alert(1)</script>' }),
	]
	const { widget } = await render(page, { feedUrls: [] }, ok(items))

	const rows = widget.locator('.news-widget__item')
	await expect(rows).toHaveCount(2)
	for (const row of await rows.all()) {
		const anchors = row.locator('a')
		for (const anchor of await anchors.all()) {
			const href = ((await anchor.getAttribute('href')) ?? '').trim().toLowerCase()
			expect(href === '' || href === '#', `item link href "${href}" must be "#" or removed`).toBe(true)
		}
	}

	await rows.nth(0).locator('.news-widget__title').click()
	await page.waitForTimeout(500)
	expect(await page.evaluate(() => (window as any).__newsLinkRan)).toBe(false)
	expect(dialogs).toEqual([])
})
