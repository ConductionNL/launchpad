// SPDX-License-Identifier: EUPL-1.2
/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * Fixtures for the scheduled-exports end-to-end specs (tasks 27-30).
 *
 * Exports are created through the API so each spec starts from a known
 * configuration and drives in the UI only the behaviour its scenario is about:
 * running, previewing, the run history, retry and retention.
 *
 * @spec openspec/changes/scheduled-exports/tasks.md#task-27
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect } from '@playwright/test'

export const EXPORTS_API = '/index.php/apps/launchpad/api/exports'

/** A scheduled export as the API answers it. */
export interface SeededExport {
	id: string
	name: string
}

/**
 * Create a scheduled export on a dashboard.
 *
 * @param api a request context of the user who owns the export.
 * @param dashboardUuid the dashboard to export.
 * @param overrides fields that replace the defaults (recipients, render targets, retention, ...).
 * @return the created export's id and name.
 */
export async function createExport(
	api: APIRequestContext,
	dashboardUuid: string,
	overrides: Record<string, unknown> = {},
): Promise<SeededExport> {
	const name = `E2E export ${Date.now()}`
	const res = await api.post(EXPORTS_API, {
		data: {
			name,
			subjectType: 'dashboard',
			subjectId: dashboardUuid,
			recurrence: { type: 'weekly', daysOfWeek: ['MO'], timeOfDay: '09:00' },
			runTimezone: 'Europe/Amsterdam',
			renderTargets: [{ format: 'pdf' }],
			recipients: [{ channel: 'nextcloudFiles', channelConfig: { targetPath: '/E2E/Exports' } }],
			...overrides,
		},
	})
	expect(res.status(), await res.text()).toBe(201)
	const body = await res.json()
	return { id: String(body.id), name: String(body.name ?? name) }
}

/**
 * Delete a seeded export. Safe to call with a partially-created fixture.
 *
 * @param api a request context of the owner.
 * @param exported the value returned by `createExport`, or null.
 * @return nothing.
 */
export async function removeExport(api: APIRequestContext, exported: SeededExport | null): Promise<void> {
	if (!exported?.id) {
		return
	}
	await api.delete(`${EXPORTS_API}/${exported.id}`)
}

/**
 * Open the scheduled-export list from the dashboard's Meer menu.
 *
 * @param page the page, on the LaunchPad app.
 * @return the list modal.
 */
export async function openExportList(page: Page) {
	await page.goto('/index.php/apps/launchpad/', { waitUntil: 'domcontentloaded' })
	await page.locator('[data-testid="launchpad-more-menu"] button').first().click()
	await page.getByRole('menuitem', { name: /scheduled exports/i }).click()
	const list = page.locator('[data-testid="scheduled-exports"]')
	await expect(list).toBeVisible()
	return list
}

/**
 * The row of one export in the list.
 *
 * @param page the page with the list open.
 * @param name the export's name.
 * @return the row locator.
 */
export function exportRow(page: Page, name: string) {
	return page.locator('tr').filter({ has: page.locator('[data-testid="scheduled-export-name"]', { hasText: name }) })
}

/**
 * Click one of an export's row actions ("Run now", "Preview", "View runs", ...).
 *
 * @param page the page with the list open.
 * @param name the export's name.
 * @param testid the action's data-testid.
 * @return nothing.
 */
export async function rowAction(page: Page, name: string, testid: string): Promise<void> {
	await exportRow(page, name).locator('button').last().click()
	await page.locator(`[data-testid="${testid}"]`).click()
}

/**
 * Wait until the export's newest run is no longer running.
 *
 * @param api a request context of the owner.
 * @param exportId the export.
 * @return the newest run-row.
 */
export async function latestSettledRun(api: APIRequestContext, exportId: string): Promise<Record<string, any>> {
	let latest: Record<string, any> = {}
	await expect.poll(async () => {
		const res = await api.get(`${EXPORTS_API}/${exportId}/runs`)
		const runs = (await res.json()).results ?? []
		latest = runs[0] ?? {}
		return latest.status ?? 'none'
	}, { timeout: 60_000 }).not.toMatch(/^(none|running)$/)
	return latest
}
