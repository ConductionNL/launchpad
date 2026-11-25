/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright e2e for header-search-dropdown (board LpWerkplek): type "team"
 * in the top bar's search field on My workspace, see the results list under
 * the field, and open "Zuiddrecht intranet" with the keyboard.
 *
 * The unified search providers are stubbed in the browser, so the spec does
 * not depend on which apps the instance has or what the demo seed holds.
 * "Zuiddrecht intranet" points at a dashboard the spec seeds through the
 * real API and removes afterwards; the "!top" shortcut is set through the
 * admin API and the earlier list is put back.
 *
 * Gate-19 @e2e traceability:
 *   @e2e nc-unified-search-integration::results-under-the-field
 *   @e2e nc-unified-search-integration::the-keyboard-drives-the-list
 *   @e2e nc-unified-search-integration::the-shortcut-tip
 *   @e2e nc-unified-search-integration::escape-closes-the-list
 *
 * @spec openspec/changes/header-search-dropdown/specs/nc-unified-search-integration/spec.md#requirement-req-srch-013-the-top-bar-search-shows-its-results-under-the-field
 */
import type { APIRequestContext, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'

import { expect, request, test } from '@playwright/test'
import { gotoLaunchPad } from './fixtures/widget-flow.ts'
import { BASE_URL } from './support/baseUrl.ts'
import {
	removeSeededDashboard,
	seedActiveDashboard,
} from './support/dashboardFixture.ts'

const ADMIN = {
	user: process.env.NEXTCLOUD_USER || 'admin',
	pass: process.env.NEXTCLOUD_PASS || 'admin',
}

const SHORTCUTS = '/index.php/apps/launchpad/api/admin/search-shortcuts'
const FIELD = '.unified-search-input, #unified-search input, .unified-search__input, input.unified-search-menu__input'
const PANEL = '[data-testid="header-search-dropdown"]'

let api: APIRequestContext
let seeded: SeededDashboard | null = null
let priorShortcuts: unknown[] = []

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	api = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	seeded = await seedActiveDashboard(api, `Zuiddrecht intranet ${Date.now()}`)
	const prior = await api.get(SHORTCUTS)
	const body = await prior.json().catch(() => ({}))
	priorShortcuts = Array.isArray(body?.shortcuts) ? body.shortcuts : []
	const saved = await api.put(SHORTCUTS, {
		data: { shortcuts: [{ prefix: '!top', name: 'TOPdesk', urlTemplate: 'https://topdesk.example/search?q={query}' }] },
	})
	expect(saved.status(), await saved.text()).toBe(200)
})

test.afterAll(async () => {
	await api?.put(SHORTCUTS, { data: { shortcuts: priorShortcuts } })
	await removeSeededDashboard(api, seeded)
	await api?.dispose()
})

/**
 * Answer the unified search with two dashboards (board LpWerkplek) and an
 * empty Files provider.
 *
 * @param page The page.
 */
async function stubUnifiedSearch(page: Page): Promise<void> {
	await page.route('**/ocs/v2.php/search/providers?**', (route) => route.fulfill({
		json: { ocs: { meta: { status: 'ok' }, data: [
			{ id: 'files', appId: 'files', name: 'Files', order: 5 },
			{ id: 'launchpad', appId: 'launchpad', name: 'Dashboards', order: 5 },
		] } },
	}))
	await page.route('**/ocs/v2.php/search/providers/files/search**', (route) => route.fulfill({
		json: { ocs: { meta: { status: 'ok' }, data: { name: 'Files', isPaginated: false, entries: [], cursor: null } } },
	}))
	await page.route('**/ocs/v2.php/search/providers/launchpad/search**', (route) => route.fulfill({
		json: { ocs: { meta: { status: 'ok' }, data: { name: 'Dashboards', isPaginated: false, cursor: null, entries: [
			{ title: 'Permits team', subline: 'Shared by Marieke Jansen · 14 widgets', thumbnailUrl: '', resourceUrl: '/index.php/apps/launchpad/#dashboard/permits-team', icon: '' },
			{ title: 'Zuiddrecht intranet', subline: 'Group dashboard of Communications', thumbnailUrl: '', resourceUrl: `/index.php/apps/launchpad/#dashboard/${seeded?.uuid ?? ''}`, icon: '' },
		] } } },
	}))
}

test('typing "team" opens the list under the field, and the keyboard opens Zuiddrecht intranet', async ({ page }) => {
	await stubUnifiedSearch(page)
	await gotoLaunchPad(page)

	const field = page.locator(FIELD).first()
	await field.click()
	await field.fill('team')

	const panel = page.locator(PANEL)
	await expect(panel).toBeVisible()
	await expect(panel).toContainText('You searched for team')
	await expect(panel.locator('[data-testid="header-search-group"]').first()).toHaveText('DASHBOARDS')
	await expect(field).toHaveAttribute('role', 'combobox')
	await expect(field).toHaveAttribute('aria-expanded', 'true')

	const selected = panel.locator('[role="option"][aria-selected="true"]')
	await expect(selected).toContainText('Permits team')
	await expect(selected).toContainText('Shared by Marieke Jansen · 14 widgets')
	await expect(panel).toContainText('Group dashboard of Communications')
	await expect(panel.locator('[data-testid="header-search-tip"]')).toHaveText('Tip: type !top to search TOPdesk directly')

	// The list sits under the field and is as wide as it.
	const fieldBox = await field.boundingBox()
	const panelBox = await panel.boundingBox()
	expect(panelBox && fieldBox && panelBox.y >= fieldBox.y + fieldBox.height - 1).toBe(true)
	expect(panelBox && fieldBox && Math.abs(panelBox.width - fieldBox.width) <= 2).toBe(true)

	await page.keyboard.press('ArrowDown')
	await expect(selected).toContainText('Zuiddrecht intranet')
	await page.keyboard.press('Enter')
	await expect(page).toHaveURL(new RegExp(`#dashboard/${seeded?.uuid}`))
})

test('Escape closes the list and the field keeps the query and the focus', async ({ page }) => {
	await stubUnifiedSearch(page)
	await gotoLaunchPad(page)

	const field = page.locator(FIELD).first()
	await field.click()
	await field.fill('team')
	await expect(page.locator(PANEL)).toBeVisible()

	await page.keyboard.press('Escape')
	await expect(page.locator(PANEL)).toHaveCount(0)
	await expect(field).toHaveValue('team')
	await expect(field).toBeFocused()
	await expect(field).toHaveAttribute('aria-expanded', 'false')
})
