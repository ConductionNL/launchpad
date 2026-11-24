/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The widget right-click popover (openspec/specs/widgets REQ-WDG-015..017):
 * when it opens, where, what each of its buttons does, and when it closes.
 * Written for e2e-coverage-programme, lane e3.
 *
 * Every test seeds its own dashboard with two `label` placements through the
 * API, makes it the admin's active dashboard and drives the popover in the
 * browser. The right-click is a dispatched `contextmenu` event at exact
 * viewport coordinates (see `rightClickAt` in support/widgetPlacements.ts);
 * its return value says whether the browser would still show its own menu.
 *
 * Gate-19 @e2e traceability:
 *   @e2e widgets::right-click-in-edit-mode-opens-popover
 *   @e2e widgets::right-click-in-view-mode-does-nothing
 *   @e2e widgets::edit-click-opens-the-addedit-modal
 *   @e2e widgets::remove-click-deletes-the-placement
 *   @e2e widgets::cancel-click-closes-without-action
 *   @e2e widgets::click-outside-closes
 *   @e2e widgets::right-click-another-widget-switches-popover
 *   @e2e widgets::popover-stays-within-viewport-on-right-edge
 *   @e2e widgets::popover-stays-within-viewport-on-bottom-edge
 *   @e2e widgets::z-index-sits-at-10000
 *
 * @spec openspec/specs/widgets/spec.md
 */

import type { Page, Request } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { Placement } from './support/widgetPlacements.ts'

import { expect, test } from '@playwright/test'
import {
	adminApi,
	contentCentre,
	gridItem,
	openInEditMode,
	openWorkspace,
	rightClickAt,
	Seeds,
} from './support/widgetPlacements.ts'

const RUN = Date.now()
const MENU = '[data-testid="widget-context-menu"]'

let seeds: Seeds

type Fixture = {
	dash: SeededDashboard
	w1: Placement
	w2: Placement
	text1: string
	text2: string
}

/**
 * Seed a dashboard holding two label widgets side by side and make it active.
 *
 * @param tag distinguishes the test.
 * @return the dashboard and both placements.
 */
async function twoLabels(tag: string): Promise<Fixture> {
	const dash = await seeds.dashboard(`E2E ctx ${tag} ${RUN}`)
	const text1 = `E2E ctx one ${tag} ${RUN}`
	const text2 = `E2E ctx two ${tag} ${RUN}`
	const w1 = await seeds.widget(dash, {
		widgetId: 'label',
		gridX: 0,
		gridY: 0,
		gridWidth: 4,
		gridHeight: 3,
		content: { text: text1 },
	})
	const w2 = await seeds.widget(dash, {
		widgetId: 'label',
		gridX: 4,
		gridY: 0,
		gridWidth: 4,
		gridHeight: 3,
		content: { text: text2 },
	})
	return { dash, w1, w2, text1, text2 }
}

/**
 * Record every request to the LaunchPad API from now on, leaving out the
 * edit-presence heartbeat (a 30 s timer unrelated to the popover).
 *
 * @param page the page.
 * @return the live list.
 */
function recordApiCalls(page: Page): Request[] {
	const seen: Request[] = []
	page.on('request', (req) => {
		const url = req.url()
		if (url.includes('/apps/launchpad/api/') && !url.includes('/presence')) {
			seen.push(req)
		}
	})
	return seen
}

/**
 * Make the viewport tall enough that a popover opened at a widget's centre
 * fits below the cursor. The start page puts the grid low enough that, at
 * 720 px, the centre of a three-row widget is within the popover's height
 * of the bottom edge, where the popover is shifted up and no longer opens at
 * the cursor.
 *
 * @param page the page.
 * @return nothing.
 */
async function roomBelowTheWidgets(page: Page): Promise<void> {
	await page.setViewportSize({ width: 1280, height: 1200 })
}

test.beforeAll(async () => {
	seeds = new Seeds(await adminApi())
})

test.afterAll(async () => {
	await seeds?.cleanup()
})

test.describe('widget context menu (REQ-WDG-015..017)', () => {
	test.describe.configure({ timeout: 120_000 })

	// @e2e widgets::right-click-in-edit-mode-opens-popover
	test('in edit mode a right-click opens the popover at the cursor and suppresses the native menu', async ({
		page,
	}) => {
		await roomBelowTheWidgets(page)
		const { w1 } = await twoLabels('open')
		await openInEditMode(page, w1.id)

		const at = await contentCentre(page, w1.id)
		const nativeMenuShown = await rightClickAt(page, w1.id, at.x, at.y)
		expect(
			nativeMenuShown,
			'the contextmenu event must be cancelled (preventDefault)',
		).toBe(false)

		const menu = page.locator(MENU)
		await expect(menu).toHaveCount(1)
		await expect(menu).toBeVisible()
		const box = await menu.boundingBox()
		expect(Math.round(box!.x)).toBe(at.x)
		expect(Math.round(box!.y)).toBe(at.y)
		await expect(
			menu.getByRole('menuitem', { name: 'Edit', exact: true }),
		).toBeVisible()
		await expect(
			menu.getByRole('menuitem', { name: 'Remove', exact: true }),
		).toBeVisible()
		await expect(
			menu.getByRole('menuitem', { name: 'Cancel', exact: true }),
		).toBeVisible()
	})

	// @e2e widgets::right-click-in-view-mode-does-nothing
	test('in view mode a right-click opens no popover and leaves the native menu alone', async ({
		page,
	}) => {
		const { w1 } = await twoLabels('view')
		await openWorkspace(page, w1.id)
		await expect(page.locator('.launchpad-edit-mode')).toHaveCount(0)

		const at = await contentCentre(page, w1.id)
		const nativeMenuShown = await rightClickAt(page, w1.id, at.x, at.y)
		expect(
			nativeMenuShown,
			'nothing may cancel the contextmenu event in view mode',
		).toBe(true)
		// Give a (wrongly) opening popover the time to render before asserting absence.
		await page.waitForTimeout(500)
		await expect(page.locator(MENU)).toHaveCount(0)
	})

	// @e2e widgets::edit-click-opens-the-addedit-modal
	test('Edit closes the popover and opens the add/edit modal for that widget', async ({
		page,
	}) => {
		const { w1, text1 } = await twoLabels('edit')
		await openInEditMode(page, w1.id)
		const at = await contentCentre(page, w1.id)
		await rightClickAt(page, w1.id, at.x, at.y)
		await page.locator('[data-testid="ctx-edit"]').click()

		await expect(page.locator(MENU)).toHaveCount(0)
		const dialog = page.getByRole('dialog', { name: 'Edit Widget' })
		await expect(dialog).toBeVisible({ timeout: 10_000 })
		// editingWidget = W: the label form is pre-filled with W's own content,
		// and the type picker of create mode is not offered.
		await expect(dialog.getByLabel(/label text/i).first()).toHaveValue(text1)
		await expect(
			dialog.locator('[data-testid="widget-type-select"]'),
		).toHaveCount(0)
		await expect(dialog.locator('[data-testid="add-widget-save"]')).toHaveText(
			/save/i,
		)
	})

	// @e2e widgets::remove-click-deletes-the-placement
	test('Remove closes the popover, deletes the placement and takes it off the grid', async ({
		page,
	}) => {
		const { dash, w1, w2 } = await twoLabels('remove')
		await openInEditMode(page, w1.id)
		const at = await contentCentre(page, w1.id)
		await rightClickAt(page, w1.id, at.x, at.y)

		// The REQ-WDG-005 delete path. appinfo/routes.php names it
		// `widgetApi#removePlacement` at DELETE /api/widgets/{placementId};
		// the scenario's `/api/placements/{id}` spelling does not exist.
		const deleted = page.waitForResponse(
			(res) =>
				res.request().method() === 'DELETE'
				&& new URL(res.url()).pathname.endsWith(
					`/apps/launchpad/api/widgets/${w1.id}`,
				),
		)
		await page.locator('[data-testid="ctx-remove"]').click()
		await expect(page.locator(MENU)).toHaveCount(0)
		expect((await deleted).status()).toBe(200)

		// Off the grid (GridStack's removeWidget takes its item node), the other one stays.
		await expect(gridItem(page, w1.id)).toHaveCount(0)
		await expect(page.locator(`.grid-stack-item[gs-id="${w1.id}"]`)).toHaveCount(
			0,
		)
		await expect(gridItem(page, w2.id)).toBeVisible()

		const ids = (await seeds.placements(dash)).map((p) => p.id)
		expect(ids).not.toContain(w1.id)
		expect(ids).toContain(w2.id)
	})

	// @e2e widgets::cancel-click-closes-without-action
	test('Cancel closes the popover with no API call and no change', async ({
		page,
	}) => {
		const { dash, w1, w2, text1 } = await twoLabels('cancel')
		const before = await seeds.placements(dash)
		await openInEditMode(page, w1.id)
		const at = await contentCentre(page, w1.id)
		await rightClickAt(page, w1.id, at.x, at.y)
		await expect(page.locator(MENU)).toBeVisible()

		const calls = recordApiCalls(page)
		await page.locator('[data-testid="ctx-cancel"]').click()
		await expect(page.locator(MENU)).toHaveCount(0)
		await page.waitForTimeout(1_500)
		expect(calls.map((r) => `${r.method()} ${r.url()}`)).toEqual([])

		// Nothing open, both widgets still there, the store and the server unchanged.
		await expect(page.getByRole('dialog')).toHaveCount(0)
		await expect(gridItem(page, w1.id)).toContainText(text1)
		await expect(gridItem(page, w2.id)).toBeVisible()
		const after = await seeds.placements(dash)
		const shape = (list: Placement[]) =>
			list
				.map((p) => ({
					id: p.id,
					x: p.gridX,
					y: p.gridY,
					w: p.gridWidth,
					h: p.gridHeight,
					content: p.content ?? p.widgetContent ?? null,
				}))
				.sort((a, b) => a.id - b.id)
		expect(shape(after)).toEqual(shape(before))
	})

	// @e2e widgets::click-outside-closes
	test('a click outside the popover closes it', async ({ page }) => {
		const { w1, w2 } = await twoLabels('outside')
		await openInEditMode(page, w1.id)
		const at = await contentCentre(page, w1.id)
		await rightClickAt(page, w1.id, at.x, at.y)
		const menu = page.locator(MENU)
		await expect(menu).toBeVisible()

		// A click inside the popover's own box (its padding) keeps it open …
		const box = (await menu.boundingBox())!
		await page.mouse.click(box.x + box.width / 2, box.y + 2)
		await expect(menu).toBeVisible()

		// … a click anywhere else, here on the other widget, closes it.
		const other = await contentCentre(page, w2.id)
		await page.mouse.click(other.x, other.y)
		await expect(menu).toHaveCount(0)
	})

	// @e2e widgets::right-click-another-widget-switches-popover
	test('right-clicking a second widget moves the one popover to it', async ({
		page,
	}) => {
		await roomBelowTheWidgets(page)
		const { w1, w2, text2 } = await twoLabels('switch')
		await openInEditMode(page, w1.id)
		const a = await contentCentre(page, w1.id)
		const b = await contentCentre(page, w2.id)
		expect(b.x).not.toBe(a.x)

		await rightClickAt(page, w1.id, a.x, a.y)
		const menu = page.locator(MENU)
		await expect(menu).toHaveCount(1)
		expect(Math.round((await menu.boundingBox())!.x)).toBe(a.x)

		await rightClickAt(page, w2.id, b.x, b.y)
		await expect(menu).toHaveCount(1)
		const box = (await menu.boundingBox())!
		expect(Math.round(box.x)).toBe(b.x)
		expect(Math.round(box.y)).toBe(b.y)

		// The popover now belongs to W2: its Edit opens W2's content.
		await page.locator('[data-testid="ctx-edit"]').click()
		const dialog = page.getByRole('dialog', { name: 'Edit Widget' })
		await expect(dialog).toBeVisible({ timeout: 10_000 })
		await expect(dialog.getByLabel(/label text/i).first()).toHaveValue(text2)
	})

	// @e2e widgets::popover-stays-within-viewport-on-right-edge
	test('near the right edge the popover is shifted left to stay on screen', async ({
		page,
	}) => {
		const { w1 } = await twoLabels('right')
		await openInEditMode(page, w1.id)
		const viewport = page.viewportSize()!
		const x = viewport.width - 50
		await rightClickAt(page, w1.id, x, 200)

		const menu = page.locator(MENU)
		await expect(menu).toBeVisible()
		const box = (await menu.boundingBox())!
		expect(box.width).toBeGreaterThanOrEqual(150)
		expect(box.x + box.width).toBeLessThanOrEqual(viewport.width)
		expect(box.x).toBeLessThan(x)
		expect(Math.round(box.y)).toBe(200)
	})

	// @e2e widgets::popover-stays-within-viewport-on-bottom-edge
	//
	// EXPECTED RED until the product is fixed (it was test.fixme, so it
	// never ran although gate-19 counted it) — suspected product bug. useGridManager.js:386-387 clamps with a
	// fixed DEFAULT_MENU_HEIGHT of 245 px, measured (its own comment) when the
	// popover had FIVE items, ~232.5 px. WidgetContextMenu.vue now renders SIX
	// (Edit, Move, Visibility rules…, Read confirmation…, Remove, Cancel —
	// "Read confirmation…" landed in e19d5d86, a month after the constant), so
	// the popover is ~277 px tall and a click 20 px above the bottom edge is
	// clamped to `innerHeight - 245`, leaving its last ~30 px below the fold.
	test('near the bottom edge the popover is shifted up to stay on screen', async ({
		page,
	}) => {
		const { w1 } = await twoLabels('bottom')
		await openInEditMode(page, w1.id)
		const viewport = page.viewportSize()!
		const y = viewport.height - 20
		await rightClickAt(page, w1.id, 400, y)

		const menu = page.locator(MENU)
		await expect(menu).toBeVisible()
		const box = (await menu.boundingBox())!
		expect(box.y + box.height).toBeLessThanOrEqual(viewport.height)
		expect(box.y).toBeLessThan(y)
		expect(box.y).toBeGreaterThanOrEqual(0)
	})

	// @e2e widgets::z-index-sits-at-10000
	test('the popover sits at z-index 10000 with a 150px minimum width', async ({
		page,
	}) => {
		const { w1 } = await twoLabels('zindex')
		await openInEditMode(page, w1.id)
		const at = await contentCentre(page, w1.id)
		await rightClickAt(page, w1.id, at.x, at.y)
		const menu = page.locator(MENU)
		await expect(menu).toBeVisible()
		await expect(menu).toHaveCSS('z-index', '10000')
		await expect(menu).toHaveCSS('min-width', '150px')
	})
})
