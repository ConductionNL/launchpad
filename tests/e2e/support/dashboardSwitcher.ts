/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Reach the dashboard switcher the way a person does on the start page.
 *
 * Every page has one "More" menu (REQ-SHELL-013); its Dashboards column lists
 * the favourites and unfolds in place into every dashboard (REQ-SHELL-014,
 * -015). There is no dashboards sidebar and no per-row menu any more.
 */

import type { Locator, Page } from '@playwright/test'

import { expect } from '@playwright/test'

/** The start page's More menu: present once the workspace shell rendered. */
export const MORE_MENU = '[data-testid="launchpad-more-menu"]'

/**
 * Open the More menu and unfold its Dashboards column into every dashboard.
 *
 * @param page The page, on the start page.
 * @return Resolves once the column is unfolded.
 */
export async function openDashboardSwitcher(page: Page): Promise<void> {
	const more = page.locator(MORE_MENU)
	await expect(more).toBeVisible({ timeout: 30_000 })
	await more.locator('[data-testid="launchpad-more-menu-trigger"]').click()
	// No slide-in panel any more (REVIEW-LAUNCHPAD items 4-5, REQ-SHELL-016):
	// "More" at the bottom of the Dashboards column unfolds it in place into
	// every dashboard the person can open, and the menu stays open.
	const unfold = page.locator('[data-testid="launchpad-more-menu-item-all-dashboards"]')
	await unfold.click()
	await expect(unfold).toHaveAttribute('aria-expanded', 'true', { timeout: 10_000 })
}

/**
 * Put the active dashboard in edit mode through More → Edit.
 *
 * @param page The page, on the start page, as someone who may edit it.
 * @return Resolves once the dashboard is in edit mode.
 */
export async function enterEditMode(page: Page): Promise<void> {
	const more = page.locator(MORE_MENU)
	await expect(more).toBeVisible({ timeout: 30_000 })
	await more.locator('[data-testid="launchpad-more-menu-trigger"]').click()
	await page.locator('[data-testid="launchpad-more-menu-item-edit"]').click()
	await expect(page.locator('.launchpad-edit-mode')).toBeVisible({ timeout: 10_000 })
}

/**
 * Open the dashboard settings the way a person does: edit mode, then
 * Dashboard settings in the edit bar (board LpBewerken).
 *
 * @param page The page, on the start page, as someone who may configure it.
 * @return Resolves once the settings window is open.
 */
export async function openDashboardSettings(page: Page): Promise<void> {
	if (await page.locator('.launchpad-edit-mode').count() === 0) {
		await enterEditMode(page)
	}
	await page.locator('[data-testid="launchpad-edit-bar-settings"]').click()
	await expect(page.locator('.dashboard-config')).toBeVisible({ timeout: 10_000 })
}

/**
 * Unfold Settings in an open More menu (board LpMeer), so its destinations
 * can be clicked.
 *
 * @param page The page, with More open.
 * @return Resolves once Settings is unfolded.
 */
export async function openSettingsGroup(page: Page): Promise<void> {
	const toggle = page.locator('[data-testid="launchpad-more-menu-settings"]:visible')
	if (await toggle.getAttribute('aria-expanded') !== 'true') {
		await toggle.click()
	}
	await expect(toggle).toHaveAttribute('aria-expanded', 'true')
}

/**
 * Download the open dashboard through More → Download (This dashboard): the
 * page header has no Download button of its own any more (REVIEW-LAUNCHPAD
 * item 8, REQ-SHELL-018).
 *
 * @param page The page, on a dashboard.
 * @return Resolves once Download was chosen.
 */
export async function downloadFromMore(page: Page): Promise<void> {
	const more = page.locator(MORE_MENU)
	await expect(more).toBeVisible({ timeout: 30_000 })
	await more.locator('[data-testid="launchpad-more-menu-trigger"]').click()
	await page.locator('[data-testid="launchpad-more-menu-item-download"]').click()
}

/** The rows of the More menu's Dashboards column (radio items). */
export const DASHBOARD_ROWS = '[data-testid="launchpad-more-menu-dashboard-list"] [role="menuitemradio"]'

/**
 * One dashboard's row in the More menu's Dashboards column, by its name.
 *
 * @param page The page, with More open.
 * @param name The dashboard's name.
 * @return The row.
 */
export function dashboardRow(page: Page, name: string): Locator {
	return page.locator(DASHBOARD_ROWS).filter({ hasText: name }).first()
}

/**
 * Close the More menu when it is open.
 *
 * @param page The page.
 * @return Resolves once no More menu panel is visible.
 */
export async function closeMore(page: Page): Promise<void> {
	const panel = page.locator('[data-testid="launchpad-more-menu-panel"]')
	if (await panel.isVisible().catch(() => false)) {
		await page.keyboard.press('Escape')
		await expect(panel).toBeHidden({ timeout: 5_000 })
	}
}

/**
 * Open a dashboard by name the way a person does: More, More in the
 * Dashboards column (every dashboard), then its row (REQ-SHELL-014, -015).
 * The dashboards sidebar and its per-row menus are gone (more-menu-favourites).
 *
 * @param page The page, on the workspace.
 * @param name The dashboard's name.
 * @return Resolves once the menu closed and the row is the checked one.
 */
export async function switchToDashboard(page: Page, name: string): Promise<void> {
	await openDashboardSwitcher(page)
	const row = dashboardRow(page, name)
	await expect(row).toBeVisible({ timeout: 10_000 })
	if (await row.getAttribute('aria-checked') === 'true') {
		await closeMore(page)
		return
	}
	await row.click()
	await expect(page.locator('[data-testid="launchpad-more-menu-panel"]')).toBeHidden({ timeout: 10_000 })
}

/**
 * Open the Add widget dialog of the open dashboard through More → Add widget.
 *
 * @param page The page, on a dashboard the person may edit.
 * @return Resolves once Add widget was chosen.
 */
export async function addWidgetFromMore(page: Page): Promise<void> {
	await closeMore(page)
	const more = page.locator(MORE_MENU)
	await expect(more).toBeVisible({ timeout: 30_000 })
	await more.locator('[data-testid="launchpad-more-menu-trigger"]').click()
	await page.locator('[data-testid="launchpad-more-menu-item-add-widget"]').click()
}
