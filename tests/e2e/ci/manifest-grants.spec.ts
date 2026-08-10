/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A dashboard shared through OpenRegister's per-object grant primitive must
 * appear in the recipient's v2 manifest.
 *
 * Before this capability the controller's docblock promised "objects the user
 * owns OR that list the user in `sharedWith`", but `sharedWith` was declared in
 * the register descriptor, seeded as `[]` by a migration, and read by NO code.
 * Only an owner filter ever ran, so a shared dashboard never appeared in
 * anybody's manifest.
 *
 * THE DISCRIMINATING ASSERTION is that the recipient sees exactly ONE of the two
 * dashboards. "The recipient sees a dashboard" would also pass if the manifest
 * leaked everything — which is the failure mode worth guarding against, since
 * the safe implementation is additive and the unsafe one (drop the owner filter
 * and let RBAC decide against an unscoped schema) returns every user's rows.
 *
 * The revoke step is the control: it proves the GRANT is what admitted the row,
 * not group membership or an unscoped schema.
 *
 * Requires the seed command to have created the recipient account (see
 * `playwright-seed-command` in .github/workflows/code-quality.yml).
 *
 * Scenarios covered:
 *   @e2e manifest-grants::granted-dashboard-appears-for-recipient
 *   @e2e manifest-grants::recipient-sees-only-the-granted-one
 *   @e2e manifest-grants::revoking-removes-it-again
 *
 * @spec openspec/specs/runtime-shell/spec.md
 */

import { expect, request, test, type APIRequestContext } from '@playwright/test'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

/* Must match the seed command in the workflow. */
const RECIPIENT = { user: 'e2e-grantee', pass: 'E2eGranteePw123' }

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

/*
 * `storageState: undefined` IS THE LOAD-BEARING LINE, NOT BOILERPLATE.
 *
 * This spec asserts what a DIFFERENT user can see. The root Playwright config
 * sets `use.storageState` to the admin session `global-setup.ts` harvests, and
 * a context created inside a test inherits it — so this "grantee" context
 * arrived carrying admin's cookies, Nextcloud preferred the session over the
 * `Authorization: Basic` header, and every request the test believed it was
 * making as `e2e-grantee` was served as `admin`.
 *
 * Measured: run 31389746411, where the recipient's manifest came back holding
 * all three of admin's dashboards — including `e2e-owned-…`, created by
 * `boot-and-manifest.spec.ts` and never granted to anyone. The sibling spec
 * failed the same way with a created share reading `"createdBy":"admin"`.
 *
 * Nothing about it was visible before, because CI ran this file under a config
 * that had no `storageState` at all. The failure mode is the dangerous kind: a
 * permission test that silently authenticates as the privileged user does not
 * error, it just stops discriminating.
 */
async function apiAs(baseURL: string, creds: { user: string, pass: string }): Promise<APIRequestContext> {
	return request.newContext({
		baseURL,
		storageState: undefined,
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Authorization: basic(creds.user, creds.pass),
		},
	})
}

/** Dashboard slugs visible in a caller's manifest, derived from page routes. */
async function manifestSlugs(api: APIRequestContext): Promise<string[]> {
	const res = await api.get('/index.php/apps/launchpad/api/manifest')

	/*
	 * The status is asserted, not just parsed. A 403 body is `{"error": …}`, and
	 * reading `.pages ?? []` off it yields an empty list indistinguishable from
	 * "nothing is shared" — which is exactly how this scenario was once
	 * mis-verified by hand.
	 */
	expect(res.status(), `manifest returned ${res.status()}: ${await res.text()}`).toBe(200)

	const body = await res.json()
	expect(body).toHaveProperty('pages')

	return (body.pages as Array<{ route?: string }>)
		.map(p => (p.route ?? '').replace(/^\//, ''))
		.filter(s => s !== '')
}

test.describe('object grants surface a shared dashboard in the manifest', () => {
	test('the recipient sees exactly the granted dashboard, and loses it on revoke', async ({ baseURL }) => {
		const admin = await apiAs(baseURL!, ADMIN)
		const grantee = await apiAs(baseURL!, RECIPIENT)

		const stamp = Date.now()
		const sharedSlug = `e2e-shared-${stamp}`
		const privateSlug = `e2e-private-${stamp}`

		// Two dashboards owned by admin: one will be granted, one will not. The
		// ungranted one is what makes the later assertion discriminating.
		const shared = await admin.post(
			`/index.php/apps/openregister/api/objects/${REGISTER}/${SCHEMA}`,
			{ data: { slug: sharedSlug, title: 'E2E Shared' } },
		)
		expect(shared.status(), await shared.text()).toBeLessThan(300)
		const sharedUuid = (await shared.json())['@self'].id

		const priv = await admin.post(
			`/index.php/apps/openregister/api/objects/${REGISTER}/${SCHEMA}`,
			{ data: { slug: privateSlug, title: 'E2E Not Shared' } },
		)
		expect(priv.status(), await priv.text()).toBeLessThan(300)

		// CONTROL, before the grant: the recipient sees neither.
		const before = await manifestSlugs(grantee)
		expect(before, 'the recipient must not see either dashboard before any grant').not.toContain(sharedSlug)
		expect(before).not.toContain(privateSlug)

		// Grant read on one of them. shareType 0 = user, permission 1 = read.
		const grant = await admin.post(
			`/index.php/apps/openregister/api/objects/${REGISTER}/${SCHEMA}/${sharedUuid}/shares`,
			{ data: { shareType: 0, shareWith: RECIPIENT.user, permissions: 1 } },
		)
		expect(grant.status(), await grant.text()).toBeLessThan(300)
		const shareId = (await grant.json()).id

		const after = await manifestSlugs(grantee)
		expect(after, 'a granted dashboard must appear in the recipient manifest').toContain(sharedSlug)
		expect(
			after,
			'the recipient must NOT see the ungranted dashboard — that would be a manifest-wide leak',
		).not.toContain(privateSlug)

		// Revoke, and it must disappear again. This is what proves the grant was
		// the thing that admitted it.
		const revoke = await admin.delete(
			`/index.php/apps/openregister/api/objects/${REGISTER}/${SCHEMA}/${sharedUuid}/shares/${encodeURIComponent(shareId)}`,
		)
		expect(revoke.status(), await revoke.text()).toBeLessThan(300)

		const afterRevoke = await manifestSlugs(grantee)
		expect(afterRevoke, 'revoking the grant must remove it from the manifest').not.toContain(sharedSlug)

		await admin.dispose()
		await grantee.dispose()
	})
})
