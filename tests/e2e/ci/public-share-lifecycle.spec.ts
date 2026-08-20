/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The rest of the public-share surface: creation options, the owner-only
 * management endpoints, expiry, and the password gate.
 *
 * `public-share.spec.ts` next door proves the happy path — a live token serves
 * an anonymous visitor and stops when revoked. This file covers what that one
 * deliberately did not: who is allowed to manage a share, what the list
 * endpoint returns, and the two independent ways a share stops working
 * (expiry, revocation) as distinct from never having existed.
 *
 * WHAT MAKES THESE ASSERTIONS DISCRIMINATING. Every negative case here is
 * bracketed by the positive one it is supposed to differ from, in the same
 * test, against the same instance:
 *
 *   - the non-owner 403s are each preceded by the SAME call succeeding as the
 *     owner, so a 403 caused by a broken route rather than by authorization
 *     cannot pass;
 *   - the expiry tests create a past-dated and a future-dated share from the
 *     same request shape, so "404 for everything" fails;
 *   - the password tests assert the wrong password is refused AND the right
 *     one is accepted, so a gate that rejects unconditionally fails;
 *   - the list test seeds three live shares and one revoked one and asserts
 *     the exact set of tokens returned, not the array's length alone — an
 *     endpoint that returned every share including the revoked one would
 *     satisfy a length-only check as soon as a fourth share existed anywhere.
 *
 * These are API-level assertions on an endpoint family that has no UI beyond
 * the anonymous render page (already covered next door), which is why they sit
 * in `tests/e2e/ci/` next to the other API-level specs. That directory no
 * longer decides execution: `playwright.config.ts` does, via its `testIgnore`,
 * and this file is not in it. The two facts to keep straight are that the
 * annotations below are counted by gate-19 only because the file runs in CI,
 * and that both halves of that sentence are decided in the same config the
 * workflow loads.
 *
 * Scenarios covered:
 *   @e2e dashboard-public-share::create-a-public-share-without-password
 *   @e2e dashboard-public-share::create-a-public-share-with-password
 *   @e2e dashboard-public-share::create-a-public-share-with-expiry
 *   @e2e dashboard-public-share::create-multiple-shares-for-one-dashboard
 *   @e2e dashboard-public-share::non-owner-cannot-create-shares
 *   @e2e dashboard-public-share::list-shares-for-a-dashboard-with-multiple-active-shares
 *   @e2e dashboard-public-share::list-shares-for-a-dashboard-with-no-shares
 *   @e2e dashboard-public-share::non-owner-cannot-list-shares
 *   @e2e dashboard-public-share::revoked-shares-are-not-included-in-list
 *   @e2e dashboard-public-share::non-owner-cannot-revoke-shares
 *   @e2e dashboard-public-share::revoking-already-revoked-share-is-idempotent
 *   @e2e dashboard-public-share::revoked-share-is-no-longer-valid
 *   @e2e dashboard-public-share::expired-token-returns-404
 *   @e2e dashboard-public-share::expired-share-returns-404
 *   @e2e dashboard-public-share::share-expiring-in-the-future-is-still-valid
 *   @e2e dashboard-public-share::share-with-null-expiresat-never-expires
 *   @e2e dashboard-public-share::password-protected-share-returns-401-without-unlock
 *   @e2e dashboard-public-share::unlock-with-correct-password
 *   @e2e dashboard-public-share::unlock-with-incorrect-password
 *   @e2e dashboard-public-share::query-param-password-alternative-to-header
 *
 * @spec openspec/specs/dashboard-public-share/spec.md
 */

import { expect, request, test, type APIRequestContext } from '@playwright/test'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

/*
 * The non-owner. Created by `tests/e2e/seed.sh`, which the quality workflow
 * runs as `playwright-seed-command` and which fails loudly when the account is
 * absent afterwards — so a missing grantee stops the job rather than silently
 * turning every 403 assertion below into a test of nothing.
 */
const GRANTEE = {
	user: process.env.LAUNCHPAD_E2E_GRANTEE ?? 'e2e-grantee',
	pass: process.env.LAUNCHPAD_E2E_GRANTEE_PASS ?? 'E2eGranteePw123',
}

/*
 * An explicit Authorization header, NOT Playwright's `httpCredentials`.
 * httpCredentials is reactive — it only sends credentials after the server
 * answers 401 with a WWW-Authenticate challenge, which Nextcloud's API routes
 * do not send; the request simply arrives as 'Anonymous' and is refused by
 * RBAC, which reads exactly like the authorization failure these tests are
 * trying to distinguish from. Same reasoning as public-share.spec.ts.
 */
function basic(user: string, pass: string): string {
	return `Basic ${Buffer.from(`${user}:${pass}`).toString('base64')}`
}

/*
 * `storageState: undefined` IS THE LOAD-BEARING LINE, NOT BOILERPLATE.
 *
 * The root config sets `use.storageState` to the admin session
 * `global-setup.ts` harvests, and a context created inside a test inherits it.
 * So the GRANTEE context arrived carrying admin's cookies, Nextcloud preferred
 * the session over the `Authorization: Basic` header, and the non-owner test
 * was quietly asserting what an ADMIN may do.
 *
 * Measured: run 31389746411, where the grantee's `POST …/public-share`
 * answered 201 with `"createdBy":"admin"` — the response naming the user the
 * request had actually been served as. Explicitly clearing it is what makes
 * the credentials below the thing that decides who the caller is.
 */
async function apiAs(
	baseURL: string,
	creds: { user: string; pass: string },
): Promise<APIRequestContext> {
	return request.newContext({
		baseURL,
		storageState: undefined,
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Authorization: basic(creds.user, creds.pass),
		},
	})
}

/*
 * An API context with NO Authorization header — a real anonymous visitor.
 * `storageState: undefined` matters even more here: inheriting the admin
 * session would make every "anonymous" assertion below a statement about a
 * logged-in administrator, and the public-share tests would pass while proving
 * the opposite of what they claim.
 */
async function anonymousApi(baseURL: string): Promise<APIRequestContext> {
	return request.newContext({ baseURL, storageState: undefined })
}

const SETTINGS = '/index.php/apps/launchpad/api/admin/settings'
const DASHBOARDS = '/index.php/apps/launchpad/api/dashboard'
const shareUrl = (uuid: string) =>
	`/index.php/apps/launchpad/api/dashboards/${uuid}/public-share`
const sharesUrl = (uuid: string) =>
	`/index.php/apps/launchpad/api/dashboards/${uuid}/public-shares`
const dataUrl = (token: string) => `/index.php/apps/launchpad/s/${token}/data`

/**
 * Create a dashboard owned by the caller and return its uuid.
 *
 * `POST /api/dashboard` answers `{dashboard: {...}, placements: [...]}` — the
 * new dashboard is nested, not at the root.
 */
async function createDashboard(
	api: APIRequestContext,
	label: string,
): Promise<string> {
	const res = await api.post(DASHBOARDS, {
		data: { name: `E2E ${label} ${Date.now()}` },
	})
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const uuid = body.dashboard?.uuid ?? body.uuid ?? body.data?.uuid
	expect(uuid, `no uuid in create response: ${JSON.stringify(body)}`).toBeTruthy()
	return uuid as string
}

/** Create a share and return its parsed body. */
async function createShare(
	api: APIRequestContext,
	uuid: string,
	payload: Record<string, unknown> = {},
): Promise<{
	id: number
	token: string
	passwordRequired: boolean
	expiresAt: string | null
}> {
	const res = await api.post(shareUrl(uuid), { data: payload })
	expect(res.status(), await res.text()).toBe(201)
	return await res.json()
}

/*
 * A fresh CI instance ships `allow_user_dashboards` OFF, so `POST
 * /api/dashboard` answers 403 personal_dashboards_disabled (REQ-ASET-003).
 * Enabling it is setup for these tests, not part of what they assert — the
 * prior value is read first and restored afterwards so the instance is left as
 * it was found for whatever runs next in the same job.
 */
let priorAllowUserDash = true

/*
 * `baseURL` is a TEST-scoped option, so it cannot be destructured in a
 * worker-scoped hook — Playwright rejects that with "Fixture "baseURL" has
 * "test" scope but is used in beforeAll hook". These hooks read the same
 * environment variable the config resolves it from.
 */
const ENV_BASE_URL = (process.env.BASE_URL ?? process.env.NC_BASE_URL ?? '').replace(
	/\/$/,
	'',
)

test.beforeAll(async () => {
	const admin = await apiAs(ENV_BASE_URL, ADMIN)
	const before = await admin.get(SETTINGS)
	expect(before.status(), await before.text()).toBe(200)
	priorAllowUserDash = (await before.json()).allowUserDashboards === true
	const enable = await admin.put(SETTINGS, { data: { allowUserDash: true } })
	expect(enable.status(), await enable.text()).toBeLessThan(300)
	await admin.dispose()
})

test.afterAll(async () => {
	if (priorAllowUserDash === true) {
		return
	}
	const admin = await apiAs(ENV_BASE_URL, ADMIN)
	await admin.put(SETTINGS, { data: { allowUserDash: false } })
	await admin.dispose()
})

test.describe('public share creation options', () => {
	test('a share created with no options has no password and no expiry, and serves anonymously', async ({
		baseURL,
	}) => {
		const admin = await apiAs(baseURL!, ADMIN)
		const anon = await anonymousApi(baseURL!)
		const uuid = await createDashboard(admin, 'plain-share')

		const share = await createShare(admin, uuid, {})

		// The FIELDS are the assertion, not the 201. A create that silently
		// dropped the caller's options would still answer 201.
		expect(
			share.passwordRequired,
			'a share created with `{}` must not be password-gated',
		).toBe(false)
		expect(
			share.expiresAt,
			'a share created with `{}` must never expire',
		).toBeNull()
		expect(share.token, 'the share must carry a token').toBeTruthy()

		// …and the token it handed out must actually work with no password,
		// which is the behaviour `passwordRequired: false` is claiming.
		const live = await anon.get(dataUrl(share.token))
		expect(live.status(), await live.text()).toBe(200)

		await admin.dispose()
		await anon.dispose()
	})

	test('a share created with a password is gated, and the password is never echoed back', async ({
		baseURL,
	}) => {
		const admin = await apiAs(baseURL!, ADMIN)
		const anon = await anonymousApi(baseURL!)
		const uuid = await createDashboard(admin, 'pw-share')

		const password = 'SecurePass123!'
		const res = await admin.post(shareUrl(uuid), { data: { password } })
		expect(res.status(), await res.text()).toBe(201)
		const raw = await res.text()
		const share = JSON.parse(raw)

		expect(
			share.passwordRequired,
			'a share created WITH a password must report passwordRequired',
		).toBe(true)
		// The stored value must be a hash, and the response is the only place
		// a plaintext leak would be visible from outside the database.
		expect(
			raw,
			'the create response must not contain the plaintext password or its hash — jsonSerialize() omits passwordHash',
		).not.toContain(password)
		expect(raw, 'passwordHash must not be serialised at all').not.toContain(
			'passwordHash',
		)

		// The gate must be real, not just a flag: an anonymous render with no
		// password must be refused.
		const gated = await anon.get(dataUrl(share.token))
		expect(
			gated.status(),
			'a password-gated share must not render without one',
		).toBe(401)

		await admin.dispose()
		await anon.dispose()
	})

	test('a share created with an expiry carries it back, and expiry decides validity in both directions', async ({
		baseURL,
	}) => {
		const admin = await apiAs(baseURL!, ADMIN)
		const anon = await anonymousApi(baseURL!)
		const uuid = await createDashboard(admin, 'expiry-share')

		const iso = (d: Date) => `${d.toISOString().slice(0, 19)}Z`
		const past = iso(new Date(Date.now() - 24 * 3600_000))
		const future = iso(new Date(Date.now() + 24 * 3600_000))

		const expired = await createShare(admin, uuid, { expiresAt: past })
		const valid = await createShare(admin, uuid, { expiresAt: future })
		const never = await createShare(admin, uuid, {})

		// The value must survive the round trip — an `expiresAt` the server
		// failed to parse and stored as NULL would leave the share permanently
		// valid while the create call still answered 201.
		expect(
			expired.expiresAt,
			'a supplied expiry must be stored, not dropped',
		).not.toBeNull()
		expect(
			valid.expiresAt,
			'a supplied expiry must be stored, not dropped',
		).not.toBeNull()
		expect(
			never.expiresAt,
			'a share created without an expiry must have none',
		).toBeNull()

		// The three arms differ ONLY in expiry, so this triple cannot be
		// satisfied by an endpoint that answers the same way for everything.
		const past404 = await anon.get(dataUrl(expired.token))
		expect(past404.status(), 'a share whose expiry has elapsed must 404').toBe(
			404,
		)

		const futureOk = await anon.get(dataUrl(valid.token))
		expect(futureOk.status(), await futureOk.text()).toBe(200)

		const neverOk = await anon.get(dataUrl(never.token))
		expect(
			neverOk.status(),
			'a share with expiresAt NULL must not have expiry logic applied to it',
		).toBe(200)

		await admin.dispose()
		await anon.dispose()
	})

	test('two shares on one dashboard are independent — revoking one leaves the other live', async ({
		baseURL,
	}) => {
		const admin = await apiAs(baseURL!, ADMIN)
		const anon = await anonymousApi(baseURL!)
		const uuid = await createDashboard(admin, 'multi-share')

		const first = await createShare(admin, uuid, {})
		const second = await createShare(admin, uuid, { password: 'SecondPass123!' })

		expect(second.token, 'two shares must not share a token').not.toBe(
			first.token,
		)
		expect(first.passwordRequired).toBe(false)
		expect(
			second.passwordRequired,
			'per-share password settings must be independent',
		).toBe(true)

		// Revoking one must not touch the other. This is the assertion that
		// catches a revoke implemented per-DASHBOARD rather than per-share.
		const revoked = await admin.delete(`${sharesUrl(uuid)}/${first.id}`)
		expect(revoked.status(), await revoked.text()).toBe(204)

		const firstAfter = await anon.get(dataUrl(first.token))
		expect(firstAfter.status(), 'the revoked share must stop working').toBe(404)

		const secondAfter = await anon.get(dataUrl(second.token))
		expect(
			secondAfter.status(),
			'the sibling share must be untouched — 401 means it is alive and still asking for its own password',
		).toBe(401)

		await admin.dispose()
		await anon.dispose()
	})
})

test.describe('owner-only management endpoints', () => {
	test('the list returns exactly the live shares, by token, and drops the revoked one', async ({
		baseURL,
	}) => {
		const admin = await apiAs(baseURL!, ADMIN)
		const uuid = await createDashboard(admin, 'list-share')

		// A dashboard with zero shares answers with an empty array — the
		// control that proves the later assertion is reading THIS dashboard's
		// shares and not a global list.
		const empty = await admin.get(sharesUrl(uuid))
		expect(empty.status(), await empty.text()).toBe(200)
		expect(
			await empty.json(),
			'a dashboard with no shares must list none',
		).toEqual([])

		const live = [
			await createShare(admin, uuid),
			await createShare(admin, uuid),
			await createShare(admin, uuid),
		]
		const doomed = await createShare(admin, uuid)
		const revoked = await admin.delete(`${sharesUrl(uuid)}/${doomed.id}`)
		expect(revoked.status(), await revoked.text()).toBe(204)

		const res = await admin.get(sharesUrl(uuid))
		expect(res.status(), await res.text()).toBe(200)
		const listed = (await res.json()) as Array<Record<string, unknown>>

		// ASSERT ON THE ITEMS. A length check alone passes for any three
		// shares; the set of tokens is what says the revoked one was excluded
		// and the live ones were not.
		const tokens = listed.map((s) => s.token)
		expect(
			tokens.slice().sort(),
			'the list must be exactly the three live shares',
		).toEqual(
			live
				.map((s) => s.token)
				.slice()
				.sort(),
		)
		expect(
			tokens,
			'a soft-revoked share must not appear in the list',
		).not.toContain(doomed.token)

		// REQ-PSHR-002 names the fields each entry must carry, including the
		// two an implementation that returned bare rows would omit.
		for (const entry of listed) {
			for (const field of [
				'id',
				'token',
				'url',
				'passwordRequired',
				'expiresAt',
				'viewCount',
				'lastViewedAt',
			]) {
				expect(
					entry,
					`every listed share must carry '${field}'`,
				).toHaveProperty(field)
			}
			expect(
				entry.token,
				'the token must be returned in full, not redacted',
			).toEqual(expect.any(String))
		}

		await admin.dispose()
	})

	test('revoking is idempotent — the second DELETE is still 204', async ({
		baseURL,
	}) => {
		const admin = await apiAs(baseURL!, ADMIN)
		const uuid = await createDashboard(admin, 'idempotent-share')
		const share = await createShare(admin, uuid)

		const first = await admin.delete(`${sharesUrl(uuid)}/${share.id}`)
		expect(first.status(), await first.text()).toBe(204)

		const second = await admin.delete(`${sharesUrl(uuid)}/${share.id}`)
		expect(
			second.status(),
			'revoking an already-revoked share must answer 204, not 404 — the row is soft-deleted, not gone',
		).toBe(204)

		await admin.dispose()
	})

	test('a non-owner cannot create, list or revoke shares the owner can', async ({
		baseURL,
	}) => {
		const admin = await apiAs(baseURL!, ADMIN)
		const grantee = await apiAs(baseURL!, GRANTEE)
		const uuid = await createDashboard(admin, 'nonowner-share')
		const share = await createShare(admin, uuid)

		/*
		 * POSITIVE CONTROL FIRST. Each 403 below is the same request the owner
		 * just made successfully, so a 403 produced by a missing route, a typo
		 * in the URL or a dead endpoint cannot masquerade as an authorization
		 * decision. Without this, all three assertions pass against an app
		 * that does not have these endpoints at all.
		 */
		const ownerList = await admin.get(sharesUrl(uuid))
		expect(ownerList.status(), 'control: the owner CAN list').toBe(200)

		const create = await grantee.post(shareUrl(uuid), { data: {} })
		expect(create.status(), await create.text()).toBe(403)

		const list = await grantee.get(sharesUrl(uuid))
		expect(list.status(), await list.text()).toBe(403)

		const revoke = await grantee.delete(`${sharesUrl(uuid)}/${share.id}`)
		expect(revoke.status(), await revoke.text()).toBe(403)

		// …and no share was created by the refused POST: the owner's list is
		// still exactly the one share they made.
		const after = await admin.get(sharesUrl(uuid))
		const tokens = ((await after.json()) as Array<{ token: string }>).map(
			(s) => s.token,
		)
		expect(tokens, 'a refused create must not have created anything').toEqual([
			share.token,
		])

		await admin.dispose()
		await grantee.dispose()
	})
})

test.describe('the password gate', () => {
	test('the wrong password is refused, the right one unlocks, and either transport carries it', async ({
		baseURL,
	}) => {
		const admin = await apiAs(baseURL!, ADMIN)
		const anon = await anonymousApi(baseURL!)
		const uuid = await createDashboard(admin, 'unlock-share')

		const password = 'SecurePass123!'
		const share = await createShare(admin, uuid, { password })

		// No password at all → 401 and NO dashboard data. The body matters:
		// a 401 that still carried the dashboard would be a leak.
		const noPassword = await anon.get(dataUrl(share.token))
		expect(noPassword.status()).toBe(401)
		const gatedBody = await noPassword.json()
		expect(
			gatedBody.passwordRequired,
			'the refusal must say what is missing',
		).toBe(true)
		expect(
			gatedBody,
			'a refused render must not carry the dashboard',
		).not.toHaveProperty('dashboard')
		expect(
			gatedBody,
			'a refused render must not carry its placements',
		).not.toHaveProperty('placements')

		// Wrong password → refused. This and the next assertion differ ONLY in
		// the password, so a gate that always says no fails the next one and a
		// gate that always says yes fails this one.
		const wrong = await anon.post(
			`/index.php/apps/launchpad/s/${share.token}/unlock`,
			{ data: { password: 'WrongPassword' } },
		)
		expect(wrong.status()).toBe(401)
		expect(
			(await wrong.json()).access,
			'a wrong password must not grant access',
		).toBe(false)

		const right = await anon.post(
			`/index.php/apps/launchpad/s/${share.token}/unlock`,
			{ data: { password } },
		)
		expect(right.status(), await right.text()).toBe(200)
		expect(
			(await right.json()).access,
			'the correct password must grant access',
		).toBe(true)

		// REQ-PSHR-005: the query param and the X-Share-Password header are
		// both accepted by the render endpoint. Assert on the RENDER, since
		// that is what the transports are for.
		const viaQuery = await anon.get(
			`${dataUrl(share.token)}?password=${encodeURIComponent(password)}`,
		)
		expect(viaQuery.status(), await viaQuery.text()).toBe(200)
		expect(
			await viaQuery.json(),
			'an unlocked render must carry the dashboard',
		).toHaveProperty('dashboard')

		const viaHeader = await anon.get(dataUrl(share.token), {
			headers: { 'X-Share-Password': password },
		})
		expect(viaHeader.status(), await viaHeader.text()).toBe(200)
		expect(
			await viaHeader.json(),
			'an unlocked render must carry the dashboard',
		).toHaveProperty('dashboard')

		await admin.dispose()
		await anon.dispose()
	})
})
