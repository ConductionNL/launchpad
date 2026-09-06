/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared e2e fixture helpers for driving a SECOND authenticated Nextcloud
 * user session alongside the admin-only storageState from
 * `global-setup.ts` — either a throwaway zero-dashboard account (for the
 * empty-state scenarios) or a pre-seeded recipient account (for the
 * dashboard-sharing recipient-side scenario).
 *
 * Every helper here goes through the real, authorised Nextcloud OCS
 * provisioning API and the real login form — the same "seed through the
 * real API" pattern as `role-feature-permissions.ts` and
 * `acknowledgements.ts` — rather than faking session state.
 */

import type {
	APIRequestContext,
	Browser,
	BrowserContext,
	Page,
} from '@playwright/test'

import { request as pwRequest } from '@playwright/test'
import { BASE_URL as BASE } from '../support/baseUrl.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

/** Build an admin API context (HTTP Basic auth + OCS header). */
async function adminApi(): Promise<APIRequestContext> {
	return pwRequest.newContext({
		baseURL: BASE,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
}

/**
 * Provision a fresh, throwaway Nextcloud account via the OCS provisioning
 * API (`POST /ocs/v1.php/cloud/users`).
 *
 * ⚠️ This used to say a brand-new account has zero dashboards "personal,
 * group, or default", and that it was therefore how the empty-state
 * scenarios reached their state. That is NO LONGER TRUE: #361 (c9e58089)
 * added `SeedDefaultDashboard`, which provisions an INSTANCE-WIDE,
 * group-shared dashboard on install and after every upgrade. A fresh
 * account resolves to it like any other, so this fixture can no longer
 * produce an empty dashboard list, and three specs that relied on it had
 * to be revised. Caller MUST call {@link deprovisionUser} afterwards
 * (e.g. in `test.afterEach`) so throwaway accounts don't accumulate on
 * the shared dev instance.
 *
 * @param {string} [prefix] Username prefix; a timestamp+random suffix is
 *   appended so parallel/repeated runs never collide.
 * @return {Promise<{username: string, password: string}>} the new account's credentials.
 */
export async function provisionThrowawayUser(
	prefix = 'e2e-throwaway',
): Promise<{ username: string; password: string }> {
	const username = `${prefix}-${Date.now()}-${Math.floor(Math.random() * 10_000)}`
	const password = `Probe-${Math.random().toString(36).slice(2)}A1!`
	const api = await adminApi()
	try {
		const res = await api.post('/ocs/v1.php/cloud/users', {
			form: { userid: username, password },
		})
		if (!res.ok()) {
			throw new Error(
				`provisionThrowawayUser(${username}) failed: ${res.status()}`,
			)
		}
	} finally {
		await api.dispose()
	}
	return { username, password }
}

/**
 * Delete a throwaway account created by {@link provisionThrowawayUser}.
 * Tolerant of an already-gone account (e.g. a previous run's cleanup step
 * failed) so it is always safe to call from `afterEach`/`finally`.
 *
 * @param {string} username The account to remove.
 * @return {Promise<void>}
 */
export async function deprovisionUser(username: string): Promise<void> {
	const api = await adminApi()
	try {
		await api.delete(`/ocs/v1.php/cloud/users/${encodeURIComponent(username)}`)
	} finally {
		await api.dispose()
	}
}

/**
 * Set (or reset) a KNOWN password for an EXISTING account via the OCS
 * provisioning API, using admin credentials. Used for the pre-seeded
 * `recipient` fixture account, whose original password is not known to
 * the test suite — idempotent and safe to call every run.
 *
 * @param {string} username The existing account.
 * @param {string} password The password to set.
 * @return {Promise<void>}
 */
export async function ensureKnownPassword(
	username: string,
	password: string,
): Promise<void> {
	const api = await adminApi()
	try {
		const res = await api.put(
			`/ocs/v1.php/cloud/users/${encodeURIComponent(username)}`,
			{
				form: { key: 'password', value: password },
			},
		)
		if (!res.ok()) {
			throw new Error(
				`ensureKnownPassword(${username}) failed: ${res.status()}`,
			)
		}
	} finally {
		await api.dispose()
	}
}

/**
 * Log in as `username`/`password` in a genuinely UNAUTHENTICATED browser
 * context (mirrors `global-setup.ts`'s admin login flow) rather than reusing
 * the shared admin session. Caller MUST `context.close()` when done.
 *
 * `storageState: undefined` is passed EXPLICITLY and is load-bearing:
 * `browser.newContext()` otherwise inherits playwright.config's top-level
 * `use.storageState` (the shared admin auth cookie), so a context created
 * without this override is still authenticated as admin. Navigating to
 * `/index.php/login` in that state redirects straight past the login form to
 * the dashboard — `input[name="user"]` never renders and every caller of
 * this helper times out on the very first `fill()`, which is exactly what
 * happened before this override was added (confirmed: deterministic across
 * repeat runs, not environment contention).
 *
 * @param {Browser} browser Playwright `browser` fixture.
 * @param {string} username The account to log in as.
 * @param {string} password Its password.
 * @return {Promise<{context: BrowserContext, page: Page}>} the fresh, authenticated context + page.
 */
export async function loginAs(
	browser: Browser,
	username: string,
	password: string,
): Promise<{ context: BrowserContext; page: Page }> {
	const context = await browser.newContext({
		baseURL: BASE,
		storageState: undefined,
	})
	const page = await context.newPage()
	await page.goto('/index.php/login')
	await page.locator('input[name="user"]').fill(username)
	await page.locator('input[name="password"]').fill(password)

	// `noWaitAfter` is load-bearing, and is NOT a timeout increase.
	//
	// By default `click()` also waits for any navigation it schedules, and
	// that wait is bounded by playwright.config's `actionTimeout` (10s) —
	// not by `navigationTimeout` (60s). The Nextcloud login POST + redirect
	// + Vue hydration routinely exceeds 10s on a loaded box, so the click
	// failed with "waiting for scheduled navigations to finish" even though
	// the login had SUCCEEDED: the saved page snapshot at the moment of
	// failure shows a fully authenticated page (`#app-dashboard`, the
	// Applications nav, the Settings menu), not the login form.
	//
	// So the premise was sound and only the signal was wrong. The correct
	// signal already existed on the next line — an explicit
	// `waitForSelector('#header')` with its own 45s budget. Handing the
	// navigation wait to that explicit check instead of to `click()`'s
	// implicit one removes the redundant short wait without weakening
	// anything: if the login genuinely fails, `#header` never appears and
	// this still fails, just for the real reason.
	await page.locator('button[type="submit"]').first().click({ noWaitAfter: true })
	await page.waitForSelector('#header, header.header', { timeout: 45_000 })
	return { context, page }
}
