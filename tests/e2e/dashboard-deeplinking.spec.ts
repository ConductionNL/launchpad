/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for dashboard deep-linking
 * (openspec/specs/dashboard-deeplinking), written for
 * e2e-coverage-programme task 3.17.
 *
 * A throwaway non-admin account gets two personal dashboards with their own
 * slugs. The tests open them the way a person does: by address, by the
 * sidebar, and with the browser's back button. `history.pushState` is counted
 * by an init script, so "no push on the initial mount" is observed rather
 * than assumed.
 *
 * Gate-19 @e2e traceability:
 *   @e2e dashboard-deeplinking::stale-slug-falls-back-silently
 *   @e2e dashboard-deeplinking::canonical-path-passed-through-initial-state
 *   @e2e dashboard-deeplinking::endpoint-returns-canonical-slug-chain
 *   @e2e dashboard-deeplinking::switching-dashboard-pushes-history-entry
 *   @e2e dashboard-deeplinking::back-button-navigates-to-previous-dashboard
 *   @e2e dashboard-deeplinking::api-health-check-still-routes-after-catch-all
 *
 * @spec openspec/specs/dashboard-deeplinking/spec.md
 */

import type { APIRequestContext, Browser, Locator, Page } from '@playwright/test'

import { expect, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { openDashboardSwitcher } from './support/dashboardSwitcher.ts'
import { adminContext, contextFor } from './support/principals.ts'

const APP = '/index.php/apps/launchpad'
const API = `${APP}/api`
const RUN = Date.now()
const NAME_A = `E2E Deeplink A ${RUN}`
const NAME_B = `E2E Deeplink B ${RUN}`
const SLUG_A = `e2e-deeplink-a-${RUN}`
const SLUG_B = `e2e-deeplink-b-${RUN}`
const ROW = '.dashboard-switcher-sidebar__item'

type Account = { username: string; password: string }
type Dashboard = { id: number; uuid: string }

let account: Account | null = null
let dashA: Dashboard
let dashB: Dashboard

/**
 * An API context as the administrator, or as the given account. It never
 * carries the admin session cookie, so a call made for the account is made
 * as the account (see support/principals.ts).
 *
 * @param who the account; the administrator when omitted.
 * @return the context; the caller disposes it.
 */
async function apiAs(who?: Account): Promise<APIRequestContext> {
	return who ? contextFor(who.username, who.password) : adminContext()
}

/**
 * Create a personal dashboard with its own slug.
 *
 * @param api the account's context.
 * @param name the name.
 * @param slug the slug.
 * @return its id and uuid.
 */
async function createDashboard(
	api: APIRequestContext,
	name: string,
	slug: string,
): Promise<Dashboard> {
	const created = await api.post(`${API}/dashboard`, { data: { name, slug } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	return { id: Number(dash.id), uuid: dash.uuid }
}

/**
 * The pathname of the page, without a trailing slash or the `/index.php`
 * front controller, which the app leaves out of the address it writes.
 *
 * @param page the page.
 * @return the pathname.
 */
function pathOf(page: Page): string {
	return new URL(page.url()).pathname
		.replace(/\/+$/, '')
		.replace(/^\/index\.php(?=\/)/, '')
}

/**
 * The sidebar row of a dashboard.
 *
 * @param page the page.
 * @param name the dashboard's name.
 * @return the row.
 */
function row(page: Page, name: string): Locator {
	return page.locator(ROW).filter({ hasText: name })
}

/**
 * Log in with `history.pushState` counted, and open an address.
 *
 * @param browser the browser.
 * @param path the address to open.
 * @return the context and page.
 */
async function openAt(browser: Browser, path: string) {
	const session = await loginAs(browser, account!.username, account!.password)
	await session.page.addInitScript(() => {
		const original = window.history.pushState.bind(window.history)
		;(window as unknown as { __pushes: number }).__pushes = 0
		window.history.pushState = (...args: Parameters<History['pushState']>) => {
			;(window as unknown as { __pushes: number }).__pushes += 1
			return original(...args)
		}
	})
	const response = await session.page.goto(path)
	return { ...session, response }
}

/**
 * How often the page called `history.pushState`.
 *
 * @param page the page.
 * @return the count.
 */
async function pushes(page: Page): Promise<number> {
	return page.evaluate(() => (window as unknown as { __pushes: number }).__pushes)
}

test.beforeAll(async () => {
	const admin = await apiAs()
	try {
		const enable = await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
		expect(enable.status(), await enable.text()).toBeLessThan(300)
	} finally {
		await admin.dispose()
	}
	account = await provisionThrowawayUser('e2e-deeplink')
	const api = await apiAs(account)
	try {
		dashA = await createDashboard(api, NAME_A, SLUG_A)
		dashB = await createDashboard(api, NAME_B, SLUG_B)
	} finally {
		await api.dispose()
	}
})

test.afterAll(async () => {
	if (account) {
		await deprovisionUser(account.username)
	}
})

test.describe.serial('dashboard deep-linking (REQ-DDL-002..007)', () => {
	// @e2e dashboard-deeplinking::stale-slug-falls-back-silently
	test('a stale slug lands on a dashboard, not on a 404', async ({ browser }) => {
		const { context, page, response } = await openAt(browser, `${APP}/no-such-dashboard-${RUN}`)
		try {
			expect(response?.status()).toBe(200)
			await expect(page.locator('.workspace-shell')).toBeVisible()
		} finally {
			await context.close()
		}
	})

	// @e2e dashboard-deeplinking::canonical-path-passed-through-initial-state
	test('a slug address opens that dashboard and keeps its canonical path', async ({ browser }) => {
		const { context, page, response } = await openAt(browser, `${APP}/${SLUG_B}`)
		try {
			expect(response?.status()).toBe(200)
			await openDashboardSwitcher(page)
			await expect(row(page, NAME_B)).toHaveClass(/\bactive\b/)
			expect(pathOf(page)).toBe(`/apps/launchpad/${SLUG_B}`)
		} finally {
			await context.close()
		}
	})

	// @e2e dashboard-deeplinking::endpoint-returns-canonical-slug-chain
	test('the path endpoint answers the canonical slug-chain', async () => {
		const api = await apiAs(account!)
		try {
			const res = await api.get(`${API}/dashboards/${dashA.uuid}/path`)
			expect(res.status(), await res.text()).toBe(200)
			const body = await res.json()
			// The body is the bare `{path}` the sidebar reads (Views.vue
			// `res.data.path`, axios' `data` being the body), not a `data`
			// envelope, and the chain carries a leading slash
			// (DashboardTreeService::computePath).
			expect(body.path, JSON.stringify(body)).toBe(`/${SLUG_A}`)
		} finally {
			await api.dispose()
		}
	})

	// @e2e dashboard-deeplinking::switching-dashboard-pushes-history-entry
	// @e2e dashboard-deeplinking::back-button-navigates-to-previous-dashboard
	test('switching pushes one history entry, and back returns to the previous dashboard', async ({
		browser,
	}) => {
		const { context, page } = await openAt(browser, `${APP}/${SLUG_A}`)
		try {
			await openDashboardSwitcher(page)
			await expect(row(page, NAME_A)).toHaveClass(/\bactive\b/)
			// The initial mount replaces the address, it never pushes.
			expect(await pushes(page)).toBe(0)

			await row(page, NAME_B).click()
			await expect.poll(() => pathOf(page)).toBe(`/apps/launchpad/${SLUG_B}`)
			expect(await pushes(page)).toBe(1)

			await page.goBack()
			await expect.poll(() => pathOf(page)).toBe(`/apps/launchpad/${SLUG_A}`)
			// Back is a same-document navigation: the sidebar may still be open.
			if (!(await page.locator('.dashboard-switcher-sidebar.open').isVisible())) {
				await openDashboardSwitcher(page)
			}
			await expect(row(page, NAME_A)).toHaveClass(/\bactive\b/)
			expect(dashB.uuid).not.toBe(dashA.uuid)
		} finally {
			await context.close()
		}
	})

	// @e2e dashboard-deeplinking::api-health-check-still-routes-after-catch-all
	test('the health endpoint is not shadowed by the catch-all', async () => {
		const api = await apiAs(account!)
		try {
			const res = await api.get(`${API}/health`)
			expect(res.status()).toBe(200)
			expect(res.headers()['content-type'] ?? '').toContain('application/json')
		} finally {
			await api.dispose()
		}
	})
})
