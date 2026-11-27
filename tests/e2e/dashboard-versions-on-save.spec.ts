/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Every Save makes a version (Ruben's review of 27 Nov 2026, item 18):
 * change a widget in edit mode and Save, and the Versions tab lists the new
 * version, with the state before it as the first one; restoring that one
 * brings the old widget back.
 *
 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboard-versioning/spec.md#requirement-every-save-makes-one-version-of-the-whole-dashboard-req-vers-010
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import { clearSeededWidgets, removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { enterEditMode, openDashboardSettings } from './support/dashboardSwitcher.ts'
import { adminContext } from './support/principals.ts'

const STAMP = Date.now().toString(36)
const API = '/index.php/apps/launchpad/api'
const ROW = '[data-testid="version-row"]'

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

/**
 * The texts of the dashboard's text widgets as stored.
 *
 * @return the texts.
 */
async function storedTexts(): Promise<string[]> {
	const res = await admin.get(`${API}/dashboard/${dashboard!.id}`)
	const body = await res.json()
	const data = body.data ?? body
	const placements = (data.placements ?? data.dashboard?.placements ?? []) as Array<{ content?: { text?: string } }>
	return placements.map((p) => p.content?.text ?? '').filter((text) => text !== '')
}

/**
 * Open the seeded dashboard.
 *
 * @param page the page.
 */
async function openDashboard(page: Page): Promise<void> {
	await page.goto('/index.php/apps/launchpad', { waitUntil: 'domcontentloaded' })
	await expect(
		page.locator(`[data-testid="launchpad-dashboard-region"][data-dashboard-uuid="${dashboard!.uuid}"]`),
	).toBeVisible({ timeout: 30_000 })
}

test.beforeAll(async () => {
	admin = await adminContext()
	dashboard = await seedActiveDashboard(admin, `E2E Versies bij opslaan ${STAMP}`)
	await clearSeededWidgets(admin, dashboard.id)
	for (const text of ['Oude tekst', 'Blijft staan']) {
		const res = await admin.post(`${API}/dashboard/${dashboard.id}/widgets`, {
			data: { widgetId: 'text', gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 2, content: { text, contentMode: 'markdown' } },
		})
		expect(res.status(), await res.text()).toBeLessThan(300)
	}
})

test.afterAll(async () => {
	if (admin) {
		await removeSeededDashboard(admin, dashboard).catch(() => undefined)
		await admin.dispose()
	}
})

// @e2e dashboard-versioning::a-widget-change-makes-a-version
// @e2e dashboard-versioning::the-first-save-of-an-old-dashboard
test('a widget change saved in edit mode makes a version, and restoring the one before brings it back', async ({ page }) => {
	const none = await admin.get(`${API}/dashboards/${dashboard!.uuid}/versions`)
	const listed = await none.json()
	expect((listed.versions ?? listed.data?.versions ?? [])).toHaveLength(0)

	await openDashboard(page)
	await enterEditMode(page)
	const widget = page.locator('.grid-stack-item').filter({ hasText: 'Oude tekst' })
	await widget.getByTestId('cn-widget-edit-cog').locator('button').first().click()
	await page.getByTestId('cn-widget-edit-cog-delete').filter({ visible: true }).click()
	await expect(page.getByTestId('launchpad-edit-bar-draft')).toHaveText('Draft · 1 change')
	const saved = page.waitForResponse((r) => r.request().method() === 'PUT' && r.url().endsWith(`/api/dashboard/${dashboard!.id}`))
	await page.getByTestId('launchpad-edit-bar-save').click()
	expect((await saved).status()).toBe(200)
	expect(await storedTexts()).toEqual(['Blijft staan'])

	// The Versions tab: the state before (the first save of a dashboard
	// without versions) and the version of this Save, newest first.
	await openDashboardSettings(page)
	await page.locator('[data-test="config-tab-versions"]').click({ timeout: 15_000 })
	const rows = page.locator('[data-testid="version-history"]').locator(ROW)
	await expect(rows).toHaveCount(2, { timeout: 15_000 })

	const restored = await admin.post(`${API}/dashboards/${dashboard!.uuid}/versions/1/restore`)
	expect(restored.status(), await restored.text()).toBe(200)
	expect((await storedTexts()).sort()).toEqual(['Blijft staan', 'Oude tekst'])
})
