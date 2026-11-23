/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * An app tile shows one app the viewer may open (app-tile-widget
 * REQ-APPTILE-002): a tile for Files is a square link named "Files" with no
 * visible text and no card header, and a tile for an app outside the viewer's
 * app menu says the app is not available instead of linking to it.
 *
 * Gate-19 @e2e traceability:
 *   @e2e app-tile-widget::a-shared-dashboard-with-an-app-the-viewer-may-not-open
 *
 * @spec openspec/changes/app-tile-widget/specs/app-tile-widget/spec.md#requirement-an-app-tile-shows-one-app-the-viewer-may-open-req-apptile-002
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { contextFor } from './support/principals.ts'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

const API = '/index.php/apps/launchpad/api'

type Account = { username: string; password: string }

let account: Account | null = null

/**
 * An API context as the administrator, or as the given account.
 *
 * @param who the account; the administrator when omitted.
 * @return the context; the caller disposes it.
 */
async function apiAs(who?: Account): Promise<APIRequestContext> {
	return contextFor(who?.username ?? ADMIN.user, who?.password ?? ADMIN.pass)
}

test.beforeAll(async () => {
	const admin = await apiAs()
	try {
		const enable = await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
		expect(enable.status(), await enable.text()).toBeLessThan(300)
	} finally {
		await admin.dispose()
	}

	account = await provisionThrowawayUser('e2e-app-tile')
	const api = await apiAs(account)
	try {
		const created = await api.post(`${API}/dashboard`, { data: { name: 'E2E app tiles', widgets: 'none' } })
		expect(created.status(), await created.text()).toBeLessThan(300)
		const body = await created.json()
		const dash = body.dashboard ?? body.data?.dashboard ?? body
		for (const [index, appId] of ['files', 'e2e_not_an_app'].entries()) {
			const widget = await api.post(`${API}/dashboard/${dash.id}/widgets`, {
				data: { widgetId: 'app-tile', gridX: index * 2, gridY: 0, gridWidth: 2, gridHeight: 4, content: { appId, colour: 'red', showCount: true } },
			})
			expect(widget.status(), await widget.text()).toBeLessThan(300)
		}
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

// @e2e app-tile-widget::a-shared-dashboard-with-an-app-the-viewer-may-not-open
test('an app tile links to an app in the viewer menu and names an app outside it as not available', async ({ browser }) => {
	const { context, page } = await loginAs(browser, account!.username, account!.password)
	try {
		await page.goto('/index.php/apps/launchpad')
		const files = page.locator('[data-testid="cn-app-tile"][href*="/apps/files"]')
		await expect(files).toBeVisible({ timeout: 30_000 })
		await expect(files).toHaveAttribute('aria-label', /\S/)
		await expect(files).toHaveAttribute('title', /\S/)
		expect((await files.innerText()).replace(/\d+\+?/, '').trim()).toBe('')
		const style = await files.evaluate((el) => getComputedStyle(el).backgroundColor)
		expect(style).toBe('rgb(204, 0, 0)')

		await expect(page.locator('[data-testid="cn-app-tile-widget-missing"]')).toHaveText('This app is not available to you')
		await expect(page.locator('[data-testid="cn-app-tile"]')).toHaveCount(1)
	} finally {
		await context.close()
	}
})
