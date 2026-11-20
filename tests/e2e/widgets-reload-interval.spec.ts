/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A Nextcloud dashboard widget with a reload interval refreshes its items
 * while it is on the page (openspec/specs/widgets REQ-WDG-029). Written for
 * e2e-coverage-programme, lane e3.
 *
 * HOW TIME PASSES
 * ===============
 * `page.clock.install()` before the page loads puts the page's timers on
 * Playwright's clock; `page.clock.runFor()` then lets 60 s (or more) pass in
 * an instant, firing every interval due on the way, the same `setInterval`
 * the renderer (src/components/WidgetRenderer.vue `setupAutoRefresh`) arms.
 *
 * WHAT IS STAGED
 * ==============
 * The CI instance has no widget whose reload interval this test can rely on,
 * so `GET /api/widgets` is answered with the server's real list plus one
 * descriptor of this run declaring the interval, and LaunchPad's
 * `GET /api/widgets/items` is answered for that widget with items numbered by
 * call. The placement itself is a real row on a real dashboard.
 *
 * Gate-19 @e2e traceability:
 *   @e2e widgets::items-refresh-on-the-widgets-interval
 *   @e2e widgets::no-interval-no-refresh
 *   @e2e widgets::leaving-the-dashboard-stops-the-timer
 *
 * @spec openspec/specs/widgets/spec.md
 */

import type { Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { Placement } from './support/widgetPlacements.ts'

import { expect, test } from '@playwright/test'
import { openDashboardSwitcher } from './support/dashboardSwitcher.ts'
import {
	adminApi,
	gridItem,
	isWidgetCatalogue,
	openWorkspace,
	Seeds,
} from './support/widgetPlacements.ts'

const RUN = Date.now()

let seeds: Seeds

/**
 * Declare a Nextcloud API widget with `reloadInterval` seconds, and serve its
 * items: call n answers one item titled `E2E reload item n`.
 *
 * @param page the page.
 * @param widgetId the widget id.
 * @param reloadInterval seconds between refreshes (0 for none).
 * @return a getter for the number of item requests made for the widget.
 */
async function stageWidget(
	page: Page,
	widgetId: string,
	reloadInterval: number,
): Promise<() => number> {
	let calls = 0
	await page.route(
		(url) => isWidgetCatalogue(url),
		async (route) => {
			const real = await route.fetch()
			const body = await real.json()
			const list = Array.isArray(body) ? body : Object.values(body ?? {})
			list.push({
				id: widgetId,
				title: `E2E reload ${RUN}`,
				order: 999,
				iconClass: 'icon-category-dashboard',
				iconUrl: null,
				widgetUrl: null,
				itemIconsRound: false,
				itemApiVersions: [1, 2],
				reloadInterval,
				buttons: [],
			})
			await route.fulfill({ response: real, body: JSON.stringify(list) })
		},
	)
	await page.route(
		(url) => url.pathname.endsWith('/apps/launchpad/api/widgets/items'),
		async (route) => {
			const asked = new URL(route.request().url()).searchParams.getAll(
				'widgets[]',
			)
			if (!asked.includes(widgetId)) {
				return route.continue()
			}
			calls++
			return route.fulfill({
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify({
					[widgetId]: {
						items: [
							{
								title: `E2E reload item ${calls}`,
								subtitle: '',
								link: '',
								iconUrl: '',
								sinceId: String(calls),
							},
						],
						emptyContentMessage: '',
						halfEmptyContentMessage: '',
					},
				}),
			})
		},
	)
	return () => calls
}

/**
 * Seed a dashboard holding one placement of `widgetId`.
 *
 * @param tag distinguishes the test.
 * @param widgetId the widget id.
 * @return the dashboard and placement.
 */
async function dashboardWith(
	tag: string,
	widgetId: string,
): Promise<{ dash: SeededDashboard; placement: Placement }> {
	const dash = await seeds.dashboard(`E2E reload ${tag} ${RUN}`)
	const placement = await seeds.widget(dash, {
		widgetId,
		gridWidth: 4,
		gridHeight: 4,
	})
	return { dash, placement }
}

/**
 * Mark the document so a reload (which would drop the mark) is detectable.
 *
 * @param page the page.
 * @return nothing.
 */
async function markDocument(page: Page): Promise<void> {
	await page.evaluate((run) => {
		;(window as unknown as Record<string, number>).__e2eSameDocument = run
	}, RUN)
}

test.beforeAll(async () => {
	seeds = new Seeds(await adminApi())
})

test.afterAll(async () => {
	await seeds?.cleanup()
})

test.describe('reload interval (REQ-WDG-029)', () => {
	test.describe.configure({ timeout: 120_000 })

	// @e2e widgets::items-refresh-on-the-widgets-interval
	test('a 60 s reload interval fetches and shows the items again without reloading the page', async ({
		page,
	}) => {
		const widgetId = `e2e-reload-60-${RUN}`
		const calls = await stageWidget(page, widgetId, 60)
		const { placement } = await dashboardWith('sixty', widgetId)
		await page.clock.install()
		await openWorkspace(page, placement.id)

		const cell = gridItem(page, placement.id)
		await expect(cell).toContainText('E2E reload item 1', { timeout: 15_000 })
		expect(calls()).toBe(1)
		await markDocument(page)

		await page.clock.runFor(60_000)
		await expect.poll(calls, { timeout: 10_000 }).toBe(2)
		await expect(cell).toContainText('E2E reload item 2')
		await expect(cell).not.toContainText('E2E reload item 1')

		const same = await page.evaluate(
			() => (window as unknown as Record<string, number>).__e2eSameDocument,
		)
		expect(same, 'the page must not have reloaded').toBe(RUN)
	})

	// @e2e widgets::no-interval-no-refresh
	test('with a reload interval of 0 the items are fetched once', async ({
		page,
	}) => {
		const widgetId = `e2e-reload-0-${RUN}`
		const calls = await stageWidget(page, widgetId, 0)
		const { placement } = await dashboardWith('zero', widgetId)
		await page.clock.install()
		await openWorkspace(page, placement.id)

		const cell = gridItem(page, placement.id)
		await expect(cell).toContainText('E2E reload item 1', { timeout: 15_000 })
		await page.clock.runFor(180_000)
		await page.waitForTimeout(1_000)
		expect(calls()).toBe(1)
		await expect(cell).toContainText('E2E reload item 1')
	})

	// @e2e widgets::leaving-the-dashboard-stops-the-timer
	test("switching to another dashboard stops the widget's refresh timer", async ({
		page,
	}) => {
		const widgetId = `e2e-reload-leave-${RUN}`
		const calls = await stageWidget(page, widgetId, 60)
		const other = await seeds.dashboard(`E2E reload other ${RUN}`)
		const { dash, placement } = await dashboardWith('leave', widgetId)
		await page.clock.install()
		await openWorkspace(page, placement.id)
		await expect(
			page.locator('[data-testid="launchpad-dashboard-region"]'),
		).toHaveAttribute('data-dashboard-uuid', dash.uuid)

		const cell = gridItem(page, placement.id)
		await expect(cell).toContainText('E2E reload item 1', { timeout: 15_000 })
		// The timer runs while the widget is on the page …
		await page.clock.runFor(60_000)
		await expect.poll(calls, { timeout: 10_000 }).toBe(2)

		// … Pieter switches to the other dashboard through the switcher …
		await openDashboardSwitcher(page)
		await page
			.locator('.dashboard-switcher-sidebar__item')
			.filter({ hasText: other.name })
			.first()
			.click()
		await expect(
			page.locator('[data-testid="launchpad-dashboard-region"]'),
		).toHaveAttribute('data-dashboard-uuid', other.uuid, { timeout: 15_000 })
		await expect(gridItem(page, placement.id)).toHaveCount(0)

		// … and no further refresh fires, however long he stays.
		await page.clock.runFor(180_000)
		await page.waitForTimeout(1_000)
		expect(calls()).toBe(2)
	})
})
