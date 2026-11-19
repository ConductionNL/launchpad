/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright end-to-end test for discovering the server's apps
 * (launcher-discover-server-services, task 5): Deck is listed under
 * "Discovered on this server", one click places it as a tile that opens
 * /apps/deck/, and the next time the add flow opens it is marked
 * "On this dashboard". LaunchPad itself is never listed.
 *
 * Deck is enabled through the OCS apps API first; on an instance where Deck
 * is not installed the test is skipped with that reason.
 *
 * The add flow is opened through "More" → "Add app" (launchpad#1300). The
 * account is a throwaway non-admin account logged in from an empty storage
 * state.
 *
 * Gate-19 @e2e traceability:
 *   @e2e discover-server-services::deck-discovered-and-placed
 *
 * @spec openspec/changes/launcher-discover-server-services/specs/tiles/spec.md
 */

import type { APIRequestContext, Locator, Page } from '@playwright/test'

import { expect, request, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { BASE_URL } from './support/baseUrl.ts'
import { MORE_MENU } from './support/dashboardSwitcher.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

const API = '/index.php/apps/launchpad/api'

type Account = { username: string; password: string }

let account: Account | null = null
let deckEnabled = false

/**
 * An API context as the administrator, or as the given account.
 *
 * @param who the account; the administrator when omitted.
 * @return the context; the caller disposes it.
 */
async function apiAs(who?: Account): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: BASE_URL,
		httpCredentials: who
			? { username: who.username, password: who.password }
			: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
}

/**
 * Open the add flow ("More" → "Add app") and return the picker.
 *
 * @param page the page.
 * @return the picker dialog.
 */
async function openAddFlow(page: Page): Promise<Locator> {
	await page.goto('/index.php/apps/launchpad')
	const more = page.locator(MORE_MENU)
	await expect(more).toBeVisible({ timeout: 30_000 })
	await more.locator('[data-testid="cn-grouped-menu-trigger"]').click()
	await page.locator('[data-testid="cn-grouped-menu-item-add-app"]').click()
	const picker = page.getByRole('dialog', { name: /add widget/i }).first()
	await expect(picker).toBeVisible({ timeout: 10_000 })
	return picker
}

test.beforeAll(async () => {
	const admin = await apiAs()
	try {
		const enable = await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
		expect(enable.status(), await enable.text()).toBeLessThan(300)
		const deck = await admin.post('/ocs/v1.php/cloud/apps/deck')
		deckEnabled = deck.ok()
	} finally {
		await admin.dispose()
	}

	account = await provisionThrowawayUser('e2e-discover')
	const api = await apiAs(account)
	try {
		const created = await api.post(`${API}/dashboard`, { data: { name: 'E2E Mijn werkplek' } })
		expect(created.status(), await created.text()).toBeLessThan(300)
		const body = await created.json()
		const dash = body.dashboard ?? body.data?.dashboard ?? body
		const activate = await api.post(`${API}/dashboard/${dash.id}/activate`)
		expect(activate.status(), await activate.text()).toBeLessThan(300)
		const preference = await api.post(`${API}/dashboards/active`, { data: { uuid: dash.uuid } })
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

// @e2e discover-server-services::deck-discovered-and-placed
test('Deck is discovered, placed in one click and then marked as on this dashboard', async ({
	browser,
}) => {
	// The server's own list, as the account sees it: Deck in, LaunchPad out.
	const api = await apiAs(account!)
	let apps: Array<{ appId: string; name: string; href: string }>
	try {
		const res = await api.get(`${API}/tiles/discover`)
		expect(res.status(), await res.text()).toBe(200)
		apps = (await res.json()).apps ?? []
	} finally {
		await api.dispose()
	}
	expect(apps.map((app) => app.appId)).not.toContain('launchpad')
	const deck = apps.find((app) => app.appId === 'deck')
	test.skip(!deckEnabled && !deck, 'Deck is not installed on this instance')
	expect(deck, 'Deck is enabled for everyone, so it is discovered').toBeDefined()

	const { context, page } = await loginAs(browser, account!.username, account!.password)
	try {
		const picker = await openAddFlow(page)
		const section = picker.locator('[data-testid="widget-picker-discovered"]')
		await expect(section).toContainText('Discovered on this server')
		const entry = section.getByRole('button', { name: new RegExp(`^\\s*${deck!.name}`) })
		await expect(entry).toBeVisible()
		await expect(entry.locator('img')).toHaveAttribute('src', /.+/)
		await expect(entry).not.toContainText('On this dashboard')

		const placed = page.waitForResponse((res) =>
			/\/api\/dashboard\/\d+\/tile$/.test(res.url()) && res.request().method() === 'POST',
		)
		await entry.click()
		const response = await placed
		expect(response.status(), await response.text()).toBeLessThan(300)
		expect(response.request().postDataJSON()).toMatchObject({
			title: deck!.name,
			linkType: 'app',
			linkValue: 'deck',
		})
		await page.keyboard.press('Escape')

		const tile = page.locator('.launchpad-grid-item').filter({ hasText: deck!.name })
		await expect(tile).toBeVisible({ timeout: 10_000 })
		await expect(tile.locator('a').first()).toHaveAttribute('href', /\/apps\/deck\/?/)

		// The next time the add flow opens, Deck is marked.
		const again = await openAddFlow(page)
		await expect(
			again
				.locator('[data-testid="widget-picker-discovered"]')
				.getByRole('button', { name: new RegExp(`^\\s*${deck!.name}`) }),
		).toContainText('On this dashboard')
	} finally {
		await context.close()
	}
})
