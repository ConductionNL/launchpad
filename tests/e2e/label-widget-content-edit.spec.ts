/*
 * SPDX-FileCopyrightText: 2026 LaunchPad Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The right-click -> Edit -> content-editor round-trip for the `label` widget.
 *
 * This file used to sit in `playwright.excluded.config.ts`. It was promoted
 * back into the CI config on 2026-08-19 (see the PROMOTED note in
 * `playwright.config.ts`), so it now runs in the root suite.
 *
 * The first test is the original round-trip check and asserts ONE field.
 * The second was written for e2e-coverage-programme task 4.21 and is the one
 * that proves `label-widget::form-pre-fills-all-six-fields-when-editing`: it
 * seeds a label placement carrying all six content fields over the API,
 * opens it in the edit form through the right-click menu the way a person
 * does, and reads back every one of the six controls.
 */

import type { APIRequestContext, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, test } from '@playwright/test'
import { request } from '@playwright/test'
import { ensureDefaultWidgetRestriction } from './fixtures/role-feature-permissions.ts'
import {
	closeSidebar,
	gotoLaunchPad,
	openAddWidgetModal,
	openSidebar,
} from './fixtures/widget-flow.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

const API = '/index.php/apps/launchpad/api'

/**
 * The scenario's own content blob, verbatim: every one of the six fields
 * carries a value that differs from the registry default
 * (`{text: '', fontSize: '16px', color: '', backgroundColor: '',
 * fontWeight: 'bold', textAlign: 'center'}`), so a form that ignored
 * `editingWidget.content` and fell back to defaults fails every assertion.
 */
const SIX_FIELDS = {
	text: 'Hi',
	fontSize: '20px',
	color: '#ff0000',
	backgroundColor: '#ffffff',
	fontWeight: '700',
	textAlign: 'right',
}

let api: APIRequestContext
let seeded: SeededDashboard | null = null
let sixFieldPlacementId = 0

let restoreWidgetRestriction: (() => Promise<void>) | null = null

test.beforeAll(async () => {
	restoreWidgetRestriction = await ensureDefaultWidgetRestriction()

	/*
	 * SEED A DASHBOARD. `gotoLaunchPad()` needs the workspace shell, which only
	 * renders once a dashboard is ACTIVE — `tests/e2e/seed.sh` creates the
	 * `e2e-grantee` user and nothing else. On a fresh instance LaunchPad shows
	 * "No dashboards available" instead and every locator here misses.
	 */
	api = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	seeded = await seedActiveDashboard(api, `E2E LabelEdit ${Date.now()}`)

	// The placement the pre-fill test opens. Seeded over the API (seeding is
	// what the API is for here); the test then reaches it only through the UI.
	const placed = await api.post(`${API}/dashboard/${seeded.id}/widgets`, {
		data: {
			widgetId: 'label',
			gridX: 0,
			gridY: 0,
			gridWidth: 4,
			gridHeight: 2,
			content: SIX_FIELDS,
		},
	})
	expect(placed.status(), await placed.text()).toBe(201)
	const body = await placed.json()
	sixFieldPlacementId = Number(body.id ?? body.placement?.id ?? body.data?.id)
	expect(
		sixFieldPlacementId,
		`no placement id: ${JSON.stringify(body)}`,
	).toBeTruthy()
})

test.afterAll(async () => {
	await restoreWidgetRestriction?.()
})

test.afterAll(async () => {
	if (sixFieldPlacementId !== 0) {
		await api.delete(`${API}/widgets/${sixFieldPlacementId}`)
	}
	await removeSeededDashboard(api, seeded)
	await api?.dispose()
})

/**
 * Put the active dashboard into edit mode (sidebar cog -> "Edit dashboard"),
 * the precondition for the right-click widget menu, and close the sidebar.
 *
 * @param page The Playwright page, already on the LaunchPad workspace.
 */
async function enterEditMode(page: Page): Promise<void> {
	if ((await page.locator('.launchpad-edit-mode').count()) > 0) {
		return
	}
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

		// Enter edit mode via the sidebar cog -> "Edit dashboard".
		await enterEditMode(page)

		// Right-click the rendered widget content to open the popover, then Edit.
		const cell = page
			.locator('.grid-stack-item')
			.filter({ hasText: text })
			.first()
		await expect(cell).toBeVisible({ timeout: 8_000 })
		/*
		 * `dispatchEvent('contextmenu')` rather than `.click({button:'right'})`.
		 *
		 * The real click is ACTIONABILITY-CHECKED, and in CI the grid cell fails
		 * that check: Playwright reports "element is visible, enabled and
		 * stable → scrolling into view if needed → done scrolling → element is
		 * outside of the viewport", then retries ~23 times and times out. It
		 * passes when the file runs alone and fails at position 66 of 133, so
		 * the cell's position depends on what the run did before it.
		 *
		 * The handler under test is a plain `@contextmenu` listener, so
		 * dispatching the event directly exercises exactly the same code path
		 * without depending on where the cell happens to sit. This is the same
		 * remedy `image-widget.spec.ts` already uses for the identical symptom.
		 */
		await cell
			.locator('.cn-widget-wrapper__content')
			.first()
			.dispatchEvent('contextmenu')
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

	// @e2e label-widget::form-pre-fills-all-six-fields-when-editing
	test('REQ-LBL-005: right-click Edit pre-fills all six label controls from the saved content', async ({
		page,
	}) => {
		await enterEditMode(page)

		// The seeded placement, found by its rendered text (exactly "Hi").
		const cell = page
			.locator('.grid-stack-item')
			.filter({
				has: page.locator('.cn-label-widget__text', {
					hasText: /^\s*Hi\s*$/,
				}),
			})
			.first()
		await expect(cell).toBeVisible({ timeout: 10_000 })

		// Same reason as above for `dispatchEvent` over a real right-click.
		await cell
			.locator('.cn-widget-wrapper__content')
			.first()
			.dispatchEvent('contextmenu')
		await expect(
			page.locator('[data-testid="widget-context-menu"]'),
		).toBeVisible({
			timeout: 5_000,
		})
		await page.locator('[data-testid="ctx-edit"]').click()

		const editDialog = page
			.getByRole('dialog', { name: /(edit|add) widget/i })
			.first()
		await expect(editDialog).toBeVisible({ timeout: 8_000 })
		// Edit mode, not a fresh add: the primary action reads "Save".
		await expect(
			editDialog.getByRole('button', { name: /^save$/i }),
		).toBeVisible({ timeout: 5_000 })

		// Everything below is scoped to the label sub-form, so a same-named
		// control elsewhere in the modal (the Appearance section) cannot answer.
		const form = editDialog.locator('.cn-label-widget-form')
		await expect(form).toBeVisible({ timeout: 5_000 })

		// 1. text — NcTextField.
		await expect(form.getByLabel(/label text/i).first()).toHaveValue(
			SIX_FIELDS.text,
		)

		// 2. fontSize — NcTextField.
		await expect(form.getByLabel(/font size/i).first()).toHaveValue(
			SIX_FIELDS.fontSize,
		)

		// 3 + 4. color and backgroundColor — CnColorPicker. Its only rendering
		// of the value is the swatch button's fill, a
		// `linear-gradient(<c>, <c>)` layered over a checker; with no value the
		// swatch has no inline style at all. The computed style serialises the
		// hex as rgb().
		const swatch = (name: RegExp) =>
			form
				.locator('.cn-label-widget-form__color-label')
				.filter({ hasText: name })
				.locator('.cn-color-picker__swatch')
				.first()
		await expect(swatch(/^\s*Color\s*$/)).toHaveCSS(
			'background-image',
			/linear-gradient\(rgb\(255, 0, 0\), rgb\(255, 0, 0\)\)/,
		)
		await expect(swatch(/^\s*Background color\s*$/)).toHaveCSS(
			'background-image',
			/linear-gradient\(rgb\(255, 255, 255\), rgb\(255, 255, 255\)\)/,
		)

		// 5 + 6. fontWeight and textAlign — NcSelect (vue-select); the chosen
		// option renders in `.vs__selected`.
		const selected = (name: RegExp) =>
			form
				.locator('.v-select')
				.filter({ has: page.getByText(name) })
				.locator('.vs__selected')
				.first()
		await expect(selected(/^Font weight$/)).toHaveText(SIX_FIELDS.fontWeight)
		await expect(selected(/^Alignment$/)).toHaveText(SIX_FIELDS.textAlign)
	})
})
