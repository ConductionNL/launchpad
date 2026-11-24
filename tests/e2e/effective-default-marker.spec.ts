/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end tests for the effective-default marker in the
 * dashboard switcher (openspec/specs/effective-default-marker), written for
 * e2e-coverage-programme task 3.19.
 *
 * A throwaway non-admin account gets two personal dashboards, A and B, and
 * pins A through the API (seeding only). Moving the pin to B goes through
 * the row's own cog menu, the way a person does it.
 *
 * Gate-19 @e2e traceability:
 *   @e2e effective-default-marker::star-renders-on-the-effective-default-row
 *   @e2e effective-default-marker::pin-wins-over-group-fallback
 *   @e2e effective-default-marker::marker-carries-tooltip-and-aria-label
 *   @e2e effective-default-marker::marker-moves-when-pin-changes
 *   @e2e effective-default-marker::marker-color-uses-theme-variable
 *
 * @spec openspec/specs/effective-default-marker/spec.md
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

const API = '/index.php/apps/launchpad/api'
const RUN = Date.now()
const NAME_A = `E2E Marker A ${RUN}`
const NAME_B = `E2E Marker B ${RUN}`
const MARKER = '.dashboard-switcher-sidebar__default-marker'
const ROW = '.dashboard-switcher-sidebar__item'

type Account = { username: string; password: string }
type Dashboard = { id: number; uuid: string }

let account: Account | null = null
let dashA: Dashboard

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
 * Create a personal dashboard for the account.
 *
 * @param api the account's context.
 * @param name the name.
 * @return its id and uuid.
 */
async function createDashboard(api: APIRequestContext, name: string): Promise<Dashboard> {
	const created = await api.post(`${API}/dashboard`, { data: { name } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const body = await created.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	return { id: Number(dash.id), uuid: dash.uuid }
}

/**
 * Pin a dashboard as the account's default (seeding only).
 *
 * @param uuid the dashboard.
 * @return nothing.
 */
async function pin(uuid: string): Promise<void> {
	const api = await apiAs(account!)
	try {
		const res = await api.post(`${API}/dashboards/default`, { data: { uuid } })
		expect(res.status(), await res.text()).toBeLessThan(300)
	} finally {
		await api.dispose()
	}
}

/**
 * Log in, open LaunchPad and the full switcher.
 *
 * @param browser the browser.
 * @return the context and page.
 */
async function openSwitcher(browser: Browser) {
	const session = await loginAs(browser, account!.username, account!.password)
	await session.page.goto('/index.php/apps/launchpad')
	await openDashboardSwitcher(session.page)
	return session
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

test.beforeAll(async () => {
	const admin = await apiAs()
	try {
		const enable = await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
		expect(enable.status(), await enable.text()).toBeLessThan(300)
	} finally {
		await admin.dispose()
	}
	account = await provisionThrowawayUser('e2e-marker')
	const api = await apiAs(account)
	try {
		dashA = await createDashboard(api, NAME_A)
		await createDashboard(api, NAME_B)
		const preference = await api.post(`${API}/dashboards/active`, { data: { uuid: dashA.uuid } })
		expect(preference.status(), await preference.text()).toBeLessThan(300)
	} finally {
		await api.dispose()
	}
})

test.afterAll(async () => {
	if (account) {
		await deprovisionUser(account.username)
	}
})

test.describe.serial('effective-default marker (REQ-EDM-001..005)', () => {
	test.beforeEach(async () => {
		await pin(dashA.uuid)
	})

	// @e2e effective-default-marker::star-renders-on-the-effective-default-row
	test('the star renders on the effective-default row only', async ({ browser }) => {
		const { context, page } = await openSwitcher(browser)
		try {
			const marker = row(page, NAME_A).locator(MARKER)
			await expect(marker).toHaveCount(1)
			await expect(marker.locator('svg')).toBeVisible()
			await expect(row(page, NAME_B).locator(MARKER)).toHaveCount(0)
			await expect(page.locator(`${ROW} ${MARKER}`)).toHaveCount(1)
		} finally {
			await context.close()
		}
	})

	// @e2e effective-default-marker::pin-wins-over-group-fallback
	test('the pin wins over a group fallback', async ({ browser }) => {
		const { context, page } = await openSwitcher(browser)
		try {
			// The instance-wide default dashboard (SeedDefaultDashboard) is a
			// group dashboard every account sees: the fallback the pin must beat.
			// It is provisioned on install and after every upgrade, so its absence
			// is a failure, not a reason to skip: a skip here would let the
			// scenario pass without the fallback it is about ever being on screen.
			const groupRows = page.locator(`${ROW}[data-source="group"], ${ROW}[data-source="default"]`)
			await expect(
				groupRows.first(),
				'a group (or default-group) dashboard is visible to the account, as SeedDefaultDashboard guarantees',
			).toBeVisible()
			await expect(row(page, NAME_A).locator(MARKER)).toHaveCount(1)
			await expect(groupRows.locator(MARKER)).toHaveCount(0)
			await expect(page.locator(`${ROW} ${MARKER}`)).toHaveCount(1)
		} finally {
			await context.close()
		}
	})

	// @e2e effective-default-marker::marker-carries-tooltip-and-aria-label
	test('the marker carries its tooltip and aria-label', async ({ browser }) => {
		const { context, page } = await openSwitcher(browser)
		try {
			const marker = row(page, NAME_A).locator(MARKER)
			await expect(marker).toHaveAttribute(
				'title',
				'Default dashboard — opens automatically when you visit LaunchPad',
			)
			await expect(marker).toHaveAttribute('aria-label', 'Default dashboard')
		} finally {
			await context.close()
		}
	})

	// @e2e effective-default-marker::marker-moves-when-pin-changes
	test('pinning another dashboard through its cog menu moves the marker without a reload', async ({
		browser,
	}) => {
		const { context, page } = await openSwitcher(browser)
		try {
			await expect(row(page, NAME_A).locator(MARKER)).toHaveCount(1)
			// A reload would drop this flag.
			await page.evaluate(() => {
				(window as unknown as { __noReload: boolean }).__noReload = true
			})

			await row(page, NAME_B).locator('.dashboard-row-actions button').first().click()
			await page.locator('[data-testid="cog-set-default"]').filter({ hasText: 'Set as default' }).click()

			await expect(row(page, NAME_B).locator(MARKER)).toHaveCount(1)
			await expect(row(page, NAME_A).locator(MARKER)).toHaveCount(0)
			expect(
				await page.evaluate(() => (window as unknown as { __noReload?: boolean }).__noReload === true),
			).toBe(true)
		} finally {
			await context.close()
		}
	})

	// @e2e effective-default-marker::marker-color-uses-theme-variable
	test('the marker colour is the theme warning colour', async ({ browser }) => {
		const { context, page } = await openSwitcher(browser)
		try {
			const marker = row(page, NAME_A).locator(MARKER)
			await expect(marker).toHaveCount(1)
			const [actual, expected] = await marker.evaluate((element) => {
				const probe = document.createElement('span')
				probe.style.color = 'var(--color-warning, #e9a800)'
				element.parentElement!.appendChild(probe)
				const colours = [getComputedStyle(element).color, getComputedStyle(probe).color]
				probe.remove()
				return colours
			})
			expect(actual).toBe(expected)
			// And it follows a theme override of the variable.
			const overridden = await marker.evaluate((element) => {
				element.parentElement!.style.setProperty('--color-warning', 'rgb(1, 2, 3)')
				const colour = getComputedStyle(element).color
				element.parentElement!.style.removeProperty('--color-warning')
				return colour
			})
			expect(overridden).toBe('rgb(1, 2, 3)')
		} finally {
			await context.close()
		}
	})
})
