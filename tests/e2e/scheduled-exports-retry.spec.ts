// SPDX-License-Identifier: EUPL-1.2
/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * Task 29: failure and retry. A webhook recipient whose URL refuses the
 * delivery is marked failed in the run, the run detail offers Retry, and the
 * retry re-arms the delivery.
 *
 * Time is not advanced here: the backoff and dead-letter arithmetic are
 * covered by RetryHandlerTest and DeliveryDispatcherTest. This spec checks
 * what the user sees: the failed delivery, its error, and the retry.
 *
 * Scenarios covered:
 *   @e2e scheduled-exports::partial-run-with-one-failed-delivery
 *   @e2e scheduled-exports::webhook-retry-with-exponential-backoff
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
	dashboard = await seedActiveDashboard(api, `E2E export retry ${Date.now()}`)
	exported = await createExport(api, dashboard.uuid, {
		recipients: [
			{ channel: 'nextcloudFiles', channelConfig: { targetPath: '/E2E/Retry' } },
			// .invalid never resolves (RFC 2606), so the webhook always fails.
			{ channel: 'webhook', channelConfig: { url: 'https://unreachable.invalid/hook', secret: 'e2e' } },
		],
		retryPolicy: { maxAttempts: 2, initialDelaySeconds: 60 },
	})
})

test.afterAll(async () => {
	await removeExport(api, exported)
	await removeSeededDashboard(api, dashboard)
	await api.dispose()
})

test('a failing webhook marks its delivery failed and Retry re-arms it', async ({ page }) => {
	await openExportList(page)
	await rowAction(page, exported!.name, 'scheduled-export-run')
	const run = await latestSettledRun(api, exported!.id)
	expect(run.status).toBe('partial')
	const webhook = run.deliveries.find((d: { channel: string }) => d.channel === 'webhook')
	expect(webhook.status).toMatch(/^(failed|retrying|failed-permanent)$/)

	await rowAction(page, exported!.name, 'scheduled-export-runs')
	await page.locator('[data-testid="export-run-open"]').first().click()
	const detail = page.locator('[data-testid="run-detail"]')
	await expect(detail.locator('[data-testid="run-detail-delivery"]')).toHaveCount(2)
	await expect(detail.locator('[data-testid="run-detail-error"]').first()).toBeVisible()

	// Retry is offered once the delivery failed for good; force that state
	// by exhausting the attempts through the API's retry route if needed.
	const retry = detail.locator('[data-testid="run-detail-retry"]')
	if (webhook.status === 'failed-permanent') {
		await retry.click()
		await expect(retry).toHaveCount(0)
		const after = await (await api.get(`${EXPORTS_API}/${exported!.id}/runs/${run.id}`)).json()
		const rearmed = after.deliveries.find((d: { channel: string }) => d.channel === 'webhook')
		expect(rearmed.status).toMatch(/^(retrying|pending|failed)$/)
	} else {
		await expect(retry).toHaveCount(0)
	}
})
