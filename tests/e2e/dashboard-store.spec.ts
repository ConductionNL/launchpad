/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dashboard store, end to end: the Store page, the registry form on
 * Beheer ▸ Sharing, and the admin gate on both.
 *
 * 🔴 WHAT THIS FILE DOES NOT PROVE, AND WHY. A SUCCESSFUL remote install needs
 * a second OpenRegister instance publishing a `dashboard-template` object. CI
 * provisions exactly one instance, so there is no registry to install from.
 * Faking one inside the suite would test the fake, so nothing here pretends a
 * template arrived. The install path is covered by
 * tests/Unit/Service/StoreServiceTest.php against the engine's signatures, and
 * here only as far as a real instance can take it: a refusal, with a reason,
 * and never a 500.
 *
 * 🔴 WHY THE STORE PAGE'S NOTE IS NOT ENOUGH ON ITS OWN. `CnStorePage` shows
 * its not-configured note WITHOUT making a request when it has no app id. So
 * the note alone cannot tell "our route answered not_configured" from "the
 * page never asked". Before this change the route did not exist at all, and
 * the page showed the UNREACHABLE note. Every UI assertion below is therefore
 * paired with the response that produced it.
 *
 * 🔴 THE NON-ADMIN IS A FRESH ACCOUNT WITH A VALID REQUEST TOKEN. A 403 is only
 * evidence of the admin gate if nothing else could have produced it. The
 * account is provisioned here, so it holds no group that could have made it an
 * admin on a warm instance, and its requests carry the page's own request
 * token, so a CSRF refusal (412) cannot pass for the gate. A positive control
 * on the login-only search route proves the session and token work.
 *
 * Every test resets the registry connection before and after it runs, so the
 * not-configured test does not depend on the order the file runs in, and the
 * suite leaves no registry URL behind for the specs that follow.
 */

import type { APIRequestContext, Page } from '@playwright/test'

import { expect, request as pwRequest, test } from '@playwright/test'
import {
	deprovisionUser,
	loginAs,
	provisionThrowawayUser,
} from './fixtures/secondary-user.ts'
import { BASE_URL as BASE } from './support/baseUrl.ts'
import { installBundleOverride } from './support/bundleOverride.ts'

const ADMIN = {
	user: process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.NC_ADMIN_PASS ?? 'admin',
}

const APP_BASE = '/apps/launchpad'
const STORE_URL = `${BASE}/index.php${APP_BASE}/store`
const SHARING_URL = `${BASE}/index.php/settings/admin/launchpad?tab=sharing`

/**
 * A registry host that never resolves. `.invalid` is reserved (RFC 6761), so
 * the engine's SSRF guard rejects it before any request leaves the instance:
 * the suite makes no outbound call, and the store reports `store_unreachable`.
 */
const UNRESOLVABLE_REGISTRY = 'https://registry.invalid/'

/** A slug the install route accepts, for probing the gate. */
const PROBE_SLUG = 'sales-overview'

/**
 * An admin API client that bypasses the browser, for setup and teardown.
 *
 * `OCS-APIRequest` satisfies Nextcloud's CSRF check for a request that
 * carries no request token, which a basic-auth client cannot have.
 *
 * @return The request context. Caller disposes it.
 */
async function adminApi(): Promise<APIRequestContext> {
	return pwRequest.newContext({
		baseURL: BASE,
		httpCredentials: { username: ADMIN.user, password: ADMIN.pass },
		extraHTTPHeaders: { 'OCS-APIRequest': 'true' },
	})
}

/**
 * Clear every registry key, so the store is unconfigured.
 *
 * @return Resolves once the server confirmed the write.
 */
async function resetRegistry(): Promise<void> {
	const api = await adminApi()
	try {
		const res = await api.put(`/index.php${APP_BASE}/api/store/config`, {
			data: { registryUrl: '', registryRegister: '', registryToken: '' },
		})
		expect(res.status(), 'the admin could reset the registry').toBe(200)
	} finally {
		await api.dispose()
	}
}

/**
 * Read the registry connection as the admin, bypassing the browser.
 *
 * @return The redacted connection.
 */
async function readRegistry(): Promise<Record<string, unknown>> {
	const api = await adminApi()
	try {
		const res = await api.get(`/index.php${APP_BASE}/api/store/config`)
		expect(res.status()).toBe(200)
		return (await res.json()) as Record<string, unknown>
	} finally {
		await api.dispose()
	}
}

/**
 * Who the browser is signed in as, read from Nextcloud rather than assumed.
 *
 * @param page The page.
 * @return The uid and admin flag.
 */
async function whoami(page: Page): Promise<{ uid: unknown; isAdmin: unknown }> {
	return page.evaluate(() => ({
		uid: (window as any).OC?.getCurrentUser?.()?.uid ?? null,
		isAdmin: (window as any).OC?.isUserAdmin?.() ?? null,
	}))
}

/**
 * Call an app route from INSIDE the page, the way the frontend would: the
 * session cookie and the page's own request token ride along, so the only
 * thing that can refuse the call is the route's own auth posture.
 *
 * @param page   The page, already on a Nextcloud page that loaded `OC`.
 * @param method HTTP verb.
 * @param path   App-relative path, e.g. `/apps/launchpad/api/store/config`.
 * @param body   Optional JSON body.
 * @return The status and raw body text.
 */
async function callFromPage(
	page: Page,
	method: string,
	path: string,
	body?: Record<string, unknown>,
): Promise<{ status: number; text: string }> {
	return page.evaluate(
		async ({ method, path, body }) => {
			const oc = (window as any).OC
			const url =
				typeof oc?.generateUrl === 'function'
					? oc.generateUrl(path)
					: `/index.php${path}`
			const res = await fetch(url, {
				method,
				headers: {
					'Content-Type': 'application/json',
					requesttoken: oc?.requestToken ?? '',
				},
				body: body === undefined ? undefined : JSON.stringify(body),
			})
			return { status: res.status, text: await res.text() }
		},
		{ method, path, body },
	)
}

/**
 * Open the Store page and return the search response that fed it.
 *
 * The listener is registered BEFORE navigating, so a fast response cannot be
 * missed and read as "no request was made".
 *
 * @param page The page.
 * @return The HTTP status and decoded body of `GET /api/store/items`.
 */
async function openStore(
	page: Page,
): Promise<{ status: number; body: Record<string, unknown> }> {
	const searched = page.waitForResponse(
		(res) =>
			res.url().includes(`${APP_BASE}/api/store/items`)
			&& res.request().method() === 'GET',
		{ timeout: 60_000 },
	)
	await page.goto(STORE_URL, { waitUntil: 'domcontentloaded' })
	const res = await searched
	await expect(page.locator('[data-testid="store-page"]')).toBeVisible({
		timeout: 60_000,
	})
	// Parsed defensively. An unrouted endpoint answers Nextcloud's HTML 404
	// page, and letting `res.json()` throw on it reports a SyntaxError instead
	// of the status assertion that names the actual defect.
	let body: Record<string, unknown>
	try {
		body = (await res.json()) as Record<string, unknown>
	} catch {
		body = {}
	}
	return { status: res.status(), body }
}

/**
 * Open Beheer ▸ Sharing and wait for the registry form to finish loading.
 *
 * @param page The page.
 */
async function openRegistryForm(page: Page): Promise<void> {
	const loaded = page.waitForResponse(
		(res) =>
			res.url().includes(`${APP_BASE}/api/store/config`)
			&& res.request().method() === 'GET',
		{ timeout: 60_000 },
	)
	await page.goto(SHARING_URL, { waitUntil: 'domcontentloaded' })
	await loaded
	await expect(
		page.locator('[data-test="dashboard-registry-settings"]'),
	).toBeVisible({ timeout: 30_000 })
	// Enabled means `load()` finished and the fields hold the server's values.
	await expect(page.locator('input[data-test="registry-url"]')).toBeEnabled()
}

test.describe('dashboard store', () => {
	test.beforeEach(async ({ page }) => {
		await installBundleOverride(page.context())
		await resetRegistry()
	})

	test.afterEach(async () => {
		// In afterEach, not in the test body, so a failing test still leaves
		// no registry URL behind for the specs that run after this file.
		await resetRegistry()
	})

	/*
	 * @e2e openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#scenario-an-unconfigured-store-shows-the-not-configured-state
	 */
	test('an unconfigured store shows the not-configured note, answered by the route', async ({
		page,
	}) => {
		const { status, body } = await openStore(page)

		// The route answered, and answered not_configured. Before this change
		// there was no route, and this read 404.
		expect(status, 'GET /api/store/items is routed').toBe(200)
		expect(body.outcome).toBe('not_configured')

		await expect(
			page.locator('[data-testid="store-not-configured"]'),
		).toBeVisible()
		await expect(page.locator('[data-testid="store-unreachable"]')).toHaveCount(
			0,
		)
		await expect(page.locator('[data-testid="store-results"]')).toHaveCount(0)
	})

	/*
	 * @e2e openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#scenario-an-install-with-no-registry-is-refused-with-a-reason
	 */
	test('an admin install with no registry is refused with a reason, not a 500', async ({
		page,
	}) => {
		await openStore(page)
		expect(
			(await whoami(page)).isAdmin,
			'the default session is the admin',
		).toBe(true)

		const res = await callFromPage(
			page,
			'POST',
			`${APP_BASE}/api/store/items/${PROBE_SLUG}/install`,
		)

		expect(res.status).toBe(400)
		const body = JSON.parse(res.text)
		expect(body.success).toBe(false)
		expect(typeof body.message).toBe('string')
		expect(body.message.length).toBeGreaterThan(0)
	})

	/*
	 * @e2e openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#scenario-an-administrator-saves-a-registry-and-reads-it-back
	 * @e2e openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#scenario-the-token-is-never-shown-back
	 * @e2e openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#scenario-reading-the-config-never-returns-the-token
	 */
	test('an admin saves a registry, reads it back, and never sees the token again', async ({
		page,
	}) => {
		const secret = `e2e-token-${Date.now()}-${Math.random().toString(36).slice(2)}`

		await openRegistryForm(page)
		await expect(page.locator('[data-test="registry-token-status"]')).toHaveText(
			'No token is set. Add one if the registry asks for it.',
		)

		await page
			.locator('input[data-test="registry-url"]')
			.fill(UNRESOLVABLE_REGISTRY)
		await page
			.locator('input[data-test="registry-register"]')
			.fill('e2e-register')
		await page.locator('input[data-test="registry-token"]').fill(secret)

		const saved = page.waitForResponse(
			(res) =>
				res.url().includes(`${APP_BASE}/api/store/config`)
				&& res.request().method() === 'PUT',
		)
		await page.locator('[data-test="registry-save"]').click()
		const saveRes = await saved
		expect(saveRes.status()).toBe(200)
		expect(
			await saveRes.text(),
			'the save response echoed the token',
		).not.toContain(secret)

		// A RELOAD, so what the fields show is what the server holds rather
		// than what is still sitting in the component's memory.
		const reread = page.waitForResponse(
			(res) =>
				res.url().includes(`${APP_BASE}/api/store/config`)
				&& res.request().method() === 'GET',
		)
		await openRegistryForm(page)
		const readRes = await reread
		const readText = await readRes.text()

		await expect(page.locator('input[data-test="registry-url"]')).toHaveValue(
			UNRESOLVABLE_REGISTRY,
		)
		await expect(
			page.locator('input[data-test="registry-register"]'),
		).toHaveValue('e2e-register')

		// The token was stored, and is reported as stored...
		expect(JSON.parse(readText).tokenConfigured).toBe(true)
		await expect(page.locator('[data-test="registry-token-status"]')).toHaveText(
			'A token is set. Enter a new one to replace it.',
		)
		// ...and is nowhere a browser can read it.
		await expect(page.locator('input[data-test="registry-token"]')).toHaveValue(
			'',
		)
		expect(readText, 'the config response carried the token').not.toContain(
			secret,
		)
		expect(await page.content(), 'the page carried the token').not.toContain(
			secret,
		)
	})

	/*
	 * @e2e openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#scenario-the-saved-registry-is-the-one-the-store-reads
	 */
	test('the registry the form saves is the one the store reads', async ({
		page,
	}) => {
		// Save through the FORM, not the API, so this proves the form and the
		// engine agree on which config keys hold the registry.
		await openRegistryForm(page)
		await page
			.locator('input[data-test="registry-url"]')
			.fill(UNRESOLVABLE_REGISTRY)
		const saved = page.waitForResponse(
			(res) =>
				res.url().includes(`${APP_BASE}/api/store/config`)
				&& res.request().method() === 'PUT',
		)
		await page.locator('[data-test="registry-save"]').click()
		expect((await saved).status()).toBe(200)

		const { status, body } = await openStore(page)

		// Unreachable, NOT not-configured: the engine saw the URL the form
		// wrote, and its SSRF guard refused a host that does not resolve.
		expect(status).toBe(200)
		expect(body.outcome).toBe('store_unreachable')
		await expect(page.locator('[data-testid="store-unreachable"]')).toBeVisible()
		await expect(
			page.locator('[data-testid="store-not-configured"]'),
		).toHaveCount(0)
	})

	test.describe('a non-admin account', () => {
		// A cold first login for a brand-new account builds its home folder
		// and can take most of a minute on a loaded instance. The budget is
		// raised so the guard gets far enough to assert, not so it asserts
		// more weakly. Same reasoning as admin-menu-permissions.spec.ts.
		test.describe.configure({ timeout: 180_000 })

		let account: { username: string; password: string }

		test.beforeAll(async () => {
			account = await provisionThrowawayUser('e2e-store')
		})

		test.afterAll(async () => {
			if (account) {
				await deprovisionUser(account.username)
			}
		})

		/*
		 * @e2e openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#scenario-a-non-admin-is-refused-the-registry-config
		 * @e2e openspec/changes/store-plane-dashboard-sharing/specs/dashboard-store/spec.md#scenario-a-non-admin-is-refused-an-install
		 */
		test('is refused the registry config and the install, but may search', async ({
			browser,
		}) => {
			const { context, page } = await loginAs(
				browser,
				account.username,
				account.password,
			)
			try {
				// A Nextcloud page that loads `OC`, so the identity and the
				// request token can be read from the session itself.
				await page.goto(`${BASE}/index.php${APP_BASE}/`, {
					waitUntil: 'domcontentloaded',
				})
				await page.waitForFunction(
					() => Boolean((window as any).OC?.requestToken),
					{
						timeout: 60_000,
					},
				)

				// IDENTITY FIRST. A "non-admin" context that is really the
				// admin makes every assertion below pass for the wrong reason.
				const who = await whoami(page)
				expect(who.uid, 'the session is the throwaway account').toBe(
					account.username,
				)
				expect(who.isAdmin, 'the throwaway account is not an admin').toBe(
					false,
				)

				// POSITIVE CONTROL. The same session and token reach the
				// login-only route. Without this, a broken session would
				// produce the refusals below and read as a working gate.
				const search = await callFromPage(
					page,
					'GET',
					`${APP_BASE}/api/store/items`,
				)
				expect(search.status, 'a signed-in non-admin may search').toBe(200)
				expect(JSON.parse(search.text).outcome).toBe('not_configured')

				const read = await callFromPage(
					page,
					'GET',
					`${APP_BASE}/api/store/config`,
				)
				expect(read.status, 'a non-admin read the registry config').toBe(403)

				const write = await callFromPage(
					page,
					'PUT',
					`${APP_BASE}/api/store/config`,
					{
						registryUrl: UNRESOLVABLE_REGISTRY,
					},
				)
				expect(write.status, 'a non-admin wrote the registry config').toBe(
					403,
				)

				const install = await callFromPage(
					page,
					'POST',
					`${APP_BASE}/api/store/items/${PROBE_SLUG}/install`,
				)
				expect(install.status, 'a non-admin reached the install').toBe(403)

				// A refused write must also not have landed.
				const stored = await readRegistry()
				expect(
					stored.registryUrl,
					'the refused write changed the registry',
				).toBe('')
			} finally {
				await context.close()
			}
		})
	})
})
