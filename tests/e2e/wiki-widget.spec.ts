/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Wiki widget (wiki-widget REQ-WIKI-001..003) on a throwaway account's
 * own dashboard. OpenRegister's XWiki search is answered by a route stub, so
 * the spec does not depend on an XWiki instance; the favourites go through
 * the real preference store, so a reload proves they are kept. One test lets
 * the real search answer and accepts only the not-connected state or a list,
 * never a broken widget.
 *
 * Gate-19 @e2e traceability:
 *   @e2e wiki-widget::pieters-wiki
 *   @e2e wiki-widget::the-wiki-is-not-connected
 *   @e2e wiki-widget::starring-a-page
 *   @e2e wiki-widget::wiki-of-the-team
 *   @e2e exclude wiki-widget::no-openregister the shared e2e instance always runs OpenRegister; the filter is unit-tested in widgetRegistry.spec.js and PageControllerHelpersTest
 *
 * @spec openspec/changes/wiki-widget/specs/wiki-widget/spec.md
 */

import type { APIRequestContext, Page } from '@playwright/test'

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
const SEARCH = '**/apps/openregister/api/integrations/xwiki/search**'

const PAGES = [
	{ reference: 'Kennisbank.Termijnen', title: 'Termijnen Omgevingswet', space: 'Kennisbank Zuiddrecht', url: 'https://wiki.zuiddrecht.example/termijnen' },
	{ reference: 'Vergunningen.Kap', title: 'Werkinstructie kapvergunningen', space: 'Vergunningen', url: 'https://wiki.zuiddrecht.example/kap' },
	{ reference: 'Vergunningen.Leges', title: 'Legesverordening 2026', space: 'Vergunningen', url: 'https://wiki.zuiddrecht.example/leges' },
]

type Account = { username: string; password: string }

let account: Account | null = null
let dashboardId = 0

/**
 * An API context as the administrator, or as the given account.
 *
 * @param who the account; the administrator when omitted.
 * @return the context; the caller disposes it.
 */
async function apiAs(who?: Account): Promise<APIRequestContext> {
	return contextFor(who?.username ?? ADMIN.user, who?.password ?? ADMIN.pass)
}

/**
 * Put one Wiki widget on the account's dashboard, replacing any before it.
 *
 * @param content the widget content.
 */
async function placeWiki(content: Record<string, unknown>): Promise<void> {
	const api = await apiAs(account!)
	try {
		const current = await api.get(`${API}/dashboard/${dashboardId}`)
		const body = await current.json()
		for (const placement of body.placements ?? body.data?.placements ?? []) {
			await api.delete(`${API}/widgets/${placement.id}`)
		}
		const widget = await api.post(`${API}/dashboard/${dashboardId}/widgets`, {
			data: { widgetId: 'wiki-pages', gridX: 0, gridY: 0, gridWidth: 6, gridHeight: 8, content },
		})
		expect(widget.status(), await widget.text()).toBeLessThan(300)
	} finally {
		await api.dispose()
	}
}

/**
 * Answer OpenRegister's wiki search with the demo pages, filtered by the query.
 *
 * @param page the page.
 */
async function stubWiki(page: Page): Promise<void> {
	await page.route(SEARCH, async (route) => {
		const q = (new URL(route.request().url()).searchParams.get('q') ?? '').toLowerCase()
		const results = PAGES.filter((entry) => q === '' || entry.title.toLowerCase().includes(q))
		await route.fulfill({ json: { results, total: results.length, limit: 25, offset: 0 } })
	})
}

test.beforeAll(async () => {
	const admin = await apiAs()
	try {
		const enable = await admin.put(`${API}/admin/settings`, { data: { allowUserDash: true } })
		expect(enable.status(), await enable.text()).toBeLessThan(300)
	} finally {
		await admin.dispose()
	}

	account = await provisionThrowawayUser('e2e-wiki')
	const api = await apiAs(account)
	try {
		const created = await api.post(`${API}/dashboard`, { data: { name: 'E2E wiki', widgets: 'none' } })
		expect(created.status(), await created.text()).toBeLessThan(300)
		const body = await created.json()
		const dash = body.dashboard ?? body.data?.dashboard ?? body
		dashboardId = Number(dash.id)
		const activate = await api.post(`${API}/dashboard/${dash.id}/activate`)
		expect(activate.status(), await activate.text()).toBeLessThan(300)
		const preference = await api.post(`${API}/dashboards/active`, { data: { uuid: dash.uuid } })
		expect(preference.status(), await preference.text()).toBeLessThan(300)
		await api.put(`${API}/preferences/wiki-favourites`, { data: { value: '' } })
	} finally {
		await api.dispose()
	}
})

test.afterAll(async () => {
	if (account) {
		await deprovisionUser(account.username)
	}
})

// @e2e wiki-widget::pieters-wiki
// @e2e wiki-widget::starring-a-page
test('the wiki lists recent pages as links, searches, and keeps a starred page across a reload', async ({ browser }) => {
	await placeWiki({ space: '', limit: 3 })
	const { context, page } = await loginAs(browser, account!.username, account!.password)
	try {
		await stubWiki(page)
		await page.goto('/index.php/apps/launchpad')
		const widget = page.locator('[data-testid="wiki-pages-widget"]')
		const recent = widget.locator('[data-testid="wiki-pages-recent"]')
		await expect(recent.getByRole('link', { name: 'Termijnen Omgevingswet' })).toHaveAttribute('href', PAGES[0].url)
		await expect(recent.getByRole('link', { name: 'Termijnen Omgevingswet' })).toHaveAttribute('target', '_blank')
		await expect(widget.locator('[data-testid="wiki-pages-favourites"]')).toContainText('Star a page to keep it here')

		await widget.getByRole('searchbox', { name: 'Search the wiki' }).fill('leges')
		const results = widget.locator('[data-testid="wiki-pages-results"]')
		await expect(results.getByRole('link')).toHaveText(['Legesverordening 2026'])
		await widget.getByRole('searchbox', { name: 'Search the wiki' }).fill('')

		await recent.getByRole('button', { name: 'Add Termijnen Omgevingswet to favourites' }).click()
		await expect(widget.locator('[data-testid="wiki-pages-favourites"]').getByRole('link')).toHaveText(['Termijnen Omgevingswet'])

		await page.reload()
		await expect(page.locator('[data-testid="wiki-pages-favourites"]').getByRole('link')).toHaveText(['Termijnen Omgevingswet'])
	} finally {
		await context.close()
	}
})

// @e2e wiki-widget::wiki-of-the-team
test('a team wiki shows only the pages of its space', async ({ browser }) => {
	await placeWiki({ space: 'Vergunningen', limit: 3 })
	const { context, page } = await loginAs(browser, account!.username, account!.password)
	try {
		await stubWiki(page)
		await page.goto('/index.php/apps/launchpad')
		const recent = page.locator('[data-testid="wiki-pages-recent"]')
		await expect(recent.getByRole('link')).toHaveText(['Werkinstructie kapvergunningen', 'Legesverordening 2026'])
	} finally {
		await context.close()
	}
})

// @e2e wiki-widget::the-wiki-is-not-connected
test('without an XWiki source the widget says the wiki is not connected', async ({ browser }) => {
	await placeWiki({ space: '', limit: 3 })
	const { context, page } = await loginAs(browser, account!.username, account!.password)
	try {
		await page.route(SEARCH, (route) => route.fulfill({ status: 503, json: { error: 'xWiki source is not available', details: { cause: 'openconnector-down' } } }))
		await page.goto('/index.php/apps/launchpad')
		await expect(page.locator('[data-testid="wiki-pages-not-connected"]')).toHaveText('The wiki is not connected yet. Ask your administrator to connect XWiki.')
		await page.unroute(SEARCH)

		// CONTROL: the real search answers either a list or the same explanation, never a broken widget.
		await page.reload()
		const widget = page.locator('[data-testid="wiki-pages-widget"]')
		await expect(widget.locator('[data-testid="wiki-pages-recent"], [data-testid="wiki-pages-not-connected"]').first()).toBeVisible({ timeout: 15_000 })
	} finally {
		await context.close()
	}
})
