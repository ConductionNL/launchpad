/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for AddWidgetModal close-discipline and
 * stale-state scenarios. Covers REQ-WDG-013 (close triggers) and the
 * edit-mode no-stale-state contract from the widget-add-edit-modal spec.
 *
 * Gate-19 @e2e traceability:
 *   @e2e add-widget-modal::cancel-closes-without-submit
 *   @e2e add-widget-modal::esc-closes-without-submit
 *   @e2e add-widget-modal::backdrop-click-closes-without-submit
 *   @e2e add-widget-modal::edit-mode-no-stale-state-on-reopen
 */

import type { APIRequestContext } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { ensureDefaultWidgetRestriction } from './fixtures/role-feature-permissions.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

let api: APIRequestContext
let seeded: SeededDashboard | null = null

// Install a restrictive `default` role-feature-permission; the admin
// break-glass bypass lets the admin still add widgets (see fixtures helper).
let restoreWidgetRestriction: (() => Promise<void>) | null = null

test.beforeAll(async () => {
	restoreWidgetRestriction = await ensureDefaultWidgetRestriction()

	/*
	 * SEED A DASHBOARD. The More menu's "Add widget" acts on the active
	 * dashboard, and `tests/e2e/seed.sh` creates none, so on a fresh instance
	 * the start page would show its empty state instead.
	 */
	api = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	seeded = await seedActiveDashboard(api, `E2E AddWidget ${Date.now()}`)
})

test.afterAll(async () => {
	await restoreWidgetRestriction?.()
})

test.afterAll(async () => {
	await removeSeededDashboard(api, seeded)
	await api?.dispose()
})

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Open the start page and wait for its More menu.
 *
 * @param page the page.
 * @return nothing.
 */
async function gotoLaunchPad(page: any) {
	await page.goto('/index.php/apps/launchpad/')
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
}

/**
 * Open the add-widget modal the way a person does: More, "Add widget", then a
 * Launchpad widget type in the picker.
 *
 * @param page the page, on the start page.
 * @param type the registry key picked in the picker.
 * @return nothing.
 */
async function openAddWidgetModal(page: any, type = 'label') {
	await page.locator(`${MORE_MENU} [data-testid="launchpad-more-menu-trigger"]`).click()
	await page.locator('[data-testid="launchpad-more-menu-item-add-widget"]').click()
	await page.locator(`[data-testid="widget-picker-type-${type}"]`).click()

	await expect(
		page.getByRole('dialog', { name: /add widget/i }).first(),
	).toBeVisible({ timeout: 10_000 })
}

// ─────────────────────────────────────────────────────────────────────────────
// REQ-WDG-013: Close discipline
// ─────────────────────────────────────────────────────────────────────────────

test.describe('add-widget-modal close discipline', () => {
	test.beforeEach(async ({ page }) => {
		await gotoLaunchPad(page)
	})

	// @e2e add-widget-modal::cancel-closes-without-submit
	test('Cancel button closes the modal without placing a widget', async ({
		page,
	}) => {
		await openAddWidgetModal(page)

		const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
		await expect(dialog).toBeVisible()

		// Intercept any POST to the placement API — there must be none on cancel.
		const submissions: string[] = []
		page.on('request', (req) => {
			if (req.method() === 'POST' && /placements|widgets/i.test(req.url())) {
				submissions.push(req.url())
			}
		})

		const cancelBtn = dialog.getByRole('button', { name: /cancel/i })
		await cancelBtn.click()

		// Dialog MUST disappear after cancel.
		await expect(dialog).not.toBeVisible({ timeout: 5_000 })
		// No placement API call MUST have fired.
		expect(submissions).toHaveLength(0)
	})

	// @e2e add-widget-modal::esc-closes-without-submit
	test('Escape key closes the modal without placing a widget', async ({
		page,
	}) => {
		await openAddWidgetModal(page)

		const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
		await expect(dialog).toBeVisible()

		const submissions: string[] = []
		page.on('request', (req) => {
			if (req.method() === 'POST' && /placements|widgets/i.test(req.url())) {
				submissions.push(req.url())
			}
		})

		await page.keyboard.press('Escape')
		await expect(dialog).not.toBeVisible({ timeout: 5_000 })
		expect(submissions).toHaveLength(0)
	})

	// @e2e add-widget-modal::backdrop-click-closes-without-submit
	test('Clicking the backdrop closes the modal without placing a widget', async ({
		page,
	}) => {
		await openAddWidgetModal(page)

		const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
		await expect(dialog).toBeVisible()

		const submissions: string[] = []
		page.on('request', (req) => {
			if (req.method() === 'POST' && /placements|widgets/i.test(req.url())) {
				submissions.push(req.url())
			}
		})

		// The top-left corner of the viewport is backdrop: the dialog panel is centred.
		await page.mouse.click(5, 5)
		await expect(dialog).not.toBeVisible({ timeout: 5_000 })
		expect(submissions).toHaveLength(0)
	})
})

// ─────────────────────────────────────────────────────────────────────────────
// Edit-mode no-stale-state contract
// ─────────────────────────────────────────────────────────────────────────────

test.describe('add-widget-modal edit-mode stale-state', () => {
	// Full add → edit-mode round-trip on a slow dev instance.
	test.describe.configure({ timeout: 90_000 })

	test.beforeEach(async ({ page }) => {
		await gotoLaunchPad(page)
	})

	// @e2e add-widget-modal::edit-mode-no-stale-state-on-reopen
	test('right-click Edit reaches the content editor pre-filled with the placement content', async ({
		page,
	}) => {
		// FIXED 2026-06-07: the right-click context-menu "Edit" now resolves
		// the placement's type from its `widgetId` via the registry and opens
		// the AddWidgetModal in CONTENT edit mode (was: always fell through to
		// the style editor because `placement.type` was never set). This drives
		// the real edit-mode path end-to-end as the admin (under a restrictive
		// `default` role-feature-permission — see beforeAll).
		const text = `Modal Edit ${Date.now()}`

		// Add a label widget via the real add flow.
		await openAddWidgetModal(page, 'label')
		const addDialog = page.getByRole('dialog', { name: /add widget/i }).first()
		await addDialog
			.getByLabel(/label text/i)
			.first()
			.fill(text)
		const addBtn = addDialog.getByRole('button', { name: /^add$/i })
		await expect(addBtn).toBeEnabled({ timeout: 5_000 })
		await addBtn.click()
		await expect(addDialog).not.toBeVisible({ timeout: 8_000 })

		const placement = page
			.locator('.cn-label-widget')
			.filter({ hasText: text })
			.first()
		await expect(placement).toBeVisible({ timeout: 8_000 })

		// Adding a widget puts the dashboard in edit mode, where the
		// right-click popover is available.
		await expect(page.locator('.launchpad-edit-mode')).toBeVisible({ timeout: 8_000 })

		// Right-click the rendered widget content to open the popover, then Edit.
		const cell = page
			.locator('.grid-stack-item')
			.filter({ hasText: text })
			.first()
		await expect(cell).toBeVisible({ timeout: 8_000 })
		await cell
			.locator('.cn-widget-wrapper__content')
			.first()
			.click({ button: 'right' })
		const menu = page.locator('[data-testid="widget-context-menu"]')
		await expect(menu).toBeVisible({ timeout: 5_000 })
		await page.locator('[data-testid="ctx-edit"]').click()

		// The CONTENT editor opens (primary button reads "Save"), pre-filled
		// with the saved label text — the no-stale-state contract. Previously
		// this routed to the STYLE editor (no label-text field, no Save button).
		const editDialog = page
			.getByRole('dialog', { name: /(edit|add) widget/i })
			.first()
		await expect(editDialog).toBeVisible({ timeout: 8_000 })
		await expect(editDialog.getByLabel(/label text/i).first()).toHaveValue(
			text,
			{ timeout: 5_000 },
		)
		await expect(
			editDialog.getByRole('button', { name: /^save$/i }),
		).toBeVisible({ timeout: 5_000 })
	})
})
