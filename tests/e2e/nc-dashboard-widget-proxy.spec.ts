/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the Nextcloud-widget picker inside the
 * unified Add Custom Widget modal (openspec/specs/nc-dashboard-widget-proxy),
 * written for e2e-coverage-programme task 4.24.
 *
 * WHERE THE PICKER'S CARDS COME FROM
 * ==================================
 * `CnNcDashboardWidgetForm` injects a `widgets` catalog. LaunchPad provides
 * it from the widget store (`src/views/Views.vue`, `provide('widgets', ...)`),
 * which the store fills from `GET /apps/launchpad/api/widgets`
 * (`src/stores/widgets.js` `loadAvailableWidgets`). That response IS the
 * catalog the scenarios call "the workspace's initial state".
 *
 * WHY THE CATALOG IS STUBBED RATHER THAN READ FROM THE INSTANCE
 * =============================================================
 * A stock Nextcloud does ship dashboard widgets, but the scenarios need an
 * exact count (8, 4, 0), and the CI fixture filters the list:
 * `ensureDefaultWidgetRestriction()` (fixtures/role-feature-permissions.ts),
 * which other specs in the same run call, limits the default group to
 * `activity` and `recommendations`. So the real list is at most two and
 * never zero. Each test therefore answers that one GET with the catalog its
 * GIVEN names, and drives everything else (opening the modal, picking the
 * type, clicking and keyboarding the cards, saving) through the real UI.
 *
 * @spec openspec/specs/nc-dashboard-widget-proxy/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { gotoLaunchPad, openAddWidgetModal } from './fixtures/widget-flow.ts'
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
const CARD = '.cn-nc-widget-grid-picker__card'

type CatalogEntry = {
	id: string
	title: string
	iconUrl: string
	iconClass: string
	order: number
}

/**
 * A distinct, loadable icon per widget: a one-colour SVG data URI.
 *
 * @param n the widget's ordinal, used to vary the colour.
 * @return a data URI the card's <img> can load without the network.
 */
function iconFor(n: number): string {
	const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" fill="hsl(${n * 40},70%,50%)"/></svg>`
	return `data:image/svg+xml,${encodeURIComponent(svg)}`
}

/**
 * A catalog of `count` Nextcloud dashboard widgets, each with id, title and
 * iconUrl, in the shape `GET /api/widgets` returns (a bare array).
 *
 * @param count how many widgets.
 * @return the catalog.
 */
function catalog(count: number): CatalogEntry[] {
	return Array.from({ length: count }, (_, i) => ({
		id: `e2e-ncw-${i + 1}`,
		title: `E2E Widget ${i + 1}`,
		iconUrl: iconFor(i + 1),
		iconClass: '',
		order: i,
	}))
}

/**
 * Answer the workspace's widget-catalog request with `widgets`. Only the
 * exact catalog path is matched, so `/api/widgets/items` and every
 * placement route still reach the server.
 *
 * @param page the page, before it navigates.
 * @param widgets the catalog to serve.
 */
async function serveCatalog(page: Page, widgets: CatalogEntry[]): Promise<void> {
	await page.route(
		(url) => url.pathname.endsWith('/apps/launchpad/api/widgets'),
		async (route) => {
			if (route.request().method() !== 'GET') {
				await route.fallback()
				return
			}
			await route.fulfill({
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify(widgets),
			})
		},
	)
}

/**
 * Open the Add Custom Widget modal and pick the "Nextcloud widget" type.
 *
 * @param page the page, on the LaunchPad workspace.
 * @return the modal dialog.
 */
async function openNcWidgetPicker(page: Page): Promise<Locator> {
	await openAddWidgetModal(page, 'nc-widget')
	const dialog = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(dialog.locator('.cn-nc-widget-grid-picker')).toBeVisible({
		timeout: 8_000,
	})
	return dialog
}

/**
 * Assert a card's selected state: aria-checked, the selected modifier class
 * (the highlighted border) and the check-mark overlay.
 *
 * @param card the card.
 * @param selected whether it should read as selected.
 */
async function expectSelected(card: Locator, selected: boolean): Promise<void> {
	await expect(card).toHaveAttribute('aria-checked', selected ? 'true' : 'false')
	if (selected) {
		await expect(card).toHaveClass(/cn-nc-widget-grid-picker__card--selected/)
		await expect(card.locator('.cn-nc-widget-grid-picker__check')).toBeVisible()
	} else {
		await expect(card).not.toHaveClass(
			/cn-nc-widget-grid-picker__card--selected/,
		)
		await expect(card.locator('.cn-nc-widget-grid-picker__check')).toHaveCount(0)
	}
}

let api: APIRequestContext
let seeded: SeededDashboard | null = null

test.beforeAll(async () => {
	// The workspace shell only renders once a dashboard is active.
	api = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	seeded = await seedActiveDashboard(api, `E2E NC picker ${Date.now()}`)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, seeded)
	await api?.dispose()
})

test.describe('nc-dashboard-widget-proxy — the Nextcloud widget picker', () => {
	test.describe.configure({ timeout: 90_000 })

	// @e2e nc-dashboard-widget-proxy::grid-renders-one-card-per-discovered-nc-widget
	test('REQ-NCDP-PICKER: eight discovered widgets render as eight icon cards in a grid, no <select>', async ({
		page,
	}) => {
		const widgets = catalog(8)
		await serveCatalog(page, widgets)
		await gotoLaunchPad(page)
		const dialog = await openNcWidgetPicker(page)

		// A responsive CSS grid, exposed as a radiogroup.
		const grid = dialog.locator('.cn-nc-widget-grid-picker__grid')
		await expect(grid).toHaveAttribute('role', 'radiogroup')
		await expect(grid).toHaveAttribute('aria-label', /.+/)
		await expect(grid).toHaveCSS('display', 'grid')
		await expect(grid).toHaveCSS('column-gap', '12px')

		// One card per discovered widget.
		const cards = grid.locator(CARD)
		await expect(cards).toHaveCount(8)

		// Each card shows that widget's icon and title.
		for (const [i, widget] of widgets.entries()) {
			const card = cards.nth(i)
			await expect(card).toHaveAttribute('role', 'radio')
			await expect(
				card.locator('.cn-nc-widget-grid-picker__title'),
			).toHaveText(widget.title)
			const icon = card.locator('img.cn-nc-widget-grid-picker__icon')
			await expect(icon).toHaveAttribute('src', widget.iconUrl)
			await expect(icon).toBeVisible()
		}

		// The picker is not a dropdown. (The modal's own type selector and the
		// form's display-mode control are <select>s; neither is the picker.)
		await expect(
			dialog.locator('.cn-nc-dashboard-widget-form__picker select'),
		).toHaveCount(0)
	})

	// @e2e nc-dashboard-widget-proxy::selecting-a-card-updates-v-model
	test('REQ-NCDP-PICKER: clicking the third card selects it and saves its widget id', async ({
		page,
	}) => {
		const widgets = catalog(8)
		await serveCatalog(page, widgets)
		await gotoLaunchPad(page)
		const dialog = await openNcWidgetPicker(page)

		const cards = dialog.locator(CARD)
		await expect(cards).toHaveCount(8)
		// GIVEN none selected.
		for (let i = 0; i < 8; i++) {
			await expectSelected(cards.nth(i), false)
		}

		await cards.nth(2).click()

		// The third card shows the selected state; every other card does not.
		for (let i = 0; i < 8; i++) {
			await expectSelected(cards.nth(i), i === 2)
		}

		// The form's `widgetId` is what the form emits; saving is where it
		// leaves the browser, so read it off the create request.
		const addBtn = dialog.getByRole('button', { name: /^add$/i })
		await expect(addBtn).toBeEnabled({ timeout: 5_000 })
		const created = page.waitForResponse(
			(r) =>
				r.request().method() === 'POST'
				&& /\/api\/dashboard\/\d+\/widgets$/.test(new URL(r.url()).pathname),
		)
		await addBtn.click()
		const res = await created

		let placementId = 0
		try {
			const body = await res.json().catch(() => ({}))
			placementId = Number(body.id ?? body.data?.id ?? 0)

			const sent = res.request().postDataJSON()
			expect(sent.widgetId).toBe('nc-widget')
			expect(sent.content?.widgetId).toBe(widgets[2].id)
		} finally {
			// The seeded dashboard's deletion would take it too; remove it
			// now so a failed teardown leaves nothing behind.
			if (placementId !== 0) {
				await api.delete(`${API}/widgets/${placementId}`)
			}
		}
	})

	// @e2e nc-dashboard-widget-proxy::empty-state-when-no-widgets-discovered
	test('REQ-NCDP-PICKER: with no discovered widgets the picker says so and renders no cards', async ({
		page,
	}) => {
		await serveCatalog(page, [])
		await gotoLaunchPad(page)
		const dialog = await openNcWidgetPicker(page)

		await expect(dialog.locator('.cn-nc-widget-grid-picker__empty')).toHaveText(
			'No Nextcloud widgets are installed',
		)
		await expect(dialog.locator(CARD)).toHaveCount(0)
	})

	/*
	 * Expected RED against nextcloud-vue 2.182.0: `CnNcWidgetGridPicker`
	 * computes each card's tabindex from the SELECTED index only
	 * (`cardTabIndex()`); the `focusedIndex` that ArrowRight updates is never
	 * read. With nothing selected the first card keeps tabindex 0 after the
	 * arrow, so the roving-tabindex assertion below fails. That is a product
	 * defect against REQ-NCDP-PICKER, not a test to weaken.
	 */
	// @e2e nc-dashboard-widget-proxy::keyboard-navigation-works
	test('REQ-NCDP-PICKER: Tab enters the grid, ArrowRight moves focus and the roving tabindex, Enter selects', async ({
		page,
	}) => {
		const widgets = catalog(4)
		await serveCatalog(page, widgets)
		await gotoLaunchPad(page)
		const dialog = await openNcWidgetPicker(page)

		const cards = dialog.locator(CARD)
		await expect(cards).toHaveCount(4)

		// Tab from the control just before the grid (the search box) lands on
		// the first card.
		await dialog.locator('.cn-nc-dashboard-widget-form__search').focus()
		await page.keyboard.press('Tab')
		await expect(cards.nth(0)).toBeFocused()

		await page.keyboard.press('ArrowRight')

		// Focus moved to the second card ...
		await expect(cards.nth(1)).toBeFocused()
		// ... which is now the only card in the tab sequence.
		for (let i = 0; i < 4; i++) {
			await expect(cards.nth(i)).toHaveAttribute(
				'tabindex',
				i === 1 ? '0' : '-1',
			)
		}

		// Enter selects the focused (second) card.
		await page.keyboard.press('Enter')
		for (let i = 0; i < 4; i++) {
			await expectSelected(cards.nth(i), i === 1)
		}
	})
})
