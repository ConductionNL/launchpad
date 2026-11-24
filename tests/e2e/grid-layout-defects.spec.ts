/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Two grid-layout scenarios (openspec/specs/grid-layout/spec.md) that were
 * cited only by `test.fixme`, in grid-layout-rendering.spec.ts and
 * grid-layout-drag-resize.spec.ts. Gate-19 counted them through those files'
 * citation lists, but no test of theirs ever executed. They now run.
 *
 * They sit in their own file, without serial mode, because both files they
 * came from run serially: a red there would skip every test after it. Each
 * test makes its own throwaway account and dashboard (the helpers are the
 * drag-resize file's).
 *
 * EXPECTED RED until the product or the scenario changes:
 *   - 12-col-60px-8px-margin-renders-predictable-height: GridStack draws a
 *     cell `h * cellHeight` tall (240 px for four rows) and insets its content
 *     by the 8 px margin, so neither the item nor its content is the
 *     scenario's 4 x 60 + 3 x 8 = 264 px. Views.vue passes cellHeight 60 and
 *     margin 8. This may be the scenario's arithmetic rather than a defect.
 *   - save-failure-with-retry: src/stores/dashboard.js updatePlacements()
 *     only logs a failed save; there is no error notification and no retry.
 *     The scenario itself notes that this is not implemented yet. It stays a
 *     running test, not an exclusion, because it is a MUST that e2e can see.
 *
 * Gate-19 @e2e traceability:
 *   @e2e grid-layout::12-col-60px-8px-margin-renders-predictable-height
 *   @e2e grid-layout::save-failure-with-retry
 *
 * @spec openspec/specs/grid-layout/spec.md
 */

import type { Browser, Locator, Page } from '@playwright/test'
import type { OwnDashboard, Rect } from './support/ownDashboard.ts'

import { expect, test } from '@playwright/test'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import {
	openOwnDashboard,
	seedTextWidget,
	setUpOwnDashboard,
	tearDownOwnDashboard,
} from './support/ownDashboard.ts'

const CELL_HEIGHT = 60

let own: OwnDashboard | undefined

test.setTimeout(120_000)

test.afterEach(async () => {
	await tearDownOwnDashboard(own)
	own = undefined
})

/**
 * A fresh account with text widgets at the given rectangles.
 *
 * @param rects The rectangles, in order.
 * @return The placement ids, in the same order.
 */
async function seed(rects: Rect[]): Promise<number[]> {
	own = await setUpOwnDashboard('e2e-grid-red', `E2E grid defects ${Date.now()}`)
	const ids: number[] = []
	for (const [index, rect] of rects.entries()) {
		ids.push(
			await seedTextWidget(own.owner.api, own.id, rect, `Widget ${index + 1}`),
		)
	}
	return ids
}

/**
 * The grid item of a placement.
 *
 * @param page The page.
 * @param id The placement id.
 * @return The item.
 */
function item(page: Page, id: number): Locator {
	return page.locator(`.grid-stack > .grid-stack-item[gs-id="${id}"]`)
}

/**
 * The width of one grid column in pixels.
 *
 * @param page The page.
 * @return The width.
 */
async function columnWidth(page: Page): Promise<number> {
	const box = await page.locator('.grid-stack').first().boundingBox()
	expect(box).not.toBeNull()
	return box!.width / 12
}

/**
 * Drag a widget by its body over a number of columns and rows.
 *
 * @param page The page.
 * @param id The placement id.
 * @param columns Columns to the right (negative: left).
 * @param rows Rows down (negative: up).
 */
async function dragBy(
	page: Page,
	id: number,
	columns: number,
	rows: number,
): Promise<void> {
	const colW = await columnWidth(page)
	const box = await item(page, id)
		.locator('.grid-stack-item-content')
		.boundingBox()
	expect(box).not.toBeNull()
	// Low in the body: on a narrow widget the header's Actions button sits
	// at the middle of the card, and GridStack starts no drag on a button.
	const startX = box!.x + box!.width / 2
	const startY = box!.y + box!.height * 0.8
	await page.mouse.move(startX, startY)
	await page.mouse.down()
	await page.mouse.move(startX + 6, startY + 6, { steps: 3 })
	await page.mouse.move(startX + columns * colW, startY + rows * CELL_HEIGHT, {
		steps: 20,
	})
	await page.mouse.up()
}

/**
 * Open the dashboard in edit mode.
 *
 * @param browser The browser.
 * @param count How many widgets it holds.
 * @return The context and page.
 */
async function openEditing(browser: Browser, count: number) {
	const session = await openOwnDashboard(browser, own!, count)
	await enterEditMode(session.page)
	return session
}

// @e2e grid-layout::12-col-60px-8px-margin-renders-predictable-height
test('a four-row widget on the 12-column grid is 4 x 60 + 3 x 8 = 264 px tall', async ({
	browser,
}) => {
	// Product disagrees: GridStack draws a cell `h * cellHeight` tall (240 px)
	// and insets its content by the 8 px margin on each side (224 px); neither
	// is 264 px. Views.vue:262-271 passes cellHeight 60 and margin 8.
	const [tall] = await seed([{ x: 8, y: 0, w: 4, h: 4 }])
	const { context, page } = await openOwnDashboard(browser, own!, 1)
	try {
		const box = await item(page, tall).boundingBox()
		expect(box!.height).toBeCloseTo(264, 0)
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::save-failure-with-retry
test('a failed layout save is reported and retried up to three times', async ({
	browser,
}) => {
	// Product disagrees: src/stores/dashboard.js:1039-1041 (`updatePlacements`)
	// only logs a failed save — no notification and no retry. The scenario
	// itself carries "NOTE: Save failure/retry is NOT currently implemented".
	const [a] = await seed([{ x: 0, y: 0, w: 4, h: 2 }])
	const { context, page } = await openEditing(browser, 1)
	try {
		let attempts = 0
		await page.route(
			new RegExp(`/api/dashboard/${own!.id}(\\?|$)`),
			async (route) => {
				if (route.request().method() !== 'PUT') {
					await route.fallback()
					return
				}
				attempts++
				await route.fulfill({
					status: 500,
					contentType: 'application/json',
					body: '{"error":"boom"}',
				})
			},
		)
		await dragBy(page, a, 4, 0)
		await expect(page.locator('.toastify.toast-error')).toBeVisible({
			timeout: 15_000,
		})
		await expect.poll(() => attempts, { timeout: 30_000 }).toBe(4)
	} finally {
		await context.close()
	}
})
