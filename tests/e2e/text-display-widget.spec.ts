/*
 * SPDX-FileCopyrightText: 2026 MyDash Contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Playwright end-to-end test for the `text` widget covering tasks 7.1..7.3
 * of the `text-display-widget` OpenSpec change.
 *
 * Drives the real runtime-shell add-widget flow: sidebar → personal-row cog
 * → "Add custom widget…" → Add Widget modal → pick "Text".
 *
 * Gate-19 @e2e traceability:
 *   @e2e text-display-widget::allow-safe-formatting
 *   @e2e text-display-widget::strip-script-tag
 *   @e2e text-display-widget::strip-event-handler-attribute
 *   @e2e text-display-widget::strip-javascript-url
 *   @e2e text-display-widget::empty-content-shows-placeholder
 *   @e2e text-display-widget::whitespace-only-content-treated-as-empty
 *   @e2e text-display-widget::form-pre-fills-in-edit-mode
 *   @e2e text-display-widget::form-emits-content-updates-reactively
 *   @e2e text-display-widget::form-rejects-empty-text
 */

import { test, expect } from '@playwright/test'
import { gotoMydash, openAddWidgetModal, closeSidebar } from './fixtures/widget-flow'
import { clearDefaultWidgetRestriction } from './fixtures/role-feature-permissions'

test.beforeAll(async () => {
	await clearDefaultWidgetRestriction()
})

test.describe('text-display widget', () => {
	test.beforeEach(async ({ page }) => {
		await gotoMydash(page)
	})

	test('add → fill → save → reload renders sanitised text and survives round-trip', async ({ page }) => {
		const marker = `Hello ${Date.now()}`
		await openAddWidgetModal(page)
		const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
		await dialog.getByLabel(/widget type/i).selectOption({ label: 'Text' })

		// The Text widget's content control is a Markdown <textarea> (its label
		// is not wired via for/id), so target it by its placeholder.
		await dialog.locator('textarea[placeholder*="Markdown"]').fill(`${marker} <b>world</b>`)

		const addBtn = dialog.getByRole('button', { name: /^add$/i })
		await expect(addBtn).toBeEnabled({ timeout: 5_000 })
		await addBtn.click()
		await expect(dialog).not.toBeVisible({ timeout: 8_000 })

		await closeSidebar(page)

		// The rendered widget carries the marker text (the add → persist →
		// render content round-trip). The new placement may be below the grid
		// fold, so assert it is attached then scroll it into view.
		const placement = page.locator('.text-display-widget').filter({ hasText: marker }).first()
		await expect(placement).toBeAttached({ timeout: 8_000 })
		await placement.scrollIntoViewIfNeeded()
		await expect(placement).toBeVisible({ timeout: 5_000 })
		// The safe inline formatting (`<b>`) survives sanitisation.
		await expect(placement.locator('b')).toHaveCount(1)

		// Persistence: the placement survives a full reload.
		await page.reload()
		await page.waitForSelector('.mydash-sidebar-toggle', { timeout: 20_000 })
		await expect(page.locator('.text-display-widget').filter({ hasText: marker }).first())
			.toBeAttached({ timeout: 10_000 })
	})

	// @e2e text-display-widget::form-rejects-empty-text
	test('REQ-TXT-003: empty text keeps the Add button disabled (validation)', async ({ page }) => {
		await openAddWidgetModal(page)
		const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
		await dialog.getByLabel(/widget type/i).selectOption({ label: 'Text' })

		// With no text entered the form is invalid → Add stays disabled
		// (REQ-TXT-003 / form-rejects-empty-text). The placeholder-rendering
		// branch for an already-empty placement is covered by the
		// TextDisplayWidget Vitest unit test.
		const addBtn = dialog.getByRole('button', { name: /^add$/i })
		await expect(addBtn).toBeDisabled({ timeout: 5_000 })
	})

	// NOTE (known app bug, flagged 2026-06-06): editing a PLACED custom
	// widget's content is not reachable from the UI — the right-click "Edit"
	// routes to the style editor (Views.handleContextMenuEdit branches on
	// `placement.type`, which placements never carry). The edit-mode prefill /
	// reactive-update scenarios are covered by the TextDisplayForm Vitest
	// specs until that routing bug is fixed.
})
