/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end test for the filter-declaration warning in the widget
 * dialog (drill-down-cross-widget-filter, Task 29, REQ-CWF-008): a chart that
 * consumes wijkCode on a dashboard whose hierarchies do not publish it still
 * renders, stays off the bus, and its edit dialog says why.
 *
 * The dashboard is seeded as the admin, made the admin's active dashboard and
 * holds only that chart (static data, so no data set is needed).
 *
 * Gate-19 @e2e traceability:
 *   @e2e cross-widget-filter::the-widget-dialog-warns-about-an-unpublished-dimension
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md
 */

import { expect, test } from '@playwright/test'
import { installBundleOverride } from './support/bundleOverride.ts'
import { clearSeededWidgets } from './support/dashboardFixture.ts'
import { adminApi, contentCentre, gridItem, openInEditMode, rightClickAt, Seeds } from './support/widgetPlacements.ts'

// @e2e cross-widget-filter::the-widget-dialog-warns-about-an-unpublished-dimension
test('a chart consuming wijkCode without a hierarchy renders, stays off the bus and its dialog warns', async ({ page }) => {
	const seeds = new Seeds(await adminApi())
	try {
		const dash = await seeds.dashboard(`E2E bus warning ${Date.now()}`)
		await clearSeededWidgets(seeds.api, dash.id)
		const chart = await seeds.widget(dash, {
			widgetId: 'chart', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 4,
			content: { title: 'Wijken', chartKind: 'bar-list', series: [{ name: 'n', data: [3, 1] }], categories: ['WK035501', 'WK035502'], consumes: ['wijkCode'] },
		})

		const offBus: string[] = []
		page.on('console', (message) => {
			if (message.text().includes(`widget ${chart.id} is not on the filter bus: consumes-unknown-dimension (wijkCode)`)) {
				offBus.push(message.text())
			}
		})
		await installBundleOverride(page.context())
		await openInEditMode(page, chart.id)

		// The chart still renders its data, and says in the console that it is off the bus.
		await expect(gridItem(page, chart.id).locator('[data-testid="cn-chart-widget-bar-row"]')).toHaveCount(2, { timeout: 20_000 })
		await expect.poll(() => offBus.length, { timeout: 20_000 }).toBeGreaterThan(0)

		const at = await contentCentre(page, chart.id)
		await rightClickAt(page, chart.id, at.x, at.y)
		await page.locator('[data-testid="ctx-edit"]').click()
		const dialog = page.getByRole('dialog', { name: 'Edit Widget' })
		await expect(dialog).toBeVisible({ timeout: 10_000 })
		const notice = dialog.locator('[data-testid="cn-add-widget-modal-notice"]')
		await expect(notice).toHaveCount(1)
		await expect(notice).toContainText('This widget consumes wijkCode, but no hierarchy on this dashboard publishes it.')
	} finally {
		await seeds.cleanup()
	}
})
