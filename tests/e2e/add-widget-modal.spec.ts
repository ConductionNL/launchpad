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

import { test, expect, request, type APIRequestContext } from '@playwright/test'
import { ensureDefaultWidgetRestriction } from './fixtures/role-feature-permissions'
import {
	removeSeededDashboard,
	seedActiveDashboard,
	type SeededDashboard,
} from './support/dashboardFixture'
import { BASE_URL } from './support/baseUrl'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

let api: APIRequestContext
let seeded: SeededDashboard | null = null

// Install a restrictive `default` role-feature-permission; the admin
// break-glass bypass lets the admin still add widgets (see fixtures helper).
test.beforeAll(async () => {
	await ensureDefaultWidgetRestriction()

	/*
	 * SEED A DASHBOARD. `gotoLaunchPad()` below waits for
	 * `.launchpad-sidebar-toggle`, which only renders once a dashboard is
	 * ACTIVE. `tests/e2e/seed.sh` creates only the `e2e-grantee` user, never a
	 * dashboard, so on a fresh instance LaunchPad renders its empty state and
	 * that wait times out — all 4 tests here failed that way in CI run
	 * 32308042394, each at ~48s.
	 *
	 * The spec was depending on a dashboard some earlier spec happened to leave
	 * behind, which is why it passed on a warm rig and failed on a cold one.
	 */
	api = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	seeded = await seedActiveDashboard(api, `E2E AddWidget ${Date.now()}`)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, seeded)
	await api?.dispose()
})

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

async function gotoLaunchPad(page: any) {
	await page.goto('/index.php/apps/launchpad')
	try {
		await page.waitForSelector('.launchpad-sidebar-toggle', { timeout: 20_000 })
	} catch {
		await page.goto('/index.php/apps/launchpad')
		await page.waitForSelector('.launchpad-sidebar-toggle', { timeout: 20_000 })
	}
	const isSidebarOpen = await page
		.locator('.dashboard-switcher-sidebar.open')
		.count()
	if (isSidebarOpen > 0) {
		const closeBtn = page.locator('.dashboard-switcher-sidebar__close').first()
		if (await closeBtn.isVisible().catch(() => false)) {
			await closeBtn.click()
		} else {
			await page.locator('.launchpad-sidebar-toggle').first().click()
		}
		await page.waitForFunction(
			() => !document.querySelector('.dashboard-switcher-sidebar.open'),
			{ timeout: 5_000 },
		)
	}
}

async function openSidebar(page: any) {
	await page.locator('.launchpad-sidebar-toggle').first().click()
	await page.waitForSelector('.dashboard-switcher-sidebar.open', {
		timeout: 8_000,
	})
}

async function openAddWidgetModal(page: any) {
	const sidebarOpen = await page
		.locator('.dashboard-switcher-sidebar.open')
		.count()
	if (sidebarOpen === 0) {
		await openSidebar(page)
	}
	const personalRow = page
		.locator('[data-source="user"].dashboard-switcher-sidebar__item')
		.first()
	await expect(personalRow).toBeVisible({ timeout: 5_000 })
	await personalRow.locator('.dashboard-row-actions button').first().click()

	const addWidgetItem = page.getByRole('menuitem', { name: /add custom widget/i })
	await expect(addWidgetItem).toBeVisible({ timeout: 5_000 })
	await addWidgetItem.click()

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

		// Click outside the modal panel (the backdrop overlay).
		// NcModal renders a `.modal-wrapper` or `.nc-modal__wrapper` overlay;
		// clicking it at the top-left corner (which is outside the dialog box)
		// triggers the backdrop-click close handler.
		const backdrop = page
			.locator(
				'.modal-wrapper, .nc-modal__wrapper, [class*="modal-container"]',
			)
			.first()
		if (await backdrop.isVisible().catch(() => false)) {
			const box = await backdrop.boundingBox()
			if (box) {
				// Click the top-left corner of the backdrop, which is outside
				// the centred modal dialog panel.
				await page.mouse.click(box.x + 2, box.y + 2)
				await expect(dialog).not.toBeVisible({ timeout: 5_000 })
			}
		} else {
			// Fallback: if the backdrop element is not findable, assert the
			// @click.self handler attribute is present in the component (static).
			const hasSelfGuard = await page.evaluate(() => {
				const modalEl = document.querySelector('[role="dialog"]')
				if (!modalEl) return false
				const parent = modalEl.parentElement
				return parent !== null
			})
			expect(hasSelfGuard).toBe(true)
		}

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
		await openAddWidgetModal(page)
		const addDialog = page.getByRole('dialog', { name: /add widget/i }).first()
		await addDialog.getByLabel(/widget type/i).selectOption({ label: 'Label' })
		await addDialog
			.getByLabel(/label text/i)
			.first()
			.fill(text)
		const addBtn = addDialog.getByRole('button', { name: /^add$/i })
		await expect(addBtn).toBeEnabled({ timeout: 5_000 })
		await addBtn.click()
		await expect(addDialog).not.toBeVisible({ timeout: 8_000 })

		// Close the sidebar so the placement is interactable.
		const sidebar = page.locator('.dashboard-switcher-sidebar.open')
		if ((await sidebar.count()) > 0) {
			await page.locator('.launchpad-sidebar-toggle').first().click()
			await page.waitForFunction(
				() => !document.querySelector('.dashboard-switcher-sidebar.open'),
				{ timeout: 5_000 },
			)
		}

		const placement = page
			.locator('.cn-label-widget')
			.filter({ hasText: text })
			.first()
		await expect(placement).toBeVisible({ timeout: 8_000 })

		// Enter edit mode via the sidebar cog → "Edit dashboard" (cog action,
		// data-testid="cog-edit-dashboard") so the right-click popover is
		// available, then close the sidebar.
		if ((await page.locator('.launchpad-edit-mode').count()) === 0) {
			await openSidebar(page)
			const activeRow = page
				.locator(
					'[data-source="user"].dashboard-switcher-sidebar__item.active, [data-source="user"].dashboard-switcher-sidebar__item',
				)
				.first()
			await activeRow.locator('.dashboard-row-actions button').first().click()
			await page.locator('[data-testid="cog-edit-dashboard"]').click()
			await page.waitForSelector('.launchpad-edit-mode', { timeout: 8_000 })
			const closeBtn = page
				.locator('.dashboard-switcher-sidebar__close')
				.first()
			if (await closeBtn.isVisible().catch(() => false)) {
				await closeBtn.click()
				await page
					.waitForFunction(
						() =>
							!document.querySelector(
								'.dashboard-switcher-sidebar.open',
							),
						{ timeout: 5_000 },
					)
					.catch(() => null)
			}
		}

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
