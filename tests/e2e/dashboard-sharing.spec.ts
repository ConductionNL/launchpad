/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Playwright end-to-end tests for the dashboard-sharing UI (dashboard-sharing
 * OpenSpec change add-dashboard-sharing-e2e-coverage).
 *
 * These drive the REAL browser flow through DashboardConfigModal's Sharing
 * tab (`data-test="config-panel-sharing"`) — router → middleware → controller
 * → Vue re-render — closing the gap left by the backend-only PHPUnit/Newman
 * coverage. They replace the spec's stale blanket `@e2e exclude ... sharing
 * UI modals not present in v1.0.5` claim, which is false at HEAD: the sharing
 * tab (sharee picker, per-share permission NcSelect, remove button) has
 * shipped since before this sweep.
 *
 * Scenarios (REQ-SHARE-001, REQ-SHARE-002, REQ-SHARE-004):
 *  - Owner adds a user share and it appears in the shares list.
 *  - Owner changes a share's permission level and it persists across reload.
 *  - Owner removes a share and it stays gone across reload.
 *  - Recipient sees the shared dashboard (skipped when the fixture has no
 *    second seeded user — honest test.skip, per the house pattern).
 *
 * NOTE: These tests require a running Nextcloud instance with the mydash app
 * installed, the authenticated owner storage state from
 * `tests/e2e/global-setup.ts`, and at least one recipient account
 * ("recipient" by default, overridable via LAUNCHPAD_E2E_SHAREE) that the
 * owner can share to. In CI the Hydra pipeline wires this up; locally run
 * `npm run test:e2e` after starting the dev stack.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-sharing::owner-adds-a-user-share
 *   @e2e dashboard-sharing::owner-changes-permission-level-persists
 *   @e2e dashboard-sharing::owner-removes-a-share-persists
 *   @e2e dashboard-sharing::recipient-sees-shared-dashboard
 *
 * @spec openspec/changes/add-dashboard-sharing-e2e-coverage/tasks.md
 */

import { test, expect, type Page } from '@playwright/test'

// The recipient account the owner shares to. Overridable so the same spec
// works against fixtures that seed a different second user.
const SHAREE = process.env.LAUNCHPAD_E2E_SHAREE ?? 'recipient'

/**
 * Open the active personal dashboard's config modal and switch to the
 * Sharing tab.
 *
 * @param {Page} page Playwright page fixture.
 * @return {Promise<import('@playwright/test').Locator>} the sharing panel locator.
 */
async function openSharingTab(page: Page) {
	await page.goto('/index.php/apps/mydash')
	try {
		await page.waitForSelector('.mydash-sidebar-toggle', { timeout: 20_000 })
	} catch {
		await page.goto('/index.php/apps/mydash')
		await page.waitForSelector('.mydash-sidebar-toggle', { timeout: 20_000 })
	}

	await page.locator('.mydash-sidebar-toggle').first().click()
	await page.waitForSelector('.dashboard-switcher-sidebar.open', { timeout: 8_000 })

	// Open the active personal dashboard's cog menu → "Dashboard settings".
	const activeRow = page.locator(
		'[data-source="user"].dashboard-switcher-sidebar__item.active, [data-source="user"].dashboard-switcher-sidebar__item',
	).first()
	await activeRow.locator('.dashboard-row-actions button').first().click()
	await page.locator('[data-testid="cog-dashboard-config"]').click()

	// The modal opens on the General tab; switch to Sharing.
	await page.locator('[data-testid="dashboard-name-input"]').waitFor({ state: 'visible', timeout: 8_000 })
	await page.locator('[data-test="config-tab-sharing"]').click()
	const panel = page.locator('[data-test="config-panel-sharing"]')
	await expect(panel).toBeVisible({ timeout: 5_000 })
	return panel
}

/**
 * Use the sharee NcSelect to search for and pick a recipient.
 *
 * @param {Page} page Playwright page fixture.
 * @param {import('@playwright/test').Locator} panel the sharing panel.
 * @param {string} sharee the user id to search for and select.
 */
async function addSharee(page: Page, panel: import('@playwright/test').Locator, sharee: string) {
	const combobox = panel.getByLabel(/share with users and groups/i).first()
	await combobox.click()
	await combobox.fill(sharee)
	// The autocomplete option carries the recipient's display name; pick the
	// first matching option row.
	const option = page.locator('.sharee-option', { hasText: new RegExp(sharee, 'i') }).first()
	await expect(option).toBeVisible({ timeout: 8_000 })
	await option.click()
}

/**
 * Persist the config modal via its Save button and wait for it to close.
 *
 * @param {Page} page Playwright page fixture.
 */
async function saveConfig(page: Page) {
	const save = page.locator('[data-testid="dashboard-save-button"]')
	await expect(save).toBeEnabled({ timeout: 5_000 })
	await save.click()
	await expect(page.locator('[data-testid="dashboard-name-input"]')).not.toBeVisible({ timeout: 8_000 })
}

test.describe('dashboard-sharing UI (REQ-SHARE-001/002/004)', () => {
	test('owner adds a user share and it appears in the shares list', async ({ page }) => {
		test.setTimeout(60_000)
		const panel = await openSharingTab(page)

		await addSharee(page, panel, SHAREE)

		const shares = panel.locator('.dashboard-config__shares')
		await expect(shares).toContainText(new RegExp(SHAREE, 'i'), { timeout: 8_000 })

		await saveConfig(page)

		// Reopen and confirm the share persisted.
		const panel2 = await openSharingTab(page)
		await expect(panel2.locator('.dashboard-config__shares')).toContainText(new RegExp(SHAREE, 'i'), { timeout: 8_000 })
	})

	test('owner changes a share permission level and it persists across reload', async ({ page }) => {
		test.setTimeout(60_000)
		const panel = await openSharingTab(page)

		// Ensure a share exists to modify.
		const shares = panel.locator('.dashboard-config__shares')
		if (!(await shares.isVisible().catch(() => false))) {
			await addSharee(page, panel, SHAREE)
			await saveConfig(page)
			await openSharingTab(page)
		}

		const row = page.locator('.dashboard-config__share', { hasText: new RegExp(SHAREE, 'i') }).first()
		await expect(row).toBeVisible({ timeout: 8_000 })

		// Open the per-share permission-level NcSelect and pick "Full".
		await row.locator('.dashboard-config__share-level').click()
		await page.getByRole('option', { name: /full/i }).first().click()

		await saveConfig(page)

		// Reopen and confirm the level persisted.
		await openSharingTab(page)
		const persistedRow = page.locator('.dashboard-config__share', { hasText: new RegExp(SHAREE, 'i') }).first()
		await expect(persistedRow.locator('.dashboard-config__share-level')).toContainText(/full/i, { timeout: 8_000 })
	})

	test('owner removes a share and it stays gone across reload', async ({ page }) => {
		test.setTimeout(60_000)
		const panel = await openSharingTab(page)

		const shares = panel.locator('.dashboard-config__shares')
		if (!(await shares.isVisible().catch(() => false))) {
			await addSharee(page, panel, SHAREE)
			await saveConfig(page)
			await openSharingTab(page)
		}

		const row = page.locator('.dashboard-config__share', { hasText: new RegExp(SHAREE, 'i') }).first()
		await expect(row).toBeVisible({ timeout: 8_000 })
		await row.getByRole('button', { name: /remove share/i }).click()

		await saveConfig(page)

		// Reopen: the recipient must no longer be listed.
		const panel2 = await openSharingTab(page)
		await expect(
			panel2.locator('.dashboard-config__share', { hasText: new RegExp(SHAREE, 'i') }),
		).toHaveCount(0, { timeout: 8_000 })
	})

	test('recipient sees the shared dashboard in their switcher', async ({ browser }) => {
		// A recipient session needs a distinct authenticated storage state that
		// the current owner-only fixture does not provide. Skip honestly rather
		// than silently omit — matching the house pattern elsewhere in tests/e2e.
		test.skip(
			!process.env.LAUNCHPAD_E2E_SHAREE_STORAGE,
			'No recipient storageState fixture (set LAUNCHPAD_E2E_SHAREE_STORAGE) — recipient-side visibility is covered by DashboardShareServiceFollowupsTest at the service layer.',
		)

		const context = await browser.newContext({ storageState: process.env.LAUNCHPAD_E2E_SHAREE_STORAGE })
		const page = await context.newPage()
		await page.goto('/index.php/apps/mydash')
		await page.locator('.mydash-sidebar-toggle').first().click()
		await page.waitForSelector('.dashboard-switcher-sidebar.open', { timeout: 8_000 })
		// A shared (non-owned) dashboard shows up as a shared-source row.
		await expect(
			page.locator('.dashboard-switcher-sidebar__item[data-source="shared"]').first(),
		).toBeVisible({ timeout: 8_000 })
		await context.close()
	})
})
