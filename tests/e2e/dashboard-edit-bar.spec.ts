/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The edit bar of board LpBewerken and the Versions tab of board LpVersies.
 * Edit mode shows the bar with Columns 6, 8 or 12, Add widget, Dashboard
 * settings, Cancel and Save; Dashboard settings opens the settings window, whose
 * last tab is Versions.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboards::edit-mode-shows-the-edit-bar
 *   @e2e dashboards::columns-change-the-grid-and-are-kept
 *   @e2e dashboards::done-leaves-edit-mode
 *   @e2e dashboards::the-versions-tab-lists-the-saved-versions
 *
 * @spec openspec/specs/dashboards/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { enterEditMode, MORE_MENU, saveEditDraft } from './support/dashboardSwitcher.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

const BAR = '[data-testid="launchpad-edit-bar"]'

let api: APIRequestContext
let seeded: SeededDashboard | null = null

test.beforeAll(async () => {
	api = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	seeded = await seedActiveDashboard(api, `E2E Edit bar ${Date.now()}`)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, seeded)
	await api?.dispose()
})

/**
 * Open LaunchPad on the seeded dashboard and put it in edit mode.
 *
 * @param page the page.
 * @return the edit bar.
 */
async function openInEditMode(page: Page) {
	await page.goto('/index.php/apps/launchpad/')
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
	await enterEditMode(page)
	const bar = page.locator(BAR)
	await expect(bar).toBeVisible()
	return bar
}

/**
 * The column count the seeded dashboard has stored.
 *
 * @return the stored `gridColumns`.
 */
async function storedColumns(): Promise<number> {
	const res = await api.get(
		`/index.php/apps/launchpad/api/dashboard/${seeded!.id}`,
	)
	expect(res.status()).toBe(200)
	const body = await res.json()
	return Number((body.dashboard ?? body).gridColumns)
}

/**
 * GridStack's live column count for the rendered dashboard grid, or 0 while
 * the grid has not started.
 *
 * @param page the page.
 * @return the column count.
 */
async function gridColumns(page: Page): Promise<number> {
	return page.evaluate(() => {
		const el = document.querySelector('.grid-stack') as
			(HTMLElement & { gridstack?: { getColumn: () => number } }) | null
		return el?.gridstack ? Number(el.gridstack.getColumn()) : 0
	})
}

test('edit mode shows the edit bar with its controls and the draft pill', async ({ page }) => {
	// @e2e dashboards::edit-mode-shows-the-edit-bar
	const bar = await openInEditMode(page)
	await expect(bar).toHaveAttribute('role', 'toolbar')
	await expect(bar).toContainText(seeded!.name)
	for (const columns of ['6', '8', '12']) {
		await expect(
			bar.getByRole('button', { name: columns, exact: true }),
		).toBeVisible()
	}
	await expect(bar.getByRole('button', { name: 'Add widget' })).toBeVisible()
	await expect(
		bar.getByRole('button', { name: 'Dashboard settings' }),
	).toHaveAttribute('aria-haspopup', 'dialog')
	// REQ-EDIT-001: the draft pill, Cancel and a disabled Save; no Done.
	await expect(bar.getByTestId('launchpad-edit-bar-draft')).toHaveText('No changes')
	await expect(bar.getByRole('button', { name: 'Cancel' })).toBeVisible()
	await expect(bar.getByRole('button', { name: 'Save' })).toBeDisabled()
	await expect(bar.getByRole('button', { name: 'Done' })).toHaveCount(0)
	// The More menu docks at the end of the bar, so it never covers Save.
	await expect(bar.locator(MORE_MENU)).toBeVisible()
})

test('columns change the grid and are kept', async ({ page }) => {
	// @e2e dashboards::columns-change-the-grid-and-are-kept
	// At 1600 px no breakpoint narrows the grid, so GridStack runs the
	// dashboard's own column count.
	await page.setViewportSize({ width: 1600, height: 900 })
	// A new dashboard takes the administrator's default column count, which
	// need not be 12 on every instance; the GIVEN is set, not assumed.
	const given = await api.put(
		`/index.php/apps/launchpad/api/dashboard/${seeded!.id}`,
		{
			data: { gridColumns: 12 },
		},
	)
	expect(given.status(), await given.text()).toBe(200)
	const bar = await openInEditMode(page)
	const eight = bar.locator('[data-testid="launchpad-edit-bar-columns-8"]')
	const twelve = bar.locator('[data-testid="launchpad-edit-bar-columns-12"]')

	// GIVEN: a dashboard with 12 columns.
	expect(await storedColumns()).toBe(12)
	await expect(twelve).toHaveAttribute('aria-pressed', 'true')
	await expect.poll(() => gridColumns(page)).toBe(12)

	await eight.click()
	await expect(eight).toHaveAttribute('aria-pressed', 'true')
	await expect(twelve).toHaveAttribute('aria-pressed', 'false')
	await expect.poll(() => gridColumns(page)).toBe(8)
	// REQ-EDIT-001: the draft holds the columns until Save.
	expect(await storedColumns()).toBe(12)
	expect(await saveEditDraft(page)).toBe(200)
	await expect.poll(storedColumns).toBe(8)

	// Kept: a fresh load still renders and stores 8.
	await page.reload()
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
	await expect.poll(() => gridColumns(page)).toBe(8)

	const barAgain = await openInEditMode(page)
	await barAgain.locator('[data-testid="launchpad-edit-bar-columns-12"]').click()
	await expect(
		barAgain.locator('[data-testid="launchpad-edit-bar-columns-12"]'),
	).toHaveAttribute('aria-pressed', 'true')
	expect(await saveEditDraft(page)).toBe(200)
	await expect.poll(storedColumns).toBe(12)
})

test('Cancel without changes leaves edit mode at once', async ({ page }) => {
	// @e2e dashboards::done-leaves-edit-mode
	const bar = await openInEditMode(page)
	await bar.getByRole('button', { name: 'Cancel' }).click()
	await expect(page.locator(BAR)).toHaveCount(0)
	await expect(page.locator('.launchpad-edit-mode')).toHaveCount(0)
})

test('the Versions tab lists the saved versions', async ({ page }) => {
	// @e2e dashboards::the-versions-tab-lists-the-saved-versions
	// Two versions with a note each, saved in this order, so "newest first"
	// has something to order.
	const notes = [`E2E older ${Date.now()}`, `E2E newer ${Date.now()}`]
	for (const note of notes) {
		const res = await api.post(
			`/index.php/apps/launchpad/api/dashboards/${seeded!.uuid}/versions`,
			{ data: { note } },
		)
		expect(res.status(), await res.text()).toBe(201)
	}
	const bar = await openInEditMode(page)
	await bar.getByRole('button', { name: 'Dashboard settings' }).click()
	const versions = page.locator('[data-test="config-tab-versions"]')
	await expect(versions).toBeVisible({ timeout: 10_000 })
	await versions.click()
	const panel = page.locator(
		'[data-test="config-panel-versions"] [data-testid="version-history"]',
	)
	await expect(panel).toBeVisible({ timeout: 10_000 })
	const rows = panel.locator('[data-testid="version-row"]')
	await expect(rows.first()).toBeVisible({ timeout: 10_000 })

	// The stored versions, read after the tab has loaded its list.
	const listed = await api.get(
		`/index.php/apps/launchpad/api/dashboards/${seeded!.uuid}/versions`,
	)
	expect(listed.status()).toBe(200)
	const stored = ((await listed.json()).versions ?? []) as Array<{
		versionNumber: number
		note: string | null
	}>
	const newestFirst = [...stored].sort((a, b) => b.versionNumber - a.versionNumber)
	expect(newestFirst.length).toBeGreaterThanOrEqual(2)

	// Every stored version is listed, newest first, and only the newest is
	// marked current.
	await expect(rows).toHaveCount(newestFirst.length)
	const texts = await rows.allInnerTexts()
	for (const [i, version] of newestFirst.entries()) {
		// A `pre-restore` note is shown as a sentence, not as the raw note.
		if (version.note && version.note !== 'pre-restore') {
			expect(
				texts[i],
				`row ${i} is version ${version.versionNumber}`,
			).toContain(version.note)
		}
	}
	const newerRow = texts.findIndex((text) => text.includes(notes[1]))
	const olderRow = texts.findIndex((text) => text.includes(notes[0]))
	expect(newerRow, 'the newer note is listed').toBeGreaterThanOrEqual(0)
	expect(olderRow, 'the older note is listed').toBeGreaterThan(newerRow)
	await expect(rows.nth(0).locator('[data-testid="version-current"]')).toHaveText(
		'Current version',
	)
	await expect(panel.locator('[data-testid="version-current"]')).toHaveCount(1)

	// "Save this version now" is offered.
	await expect(panel.locator('[data-testid="version-save"]')).toHaveText(
		'Save this version now',
	)
	await expect(panel.locator('[data-testid="version-save"]')).toBeEnabled()

	// A restore per version: each older version, once picked, can be
	// restored; the current one cannot.
	const restore = panel.locator('[data-testid="version-restore"]')
	await rows.nth(0).click()
	await expect(restore).toBeDisabled()
	const count = await rows.count()
	for (let i = 1; i < count; i++) {
		await rows.nth(i).click()
		await expect(rows.nth(i)).toHaveAttribute('aria-selected', 'true')
		await expect(restore, `row ${i} can be restored`).toBeEnabled()
		await expect(restore).toContainText('Restore this version')
	}
})
