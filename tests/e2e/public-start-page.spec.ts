/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The public start page (sharing-public-start-page): a signed-out visitor of
 * the plain address lands on the chosen public dashboard with a "Sign in"
 * link, and a deep link still gets the login form and its target.
 *
 * The admin picks a fresh public link of a fresh dashboard; the setting is put
 * back to what it was afterwards. Visitors run in contexts with an empty jar.
 *
 * @e2e openspec/specs/dashboard-public-share/spec.md
 */

import type { APIRequestContext, Browser, Page } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import { BASE_URL } from './support/baseUrl.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}
const STAFF = {
	user: process.env.LAUNCHPAD_E2E_SHAREE ?? 'recipient',
	pass: process.env.LAUNCHPAD_E2E_SHAREE_PASS ?? 'Recipient-e2e-A1!',
}
const API = '/index.php/apps/launchpad/api'
const NAME = `Bibliotheek Noord ${Date.now()}`

let admin: APIRequestContext
let dashboardId = 0
let before: { enabled: boolean, token: string } | null = null

async function visitor(browser: Browser): Promise<Page> {
	const context = await browser.newContext({ baseURL: BASE_URL, storageState: { cookies: [], origins: [] } })
	return context.newPage()
}

async function signIn(page: Page): Promise<void> {
	await page.locator('input[name="user"]').fill(STAFF.user)
	await page.locator('input[name="password"]').fill(STAFF.pass)
	await page.locator('button[type="submit"]').click({ noWaitAfter: true })
}

test.describe.configure({ mode: 'serial' })
test.setTimeout(120_000)

test.beforeAll(async () => {
	admin = await request.newContext({
		baseURL: BASE_URL,
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Authorization: `Basic ${Buffer.from(`${ADMIN.user}:${ADMIN.pass}`).toString('base64')}`,
		},
	})
	const current = await admin.get(`${API}/admin/public-start`)
	expect(current.status(), await current.text()).toBe(200)
	const body = await current.json()
	before = { enabled: body.enabled === true, token: String(body.token ?? '') }

	const enable = await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
	expect(enable.status(), await enable.text()).toBeLessThan(300)
	const created = await admin.post(`${API}/dashboard`, { data: { name: NAME } })
	expect(created.status(), await created.text()).toBeLessThan(300)
	const dash = (await created.json()).dashboard
	dashboardId = Number(dash.id)
	const share = await admin.post(`${API}/dashboards/${dash.uuid}/public-share`, { data: {} })
	expect(share.status(), await share.text()).toBe(201)
	const token = (await share.json()).token as string

	const on = await admin.put(`${API}/admin/public-start`, { data: { enabled: true, token } })
	expect(on.status(), await on.text()).toBe(200)
})

test.afterAll(async () => {
	if (before !== null) {
		await admin.put(`${API}/admin/public-start`, { data: before })
	}
	if (dashboardId !== 0) {
		await admin.delete(`${API}/dashboard/${dashboardId}`)
	}
	await admin.dispose()
})

// @e2e dashboard-public-share::visitor-opens-the-address
test('a signed-out visitor of the plain address lands on the start page', async ({ browser }) => {
	const page = await visitor(browser)
	try {
		await page.goto('/')
		await expect(page.getByText(NAME).first()).toBeVisible({ timeout: 30_000 })
		const signInLink = page.getByRole('link', { name: 'Sign in' })
		await expect(signInLink).toBeVisible()
		expect(await signInLink.getAttribute('href')).toMatch(/\/login\?direct=1$/)
	} finally {
		await page.context().close()
	}
})

// @e2e dashboard-public-share::staff-member-signs-in
test('"Sign in" opens the login form, which signs the staff member in', async ({ browser }) => {
	const page = await visitor(browser)
	try {
		await page.goto('/')
		await page.getByRole('link', { name: 'Sign in' }).click()
		await expect(page).toHaveURL(/\/login\?direct=1/)
		await signIn(page)
		// Where a signed-in user lands is the instance's default app.
		await expect(page).not.toHaveURL(/\/login/, { timeout: 60_000 })
		await expect(page).not.toHaveURL(/\/start/)
	} finally {
		await page.context().close()
	}
})

// @e2e dashboard-public-share::deep-link-keeps-the-login-form
test('a deep link still gets the login form and lands where it pointed', async ({ browser }) => {
	const page = await visitor(browser)
	try {
		await page.goto('/index.php/apps/files/')
		await expect(page.locator('input[name="user"]')).toBeVisible({ timeout: 30_000 })
		expect(page.url()).toContain('redirect_url=')
		await signIn(page)
		await expect(page).toHaveURL(/\/apps\/files/, { timeout: 60_000 })
	} finally {
		await page.context().close()
	}
})
