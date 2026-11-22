/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Helpers for the cross-widget filter e2e specs
 * (drill-down-cross-widget-filter).
 *
 * The filter state of a dashboard lives in the `f` query parameter, encoded
 * exactly as `src/composables/useDashboardFilter.js` writes it: a base64url
 * (no padding) JSON list of compact clauses with the keys d, o, v, s, w, p.
 * `filterParam()` below is that encoder, so a spec can open a dashboard with
 * a known filter state (including a pinned clause) without a widget click.
 *
 * Every account is a throwaway NON-ADMIN account with its own dashboard, which
 * stores the nl-administrative hierarchy, logged in through `loginAs()` from an
 * empty storage state. API calls go through `contextFor()`, so they act as
 * that account and never as the admin whose session the config stores.
 */

import type { APIRequestContext, Browser, BrowserContext, Page } from '@playwright/test'

import { expect } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from '../fixtures/secondary-user.ts'
import { NL_ADMINISTRATIVE, storeHierarchies } from './hierarchies.ts'
import { assertActingAs, contextFor } from './principals.ts'

export const API = '/index.php/apps/launchpad/api'
export const OBJECTS = '/index.php/apps/openregister/api/objects/launchpad'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

export type Account = { username: string; password: string }

export type Clause = {
	dimension: string
	operator: 'eq' | 'in' | 'notNull' | string
	value?: unknown
	source?: string
	sourceWidgetId?: string
	pinned?: boolean
}

export type SeededUser = Account & { dashboardId: number; dashboardUuid: string }

/**
 * An API context as the administrator, or as the given account, that does
 * not ride the admin's stored session (see `contextFor`).
 *
 * @param account the account; the administrator when omitted.
 * @return the context; the caller disposes it.
 */
export async function apiAs(account?: Account): Promise<APIRequestContext> {
	// Without both, the context carries the config's admin cookie and never
	// sends the account's credentials: see the header of principals.ts.
	return request.newContext({
		baseURL: BASE_URL,
		storageState: undefined,
		httpCredentials: account
			? { username: account.username, password: account.password, send: 'always' }
			: { username: ADMIN.user, password: ADMIN.pass, send: 'always' },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
}

/**
 * Change instance settings as the administrator.
 *
 * @param data the settings to change.
 * @return nothing.
 */
export async function putAdminSettings(data: Record<string, unknown>): Promise<void> {
	const admin = await apiAs()
	try {
		const res = await admin.put(`${API}/admin/settings`, { data })
		expect(res.status(), await res.text()).toBeLessThan(300)
	} finally {
		await admin.dispose()
	}
}

/**
 * A throwaway non-admin account with its own active dashboard.
 *
 * @param prefix the username prefix.
 * @return the account and its dashboard.
 */
export async function provisionUserWithDashboard(prefix: string): Promise<SeededUser> {
	await putAdminSettings({ allowUserDash: true })
	const account = await provisionThrowawayUser(prefix)
	const api = await apiAs(account)
	try {
		// The account's first request sets up its home, which can take a while.
		await assertActingAs(api, account.username)
		const created = await api.post(`${API}/dashboard`, {
			data: { name: `E2E filters ${Date.now()}` },
			timeout: 60_000,
		})
		expect(created.status(), await created.text()).toBeLessThan(300)
		const body = await created.json()
		const dash = body.dashboard ?? body.data?.dashboard ?? body
		const activate = await api.post(`${API}/dashboard/${dash.id}/activate`)
		expect(activate.status(), await activate.text()).toBeLessThan(300)
		const preference = await api.post(`${API}/dashboards/active`, {
			data: { uuid: dash.uuid },
		})
		expect(preference.status(), await preference.text()).toBeLessThan(300)
		// The dashboard joins widgets on the stored nl-administrative hierarchy (REQ-CWF-011).
		await storeHierarchies(api, Number(dash.id), [NL_ADMINISTRATIVE])
		return { ...account, dashboardId: Number(dash.id), dashboardUuid: dash.uuid }
	} finally {
		await api.dispose()
	}
}

/**
 * Remove a throwaway account (its dashboards go with it).
 *
 * @param account the account, or null when provisioning failed.
 * @return nothing.
 */
export async function removeUser(account: Account | null): Promise<void> {
	if (account) {
		await deprovisionUser(account.username)
	}
}

/**
 * The `f` parameter for a list of clauses, as useDashboardFilter writes it.
 *
 * @param clauses the clauses.
 * @return the base64url value.
 */
export function filterParam(clauses: Clause[]): string {
	const compact = clauses.map((clause) => {
		const short: Record<string, unknown> = { d: clause.dimension, o: clause.operator }
		if (clause.operator !== 'notNull') {
			short.v = clause.value
		}
		if (clause.source) {
			short.s = clause.source
		}
		if (clause.sourceWidgetId) {
			short.w = clause.sourceWidgetId
		}
		if (clause.pinned === true) {
			short.p = true
		}
		return short
	})
	return Buffer.from(JSON.stringify(compact), 'utf8')
		.toString('base64')
		.replace(/\+/g, '-')
		.replace(/\//g, '_')
		.replace(/=+$/, '')
}

/**
 * The clauses an `f` value holds.
 *
 * @param value the base64url value.
 * @return the compact clauses.
 */
export function decodeFilterParam(value: string): Array<Record<string, unknown>> {
	const base64 = value.replace(/-/g, '+').replace(/_/g, '/')
	return JSON.parse(Buffer.from(base64, 'base64').toString('utf8'))
}

/**
 * Log in and open LaunchPad on the account's dashboard, optionally filtered.
 *
 * @param browser the Playwright browser.
 * @param account the account.
 * @param clauses the filter to open with; none when omitted.
 * @return the context and page; the caller closes the context.
 */
export async function openFiltered(
	browser: Browser,
	account: Account,
	clauses: Clause[] = [],
): Promise<{ context: BrowserContext; page: Page }> {
	const { context, page } = await loginAs(browser, account.username, account.password)
	await gotoFiltered(page, clauses)
	return { context, page }
}

/**
 * Open LaunchPad in an existing page, optionally filtered.
 *
 * @param page the page.
 * @param clauses the filter to open with.
 * @return nothing.
 */
export async function gotoFiltered(page: Page, clauses: Clause[] = []): Promise<void> {
	const query = clauses.length > 0 ? `?f=${filterParam(clauses)}` : ''
	await page.goto(`/index.php/apps/launchpad${query}`)
	await expect(page.locator('[data-testid="launchpad-more-menu"]')).toBeVisible({
		timeout: 30_000,
	})
}

/**
 * The `f` parameter of the page's current URL, or null.
 *
 * @param page the page.
 * @return the value or null.
 */
export function currentFilterParam(page: Page): string | null {
	return new URL(page.url()).searchParams.get('f')
}

/**
 * The chip of one dimension in the active-filters panel.
 *
 * @param page the page.
 * @param dimension the dimension.
 * @return the chip locator.
 */
export function chip(page: Page, dimension: string) {
	return page.locator(`[data-testid="active-filters"] [data-dimension="${dimension}"]`)
}
