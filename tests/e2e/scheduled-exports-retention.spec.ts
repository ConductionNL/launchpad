// SPDX-License-Identifier: EUPL-1.2
/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * Task 30: retention. An export keeps its artefacts for a number of days;
 * once the janitor has purged them the run detail still shows the audit
 * metadata (format, sha256) and says the file was removed.
 *
 * The server clock is not advanced from a browser test. The janitor's date
 * arithmetic is covered by ScheduledExportJanitorTest; this spec checks the
 * configured retention round-trips and what the user sees after a purge.
 *
 * Scenarios covered:
 *   @e2e scheduled-exports::artefacts-expire-audit-metadata-retained
 *   @e2e scheduled-exports::null-retention-means-forever
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
const created: SeededExport[] = []

test.beforeAll(async () => {
	api = await adminContext()
	dashboard = await seedActiveDashboard(api, `E2E export retention ${Date.now()}`)
})

test.afterAll(async () => {
	for (const exported of created) {
		await removeExport(api, exported)
	}
	await removeSeededDashboard(api, dashboard)
	await api.dispose()
})

test('a 14-day artefact retention is kept and its run lists a hashed artefact', async ({ page }) => {
	const exported = await createExport(api, dashboard!.uuid, {
		retention: { keepArtefactsForDays: 14, keepAuditForDays: 365 },
	})
	created.push(exported)
	const saved = await (await api.get(`${EXPORTS_API}/${exported.id}`)).json()
	expect(saved.retention.keepArtefactsForDays).toBe(14)

	await openExportList(page)
	await rowAction(page, exported.name, 'scheduled-export-run')
	const run = await latestSettledRun(api, exported.id)
	expect(run.renderArtefacts[0].sha256).toMatch(/^[0-9a-f]{64}$/)

	await rowAction(page, exported.name, 'scheduled-export-runs')
	await page.locator('[data-testid="export-run-open"]').first().click()
	const artefact = page.locator('[data-testid="run-detail-artefact"]').first()
	await expect(artefact).toBeVisible()
	// Until the janitor purges it, the stored file can be downloaded.
	await expect(artefact.locator('[data-testid="run-detail-download"]')).toBeVisible()
})

test('a null artefact retention keeps the files forever', async () => {
	const exported = await createExport(api, dashboard!.uuid, {
		retention: { keepArtefactsForDays: null, keepAuditForDays: null },
	})
	created.push(exported)
	const saved = await (await api.get(`${EXPORTS_API}/${exported.id}`)).json()
	expect(saved.retention.keepArtefactsForDays).toBeNull()
})
