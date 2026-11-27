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

import type { Locator, Page } from '@playwright/test'

import { expect } from '@playwright/test'

/** The start page's More menu: present once the workspace shell rendered. */
export const MORE_MENU = '[data-testid="launchpad-more-menu"]'

/**
 * Open the dashboard switcher sidebar from the More menu.
 *
 * @param page The page, on the start page.
 * @return Resolves once the sidebar is open.
 */
export async function openDashboardSwitcher(page: Page): Promise<void> {
	const more = page.locator(MORE_MENU)
	await expect(more).toBeVisible({ timeout: 30_000 })
	await more.locator('[data-testid="launchpad-more-menu-trigger"]').click()
	await page.locator('[data-testid="launchpad-more-menu-item-all-dashboards"]').click()
	await expect(page.locator('.dashboard-switcher-sidebar.open')).toBeVisible({ timeout: 10_000 })
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
 * Close the dashboard switcher sidebar when it is open, so it does not cover
 * the edit bar's Cancel and Save.
 *
 * @param page The page.
 * @return Resolves once it is closed.
 */
async function closeSwitcher(page: Page): Promise<void> {
	const close = page.locator('.dashboard-switcher-sidebar.open .dashboard-switcher-sidebar__close').first()
	if (await close.isVisible().catch(() => false)) {
		await close.click()
		await expect(page.locator('.dashboard-switcher-sidebar.open')).toHaveCount(0, { timeout: 5_000 })
	}
}

/**
 * Store the edit-mode draft (REQ-EDIT-002): choose Save in the edit bar and
 * wait for its one dashboard request. Edit mode ends with it.
 *
 * @param page The page, in edit mode with changes.
 * @return The answer's status.
 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
 */
export async function saveEditDraft(page: Page): Promise<number> {
	await closeSwitcher(page)
	const save = page.locator('[data-testid="launchpad-edit-bar-save"]')
	await expect(save).toBeEnabled({ timeout: 10_000 })
	const saved = page.waitForResponse(
		(res) => res.request().method() === 'PUT'
			&& /\/apps\/launchpad\/api\/dashboard\/\d+$/.test(new URL(res.url()).pathname)
			&& (res.request().postDataJSON()?.draft === true),
	)
	await save.click()
	return (await saved).status()
}

/**
 * Add a widget through an open Add widget (or tile) dialog the way edit mode
 * does now (REQ-EDIT-001/002): press its Add button, then Save the draft,
 * and return what the server stored for the new widget.
 *
 * @param page The page, in edit mode with the dialog open.
 * @param dialog The dialog.
 * @param button The name of its add button.
 * @return The dashboard id, the stored new placement, and what the Save sent for it.
 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-save-stores-the-whole-draft-at-once-req-edit-002
 */
export async function addAndSaveDraft(
	page: Page,
	dialog: Locator,
	button: RegExp = /^add$/i,
): Promise<{ dashboardId: number, placement: Record<string, any>, sent: Record<string, any> }> {
	const add = dialog.getByRole('button', { name: button })
	await expect(add).toBeEnabled({ timeout: 5_000 })
	await add.click()
	await expect(dialog).not.toBeVisible({ timeout: 8_000 })
	await closeSwitcher(page)
	const saved = page.waitForResponse(
		(res) => res.request().method() === 'PUT'
			&& /\/apps\/launchpad\/api\/dashboard\/\d+$/.test(new URL(res.url()).pathname)
			&& res.request().postDataJSON()?.draft === true,
	)
	await page.locator('[data-testid="launchpad-edit-bar-save"]').click()
	const response = await saved
	expect(response.status(), await response.text()).toBeLessThan(300)
	const body = response.request().postDataJSON() as { placements: Array<Record<string, any>> }
	const kept = new Set(body.placements.filter((p) => p.id !== undefined).map((p) => Number(p.id)))
	const json = await response.json()
	const stored = ((json.data ?? json).placements ?? []) as Array<Record<string, any>>
	return {
		dashboardId: Number(new URL(response.url()).pathname.match(/dashboard\/(\d+)$/)![1]),
		placement: stored.find((p) => !kept.has(Number(p.id)))!,
		sent: body.placements.find((p) => p.id === undefined)!,
	}
}
