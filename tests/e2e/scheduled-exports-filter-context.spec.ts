// SPDX-License-Identifier: EUPL-1.2
/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * Task 28: per-recipient filter contexts. Two recipients with distinct
 * filter contexts produce two renders in one run; two with the same context
 * share one render.
 *
 * Scenarios covered:
 *   @e2e scheduled-exports::two-recipients-with-distinct-filter-contexts-produce-two-renders
 *   @e2e scheduled-exports::two-recipients-with-identical-filter-contexts-share-one-render
 *
 * @spec openspec/changes/scheduled-exports/specs/scheduled-exports/spec.md
 */

import type { APIRequestContext } from '@playwright/test'
import type { SeededExport } from './fixtures/scheduledExports.ts'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import { createExport, latestSettledRun, openExportList, removeExport, rowAction } from './fixtures/scheduledExports.ts'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { adminContext } from './support/principals.ts'

let api: APIRequestContext
let dashboard: SeededDashboard | null = null
const created: SeededExport[] = []

/**
 * A Files recipient filtered to one gemeente.
 *
 * @param gemeente the filter value.
 * @param folder the target folder.
 * @return the recipient.
 */
function recipient(gemeente: string, folder: string) {
	return { channel: 'nextcloudFiles', channelConfig: { targetPath: `/E2E/${folder}` }, filterContext: { gemeente } }
}

test.beforeAll(async () => {
	api = await adminContext()
	dashboard = await seedActiveDashboard(api, `E2E export filters ${Date.now()}`)
})

test.afterAll(async () => {
	for (const exported of created) {
		await removeExport(api, exported)
	}
	await removeSeededDashboard(api, dashboard)
	await api.dispose()
})

test('two recipients with distinct filter contexts get two renders', async ({ page }) => {
	const exported = await createExport(api, dashboard!.uuid, {
		recipients: [recipient('Gemeente A', 'A'), recipient('Gemeente B', 'B')],
	})
	created.push(exported)

	await openExportList(page)
	await rowAction(page, exported.name, 'scheduled-export-run')
	const run = await latestSettledRun(api, exported.id)

	expect(run.renderArtefacts).toHaveLength(2)
	const keys = new Set(run.renderArtefacts.map((a: { filterKey: string }) => a.filterKey))
	expect(keys.size).toBe(2)
	expect(run.deliveries).toHaveLength(2)

	await rowAction(page, exported.name, 'scheduled-export-runs')
	await page.locator('[data-testid="export-run-open"]').first().click()
	await expect(page.locator('[data-testid="run-detail-artefact"]')).toHaveCount(2)
})

test('two recipients with the same filter context share one render', async ({ page }) => {
	const exported = await createExport(api, dashboard!.uuid, {
		recipients: [recipient('Gemeente A', 'A1'), recipient('Gemeente A', 'A2')],
	})
	created.push(exported)

	await openExportList(page)
	await rowAction(page, exported.name, 'scheduled-export-run')
	const run = await latestSettledRun(api, exported.id)

	expect(run.renderArtefacts).toHaveLength(1)
	expect(run.deliveries).toHaveLength(2)
})
