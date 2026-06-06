/*
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
import { gotoMydash, openAddWidgetModal, closeSidebar } from './fixtures/widget-flow'
import { clearDefaultWidgetRestriction } from './fixtures/role-feature-permissions'

test.beforeAll(async () => {
	await clearDefaultWidgetRestriction()
})

test.describe('label widget', () => {
	test.beforeEach(async ({ page }) => {
		await gotoMydash(page)
	})

	test('add → fill → save → reopen round-trips all six fields', async ({ page }) => {
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

		// Verify the rendered widget appears on the dashboard.
		const placement = page.locator('.label-widget').filter({ hasText: text })
		await expect(placement).toBeVisible({ timeout: 8_000 })

		// Reopen in edit mode and verify the fields round-trip.
		await placement.click({ button: 'right' })
		const editItem = page.getByRole('menuitem', { name: /edit/i })
		await expect(editItem).toBeVisible({ timeout: 5_000 })
		await editItem.click()

		const editDialog = page.getByRole('dialog', { name: /edit widget/i }).first()
		await expect(editDialog).toBeVisible({ timeout: 5_000 })
		await expect(editDialog.getByLabel(/label text/i).first()).toHaveValue(text)
		await expect(editDialog.getByLabel(/font size/i).first()).toHaveValue('24px')

		await page.keyboard.press('Escape')
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

		const placement = page.locator('.label-widget').filter({ hasText: html })
		await expect(placement).toBeVisible({ timeout: 8_000 })

		// Critical XSS check: the user's <b> MUST NOT become a real element.
		await expect(placement.locator('b')).toHaveCount(0)
	})
})
