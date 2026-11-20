/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the `menu` widget
 * (openspec/specs/menu-widget): registration, save-time validation, the
 * add/edit form and the empty state. Written for e2e-coverage-programme.
 *
 * The form scenarios drive the real Add Widget modal (sidebar → dashboard
 * row cog → "Add custom widget…" → type "Menu") and read the placement the
 * server stored back by id, so "the content updates before save" is proven
 * by what was saved, not by what the form displayed. The rendering scenarios
 * seed a placement with exactly the content the scenario names
 * (`support/menuWidget.ts`) and assert the rendered DOM.
 *
 * The one save the UI cannot make — a four-level tree, because the form
 * stops offering "Add Children" at level 3 — is sent to the placement API
 * directly, which is the only way such a payload can reach the server.
 *
 * Styles, keyboard behaviour and links live in the sibling files
 * `menu-widget-styles.spec.ts` and `menu-widget-links.spec.ts`.
 *
 * Gate-19 @e2e traceability:
 *   @e2e menu-widget::widget-registers-with-default-config
 *   @e2e menu-widget::hierarchical-item-structure
 *   @e2e menu-widget::depth-validation-rejects-4-level-nesting
 *   @e2e menu-widget::three-level-nesting-is-valid
 *   @e2e menu-widget::config-section-controls-all-fields
 *   @e2e menu-widget::depth-validation-prevents-overly-nested-drops
 *   @e2e menu-widget::icon-picker-per-item
 *   @e2e menu-widget::empty-menu-shows-placeholder
 *   @e2e menu-widget::edit-mode-enables-gear-icon-click
 *   @e2e menu-widget::non-empty-menu-hides-placeholder
 *
 * @spec openspec/specs/menu-widget/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import { ensureDefaultWidgetRestriction } from './fixtures/role-feature-permissions.ts'
import { gotoLaunchPad } from './fixtures/widget-flow.ts'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import {
	activate,
	API,
	chooseOption,
	createDashboard,
	DEFAULT_CONTENT,
	EMPTY_TEXT,
	menuWidget,
	openMenuDashboard,
	openMenuForm,
	postMenuPlacement,
	readPlacements,
	removeDashboards,
	seedMenuDashboard,
	submitMenuForm,
} from './support/menuWidget.ts'
import { adminContext } from './support/principals.ts'

const RUN = Date.now()

let api: APIRequestContext
/** Dashboards a test created, removed after it. */
const dashboards: number[] = []
/** Placements the Add Widget modal created, removed after the test. */
const placements: number[] = []

let restoreWidgetRestriction: (() => Promise<void>) | null = null

test.beforeAll(async () => {
	restoreWidgetRestriction = await ensureDefaultWidgetRestriction()
	api = await adminContext()
})

test.afterAll(async () => {
	await restoreWidgetRestriction?.()
})

test.afterEach(async () => {
	while (placements.length > 0) {
		await api.delete(`${API}/widgets/${placements.pop()}`)
	}
	await removeDashboards(api, dashboards)
})

test.afterAll(async () => {
	await api?.dispose()
})

/**
 * Seed and remember a dashboard with one menu placement.
 *
 * @param name a readable part of the dashboard name.
 * @param content the placement content.
 * @return the seeded dashboard.
 */
async function seed(name: string, content: Record<string, unknown>) {
	const dash = await seedMenuDashboard(api, `E2E Menu ${name} ${RUN}`, { content })
	dashboards.push(dash.id)
	return dash
}

/**
 * Seed and activate an EMPTY dashboard, so the Add Widget modal has a home.
 *
 * @param name a readable part of the dashboard name.
 * @return nothing.
 */
async function seedEmptyActive(name: string): Promise<void> {
	const dash = await createDashboard(api, `E2E Menu ${name} ${RUN}`)
	dashboards.push(dash.id)
	await activate(api, dash)
}

test.describe('menu widget — registration and structure (REQ-MENU-001)', () => {
	// @e2e menu-widget::widget-registers-with-default-config
	test('the picker offers Menu, and picking it saves the six-field default content', async ({ page }) => {
		await seedEmptyActive('Defaults')
		await gotoLaunchPad(page)
		const dialog = await openMenuForm(page)

		// Untouched form: what it assembles is exactly the registry default.
		const saved = await submitMenuForm(page, dialog, api)
		placements.push(saved.placementId)
		const six = Object.fromEntries(
			Object.keys(DEFAULT_CONTENT).map((key) => [key, saved.content[key]]),
		)
		expect(six).toEqual(DEFAULT_CONTENT)
	})

	// @e2e menu-widget::hierarchical-item-structure
	test('a parent and its child both render, the child nested under the parent', async ({ page }) => {
		const dash = await seed('Hierarchy', {
			items: [{ label: 'Parent', url: 'https://example.com', children: [{ label: 'Child', url: '/route' }] }],
		})
		const widget = await openMenuDashboard(page, dash)

		const parent = widget.getByRole('menuitem', { name: 'Parent', exact: true })
		await expect(parent).toBeVisible()
		await parent.click()
		const child = widget.getByRole('menuitem', { name: 'Child', exact: true })
		await expect(child).toBeVisible()

		// In the DOM: the child sits inside the parent's own list item.
		// `has` is matched inside each <li>, so it takes a locator without the widget root.
		const parentItem = widget
			.locator('li.cn-menu-widget__bar-item')
			.filter({ has: page.getByRole('menuitem', { name: 'Parent', exact: true }) })
		await expect(parentItem.getByRole('menuitem', { name: 'Child', exact: true })).toBeVisible()

		// Visually: the child is drawn below the parent.
		const p = (await parent.boundingBox())!
		const c = (await child.boundingBox())!
		expect(c.y).toBeGreaterThanOrEqual(p.y + p.height - 1)
	})
})

test.describe('menu widget — save-time depth validation (REQ-MENU-002)', () => {
	// @e2e menu-widget::depth-validation-rejects-4-level-nesting
	test('a four-level tree is refused with 400 and the depth message, and nothing is saved', async () => {
		// The form cannot build this (no "Add Children" at level 3), so the
		// API is the only route by which such a save reaches the server.
		const dash = await createDashboard(api, `E2E Menu Depth4 ${RUN}`)
		dashboards.push(dash.id)

		const res = await postMenuPlacement(api, dash.id, {
			items: [{ label: 'L1', children: [{ label: 'L2', children: [{ label: 'L3', children: [{ label: 'L4' }] }] }] }],
		})
		expect(res.status(), await res.text()).toBe(400)
		expect((await res.json()).error).toBe('Menu items can nest at most 3 levels deep')

		const menus = (await readPlacements(api, dash.id)).filter((p) => p.widgetId === 'menu')
		expect(menus, 'a refused placement must not be stored').toHaveLength(0)
	})

	// @e2e menu-widget::three-level-nesting-is-valid
	test('a three-level tree built in the form saves', async ({ page }) => {
		await seedEmptyActive('Depth3')
		await gotoLaunchPad(page)
		const dialog = await openMenuForm(page)
		const form = dialog.locator('.cn-menu-form')
		const rows = form.locator('.cn-menu-item-editor__row')

		await form.locator('.cn-menu-form__add-top').click()
		await rows.nth(0).getByPlaceholder('Label').fill('L1')
		await rows.nth(0).getByRole('button', { name: /add children/i }).click()
		await rows.nth(1).getByPlaceholder('Label').fill('L2')
		await rows.nth(1).getByRole('button', { name: /add children/i }).click()
		await rows.nth(2).getByPlaceholder('Label').fill('L3')

		const saved = await submitMenuForm(page, dialog, api)
		placements.push(saved.placementId)
		const l1 = saved.content.items?.[0]
		expect(l1?.label).toBe('L1')
		expect(l1?.children?.[0]?.label).toBe('L2')
		expect(l1?.children?.[0]?.children?.[0]?.label).toBe('L3')
		expect(l1?.children?.[0]?.children?.[0]?.children ?? []).toEqual([])
	})
})

test.describe('menu widget — add/edit form (REQ-MENU-009)', () => {
	// @e2e menu-widget::config-section-controls-all-fields
	test('style, orientation, highlight and both toggles are present, and every change is saved', async ({ page }) => {
		await seedEmptyActive('Config')
		await gotoLaunchPad(page)
		const dialog = await openMenuForm(page)
		const form = dialog.locator('.cn-menu-form')
		const selects = form.locator('.v-select')

		await expect(form.getByText('Menu Style', { exact: true })).toBeVisible()
		await expect(form.getByText('Orientation', { exact: true })).toBeVisible()
		await expect(form.getByText('Active Item Highlight', { exact: true })).toBeVisible()
		await expect(form.getByLabel('Show Icons')).toBeChecked()
		// expandedByDefault is a tree-only toggle.
		await expect(form.getByLabel('Expanded by Default')).toHaveCount(0)
		await expect(selects).toHaveCount(3)

		// Orientation first, while it is still offered: Vertical.
		await chooseOption(page, selects.nth(1), 1)
		await expect(selects.nth(1).locator('.vs__selected')).toHaveText(/vertical/i)
		// Style → Tree reveals the tree-only toggle.
		await chooseOption(page, selects.nth(0), 2)
		await expect(selects.nth(0).locator('.vs__selected')).toHaveText(/tree/i)
		await expect(form.getByLabel('Expanded by Default')).toBeVisible()
		// Highlight → Left Bar (options: underline, background, left-bar, none).
		await chooseOption(page, selects.last(), 2)
		await form.getByLabel('Show Icons').uncheck()
		await form.getByLabel('Expanded by Default').check()
		await form.locator('.cn-menu-form__add-top').click()
		await form.locator('.cn-menu-item-editor__row').first().getByPlaceholder('Label').fill('Only')

		const saved = await submitMenuForm(page, dialog, api)
		placements.push(saved.placementId)
		expect(saved.content.style).toBe('tree')
		expect(saved.content.orientation).toBe('vertical')
		expect(saved.content.activeItemHighlight).toBe('left-bar')
		expect(saved.content.showIcons).toBe(false)
		expect(saved.content.expandedByDefault).toBe(true)
		expect(saved.content.items?.map((i: { label: string }) => i.label)).toEqual(['Only'])
	})

	// @e2e menu-widget::depth-validation-prevents-overly-nested-drops
	test('an item with two ancestors reads "Level 3 - max reached" and cannot take children', async ({ page }) => {
		await seedEmptyActive('MaxDepth')
		await gotoLaunchPad(page)
		const dialog = await openMenuForm(page)
		const form = dialog.locator('.cn-menu-form')
		const rows = form.locator('.cn-menu-item-editor__row')

		await form.locator('.cn-menu-form__add-top').click()
		await rows.nth(0).getByRole('button', { name: /add children/i }).click()
		await rows.nth(1).getByRole('button', { name: /add children/i }).click()
		await expect(rows).toHaveCount(3)

		await expect(rows.nth(0).locator('.cn-menu-item-editor__depth')).toHaveText('Level 1')
		await expect(rows.nth(1).locator('.cn-menu-item-editor__depth')).toHaveText('Level 2')
		await expect(rows.nth(2).locator('.cn-menu-item-editor__depth')).toHaveText('Level 3 - max reached')

		// The level-3 row offers no usable "Add Children": absent or disabled.
		const addChild = rows.nth(2).getByRole('button', { name: /add children/i })
		if ((await addChild.count()) > 0) {
			await expect(addChild).toBeDisabled()
		}
		// ...and no fourth row can be produced from it.
		await expect(rows).toHaveCount(3)
	})

	// @e2e menu-widget::icon-picker-per-item
	test('an item\'s icon field opens the icon picker, and the picked icon is saved and drawn', async ({ page }) => {
		await seedEmptyActive('IconPick')
		await gotoLaunchPad(page)
		const dialog = await openMenuForm(page)
		const form = dialog.locator('.cn-menu-form')
		await form.locator('.cn-menu-form__add-top').click()
		const row = form.locator('.cn-menu-item-editor__row').first()
		await row.getByPlaceholder('Label').fill('With icon')

		await row.locator('.cn-icon-browser__trigger').click()
		const picker = page.getByRole('dialog', { name: 'Icon browser' })
		await expect(picker).toBeVisible({ timeout: 5_000 })
		const cell = picker.locator('.cn-icon-browser-panel__cell').first()
		await expect(cell).toBeVisible({ timeout: 10_000 })
		await cell.click()
		// The trigger now shows the chosen icon instead of its placeholder.
		await expect(row.locator('.cn-icon-browser__trigger-placeholder')).toHaveCount(0)

		const saved = await submitMenuForm(page, dialog, api)
		placements.push(saved.placementId)
		const icon = saved.content.items?.[0]?.icon
		expect(typeof icon === 'string' && icon !== '', `saved icon: ${JSON.stringify(icon)}`).toBe(true)

		// And the widget draws it next to the label.
		const item = menuWidget(page).getByRole('menuitem', { name: 'With icon', exact: true })
		await expect(item).toBeVisible({ timeout: 10_000 })
		await expect(item.locator('.cn-menu-widget__icon').locator('svg, img')).toHaveCount(1)
	})
})

test.describe('menu widget — empty state (REQ-MENU-011)', () => {
	// @e2e menu-widget::empty-menu-shows-placeholder
	test('an empty menu shows the placeholder and its gear, inert for a viewer', async ({ page }) => {
		const dash = await seed('Empty', { items: [] })
		const widget = await openMenuDashboard(page, dash)

		const empty = widget.locator('.cn-menu-widget__empty')
		await expect(empty).toContainText(EMPTY_TEXT)
		await expect(empty.locator('.cn-menu-widget__empty-icon')).toBeVisible()
		// Not editing: the message is not a control.
		await expect(empty).not.toHaveAttribute('role', 'button')
	})

	// @e2e menu-widget::edit-mode-enables-gear-icon-click
	test('in edit mode the gear opens the edit form, where items can be added', async ({ page }) => {
		const dash = await seed('EmptyEdit', { items: [] })
		await openMenuDashboard(page, dash)
		await enterEditMode(page)

		const gear = menuWidget(page).locator('.cn-menu-widget__empty-icon')
		await expect(gear).toBeVisible()
		await gear.click()

		const dialog = page.getByRole('dialog', { name: /edit widget/i }).first()
		await expect(dialog).toBeVisible({ timeout: 8_000 })
		const form = dialog.locator('.cn-menu-form')
		await form.locator('.cn-menu-form__add-top').click()
		await expect(form.locator('.cn-menu-item-editor__row')).toHaveCount(1)
	})

	// @e2e menu-widget::non-empty-menu-hides-placeholder
	test('a menu with an item renders the item and no placeholder', async ({ page }) => {
		const dash = await seed('NonEmpty', { items: [{ label: 'Item', url: '/path' }] })
		const widget = await openMenuDashboard(page, dash)

		await expect(widget.getByRole('menubar')).toBeVisible()
		await expect(widget.getByRole('menuitem', { name: 'Item', exact: true })).toBeVisible()
		await expect(widget.locator('.cn-menu-widget__empty')).toHaveCount(0)
		await expect(widget.getByText(EMPTY_TEXT)).toHaveCount(0)
	})
})
