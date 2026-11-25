/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end test for filter telemetry
 * (drill-down-cross-widget-filter, Task 28, REQ-CWF-010): with the
 * organisation's switch on, opening a bookmarked filter writes a `bookmark`
 * row and "Reset filters" a `reset` row, neither carrying a value; with the
 * switch off nothing is written.
 *
 * The `apply` row of a widget click is in cross-widget-filter-clicks.spec.ts.
 *
 * Gate-19 @e2e traceability:
 *   @e2e cross-widget-filter::telemetry-records-bookmark-and-reset
 *   @e2e cross-widget-filter::telemetry-off-writes-nothing
 *
 * @spec openspec/changes/archive/2026-11-25-drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md
 */

import type { Page } from '@playwright/test'
import type { Clause, SeededUser } from './support/filterBus.ts'

import { expect, test } from '@playwright/test'
import {
	apiAs,
	OBJECTS,
	openFiltered,
	provisionUserWithDashboard,
	putAdminSettings,
	removeUser,
} from './support/filterBus.ts'

const CLAUSES: Clause[] = [
	{ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355' },
	{ dimension: 'status', operator: 'eq', value: 'open' },
]

type FilterEvent = {
	eventType: string
	userId: string
	dashboardId: string
	filterDelta: Array<Record<string, unknown>>
}

let user: SeededUser | null = null

/**
 * The `filter_event` rows of the test user, read as the administrator.
 *
 * @return the rows.
 */
async function eventsOfUser(): Promise<FilterEvent[]> {
	const admin = await apiAs()
	try {
		const res = await admin.get(`${OBJECTS}/filter_event`, {
			params: { userId: user!.username, _limit: 100 },
		})
		expect(res.status(), await res.text()).toBe(200)
		const body = await res.json()
		const rows: FilterEvent[] = body.results ?? body.data ?? body.objects ?? []
		return rows.filter((row) => row.userId === user!.username)
	} finally {
		await admin.dispose()
	}
}

/**
 * Open the bookmark, then reset it.
 *
 * @param page the page.
 * @return nothing.
 */
async function resetFilters(page: Page): Promise<void> {
	await expect(page.locator('[data-testid="active-filters"] [data-dimension]')).toHaveCount(2)
	await page.getByRole('button', { name: 'Reset filters' }).click()
	await expect(page.locator('[data-testid="active-filters"]')).toHaveCount(0)
}

test.beforeAll(async () => {
	user = await provisionUserWithDashboard('e2e-cwf-telemetry')
})

test.afterAll(async () => {
	await putAdminSettings({ filterTelemetryEnabled: false })
	await removeUser(user)
})

test.describe.serial('filter telemetry (REQ-CWF-010)', () => {
	// @e2e cross-widget-filter::telemetry-records-bookmark-and-reset
	test('with telemetry on, a bookmark and a reset are recorded without values', async ({
		browser,
	}) => {
		await putAdminSettings({ filterTelemetryEnabled: true })
		const { context, page } = await openFiltered(browser, user!, CLAUSES)
		try {
			await expect
				.poll(async () => (await eventsOfUser()).map((row) => row.eventType), {
					timeout: 10_000,
				})
				.toContain('bookmark')
			await resetFilters(page)
			await expect
				.poll(async () => (await eventsOfUser()).map((row) => row.eventType), {
					timeout: 10_000,
				})
				.toContain('reset')
		} finally {
			await context.close()
		}

		const rows = await eventsOfUser()
		for (const row of rows) {
			expect(row.dashboardId).toBe(user!.dashboardUuid)
			const dimensions = row.filterDelta.map((entry) => entry.dimension).sort()
			expect(dimensions).toEqual(['gemeenteCode', 'status'])
			for (const entry of row.filterDelta) {
				expect(entry).not.toHaveProperty('value')
			}
		}
	})

	// @e2e cross-widget-filter::telemetry-off-writes-nothing
	test('with telemetry off, no further rows are written', async ({ browser }) => {
		await putAdminSettings({ filterTelemetryEnabled: false })
		const before = (await eventsOfUser()).length
		const { context, page } = await openFiltered(browser, user!, CLAUSES)
		try {
			await resetFilters(page)
			await page.waitForTimeout(2_000)
		} finally {
			await context.close()
		}
		expect((await eventsOfUser()).length).toBe(before)
	})
})
