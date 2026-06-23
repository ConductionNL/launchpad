/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared e2e helpers for driving the LaunchPad runtime-shell widget flow.
 *
 * The current UI does NOT expose a top-level "Add widget" button. Widgets
 * are added per-dashboard via the dashboard-switcher sidebar: open the
 * sidebar → open the cog ("Dashboard menu") on a personal dashboard row →
 * "Add custom widget…" → the Add Widget modal. These helpers encapsulate
 * that flow so individual specs stay readable.
 */

import { expect, type Page } from '@playwright/test'

/** Navigate to the app and wait for the runtime shell to hydrate (503-resilient). */
export async function gotoLaunchPad(page: Page): Promise<void> {
	await page.goto('/index.php/apps/launchpad')
	try {
		await page.waitForSelector('.launchpad-sidebar-toggle', { timeout: 20_000 })
	} catch {
		// Transient dev-instance 503 (needsDbUpgrade blip) — retry once.
		await page.goto('/index.php/apps/launchpad')
		await page.waitForSelector('.launchpad-sidebar-toggle', { timeout: 20_000 })
	}
	const open = await page.locator('.dashboard-switcher-sidebar.open').count()
	if (open > 0) {
		const closeBtn = page.locator('.dashboard-switcher-sidebar__close').first()
		if (await closeBtn.isVisible().catch(() => false)) {
			await closeBtn.click()
		} else {
			await page.locator('.launchpad-sidebar-toggle').first().click()
		}
		await page.waitForFunction(
			() => !document.querySelector('.dashboard-switcher-sidebar.open'),
			{ timeout: 5_000 },
		).catch(() => null)
	}
}

/** Open the sidebar (idempotent). */
export async function openSidebar(page: Page): Promise<void> {
	const open = await page.locator('.dashboard-switcher-sidebar.open').count()
	if (open > 0) {
		return
	}
	await page.locator('.launchpad-sidebar-toggle').first().click()
	await page.waitForSelector('.dashboard-switcher-sidebar.open', { timeout: 8_000 })
}

/** Close the sidebar so the grid is not occluded. */
export async function closeSidebar(page: Page): Promise<void> {
	const closeBtn = page.locator('.dashboard-switcher-sidebar__close').first()
	if (await closeBtn.isVisible().catch(() => false)) {
		await closeBtn.click()
		await page.waitForFunction(
			() => !document.querySelector('.dashboard-switcher-sidebar.open'),
			{ timeout: 5_000 },
		).catch(() => null)
	}
}

/**
 * Open the Add Widget modal via the first personal dashboard row's cog menu.
 * Leaves the modal visible.
 */
export async function openAddWidgetModal(page: Page): Promise<void> {
	await openSidebar(page)
	const personalRow = page.locator('[data-source="user"].dashboard-switcher-sidebar__item').first()
	await expect(personalRow).toBeVisible({ timeout: 8_000 })
	await personalRow.locator('.dashboard-row-actions button').first().click()
	const addWidgetItem = page.getByRole('menuitem', { name: /add custom widget/i })
	await expect(addWidgetItem).toBeVisible({ timeout: 5_000 })
	await addWidgetItem.click()
	await expect(page.getByRole('dialog', { name: /add widget/i }).first())
		.toBeVisible({ timeout: 10_000 })
}
