/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The anonymous public-share surface: a dashboard published as a read-only
 * link must render for someone with no Nextcloud session, and ONLY for a live
 * token.
 *
 * This is the one page LaunchPad serves to unauthenticated visitors — a
 * separate entry point (`templates/public.php` -> `src/public.js` ->
 * `DashboardPublicShareView.vue`) that does not go through the app's normal
 * shell or its session. It had no e2e coverage at all, which is what gate-26
 * reports: a page component with no visual or behavioural proof.
 *
 * THE DISCRIMINATING ASSERTIONS are the negative ones. "An anonymous request
 * with a valid token returns the dashboard" would also pass if the endpoint
 * ignored the token entirely and served any dashboard to anyone — which is
 * the failure mode that actually matters on a route with no authentication in
 * front of it. So the test brackets the positive case with two controls:
 *
 *   - a well-formed but unissued token must NOT return data (before)
 *   - the same token that just worked must STOP working once revoked (after)
 *
 * Both run against the same endpoint in the same session, so a pass means the
 * token is what admits the request, not the absence of a check.
 *
 * The browser leg uses a context with no credentials, deliberately: the API
 * legs could pass while the rendered page still required a session, and that
 * is exactly the bug a public link has to not have.
 *
 * Scenarios covered:
 *   @e2e dashboard-public-share::public-render-via-valid-token-without-password
 *   @e2e dashboard-public-share::invalid-token-returns-404
 *   @e2e dashboard-public-share::revoked-token-returns-404
 *   @e2e dashboard-public-share::soft-revoke-a-public-share
 *
 * @spec openspec/specs/dashboard-public-share/spec.md
 */

import { expect, request, test, type APIRequestContext } from '@playwright/test'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

/*
 * An explicit Authorization header, NOT Playwright's `httpCredentials` —
 * httpCredentials is reactive and only sends credentials after a 401 with a
 * WWW-Authenticate challenge, which Nextcloud's API routes do not send. Same
 * reasoning as manifest-grants.spec.ts.
 */
function basic(user: string, pass: string): string {
	return `Basic ${Buffer.from(`${user}:${pass}`).toString('base64')}`
}

async function apiAs(baseURL: string, creds: { user: string, pass: string }): Promise<APIRequestContext> {
	return request.newContext({
		baseURL,
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Authorization: basic(creds.user, creds.pass),
		},
	})
}

/*
 * An API context with NO Authorization header — a real anonymous visitor.
 *
 * `storageState: undefined` is not decoration. The root config sets
 * `use.storageState` to the admin session `global-setup.ts` harvests, and a
 * context created inside a test inherits it, so "anonymous" silently became
 * "logged in as an administrator" (measured on the sibling specs in run
 * 31389746411, where a grantee's create answered `"createdBy":"admin"`).
 *
 * This spec kept PASSING under that inheritance, which is the reason to fix it
 * here too: token gating is independent of who the caller is, so the
 * assertions below survive being made by an admin — and a test that passes
 * while the premise in its name is false is the most expensive kind to leave
 * alone.
 */
async function anonymousApi(baseURL: string): Promise<APIRequestContext> {
	return request.newContext({ baseURL, storageState: undefined })
}

test.describe('anonymous public share', () => {
	test('a valid token renders read-only for a visitor with no session, and stops on revoke', async ({ baseURL, browser }) => {
		const admin = await apiAs(baseURL!, ADMIN)
		const anon = await anonymousApi(baseURL!)

		// --- precondition: personal dashboards must be enabled -----------
		// A fresh CI instance ships `allow_user_dashboards` OFF, so
		// `POST /api/dashboard` answers 403 personal_dashboards_disabled
		// (REQ-ASET-003). Enabling it is setup for this test, not part of
		// what it asserts — the original value is read first and restored at
		// the end so the instance is left as it was found for whatever runs
		// next in the same job.
		const settingsBefore = await admin.get('/index.php/apps/launchpad/api/admin/settings')
		expect(settingsBefore.status(), await settingsBefore.text()).toBe(200)
		const priorAllowUserDash = (await settingsBefore.json()).allowUserDashboards === true

		const enable = await admin.put('/index.php/apps/launchpad/api/admin/settings', {
			data: { allowUserDash: true },
		})
		expect(enable.status(), await enable.text()).toBeLessThan(300)

		// --- create a dashboard to publish -------------------------------
		const stamp = Date.now()
		const created = await admin.post('/index.php/apps/launchpad/api/dashboard', {
			data: { name: `E2E Public Share ${stamp}` },
		})
		expect(created.status(), await created.text()).toBeLessThan(300)
		// `POST /api/dashboard` answers `{dashboard: {...}, placements: [...]}`
		// — the new dashboard is nested, not at the root, and the default
		// widget bundle comes back alongside it.
		const createdBody = await created.json()
		const uuid = createdBody.dashboard?.uuid ?? createdBody.uuid ?? createdBody.data?.uuid
		expect(uuid, `no uuid in create response: ${JSON.stringify(createdBody)}`).toBeTruthy()

		// --- CONTROL, before any share exists ----------------------------
		// A well-formed token that was never issued. If this returns 200 the
		// endpoint is not consulting the token at all, and every later
		// assertion in this test is meaningless.
		const unissued = 'a'.repeat(64)
		const bogus = await anon.get(`/index.php/apps/launchpad/s/${unissued}/data`)
		expect(
			bogus.status(),
			'an unissued token must not return dashboard data — if it does, the token is not being checked',
		).not.toBe(200)

		// --- publish -----------------------------------------------------
		const share = await admin.post(
			`/index.php/apps/launchpad/api/dashboards/${uuid}/public-share`,
			{ data: {} },
		)
		expect(share.status(), await share.text()).toBeLessThan(300)
		const shareBody = await share.json()
		const token = shareBody.token ?? shareBody.data?.token ?? shareBody.share?.token
		const shareId = shareBody.id ?? shareBody.data?.id ?? shareBody.share?.id
		expect(token, `no token in share response: ${JSON.stringify(shareBody)}`).toBeTruthy()

		// --- the positive case, anonymously ------------------------------
		const live = await anon.get(`/index.php/apps/launchpad/s/${token}/data`)
		expect(
			live.status(),
			`a live token must serve data to an anonymous caller: ${await live.text()}`,
		).toBe(200)

		// --- the page itself, in a browser with no session ---------------
		// A fresh context inherits no storage state, so this is a genuine
		// first-time visitor. The API legs above could both pass while the
		// rendered page still redirected to the login form.
		const context = await browser.newContext({ baseURL, storageState: undefined })
		const page = await context.newPage()
		const response = await page.goto(`/index.php/apps/launchpad/s/${token}`)
		expect(response?.status(), 'the public page must not 4xx for an anonymous visitor').toBeLessThan(400)
		// `.public-share-view` is DashboardPublicShareView.vue's root element —
		// its presence means the public bundle booted and mounted, not merely
		// that some HTML came back.
		await expect(page.locator('.public-share-view')).toBeVisible({ timeout: 15000 })
		expect(page.url(), 'an anonymous visitor must not be bounced to the login page').not.toContain('/login')
		await context.close()

		// --- revoke, and prove the same token stops working --------------
		expect(shareId, `no share id in response: ${JSON.stringify(shareBody)}`).toBeTruthy()
		const revoked = await admin.delete(
			`/index.php/apps/launchpad/api/dashboards/${uuid}/public-shares/${shareId}`,
		)
		expect(revoked.status(), await revoked.text()).toBeLessThan(300)

		const afterRevoke = await anon.get(`/index.php/apps/launchpad/s/${token}/data`)
		expect(
			afterRevoke.status(),
			'a revoked token must stop serving data — this is the assertion that proves the token gates the route',
		).not.toBe(200)

		// Leave the instance as it was found.
		if (priorAllowUserDash === false) {
			await admin.put('/index.php/apps/launchpad/api/admin/settings', {
				data: { allowUserDash: false },
			})
		}

		await admin.dispose()
		await anon.dispose()
	})
})
