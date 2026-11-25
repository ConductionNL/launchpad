/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workspace carries its org navigation in the initial state
 * (initial-state-contract REQ-INIT-007), so the shell places the org rail
 * without asking the two org-navigation endpoints on the first load.
 *
 * Gate-19 @e2e traceability:
 *   @e2e initial-state-contract::the-workspace-opens-without-asking-for-its-org-navigation
 *
 * @spec openspec/changes/archive/2026-11-25-org-navigation-from-initial-state/specs/initial-state-contract/spec.md
 */

import { expect, test } from '@playwright/test'

test('the workspace opens without asking for its org navigation', async ({ page }) => {
	const asked: string[] = []
	page.on('request', (request) => {
		if (/\/api\/admin\/org-navigation(\/position)?(\?|$)/.test(request.url())) {
			asked.push(request.url())
		}
	})

	await page.goto('/index.php/apps/launchpad/')
	await expect(page.locator('main, [role="main"]').first()).toBeVisible({ timeout: 30_000 })
	// The view asks for these while it boots, before its grid renders.
	await expect(page.locator('[data-placement-id]').first()).toBeVisible({ timeout: 30_000 })

	const carried = await page.locator('#initial-state-launchpad-orgNavigation').getAttribute('value')
	expect(carried, 'the page carries the org navigation').not.toBeNull()
	const orgNavigation = JSON.parse(Buffer.from(String(carried), 'base64').toString('utf8'))
	expect(['left', 'right', 'top', 'hidden']).toContain(orgNavigation.position)
	expect(Array.isArray(orgNavigation.tree)).toBe(true)
	expect(asked, 'no org-navigation request on the first load').toEqual([])
})
