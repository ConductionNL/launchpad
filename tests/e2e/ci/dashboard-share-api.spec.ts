/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Owner-driven dashboard sharing, end to end against a live instance.
 *
 * WHY THIS FILE EXISTS WHEN `tests/e2e/dashboard-sharing.spec.ts` ALREADY DOES
 * ===========================================================================
 * That file is in `playwright.config.ts`'s `testIgnore`, and correctly so: it
 * drives the sharing SIDEBAR and needs a pre-seeded `recipient` account
 * (`LAUNCHPAD_E2E_SHAREE`, default `recipient`) that the CI seed does not
 * create — `tests/e2e/seed.sh` creates `e2e-grantee` and nothing else. All
 * four of its tests fail in that job. Because it never runs, gate-19 counts
 * none of its annotations, and eleven REQ-SHARE scenarios have no proof.
 *
 * Its four `@e2e` slugs additionally do not match any scenario heading in
 * `openspec/specs/dashboard-sharing/spec.md` (it writes
 * `owner-adds-a-user-share`; the spec's heading slugifies to
 * `owner-adds-a-share`), so promoting that file would still not have closed
 * them.
 *
 * This file takes the other route: it PROVISIONS the accounts it needs, via
 * the same OCS provisioning API `tests/e2e/fixtures/secondary-user.ts` uses,
 * and asserts at the HTTP layer that the sharing contract holds. It does not
 * replace the sidebar spec — that one still owns the UI — and it deliberately
 * does not touch it.
 *
 * WHY HTTP AND NOT THE DOM
 * ========================
 * Every scenario in REQ-SHARE-001/002/004/006/009 is a statement about what a
 * SECOND user is allowed to see or do. The share sidebar renders for the
 * owner; the recipient's half of each scenario has no owner-visible UI at all,
 * so a DOM assertion could only ever prove the half that is already covered.
 * This is the same reasoning `ci/public-share-lifecycle.spec.ts` records for
 * sitting in `tests/e2e/ci/`, and this file follows its conventions exactly —
 * `Authorization: Basic` rather than Playwright's reactive `httpCredentials`,
 * and an explicitly EMPTY cookie jar on every context so no request is silently
 * served as the admin from `global-setup.ts`.
 *
 * TWO PLACES WHERE THE SPEC PROSE NAMES A PATH THE APP DOES NOT SERVE
 * ==================================================================
 * Recorded here rather than worked around silently, because a reader
 * comparing this file to the spec will notice:
 *
 *   1. REQ-SHARE-002 says the recipient's dashboards come from
 *      `GET /api/dashboards`. That route is `dashboardApi#list`, which calls
 *      `DashboardService::getUserDashboards()` -> `findByUserId()` — the
 *      caller's OWN rows only. The union that includes shares is
 *      `GET /api/dashboards/visible` (`dashboardApi#visible` ->
 *      `getVisibleToUser()`, which folds in `findSharedDashboards()` under a
 *      comment naming REQ-SHARE-002). The behaviour is implemented; the path
 *      in the prose is stale, so the tests below use the real one.
 *
 *   2. REQ-SHARE-002 also names a field `effectivePermissionLevel`. No such
 *      key exists in any response: `/api/dashboards/visible` carries
 *      `source` + `isOwner`, and the resolved level is served by
 *      `GET /api/dashboard/{id}` as `permissionLevel` alongside `isOwner` and
 *      `sharedBy`. The tests assert on the keys the app actually sends.
 *
 * @spec openspec/specs/dashboard-sharing/spec.md
 */

import { expect, request, test, type APIRequestContext } from '@playwright/test'
import { ensureDefaultWidgetRestriction } from '../fixtures/role-feature-permissions'

/*
 * THE WIDGET THE REQ-SHARE-004 TEST ADDS, AND WHY IT IS NOT `label`.
 *
 * `WidgetApiController::denyAddWidget()` has TWO independent 403 branches and
 * both return `ResponseHelper::forbidden()`, i.e. the byte-identical body
 * `{"error":"Access denied"}`:
 *
 *   1. `PermissionService::canAddWidget()` — the share-permission check,
 *      which is the ONLY thing REQ-SHARE-004 is about.
 *   2. `RoleFeaturePermissionService::isWidgetAllowed()` — the role-feature
 *      widget allow-list, which is about something else entirely.
 *
 * `tests/e2e/fixtures/role-feature-permissions.ts` installs a RESTRICTIVE
 * `default` row — `allowedWidgets: ['activity', 'recommendations']` — and ten
 * widget-adding specs call it in `beforeAll`. playwright.config.ts runs
 * `workers: 1, fullyParallel: false` against ONE instance, so that row is in
 * force here whether or not this file asks for it. `isWidgetAllowed()`
 * short-circuits for Nextcloud admins but bob is a plain provisioned account,
 * so branch 2 refused `label` outright — measured in CI on this branch, where
 * the "allowed at full" arm failed with `Access denied`.
 *
 * The dangerous half is that the view_only CONTROL arm PASSED THROUGHOUT. It
 * expects 403 and branch 2 supplied one, so the control was green while
 * proving nothing about share permissions at all.
 *
 * Two changes remove the ambiguity rather than working around it. This file
 * now installs the restriction itself in `beforeAll`, so the state is known
 * instead of inherited from whichever spec happened to run first; and it adds
 * a widget that restriction ALLOWS, so branch 2 is constant across both arms
 * and the share level is the only variable left.
 */
const ALLOWED_WIDGET = 'activity'

const ADMIN = {
	user: process.env.ADMIN_USER ?? process.env.NC_ADMIN_USER ?? 'admin',
	pass: process.env.ADMIN_PASSWORD ?? process.env.NC_ADMIN_PASS ?? 'admin',
}

/*
 * `baseURL` is a TEST-scoped Playwright option and cannot be destructured in
 * a worker-scoped hook. The neighbouring public-share specs read the same
 * environment variable the config resolves it from; so does this file.
 */
const ENV_BASE_URL = (process.env.BASE_URL ?? process.env.NC_BASE_URL ?? '').replace(/\/$/, '')

const SETTINGS = '/index.php/apps/launchpad/api/admin/settings'
const DASHBOARDS = '/index.php/apps/launchpad/api/dashboard'
const VISIBLE = '/index.php/apps/launchpad/api/dashboards/visible'
const sharesUrl = (id: number) => `/index.php/apps/launchpad/api/dashboard/${id}/shares`
const dashboardUrl = (id: number) => `/index.php/apps/launchpad/api/dashboard/${id}`
const shareesUrl = (query: string) => `/index.php/apps/launchpad/api/sharees?query=${encodeURIComponent(query)}`

function basic(user: string, pass: string): string {
	return `Basic ${Buffer.from(`${user}:${pass}`).toString('base64')}`
}

/*
 * An API context that is unambiguously ONE named user.
 *
 * An EXPLICIT EMPTY JAR is load-bearing here and is not defensive tidying:
 * playwright.config.ts sets a top-level `use.storageState` (the admin session
 * from global-setup), and a context that inherits it is served as admin
 * whatever Authorization header it also sends — a valid session cookie
 * outranks a later `Authorization` header, so a per-request header is not an
 * identity switch. Measured on this repo in run 31389746411, where a
 * non-admin's create answered 201 with `"createdBy":"admin"` — the response
 * naming the user the request had actually been served as. Every "bob is
 * refused" assertion below would otherwise be an assertion about an
 * administrator.
 *
 * The jar is spelled `{ cookies: [], origins: [] }` rather than
 * `storageState: undefined`. Those are not equivalent: option merging treats
 * an explicit `undefined` as "not supplied", which is exactly the case that
 * falls back to the project default — i.e. the very inheritance this is meant
 * to prevent. The empty-jar literal cannot be read that way. (The same fix,
 * in the same form, closed an identical false green in pipelinq, where a
 * context created to be anonymous read back `ocs.data.id === "admin"`.)
 */
async function apiAs(creds: { user: string, pass: string }): Promise<APIRequestContext> {
	return request.newContext({
		baseURL: ENV_BASE_URL,
		storageState: { cookies: [], origins: [] },
		extraHTTPHeaders: {
			'OCS-APIRequest': 'true',
			Authorization: basic(creds.user, creds.pass),
		},
	})
}

/** Provision a throwaway account through the OCS provisioning API. */
async function makeUser(label: string): Promise<{ user: string, pass: string }> {
	const user = `e2e-share-${label}-${Date.now()}-${Math.floor(Math.random() * 10_000)}`
	// Nextcloud silently rejects passwords under the policy minimum, so this
	// is comfortably over it and carries all four character classes.
	const pass = `Share-${Math.random().toString(36).slice(2)}A1!`
	const admin = await apiAs(ADMIN)
	const res = await admin.post('/ocs/v1.php/cloud/users', { form: { userid: user, password: pass } })
	expect(res.ok(), `provisioning ${user} failed: ${res.status()} ${await res.text()}`).toBeTruthy()
	await admin.dispose()
	return { user, pass }
}

async function deleteUser(user: string): Promise<void> {
	const admin = await apiAs(ADMIN)
	await admin.delete(`/ocs/v1.php/cloud/users/${encodeURIComponent(user)}`)
	await admin.dispose()
}

async function makeGroup(label: string): Promise<string> {
	const gid = `e2e-share-grp-${label}-${Date.now()}-${Math.floor(Math.random() * 10_000)}`
	const admin = await apiAs(ADMIN)
	const res = await admin.post('/ocs/v1.php/cloud/groups', { form: { groupid: gid } })
	expect(res.ok(), `creating group ${gid} failed: ${res.status()} ${await res.text()}`).toBeTruthy()
	await admin.dispose()
	return gid
}

async function deleteGroup(gid: string): Promise<void> {
	const admin = await apiAs(ADMIN)
	await admin.delete(`/ocs/v1.php/cloud/groups/${encodeURIComponent(gid)}`)
	await admin.dispose()
}

async function addToGroup(user: string, gid: string): Promise<void> {
	const admin = await apiAs(ADMIN)
	const res = await admin.post(`/ocs/v1.php/cloud/users/${encodeURIComponent(user)}/groups`, {
		form: { groupid: gid },
	})
	expect(res.ok(), `adding ${user} to ${gid} failed: ${res.status()} ${await res.text()}`).toBeTruthy()
	await admin.dispose()
}

/**
 * Create a dashboard owned by the caller; return both ids.
 *
 * The share routes are declared `int $id`, so they take the numeric id — the
 * public-share routes next door take the uuid, and the two are not
 * interchangeable.
 */
async function createDashboard(api: APIRequestContext, label: string): Promise<{ id: number, uuid: string }> {
	const res = await api.post(DASHBOARDS, { data: { name: `E2E Share ${label} ${Date.now()}` } })
	expect(res.status(), await res.text()).toBeLessThan(300)
	const body = await res.json()
	const dash = body.dashboard ?? body.data?.dashboard ?? body
	expect(dash?.id, `no numeric id in create response: ${JSON.stringify(body)}`).toBeTruthy()
	// Registered BEFORE the caller can throw, so a failing test still gets
	// its dashboard cleaned up — see the teardown note on `createdDashboards`.
	createdDashboards.push(Number(dash.id))
	return { id: Number(dash.id), uuid: dash.uuid }
}

/** The share rows the owner can see for a dashboard. */
async function listShares(api: APIRequestContext, id: number): Promise<Array<Record<string, unknown>>> {
	const res = await api.get(sharesUrl(id))
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return Array.isArray(body) ? body : (body.data ?? body.items ?? [])
}

/** The dashboards visible to the caller, shares folded in. */
async function listVisible(api: APIRequestContext): Promise<Array<Record<string, unknown>>> {
	const res = await api.get(VISIBLE)
	expect(res.status(), await res.text()).toBe(200)
	const body = await res.json()
	return body.items ?? body.data?.items ?? (Array.isArray(body) ? body : [])
}

/*
 * A fresh CI instance ships `allow_user_dashboards` OFF, so `POST
 * /api/dashboard` answers 403 `personal_dashboards_disabled` (REQ-ASET-003).
 * Enabling it is setup, not part of what anything here asserts — the prior
 * value is read first and restored afterwards so the instance is left as it
 * was found for whatever runs next in the same serial job.
 */
let priorAllowUserDash = true

/*
 * Provisioned once for the whole file: three accounts and a group. Creating
 * them per-test would multiply OCS round-trips for no isolation gain, since
 * playwright.config.ts runs `workers: 1, fullyParallel: false` and every test
 * below creates its OWN dashboard — which is the state that actually needs to
 * be isolated.
 */
/*
 * Every dashboard this file creates, so `afterAll` can remove them.
 *
 * WHY THIS EXISTS. Each test below creates its own dashboard — that is the
 * isolation that matters, since `workers: 1, fullyParallel: false` means the
 * only thing to isolate is state, not concurrency. But creating ten and
 * deleting none leaves them in a register every other spec in the job reads,
 * and `POST /api/dashboard` also moves the caller's ACTIVE dashboard. That is
 * not hypothetical here: `tile-quick-search.spec.ts` failed on this branch
 * precisely because a dashboard it did not create was active when its tests
 * ran, and this file runs before it (`ci/…` sorts ahead of `tile-…`).
 *
 * Ids are recorded inside `createDashboard()` at the moment of creation, not
 * by the caller afterwards, so a test that throws mid-way still has its
 * dashboard removed.
 */
const createdDashboards: number[] = []

let bob: { user: string, pass: string }
let carol: { user: string, pass: string }
let dave: { user: string, pass: string }
let salesGroup: string

test.beforeAll(async () => {
	const admin = await apiAs(ADMIN)
	const before = await admin.get(SETTINGS)
	expect(before.status(), await before.text()).toBe(200)
	priorAllowUserDash = (await before.json()).allowUserDashboards === true
	const enable = await admin.put(SETTINGS, { data: { allowUserDash: true } })
	expect(enable.status(), await enable.text()).toBeLessThan(300)
	await admin.dispose()

	// Pin the role-feature widget allow-list instead of inheriting whatever
	// the previously-run specs left behind — see the ALLOWED_WIDGET note.
	await ensureDefaultWidgetRestriction()

	bob = await makeUser('bob')
	carol = await makeUser('carol')
	dave = await makeUser('dave')
	salesGroup = await makeGroup('sales')
	await addToGroup(carol.user, salesGroup)
})

test.afterAll(async () => {
	/*
	 * Dashboards first, and tolerantly: a delete that 404s because a test
	 * already removed the row must not stop the accounts below from being
	 * cleaned up. `afterAll` runs on the failure path too, which is the path
	 * that matters — a red run that leaves rows behind poisons the next one.
	 */
	const cleanup = await apiAs(ADMIN)
	for (const id of createdDashboards) {
		await cleanup.delete(dashboardUrl(id)).catch(() => undefined)
	}
	await cleanup.dispose()

	await Promise.all([
		deleteUser(bob.user),
		deleteUser(carol.user),
		deleteUser(dave.user),
	])
	await deleteGroup(salesGroup)

	if (priorAllowUserDash === false) {
		const admin = await apiAs(ADMIN)
		await admin.put(SETTINGS, { data: { allowUserDash: false } })
		await admin.dispose()
	}
})

test.describe('REQ-SHARE-001 owner-only share management', () => {
	// @e2e dashboard-sharing::owner-adds-a-share
	test('the owner adds a share and it comes back in the share list with the level she set', async () => {
		const owner = await apiAs(ADMIN)
		const dash = await createDashboard(owner, 'add')

		// CONTROL — a new dashboard has no shares. Without this, "the list
		// contains a bob row" could be true of a list that was never empty.
		expect(
			await listShares(owner, dash.id),
			'CONTROL: a freshly created dashboard must start with no shares',
		).toHaveLength(0)

		const created = await owner.post(sharesUrl(dash.id), {
			data: { shareType: 'user', shareWith: bob.user, permissionLevel: 'view_only' },
		})
		expect(created.status(), await created.text()).toBeLessThan(300)

		const shares = await listShares(owner, dash.id)
		expect(shares, 'exactly the one share just added must be listed').toHaveLength(1)
		expect(shares[0].shareWith).toBe(bob.user)
		expect(shares[0].shareType).toBe('user')
		expect(shares[0].permissionLevel).toBe('view_only')

		await owner.dispose()
	})

	// @e2e dashboard-sharing::recipient-cannot-manage-shares
	test('a recipient at full level still cannot add a share of their own', async () => {
		const owner = await apiAs(ADMIN)
		const asBob = await apiAs(bob)
		const dash = await createDashboard(owner, 'recipient-mgmt')

		// bob is given the MOST permissive level the model has, so a refusal
		// below is about share MANAGEMENT being owner-only and not about bob
		// lacking permission generally.
		const grant = await owner.post(sharesUrl(dash.id), {
			data: { shareType: 'user', shareWith: bob.user, permissionLevel: 'full' },
		})
		expect(grant.status(), await grant.text()).toBeLessThan(300)

		// CONTROL — bob really can reach this dashboard, so the 403 below is
		// a statement about the endpoint and not about visibility.
		const canRead = await asBob.get(dashboardUrl(dash.id))
		expect(
			canRead.status(),
			'CONTROL: a full-level recipient must be able to read the dashboard, otherwise the 403 below proves nothing',
		).toBe(200)

		const attempt = await asBob.post(sharesUrl(dash.id), {
			data: { shareType: 'user', shareWith: dave.user, permissionLevel: 'view_only' },
		})
		expect(attempt.status(), await attempt.text()).toBe(403)

		// …and nothing was written.
		expect(
			await listShares(owner, dash.id),
			'a refused share attempt must not leave a row behind',
		).toHaveLength(1)

		await owner.dispose()
		await asBob.dispose()
	})

	// @e2e dashboard-sharing::updating-an-existing-share-replaces-does-not-duplicate
	test('re-sharing with the same recipient upgrades the existing row rather than adding a second', async () => {
		const owner = await apiAs(ADMIN)
		const dash = await createDashboard(owner, 'upsert')

		const first = await owner.post(sharesUrl(dash.id), {
			data: { shareType: 'user', shareWith: bob.user, permissionLevel: 'view_only' },
		})
		expect(first.status(), await first.text()).toBeLessThan(300)
		expect(await listShares(owner, dash.id)).toHaveLength(1)

		const second = await owner.post(sharesUrl(dash.id), {
			data: { shareType: 'user', shareWith: bob.user, permissionLevel: 'full' },
		})
		expect(second.status(), await second.text()).toBeLessThan(300)

		// THE assertion. A duplicate row would also leave bob at `full`, so
		// the count is what distinguishes an upsert from an insert.
		const shares = await listShares(owner, dash.id)
		expect(shares, 're-sharing the same recipient must update, not duplicate').toHaveLength(1)
		expect(shares[0].permissionLevel).toBe('full')

		await owner.dispose()
	})
})

test.describe('REQ-SHARE-002 listing dashboards visible to a user', () => {
	// @e2e dashboard-sharing::recipient-sees-a-shared-dashboard-in-their-list
	test('a user share puts the dashboard in the recipient list, tagged as not owned', async () => {
		const owner = await apiAs(ADMIN)
		const asBob = await apiAs(bob)
		const dash = await createDashboard(owner, 'visible')

		// CONTROL — before the share, bob must NOT see it. This is the half
		// that makes the positive assertion mean anything: a union endpoint
		// that returned every dashboard on the instance would pass the
		// positive check alone.
		const beforeIds = (await listVisible(asBob)).map(d => Number(d.id))
		expect(
			beforeIds,
			'CONTROL: an unshared dashboard must not appear in another user\'s visible list',
		).not.toContain(dash.id)

		const grant = await owner.post(sharesUrl(dash.id), {
			data: { shareType: 'user', shareWith: bob.user, permissionLevel: 'add_only' },
		})
		expect(grant.status(), await grant.text()).toBeLessThan(300)

		const entry = (await listVisible(asBob)).find(d => Number(d.id) === dash.id)
		expect(entry, 'the shared dashboard must appear in the recipient\'s visible list').toBeTruthy()
		expect(entry!.isOwner, 'the recipient is not the owner and the payload must say so').toBe(false)

		// The resolved level is served by GET /api/dashboard/{id}, not by the
		// list — see the header note about `effectivePermissionLevel`.
		const detail = await asBob.get(dashboardUrl(dash.id))
		expect(detail.status(), await detail.text()).toBe(200)
		const detailBody = await detail.json()
		expect(detailBody.permissionLevel).toBe('add_only')
		expect(detailBody.isOwner).toBe(false)
		expect(detailBody.sharedBy).toBe(ADMIN.user)

		await owner.dispose()
		await asBob.dispose()
	})

	// @e2e dashboard-sharing::group-share-grants-visibility-to-all-group-members
	test('a group share reaches a member who was never named individually', async () => {
		const owner = await apiAs(ADMIN)
		const asCarol = await apiAs(carol)
		const asDave = await apiAs(dave)
		const dash = await createDashboard(owner, 'groupshare')

		const grant = await owner.post(sharesUrl(dash.id), {
			data: { shareType: 'group', shareWith: salesGroup, permissionLevel: 'view_only' },
		})
		expect(grant.status(), await grant.text()).toBeLessThan(300)

		// carol is in the group and was never named.
		const carolEntry = (await listVisible(asCarol)).find(d => Number(d.id) === dash.id)
		expect(carolEntry, 'a group member must see a dashboard shared with their group').toBeTruthy()

		// CONTROL — dave is NOT in the group. Without this, "carol can see it"
		// would also hold if group shares granted visibility to everyone.
		const daveIds = (await listVisible(asDave)).map(d => Number(d.id))
		expect(
			daveIds,
			'CONTROL: a non-member must not gain visibility from a group share',
		).not.toContain(dash.id)

		const detail = await asCarol.get(dashboardUrl(dash.id))
		expect(detail.status(), await detail.text()).toBe(200)
		expect((await detail.json()).permissionLevel).toBe('view_only')

		await owner.dispose()
		await asCarol.dispose()
		await asDave.dispose()
	})

	// @e2e dashboard-sharing::most-permissive-level-wins-when-a-user-matches-multiple-shares
	test('a user matched by both a personal and a group share gets the more permissive of the two', async () => {
		const owner = await apiAs(ADMIN)
		const asCarol = await apiAs(carol)
		const dash = await createDashboard(owner, 'most-permissive')

		// carol personally at view_only …
		const direct = await owner.post(sharesUrl(dash.id), {
			data: { shareType: 'user', shareWith: carol.user, permissionLevel: 'view_only' },
		})
		expect(direct.status(), await direct.text()).toBeLessThan(300)

		// CONTROL — with only the view_only share in place she must be
		// view_only. This is what proves the final `full` came from the group
		// share and not from the resolver defaulting high.
		const beforeDetail = await asCarol.get(dashboardUrl(dash.id))
		expect(beforeDetail.status(), await beforeDetail.text()).toBe(200)
		expect(
			(await beforeDetail.json()).permissionLevel,
			'CONTROL: a lone view_only share must resolve to view_only',
		).toBe('view_only')

		// … and her group at full.
		const viaGroup = await owner.post(sharesUrl(dash.id), {
			data: { shareType: 'group', shareWith: salesGroup, permissionLevel: 'full' },
		})
		expect(viaGroup.status(), await viaGroup.text()).toBeLessThan(300)

		const afterDetail = await asCarol.get(dashboardUrl(dash.id))
		expect(afterDetail.status(), await afterDetail.text()).toBe(200)
		expect(
			(await afterDetail.json()).permissionLevel,
			'the more permissive of two matching shares must win',
		).toBe('full')

		await owner.dispose()
		await asCarol.dispose()
	})
})

test.describe('REQ-SHARE-004 per-share permission overrides the dashboard default', () => {
	// @e2e dashboard-sharing::owner-has-viewonly-recipient-has-full
	test('a full-level recipient may add a widget to a dashboard whose own level is view_only', async () => {
		const owner = await apiAs(ADMIN)
		const asBob = await apiAs(bob)
		const dash = await createDashboard(owner, 'override')

		// Put the dashboard's OWN level at view_only, so that anything bob is
		// allowed to do has to come from his share rather than from the
		// dashboard's default.
		const setLevel = await owner.put(dashboardUrl(dash.id), { data: { permissionLevel: 'view_only' } })
		expect(setLevel.status(), await setLevel.text()).toBeLessThan(300)

		const viewOnly = await owner.post(sharesUrl(dash.id), {
			data: { shareType: 'user', shareWith: bob.user, permissionLevel: 'view_only' },
		})
		expect(viewOnly.status(), await viewOnly.text()).toBeLessThan(300)

		/*
		 * CONTROL, and the half that makes this test able to fail in the
		 * right direction: at view_only the add MUST be refused. If it were
		 * allowed here, the "allowed at full" assertion below would be
		 * vacuous — it would pass on a build with no permission check at all.
		 */
		const refused = await asBob.post(`/index.php/apps/launchpad/api/dashboard/${dash.id}/widgets`, {
			data: { widgetId: ALLOWED_WIDGET, gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 2 },
		})
		expect(
			refused.status(),
			'CONTROL: a view_only recipient must be refused the widget add. This refusal is '
			+ 'attributable to the share check only because the SAME widget id succeeds for the '
			+ 'same user at `full` below — that arm is what rules out the role-feature allow-list '
			+ `as the cause. Body: ${await refused.text()}`,
		).toBe(403)

		// Upgrade the share only. Nothing else about the dashboard changes.
		const upgrade = await owner.post(sharesUrl(dash.id), {
			data: { shareType: 'user', shareWith: bob.user, permissionLevel: 'full' },
		})
		expect(upgrade.status(), await upgrade.text()).toBeLessThan(300)

		/*
		 * SPLITTING PROBE. The share row must actually read `full` before
		 * the add is attempted, so a failure names its own cause instead of
		 * leaving the next reader to re-derive it: a row still reading
		 * `view_only` means the upgrade POST did not take, whereas a row
		 * reading `full` under a refused add means the per-share level is
		 * being ignored downstream.
		 */
		const rowAfterUpgrade = (await listShares(owner, dash.id))
			.find(s => String(s.shareWith) === bob.user)
		expect(
			rowAfterUpgrade?.permissionLevel,
			'SPLITTING PROBE: the upgrade POST must leave bob\'s share row at `full`',
		).toBe('full')

		const allowed = await asBob.post(`/index.php/apps/launchpad/api/dashboard/${dash.id}/widgets`, {
			data: { widgetId: ALLOWED_WIDGET, gridX: 0, gridY: 0, gridWidth: 4, gridHeight: 2 },
		})
		expect(
			allowed.status(),
			`a full-level share must override the dashboard's own view_only level: ${await allowed.text()}`,
		).toBeLessThan(300)

		await owner.dispose()
		await asBob.dispose()
	})
})

test.describe('REQ-SHARE-006 sharee autocomplete', () => {
	// @e2e dashboard-sharing::search-returns-matching-users-and-groups
	test('the sharee search returns matching users and groups, and omits non-matches', async () => {
		const owner = await apiAs(ADMIN)

		// Both provisioned accounts and the group share the `e2e-share-`
		// prefix, so one query is expected to match across both arrays.
		const res = await owner.get(shareesUrl('e2e-share-'))
		expect(res.status(), await res.text()).toBe(200)
		const body = await res.json()
		const users: string[] = (body.users ?? body.data?.users ?? []).map(
			(u: Record<string, unknown>) => String(u.id ?? u.shareWith ?? u.label ?? ''),
		)
		const groups: string[] = (body.groups ?? body.data?.groups ?? []).map(
			(g: Record<string, unknown>) => String(g.id ?? g.shareWith ?? g.label ?? ''),
		)

		expect(users, 'a provisioned user matching the query must be offered').toContain(bob.user)
		expect(groups, 'a group matching the query must be offered').toContain(salesGroup)

		/*
		 * CONTROL — a query that matches nothing must come back empty. An
		 * autocomplete that ignored its query and returned every account
		 * would satisfy both assertions above.
		 */
		const noMatch = await owner.get(shareesUrl(`zzz-no-such-principal-${Date.now()}`))
		expect(noMatch.status(), await noMatch.text()).toBe(200)
		const noMatchBody = await noMatch.json()
		expect(
			[...(noMatchBody.users ?? []), ...(noMatchBody.groups ?? [])],
			'CONTROL: a query matching nothing must return nothing — otherwise the search is not filtering',
		).toHaveLength(0)

		await owner.dispose()
	})
})

test.describe('REQ-SHARE-009 bulk replace', () => {
	// @e2e dashboard-sharing::replace-adds-upgrades-and-removes-in-one-call
	test('one PUT upgrades one recipient, adds another, and drops the rest', async () => {
		const owner = await apiAs(ADMIN)
		const dash = await createDashboard(owner, 'replace')

		for (const payload of [
			{ shareType: 'user', shareWith: bob.user, permissionLevel: 'view_only' },
			{ shareType: 'user', shareWith: carol.user, permissionLevel: 'view_only' },
			{ shareType: 'group', shareWith: salesGroup, permissionLevel: 'view_only' },
		]) {
			const res = await owner.post(sharesUrl(dash.id), { data: payload })
			expect(res.status(), await res.text()).toBeLessThan(300)
		}
		expect(await listShares(owner, dash.id), 'three shares must exist before the replace').toHaveLength(3)

		const replaced = await owner.put(sharesUrl(dash.id), {
			data: {
				shares: [
					{ shareType: 'user', shareWith: bob.user, permissionLevel: 'full' },
					{ shareType: 'user', shareWith: dave.user, permissionLevel: 'view_only' },
				],
			},
		})
		expect(replaced.status(), await replaced.text()).toBeLessThan(300)

		const after = await listShares(owner, dash.id)
		expect(after, 'the replace must leave exactly the two rows it was given').toHaveLength(2)
		const byRecipient = Object.fromEntries(after.map(s => [String(s.shareWith), String(s.permissionLevel)]))
		expect(byRecipient[bob.user], 'bob must be upgraded in place').toBe('full')
		expect(byRecipient[dave.user], 'dave must be added').toBe('view_only')
		expect(Object.keys(byRecipient), 'carol must be removed').not.toContain(carol.user)
		expect(Object.keys(byRecipient), 'the group share must be removed').not.toContain(salesGroup)

		await owner.dispose()
	})

	/*
	 * The spec's scenario has two clauses: "no rows MUST change" and "no
	 * notifications MUST be published". Only the first is observable from a
	 * browser — a notification is a server-side dispatch to a recipient's
	 * queue, which is why REQ-SHARE-008 carries its own `@e2e exclude`. This
	 * test asserts the row clause, exactly and completely.
	 */
	// @e2e dashboard-sharing::idempotent-re-put-publishes-nothing
	test('replaying the same PUT changes no rows', async () => {
		const owner = await apiAs(ADMIN)
		const dash = await createDashboard(owner, 'idempotent')

		const payload = {
			shares: [
				{ shareType: 'user', shareWith: bob.user, permissionLevel: 'full' },
				{ shareType: 'user', shareWith: dave.user, permissionLevel: 'view_only' },
			],
		}

		const first = await owner.put(sharesUrl(dash.id), { data: payload })
		expect(first.status(), await first.text()).toBeLessThan(300)
		const afterFirst = await listShares(owner, dash.id)
		expect(afterFirst).toHaveLength(2)

		const second = await owner.put(sharesUrl(dash.id), { data: payload })
		expect(second.status(), await second.text()).toBeLessThan(300)
		const afterSecond = await listShares(owner, dash.id)

		// Compare the meaningful tuple rather than the raw rows: an id or a
		// createdAt that moved is exactly the "row changed" this asserts
		// against, so both are included.
		const shape = (rows: Array<Record<string, unknown>>) => rows
			.map(r => `${r.shareType}:${r.shareWith}:${r.permissionLevel}:${r.id}:${r.createdAt}`)
			.sort()
		expect(
			shape(afterSecond),
			'an identical re-PUT must leave every row byte-identical, ids and timestamps included',
		).toEqual(shape(afterFirst))

		await owner.dispose()
	})

	// @e2e dashboard-sharing::non-owner-is-denied
	test('a non-owner cannot bulk-replace the share list, and nothing moves when they try', async () => {
		const owner = await apiAs(ADMIN)
		const asBob = await apiAs(bob)
		const dash = await createDashboard(owner, 'replace-denied')

		const grant = await owner.post(sharesUrl(dash.id), {
			data: { shareType: 'user', shareWith: bob.user, permissionLevel: 'full' },
		})
		expect(grant.status(), await grant.text()).toBeLessThan(300)
		const before = await listShares(owner, dash.id)

		const attempt = await asBob.put(sharesUrl(dash.id), {
			data: { shares: [{ shareType: 'user', shareWith: dave.user, permissionLevel: 'full' }] },
		})
		expect(attempt.status(), await attempt.text()).toBe(403)

		const after = await listShares(owner, dash.id)
		expect(
			after.map(s => `${s.shareWith}:${s.permissionLevel}`).sort(),
			'a refused replace must modify no rows',
		).toEqual(before.map(s => `${s.shareWith}:${s.permissionLevel}`).sort())

		await owner.dispose()
		await asBob.dispose()
	})
})
