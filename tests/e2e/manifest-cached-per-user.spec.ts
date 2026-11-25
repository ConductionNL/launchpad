/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A warm workspace load carries the manifest kept for the viewer
 * (initial-state-contract REQ-INIT-009), so it does not ask GET /api/manifest.
 *
 * Gate-19 @e2e traceability:
 *   @e2e initial-state-contract::a-warm-reload-does-not-ask-for-the-manifest
 *
 * @spec openspec/changes/archive/2026-11-25-manifest-cached-per-user/specs/initial-state-contract/spec.md
 */

import { expect, test } from '@playwright/test'

test('a warm reload does not ask for the manifest', async ({ page }) => {
	await page.goto('/index.php/apps/launchpad/')
	await expect(page.locator('[data-placement-id]').first()).toBeVisible({ timeout: 30_000 })

	const asked: string[] = []
	page.on('request', (request) => {
		if (/\/api\/manifest(\?|$)/.test(request.url())) {
			asked.push(request.url())
		}
	})
	await page.reload()
	await expect(page.locator('[data-placement-id]').first()).toBeVisible({ timeout: 30_000 })

	const carried = await page.locator('#initial-state-launchpad-runtimeManifest').getAttribute('value')
	expect(carried, 'the page carries the kept manifest').not.toBeNull()
	const manifest = JSON.parse(Buffer.from(String(carried), 'base64').toString('utf8'))
	expect(typeof manifest.$schema).toBe('string')
	expect(asked, 'no manifest request on the warm reload').toEqual([])
})
