/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Reach the dashboard switcher the way a person does on the start page.
 *
 * The start page has one "More" menu (lpvandaag-start-page); its Dashboards
 * group ends in "All dashboards", which opens the switcher sidebar with a row
 * and a row menu per dashboard.
 */

import type { Page } from '@playwright/test'

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
