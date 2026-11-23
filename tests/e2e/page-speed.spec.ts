/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Page speed of LaunchPad's main views, measured as the admin on their own
 * start page. Read-only: every dialog it opens is closed again without saving.
 *
 * For each step it records the time from the action to the view being usable,
 * the navigation timing of page loads, and every request slower than the
 * budget. The numbers go to the report as JSON; the assertions hold each step
 * and each request to the budget.
 */

import type { Page, Request } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { MORE_MENU } from './support/dashboardSwitcher.ts'

const BUDGET_MS = Number(process.env.PAGE_SPEED_BUDGET_MS ?? 350)
const START = '/index.php/apps/launchpad/'
const TRIGGER = `${MORE_MENU} [data-testid="cn-grouped-menu-trigger"]`

interface SlowRequest {
	method: string
	url: string
	status: number
	ms: number
}

interface Step {
	name: string
	ms: number
	navigation?: Record<string, number>
	slowRequests: SlowRequest[]
}

/**
 * Collect every finished request on the page with its duration.
 *
 * @param page the page.
 * @return a function returning the requests finished since `from` (epoch ms) that took longer than the budget.
 */
function watchRequests(page: Page): (from: number) => SlowRequest[] {
	const done: Array<SlowRequest & { endedAt: number }> = []
	page.on('requestfinished', async (req: Request) => {
		const timing = req.timing()
		const ms = timing.responseEnd > 0 ? timing.responseEnd : -1
		const response = await req.response().catch(() => null)
		done.push({
			method: req.method(),
			url: req.url().replace(/^https?:\/\/[^/]+/, ''),
			status: response?.status() ?? 0,
			ms: Math.round(ms),
			endedAt: Date.now(),
		})
	})
	return (from: number) => done
		.filter((r) => r.endedAt >= from && r.ms > BUDGET_MS)
		.map(({ endedAt: _endedAt, ...r }) => r)
}

/**
 * The navigation timing of the current document, relative to its start.
 *
 * @param page the page.
 * @return named marks in ms.
 */
async function navigationTiming(page: Page): Promise<Record<string, number>> {
	return page.evaluate(() => {
		const nav = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming
		return {
			ttfb: Math.round(nav.responseStart),
			domContentLoaded: Math.round(nav.domContentLoadedEventEnd),
			load: Math.round(nav.loadEventEnd),
			transferKb: Math.round(nav.transferSize / 1024),
		}
	})
}

/**
 * Time an action until its result is visible.
 *
 * @param action the action.
 * @param ready resolves once the view is usable.
 * @return elapsed ms.
 */
async function timed(action: () => Promise<unknown>, ready: () => Promise<unknown>): Promise<number> {
	const started = Date.now()
	await action()
	await ready()
	return Date.now() - started
}

/**
 * Wait until the menu and the first widget have rendered.
 *
 * @param page the page.
 * @return nothing.
 */
async function startPageReady(page: Page): Promise<void> {
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 60_000 })
	await expect(page.locator('.launchpad-grid-item').first()).toBeVisible({ timeout: 60_000 })
}

test('the main views open within the budget', async ({ page }, info) => {
	test.setTimeout(300_000)
	const slowSince = watchRequests(page)
	const steps: Step[] = []
	const record = async (name: string, from: number, ms: number, withNavigation = false) => {
		steps.push({
			name,
			ms,
			...(withNavigation ? { navigation: await navigationTiming(page) } : {}),
			slowRequests: slowSince(from),
		})
	}

	let from = Date.now()
	let ms = await timed(() => page.goto(START), () => startPageReady(page))
	await page.waitForLoadState('networkidle', { timeout: 60_000 })
	await record('start page, first load', from, ms, true)

	from = Date.now()
	ms = await timed(() => page.reload(), () => startPageReady(page))
	await page.waitForLoadState('networkidle', { timeout: 60_000 })
	await record('start page, warm load', from, ms, true)

	from = Date.now()
	ms = await timed(
		() => page.locator(TRIGGER).click(),
		() => expect(page.locator('[data-testid="cn-grouped-menu-item-config"]')).toBeVisible(),
	)
	await record('More menu', from, ms)

	from = Date.now()
	ms = await timed(
		() => page.locator('[data-testid="cn-grouped-menu-item-config"]').click(),
		() => expect(page.locator('.dashboard-config')).toBeVisible(),
	)
	await page.waitForLoadState('networkidle')
	await record('dashboard settings', from, ms)
	await page.keyboard.press('Escape')
	await expect(page.locator('.dashboard-config')).toBeHidden()

	await page.locator(TRIGGER).click()
	from = Date.now()
	ms = await timed(
		() => page.locator('[data-testid="cn-grouped-menu-item-add-widget"]').click(),
		() => expect(page.locator('[data-testid^="widget-picker-type-"]').first()).toBeVisible(),
	)
	await page.waitForLoadState('networkidle')
	await record('add-widget picker', from, ms)
	await page.keyboard.press('Escape')

	const text = JSON.stringify({ budgetMs: BUDGET_MS, steps }, null, 2)
	console.log(`[page-speed] ${text}`)
	await info.attach('page-speed', { body: text, contentType: 'application/json' })

	for (const step of steps) {
		expect.soft(step.ms, `${step.name}: action to usable`).toBeLessThanOrEqual(BUDGET_MS)
		expect.soft(step.slowRequests, `${step.name}: requests over ${BUDGET_MS} ms`).toEqual([])
	}
})
