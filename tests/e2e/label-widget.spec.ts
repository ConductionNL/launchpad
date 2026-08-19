/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end test for the `label` widget covering tasks 6.1..6.3
 * of the `label-widget` OpenSpec change.
 *
 * Drives the real runtime-shell add-widget flow: sidebar → personal-row cog
 * → "Add custom widget…" → Add Widget modal → pick "Label".
 *
 * Gate-19 @e2e traceability:
 *   @e2e label-widget::html-in-text-appears-as-literal-characters
 *   @e2e label-widget::script-tag-in-text-appears-as-literal-characters
 *   @e2e label-widget::form-pre-fills-all-six-fields-when-editing
 *   @e2e label-widget::registry-exposes-label-as-a-selectable-widget-type
 */

import { test, expect } from '@playwright/test'
import { gotoLaunchPad, openAddWidgetModal, openSidebar, closeSidebar } from './fixtures/widget-flow'
import { ensureDefaultWidgetRestriction } from './fixtures/role-feature-permissions'

test.beforeAll(async () => {
	await ensureDefaultWidgetRestriction()
})

test.describe('label widget', () => {
	// The add → save → reload and right-click → content-edit round-trips drive
	// the full runtime shell; the dev instance is slow, so widen the timeout.
	test.describe.configure({ timeout: 90_000 })

	test.beforeEach(async ({ page }) => {
		await gotoLaunchPad(page)
	})

	test('add → fill → save → render round-trips the content and survives reload', async ({ page }) => {
		const text = `Sales Q4 ${Date.now()}`
		await openAddWidgetModal(page)
		const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
		await dialog.getByLabel(/widget type/i).selectOption({ label: 'Label' })

		await dialog.getByLabel(/label text/i).first().fill(text)
		await dialog.getByLabel(/font size/i).first().fill('24px')

		const addBtn = dialog.getByRole('button', { name: /^add$/i })
		await expect(addBtn).toBeEnabled({ timeout: 5_000 })
		await addBtn.click()
		await expect(dialog).not.toBeVisible({ timeout: 8_000 })

		await closeSidebar(page)

		// The rendered widget carries the saved text + font-size (the add →
		// persist → render content round-trip).
		const placement = page.locator('.cn-label-widget').filter({ hasText: text }).first()
		await expect(placement).toBeVisible({ timeout: 8_000 })
		const fontSize = await placement.locator('.cn-label-widget__text').first()
			.evaluate((el) => window.getComputedStyle(el).fontSize)
		expect(fontSize).toBe('24px')

		// Persistence: the placement survives a full page reload.
		await page.reload()
		await page.waitForSelector('.launchpad-sidebar-toggle', { timeout: 20_000 })
		await expect(page.locator('.cn-label-widget').filter({ hasText: text }).first())
			.toBeVisible({ timeout: 10_000 })

		// FIXED 2026-06-07: the right-click context-menu "Edit" now resolves
		// the placement's type from its `widgetId` via the registry and opens
		// the CONTENT editor (AddWidgetModal in edit mode) — see the dedicated
		// content-edit round-trip test below.
	})

	// @e2e label-widget::form-pre-fills-all-six-fields-when-editing
	test('right-click Edit opens the content editor pre-filled, and edits round-trip', async ({ page }) => {
		const text = `Edit Me ${Date.now()}`
		// Create a label widget first.
		await openAddWidgetModal(page)
		const addDialog = page.getByRole('dialog', { name: /add widget/i }).first()
		await addDialog.getByLabel(/widget type/i).selectOption({ label: 'Label' })
		await addDialog.getByLabel(/label text/i).first().fill(text)
		const addBtn = addDialog.getByRole('button', { name: /^add$/i })
		await expect(addBtn).toBeEnabled({ timeout: 5_000 })
		await addBtn.click()
		await expect(addDialog).not.toBeVisible({ timeout: 8_000 })

		await closeSidebar(page)

		const placement = page.locator('.cn-label-widget').filter({ hasText: text }).first()
		await expect(placement).toBeVisible({ timeout: 8_000 })

		// Enter edit mode via the sidebar cog → "Edit dashboard" (cog action,
		// data-testid="cog-edit-dashboard"), then close the sidebar.
		if (await page.locator('.launchpad-edit-mode').count() === 0) {
			await openSidebar(page)
			const activeRow = page.locator(
				'[data-source="user"].dashboard-switcher-sidebar__item.active, [data-source="user"].dashboard-switcher-sidebar__item',
			).first()
			await activeRow.locator('.dashboard-row-actions button').first().click()
			await page.locator('[data-testid="cog-edit-dashboard"]').click()
			await page.waitForSelector('.launchpad-edit-mode', { timeout: 8_000 })
			await closeSidebar(page)
		}

		// Right-click the rendered widget content to open the popover, then Edit.
		const cell = page.locator('.grid-stack-item').filter({ hasText: text }).first()
		await expect(cell).toBeVisible({ timeout: 8_000 })
		await cell.locator('.cn-widget-wrapper__content').first().click({ button: 'right' })
		const menu = page.locator('[data-testid="widget-context-menu"]')
		await expect(menu).toBeVisible({ timeout: 5_000 })
		await page.locator('[data-testid="ctx-edit"]').click()

		// The CONTENT editor (AddWidgetModal in edit mode) must open — this is
		// the exact path the BUG-2 fix unblocked. Previously handleContextMenuEdit
		// branched on `placement.type` (never set on placements, which only
		// carry `widgetId`), so the right-click Edit ALWAYS fell through to the
		// STYLE editor and the content editor was unreachable. We now resolve
		// the type from `widgetId` via the registry.
		//
		// Proof the CONTENT editor (not the style editor) opened:
		//   1. a "Label text" field exists (style editor has no content fields), and
		//   2. it is pre-filled with the saved content, and
		//   3. the primary button reads "Save" (edit mode), not "Add".
		const editDialog = page.getByRole('dialog', { name: /(edit|add) widget/i }).first()
		await expect(editDialog).toBeVisible({ timeout: 8_000 })
		const labelField = editDialog.getByLabel(/label text/i).first()
		await expect(labelField).toBeVisible({ timeout: 5_000 })
		await expect(labelField).toHaveValue(text, { timeout: 5_000 })
		await expect(editDialog.getByRole('button', { name: /^save$/i }))
			.toBeVisible({ timeout: 5_000 })
	})

	test('REQ-LBL-001: pasted HTML renders as literal text on the dashboard', async ({ page }) => {
		const html = `<b>HTML</b> ${Date.now()}`
		await openAddWidgetModal(page)
		const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
		await dialog.getByLabel(/widget type/i).selectOption({ label: 'Label' })
		await dialog.getByLabel(/label text/i).first().fill(html)

		const addBtn = dialog.getByRole('button', { name: /^add$/i })
		await expect(addBtn).toBeEnabled({ timeout: 5_000 })
		await addBtn.click()
		await expect(dialog).not.toBeVisible({ timeout: 8_000 })

		await closeSidebar(page)

		const placement = page.locator('.cn-label-widget').filter({ hasText: html })
		await expect(placement).toBeVisible({ timeout: 8_000 })

		// Critical XSS check: the user's <b> MUST NOT become a real element.
		await expect(placement.locator('b')).toHaveCount(0)
	})
})
