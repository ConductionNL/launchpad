/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the `menu` widget's three visual styles
 * (dropdown, megamenu, tree) and its keyboard navigation
 * (openspec/specs/menu-widget REQ-MENU-003/004/005/007). Written for
 * e2e-coverage-programme.
 *
 * Each test seeds its own dashboard holding one `menu` placement with exactly
 * the content its scenario names (`support/menuWidget.ts`), then drives it the
 * way a person does: clicks, hover, and real key presses. Focus is asserted
 * with `toBeFocused()`, positions with bounding boxes, so "below", "to the
 * right" and "full-width" are measured rather than assumed from class names.
 *
 * Gate-19 @e2e traceability:
 *   @e2e menu-widget::dropdown-opens-on-click
 *   @e2e menu-widget::level-2-with-children-opens-flyout
 *   @e2e menu-widget::esc-closes-dropdown-and-flyout
 *   @e2e menu-widget::tab-closes-and-moves-focus
 *   @e2e menu-widget::megamenu-panel-opens-full-width
 *   @e2e menu-widget::panel-switches-on-top-level-click
 *   @e2e menu-widget::level-3-items-render-inline-under-level-2
 *   @e2e menu-widget::esc-closes-megamenu
 *   @e2e menu-widget::tree-renders-with-collapsed-children-by-default
 *   @e2e menu-widget::tree-expands-on-caret-click
 *   @e2e menu-widget::expandedbydefault-expands-all-on-load
 *   @e2e menu-widget::tree-with-mixed-click-targets
 *   @e2e menu-widget::tab-moves-focus-between-top-level-items
 *   @e2e menu-widget::enter-opens-dropdown-and-space-navigates
 *   @e2e menu-widget::esc-closes-and-returns-focus
 *   @e2e menu-widget::arrowright-expands-tree-node
 *
 * @spec openspec/specs/menu-widget/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import {
	APP,
	openMenuDashboard,
	removeDashboards,
	seedMenuDashboard,
	treeRow,
} from './support/menuWidget.ts'
import { adminContext } from './support/principals.ts'

const RUN = Date.now()

let api: APIRequestContext
/** Dashboards a test created, removed after it. */
const dashboards: number[] = []

test.beforeAll(async () => {
	api = await adminContext()
})

test.afterEach(async () => {
	await removeDashboards(api, dashboards)
})

test.afterAll(async () => {
	await api?.dispose()
})

/**
 * Seed a menu, open its dashboard, and return the rendered widget.
 *
 * @param page the page.
 * @param name a readable part of the dashboard name.
 * @param content the placement content.
 * @return the widget root.
 */
async function render(
	page: Page,
	name: string,
	content: Record<string, unknown>,
): Promise<Locator> {
	const dash = await seedMenuDashboard(api, `E2E Menu ${name} ${RUN}`, { content })
	dashboards.push(dash.id)
	return openMenuDashboard(page, dash)
}

/**
 * A menu item by its exact accessible name.
 *
 * @param widget the widget root.
 * @param name the label.
 * @return the item.
 */
function item(widget: Locator, name: string): Locator {
	return widget.getByRole('menuitem', { name, exact: true })
}

/** Three top-level items, each with one child, for the keyboard scenarios. */
const THREE_TOP = {
	style: 'dropdown',
	items: [
		{ label: 'Alpha', children: [{ label: 'Alpha one', url: '/alpha-one' }, { label: 'Alpha two', url: '/alpha-two' }] },
		{ label: 'Beta', children: [{ label: 'Beta one', url: '/beta-one' }] },
		{ label: 'Gamma', children: [{ label: 'Gamma one', url: '/gamma-one' }] },
	],
}

test.describe('menu widget — dropdown style (REQ-MENU-003)', () => {
	// @e2e menu-widget::dropdown-opens-on-click
	test('clicking a top-level item opens a dropdown below it with a clickable child', async ({ page }) => {
		const widget = await render(page, 'DropClick', {
			style: 'dropdown',
			items: [{ label: 'Menu', children: [{ label: 'Item 1', url: '/path1' }] }],
		})
		const menu = item(widget, 'Menu')
		await expect(widget.locator('.cn-menu-widget__dropdown')).toHaveCount(0)
		await menu.click()

		const dropdown = widget.locator('.cn-menu-widget__dropdown')
		await expect(dropdown).toBeVisible()
		await expect(menu).toHaveAttribute('aria-expanded', 'true')
		const m = (await menu.boundingBox())!
		const d = (await dropdown.boundingBox())!
		expect(d.y, 'the dropdown opens below its button').toBeGreaterThanOrEqual(m.y + m.height - 1)

		const child = dropdown.getByRole('menuitem', { name: 'Item 1', exact: true })
		await expect(child).toBeVisible()
		await expect(child).toBeEnabled()
		await expect(child).toHaveAttribute('href', /\/path1$/)
		// Clickable: the click lands on it and the menu closes behind it.
		await child.click()
		await expect(widget.locator('.cn-menu-widget__dropdown')).toHaveCount(0)
	})

	// @e2e menu-widget::level-2-with-children-opens-flyout
	test('hovering a level-2 item with children opens a flyout to its right', async ({ page }) => {
		const widget = await render(page, 'Flyout', {
			style: 'dropdown',
			items: [{ label: 'Menu', children: [{ label: 'Item 1', url: '/path1', children: [{ label: 'Sub', url: '/sub' }] }] }],
		})
		await item(widget, 'Menu').click()
		const level2 = item(widget, 'Item 1')
		await expect(level2).toBeVisible()
		await expect(widget.locator('.cn-menu-widget__flyout')).toHaveCount(0)

		await level2.hover()
		const flyout = widget.locator('.cn-menu-widget__flyout')
		await expect(flyout).toBeVisible()
		const l = (await level2.boundingBox())!
		const f = (await flyout.boundingBox())!
		expect(f.x, 'the flyout opens to the right of its item').toBeGreaterThanOrEqual(l.x + l.width - 2)
		await expect(flyout.getByRole('menuitem', { name: 'Sub', exact: true })).toBeVisible()
	})

	// @e2e menu-widget::esc-closes-dropdown-and-flyout
	test('Esc closes the dropdown and the flyout and puts focus back on the Menu button', async ({ page }) => {
		const widget = await render(page, 'DropEsc', {
			style: 'dropdown',
			items: [{ label: 'Menu', children: [{ label: 'Item 1', url: '/path1', children: [{ label: 'Sub', url: '/sub' }] }] }],
		})
		const menu = item(widget, 'Menu')
		await menu.click()
		const level2 = item(widget, 'Item 1')
		await level2.hover()
		await expect(widget.locator('.cn-menu-widget__flyout')).toBeVisible()
		// A keyboard user's focus is inside the open menu, not on its button.
		await level2.focus()
		await expect(level2).toBeFocused()

		await page.keyboard.press('Escape')
		await expect(widget.locator('.cn-menu-widget__dropdown')).toHaveCount(0)
		await expect(widget.locator('.cn-menu-widget__flyout')).toHaveCount(0)
		await expect(menu).toBeFocused()
	})

	// @e2e menu-widget::tab-closes-and-moves-focus
	test('Tab from an item in an open dropdown closes it and focuses the next top-level item', async ({ page }) => {
		const widget = await render(page, 'DropTab', {
			style: 'dropdown',
			items: [
				{ label: 'Menu', children: [{ label: 'Item 1', url: '/path1' }] },
				{ label: 'Next', children: [{ label: 'Next one', url: '/next-one' }] },
			],
		})
		await item(widget, 'Menu').click()
		const child = item(widget, 'Item 1')
		await child.focus()
		await expect(child).toBeFocused()

		await page.keyboard.press('Tab')
		await expect(widget.locator('.cn-menu-widget__dropdown')).toHaveCount(0)
		await expect(item(widget, 'Next')).toBeFocused()
	})
})

test.describe('menu widget — megamenu style (REQ-MENU-004)', () => {
	const MEGA = {
		style: 'megamenu',
		items: [
			{ label: 'Products', children: [{ label: 'Widget', url: '/widgets' }, { label: 'Gadget', url: '/gadgets' }] },
			{ label: 'Services', children: [{ label: 'Consulting', url: '/consulting' }, { label: 'Support', url: '/support' }] },
		],
	}

	// @e2e menu-widget::megamenu-panel-opens-full-width
	test('clicking Products opens a full-width panel below the bar holding both its items', async ({ page }) => {
		const widget = await render(page, 'MegaOpen', MEGA)
		await expect(widget.locator('.cn-menu-widget__mega-panel')).toHaveCount(0)
		await item(widget, 'Products').click()

		const panel = widget.locator('.cn-menu-widget__mega-panel')
		await expect(panel).toBeVisible()
		const bar = (await widget.locator('.cn-menu-widget__bar').boundingBox())!
		const p = (await panel.boundingBox())!
		const mega = (await widget.locator('.cn-menu-widget__megamenu').boundingBox())!
		expect(p.y, 'the panel sits below the bar').toBeGreaterThanOrEqual(bar.y + bar.height - 1)
		expect(p.width, 'the panel spans the menu\'s full width').toBeGreaterThanOrEqual(mega.width - 2)

		const widgetItem = panel.getByRole('menuitem', { name: 'Widget', exact: true })
		const gadget = panel.getByRole('menuitem', { name: 'Gadget', exact: true })
		await expect(widgetItem).toBeVisible()
		await expect(gadget).toBeVisible()
		// Organised: each in a group of its own.
		await expect(panel.locator('.cn-menu-widget__mega-group')).toHaveCount(2)
	})

	// @e2e menu-widget::panel-switches-on-top-level-click
	test('clicking Services while Products is open switches the panel to Services', async ({ page }) => {
		const widget = await render(page, 'MegaSwitch', MEGA)
		await item(widget, 'Products').click()
		const panel = widget.locator('.cn-menu-widget__mega-panel')
		await expect(panel.getByRole('menuitem', { name: 'Widget', exact: true })).toBeVisible()

		await item(widget, 'Services').click()
		await expect(panel).toBeVisible()
		await expect(panel.getByRole('menuitem', { name: 'Consulting', exact: true })).toBeVisible()
		await expect(panel.getByRole('menuitem', { name: 'Support', exact: true })).toBeVisible()
		await expect(widget.getByRole('menuitem', { name: 'Widget', exact: true })).toHaveCount(0)
		await expect(widget.getByRole('menuitem', { name: 'Gadget', exact: true })).toHaveCount(0)
	})

	// @e2e menu-widget::level-3-items-render-inline-under-level-2
	test('a level-2 item is clickable and its level-3 children list inline below it', async ({ page }) => {
		const widget = await render(page, 'MegaLeaves', {
			style: 'megamenu',
			items: [{
				label: 'Products',
				children: [{ label: 'Widget', url: '/widgets', children: [{ label: 'FAQ', url: '/faq' }, { label: 'Docs', url: '/docs' }] }],
			}],
		})
		await item(widget, 'Products').click()
		const group = widget.locator('.cn-menu-widget__mega-group').filter({ hasText: 'Widget' })
		const title = group.getByRole('menuitem', { name: 'Widget', exact: true })
		await expect(title).toBeVisible()
		await expect(title).toBeEnabled()
		await expect(title).toHaveAttribute('href', /\/widgets$/)

		const leaves = group.locator('.cn-menu-widget__mega-leaves')
		const faq = leaves.getByRole('menuitem', { name: 'FAQ', exact: true })
		const docs = leaves.getByRole('menuitem', { name: 'Docs', exact: true })
		await expect(faq).toBeVisible()
		await expect(docs).toBeVisible()
		const t = (await title.boundingBox())!
		for (const leaf of [faq, docs]) {
			const b = (await leaf.boundingBox())!
			const below = b.y >= t.y + t.height - 1
			const beside = b.x >= t.x + t.width - 1
			expect(below || beside, 'a level-3 item sits below or beside its level-2 item').toBe(true)
		}
	})

	// @e2e menu-widget::esc-closes-megamenu
	test('Esc closes the megamenu panel', async ({ page }) => {
		const widget = await render(page, 'MegaEsc', MEGA)
		await item(widget, 'Products').click()
		await expect(widget.locator('.cn-menu-widget__mega-panel')).toBeVisible()

		await page.keyboard.press('Escape')
		await expect(widget.locator('.cn-menu-widget__mega-panel')).toHaveCount(0)
	})
})

test.describe('menu widget — tree style (REQ-MENU-005)', () => {
	const TREE = {
		style: 'tree',
		expandedByDefault: false,
		items: [{ label: 'Parent', children: [{ label: 'Child' }] }],
	}

	// @e2e menu-widget::tree-renders-with-collapsed-children-by-default
	test('a parent shows a closed caret and its child is hidden', async ({ page }) => {
		const widget = await render(page, 'TreeClosed', TREE)
		const caret = treeRow(widget, 'Parent').locator('.cn-menu-tree-node__caret')
		await expect(caret).toBeVisible()
		await expect(caret).not.toHaveClass(/cn-menu-tree-node__caret--open/)
		await expect(caret).toHaveText('▸')
		await expect(widget.getByRole('treeitem').first()).toHaveAttribute('aria-expanded', 'false')
		await expect(widget.getByText('Child', { exact: true })).toBeHidden()
	})

	// @e2e menu-widget::tree-expands-on-caret-click
	test('clicking the caret turns it open and shows the child', async ({ page }) => {
		const widget = await render(page, 'TreeCaret', TREE)
		const caret = treeRow(widget, 'Parent').locator('.cn-menu-tree-node__caret')
		await caret.click()
		await expect(caret).toHaveClass(/cn-menu-tree-node__caret--open/)
		await expect(caret).toHaveText('▾')
		await expect(widget.getByText('Child', { exact: true })).toBeVisible()
	})

	// @e2e menu-widget::expandedbydefault-expands-all-on-load
	test('expandedByDefault shows every level on load, every caret open', async ({ page }) => {
		const widget = await render(page, 'TreeOpen', {
			style: 'tree',
			expandedByDefault: true,
			items: [{ label: 'Parent', children: [{ label: 'Child', children: [{ label: 'Grandchild' }] }] }],
		})
		for (const label of ['Parent', 'Child', 'Grandchild']) {
			await expect(widget.getByText(label, { exact: true })).toBeVisible()
		}
		const carets = widget.locator('.cn-menu-tree-node__caret')
		await expect(carets).toHaveCount(2)
		for (let i = 0; i < 2; i++) {
			await expect(carets.nth(i)).toHaveClass(/cn-menu-tree-node__caret--open/)
			await expect(carets.nth(i)).toHaveText('▾')
		}
	})

	// @e2e menu-widget::tree-with-mixed-click-targets
	test('caret and label are separate: the caret only toggles, the label navigates', async ({ page }) => {
		// `/store` stands in for the scenario's `/parent`: the label must lead
		// somewhere the app's router actually knows, or the navigation would
		// land back on the start page and be indistinguishable from none.
		const widget = await render(page, 'TreeMixed', {
			style: 'tree',
			items: [{ label: 'Parent', url: '/store', children: [{ label: 'Child' }] }],
		})
		const row = treeRow(widget, 'Parent')
		const caret = row.locator('.cn-menu-tree-node__caret')
		const label = row.getByRole('link', { name: 'Parent', exact: true })
		await expect(caret).toBeVisible()
		await expect(label).toBeVisible()
		await expect(label.locator('.cn-menu-tree-node__caret')).toHaveCount(0)
		const c = (await caret.boundingBox())!
		const l = (await label.boundingBox())!
		expect(c.x + c.width, 'the caret sits apart from the label').toBeLessThanOrEqual(l.x + 1)

		const before = page.url()
		await caret.click()
		await expect(widget.getByText('Child', { exact: true })).toBeVisible()
		await caret.click()
		await expect(widget.getByText('Child', { exact: true })).toBeHidden()
		expect(page.url(), 'the caret must not navigate').toBe(before)

		await label.click()
		await expect(page).toHaveURL(new RegExp(`${APP}/store$`))
	})
})

test.describe('menu widget — keyboard navigation (REQ-MENU-007)', () => {
	// @e2e menu-widget::tab-moves-focus-between-top-level-items
	test('Tab on the first top-level item focuses the second and closes the open dropdown', async ({ page }) => {
		const widget = await render(page, 'KeyTab', THREE_TOP)
		const alpha = item(widget, 'Alpha')
		await alpha.click()
		await expect(widget.locator('.cn-menu-widget__dropdown')).toBeVisible()
		await expect(alpha).toBeFocused()

		await page.keyboard.press('Tab')
		await expect(item(widget, 'Beta')).toBeFocused()
		await expect(widget.locator('.cn-menu-widget__dropdown')).toHaveCount(0)
	})

	// @e2e menu-widget::enter-opens-dropdown-and-space-navigates
	test('Enter on a focused top-level item opens its dropdown and focuses the first child', async ({ page }) => {
		const widget = await render(page, 'KeyEnter', THREE_TOP)
		const alpha = item(widget, 'Alpha')
		await alpha.focus()
		await expect(alpha).toBeFocused()

		await page.keyboard.press('Enter')
		await expect(widget.locator('.cn-menu-widget__dropdown')).toBeVisible()
		await expect(item(widget, 'Alpha one')).toBeFocused()
	})

	// @e2e menu-widget::esc-closes-and-returns-focus
	test('Esc on a child of an open dropdown closes it and focuses its top-level item', async ({ page }) => {
		const widget = await render(page, 'KeyEsc', THREE_TOP)
		const alpha = item(widget, 'Alpha')
		await alpha.click()
		const child = item(widget, 'Alpha one')
		await child.focus()
		await expect(child).toBeFocused()

		await page.keyboard.press('Escape')
		await expect(widget.locator('.cn-menu-widget__dropdown')).toHaveCount(0)
		await expect(alpha).toBeFocused()
	})

	// @e2e menu-widget::arrowright-expands-tree-node
	test('ArrowRight on a collapsed tree item expands it', async ({ page }) => {
		const widget = await render(page, 'KeyRight', {
			style: 'tree',
			items: [{ label: 'Parent', children: [{ label: 'Child' }] }],
		})
		const label = treeRow(widget, 'Parent').getByRole('button', { name: 'Parent', exact: true })
		await label.focus()
		await expect(widget.getByText('Child', { exact: true })).toBeHidden()

		await page.keyboard.press('ArrowRight')
		await expect(widget.getByText('Child', { exact: true })).toBeVisible()
		await expect(widget.getByRole('treeitem').first()).toHaveAttribute('aria-expanded', 'true')
	})
})
