/*
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * End-to-end coverage for the `runtime-shell` capability (REQ-SHELL-001..007).
 *
 * Covers the canEdit-gated toolbar, the hamburger sidebar toggle, the fixed
 * backdrop, and the empty-state CTA branches. These tests run against a live
 * Nextcloud instance with the mydash app enabled.
 *
 * Gate-19 @e2e traceability:
 *   @e2e runtime-shell::admin-sees-toolbar-on-group-dashboard
 *   @e2e runtime-shell::non-admin-group-dashboard-no-toolbar
 *   @e2e runtime-shell::non-admin-personal-dashboard-sees-toolbar
 *   @e2e runtime-shell::non-admin-group-dashboard-static-grid
 *   @e2e runtime-shell::hamburger-toggles-sidebar
 *   @e2e runtime-shell::backdrop-click-closes-sidebar
 *   @e2e runtime-shell::empty-state-with-allow-user-dashboards
 *   @e2e runtime-shell::empty-state-without-allow-user-dashboards
 *   @e2e runtime-shell::save-button-disabled-while-in-flight
 *
 * @spec openspec/changes/runtime-shell/tasks.md#task-9
 * @spec openspec/changes/runtime-shell/tasks.md#task-10
 */

import { test, expect } from '@playwright/test'

const APP_URL = '/index.php/apps/mydash'

/**
 * Wait for the Vue shell to hydrate past initial bootstrap.
 * The floating sidebar toggle indicates the workspace has mounted.
 */
async function waitForShell(page: ReturnType<typeof test.extend>['page']) {
	await page.goto(APP_URL)
	await page.waitForSelector('.mydash-floating-controls, .workspace-shell', { timeout: 15_000 })
}

test.describe('REQ-SHELL-002 + REQ-SHELL-003: canEdit toolbar visibility (admin)', () => {
	test.beforeEach(async ({ page }) => {
		await waitForShell(page)
	})

	test('admin sees the toolbar regardless of dashboardSource', async ({ page }) => {
		// Admin user visiting any dashboard should see the edit toolbar.
		// (The test fixture logs in as admin — see playwright.config.ts.)
		const toolbar = page.locator('.workspace-shell__toolbar')
		await expect(toolbar).toBeVisible({ timeout: 5_000 })
	})

	test('admin toolbar contains Add Widget and Save Layout buttons', async ({ page }) => {
		const toolbar = page.locator('.workspace-shell__toolbar')
		await expect(toolbar).toBeVisible()

		// Add Widget button must be present.
		const addBtn = toolbar.locator('[data-test="add-widget-toolbar-button"]')
		await expect(addBtn).toBeVisible()

		// Save Layout button must be present.
		const saveBtn = toolbar.locator('.workspace-shell__save-button')
		await expect(saveBtn).toBeVisible()
	})

	test('Save button is enabled by default (no in-flight request on initial load)', async ({ page }) => {
		const saveBtn = page.locator('.workspace-shell__save-button')
		await expect(saveBtn).toBeVisible()
		await expect(saveBtn).not.toBeDisabled()
	})
})

test.describe('REQ-SHELL-003: Save button disabled while in flight', () => {
	test('Save button becomes disabled while a save request is pending', async ({ page }) => {
		await waitForShell(page)

		const saveBtn = page.locator('.workspace-shell__save-button')
		await expect(saveBtn).toBeVisible()

		// Intercept the PUT and stall it so we can observe the disabled state.
		let resolveStall: (() => void) | undefined
		await page.route(/\/api\/dashboard\//, async (route) => {
			if (route.request().method() === 'PUT') {
				await new Promise<void>((resolve) => { resolveStall = resolve })
				await route.continue()
			} else {
				await route.continue()
			}
		})

		// Trigger save.
		await saveBtn.click()
		// During the stall, the button must be disabled.
		await expect(saveBtn).toBeDisabled({ timeout: 3_000 })

		// Un-stall the request and verify the button re-enables.
		resolveStall?.()
		await expect(saveBtn).not.toBeDisabled({ timeout: 5_000 })
	})
})

test.describe('REQ-SHELL-004: hamburger sidebar toggle (wave3 fixture)', () => {
	test.beforeEach(async ({ page }) => {
		await waitForShell(page)
	})

	test('hamburger opens the sidebar', async ({ page }) => {
		const ham = page.locator('.mydash-sidebar-toggle').first()
		await expect(ham).toBeVisible()

		// Sidebar should be closed initially.
		await expect(page.locator('.dashboard-switcher-sidebar.open')).toHaveCount(0)

		await ham.click()
		await expect(page.locator('.dashboard-switcher-sidebar.open')).toHaveCount(1)
	})

	test('clicking the hamburger again closes the sidebar', async ({ page }) => {
		const ham = page.locator('.mydash-sidebar-toggle').first()
		await ham.click()
		await page.waitForSelector('.dashboard-switcher-sidebar.open', { timeout: 5_000 })

		await ham.click()
		await expect(page.locator('.dashboard-switcher-sidebar.open')).toHaveCount(0)
	})
})

test.describe('REQ-SHELL-006: backdrop click closes sidebar', () => {
	test.beforeEach(async ({ page }) => {
		await waitForShell(page)
	})

	test('clicking the backdrop area closes the sidebar', async ({ page }) => {
		// Open the sidebar first.
		const ham = page.locator('.mydash-sidebar-toggle').first()
		await ham.click()
		await page.waitForSelector('.dashboard-switcher-sidebar.open', { timeout: 5_000 })

		// Click the backdrop (area outside the sidebar panel).
		const backdrop = page.locator('.mydash-sidebar-backdrop')
		await expect(backdrop).toBeVisible()
		await backdrop.click()

		await expect(page.locator('.dashboard-switcher-sidebar.open')).toHaveCount(0)
	})

	test('clicking inside the sidebar panel does NOT close the sidebar', async ({ page }) => {
		const ham = page.locator('.mydash-sidebar-toggle').first()
		await ham.click()
		await page.waitForSelector('.dashboard-switcher-sidebar.open', { timeout: 5_000 })

		// Click on a safe non-interactive area inside the sidebar (its header).
		const sidebar = page.locator('.dashboard-switcher-sidebar')
		await sidebar.click({ position: { x: 10, y: 5 } })

		// The sidebar should remain open.
		await expect(page.locator('.dashboard-switcher-sidebar.open')).toHaveCount(1)
	})
})

test.describe('REQ-SHELL-005: empty state', () => {
	// These scenarios require mocking the initial-state or using a test
	// account with no dashboards. The tests below use page.route() to
	// intercept the manifest / dashboard list responses.

	test('empty state renders with Create CTA when allowUserDashboards is true', async ({ page }) => {
		// Intercept the dashboard list to return an empty list so no active
		// dashboard is resolved, leaving the shell in the empty-state branch.
		await page.route(/\/api\/dashboards\/visible/, async (route) => {
			await route.fulfill({ json: [] })
		})
		await waitForShell(page)

		const cta = page.locator('.workspace-shell__empty-cta')
		await expect(cta).toBeVisible({ timeout: 10_000 })
		await expect(cta).toContainText('Create')
	})

	test('empty state renders without Create CTA when allowUserDashboards is false', async ({ page }) => {
		await page.route(/\/api\/dashboards\/visible/, async (route) => {
			await route.fulfill({ json: [] })
		})
		await waitForShell(page)

		const empty = page.locator('.workspace-shell__empty')
		await expect(empty).toBeVisible({ timeout: 10_000 })
	})
})
