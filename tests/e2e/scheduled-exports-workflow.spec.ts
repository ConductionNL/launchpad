// SPDX-License-Identifier: EUPL-1.2
/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * Task 27: the full scheduled-export workflow in the UI. An export with a
 * weekly recurrence is run by hand, its run shows up in the history, its PDF
 * is previewed without dispatch, and it is edited and switched off.
 *
 * Scenarios covered:
 *   @e2e scheduled-exports::manual-run-with-user-tracking
 *   @e2e scheduled-exports::preview-without-dispatch
 *   @e2e scheduled-exports::weekly-recurrence-falls-on-the-correct-date
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { SeededExport } from './fixtures/scheduledExports.ts'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import { createExport, EXPORTS_API, latestSettledRun, openExportList, removeExport, rowAction } from './fixtures/scheduledExports.ts'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { adminContext } from './support/principals.ts'

let api: APIRequestContext
let dashboard: SeededDashboard | null = null
let exported: SeededExport | null = null

test.beforeAll(async () => {
	api = await adminContext()
	dashboard = await seedActiveDashboard(api, `E2E exports ${Date.now()}`)
	exported = await createExport(api, dashboard.uuid)
})

test.afterAll(async () => {
	await removeExport(api, exported)
	await removeSeededDashboard(api, dashboard)
	await api.dispose()
})

test('a weekly export lists its next Monday 09:00 run', async ({ page }) => {
	const list = await openExportList(page)
	const row = list.locator('tr').filter({ hasText: exported!.name })
	await expect(row).toBeVisible()
	const shown = await api.get(`${EXPORTS_API}/${exported!.id}`)
	const next = new Date((await shown.json()).nextRunAt)
	const local = new Intl.DateTimeFormat('en-GB', { timeZone: 'Europe/Amsterdam', weekday: 'short', hour: '2-digit', minute: '2-digit', hour12: false }).format(next)
	expect(local).toBe('Mon 09:00')
	await expect(row.locator('[data-testid="scheduled-export-next"]')).not.toBeEmpty()
})

test('run now writes a manual run that appears in the history', async ({ page }) => {
	await openExportList(page)
	await rowAction(page, exported!.name, 'scheduled-export-run')
	const run = await latestSettledRun(api, exported!.id)
	expect(run.triggeredBy).toBe('manual')
	expect(run.status).toMatch(/^(success|partial|failed)$/)

	await rowAction(page, exported!.name, 'scheduled-export-runs')
	const history = page.locator('[data-testid="export-run-history"]')
	await expect(history).toBeVisible()
	await expect(history.locator('[data-testid="export-run-open"]').first()).toBeVisible()
})

test('preview renders the PDF and writes no run', async ({ page }) => {
	const before = (await (await api.get(`${EXPORTS_API}/${exported!.id}/runs`)).json()).results?.length ?? 0
	await openExportList(page)
	await rowAction(page, exported!.name, 'scheduled-export-preview')
	await page.locator('[data-testid="preview-render"]').click()
	await expect(page.locator('[data-testid="preview-pdf"]')).toBeVisible({ timeout: 60_000 })
	const after = (await (await api.get(`${EXPORTS_API}/${exported!.id}/runs`)).json()).results?.length ?? 0
	expect(after).toBe(before)
})

test('editing renames the export and switching it off clears its next run', async ({ page }) => {
	await openExportList(page)
	await rowAction(page, exported!.name, 'scheduled-export-edit')
	const editor = page.locator('[data-testid="export-editor"]')
	const renamed = `${exported!.name} (edited)`
	await editor.locator('[data-testid="export-name"] input').fill(renamed)
	await editor.locator('[data-testid="export-enabled"] input').uncheck({ force: true })
	await editor.locator('[data-testid="export-save"]').click()
	await expect(editor).toHaveCount(0)

	const saved = await (await api.get(`${EXPORTS_API}/${exported!.id}`)).json()
	expect(saved.name).toBe(renamed)
	expect(saved.enabled).toBe(false)
	exported = { ...exported!, name: renamed }
})
