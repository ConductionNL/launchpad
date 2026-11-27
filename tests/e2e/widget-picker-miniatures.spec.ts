/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright e2e for add-widget-dialog-with-miniatures (board LpWidgetKiezen):
 * Add widget is 1120 px wide, every visible card shows a miniature of its
 * widget, no miniature sends a request, Tiles and links lists the tile
 * widgets, Layout holds Container, and the edit bar has no Add tiles button.
 *
 * Gate-19 @e2e traceability:
 *   @e2e widgets::open-the-picker
 *   @e2e widgets::filter-by-kind
 *   @e2e widgets::layout-holds-the-container
 *   @e2e widgets::the-weather-card-shows-a-weather-widget
 *   @e2e widgets::no-add-tiles-button
 *
 * @spec openspec/changes/add-widget-dialog-with-miniatures/specs/widgets/spec.md
 */
import type { APIRequestContext } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { gotoLaunchPad } from './fixtures/widget-flow.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'
import { enterEditMode } from './support/dashboardSwitcher.ts'

const ADMIN = {
	user: process.env.NEXTCLOUD_USER || 'admin',
	pass: process.env.NEXTCLOUD_PASS || 'admin',
}

let api: APIRequestContext
let seeded: SeededDashboard | null = null

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	api = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	seeded = await seedActiveDashboard(api, `E2E Picker miniatures ${Date.now()}`)
})

test.afterAll(async () => {
	await removeSeededDashboard(api, seeded)
	await api?.dispose()
})

test('Add widget is 1120 px wide and every card shows a miniature that sends no request', async ({ page }) => {
	await gotoLaunchPad(page)
	await enterEditMode(page)
	await expect(page.getByTestId('launchpad-edit-bar-add-tiles')).toHaveCount(0)
	// Let the dashboard's own widgets finish their reads first.
	await expect(page.getByTestId('launchpad-dashboard-region')).toBeVisible()
	await page.waitForTimeout(1500)
	// REQ-WDG-048: miniatures send nothing. The picker's own reads (the tile
	// form's lists, the assistant check) are not miniature requests.
	const miniatureRequests: string[] = []
	page.on('request', (req) => {
		if (/\/api\/(weather|people|announcements|attention|colleague-activity|livetile|work-packages|activity-report)\b|\/core\/navigation\/apps|\/avatar\/|\/activity\/api\//.test(req.url())) {
			miniatureRequests.push(req.url())
		}
	})
	await page.getByTestId('launchpad-edit-bar-add-widget').click()

	const dialog = page.locator('.widget-picker-modal .modal-container')
	await expect(dialog).toBeVisible()
	const box = await dialog.boundingBox()
	const viewport = page.viewportSize()
	expect(Math.round(box?.width ?? 0)).toBe(Math.min(1120, (viewport?.width ?? 1120) - 32))

	const cards = page.locator('.widget-picker__widget')
	await expect(cards.first()).toBeVisible()
	const count = await cards.count()
	for (let index = 0; index < Math.min(count, 9); index++) {
		await expect(cards.nth(index).locator('.widget-miniature')).toHaveAttribute('aria-hidden', 'true')
	}

	const weather = page.getByTestId('widget-picker-type-weather')
	await weather.scrollIntoViewIfNeeded()
	await expect(weather.getByTestId('widget-miniature-weather')).toHaveAttribute('data-state', 'ready')
	await expect(weather.getByTestId('widget-miniature-weather')).toContainText('Utrecht')
	for (let index = 0; index < count; index++) {
		await cards.nth(index).scrollIntoViewIfNeeded()
	}
	await page.waitForTimeout(1500)
	expect(miniatureRequests).toEqual([])
	// The shared people list draws its sample people, not the server's.
	await expect(page.getByTestId('widget-picker-type-people').locator('.widget-miniature')).toContainText('Anna de Vries')
})

test('Tiles and links lists the tile widgets and Layout holds Container', async ({ page }) => {
	await gotoLaunchPad(page)
	await enterEditMode(page)
	await page.getByTestId('launchpad-edit-bar-add-widget').click()

	await page.getByTestId('widget-picker-kind-tiles').click()
	await expect(page.getByTestId('widget-picker-type-tile')).toBeVisible()
	await expect(page.getByTestId('widget-picker-type-container')).toHaveCount(0)

	await page.getByTestId('widget-picker-kind-layout').click()
	await expect(page.getByTestId('widget-picker-type-container')).toBeVisible()
})

test('the KPI, calendar, activity report, communities, week strip, stacked bar, news, notepad, running flows, tasks, saved view and object table miniatures draw their samples without a request', async ({ page }) => {
	await gotoLaunchPad(page)
	await enterEditMode(page)
	await expect(page.getByTestId('launchpad-dashboard-region')).toBeVisible()
	await page.waitForTimeout(1500)
	// REQ-WDG-048: these read OpenRegister aggregates, the calendar, Talk or
	// the activity report, flow runs, tasks or saved views when in use; as a
	// miniature they read nothing.
	const miniatureRequests: string[] = []
	page.on('request', (req) => {
		if (/\/api\/widgets\/news\/|\/provisioning_api\/|\/apps\/provisioning_api\/|\/aggregations\/|\/api\/objects\/|\/api\/activity-report|\/api\/calendar|\/apps\/spreed\/|\/ocs\/v2\.php\/apps\/spreed|\/api\/flow-runs|\/api\/flow-tasks|\/api\/views/.test(req.url())) {
			miniatureRequests.push(req.url())
		}
	})
	await page.getByTestId('launchpad-edit-bar-add-widget').click()

	const samples: Record<string, string> = {
		stat: '42',
		delta: '18',
		gauge: '82%',
		calendar: 'Team meeting',
		'activity-report': 'file changed',
		communities: 'Cycling club',
		'week-strip': 'Council meeting',
		'stacked-bar': 'In progress',
		news: 'Road works on the Dorpsstraat',
		notepad: 'Call Anna about the permit',
		'flow-runs': 'Permit intake',
		tasks: 'Review permit Dorpsstraat 12',
		'saved-view': 'Permit Dorpsstraat 12',
		'object-table': 'Permit Dorpsstraat 12',
	}
	for (const [type, text] of Object.entries(samples)) {
		const card = page.getByTestId(`widget-picker-type-${type}`)
		await card.scrollIntoViewIfNeeded()
		const miniature = card.getByTestId(`widget-miniature-${type}`)
		await expect(miniature, type).toHaveAttribute('data-state', 'ready', { timeout: 10_000 })
		await expect(miniature, type).toContainText(text, { timeout: 10_000 })
	}
	await page.waitForTimeout(1000)
	expect(miniatureRequests).toEqual([])
})
