/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright e2e for REQ-DIV-001 of openspec/specs/divider-widget/spec.md:
 * the divider is offered in the Add widget picker (e2e-coverage-programme
 * task 4.13).
 *
 * WHY A FILE OF ITS OWN
 * =====================
 * These scenarios were cited by tests/e2e/spec-coverage/spec-coverage.spec.ts,
 * whose test read the picker as a `<select>` with a "Widget type" label and
 * `<option>`s. The picker has been `WidgetPickerModal.vue` for a while: a list
 * of buttons in sections, the LaunchPad types under
 * `[data-testid="widget-picker-custom"]`, each `widget-picker-type-<type>`.
 * That test could only fail on the stale locator, never on the scenario, so
 * the citations move here.
 *
 * WHAT IS UNDER TEST
 * ==================
 * The divider is a client-side widget type: nc-vue's
 * `registerDashboardWidget('divider', { displayName: 'Divider', icon: 'Minus' })`
 * (node_modules/@conduction/nextcloud-vue/src/components/CnDividerWidget/index.js),
 * listed by `Views.vue` `customWidgetTypes` and rendered by the picker's
 * "Widgets from Launchpad" section. The spec's id `launchpad_divider` is the
 * name of a Nextcloud IManager widget that was never built (see the excluded
 * "Widget registration via IManager"); the type id the app uses is `divider`.
 *
 * Tests that are expected to go red against the code as shipped say so at the
 * test, with the file and line that disagrees with the scenario.
 */

import type { APIRequestContext, Locator, Page, Request } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { SeededUser } from './support/filterBus.ts'

import { expect, request, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { gotoLaunchPad, openSidebar } from './fixtures/widget-flow.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import {
	apiAs,
	provisionUserWithDashboard,
	removeUser,
} from './support/filterBus.ts'
import {
	readAllowUserDashboards,
	restoreAllowUserDashboards,
} from './support/widgets.ts'

const API = '/index.php/apps/launchpad/api'
const ADMIN = {
	user: process.env.NEXTCLOUD_USER || 'admin',
	pass: process.env.NEXTCLOUD_PASS || 'admin',
}
const STAMP = Date.now().toString(36)
const DASHBOARD_NAME = `Divider discovery ${STAMP}`

let admin: APIRequestContext
let dashboard: SeededDashboard | null = null
let allowUserDashboardsBefore: boolean | null = null

test.describe.configure({ timeout: 120_000 })

test.beforeAll(async () => {
	admin = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true', Accept: 'application/json' },
	})
	allowUserDashboardsBefore = await readAllowUserDashboards(admin)
	dashboard = await seedActiveDashboard(admin, DASHBOARD_NAME)
})

test.afterAll(async () => {
	if (!admin) {
		return
	}
	await removeSeededDashboard(admin, dashboard)
	await restoreAllowUserDashboards(admin, allowUserDashboardsBefore)
	await admin.dispose()
})

/**
 * Open the Add widget picker of a dashboard from its row in the switcher,
 * recording every request the page makes from the click on "Add widget" on.
 *
 * @param page The page.
 * @param dashboardName The dashboard's name, its row's aria-label.
 * @return The picker root and the recorded requests.
 */
async function openPicker(
	page: Page,
	dashboardName: string,
): Promise<{ picker: Locator; requests: Request[] }> {
	await gotoLaunchPad(page)
	await openSidebar(page)
	const row = page.locator(
		`[data-source="user"].dashboard-switcher-sidebar__item[aria-label="${dashboardName}"]`,
	)
	await expect(row, `the switcher lists "${dashboardName}"`).toBeVisible({
		timeout: 15_000,
	})
	await row.locator('.dashboard-row-actions button').first().click()

	const requests: Request[] = []
	page.on('request', (req) => requests.push(req))
	await page.getByRole('menuitem', { name: /^add widget$/i }).click()

	const picker = page.locator('.widget-picker')
	await expect(picker, 'the Add widget picker opens').toBeVisible({
		timeout: 10_000,
	})
	return { picker, requests }
}

test.describe('REQ-DIV-001 the divider in the widget picker', () => {
	// Expected RED on the icon as shipped: the picker renders a LaunchPad
	// type as its title and a Plus action only (src/modals/WidgetPickerModal.vue,
	// the `filteredCustomTypes` loop), and `Views.vue` `customWidgetTypes`
	// passes `{ type, title }` without the registry's `icon: 'Minus'`. Every
	// other assertion runs first, so a red names the icon alone.
	// @e2e divider-widget::widget-appears-in-discovery
	test('the Add widget picker lists the divider with a title and an icon, fetching nothing for it', async ({
		page,
	}) => {
		const { picker, requests } = await openPicker(page, DASHBOARD_NAME)

		const section = picker.locator('[data-testid="widget-picker-custom"]')
		await expect(section, 'the "Widgets from Launchpad" section').toBeVisible()
		const entry = section.locator('[data-testid="widget-picker-type-divider"]')
		await expect(entry, 'the divider is offered').toBeVisible()
		await expect(entry).toBeEnabled()
		await expect(
			entry.locator('.widget-picker__widget-title'),
			'the entry carries the divider title',
		).toHaveText(/^\s*(Divider|Scheidingslijn)\s*$/i)

		// "Fully client-side": opening the picker fetches nothing divider-specific.
		const dividerCalls = requests
			.map((req) => req.url())
			.filter((url) => /divider/i.test(url))
		expect(dividerCalls, 'no request names the divider').toEqual([])

		// The icon: an image or icon element other than the trailing Plus.
		const icons = entry.locator(
			'.widget-picker__widget-icon, img, svg:not(.widget-picker__widget-action svg)',
		)
		expect(
			await icons.count(),
			'the divider entry shows an icon (the registry declares `Minus`)',
		).toBeGreaterThan(0)
	})

	// @e2e divider-widget::widget-appears-alongside-standard-widgets
	test('the divider is listed beside the Nextcloud widgets, and a plain user can add it', async ({
		page,
		browser,
	}) => {
		// The Nextcloud dashboard widgets the instance offers this account.
		const res = await admin.get(`${API}/widgets`)
		expect(res.status(), await res.text()).toBe(200)
		const body = await res.json()
		const widgets: Array<{ id: string; title: string }> = (
			Array.isArray(body) ? body : body.data ?? body.widgets ?? []
		).filter((w: any) => w && typeof w.title === 'string' && w.title !== '')
		expect(
			widgets.length,
			'the instance offers at least one Nextcloud dashboard widget',
		).toBeGreaterThan(0)
		// The scenario names weather_status and notes; take them when installed.
		const named = widgets.filter((w) => ['weather_status', 'notes'].includes(w.id))
		const standard = named.length > 0 ? named : widgets.slice(0, 1)

		const { picker } = await openPicker(page, DASHBOARD_NAME)
		const titles = picker.locator('.widget-picker__widget-title')
		for (const widget of standard) {
			await expect(
				titles.filter({ hasText: widget.title }).first(),
				`the picker lists the Nextcloud widget "${widget.title}" (${widget.id})`,
			).toBeVisible()
		}
		await expect(
			picker.locator('[data-testid="widget-picker-type-divider"]'),
			'and the divider in the same picker',
		).toBeVisible()

		// "Available to all logged-in users": a non-admin account sees it too,
		// unmarked, and adding it is not refused.
		let account: SeededUser | null = null
		try {
			account = await provisionUserWithDashboard('e2e-divider')
			const api = await apiAs(account)
			let name = ''
			try {
				const res = await api.get(`${API}/dashboard/${account.dashboardId}`)
				expect(res.status(), await res.text()).toBe(200)
				const own = await res.json()
				name = String(own.dashboard?.name ?? own.data?.dashboard?.name ?? '')
			} finally {
				await api.dispose()
			}
			expect(name, 'the account owns a named dashboard').not.toBe('')

			const session = await loginAs(browser, account.username, account.password)
			try {

				const user = await openPicker(session.page, name)
				const entry = user.picker.locator(
					'[data-testid="widget-picker-type-divider"]',
				)
				await expect(entry, 'a plain user is offered the divider').toBeVisible()
				await expect(entry).toBeEnabled()
				await expect(
					entry.locator('.widget-picker__widget-badge'),
					'the entry is not marked as restricted or placed',
				).toHaveCount(0)

				await entry.click()
				const dialog = session.page
					.getByRole('dialog', { name: /add widget/i })
					.first()
				await expect(dialog.locator('.cn-divider-widget-form')).toBeVisible({
					timeout: 10_000,
				})
				const post = session.page.waitForResponse(
					(r) =>
						r.request().method() === 'POST'
						&& r.url().includes(`/api/dashboard/${account!.dashboardId}/widgets`),
					{ timeout: 10_000 },
				)
				await dialog.getByRole('button', { name: /^add$/i }).click()
				const created = await post
				expect(
					created.status(),
					`a plain user adds a divider: ${await created.text()}`,
				).toBeLessThan(300)
				expect(created.request().postDataJSON()?.widgetId).toBe('divider')
			} finally {
				await session.context.close()
			}
		} finally {
			await removeUser(account)
		}
	})
})
