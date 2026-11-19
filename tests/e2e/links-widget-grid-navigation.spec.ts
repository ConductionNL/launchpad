/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the links widget's column grid and its
 * link navigation (openspec/specs/links-widget, REQ-LNKS-006 and
 * REQ-LNKS-010), written for the e2e-coverage-programme change.
 *
 * Grid scenarios seed a full-width placement with a known column count and
 * measure where the rendered links land: "N columns" means the first N links
 * share a row and link N+1 starts the next one under link 1.
 *
 * Navigation scenarios click the rendered anchor for real. External and
 * internal targets are answered by `context.route()` so nothing leaves the
 * runner, and the opened tab is asked for its own `window.opener`, which is
 * what `noopener` is about and what no attribute check can show.
 *
 * Gate-19 @e2e traceability:
 *   @e2e links-widget::grid-renders-with-configured-columns
 *   @e2e links-widget::column-count-respects-bounds
 *   @e2e links-widget::single-column-layout
 *   @e2e links-widget::six-column-layout
 *   @e2e links-widget::responsive-behavior-on-mobile
 *   @e2e links-widget::external-url-uses-relnoopener-noreferrer
 *   @e2e links-widget::internal-url-preserves-windowopener
 *   @e2e links-widget::same-window-navigation
 *   @e2e links-widget::internal-link-in-same-window
 *   @e2e links-widget::click-handler-does-not-fire-in-edit-mode
 *
 * @spec openspec/specs/links-widget/spec.md
 */

import type { APIRequestContext, Locator, Page, Request } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import {
	adminApi,
	formOption,
	gotoDashboard,
	linksPlacementsOf,
	linksWidget,
	oneSection,
	openLinksForm,
	postLinksPlacement,
	removeLinksDashboard,
	seedLinksDashboard,
	seedLinksPlacement,
	storedContent,
	typeSection,
} from './support/linksWidget.ts'

// Root-relative hrefs resolve against the origin, not against BASE_URL's path.
const ORIGIN = new URL(BASE_URL).origin
const PAGE_HTML =
	'<!doctype html><html><head><title>e2e target</title></head><body>e2e target</body></html>'

const LABEL = 'grid and navigation'

let api: APIRequestContext
let dashboard: SeededDashboard | null = null

test.describe.configure({ timeout: 120_000 })

test.beforeEach(async () => {
	api = await adminApi()
	dashboard = await seedLinksDashboard(api, LABEL)
})

test.afterEach(async () => {
	await removeLinksDashboard(api, dashboard)
	dashboard = null
	await api.dispose()
})

/**
 * `count` links labelled L1…Ln.
 *
 * @param count how many.
 * @return the links.
 */
function numberedLinks(count: number) {
	return Array.from({ length: count }, (_, i) => ({
		label: `L${i + 1}`,
		url: `https://example.com/l${i + 1}`,
		icon: 'icon-link',
	}))
}

/**
 * Seed a placement and open the dashboard on it.
 *
 * @param page the page.
 * @param content the placement content.
 * @return the rendered widget root.
 */
async function renderLinks(
	page: Page,
	content: Record<string, unknown>,
): Promise<Locator> {
	const id = await seedLinksPlacement(api, dashboard!.id, content)
	await gotoDashboard(page)
	const widget = linksWidget(page, id)
	await expect(widget).toBeVisible({ timeout: 20_000 })
	return widget
}

/**
 * The bounding boxes of every rendered link, in DOM order.
 *
 * @param widget the widget root.
 * @return the boxes.
 */
async function linkBoxes(widget: Locator) {
	const links = widget.locator('a.cn-links-widget__link')
	const boxes = []
	for (let i = 0; i < (await links.count()); i++) {
		const box = await links.nth(i).boundingBox()
		expect(box, `link ${i + 1} is not laid out`).not.toBeNull()
		boxes.push(box!)
	}
	return boxes
}

/**
 * Record main-frame navigation requests made by a page.
 *
 * @param page the page.
 * @return the live list of navigated URLs.
 */
function recordNavigations(page: Page): string[] {
	const seen: string[] = []
	page.on('request', (req: Request) => {
		if (req.isNavigationRequest() && req.frame() === page.mainFrame()) {
			seen.push(req.url())
		}
	})
	return seen
}

// ── REQ-LNKS-006 Multi-column grid ───────────────────────────────────────

// @e2e links-widget::grid-renders-with-configured-columns
test('columns: 3 lays the links out in a three-track grid of equal width that wraps', async ({
	page,
}) => {
	const widget = await renderLinks(
		page,
		oneSection(numberedLinks(5), { columns: 3, linkLayout: 'inline' }),
	)

	const grid = await widget.locator('.cn-links-widget__items').evaluate((el) => {
		const s = getComputedStyle(el)
		return {
			display: s.display,
			tracks: s.gridTemplateColumns.split(' ').filter(Boolean),
		}
	})
	expect(grid.display, 'the links are not laid out as a CSS grid').toBe('grid')
	expect(grid.tracks).toHaveLength(3)
	expect(new Set(grid.tracks.map((t) => Math.round(parseFloat(t)))).size).toBe(1)

	const boxes = await linkBoxes(widget)
	expect(boxes).toHaveLength(5)
	// Left to right on the first row ...
	expect(Math.round(boxes[1].y)).toBe(Math.round(boxes[0].y))
	expect(Math.round(boxes[2].y)).toBe(Math.round(boxes[0].y))
	expect(boxes[0].x).toBeLessThan(boxes[1].x)
	expect(boxes[1].x).toBeLessThan(boxes[2].x)
	// ... wrapping to the next row under the first link.
	expect(boxes[3].y).toBeGreaterThan(boxes[0].y)
	expect(Math.round(boxes[3].x)).toBe(Math.round(boxes[0].x))
	// Equal widths.
	const widths = new Set(boxes.map((b) => Math.round(b.width)))
	expect(widths.size, `link widths differ: ${[...widths].join(', ')}`).toBe(1)
})

// @e2e links-widget::column-count-respects-bounds
test('columns: 7 is refused or clamped to 6 with a warning, through the form and the API', async ({
	page,
}) => {
	// Through the form.
	await gotoDashboard(page)
	const { dialog, form } = await openLinksForm(page, dashboard!.name)
	await typeSection(form, {
		title: 'Tools',
		links: [{ label: 'Docs', url: 'https://example.com/docs' }],
	})
	const columns = formOption(form, 'Columns')
	await columns.fill('7')
	const shown = Number(await columns.inputValue())
	const message = dialog.locator('.cn-links-form__error, [role="alert"]')
	if (shown === 7) {
		// Kept as typed: then it must be refused.
		await expect(
			dialog.locator('[data-testid="add-widget-save"]'),
		).toBeDisabled()
		await expect(message).toBeVisible()
	} else {
		// Clamped: to 6, and the user is told.
		expect(shown).toBe(6)
		await expect(
			message,
			'columns were clamped to 6 without telling the user',
		).toBeVisible()
	}
	await dialog.getByRole('button', { name: /cancel/i }).click()

	// Through the API.
	const res = await postLinksPlacement(
		api,
		dashboard!.id,
		oneSection(numberedLinks(1), { columns: 7 }),
	)
	if (res.status() >= 300) {
		expect(res.status()).toBe(400)
		expect(await linksPlacementsOf(api, dashboard!.id)).toHaveLength(0)
	} else {
		const body = await res.json()
		const id = Number(body.id ?? body.data?.id)
		const stored = await storedContent(api, dashboard!.id, id)
		expect(
			Number(stored.columns),
			'columns: 7 was stored as given',
		).toBeLessThanOrEqual(6)
	}
})

// @e2e links-widget::single-column-layout
test('columns: 1 renders the links as a single vertical list', async ({ page }) => {
	const widget = await renderLinks(
		page,
		oneSection(numberedLinks(3), { columns: 1, linkLayout: 'inline' }),
	)
	const boxes = await linkBoxes(widget)
	expect(boxes).toHaveLength(3)
	for (let i = 1; i < boxes.length; i++) {
		expect(Math.round(boxes[i].x), `link ${i + 1} is not under link 1`).toBe(
			Math.round(boxes[0].x),
		)
		expect(boxes[i].y).toBeGreaterThan(boxes[i - 1].y)
	}
})

// @e2e links-widget::six-column-layout
test('columns: 6 puts six links on a row and wraps the seventh', async ({
	page,
}) => {
	const widget = await renderLinks(
		page,
		oneSection(numberedLinks(7), { columns: 6, linkLayout: 'inline' }),
	)
	const boxes = await linkBoxes(widget)
	expect(boxes).toHaveLength(7)
	for (let i = 1; i < 6; i++) {
		expect(Math.round(boxes[i].y), `link ${i + 1} is not on the first row`).toBe(
			Math.round(boxes[0].y),
		)
		expect(boxes[i].x).toBeGreaterThan(boxes[i - 1].x)
	}
	expect(boxes[6].y, 'the seventh link did not wrap').toBeGreaterThan(boxes[0].y)
	expect(Math.round(boxes[6].x)).toBe(Math.round(boxes[0].x))
})

// @e2e links-widget::responsive-behavior-on-mobile
test('on a phone-sized viewport every link stays reachable', async ({ page }) => {
	await page.setViewportSize({ width: 390, height: 844 })
	const widget = await renderLinks(
		page,
		oneSection(
			numberedLinks(5).map((l) => ({
				...l,
				label: `${l.label} a fairly long link label`,
			})),
			{ columns: 3, linkLayout: 'card' },
		),
	)
	const items = widget.locator('.cn-links-widget__items')
	const scroll = await items.evaluate((el) => ({
		scrollable:
			el.scrollWidth > el.clientWidth
			&& ['auto', 'scroll'].includes(getComputedStyle(el).overflowX),
		ownerScrollable: (() => {
			const root = el.closest('.cn-links-widget') as HTMLElement
			return (
				root.scrollWidth > root.clientWidth
				&& ['auto', 'scroll'].includes(getComputedStyle(root).overflowX)
			)
		})(),
	}))

	const links = widget.locator('a.cn-links-widget__link')
	await expect(links).toHaveCount(5)
	for (let i = 0; i < 5; i++) {
		const link = links.nth(i)
		await link.scrollIntoViewIfNeeded()
		await expect(link).toBeVisible()
		const box = await link.boundingBox()
		if (!scroll.scrollable && !scroll.ownerScrollable) {
			// Without horizontal scrolling the layout must have adapted: every
			// link fits the viewport width.
			expect(box!.x, `link ${i + 1} starts off-screen`).toBeGreaterThanOrEqual(
				0,
			)
			expect(
				box!.x + box!.width,
				`link ${i + 1} overflows the viewport`,
			).toBeLessThanOrEqual(390 + 1)
		}
		// Usable: a real click would land on it.
		await link.click({ trial: true })
	}
})

// ── REQ-LNKS-010 Navigation ──────────────────────────────────────────────

// @e2e links-widget::external-url-uses-relnoopener-noreferrer
test('an external link opens in a new tab with noopener noreferrer, leaving the tab no opener', async ({
	page,
	context,
}) => {
	await context.route('https://example.com/**', (route) =>
		route.fulfill({ contentType: 'text/html', body: PAGE_HTML }),
	)
	const widget = await renderLinks(
		page,
		oneSection(
			[{ label: 'External', url: 'https://example.com', icon: 'icon-link' }],
			{ openInNewTab: true },
		),
	)
	const link = widget
		.locator('a.cn-links-widget__link')
		.filter({ hasText: 'External' })
	await expect(link).toHaveAttribute('target', '_blank')
	const rel = (await link.getAttribute('rel')) ?? ''
	expect(rel.split(/\s+/)).toEqual(
		expect.arrayContaining(['noopener', 'noreferrer']),
	)

	const before = page.url()
	const [popup] = await Promise.all([page.waitForEvent('popup'), link.click()])
	await popup.waitForLoadState('domcontentloaded')
	expect(popup.url()).toMatch(/^https:\/\/example\.com\/?$/)
	expect(
		await popup.evaluate(() => window.opener === null),
		'the external tab can reach window.opener',
	).toBe(true)
	expect(page.url()).toBe(before)
	await popup.close()
})

// @e2e links-widget::internal-url-preserves-windowopener
test('an internal link opens in a new tab that can still reach window.opener', async ({
	page,
	context,
}) => {
	await context.route(`${ORIGIN}/apps/files`, (route) =>
		route.fulfill({ contentType: 'text/html', body: PAGE_HTML }),
	)
	const widget = await renderLinks(
		page,
		oneSection([{ label: 'Internal', url: '/apps/files', icon: 'icon-files' }], {
			openInNewTab: true,
		}),
	)
	const link = widget
		.locator('a.cn-links-widget__link')
		.filter({ hasText: 'Internal' })
	await expect(link).toHaveAttribute('target', '_blank')
	expect((await link.getAttribute('rel')) ?? '').not.toContain('noopener')

	const [popup] = await Promise.all([page.waitForEvent('popup'), link.click()])
	await popup.waitForLoadState('domcontentloaded')
	expect(new URL(popup.url()).pathname).toBe('/apps/files')
	expect(
		await popup.evaluate(() => window.opener !== null),
		'the internal tab was opened without access to window.opener',
	).toBe(true)
	await popup.close()
})

// @e2e links-widget::same-window-navigation
test('with openInNewTab off an external link navigates the same window', async ({
	page,
	context,
}) => {
	await context.route('https://example.com/**', (route) =>
		route.fulfill({ contentType: 'text/html', body: PAGE_HTML }),
	)
	const widget = await renderLinks(
		page,
		oneSection(
			[
				{
					label: 'Same window',
					url: 'https://example.com',
					icon: 'icon-link',
				},
			],
			{ openInNewTab: false },
		),
	)
	const link = widget
		.locator('a.cn-links-widget__link')
		.filter({ hasText: 'Same window' })
	await expect(link).not.toHaveAttribute('target', '_blank')
	await expect(link).not.toHaveAttribute('rel', /noopener/)

	const popups: Page[] = []
	context.on('page', (p) => popups.push(p))
	await Promise.all([page.waitForURL(/^https:\/\/example\.com\/?$/), link.click()])
	expect(popups, 'a new tab was opened').toHaveLength(0)
})

// @e2e links-widget::internal-link-in-same-window
test('with openInNewTab off an internal link navigates the same window without rel', async ({
	page,
	context,
}) => {
	const target = `${ORIGIN}/apps/launchpad/dashboard/1`
	await context.route(target, (route) =>
		route.fulfill({ contentType: 'text/html', body: PAGE_HTML }),
	)
	const widget = await renderLinks(
		page,
		oneSection(
			[
				{
					label: 'Dashboard one',
					url: '/apps/launchpad/dashboard/1',
					icon: 'icon-dashboard',
				},
			],
			{
				openInNewTab: false,
			},
		),
	)
	const link = widget
		.locator('a.cn-links-widget__link')
		.filter({ hasText: 'Dashboard one' })
	await expect(link).toHaveAttribute('href', '/apps/launchpad/dashboard/1')
	expect(await link.getAttribute('rel')).toBeNull()

	const popups: Page[] = []
	context.on('page', (p) => popups.push(p))
	await Promise.all([page.waitForURL(target), link.click()])
	expect(popups, 'a new tab was opened').toHaveLength(0)
})

// @e2e links-widget::click-handler-does-not-fire-in-edit-mode
test('in edit mode clicking a link does not navigate and the widget stays editable', async ({
	page,
	context,
}) => {
	const target = `${ORIGIN}/apps/launchpad-e2e-links-must-not-open`
	await context.route(target, (route) =>
		route.fulfill({ contentType: 'text/html', body: PAGE_HTML }),
	)
	const id = await seedLinksPlacement(
		api,
		dashboard!.id,
		oneSection(
			[
				{
					label: 'Do not follow',
					url: '/apps/launchpad-e2e-links-must-not-open',
					icon: 'icon-link',
				},
			],
			{
				openInNewTab: false,
			},
		),
	)
	await gotoDashboard(page)
	await expect(linksWidget(page, id)).toBeVisible({ timeout: 20_000 })
	await enterEditMode(page)

	const navigations = recordNavigations(page)
	const popups: Page[] = []
	context.on('page', (p) => popups.push(p))
	const before = page.url()

	await linksWidget(page, id)
		.locator('a.cn-links-widget__link')
		.filter({ hasText: 'Do not follow' })
		.click()
	// Give a navigation, if one was started, time to show up.
	await page.waitForTimeout(2_000)

	expect(
		navigations.filter((u) => u.includes('launchpad-e2e-links-must-not-open')),
		'the click navigated',
	).toEqual([])
	expect(popups).toHaveLength(0)
	expect(page.url()).toBe(before)
	// Still editable: edit mode is on and the widget's own edit menu is there.
	await expect(page.locator('.launchpad-edit-mode')).toBeVisible()
	await expect(
		page
			.locator(`[data-placement-id="${id}"]`)
			.getByRole('button', { name: 'Widget menu' }),
	).toBeVisible()
})
