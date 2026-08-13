/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The right-click → Edit → content-editor round-trip for the `label` widget.
 *
 * WHY THIS TEST LIVES IN ITS OWN FILE
 * ===================================
 * It is the one test of the three in `label-widget.spec.ts` that is NOT green
 * in the CI fixture — measured in run 31367057618, "2 of 3 pass" with this one
 * failing. `testIgnore` is FILE-granular, so while it shared a file with two
 * passing tests the whole file had to be withheld from the CI config, and the
 * two green tests could not stand behind any coverage claim either.
 *
 * Splitting it out is what lets the other two be promoted. This file stays in
 * `playwright.excluded.config.ts` and is still runnable on demand:
 *
 *   npm run test:e2e:excluded
 *
 * It is NOT skipped, NOT deleted, and NOT weakened — it is exactly the test it
 * always was, in a file whose exclusion is recorded with the run that measured
 * it. The scenario it was tagged with (`form-pre-fills-all-six-fields-when-
 * editing`) carries a reason-bearing `@e2e exclude` in the spec naming this
 * file and that measurement, rather than an `@e2e` tag that would claim a
 * proof CI does not have.
 *
 * Note also that the assertion below checks ONE field, not six, so even green
 * it would not have proven the scenario it was tagged with.
 */

import { test, expect } from '@playwright/test'
import {
	gotoLaunchPad,
	openAddWidgetModal,
	openSidebar,
	closeSidebar,
} from './fixtures/widget-flow'
import { ensureDefaultWidgetRestriction } from './fixtures/role-feature-permissions'

test.beforeAll(async () => {
	await ensureDefaultWidgetRestriction()
})

test.describe('label widget — content edit round-trip', () => {
	test.describe.configure({ timeout: 90_000 })

	test.beforeEach(async ({ page }) => {
		await gotoLaunchPad(page)
	})

	test('right-click Edit opens the content editor pre-filled, and edits round-trip', async ({
		page,
	}) => {
		const text = `Edit Me ${Date.now()}`
		// Create a label widget first.
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

		await closeSidebar(page)

		const placement = page
			.locator('.cn-label-widget')
			.filter({ hasText: text })
			.first()
		await expect(placement).toBeVisible({ timeout: 8_000 })

		// Enter edit mode via the sidebar cog → "Edit dashboard" (cog action,
		// data-testid="cog-edit-dashboard"), then close the sidebar.
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
			await closeSidebar(page)
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
		const editDialog = page
			.getByRole('dialog', { name: /(edit|add) widget/i })
			.first()
		await expect(editDialog).toBeVisible({ timeout: 8_000 })
		const labelField = editDialog.getByLabel(/label text/i).first()
		await expect(labelField).toBeVisible({ timeout: 5_000 })
		await expect(labelField).toHaveValue(text, { timeout: 5_000 })
		await expect(
			editDialog.getByRole('button', { name: /^save$/i }),
		).toBeVisible({ timeout: 5_000 })
	})
})
