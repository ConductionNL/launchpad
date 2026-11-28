/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The first-paint shell prints nextcloud-vue's placeholder markup in every
 * frame, and the mounted card waits in the same shape
 * (widget-declared-placeholders task 4): a Conduction Nextcloud widget that
 * declares its shape (launchpad_links: rows), the Talk widget (rows, from the
 * default) and a chart placement (block, from the registry).
 *
 * Written from the change's scenarios; runs in the nightly against a live
 * instance (it is not run in the cloud sandbox).
 *
 * Gate-19 @e2e traceability:
 *   @e2e first-paint-shell::the-shell-and-the-card-draw-the-same-shape
 *
 * @spec openspec/changes/widget-declared-placeholders/specs/first-paint-shell/spec.md#requirement-the-placeholder-shape-comes-from-the-widgets-declaration-req-fps-009
 */

import type { APIRequestContext } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import {
	clearSeededWidgets,
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { adminContext, API } from './support/principals.ts'

const RUN = Date.now()
const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

/** The placements and the shape each must wait in, everywhere. */
const CASES: Array<{ widgetId: string, shape: string, id?: number }> = [
	{ widgetId: 'launchpad_links', shape: 'rows' },
	{ widgetId: 'spreed', shape: 'rows' },
	{ widgetId: 'chart', shape: 'block' },
]

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await adminContext()
	dashboard = await seedActiveDashboard(admin, `E2E First paint shapes ${RUN}`)
	await clearSeededWidgets(admin, dashboard.id)
	// Below the fold, so the mounted card still waits when the page has loaded.
	for (const [index, entry] of CASES.entries()) {
		const res = await admin.post(`${API}/dashboard/${dashboard.id}/widgets`, {
			data: { widgetId: entry.widgetId, gridX: index * 4, gridY: 80, gridWidth: 4, gridHeight: 4 },
		})
		expect(res.status(), await res.text()).toBeLessThan(300)
		const body = await res.json()
		entry.id = Number((body.data ?? body).id)
	}
})

test.afterAll(async () => {
	await removeSeededDashboard(admin, dashboard)
	await admin.dispose()
})

// @e2e first-paint-shell::the-shell-and-the-card-draw-the-same-shape
test('the shell and the mounted card draw the same placeholder shape', async ({ page }) => {
	await page.context().setHTTPCredentials({ username: ADMIN.user, password: ADMIN.pass })

	// The shell alone: the app's scripts never arrive, so the server's frames stay.
	await page.route(/\/apps\/launchpad\/js\//, (route) => route.abort())
	await page.goto('/index.php/apps/launchpad/')
	const shell = page.locator('#launchpad-first-paint')
	for (const entry of CASES) {
		const frame = shell.locator(`[data-placement-id="${entry.id}"]`)
		await expect(frame.locator('.cn-widget-placeholder')).toHaveAttribute('data-shape', entry.shape)
		await expect(frame.locator('.cn-widget-placeholder')).toHaveAttribute('aria-hidden', 'true')
	}

	// The mounted page: each card waits below the fold in the same shape.
	await page.unroute(/\/apps\/launchpad\/js\//)
	await page.goto('/index.php/apps/launchpad/')
	for (const entry of CASES) {
		const card = page.locator(`[data-testid="widget-placement-${entry.id}"]`)
		await expect(card).toHaveCount(1, { timeout: 30_000 })
		await expect(card.locator('.cn-widget-placeholder')).toHaveAttribute('data-shape', entry.shape)
	}
})

/**
 * first-paint-skeleton task 8: the shell against the mounted page. One
 * dashboard of four widgets that reflow on 8 and 4 columns, one hidden
 * placement and one of an app the instance lacks (wiki-pages needs
 * OpenRegister): the shell draws the four in the grid's places at every
 * breakpoint, the other two not at all, says the page could not load when
 * the scripts never come, and never says so on a normal load.
 *
 * @spec openspec/changes/first-paint-skeleton/specs/first-paint-shell/spec.md
 */
test.describe('first paint against the mounted grid (first-paint-skeleton task 8)', () => {
	let own: SeededDashboard | null = null
	const NAME = `E2E First paint grid ${RUN}`
	const DESCRIPTION = 'Shell against grid'
	const SHOWN: Array<{ widgetId: string, x: number, y: number, w: number, h: number, id?: number }> = [
		{ widgetId: 'text', x: 0, y: 0, w: 6, h: 3 },
		{ widgetId: 'label', x: 6, y: 0, w: 6, h: 3 },
		{ widgetId: 'chart', x: 0, y: 3, w: 4, h: 4 },
		{ widgetId: 'launchpad_links', x: 4, y: 3, w: 8, h: 4 },
	]
	const LEFT_OUT: Array<{ widgetId: string, x: number, y: number, w: number, h: number, id?: number }> = [
		{ widgetId: 'text', x: 0, y: 12, w: 6, h: 2 },
		{ widgetId: 'wiki-pages', x: 6, y: 12, w: 6, h: 2 },
	]
	const BUNDLE = /\/apps\/launchpad\/js\//

	test.beforeAll(async () => {
		own = await seedActiveDashboard(admin, NAME)
		await clearSeededWidgets(admin, own.id)
		const described = await admin.put(`${API}/dashboard/${own.id}`, { data: { description: DESCRIPTION } })
		expect(described.status(), await described.text()).toBeLessThan(300)
		for (const entry of [...SHOWN, ...LEFT_OUT]) {
			const res = await admin.post(`${API}/dashboard/${own.id}/widgets`, {
				data: { widgetId: entry.widgetId, gridX: entry.x, gridY: entry.y, gridWidth: entry.w, gridHeight: entry.h },
			})
			expect(res.status(), await res.text()).toBeLessThan(300)
			const body = await res.json()
			entry.id = Number((body.data ?? body).id)
		}
		const hide = await admin.put(`${API}/widgets/${LEFT_OUT[0].id}`, { data: { isVisible: 0 } })
		expect(hide.status(), await hide.text()).toBeLessThan(300)
	})

	test.afterAll(async () => {
		await removeSeededDashboard(admin, own)
	})

	test('the header and the frames paint without script; hidden and refused widgets have none', async ({ browser }) => {
		const context = await browser.newContext({ javaScriptEnabled: false, httpCredentials: { username: ADMIN.user, password: ADMIN.pass } })
		const page = await context.newPage()
		await page.goto('/index.php/apps/launchpad/')
		const shell = page.locator('#launchpad-first-paint')
		await expect(shell.locator('h1.lp-fp__t')).toHaveText(NAME)
		await expect(shell.locator('.lp-fp__m')).toContainText(DESCRIPTION)
		await expect(shell.locator('.lp-fp__f')).toHaveCount(SHOWN.length)
		for (const entry of SHOWN) {
			await expect(shell.locator(`[data-placement-id="${entry.id}"]`)).toHaveCount(1)
		}
		for (const left of LEFT_OUT) {
			await expect(shell.locator(`[data-placement-id="${left.id}"]`)).toHaveCount(0)
		}
		// Placeholders are hidden from assistive tech; one status line speaks.
		await expect(shell.locator('.cn-widget-placeholder:not([aria-hidden="true"])')).toHaveCount(0)
		await expect(shell.locator('[role="status"]')).toHaveCount(1)
		await context.close()
	})

	for (const viewport of [{ width: 1440, height: 900 }, { width: 1100, height: 900 }, { width: 390, height: 844 }]) {
		test(`every frame sits where the grid puts its widget at ${viewport.width} px, without a shift`, async ({ page }) => {
			await page.context().setHTTPCredentials({ username: ADMIN.user, password: ADMIN.pass })
			await page.setViewportSize(viewport)
			// Each box relative to its grid's top left: the frame geometry,
			// whatever stands above the grid (a notice banner, the header).
			const rectOf = (selector: string, gridSelector: string) => page.evaluate(([sel, gridSel]) => {
				const element = document.querySelector(sel)
				const grid = document.querySelector(gridSel)
				if (!element || !grid) {
					return null
				}
				const box = element.getBoundingClientRect()
				const origin = grid.getBoundingClientRect()
				return { x: box.x - origin.x, y: box.y - origin.y, w: box.width, h: box.height }
			}, [selector, gridSelector])

			// The shell alone, as it paints before any script.
			await page.route(BUNDLE, (route) => route.abort())
			await page.goto('/index.php/apps/launchpad/')
			const shellRects: Record<number, { x: number, y: number, w: number, h: number } | null> = {}
			for (const entry of SHOWN) {
				shellRects[entry.id!] = await rectOf(`#launchpad-first-paint [data-placement-id="${entry.id}"]`, '#launchpad-first-paint .lp-fp__g')
			}
			await expect(page.locator('#launchpad-first-paint .lp-fp__d:empty')).toHaveCount(0)

			// The mounted page, with the layout shifts it makes counted: those
			// that move something inside the page (#app-workspace), not
			// Nextcloud's own top bar filling in its icons.
			await page.unroute(BUNDLE)
			await page.addInitScript(() => {
				type Shift = PerformanceEntry & { value: number, hadRecentInput: boolean, sources?: Array<{ node?: Node | null }> }
				(window as unknown as { __lpShift: number }).__lpShift = 0
				new PerformanceObserver((list) => {
					for (const entry of list.getEntries() as Shift[]) {
						const inPage = (entry.sources ?? []).some((source) => source.node instanceof Element && source.node.closest('#app-workspace') !== null)
						if (!entry.hadRecentInput && inPage) {
							(window as unknown as { __lpShift: number }).__lpShift += entry.value
						}
					}
				}).observe({ type: 'layout-shift', buffered: true })
			})
			await page.goto('/index.php/apps/launchpad/')
			await expect(page.locator('#launchpad-first-paint')).toHaveCount(0, { timeout: 30_000 })
			for (const entry of SHOWN) {
				const item = `.grid-stack-item:has(> .grid-stack-item-content [data-placement-id="${entry.id}"])`
				await expect(page.locator(item)).toHaveCount(1)
				const grid = await rectOf(item, '.cn-dashboard-grid .grid-stack')
				const shell = shellRects[entry.id!]
				expect(shell, `shell frame ${entry.widgetId}`).not.toBeNull()
				for (const key of ['x', 'y', 'w', 'h'] as const) {
					expect(Math.abs(shell![key] - grid![key]), `${entry.widgetId} ${key}: shell ${shell![key]} grid ${grid![key]}`).toBeLessThanOrEqual(1)
				}
			}
			const shift = await page.evaluate(() => (window as unknown as { __lpShift: number }).__lpShift)
			expect(shift).toBeLessThan(0.001)
			// A normal load never shows the could-not-load line.
			await expect(page.getByText('The page could not load')).toHaveCount(0)
		})
	}

	test('without the scripts the shell says after 15 seconds that the page could not load', async ({ page }) => {
		await page.context().setHTTPCredentials({ username: ADMIN.user, password: ADMIN.pass })
		await page.route(BUNDLE, (route) => route.abort())
		await page.goto('/index.php/apps/launchpad/')
		const line = page.locator('#launchpad-first-paint [role="alert"]')
		await expect(line).toBeHidden()
		await expect(line).toBeVisible({ timeout: 25_000 })
		await expect(line.getByRole('link')).toHaveAttribute('href', /\/apps\/launchpad/)
	})

	test('a slow widget keeps its placeholder while the others fill; the status line counts it, then says all loaded once', async ({ page }) => {
		await page.context().setHTTPCredentials({ username: ADMIN.user, password: ADMIN.pass })
		await page.setViewportSize({ width: 1440, height: 900 })
		// The links widget's items (a Nextcloud API widget) are held back.
		let release: () => void = () => {}
		const held = new Promise<void>((resolve) => { release = resolve })
		await page.route(/\/api\/widgets\/items/, async (route) => {
			await held
			await route.continue()
		})
		// Every text the status line shows, in order.
		await page.addInitScript(() => {
			const w = window as unknown as { __lpStatus: string[] }
			w.__lpStatus = []
			new MutationObserver(() => {
				const line = document.querySelector('.lp-fp__s')
				const text = line?.textContent?.trim() ?? ''
				if (line && w.__lpStatus.at(-1) !== text) {
					w.__lpStatus.push(text)
				}
			}).observe(document, { childList: true, subtree: true, characterData: true })
		})
		await page.goto('/index.php/apps/launchpad/')
		await expect(page.locator('#launchpad-first-paint')).toHaveCount(0, { timeout: 30_000 })

		const links = SHOWN.find((entry) => entry.widgetId === 'launchpad_links')!
		const text = SHOWN.find((entry) => entry.widgetId === 'text')!
		const waiting = page.locator(`[data-testid="widget-placement-${links.id}"] .cn-widget-wrapper`)
		await expect(waiting).toHaveAttribute('aria-busy', 'true')
		await expect(waiting.locator('.cn-widget-placeholder')).toHaveCount(1)
		await expect(page.locator(`[data-testid="widget-placement-${text.id}"] .cn-widget-wrapper`)).not.toHaveAttribute('aria-busy', 'true')
		await expect(page.locator('.lp-fp__s')).toHaveText('Still loading 1 widget', { timeout: 10_000 })

		release()
		await expect(waiting).not.toHaveAttribute('aria-busy', 'true', { timeout: 15_000 })
		await expect(page.locator('.lp-fp__s')).toHaveText('', { timeout: 10_000 })
		const said = await page.evaluate(() => (window as unknown as { __lpStatus: string[] }).__lpStatus)
		expect(said.filter((line) => line === 'All widgets loaded')).toHaveLength(1)
		expect(said).toContain('Still loading 1 widget')
	})

	test('no shimmer under reduced motion', async ({ browser }) => {
		const context = await browser.newContext({ reducedMotion: 'reduce', httpCredentials: { username: ADMIN.user, password: ADMIN.pass } })
		const page = await context.newPage()
		await page.route(BUNDLE, (route) => route.abort())
		await page.goto('/index.php/apps/launchpad/')
		const animation = await page.locator('#launchpad-first-paint .cn-widget-placeholder__line').first()
			.evaluate((element) => getComputedStyle(element).animationName)
		expect(animation).toBe('none')
		await context.close()
	})
})
