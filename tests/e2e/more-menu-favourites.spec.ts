/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The More menu's Dashboards column after Ruben's review of 27 Nov 2026
 * (REVIEW-LAUNCHPAD items 4-8, openspec change more-menu-favourites): no
 * left panel, the favourites as a short list, "More" unfolding every
 * dashboard in place with a star each, and "Powered by" in the menu.
 *
 * Gate-19 @e2e traceability:
 *   @e2e runtime-shell::no-dashboards-panel
 *   @e2e runtime-shell::favourites-only
 *   @e2e runtime-shell::more-unfolds-in-place
 *   @e2e runtime-shell::starring-survives-a-reload
 *   @e2e runtime-shell::download-from-more
 */

import type { APIRequestContext, Browser, BrowserContext, Page } from '@playwright/test'
import type { SeededDashboard } from './support/dashboardFixture.ts'
import type { Principal } from './support/principals.ts'

import { expect, request, test } from '@playwright/test'
import { loginAs } from './fixtures/secondary-user.ts'
import { BASE_URL } from './support/baseUrl.ts'
import { removeSeededDashboard, seedActiveDashboard } from './support/dashboardFixture.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'
import { API, createPrincipal, removePrincipal } from './support/principals.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

/** More dashboards than the old folded list of eight held. */
const COUNT = 9
const RUN = Date.now()
const TRIGGER = `${MORE_MENU} [data-testid="launchpad-more-menu-trigger"]`
const LIST = '[data-testid="launchpad-more-menu-dashboard-list"]'
const UNFOLD = '[data-testid="launchpad-more-menu-item-all-dashboards"]'
const HINT = '[data-testid="launchpad-more-menu-favourites-hint"]'
const HINT_TEXT = 'Star a dashboard under More to see it here.'

let api: APIRequestContext
let seeded: SeededDashboard | null = null
let member: Principal | null = null
const own: Array<{ id: number, uuid: string, name: string }> = []

test.describe.configure({ mode: 'serial' })

test.beforeAll(async () => {
	api = await request.newContext({
		baseURL: BASE_URL,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
	// Also turns personal dashboards on, which the member's own need.
	seeded = await seedActiveDashboard(api, `E2E favourites ${RUN}`)
	member = await createPrincipal(api, 'e2e-fav-member')
	for (let i = 0; i < COUNT; i++) {
		const created = await member.api.post(`${API}/dashboard`, { data: { name: `Fav ${i} ${RUN}` } })
		expect(created.status(), await created.text()).toBeLessThan(300)
		const body = await created.json()
		const dash = body.dashboard ?? body.data?.dashboard ?? body
		own.push({ id: Number(dash.id), uuid: dash.uuid, name: `Fav ${i} ${RUN}` })
	}
	const preference = await member.api.post(`${API}/dashboards/active`, { data: { uuid: own[0].uuid } })
	expect(preference.status(), await preference.text()).toBeLessThan(300)
	await member.api.put(`${API}/preferences/favourite-dashboards`, { data: { value: '' } })
})

test.afterAll(async () => {
	if (member !== null) {
		for (const d of own) {
			await member.api.delete(`${API}/dashboard/${d.id}`)
		}
	}
	await removeSeededDashboard(api, seeded)
	await removePrincipal(api, member)
	await api?.dispose()
})

/**
 * Log the member in.
 *
 * @param browser the browser.
 * @return the context and page; the caller closes the context.
 */
async function memberSession(browser: Browser): Promise<{ context: BrowserContext, page: Page }> {
	expect(member, 'the member was not provisioned').not.toBeNull()
	return loginAs(browser, (member as Principal).username, (member as Principal).password)
}

/**
 * Open LaunchPad and open More.
 *
 * @param page the page.
 */
async function openMore(page: Page): Promise<void> {
	await page.goto('/index.php/apps/launchpad/')
	await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
	await page.locator(TRIGGER).click()
	await expect(page.locator(LIST)).toBeVisible()
}

/**
 * The labels of the Dashboards column's rows, More/Fewer included.
 *
 * @param page the page.
 * @return the labels.
 */
async function rows(page: Page): Promise<string[]> {
	return (await page.locator(`${LIST} .lp-more-menu__label`).allTextContents()).map((l) => l.trim())
}

test('the dashboard has no Dashboards panel and its header keeps only More', async ({ browser }) => {
	const { context, page } = await memberSession(browser)
	try {
		await page.goto('/index.php/apps/launchpad/')
		await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
		await expect(page.locator('.dashboard-switcher-sidebar')).toHaveCount(0)
		await expect(page.getByTestId('dashboard-download')).toHaveCount(0)
		await expect(page.getByTestId('dashboard-page-header').locator('button:visible')).toHaveCount(1)
	} finally {
		await context.close()
	}
})

test('More unfolds every dashboard in place, each with an empty star, and Fewer folds it', async ({ browser }) => {
	const { context, page } = await memberSession(browser)
	try {
		await openMore(page)
		// Without favourites the folded column is the open dashboard, a line
		// saying that a star puts a dashboard here, and More (Ruben, 27 Nov).
		expect(await rows(page)).toEqual([own[0].name, 'More'])
		await expect(page.locator(HINT)).toHaveText(HINT_TEXT)
		await expect(page.locator(`${LIST} .lp-more-menu__star`)).toHaveCount(0)

		await page.locator(UNFOLD).click()
		await expect(page.locator('[data-testid="launchpad-more-menu-panel"]')).toBeVisible()
		await expect(page.locator(UNFOLD)).toHaveAttribute('aria-expanded', 'true')
		const all = await rows(page)
		for (const d of own) {
			expect(all).toContain(d.name)
		}
		expect(all.at(-1)).toBe('Fewer')
		const stars = page.locator(`${LIST} .lp-more-menu__star`)
		expect(await stars.count()).toBeGreaterThanOrEqual(COUNT)
		for (const pressed of await stars.evaluateAll((els) => els.map((el) => el.getAttribute('aria-pressed')))) {
			expect(pressed).toBe('false')
		}

		await page.locator(UNFOLD).click()
		expect(await rows(page)).toEqual([own[0].name, 'More'])
	} finally {
		await context.close()
	}
})

test('a star is stored for the person, survives a reload, and the folded column then lists only the favourites', async ({ browser }) => {
	const { context, page } = await memberSession(browser)
	try {
		await openMore(page)
		await page.locator(UNFOLD).click()
		const last = own[COUNT - 1]
		const star = page.locator(`[data-testid="launchpad-more-menu-star-dashboard-${last.id}"]`)
		const stored = page.waitForResponse((r) => r.url().includes('/api/preferences/favourite-dashboards') && r.request().method() === 'PUT')
		await star.click()
		expect((await stored).status()).toBe(200)
		await expect(star).toHaveAttribute('aria-pressed', 'true')
		await expect(star).toHaveCSS('color', 'rgb(245, 179, 1)')
		// Starring does not switch: the open dashboard is still the first.
		await expect(page.locator(`[data-testid="launchpad-more-menu-item-dashboard-${own[0].id}"]`)).toHaveAttribute('aria-checked', 'true')

		await page.reload()
		await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
		await page.locator(TRIGGER).click()
		expect(await rows(page)).toEqual([last.name, 'More'])
		await expect(page.locator(HINT)).toHaveCount(0)

		await page.locator(UNFOLD).click()
		const again = page.waitForResponse((r) => r.url().includes('/api/preferences/favourite-dashboards') && r.request().method() === 'PUT')
		await page.locator(`[data-testid="launchpad-more-menu-star-dashboard-${last.id}"]`).click()
		await again
		await page.locator(UNFOLD).click()
		expect(await rows(page)).toEqual([own[0].name, 'More'])
		await expect(page.locator(HINT)).toBeVisible()
	} finally {
		await context.close()
	}
})

test('Powered by and Documentation are the menu footer, and Download is in This dashboard', async ({ browser }) => {
	const { context, page } = await memberSession(browser)
	try {
		await openMore(page)
		const footer = page.locator('[data-testid="launchpad-more-menu-panel"] [data-testid="sidebar-footer"]')
		await expect(footer).toBeVisible()
		await expect(footer).toContainText('Powered by')
		for (const href of ['https://sendent.com', 'https://conduction.nl']) {
			await expect(footer.locator(`a[href="${href}"]`)).toHaveAttribute('rel', 'noopener noreferrer')
		}
		await expect(page.locator('[data-testid="launchpad-more-menu-group-this-dashboard"] [data-testid="launchpad-more-menu-item-download"]')).toBeVisible()
	} finally {
		await context.close()
	}
})
