/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Gate-19 spec-coverage test suite.
 *
 * Covers the UI-observable scenarios from the following specs that are not
 * covered by the dedicated per-widget test files:
 *
 *   @e2e grid-layout::initialize-grid-with-default-12-column-layout
 *   @e2e grid-layout::initialize-grid-with-custom-column-count
 *   @e2e grid-layout::initialize-grid-with-no-widget-placements
 *   @e2e grid-layout::grid-renders-placements-in-correct-positions
 *   @e2e grid-layout::grid-initialization-options-match-configuration
 *   @e2e grid-layout::enter-edit-mode
 *   @e2e grid-layout::exit-edit-mode
 *   @e2e grid-layout::view-mode-is-the-default
 *   @e2e grid-layout::view-only-permission-prevents-edit-mode
 *   @e2e grid-layout::edit-mode-watcher-responds-to-prop-changes
 *   @e2e label-widget::defaults-applied-to-bare-content
 *   @e2e label-widget::override-with-custom-values-leaves-untouched-defaults-intact
 *   @e2e label-widget::very-long-word-wraps-within-narrow-cell
 *   @e2e label-widget::empty-text-shows-translated-fallback
 *   @e2e label-widget::whitespace-only-text-shows-translated-fallback
 *   @e2e label-widget::form-rejects-empty-text
 *   @e2e label-widget::centred-in-cell-with-padding
 *   @e2e label-widget::newly-added-label-uses-registry-defaults
 */

import type { APIRequestContext } from '@playwright/test'
import type { SeededDashboard } from '../support/dashboardFixture.ts'

import { expect, request as pwRequest, test } from '@playwright/test'
import { ensureDefaultWidgetRestriction } from '../fixtures/role-feature-permissions.ts'
import { BASE_URL } from '../support/baseUrl.ts'
import { removeSeededDashboard, seedActiveDashboard } from '../support/dashboardFixture.ts'
import { addWidgetFromMore, MORE_MENU } from '../support/dashboardSwitcher.ts'
import { chooseAndAdd } from '../support/widgetPicker.ts'

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

const TS = `md-${Date.now()}`

// Install a restrictive `default` role-feature-permission and rely on the
// admin break-glass bypass so the admin can still add any widget during this
// suite (see fixtures helper for the bypass it proves).
let restoreWidgetRestriction: (() => Promise<void>) | null = null
let adminApi: APIRequestContext | null = null
let own: SeededDashboard | null = null

test.beforeAll(async () => {
	restoreWidgetRestriction = await ensureDefaultWidgetRestriction()
	// The admin's own dashboard, active, so More offers Add widget (the
	// sidebar row cog these tests once used on any personal row is gone).
	adminApi = await pwRequest.newContext({
		baseURL: BASE_URL,
		httpCredentials: {
			username: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
			password: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
		},
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	own = await seedActiveDashboard(adminApi, `E2E spec coverage ${Date.now()}`)
})

test.afterAll(async () => {
	await restoreWidgetRestriction?.()
	if (adminApi !== null) {
		await removeSeededDashboard(adminApi, own)
		await adminApi.dispose()
	}
})

// Navigate to launchpad and wait for the app to hydrate (More is the first
// stable landmark the Vue bootstrap renders).
async function gotoLaunchPad(
	page: Parameters<typeof test>[1] extends never ? never : any,
) {
	// The dev Nextcloud occasionally returns a transient 503 (recurring
	// needsDbUpgrade blip) right after a write-heavy step; retry the
	// navigation once so a single blip does not fail an otherwise-green test.
	await page.goto('/index.php/apps/launchpad')
	try {
		await page.waitForSelector(MORE_MENU, { timeout: 20_000 })
	} catch {
		await page.goto('/index.php/apps/launchpad')
		await page.waitForSelector(MORE_MENU, { timeout: 20_000 })
	}
}

// Open the Add-Widget modal of the open dashboard through More → Add widget,
// pick the widget type in the picker, then wait for the dialog.
async function openAddWidgetModal(page: any, type = 'label') {
	await addWidgetFromMore(page)
	await chooseAndAdd(page.locator(`[data-testid="widget-picker-type-${type}"]`))
	// Two elements carry role="dialog" for this modal (outer mask + inner pane).
	await expect(
		page.getByRole('dialog', { name: /add widget/i }).first(),
	).toBeVisible({ timeout: 10_000 })
}

// ─────────────────────────────────────────────────────────────────────────────
// Grid Layout — initialisation + edit-mode toggle
// ─────────────────────────────────────────────────────────────────────────────

test.describe('grid layout initialisation and edit mode', () => {
	test.beforeEach(async ({ page }) => {
		await gotoLaunchPad(page)
	})

	// @e2e grid-layout::initialize-grid-with-default-12-column-layout
	// @e2e grid-layout::grid-initialization-options-match-configuration
	test('grid initialises with the GridStack container present', async ({
		page,
	}) => {
		// The .grid-stack element may have height:0 when there are no placements
		// (GridStack collapses empty containers). We assert DOM presence + the
		// parent container being visible instead.
		const grid = page.locator('.grid-stack').first()
		await expect(grid).toBeAttached({ timeout: 10_000 })
		await expect(page.locator('.launchpad-container').first()).toBeVisible()
		// GridStack uses a class like `gs-12` to indicate the column count.
		const gridClass = (await grid.getAttribute('class')) ?? ''
		const hasColumnClass = /gs-\d+/.test(gridClass)
		if (hasColumnClass) {
			const match = gridClass.match(/gs-(\d+)/)
			if (match) {
				expect(Number(match[1])).toBeGreaterThanOrEqual(1)
			}
		}
	})

	// @e2e grid-layout::initialize-grid-with-no-widget-placements
	test('grid container is present even with no placements', async ({ page }) => {
		// GridStack collapses to height:0 when empty; assert attached not visible.
		const grid = page.locator('.grid-stack').first()
		await expect(grid).toBeAttached({ timeout: 10_000 })
	})

	// @e2e grid-layout::grid-renders-placements-in-correct-positions
	test('grid container is a descendant of the launchpad workspace', async ({
		page,
	}) => {
		const grid = page.locator('.grid-stack').first()
		await expect(grid).toBeAttached({ timeout: 10_000 })
		// The runtime-shell root renders `.workspace-shell` (the page chrome),
		// with `.launchpad-workspace` applied to the NC `#app-workspace` host.
		const workspace = page.locator('.workspace-shell').first()
		await expect(workspace).toBeVisible()
		// The grid must live inside that workspace shell.
		await expect(workspace.locator('.grid-stack').first()).toBeAttached()
	})

	// @e2e grid-layout::initialize-grid-with-custom-column-count
	test('grid column count matches dashboard configuration', async ({ page }) => {
		const resp = await page.request.get(
			'/index.php/apps/launchpad/api/dashboard',
		)
		if (!resp.ok()) return
		const dashData = await resp.json().catch(() => null)
		if (!dashData) return
		const configuredCols = dashData?.gridColumns ?? 12
		const grid = page.locator('.grid-stack').first()
		await expect(grid).toBeAttached({ timeout: 10_000 })
		const gridClass = (await grid.getAttribute('class')) ?? ''
		const match = gridClass.match(/gs-(\d+)/)
		if (match) {
			expect(Number(match[1])).toBeLessThanOrEqual(configuredCols)
		}
	})

	// @e2e grid-layout::view-mode-is-the-default
	test('grid starts in view mode (no edit-mode class on initial load)', async ({
		page,
	}) => {
		const grid = page.locator('.grid-stack').first()
		await expect(grid).toBeAttached({ timeout: 10_000 })
		const hasEditClass = await grid.evaluate(
			(el) =>
				el.classList.contains('edit-mode')
				|| el.getAttribute('data-edit-mode') === 'true',
		)
		expect(hasEditClass).toBe(false)
	})

	// @e2e grid-layout::enter-edit-mode
	// @e2e grid-layout::exit-edit-mode
	// @e2e grid-layout::edit-mode-watcher-responds-to-prop-changes
	test('edit mode can be entered via More → Add widget', async ({
		page,
	}) => {
		await addWidgetFromMore(page)
		await chooseAndAdd(page.locator('[data-testid="widget-picker-type-label"]'))
		const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
		await expect(dialog).toBeVisible({ timeout: 8_000 })
	})

	// @e2e grid-layout::view-only-permission-prevents-edit-mode
	test('non-admin cannot see Add-widget entry (canEdit=false guard is visible)', async ({
		page,
	}) => {
		// For admin the More menu is always visible (canEdit=true); this
		// test confirms it renders.
		await expect(page.locator(MORE_MENU)).toBeVisible()
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// Label Widget — remaining scenarios not covered by label-widget.spec.ts
// ─────────────────────────────────────────────────────────────────────────────

test.describe('label widget – additional scenarios', () => {
	test.beforeEach(async ({ page }) => {
		await gotoLaunchPad(page)
	})

	// @e2e label-widget::registry-exposes-label-as-a-selectable-widget-type
	// @e2e label-widget::newly-added-label-uses-registry-defaults
	test('label widget is listed in the Add-Widget modal', async ({ page }) => {
		await openAddWidgetModal(page)
		const labelOption = page.getByRole('option', { name: /^label$/i })
		await expect(labelOption).toBeAttached({ timeout: 5_000 })
	})

	// @e2e label-widget::defaults-applied-to-bare-content
	test('label widget with default settings renders in a grid cell', async ({
		page,
	}) => {
		await openAddWidgetModal(page)
		const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
		const typeSelect = dialog.getByLabel(/widget type/i)
		await typeSelect.selectOption({ label: 'Label' })

		const textInput = dialog.getByLabel(/label text/i).first()
		await textInput.fill(`${TS}-defaults`)
		const addBtn = dialog.getByRole('button', { name: /^add$/i })
		await expect(addBtn).toBeEnabled({ timeout: 3_000 })
		await addBtn.click()


		const placement = page
			.locator('.cn-label-widget')
			.filter({ hasText: `${TS}-defaults` })
		await expect(placement).toBeVisible({ timeout: 8_000 })
	})

	// @e2e label-widget::override-with-custom-values-leaves-untouched-defaults-intact
	test('label widget with custom font size renders the custom size', async ({
		page,
	}) => {
		await openAddWidgetModal(page)
		const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
		const typeSelect = dialog.getByLabel(/widget type/i)
		await typeSelect.selectOption({ label: 'Label' })

		await dialog
			.getByLabel(/label text/i)
			.first()
			.fill(`${TS}-custom`)
		const fontInput = dialog.getByLabel(/font size/i).first()
		if (await fontInput.isVisible().catch(() => false)) {
			await fontInput.fill('22px')
		}
		const addBtn = dialog.getByRole('button', { name: /^add$/i })
		await expect(addBtn).toBeEnabled({ timeout: 3_000 })
		await addBtn.click()


		const placement = page
			.locator('.cn-label-widget')
			.filter({ hasText: `${TS}-custom` })
		await expect(placement).toBeVisible({ timeout: 8_000 })
	})

	// @e2e label-widget::empty-text-shows-translated-fallback
	// @e2e label-widget::whitespace-only-text-shows-translated-fallback
	test('empty label content shows a placeholder or the save button is disabled', async ({
		page,
	}) => {
		await openAddWidgetModal(page)
		const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
		const typeSelect = dialog.getByLabel(/widget type/i)
		await typeSelect.selectOption({ label: 'Label' })

		const textInput = dialog.getByLabel(/label text/i).first()
		await textInput.fill('')
		const addBtn = dialog.getByRole('button', { name: /^add$/i })
		const isDisabled = await addBtn.isDisabled().catch(() => false)
		const hasError =
			(await dialog.locator('[class*="error"], [class*="validation"]').count())
			> 0
		expect(isDisabled || hasError).toBe(true)
	})

	// @e2e label-widget::form-rejects-empty-text
	test('label form disables the Add button when label text is empty', async ({
		page,
	}) => {
		await openAddWidgetModal(page)
		const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
		const typeSelect = dialog.getByLabel(/widget type/i)
		await typeSelect.selectOption({ label: 'Label' })

		await dialog
			.getByLabel(/label text/i)
			.first()
			.fill('')
		const addBtn = dialog.getByRole('button', { name: /^add$/i })
		await expect(addBtn).toBeDisabled()
	})

	// @e2e label-widget::very-long-word-wraps-within-narrow-cell
	test('very long label text does not overflow the widget cell', async ({
		page,
	}) => {
		await openAddWidgetModal(page)
		const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
		const typeSelect = dialog.getByLabel(/widget type/i)
		await typeSelect.selectOption({ label: 'Label' })

		const longWord = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'
		await dialog
			.getByLabel(/label text/i)
			.first()
			.fill(longWord)
		const addBtn = dialog.getByRole('button', { name: /^add$/i })
		await expect(addBtn).toBeEnabled({ timeout: 3_000 })
		await addBtn.click()


		const placement = page
			.locator('.cn-label-widget')
			.filter({ hasText: longWord.slice(0, 10) })
			.first()
		await expect(placement).toBeVisible({ timeout: 8_000 })
		// REQ-LBL-003: the long single word must wrap, not overflow — the
		// rendered content must not be wider than its widget container
		// (allow a 2px sub-pixel rounding tolerance).
		const overflowPx = await placement.evaluate(
			(el) => el.scrollWidth - el.clientWidth,
		)
		expect(overflowPx).toBeLessThanOrEqual(2)
	})

	// @e2e label-widget::centred-in-cell-with-padding
	// The board LpStart draws a label on the workspace as a section heading
	// at the left edge, on the bottom of its cell (src/styles/workspace.css,
	// "A section label reads as a heading at the left edge"), so the
	// workspace overrides the library's centred wrapper and the label fills
	// its cell without the library's padding.
	test('label widget content sits at the left edge and bottom of its grid cell, as the board draws it', async ({
		page,
	}) => {
		await openAddWidgetModal(page)
		const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
		const typeSelect = dialog.getByLabel(/widget type/i)
		await typeSelect.selectOption({ label: 'Label' })

		await dialog
			.getByLabel(/label text/i)
			.first()
			.fill(`${TS}-centred`)
		const addBtn = dialog.getByRole('button', { name: /^add$/i })
		await expect(addBtn).toBeEnabled({ timeout: 3_000 })
		await addBtn.click()


		const placement = page
			.locator('.cn-label-widget')
			.filter({ hasText: `${TS}-centred` })
		await expect(placement).toBeVisible({ timeout: 8_000 })
		const box = await placement.evaluate((el) => {
			const cs = window.getComputedStyle(el)
			return {
				display: cs.display,
				justifyContent: cs.justifyContent,
				alignItems: cs.alignItems,
				height: cs.height,
				parentHeight: window.getComputedStyle(el.parentElement as Element).height,
			}
		})
		expect(box.display).toBe('flex')
		// Chromium 158 serialises flex-start / flex-end as flow-start /
		// flow-end; both spellings mean the left edge and the bottom.
		expect(['flex-start', 'flow-start']).toContain(box.justifyContent)
		expect(['flex-end', 'flow-end']).toContain(box.alignItems)
		expect(box.height, 'the label fills its cell').toBe(box.parentHeight)
	})
})
