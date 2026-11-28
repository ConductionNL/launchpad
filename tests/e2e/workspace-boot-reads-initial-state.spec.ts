/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workspace carries its view's boot reads in the initial state
 * (initial-state-contract REQ-INIT-008), so the view opens without asking
 * for its tiles, pending acknowledgements and default dashboard.
 *
 * Gate-19 @e2e traceability:
 *   @e2e initial-state-contract::the-workspace-opens-without-asking-for-its-tiles-acknowledgements-and-default-dashboard
 *
 * @spec openspec/changes/archive/2026-11-25-workspace-boot-reads-from-initial-state/specs/initial-state-contract/spec.md
 */

import { expect, test } from '@playwright/test'

test('the workspace opens without asking for its tiles, acknowledgements and default dashboard', async ({ page }) => {
	const asked: string[] = []
	page.on('request', (request) => {
		if (request.method() === 'GET' && /\/api\/(tiles|acknowledgements\/pending|dashboards\/default)(\?|$)/.test(request.url())) {
			asked.push(request.url())
		}
	})

	await page.goto('/index.php/apps/launchpad/')
	await expect(page.locator('main, [role="main"]').first()).toBeVisible({ timeout: 30_000 })
	// The view asks for these while it boots, before its grid renders.
	await expect(page.locator('[data-placement-id]').first()).toBeVisible({ timeout: 30_000 })

	const carried = await page.locator('#initial-state-launchpad-workspaceBootReads').getAttribute('value')
	expect(carried, 'the page carries the boot reads').not.toBeNull()
	const reads = JSON.parse(Buffer.from(String(carried), 'base64').toString('utf8'))
	expect(Array.isArray(reads.tiles)).toBe(true)
	expect(Array.isArray(reads.pendingAcknowledgements?.items)).toBe(true)
	expect(typeof reads.defaultDashboard?.uuid).toBe('string')
	expect(asked, 'no tile, acknowledgement or default-dashboard request on the first load').toEqual([])
})
