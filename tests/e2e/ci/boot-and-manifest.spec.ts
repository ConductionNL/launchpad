/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Regression floor for two defects that both shipped silently and both had the
 * same shape: the app looked fine and returned nothing useful.
 *
 * 1. EVERY ROUTE RETURNED 500 on an instance without OpenRegister, because
 *    HealthController/MetricsController extended OpenRegister classes and
 *    Nextcloud's router reflects every controller while scanning attribute
 *    routes. Not just the two endpoints that needed OR — all of them.
 *
 * 2. THE MANIFEST WAS ALWAYS EMPTY, for every user including the owner of the
 *    dashboards, because the owner filter was spelled `owner` instead of a
 *    nested `@self` metadata filter and so degraded into a property filter that
 *    matched nothing.
 *
 * Neither was caught by 1513 unit tests. The first is invisible to a suite that
 * never loads the router; the second is invisible to any test that doubles
 * ObjectService, because a double confirms the call shape you invented rather
 * than the one OpenRegister accepts.
 *
 * Scenarios covered:
 *   @e2e boot::routes-do-not-500-without-open-register-inheritance
 *   @e2e manifest::owner-sees-their-own-dashboards
 *
 * @spec openspec/specs/runtime-shell/spec.md
 */

import type { APIRequestContext } from '@playwright/test'

import { expect, request, test } from '@playwright/test'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

/*
 * An explicit Authorization header, NOT Playwright's `httpCredentials`.
 * httpCredentials is reactive — it only sends credentials after the server
 * answers 401 with a WWW-Authenticate challenge. Nextcloud's API routes do not
 * challenge; they simply treat the request as anonymous, so every call arrived as
 * user 'Anonymous' and was refused by RBAC. Sending the header up front is
 * deterministic.
 */
function basic(user: string, pass: string): string {
	return `Basic ${Buffer.from(`${user}:${pass}`).toString('base64')}`
}

const REGISTER = 'launchpad'
const SCHEMA = 'dashboard'

/** An authenticated API context for the admin account. */
async function adminApi(baseURL: string): Promise<APIRequestContext> {
	return request.newContext({
		baseURL,
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Authorization: basic(ADMIN.user, ADMIN.pass),
		},
	})
}

test.describe('launchpad boots and serves a populated manifest', () => {
	test('the observability routes respond rather than fataling', async ({
		baseURL,
	}) => {
		const api = await adminApi(baseURL!)

		/*
		 * The assertion that matters is "not 500". Before the fix these returned
		 * 500 — and so did every other route in the app, because the fatal was
		 * raised during route MATCHING, not inside the handler.
		 *
		 * 200 is expected here because CI now installs OpenRegister
		 * (additional-apps). A 503 would mean the engine is genuinely absent,
		 * which is the correct degraded answer but wrong for this job's setup —
		 * so both are distinguished rather than lumped into "not 500".
		 */
		const health = await api.get('/index.php/apps/launchpad/api/health')
		expect(
			health.status(),
			'health 500 means a controller failed to LOAD — check for an `extends` on another app',
		).not.toBe(500)
		expect(
			health.status(),
			'OpenRegister is installed in CI, so the engine should answer',
		).toBe(200)

		const metrics = await api.get('/index.php/apps/launchpad/api/metrics')
		expect(metrics.status()).not.toBe(500)
		expect(metrics.status()).toBe(200)
		expect(
			await metrics.text(),
			'a Prometheus body proves the AppHost engine ran, not just that the route resolved',
		).toMatch(/^# HELP|^launchpad_/m)

		await api.dispose()
	})

	test('a dashboard the caller owns appears in their manifest', async ({
		baseURL,
	}) => {
		const api = await adminApi(baseURL!)

		const slug = `e2e-owned-${Date.now()}`

		// Create a dashboard object through OpenRegister, the same path the real
		// UI uses. `_limit` — not `limit` — because OpenRegister treats an
		// unprefixed control param as a property filter.
		const created = await api.post(
			`/index.php/apps/openregister/api/objects/${REGISTER}/${SCHEMA}`,
			{ data: { slug, title: 'E2E Owned Dashboard' } },
		)
		expect(created.status(), await created.text()).toBeLessThan(300)

		const manifest = await api.get('/index.php/apps/launchpad/api/manifest')
		expect(manifest.status()).toBe(200)

		const body = await manifest.json()
		expect(body).toHaveProperty('pages')

		const routes = (body.pages as Array<{ route?: string }>).map((p) => p.route)
		expect(
			routes,
			'the owner must see their own dashboard — an empty manifest here is the `owner` vs `@self` filter bug',
		).toContain(`/${slug}`)

		await api.dispose()
	})
})
