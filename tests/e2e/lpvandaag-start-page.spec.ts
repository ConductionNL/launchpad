/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The start page as board LpVandaag draws it (lpvandaag-start-page,
 * attention-feed REQ-ATT-007):
 *
 *  - its header holds one control, the menu More (board LpMeer), next to the
 *    greeting; there is no "Customise start page" button and no title row;
 *  - the greeting reads the part of the day and the reader's first name, then
 *    the date and the week ("Good afternoon, Pieter · Monday, October 5,
 *    2026 · week 41"), as text;
 *  - Vandaag eerst with `itemCards` draws one card per due case, named by the
 *    case, with the source's reason and "Zaak openen"; without `itemCards` it
 *    draws one item for the source with its count.
 *
 * WHAT IS REAL AND WHAT IS STAGED
 * ===============================
 * Pieter is a throwaway account named "Pieter Jansen"; his start page, its
 * header and its two attention widgets are seeded over the API as him and
 * removed afterwards. The clock of his page is set to Monday 5 October 2026,
 * 13:10 (the runner's time zone, which is also the browser's), so the greeting
 * and the week are known. The cases are dossiq's, and a CI instance has
 * neither dossiq nor OpenRegister, so his page is answered in the browser
 * with what they answer: the sources endpoint declares dossiq's "decision
 * term ends today" source and the OpenRegister search lists two cases.
 *
 * The item cards carry the source's own action label ("Zaak openen");
 * `expandToRecords` (src/services/attentionFeed.js) falls back to
 * "Open {item}" only when the source names none.
 *
 * Written from the change's scenarios (start-page-board-sync); it runs in
 * the nightly against a live instance.
 *
 * @e2e openspec/specs/lpvandaag-start-page/spec.md
 * @e2e openspec/specs/attention-feed/spec.md
 */

import type { APIRequestContext, Browser, BrowserContext, Locator, Page } from '@playwright/test'
import type { Principal } from './support/principals.ts'
import type { OwnDashboard } from './support/widgets.ts'

import { expect, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { clearSeededWidgets, removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { MORE_MENU, openSettingsGroup } from './support/dashboardSwitcher.ts'
import { quietFirstVisit } from './support/firstVisit.ts'
import { adminContext, API, createPrincipal, removePrincipal } from './support/principals.ts'
import {
	activate,
	createOwnDashboard,
	readAllowUserDashboards,
	restoreAllowUserDashboards,
	seedPlacement,
} from './support/widgets.ts'

const RUN = Date.now()
/** Monday 5 October 2026, 13:10, in the runner's (and the browser's) time zone. */
const AFTER_LUNCH = new Date(2026, 9, 5, 13, 10)
const REASON = 'De beslistermijn loopt vandaag af'
const CASES = [
	{ id: 'zaak-1', title: 'Parkeervergunningen binnenstad' },
	{ id: 'zaak-2', title: 'Bezwaar WOZ Havenpark' },
]
const HEADER = {
	title: 'Vandaag',
	greeting: true,
	greetingLine: true,
	showDate: true,
	showWeek: true,
	ground: true,
}

/** dossiq's declared source, as `GET /api/attention/sources` returns it. */
const DOSSIQ_SOURCE = {
	appId: 'dossiq',
	appName: 'dossiq',
	id: 'decision-due-today',
	title: REASON,
	severity: 'error',
	op: 'gt',
	value: 0,
	reason: '{value} zaken met een beslistermijn die vandaag afloopt',
	action: { label: 'Zaak openen', path: '/apps/dossiq/zaken' },
	source: { register: 'dossiq', schema: 'zaak', filter: { deadline: '@today' } },
}

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

let admin: APIRequestContext
let allowUserDashboardsBefore: boolean | null = null
let pieter: Principal | null = null
let dashboard: OwnDashboard | null = null
let header = 0
let cards = 0
let counts = 0

/**
 * Answer dossiq's attention source and its OpenRegister search in Pieter's
 * browser: the search lists up to `_limit` of the two cases due today and
 * reports their total.
 *
 * @param context Pieter's browser context.
 * @return nothing.
 */
async function stageDossiq(context: BrowserContext): Promise<void> {
	await context.route('**/apps/launchpad/api/attention/sources', (route) =>
		route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify({ userId: pieter!.username, sources: [DOSSIQ_SOURCE], invalid: [] }),
		}),
	)
	await context.route((url) => url.pathname.endsWith('/apps/openregister/api/objects/dossiq/zaak'), (route) => {
		const limit = Number(new URL(route.request().url()).searchParams.get('_limit') ?? '1')
		route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify({ total: CASES.length, results: CASES.slice(0, limit) }),
		})
	})
}

/**
 * Open Pieter's start page after lunch on Monday 5 October 2026.
 *
 * @param browser the browser.
 * @return his context and page.
 */
async function openStartPage(browser: Browser): Promise<{ context: BrowserContext; page: Page }> {
	const session = await loginAs(browser, pieter!.username, pieter!.password)
	await stageDossiq(session.context)
	await session.page.clock.setFixedTime(AFTER_LUNCH)
	await session.page.goto('/index.php/apps/launchpad/')
	await expect(session.page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
	return session
}

/**
 * A seeded placement's grid cell.
 *
 * @param page the page.
 * @param id the placement id.
 * @return its locator.
 */
function placement(page: Page, id: number): Locator {
	return page.locator(`.launchpad-grid-item[data-placement-id="${id}"]`)
}

test.beforeAll(async () => {
	test.setTimeout(240_000)
	admin = await adminContext()
	allowUserDashboardsBefore = await readAllowUserDashboards(admin)
	const allow = await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
	expect(allow.status(), await allow.text()).toBeLessThan(300)

	pieter = await createPrincipal(admin, 'e2e-vandaag-pieter')
	const named = await admin.put(
		`/ocs/v1.php/cloud/users/${encodeURIComponent(pieter.username)}`,
		{ form: { key: 'displayname', value: 'Pieter Jansen' } },
	)
	expect(named.ok(), `set the display name -> ${named.status()}`).toBeTruthy()
	await quietFirstVisit(pieter.api)

	dashboard = await createOwnDashboard(pieter.api, `Vandaag ${RUN}`)
	await clearSeededWidgets(pieter.api, dashboard.id)
	header = (await seedPlacement(pieter.api, dashboard.id, {
		widgetId: 'header',
		gridX: 0,
		gridY: 0,
		gridWidth: 12,
		gridHeight: 2,
		content: HEADER,
	})).id
	cards = (await seedPlacement(pieter.api, dashboard.id, {
		widgetId: 'attention',
		gridX: 0,
		gridY: 2,
		gridWidth: 12,
		gridHeight: 3,
		content: { title: 'Vandaag eerst', layout: 'columns', limit: 3, itemCards: true },
	})).id
	counts = (await seedPlacement(pieter.api, dashboard.id, {
		widgetId: 'attention',
		gridX: 0,
		gridY: 5,
		gridWidth: 12,
		gridHeight: 3,
		content: { limit: 3 },
	})).id
	await activate(pieter.api, dashboard)
})

test.afterAll(async () => {
	if (pieter !== null && dashboard !== null) {
		await pieter.api.delete(`${API}/dashboard/${dashboard.id}`)
	}
	await removePrincipal(admin, pieter)
	await restoreAllowUserDashboards(admin, allowUserDashboardsBefore)
	await admin.dispose()
})

// @e2e lpvandaag-start-page::pieter-opens-the-start-page-after-lunch
test('the header greets Pieter after lunch with the date, and holds only More', async ({
	browser,
}) => {
	const { context, page } = await openStartPage(browser)
	try {
		const greetingRow = placement(page, header).locator('.cn-header-widget--ground')
		await expect(greetingRow).toBeVisible({ timeout: 30_000 })

		// THEN: "Good afternoon, Pieter · Monday, October 5, 2026", as text.
		const line = greetingRow.locator('[data-testid="cn-header-widget-line"]')
		await expect(line).toHaveText(/^Good afternoon, Pieter · /)
		await expect(line).toContainText('October')
		await expect(line).toContainText('2026')
		await expect(line).toContainText(/\b5\b/)
		await expect(line).toContainText('Monday')
		await expect(greetingRow.locator('img[alt*="Pieter"]')).toHaveCount(0)

		// The header's one control is More, docked in the greeting row.
		const actions = greetingRow.locator('.launchpad-header-actions')
		await expect(actions.locator(MORE_MENU)).toBeVisible()
		const controls = page.locator('[data-testid="launchpad-top-controls"]')
		await expect(controls.locator('button:visible')).toHaveCount(1)
		await expect(controls.locator('[data-testid="launchpad-more-menu-trigger"]')).toBeVisible()
		// No title row, no "Customise start page" and no Edit button.
		await expect(page.locator('[data-testid="dashboard-page-header"]')).toHaveCount(0)
		await expect(
			page.getByRole('button', { name: /customi[sz]e (the )?start ?page|startpagina aanpassen/i }),
		).toHaveCount(0)
		await expect(page.getByRole('button', { name: /^(edit|bewerken)$/i })).toHaveCount(0)
	} finally {
		await context.close()
	}
})

// @e2e lpvandaag-start-page::the-week-number
test('with showWeek the header also shows week 41', async ({ browser }) => {
	const { context, page } = await openStartPage(browser)
	try {
		const line = placement(page, header).locator('[data-testid="cn-header-widget-line"]')
		await expect(line).toHaveText(/^Good afternoon, Pieter · .+ · week 41$/, { timeout: 30_000 })
	} finally {
		await context.close()
	}
})

// @e2e lpvandaag-start-page::an-administrator-opens-meer
test('an administrator opens More on a start page: Admin settings once under Settings, beside Templates', async ({
	page,
}) => {
	const own = await seedActiveDashboard(admin, `Vandaag beheer ${RUN}`)
	try {
		await clearSeededWidgets(admin, own.id)
		const res = await admin.post(`${API}/dashboard/${own.id}/widgets`, {
			data: { widgetId: 'header', gridX: 0, gridY: 0, gridWidth: 12, gridHeight: 2, content: HEADER },
		})
		expect(res.status(), await res.text()).toBe(201)

		await page.goto('/index.php/apps/launchpad/')
		const more = page.locator(MORE_MENU)
		await expect(more).toBeVisible({ timeout: 30_000 })
		await expect(page.locator('.cn-header-widget--ground .launchpad-header-actions')).toBeVisible()
		// AND: the header shows no "Customise start page" button.
		await expect(
			page.getByRole('button', { name: /customi[sz]e (the )?start ?page|startpagina aanpassen/i }),
		).toHaveCount(0)

		await more.locator('[data-testid="launchpad-more-menu-trigger"]').click()
		await openSettingsGroup(page)
		const settings = page.locator('[data-testid="launchpad-more-menu-group-settings"]:visible')
		// "Admin settings" appears once in the whole menu, and under Settings.
		await expect(
			page.locator('[data-testid="launchpad-more-menu-panel"]').getByText('Admin settings', { exact: true }),
		).toHaveCount(1)
		await expect(settings.getByText('Admin settings', { exact: true })).toBeVisible()
		await expect(settings.getByText('Templates', { exact: true })).toBeVisible()
	} finally {
		await removeSeededDashboard(admin, own)
	}
})

// @e2e attention-feed::two-cases-due-today
test('Vandaag eerst shows one card per due case, each with the reason and Zaak openen', async ({
	browser,
}) => {
	const { context, page } = await openStartPage(browser)
	try {
		const widget = placement(page, cards).locator('[data-testid="attention-widget"]')
		const items = widget.locator('.attention-widget__item')
		await expect(items).toHaveCount(2, { timeout: 30_000 })
		for (const [i, zaak] of CASES.entries()) {
			const card = items.nth(i)
			await expect(card.locator('.attention-widget__title')).toContainText(zaak.title)
			await expect(card.locator('.attention-widget__reason')).toHaveText(REASON)
			await expect(card.locator('.attention-widget__app')).toHaveText('dossiq')
			const action = card.locator('.attention-widget__action')
			await expect(action).toHaveText('Zaak openen')
			// "Zaak openen" opens that case in dossiq.
			await expect(action).toHaveAttribute('href', new RegExp(`/apps/dossiq/zaken/${zaak.id}$`))
		}
	} finally {
		await context.close()
	}
})

// @e2e attention-feed::without-item-cards
test('without itemCards the same feed shows one dossiq item with the count 2', async ({
	browser,
}) => {
	const { context, page } = await openStartPage(browser)
	try {
		const widget = placement(page, counts).locator('[data-testid="attention-widget"]')
		const items = widget.locator('.attention-widget__item')
		await expect(items).toHaveCount(1, { timeout: 30_000 })
		await expect(items.first().locator('.attention-widget__title')).toContainText(REASON)
		await expect(items.first().locator('.attention-widget__reason')).toHaveText(
			'2 zaken met een beslistermijn die vandaag afloopt',
		)
		await expect(items.first().locator('.attention-widget__action')).toHaveText('Zaak openen')
	} finally {
		await context.close()
	}
})
