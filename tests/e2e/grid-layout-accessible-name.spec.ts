/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * What a screen reader hears on a focused grid item (grid-layout REQ-GRID-008,
 * REQ-GRID-KBD-001).
 *
 * A fresh throwaway account owns one dashboard with one text widget whose
 * title is set to "Team Announcements". The dashboard is opened in edit mode,
 * where grid items are tab stops, the item is focused and its computed
 * accessible name is read.
 *
 * Views.vue passes CnDashboardGrid an `itemLabel` (src/utils/gridItemLabel.js)
 * that names the item "Widget: {title}" and, in edit mode, adds the position
 * and size, because the library uses a custom label verbatim.
 *
 * They sit in their own file, without serial mode, so a red here cannot skip
 * the keyboard tests in grid-layout-keyboard.spec.ts.
 *
 * The two scenarios fix the same name from two ends: KBD-001 the start
 * ("Widget: Team Announcements"), REQ-GRID-008 the title plus position and
 * size. In edit mode the coordinates follow the title, so a name such as
 * "Widget: Team Announcements, column 1 of 12, row 1, 4 columns wide, 2 rows
 * tall" satisfies both; each test asserts its own end.
 *
 * Gate-19 @e2e traceability:
 *   @e2e grid-layout::screen-reader-announces-widget-positions
 *   @e2e grid-layout::screen-reader-announces-the-widget
 *
 * @spec openspec/specs/grid-layout/spec.md
 */

import type { Browser, Locator, Page } from '@playwright/test'
import type { OwnDashboard } from './support/ownDashboard.ts'

import { expect, test } from '@playwright/test'
import { enterEditMode } from './support/dashboardSwitcher.ts'
import {
	openOwnDashboard,
	seedTextWidget,
	setUpOwnDashboard,
	tearDownOwnDashboard,
} from './support/ownDashboard.ts'
import { API } from './support/principals.ts'

const TITLE = 'Team Announcements'

let own: OwnDashboard | undefined

test.setTimeout(120_000)

test.afterEach(async () => {
	await tearDownOwnDashboard(own)
	own = undefined
})

/**
 * A fresh account with one text widget at x=0, y=0, 4x2, titled TITLE.
 *
 * @return The placement id.
 */
async function seedTitledWidget(): Promise<number> {
	own = await setUpOwnDashboard('e2e-grid-name', `E2E grid name ${Date.now()}`)
	const id = await seedTextWidget(
		own.owner.api,
		own.id,
		{ x: 0, y: 0, w: 4, h: 2 },
		'Widget 1',
	)
	const titled = await own.owner.api.put(`${API}/widgets/${id}`, {
		data: { customTitle: TITLE },
	})
	expect(titled.status(), await titled.text()).toBe(200)
	return id
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
 * Open the dashboard in edit mode and focus the item.
 *
 * @param browser The browser.
 * @param id The placement id.
 * @return The context and page.
 */
async function openFocused(browser: Browser, id: number) {
	const session = await openOwnDashboard(browser, own!, 1)
	await enterEditMode(session.page)
	await expect(item(session.page, id)).toHaveAttribute('tabindex', '0')
	await item(session.page, id).focus()
	await expect(item(session.page, id)).toBeFocused()
	return session
}

// @e2e grid-layout::screen-reader-announces-widget-positions
test('a focused widget is announced with its title, position and size', async ({
	browser,
}) => {
	const only = await seedTitledWidget()
	const { context, page } = await openFocused(browser, only)
	try {
		await expect(item(page, only)).toHaveAttribute('role', 'group')
		// The title, then the grid position and the size, as the last part of
		// the name.
		await expect(item(page, only)).toHaveAccessibleName(
			new RegExp(`${TITLE}, column 1 of 12, row 1, 4 columns wide, 2 rows tall$`),
		)
	} finally {
		await context.close()
	}
})

// @e2e grid-layout::screen-reader-announces-the-widget
test('a widget titled "Team Announcements" is a group named "Widget: Team Announcements"', async ({
	browser,
}) => {
	const only = await seedTitledWidget()
	const { context, page } = await openFocused(browser, only)
	try {
		await expect(item(page, only)).toHaveAttribute('role', 'group')
		// The name starts with the scenario's label; anything after it is the
		// edit-mode coordinates.
		await expect(item(page, only)).toHaveAccessibleName(
			new RegExp(`^Widget: ${TITLE}(,|$)`),
		)
		await expect(
			page.getByRole('group', { name: new RegExp(`^Widget: ${TITLE}(,|$)`) }),
		).toHaveCount(1)
	} finally {
		await context.close()
	}
})
