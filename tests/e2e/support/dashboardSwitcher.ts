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
	// Idempotent: a menu that is already open is not toggled shut.
	const panel = page.locator('[data-testid="launchpad-more-menu-panel"]')
	if (!await panel.isVisible().catch(() => false)) {
		await more.locator('[data-testid="launchpad-more-menu-trigger"]').click()
	}
	// No slide-in panel any more (more-menu-favourites, REQ-SHELL-015):
	// "More" at the bottom of the Dashboards column unfolds it in place into
	// every dashboard the person can open, and the menu stays open.
	const unfold = page.locator('[data-testid="launchpad-more-menu-item-all-dashboards"]')
	await expect(unfold).toBeVisible({ timeout: 10_000 })
	if (await unfold.getAttribute('aria-expanded') !== 'true') {
		await unfold.click()
	}
	await expect(unfold).toHaveAttribute('aria-expanded', 'true', { timeout: 10_000 })
}

/**
 * Assert which dashboard the page opened: More's Dashboards column marks the
 * open one (`aria-checked`). Since the dashboards sidebar is gone its name is
 * nowhere else on the page (a template's header carries its own title).
 *
 * @param page The page, on LaunchPad.
 * @param name The dashboard name.
 * @param timeout How long the page may take to show More.
 * @return Resolves once the open dashboard is confirmed and More is closed.
 */
export async function expectOpenDashboard(page: Page, name: string, timeout = 30_000): Promise<void> {
	const more = page.locator(MORE_MENU)
	await expect(more).toBeVisible({ timeout })
	await more.locator('[data-testid="launchpad-more-menu-trigger"]').click()
	const open = page.locator('[data-testid="launchpad-more-menu-dashboard-list"] [role="menuitemradio"][aria-checked="true"]')
	await expect(open).toHaveCount(1)
	await expect(open).toContainText(name)
	await page.keyboard.press('Escape')
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
 * Open the App or link tile form on "An app", where "Choose the app" and
 * "Import bookmarks" live (app-link-tile-widget): edit mode, Add widget, the
 * App or link tile card, Add, then "An app".
 *
 * @param page The page, on the dashboard, as someone who may edit it.
 * @return The "Choose the app" list.
 */
export async function openTileAppChooser(page: Page): Promise<Locator> {
	if (await page.locator('.launchpad-edit-mode').count() === 0) {
		await enterEditMode(page)
	}
	await page.getByTestId('launchpad-edit-bar-add-widget').click()
	await page.getByTestId('widget-picker-type-tile').click()
	await page.getByTestId('widget-picker-add').click()
	await expect(page.getByTestId('tile-mode')).toBeVisible({ timeout: 10_000 })
	await page.getByTestId('tile-mode').getByText('An app', { exact: true }).click()
	const chooser = page.getByTestId('tile-app-chooser')
	await expect(chooser).toBeVisible()
	return chooser
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
