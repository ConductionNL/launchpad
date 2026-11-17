/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end test for saved views (drill-down-cross-widget-filter,
 * Task 27, REQ-CWF-009): save the current filter as a named view, restore it
 * later, share it, and see it read-only as another user.
 *
 * Both accounts are throwaway non-admin accounts; the owner shares the
 * dashboard with the second one (view only) so the shared view is reachable.
 *
 * Gate-19 @e2e traceability:
 *   @e2e cross-widget-filter::saved-view-saves-restores-and-shares
 *
 * @spec openspec/changes/drill-down-cross-widget-filter/specs/cross-widget-filter/spec.md
 */

import type { Clause, SeededUser } from './support/filterBus.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import {
	API,
	apiAs,
	chip,
	gotoFiltered,
	openFiltered,
	provisionUserWithDashboard,
	removeUser,
} from './support/filterBus.ts'

const VIEW_NAME = `Q2 Trend ${Date.now()}`

const THREE: Clause[] = [
	{ dimension: 'gemeenteCode', operator: 'eq', value: 'GM0355' },
	{ dimension: 'kwartaal', operator: 'eq', value: '2026-Q2' },
	{ dimension: 'status', operator: 'in', value: ['open', 'gesloten'] },
]

let owner: SeededUser | null = null
let viewer: SeededUser | null = null

test.beforeAll(async () => {
	owner = await provisionUserWithDashboard('e2e-cwf-owner')
	viewer = await provisionUserWithDashboard('e2e-cwf-viewer')

	const api = await apiAs(owner)
	try {
		const shared = await api.post(`${API}/dashboard/${owner.dashboardId}/shares`, {
			data: { shareType: 'user', shareWith: viewer.username, permissionLevel: 'view_only' },
		})
		expect(shared.status(), await shared.text()).toBeLessThan(300)
	} finally {
		await api.dispose()
	}
})

test.afterAll(async () => {
	await removeUser(owner)
	await removeUser(viewer)
})

test('a saved view is saved, restores its clauses, and is read-only for another user once shared', async ({
	browser,
}) => {
	const { context, page } = await openFiltered(browser, owner!, THREE)
	try {
		await expect(page.locator('[data-testid="active-filters"] [data-dimension]')).toHaveCount(3)

		// Save the current filter as a named view.
		await page.locator('[data-testid="saved-views-save"]').click()
		const modal = page.locator('[data-testid="save-view-modal"]')
		await expect(modal).toBeVisible()
		await modal.getByRole('textbox', { name: 'Name' }).fill(VIEW_NAME)
		await modal.locator('[data-testid="save-view-submit"]').click()
		await expect(modal).toHaveCount(0, { timeout: 10_000 })

		// It is listed under "My views".
		await page.locator('[data-testid="saved-views-toggle"]').click()
		const mine = page.locator('[data-testid="saved-views-mine"]')
		await expect(mine).toContainText(VIEW_NAME)

		// Later, with a different filter active, the view restores all three clauses.
		await gotoFiltered(page, [{ dimension: 'jaar', operator: 'eq', value: 2025 }])
		await expect(chip(page, 'jaar')).toHaveCount(1)
		await page.locator('[data-testid="saved-views-toggle"]').click()
		await page
			.locator('[data-testid="saved-views-mine"] [data-testid="saved-view-apply"]')
			.filter({ hasText: VIEW_NAME })
			.click()
		await expect(page.locator('[data-testid="active-filters"] [data-dimension]')).toHaveCount(3)
		await expect(chip(page, 'gemeenteCode')).toContainText('GM0355')
		await expect(chip(page, 'kwartaal')).toContainText('2026-Q2')
		await expect(chip(page, 'jaar')).toHaveCount(0)

		// Share it.
		const toggle = page.locator('[data-testid="saved-views-toggle"]')
		if (!(await page.locator('[data-testid="saved-views-list"]').isVisible())) {
			await toggle.click()
		}
		await page.getByRole('button', { name: `Share ${VIEW_NAME}` }).click()
		await expect(page.getByRole('button', { name: `Stop sharing ${VIEW_NAME}` })).toBeVisible({
			timeout: 10_000,
		})
	} finally {
		await context.close()
	}

	// The other user opens the shared dashboard and sees the view under "Shared views".
	const viewerApi = await apiAs(viewer!)
	try {
		const active = await viewerApi.post(`${API}/dashboards/active`, {
			data: { uuid: owner!.dashboardUuid },
		})
		expect(active.status(), await active.text()).toBeLessThan(300)
	} finally {
		await viewerApi.dispose()
	}
	const other = await loginAs(browser, viewer!.username, viewer!.password)
	try {
		await gotoFiltered(other.page)
		await other.page.locator('[data-testid="saved-views-toggle"]').click()
		const shared = other.page.locator('[data-testid="saved-views-shared"]')
		await expect(shared).toContainText(VIEW_NAME, { timeout: 10_000 })
		// Read-only: no edit, share or delete for a view someone else made.
		await expect(shared.locator('[data-testid="saved-view-edit"]')).toHaveCount(0)
		await expect(shared.locator('[data-testid="saved-view-share"]')).toHaveCount(0)
		await expect(shared.locator('[data-testid="saved-view-delete"]')).toHaveCount(0)

		await shared.locator('[data-testid="saved-view-apply"]').filter({ hasText: VIEW_NAME }).click()
		await expect(chip(other.page, 'gemeenteCode')).toContainText('GM0355')
	} finally {
		await other.context.close()
	}
})
