/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Edit mode keeps a draft and stores it on Save (Ruben's review of 27 Nov
 * 2026, item 10; board LpBewerken): a change only counts in the draft pill
 * until Save sends one request; Cancel discards; leaving with changes asks
 * "Save changes?"; a reload with changes gets the browser's leave warning.
 *
 * Seeds its own dashboards: a new dashboard gets the default bundle (three
 * tiles and a Files widget).
 *
 * @spec openspec/changes/edit-mode-draft-and-save/specs/dashboards/spec.md#requirement-the-edit-bar-holds-a-draft-req-edit-001
 */

import type { APIRequestContext, Page, Request } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { enterEditMode, MORE_MENU } from './support/dashboardSwitcher.ts'
import { adminContext } from './support/principals.ts'

const STAMP = Date.now().toString(36)
const BAR = '[data-testid="launchpad-edit-bar"]'
const PILL = '[data-testid="launchpad-edit-bar-draft"]'
const SAVE = '[data-testid="launchpad-edit-bar-save"]'
const DASHBOARDS = '/index.php/apps/launchpad/api/dashboard'

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null
let other: SeededDashboard | null = null

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

/**
 * The stored dashboard: its grid columns and placement ids.
 *
 * @return its columns and placements.
 */
async function stored(): Promise<{ gridColumns: number, ids: number[] }> {
	const res = await admin.get(`${DASHBOARDS}/${dashboard!.id}`)
	const body = await res.json()
	const data = body.data ?? body
	const dash = data.dashboard ?? data
	const placements = (data.placements ?? dash.placements ?? []) as Array<{ id: number }>
	return { gridColumns: Number(dash.gridColumns ?? 12), ids: placements.map((p) => p.id) }
}

/**
 * Open the seeded dashboard in edit mode, recording every write the page sends.
 *
 * @param page the page.
 * @return the writes sent from here on.
 */
async function openInEditMode(page: Page): Promise<Request[]> {
	await admin.post(`${DASHBOARDS}/${dashboard!.id}/activate`)
	await admin.post('/index.php/apps/launchpad/api/dashboards/active', { data: { uuid: dashboard!.uuid } })
	await page.goto('/index.php/apps/launchpad', { waitUntil: 'domcontentloaded' })
	await expect(
		page.locator(`[data-testid="launchpad-dashboard-region"][data-dashboard-uuid="${dashboard!.uuid}"]`),
	).toBeVisible({ timeout: 30_000 })
	await enterEditMode(page)
	await expect(page.locator(BAR)).toBeVisible()
	const writes: Request[] = []
	page.on('request', (req) => {
		const writing = ['PUT', 'POST', 'DELETE'].includes(req.method())
		// The edit presence (co-editing) is not a change to the dashboard.
		if (writing && /\/apps\/launchpad\/api\/(dashboard|widgets)/.test(req.url()) && !req.url().endsWith('/presence')) {
			writes.push(req)
		}
	})
	return writes
}

test.beforeAll(async () => {
	admin = await adminContext()
	other = await seedActiveDashboard(admin, `E2E Concept ander ${STAMP}`)
	dashboard = await seedActiveDashboard(admin, `E2E Concept ${STAMP}`)
})

test.afterAll(async () => {
	if (admin) {
		await removeSeededDashboard(admin, dashboard).catch(() => undefined)
		await removeSeededDashboard(admin, other).catch(() => undefined)
		await admin.dispose()
	}
})

// @e2e dashboards::edit-mode-shows-the-draft-bar
test('edit mode shows the draft bar: No changes, Cancel and a disabled Save, no Done', async ({ page }) => {
	await openInEditMode(page)
	await expect(page.locator(PILL)).toHaveText('No changes')
	await expect(page.locator(SAVE)).toBeDisabled()
	await expect(page.locator('[data-testid="launchpad-edit-bar-cancel"]')).toBeVisible()
	await expect(page.locator('[data-testid="launchpad-edit-bar-done"]')).toHaveCount(0)
	await expect(page.locator(BAR)).not.toContainText('saved immediately')
})

// @e2e dashboards::columns-change-the-draft
// @e2e dashboards::cancel-brings-the-old-layout-back
test('a change only counts in the draft, and Cancel then Discard sends nothing', async ({ page }) => {
	const writes = await openInEditMode(page)
	await page.locator('[data-testid="launchpad-edit-bar-columns-8"]').click()
	await expect(page.locator(PILL)).toHaveText('Draft · 1 change')
	await expect(page.locator('[data-testid="launchpad-edit-bar-columns-8"]')).toHaveAttribute('aria-pressed', 'true')
	await expect(page.locator(SAVE)).toBeEnabled()

	await page.locator('[data-testid="launchpad-edit-bar-cancel"]').click()
	const dialog = page.getByTestId('edit-draft-leave-dialog')
	await expect(dialog).toContainText('Discard changes?')
	await page.getByTestId('edit-draft-discard').click()
	await expect(page.locator(BAR)).toHaveCount(0)

	expect(writes.map((r) => `${r.method()} ${r.url()}`)).toEqual([])
	expect((await stored()).gridColumns).toBe(12)
})

// @e2e dashboards::save-stores-and-leaves-edit-mode
test('Save sends the draft in one request, stores it and leaves edit mode', async ({ page }) => {
	const before = await stored()
	const writes = await openInEditMode(page)

	await page.locator('[data-testid="launchpad-edit-bar-columns-8"]').click()
	const menus = page.getByTestId('cn-widget-edit-cog')
	await menus.last().locator('button').first().click()
	await page.getByTestId('cn-widget-edit-cog-delete').filter({ visible: true }).click()
	await expect(page.locator(PILL)).toHaveText('Draft · 2 changes')
	expect(writes).toHaveLength(0)

	const saved = page.waitForResponse((res) => res.request().method() === 'PUT' && res.url().endsWith(`${DASHBOARDS}/${dashboard!.id}`))
	await page.locator(SAVE).click()
	expect((await saved).status()).toBe(200)
	await expect(page.locator(BAR)).toHaveCount(0)

	expect(writes).toHaveLength(1)
	expect(writes[0].postDataJSON()).toMatchObject({ draft: true, gridColumns: 8 })
	const after = await stored()
	expect(after.gridColumns).toBe(8)
	expect(after.ids).toHaveLength(before.ids.length - 1)
})

// @e2e dashboards::switching-dashboards-with-changes
test('choosing another dashboard with changes asks Save changes?, and Don\'t save opens it unsaved', async ({ page }) => {
	const before = await stored()
	const writes = await openInEditMode(page)
	await page.locator('[data-testid="launchpad-edit-bar-columns-6"]').click()
	await expect(page.locator(PILL)).toHaveText('Draft · 1 change')

	await page.locator(MORE_MENU).locator('[data-testid="launchpad-more-menu-trigger"]').click()
	await page.locator('[data-testid^="launchpad-more-menu-item-dashboard-"]').filter({ hasText: `E2E Concept ander ${STAMP}` }).click()
	const dialog = page.getByTestId('edit-draft-leave-dialog')
	await expect(dialog).toContainText('Save changes?')
	await expect(page.getByTestId('edit-draft-keep')).toBeVisible()
	await expect(page.getByTestId('edit-draft-save')).toBeVisible()
	await page.getByTestId('edit-draft-discard').click()

	await expect(
		page.locator(`[data-testid="launchpad-dashboard-region"][data-dashboard-uuid="${other!.uuid}"]`),
	).toBeVisible({ timeout: 30_000 })
	expect(writes.filter((r) => r.url().includes(`/dashboard/${dashboard!.id}`) && r.method() === 'PUT')).toHaveLength(0)
	expect((await stored()).gridColumns).toBe(before.gridColumns)
})

// @e2e dashboards::reload-warns-with-changes
test('a reload with unsaved changes gets the browser\'s leave warning', async ({ page }) => {
	await openInEditMode(page)
	await page.locator('[data-testid="launchpad-edit-bar-columns-6"]').click()
	await expect(page.locator(PILL)).toHaveText('Draft · 1 change')

	const warned = new Promise<string>((resolve) => {
		page.once('dialog', async (dialog) => {
			resolve(dialog.type())
			await dialog.dismiss()
		})
	})
	await page.reload({ timeout: 5_000 }).catch(() => undefined)
	expect(await warned).toBe('beforeunload')
	await expect(page.locator(BAR)).toBeVisible()
})
