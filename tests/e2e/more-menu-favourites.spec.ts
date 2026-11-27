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
 *   @e2e runtime-shell::new-dashboard-from-the-full-list
 *   @e2e runtime-shell::favourite-of-a-hidden-dashboard-is-404
 *
 * @spec openspec/changes/more-menu-favourites/tasks.md#task-12
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
const HINT_TEXT = 'Star a dashboard to keep it here'
const TITLE = '[data-testid="launchpad-more-menu-group-dashboards"] .lp-more-menu__title'
const NEW = '[data-testid="launchpad-more-menu-item-new-dashboard"]'

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
 * The labels of the Dashboards column's dashboard rows (More, Less and New
 * dashboard stand below the list).
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

test('without favourites the column shows the open dashboard, the starring line and More (REQ-SHELL-014)', async ({ browser }) => {
	const { context, page } = await memberSession(browser)
	try {
		await openMore(page)
		expect(await rows(page)).toEqual([own[0].name])
		await expect(page.locator(HINT)).toHaveText(HINT_TEXT)
		await expect(page.locator(TITLE)).toHaveText('Dashboards')
		// The open dashboard's row carries its (empty) star.
		await expect(page.locator(`${LIST} .lp-more-menu__star`)).toHaveCount(1)
		await expect(page.locator(UNFOLD)).toContainText('More')
		// The count is every other dashboard the member can open (checked
		// against the expanded list in the next test).
		await expect(page.locator(UNFOLD)).toContainText(/\d+ more dashboards/)
	} finally {
		await context.close()
	}
})

test('More turns the column into All dashboards with a star per row, New dashboard below it, and Less folds it (REQ-SHELL-015)', async ({ browser }) => {
	const { context, page } = await memberSession(browser)
	try {
		await openMore(page)
		// Opening More and pressing More fetch nothing (the page carries the list).
		const requests: string[] = []
		page.on('request', (r) => {
			if (r.url().includes('/apps/launchpad/api/')) {
				requests.push(r.url())
			}
		})
		await page.locator(UNFOLD).click()
		await expect(page.locator('[data-testid="launchpad-more-menu-panel"]')).toBeVisible()
		await expect(page.locator(UNFOLD)).toHaveAttribute('aria-expanded', 'true')
		await expect(page.locator(TITLE)).toHaveText('All dashboards')
		const all = await rows(page)
		for (const d of own) {
			expect(all).toContain(d.name)
		}
		await expect(page.locator(UNFOLD)).toContainText('Less')
		await expect(page.locator(NEW)).toBeVisible()
		// New dashboard and Less stand below the scrolling list, not in it.
		await expect(page.locator(`${LIST} ${NEW}`)).toHaveCount(0)
		const stars = page.locator(`${LIST} .lp-more-menu__star`)
		expect(await stars.count()).toBeGreaterThanOrEqual(COUNT)
		for (const pressed of await stars.evaluateAll((els) => els.map((el) => el.getAttribute('aria-pressed')))) {
			expect(pressed).toBe('false')
		}
		expect(requests).toEqual([])

		await page.locator(UNFOLD).click()
		expect(await rows(page)).toEqual([own[0].name])
		await expect(page.locator(UNFOLD)).toContainText(`${all.length - 1} more dashboards`)
	} finally {
		await context.close()
	}
})

test('a star is stored for the person, survives a reload, and unstarring removes it (REQ-SHELL-016)', async ({ browser }) => {
	const { context, page } = await memberSession(browser)
	try {
		await openMore(page)
		await page.locator(UNFOLD).click()
		const last = own[COUNT - 1]
		const star = page.locator(`[data-testid="launchpad-more-menu-star-dashboard-${last.id}"]`)
		await expect(star).toHaveAttribute('aria-label', `Add to favourites: ${last.name}`)
		const stored = page.waitForResponse((r) => r.url().endsWith(`/api/dashboards/${last.uuid}/favourite`) && r.request().method() === 'POST')
		await star.click()
		const answer = await stored
		expect(answer.status()).toBe(200)
		expect((await answer.json()).favourites).toEqual([last.uuid])
		await expect(star).toHaveAttribute('aria-pressed', 'true')
		await expect(star).toHaveCSS('color', 'rgb(240, 180, 0)')
		// Starring neither switches nor closes the menu.
		await expect(page.locator('[data-testid="launchpad-more-menu-panel"]')).toBeVisible()
		await expect(page.locator(`[data-testid="launchpad-more-menu-item-dashboard-${own[0].id}"]`)).toHaveAttribute('aria-checked', 'true')

		await page.reload()
		await expect(page.locator(MORE_MENU)).toBeVisible({ timeout: 30_000 })
		await page.locator(TRIGGER).click()
		expect(await rows(page)).toEqual([last.name])
		await expect(page.locator(HINT)).toHaveCount(0)
		await expect(page.locator(UNFOLD)).toContainText(/\d+ more dashboards/)

		// Unstar from the short list.
		const removed = page.waitForResponse((r) => r.url().endsWith(`/api/dashboards/${last.uuid}/favourite`) && r.request().method() === 'DELETE')
		await page.locator(`[data-testid="launchpad-more-menu-star-dashboard-${last.id}"]`).click()
		expect((await removed).status()).toBe(200)
		expect(await rows(page)).toEqual([own[0].name])
		await expect(page.locator(HINT)).toBeVisible()
	} finally {
		await context.close()
	}
})

test('the server refuses a favourite on a dashboard the person cannot open with 404 (REQ-SHELL-016)', async () => {
	expect(member).not.toBeNull()
	expect(seeded).not.toBeNull()
	const hidden = await api.post(`${API}/dashboard`, { data: { name: `Hidden ${RUN}` } })
	expect(hidden.status(), await hidden.text()).toBeLessThan(300)
	const body = await hidden.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	try {
		const refused = await (member as Principal).api.post(`${API}/dashboards/${dash.uuid}/favourite`)
		expect(refused.status()).toBe(404)
		const unknown = await (member as Principal).api.delete(`${API}/dashboards/no-such-uuid/favourite`)
		expect(unknown.status()).toBe(404)
	} finally {
		await api.delete(`${API}/dashboard/${dash.id}`)
	}
})

test('New dashboard in the expanded list creates "My workspace" and opens it (REQ-SHELL-015)', async ({ browser }) => {
	const { context, page } = await memberSession(browser)
	let createdId: number | null = null
	try {
		await openMore(page)
		await page.locator(UNFOLD).click()
		const created = page.waitForResponse((r) => /\/api\/dashboard$/.test(r.url()) && r.request().method() === 'POST')
		await page.locator(NEW).click()
		const answer = await created
		expect(answer.status(), await answer.text()).toBeLessThan(300)
		const body = await answer.json()
		const dash = body.dashboard ?? body.data?.dashboard ?? body
		createdId = Number(dash.id)
		expect(dash.name).toBe('My workspace')
		await page.locator(TRIGGER).click()
		await expect(page.locator(`[data-testid="launchpad-more-menu-item-dashboard-${createdId}"]`)).toHaveAttribute('aria-checked', 'true')
	} finally {
		if (createdId !== null && member !== null) {
			await member.api.delete(`${API}/dashboard/${createdId}`)
		}
		await context.close()
	}
})

test('Powered by is the menu footer, and Download is in This dashboard between Duplicate and Set as default (REQ-SHELL-017, REQ-DPD-003)', async ({ browser }) => {
	const { context, page } = await memberSession(browser)
	try {
		await openMore(page)
		const footer = page.locator('[data-testid="launchpad-more-menu-panel"] [data-testid="more-menu-footer"]')
		await expect(footer).toBeVisible()
		await expect(footer).toContainText('Powered by')
		for (const href of ['https://sendent.com', 'https://conduction.nl']) {
			await expect(footer.locator(`a[href="${href}"]`)).toHaveAttribute('rel', 'noopener noreferrer')
		}
		const group = page.locator('[data-testid="launchpad-more-menu-group-this-dashboard"]')
		await expect(group.locator('[data-testid="launchpad-more-menu-item-download"]')).toBeVisible()
		const ids = await group.locator('.lp-more-menu__item').evaluateAll((els) => els.map((el) => el.getAttribute('data-testid')))
		const at = (id: string) => ids.indexOf(`launchpad-more-menu-item-${id}`)
		expect(at('download')).toBeGreaterThan(at('duplicate'))
		expect(at('download')).toBeLessThan(at('set-default'))
	} finally {
		await context.close()
	}
})
