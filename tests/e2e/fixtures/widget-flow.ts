/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared e2e helpers for driving the LaunchPad runtime-shell widget flow.
 *
 * Widgets are added to the open dashboard through More → Add widget → the
 * type in the picker → the Add Widget modal. These helpers encapsulate that
 * flow so individual specs stay readable. (The dashboards sidebar and its
 * per-row menus are gone, more-menu-favourites.)
 */

import type { Page } from '@playwright/test'

import { expect } from '@playwright/test'
import { addWidgetFromMore, closeMore, MORE_MENU, openDashboardSwitcher } from '../support/dashboardSwitcher.ts'
import { chooseAndAdd } from '../support/widgetPicker.ts'

/** Navigate to the app and wait for the runtime shell to hydrate (503-resilient). */
export async function gotoLaunchPad(page: Page): Promise<void> {
	await page.goto('/index.php/apps/launchpad')
	try {
		await page.waitForSelector(MORE_MENU, { timeout: 20_000 })
	} catch {
		// Transient dev-instance 503 (needsDbUpgrade blip) — retry once.
		await page.goto('/index.php/apps/launchpad')
		await page.waitForSelector(MORE_MENU, { timeout: 20_000 })
	}
}

/** Open More with every dashboard listed (idempotent); the sidebar is gone. */
export async function openSidebar(page: Page): Promise<void> {
	const unfold = page.locator('[data-testid="launchpad-more-menu-item-all-dashboards"]')
	if (await unfold.isVisible().catch(() => false) && await unfold.getAttribute('aria-expanded') === 'true') {
		return
	}
	await closeMore(page)
	await openDashboardSwitcher(page)
}

/** Close the More menu so the grid is not occluded. */
export async function closeSidebar(page: Page): Promise<void> {
	await closeMore(page)
}

/**
 * Open the Add Widget modal of the open dashboard through More → Add widget,
 * picking `type` in the widget picker. Leaves the modal visible, opened on
 * that type.
 */
export async function openAddWidgetModal(page: Page, type = 'label'): Promise<void> {
	await addWidgetFromMore(page)
	await chooseAndAdd(page.locator(`[data-testid="widget-picker-type-${type}"]`))
	await expect(
		page.getByRole('dialog', { name: /add widget/i }).first(),
	).toBeVisible({ timeout: 10_000 })
}
